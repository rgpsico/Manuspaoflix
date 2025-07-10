<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class AsaasService
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key', env('ASAAS_API_KEY'));
        $this->baseUrl = config('services.asaas.api_url', env('ASAAS_API_URL', 'https://sandbox.asaas.com/api/v3'));
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
        ]);
    }

    /**
     * Create a customer in Asaas.
     */
    public function createCustomer(array $customerData): array
    {
        try {
            $response = $this->client->post('/customers', [
                'json' => $customerData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas customer created', [
                'customer_id' => $data['id'] ?? null,
                'email' => $customerData['email'] ?? null
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to create Asaas customer', [
                'error' => $e->getMessage(),
                'customer_data' => $customerData
            ]);

            throw new \Exception('Erro ao criar cliente no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Update a customer in Asaas.
     */
    public function updateCustomer(string $customerId, array $customerData): array
    {
        try {
            $response = $this->client->post("/customers/{$customerId}", [
                'json' => $customerData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas customer updated', [
                'customer_id' => $customerId
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to update Asaas customer', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId
            ]);

            throw new \Exception('Erro ao atualizar cliente no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Get a customer from Asaas.
     */
    public function getCustomer(string $customerId): array
    {
        try {
            $response = $this->client->get("/customers/{$customerId}");
            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            Log::error('Failed to get Asaas customer', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId
            ]);

            throw new \Exception('Erro ao buscar cliente no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Create a subscription in Asaas.
     */
    public function createSubscription(array $subscriptionData): array
    {
        try {
            $response = $this->client->post('/subscriptions', [
                'json' => $subscriptionData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas subscription created', [
                'subscription_id' => $data['id'] ?? null,
                'customer_id' => $subscriptionData['customer'] ?? null
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to create Asaas subscription', [
                'error' => $e->getMessage(),
                'subscription_data' => $subscriptionData
            ]);

            throw new \Exception('Erro ao criar assinatura no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Update a subscription in Asaas.
     */
    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        try {
            $response = $this->client->post("/subscriptions/{$subscriptionId}", [
                'json' => $subscriptionData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas subscription updated', [
                'subscription_id' => $subscriptionId
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to update Asaas subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);

            throw new \Exception('Erro ao atualizar assinatura no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a subscription in Asaas.
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $response = $this->client->delete("/subscriptions/{$subscriptionId}");

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas subscription cancelled', [
                'subscription_id' => $subscriptionId
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to cancel Asaas subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);

            throw new \Exception('Erro ao cancelar assinatura no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Get subscription payments from Asaas.
     */
    public function getSubscriptionPayments(string $subscriptionId): array
    {
        try {
            $response = $this->client->get("/subscriptions/{$subscriptionId}/payments");
            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            Log::error('Failed to get Asaas subscription payments', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId
            ]);

            throw new \Exception('Erro ao buscar pagamentos da assinatura no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Create a payment in Asaas.
     */
    public function createPayment(array $paymentData): array
    {
        try {
            $response = $this->client->post('/payments', [
                'json' => $paymentData
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas payment created', [
                'payment_id' => $data['id'] ?? null,
                'customer_id' => $paymentData['customer'] ?? null
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to create Asaas payment', [
                'error' => $e->getMessage(),
                'payment_data' => $paymentData
            ]);

            throw new \Exception('Erro ao criar pagamento no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Get a payment from Asaas.
     */
    public function getPayment(string $paymentId): array
    {
        try {
            $response = $this->client->get("/payments/{$paymentId}");
            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            Log::error('Failed to get Asaas payment', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);

            throw new \Exception('Erro ao buscar pagamento no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a payment in Asaas.
     */
    public function cancelPayment(string $paymentId): array
    {
        try {
            $response = $this->client->delete("/payments/{$paymentId}");

            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('Asaas payment cancelled', [
                'payment_id' => $paymentId
            ]);

            return $data;

        } catch (RequestException $e) {
            Log::error('Failed to cancel Asaas payment', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);

            throw new \Exception('Erro ao cancelar pagamento no Asaas: ' . $e->getMessage());
        }
    }

    /**
     * Get PIX QR Code for a payment.
     */
    public function getPixQrCode(string $paymentId): array
    {
        try {
            $response = $this->client->get("/payments/{$paymentId}/pixQrCode");
            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            Log::error('Failed to get PIX QR Code', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId
            ]);

            throw new \Exception('Erro ao buscar QR Code PIX: ' . $e->getMessage());
        }
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $webhookSecret = config('services.asaas.webhook_secret', env('WEBHOOK_SECRET'));
        
        if (!$webhookSecret) {
            Log::warning('Webhook secret not configured');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Format customer data for Asaas API.
     */
    public function formatCustomerData(\App\Models\User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'mobilePhone' => $user->phone,
            'cpfCnpj' => $user->cpf,
            'personType' => 'FISICA',
            'notificationDisabled' => false,
        ];
    }

    /**
     * Format subscription data for Asaas API.
     */
    public function formatSubscriptionData(\App\Models\Subscription $subscription): array
    {
        $cycleMap = [
            'daily' => 'MONTHLY', // Asaas não suporta diário, usar mensal
            'alternate_days' => 'MONTHLY',
            'weekends' => 'WEEKLY',
            'weekly' => 'WEEKLY',
            'monthly' => 'MONTHLY',
        ];

        return [
            'customer' => $subscription->user->asaas_customer_id,
            'billingType' => 'BOLETO',
            'nextDueDate' => $subscription->start_date->format('Y-m-d'),
            'value' => (float) $subscription->price,
            'cycle' => $cycleMap[$subscription->plan->frequency] ?? 'MONTHLY',
            'description' => "Assinatura {$subscription->plan->name}",
        ];
    }

    /**
     * Format payment data for Asaas API.
     */
    public function formatPaymentData(\App\Models\Payment $payment): array
    {
        return [
            'customer' => $payment->user->asaas_customer_id,
            'billingType' => $payment->billing_type,
            'dueDate' => $payment->due_date->format('Y-m-d'),
            'value' => (float) $payment->amount,
            'description' => $payment->description ?: "Pagamento {$payment->subscription->plan->name}",
        ];
    }
}

