<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Payment;
use App\Models\User;

class SendPaymentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Payment $payment;
    private string $notificationType;

    /**
     * Create a new job instance.
     */
    public function __construct(Payment $payment, string $notificationType)
    {
        $this->payment = $payment;
        $this->notificationType = $notificationType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = $this->payment->user;
            
            switch ($this->notificationType) {
                case 'payment_created':
                    $this->sendPaymentCreatedNotification($user);
                    break;
                    
                case 'payment_confirmed':
                    $this->sendPaymentConfirmedNotification($user);
                    break;
                    
                case 'payment_overdue':
                    $this->sendPaymentOverdueNotification($user);
                    break;
                    
                case 'payment_failed':
                    $this->sendPaymentFailedNotification($user);
                    break;
                    
                default:
                    Log::warning('Unknown payment notification type', [
                        'type' => $this->notificationType,
                        'payment_id' => $this->payment->id
                    ]);
            }
            
            Log::info('Payment notification sent', [
                'payment_id' => $this->payment->id,
                'user_id' => $user->id,
                'type' => $this->notificationType
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send payment notification', [
                'payment_id' => $this->payment->id,
                'type' => $this->notificationType,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Send payment created notification.
     */
    private function sendPaymentCreatedNotification(User $user): void
    {
        // In a real application, you would send an email here
        // For now, we'll just log the notification
        
        $message = "Nova cobrança gerada para sua assinatura de pães.";
        $details = [
            'amount' => $this->payment->formatted_amount,
            'due_date' => $this->payment->formatted_due_date,
            'billing_type' => $this->payment->billing_type_label,
            'subscription' => $this->payment->subscription->plan->name,
        ];
        
        $this->logNotification($user, $message, $details);
        
        // Example of how you would send an email:
        // Mail::to($user->email)->send(new PaymentCreatedMail($this->payment));
    }

    /**
     * Send payment confirmed notification.
     */
    private function sendPaymentConfirmedNotification(User $user): void
    {
        $message = "Pagamento confirmado! Sua assinatura está ativa.";
        $details = [
            'amount' => $this->payment->formatted_amount,
            'payment_date' => $this->payment->formatted_payment_date,
            'subscription' => $this->payment->subscription->plan->name,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Send payment overdue notification.
     */
    private function sendPaymentOverdueNotification(User $user): void
    {
        $message = "Pagamento em atraso. Sua assinatura pode ser suspensa.";
        $details = [
            'amount' => $this->payment->formatted_amount,
            'due_date' => $this->payment->formatted_due_date,
            'days_overdue' => $this->payment->days_overdue,
            'subscription' => $this->payment->subscription->plan->name,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Send payment failed notification.
     */
    private function sendPaymentFailedNotification(User $user): void
    {
        $message = "Falha no processamento do pagamento. Verifique seus dados.";
        $details = [
            'amount' => $this->payment->formatted_amount,
            'due_date' => $this->payment->formatted_due_date,
            'subscription' => $this->payment->subscription->plan->name,
        ];
        
        $this->logNotification($user, $message, $details);
    }

    /**
     * Log notification for debugging purposes.
     */
    private function logNotification(User $user, string $message, array $details): void
    {
        Log::info('Payment notification details', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_name' => $user->name,
            'message' => $message,
            'details' => $details,
            'payment_id' => $this->payment->id,
            'notification_type' => $this->notificationType
        ]);
        
        // Here you could also save to a notifications table
        // or send push notifications, SMS, etc.
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
        return [30, 120, 300]; // 30 seconds, 2 minutes, 5 minutes
    }
}
