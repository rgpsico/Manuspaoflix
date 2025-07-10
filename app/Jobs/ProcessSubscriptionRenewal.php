<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use App\Models\Payment;
use App\Services\AsaasService;
use Carbon\Carbon;

class ProcessSubscriptionRenewal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Subscription $subscription;

    /**
     * Create a new job instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing subscription renewal', [
                'subscription_id' => $this->subscription->id,
                'user_id' => $this->subscription->user_id
            ]);

            // Check if subscription is still active
            if (!$this->subscription->isActive()) {
                Log::warning('Subscription is not active, skipping renewal', [
                    'subscription_id' => $this->subscription->id,
                    'status' => $this->subscription->status
                ]);
                return;
            }

            // Check if there's already a pending payment for this period
            $existingPayment = $this->subscription->payments()
                ->where('status', 'pending')
                ->where('due_date', '>=', now())
                ->first();

            if ($existingPayment) {
                Log::info('Pending payment already exists, skipping renewal', [
                    'subscription_id' => $this->subscription->id,
                    'payment_id' => $existingPayment->id
                ]);
                return;
            }

            // Calculate next billing date
            $nextBillingDate = $this->calculateNextBillingDate();

            // Create payment record
            $payment = $this->createPaymentRecord($nextBillingDate);

            // Create payment in Asaas (if integration is enabled)
            if (config('services.asaas.enabled', false)) {
                $this->createAsaasPayment($payment);
            }

            // Update subscription next billing date
            $this->subscription->update([
                'next_billing_date' => $nextBillingDate,
                'updated_at' => now()
            ]);

            // Dispatch notification
            SendPaymentNotification::dispatch($payment, 'payment_created');

            // Schedule delivery generation for the next period
            GenerateDeliveries::dispatch(now(), 14); // Generate deliveries for next 2 weeks

            Log::info('Subscription renewal processed successfully', [
                'subscription_id' => $this->subscription->id,
                'payment_id' => $payment->id,
                'next_billing_date' => $nextBillingDate->format('Y-m-d')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process subscription renewal', [
                'subscription_id' => $this->subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark subscription as needing attention
            $this->subscription->markAsNeedsAttention('Falha na renovação automática');

            throw $e;
        }
    }

    /**
     * Calculate next billing date based on plan frequency.
     */
    private function calculateNextBillingDate(): Carbon
    {
        $plan = $this->subscription->plan;
        $lastBillingDate = $this->subscription->next_billing_date ?? $this->subscription->created_at;

        switch ($plan->frequency) {
            case 'daily':
                return $lastBillingDate->copy()->addDay();

            case 'alternate_days':
                return $lastBillingDate->copy()->addDays(2);

            case 'weekly':
                return $lastBillingDate->copy()->addWeek();

            case 'monthly':
                return $lastBillingDate->copy()->addMonth();

            case 'weekends':
                // Bill weekly for weekend deliveries
                return $lastBillingDate->copy()->addWeek();

            default:
                // Default to monthly billing
                return $lastBillingDate->copy()->addMonth();
        }
    }

    /**
     * Create payment record in database.
     */
    private function createPaymentRecord(Carbon $dueDate): Payment
    {
        return Payment::create([
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->subscription->user_id,
            'amount' => $this->subscription->plan->price,
            'status' => 'pending',
            'billing_type' => $this->subscription->preferred_payment_method ?? 'pix',
            'due_date' => $dueDate,
            'description' => "Assinatura {$this->subscription->plan->name} - " . $dueDate->format('m/Y'),
        ]);
    }

    /**
     * Create payment in Asaas platform.
     */
    private function createAsaasPayment(Payment $payment): void
    {
        try {
            $asaasService = new AsaasService();
            
            $paymentData = [
                'customer' => $this->subscription->user->asaas_customer_id,
                'billingType' => strtoupper($payment->billing_type),
                'value' => $payment->amount,
                'dueDate' => $payment->due_date->format('Y-m-d'),
                'description' => $payment->description,
                'externalReference' => "payment_{$payment->id}",
            ];

            $asaasPayment = $asaasService->createPayment($paymentData);

            // Update payment with Asaas data
            $payment->update([
                'asaas_payment_id' => $asaasPayment['id'],
                'invoice_url' => $asaasPayment['invoiceUrl'] ?? null,
                'bank_slip_url' => $asaasPayment['bankSlipUrl'] ?? null,
            ]);

            // Get PIX QR Code if payment method is PIX
            if ($payment->billing_type === 'pix') {
                $pixData = $asaasService->getPixQrCode($asaasPayment['id']);
                $payment->update([
                    'pix_qr_code' => $pixData['qrCode'] ?? null,
                    'pix_copy_paste' => $pixData['payload'] ?? null,
                ]);
            }

            Log::info('Asaas payment created successfully', [
                'payment_id' => $payment->id,
                'asaas_payment_id' => $asaasPayment['id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create Asaas payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            // Don't throw exception here, payment record is still valid
            // Mark payment as needing manual processing
            $payment->update(['notes' => 'Falha na integração Asaas - processamento manual necessário']);
        }
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
        return [300, 900, 1800]; // 5 minutes, 15 minutes, 30 minutes
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }
}
