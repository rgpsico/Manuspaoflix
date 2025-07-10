<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Services\AsaasService;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Jobs\SendPaymentNotification;
use App\Jobs\ProcessSubscriptionRenewal;

class WebhookController extends Controller
{
    private AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    /**
     * Handle Asaas webhook events.
     */
    public function handleAsaasWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Asaas-Signature');

        // Verify webhook signature
        if (!$this->asaasService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Invalid webhook signature', [
                'signature' => $signature,
                'payload_length' => strlen($payload)
            ]);
            
            return response('Invalid signature', 401);
        }

        $data = json_decode($payload, true);

        if (!$data || !isset($data['event'])) {
            Log::warning('Invalid webhook payload', ['payload' => $payload]);
            return response('Invalid payload', 400);
        }

        try {
            $this->processWebhookEvent($data);
            
            Log::info('Webhook processed successfully', [
                'event' => $data['event'],
                'payment_id' => $data['payment']['id'] ?? null
            ]);
            
            return response('OK', 200);
            
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'event' => $data['event'],
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
            
            return response('Processing failed', 500);
        }
    }

    /**
     * Process webhook event based on type.
     */
    private function processWebhookEvent(array $data): void
    {
        $event = $data['event'];
        $paymentData = $data['payment'] ?? null;

        if (!$paymentData) {
            Log::warning('No payment data in webhook', ['event' => $event]);
            return;
        }

        switch ($event) {
            case 'PAYMENT_CREATED':
                $this->handlePaymentCreated($paymentData);
                break;

            case 'PAYMENT_RECEIVED':
            case 'PAYMENT_CONFIRMED':
                $this->handlePaymentConfirmed($paymentData);
                break;

            case 'PAYMENT_OVERDUE':
                $this->handlePaymentOverdue($paymentData);
                break;

            case 'PAYMENT_DELETED':
            case 'PAYMENT_REFUNDED':
                $this->handlePaymentRefunded($paymentData);
                break;

            case 'PAYMENT_RECEIVED_IN_CASH_UNDONE':
            case 'PAYMENT_CHARGEBACK_REQUESTED':
            case 'PAYMENT_CHARGEBACK_DISPUTE':
                $this->handlePaymentFailed($paymentData);
                break;

            default:
                Log::info('Unhandled webhook event', ['event' => $event]);
        }
    }

    /**
     * Handle payment created event.
     */
    private function handlePaymentCreated(array $paymentData): void
    {
        $payment = $this->findOrCreatePayment($paymentData);
        
        if ($payment) {
            $payment->update([
                'status' => 'pending',
                'asaas_payment_id' => $paymentData['id'],
                'invoice_url' => $paymentData['invoiceUrl'] ?? null,
                'bank_slip_url' => $paymentData['bankSlipUrl'] ?? null,
            ]);

            // Dispatch notification job
            SendPaymentNotification::dispatch($payment, 'payment_created');
        }
    }

    /**
     * Handle payment confirmed event.
     */
    private function handlePaymentConfirmed(array $paymentData): void
    {
        $payment = $this->findPaymentByAsaasId($paymentData['id']);
        
        if ($payment) {
            $payment->update([
                'status' => 'paid',
                'payment_date' => now(),
                'payment_method' => $paymentData['billingType'] ?? null,
            ]);

            // Reactivate subscription if it was suspended
            $subscription = $payment->subscription;
            if ($subscription && $subscription->status === 'suspended') {
                $subscription->update(['status' => 'active']);
            }

            // Dispatch notification job
            SendPaymentNotification::dispatch($payment, 'payment_confirmed');

            // Schedule next renewal if this is a recurring subscription
            if ($subscription && $subscription->isActive()) {
                ProcessSubscriptionRenewal::dispatch($subscription)
                    ->delay(now()->addDays(1)); // Process renewal tomorrow
            }
        }
    }

    /**
     * Handle payment overdue event.
     */
    private function handlePaymentOverdue(array $paymentData): void
    {
        $payment = $this->findPaymentByAsaasId($paymentData['id']);
        
        if ($payment) {
            $payment->update(['status' => 'overdue']);

            // Suspend subscription after grace period
            $subscription = $payment->subscription;
            if ($subscription && $subscription->isActive()) {
                $daysOverdue = now()->diffInDays($payment->due_date);
                
                if ($daysOverdue > 7) { // 7 days grace period
                    $subscription->update([
                        'status' => 'suspended',
                        'suspended_at' => now(),
                        'suspension_reason' => 'Pagamento em atraso'
                    ]);
                }
            }

            // Dispatch notification job
            SendPaymentNotification::dispatch($payment, 'payment_overdue');
        }
    }

    /**
     * Handle payment refunded event.
     */
    private function handlePaymentRefunded(array $paymentData): void
    {
        $payment = $this->findPaymentByAsaasId($paymentData['id']);
        
        if ($payment) {
            $payment->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);

            // Suspend subscription
            $subscription = $payment->subscription;
            if ($subscription) {
                $subscription->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'suspension_reason' => 'Pagamento reembolsado'
                ]);
            }
        }
    }

    /**
     * Handle payment failed event.
     */
    private function handlePaymentFailed(array $paymentData): void
    {
        $payment = $this->findPaymentByAsaasId($paymentData['id']);
        
        if ($payment) {
            $payment->update(['status' => 'failed']);

            // Dispatch notification job
            SendPaymentNotification::dispatch($payment, 'payment_failed');
        }
    }

    /**
     * Find payment by Asaas payment ID.
     */
    private function findPaymentByAsaasId(string $asaasPaymentId): ?Payment
    {
        return Payment::where('asaas_payment_id', $asaasPaymentId)->first();
    }

    /**
     * Find or create payment from webhook data.
     */
    private function findOrCreatePayment(array $paymentData): ?Payment
    {
        // First try to find by Asaas payment ID
        $payment = $this->findPaymentByAsaasId($paymentData['id']);
        
        if ($payment) {
            return $payment;
        }

        // Try to find by external reference
        if (isset($paymentData['externalReference'])) {
            $externalRef = $paymentData['externalReference'];
            if (str_starts_with($externalRef, 'payment_')) {
                $paymentId = str_replace('payment_', '', $externalRef);
                $payment = Payment::find($paymentId);
                
                if ($payment) {
                    return $payment;
                }
            }
        }

        // Find customer and subscription
        $customer = User::where('asaas_customer_id', $paymentData['customer'])->first();
        
        if (!$customer) {
            Log::warning('Customer not found for webhook payment', [
                'asaas_customer_id' => $paymentData['customer'],
                'asaas_payment_id' => $paymentData['id']
            ]);
            return null;
        }

        // Find active subscription for this customer
        $subscription = $customer->subscriptions()->active()->first();
        
        if (!$subscription) {
            Log::warning('No active subscription found for customer', [
                'customer_id' => $customer->id,
                'asaas_payment_id' => $paymentData['id']
            ]);
            return null;
        }

        // Create payment record
        return Payment::create([
            'subscription_id' => $subscription->id,
            'user_id' => $customer->id,
            'amount' => $paymentData['value'],
            'status' => 'pending',
            'billing_type' => strtolower($paymentData['billingType']),
            'due_date' => $paymentData['dueDate'],
            'description' => $paymentData['description'] ?? "Pagamento Assinatura",
            'asaas_payment_id' => $paymentData['id'],
            'invoice_url' => $paymentData['invoiceUrl'] ?? null,
            'bank_slip_url' => $paymentData['bankSlipUrl'] ?? null,
        ]);
    }
}

