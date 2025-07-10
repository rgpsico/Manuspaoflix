<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use App\Models\Delivery;
use Carbon\Carbon;

class GenerateDeliveries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Carbon $targetDate;
    private int $daysAhead;

    /**
     * Create a new job instance.
     */
    public function __construct(?Carbon $targetDate = null, int $daysAhead = 7)
    {
        $this->targetDate = $targetDate ?? now();
        $this->daysAhead = $daysAhead;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting delivery generation', [
            'target_date' => $this->targetDate->format('Y-m-d'),
            'days_ahead' => $this->daysAhead
        ]);

        $generated = 0;
        $errors = 0;

        // Get all active subscriptions
        $subscriptions = Subscription::active()
            ->with(['plan', 'address', 'user'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $deliveriesGenerated = $this->generateDeliveriesForSubscription($subscription);
                $generated += $deliveriesGenerated;
            } catch (\Exception $e) {
                $errors++;
                Log::error('Failed to generate deliveries for subscription', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Delivery generation completed', [
            'total_subscriptions' => $subscriptions->count(),
            'deliveries_generated' => $generated,
            'errors' => $errors
        ]);
    }

    /**
     * Generate deliveries for a specific subscription.
     */
    private function generateDeliveriesForSubscription(Subscription $subscription): int
    {
        $generated = 0;
        $plan = $subscription->plan;
        
        // Calculate date range for delivery generation
        $startDate = $this->targetDate->copy();
        $endDate = $this->targetDate->copy()->addDays($this->daysAhead);

        // Get delivery dates based on plan frequency
        $deliveryDates = $this->getDeliveryDates($plan, $startDate, $endDate);

        foreach ($deliveryDates as $deliveryDate) {
            // Check if delivery already exists for this date
            $existingDelivery = Delivery::where('subscription_id', $subscription->id)
                ->whereDate('scheduled_date', $deliveryDate->format('Y-m-d'))
                ->first();

            if ($existingDelivery) {
                continue; // Skip if delivery already exists
            }

            // Create delivery
            $delivery = Delivery::create([
                'subscription_id' => $subscription->id,
                'status' => 'pending',
                'scheduled_date' => $deliveryDate,
                'scheduled_time' => $this->getDeliveryTime($plan, $deliveryDate),
            ]);

            $generated++;

            Log::debug('Delivery generated', [
                'delivery_id' => $delivery->id,
                'subscription_id' => $subscription->id,
                'scheduled_date' => $deliveryDate->format('Y-m-d H:i:s')
            ]);
        }

        return $generated;
    }

    /**
     * Get delivery dates based on plan frequency.
     */
    private function getDeliveryDates($plan, Carbon $startDate, Carbon $endDate): array
    {
        $dates = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            if ($this->shouldDeliverOnDate($plan, $current)) {
                $dates[] = $current->copy();
            }
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Check if delivery should happen on a specific date based on plan frequency.
     */
    private function shouldDeliverOnDate($plan, Carbon $date): bool
    {
        switch ($plan->frequency) {
            case 'daily':
                // Deliver every day except Sundays
                return $date->dayOfWeek !== Carbon::SUNDAY;

            case 'alternate_days':
                // Deliver every other day (Monday, Wednesday, Friday)
                return in_array($date->dayOfWeek, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::FRIDAY]);

            case 'weekends':
                // Deliver on weekends (Saturday and Sunday)
                return in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY]);

            case 'weekly':
                // Deliver once a week (default: Tuesday)
                $deliveryDays = $plan->delivery_days ?? [Carbon::TUESDAY];
                return in_array($date->dayOfWeek, $deliveryDays);

            case 'monthly':
                // Deliver on the same day of the month
                $deliveryDay = $plan->delivery_days[0] ?? 1; // Default to 1st day of month
                return $date->day === $deliveryDay;

            default:
                return false;
        }
    }

    /**
     * Get delivery time based on plan and date.
     */
    private function getDeliveryTime($plan, Carbon $date): string
    {
        // Default delivery time ranges by day of week
        $timeRanges = [
            Carbon::MONDAY => ['07:00', '10:00'],
            Carbon::TUESDAY => ['07:00', '10:00'],
            Carbon::WEDNESDAY => ['07:00', '10:00'],
            Carbon::THURSDAY => ['07:00', '10:00'],
            Carbon::FRIDAY => ['07:00', '10:00'],
            Carbon::SATURDAY => ['08:00', '11:00'],
            Carbon::SUNDAY => ['08:00', '11:00'],
        ];

        $range = $timeRanges[$date->dayOfWeek] ?? ['07:00', '10:00'];
        
        // Return a random time within the range
        $startTime = Carbon::createFromTimeString($range[0]);
        $endTime = Carbon::createFromTimeString($range[1]);
        
        $randomMinutes = rand(0, $endTime->diffInMinutes($startTime));
        
        return $startTime->addMinutes($randomMinutes)->format('H:i:s');
    }

    /**
     * Get the number of retries for the job.
     */
    public function retries(): int
    {
        return 3;
    }

    /**
     * Get the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }
}
