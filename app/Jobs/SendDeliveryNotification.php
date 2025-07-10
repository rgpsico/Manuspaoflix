<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Delivery;
use App\Models\User;

class SendDeliveryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Delivery $delivery;
    private string $notificationType;

    /**
     * Create a new job instance.
     */
    public function __construct(Delivery $delivery, string $notificationType)
    {
        $this->delivery = $delivery;
        $this->notificationType = $notificationType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = $this->delivery->subscription->user;
            
            switch ($this->notificationType) {
                case 'delivery_scheduled':
                    $this->sendDeliveryScheduledNotification($user);
                    break;
                    
                case 'delivery_in_transit':
                    $this->sendDeliveryInTransitNotification($user);
                    break;
                    
                case 'delivery_completed':
                    $this->sendDeliveryCompletedNotification($user);
                    break;
                    
                case 'delivery_failed':
                    $this->sendDeliveryFailedNotification($user);
                    break;
                    
                case 'delivery_reminder':
                    $this->sendDeliveryReminderNotification($user);
                    break;
                    
                default:
                    Log::warning('Unknown delivery notification type', [
                        'type' => $this->notificationType,
                        'delivery_id' => $this->delivery->id
                    ]);
            }
            
            Log::info('Delivery notification sent', [
                'delivery_id' => $this->delivery->id,
                'user_id' => $user->id,
                'type' => $this->notificationType
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send delivery notification', [
                'delivery_id' => $this->delivery->id,
                'type' => $this->notificationType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send delivery scheduled notification.
     */
    private function sendDeliveryScheduledNotification(User $user): void
    {
        $message = "Sua entrega de pães foi agendada!";
        $details = [
            'scheduled_date' => $this->delivery->scheduled_date->format('d/m/Y'),
            'scheduled_time' => $this->delivery->scheduled_time,
            'plan_name' => $this->delivery->subscription->plan->name,
            'address' => $this->delivery->subscription->address->full_address,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Send delivery in transit notification.
     */
    private function sendDeliveryInTransitNotification(User $user): void
    {
        $message = "Seu entregador está a caminho!";
        $details = [
            'plan_name' => $this->delivery->subscription->plan->name,
            'estimated_time' => '15-30 minutos',
            'address' => $this->delivery->subscription->address->full_address,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Send delivery completed notification.
     */
    private function sendDeliveryCompletedNotification(User $user): void
    {
        $message = "Entrega realizada com sucesso!";
        $details = [
            'delivered_at' => $this->delivery->delivered_at->format('d/m/Y H:i'),
            'plan_name' => $this->delivery->subscription->plan->name,
            'delivery_notes' => $this->delivery->delivery_notes,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Send delivery failed notification.
     */
    private function sendDeliveryFailedNotification(User $user): void
    {
        $message = "Não foi possível realizar a entrega.";
        $details = [
            'scheduled_date' => $this->delivery->scheduled_date->format('d/m/Y'),
            'plan_name' => $this->delivery->subscription->plan->name,
            'delivery_notes' => $this->delivery->delivery_notes,
            'next_attempt' => 'Entraremos em contato para reagendar',
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Send delivery reminder notification.
     */
    private function sendDeliveryReminderNotification(User $user): void
    {
        $message = "Lembrete: Você tem uma entrega agendada para amanhã!";
        $details = [
            'scheduled_date' => $this->delivery->scheduled_date->format('d/m/Y'),
            'scheduled_time' => $this->delivery->scheduled_time,
            'plan_name' => $this->delivery->subscription->plan->name,
            'address' => $this->delivery->subscription->address->full_address,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Log notification for debugging purposes.
     */
    private function logNotification(User $user, string $message, array $details): void
    {
        Log::info('Delivery notification details', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'message' => $message,
            'details' => $details,
            'delivery_id' => $this->delivery->id,
            'notification_type' => $this->notificationType
        ]);
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
        return [30, 120, 300];
    }
}
