<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Address;
use App\Services\AsaasService;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    private AsaasService $asaasService;

    public function __construct(AsaasService $asaasService)
    {
        $this->asaasService = $asaasService;
    }

    /**
     * Display user's subscriptions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $subscriptions = $user->subscriptions()
            ->with(['plan', 'address'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions->map(function ($subscription) {
                return $this->formatSubscriptionResponse($subscription);
            })
        ]);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Request $request, Subscription $subscription): JsonResponse
    {
        // Check if user owns this subscription
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        $subscription->load(['plan', 'address', 'deliveries', 'payments']);

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscriptionResponse($subscription, true)
        ]);
    }

    /**
     * Create a new subscription.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'address_id' => 'required|exists:addresses,id',
            'start_date' => 'required|date|after_or_equal:today',
            'preferences' => 'nullable|array',
            'special_instructions' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $plan = Plan::findOrFail($validated['plan_id']);
        $address = Address::findOrFail($validated['address_id']);

        // Check if plan is active
        if (!$plan->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Plano não está disponível para assinatura'
            ], 422);
        }

        // Check if address belongs to user
        if ($address->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado'
            ], 404);
        }

        // Check if user already has an active subscription for this plan
        $existingSubscription = $user->subscriptions()
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['active', 'pending_payment'])
            ->first();

        if ($existingSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'Você já possui uma assinatura ativa para este plano'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create or update customer in Asaas
            if (!$user->asaas_customer_id) {
                $customerData = $this->asaasService->formatCustomerData($user);
                $asaasCustomer = $this->asaasService->createCustomer($customerData);
                $user->update(['asaas_customer_id' => $asaasCustomer['id']]);
            }

            // Create subscription
            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'address_id' => $address->id,
                'status' => 'pending_payment',
                'start_date' => $validated['start_date'],
                'next_delivery_date' => $plan->getNextDeliveryDate(Carbon::parse($validated['start_date'])),
                'price' => $plan->price,
                'preferences' => $validated['preferences'] ?? [],
                'special_instructions' => $validated['special_instructions'] ?? null,
            ]);

            // Create subscription in Asaas
            $subscriptionData = $this->asaasService->formatSubscriptionData($subscription);
            $asaasSubscription = $this->asaasService->createSubscription($subscriptionData);
            
            $subscription->update([
                'asaas_subscription_id' => $asaasSubscription['id']
            ]);

            DB::commit();

            Log::info('Subscription created successfully', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'asaas_subscription_id' => $asaasSubscription['id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assinatura criada com sucesso',
                'data' => $this->formatSubscriptionResponse($subscription->fresh(['plan', 'address']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create subscription', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'plan_id' => $plan->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update subscription preferences.
     */
    public function update(Request $request, Subscription $subscription): JsonResponse
    {
        // Check if user owns this subscription
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'address_id' => 'sometimes|exists:addresses,id',
            'preferences' => 'nullable|array',
            'special_instructions' => 'nullable|string|max:500',
        ]);

        // Check if address belongs to user (if provided)
        if (isset($validated['address_id'])) {
            $address = Address::findOrFail($validated['address_id']);
            if ($address->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endereço não encontrado'
                ], 404);
            }
        }

        $subscription->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Assinatura atualizada com sucesso',
            'data' => $this->formatSubscriptionResponse($subscription->fresh(['plan', 'address']))
        ]);
    }

    /**
     * Pause subscription.
     */
    public function pause(Request $request, Subscription $subscription): JsonResponse
    {
        // Check if user owns this subscription
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        if (!$subscription->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas assinaturas ativas podem ser pausadas'
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255'
        ]);

        $subscription->pause($validated['reason'] ?? 'Pausada pelo cliente');

        return response()->json([
            'success' => true,
            'message' => 'Assinatura pausada com sucesso',
            'data' => $this->formatSubscriptionResponse($subscription->fresh(['plan', 'address']))
        ]);
    }

    /**
     * Resume subscription.
     */
    public function resume(Request $request, Subscription $subscription): JsonResponse
    {
        // Check if user owns this subscription
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        if (!$subscription->isPaused()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas assinaturas pausadas podem ser reativadas'
            ], 422);
        }

        $subscription->resume();

        return response()->json([
            'success' => true,
            'message' => 'Assinatura reativada com sucesso',
            'data' => $this->formatSubscriptionResponse($subscription->fresh(['plan', 'address']))
        ]);
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        // Check if user owns this subscription
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        if ($subscription->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura já está cancelada'
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255'
        ]);

        try {
            // Cancel subscription in Asaas
            if ($subscription->asaas_subscription_id) {
                $this->asaasService->cancelSubscription($subscription->asaas_subscription_id);
            }

            $subscription->cancel($validated['reason'] ?? 'Cancelada pelo cliente');

            Log::info('Subscription cancelled', [
                'subscription_id' => $subscription->id,
                'user_id' => $request->user()->id,
                'reason' => $validated['reason'] ?? 'Cancelada pelo cliente'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assinatura cancelada com sucesso',
                'data' => $this->formatSubscriptionResponse($subscription->fresh(['plan', 'address']))
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscription->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao cancelar assinatura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription payments.
     */
    public function payments(Request $request, Subscription $subscription): JsonResponse
    {
        // Check if user owns this subscription
        if ($subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assinatura não encontrada'
            ], 404);
        }

        $payments = $subscription->payments()
            ->orderBy('due_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'formatted_amount' => $payment->formatted_amount,
                    'status' => $payment->status,
                    'status_label' => $payment->status_label,
                    'billing_type' => $payment->billing_type,
                    'billing_type_label' => $payment->billing_type_label,
                    'due_date' => $payment->due_date,
                    'formatted_due_date' => $payment->formatted_due_date,
                    'payment_date' => $payment->payment_date,
                    'formatted_payment_date' => $payment->formatted_payment_date,
                    'description' => $payment->description,
                    'days_until_due' => $payment->days_until_due,
                    'days_overdue' => $payment->days_overdue,
                    'is_overdue' => $payment->isOverdue(),
                    'is_paid' => $payment->isPaid(),
                    'has_invoice_url' => $payment->hasInvoiceUrl(),
                    'asaas_payment_url' => $payment->asaas_payment_url,
                    'pix_qr_code' => $payment->pix_qr_code,
                    'pix_copy_paste' => $payment->pix_copy_paste,
                ];
            })
        ]);
    }

    /**
     * Format subscription response.
     */
    private function formatSubscriptionResponse(Subscription $subscription, bool $detailed = false): array
    {
        $data = [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'status_label' => $subscription->status_label,
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'next_delivery_date' => $subscription->next_delivery_date,
            'price' => $subscription->price,
            'formatted_price' => $subscription->formatted_price,
            'preferences' => $subscription->preferences,
            'special_instructions' => $subscription->special_instructions,
            'paused_at' => $subscription->paused_at,
            'cancelled_at' => $subscription->cancelled_at,
            'cancellation_reason' => $subscription->cancellation_reason,
            'days_until_next_delivery' => $subscription->days_until_next_delivery,
            'is_due_today' => $subscription->isDueToday(),
            'is_overdue' => $subscription->isOverdue(),
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'description' => $subscription->plan->description,
                'frequency' => $subscription->plan->frequency,
                'frequency_label' => $subscription->plan->frequency_label,
                'bread_quantity' => $subscription->plan->bread_quantity,
                'bread_types' => $subscription->plan->bread_types,
                'bread_types_string' => $subscription->plan->bread_types_string,
            ],
            'address' => [
                'id' => $subscription->address->id,
                'label' => $subscription->address->label,
                'full_address' => $subscription->address->full_address,
                'street' => $subscription->address->street,
                'number' => $subscription->address->number,
                'complement' => $subscription->address->complement,
                'neighborhood' => $subscription->address->neighborhood,
                'city' => $subscription->address->city,
                'state' => $subscription->address->state,
                'postal_code' => $subscription->address->postal_code,
                'formatted_postal_code' => $subscription->address->formatted_postal_code,
                'reference' => $subscription->address->reference,
            ],
        ];

        if ($detailed) {
            $data['total_deliveries'] = $subscription->total_deliveries;
            $data['completed_deliveries'] = $subscription->completed_deliveries;
            $data['pending_deliveries'] = $subscription->pending_deliveries;
            $data['duration_in_days'] = $subscription->duration_in_days;
            $data['created_at'] = $subscription->created_at;
            $data['updated_at'] = $subscription->updated_at;
        }

        return $data;
    }
}
