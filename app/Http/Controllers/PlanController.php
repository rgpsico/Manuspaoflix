<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use App\Models\Plan;

class PlanController extends Controller
{
    /**
     * Display a listing of active plans.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'monthly_price' => $plan->monthly_price,
                    'frequency' => $plan->frequency,
                    'frequency_label' => $plan->frequency_label,
                    'delivery_days' => $plan->delivery_days,
                    'delivery_days_labels' => $plan->delivery_days_labels,
                    'bread_quantity' => $plan->bread_quantity,
                    'bread_types' => $plan->bread_types,
                    'bread_types_string' => $plan->bread_types_string,
                    'is_active' => $plan->is_active,
                    'sort_order' => $plan->sort_order,
                ];
            })
        ]);
    }

    /**
     * Display the specified plan.
     */
    public function show(Plan $plan): JsonResponse
    {
        if (!$plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Plano não encontrado ou inativo'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => $plan->price,
                'formatted_price' => $plan->formatted_price,
                'monthly_price' => $plan->monthly_price,
                'frequency' => $plan->frequency,
                'frequency_label' => $plan->frequency_label,
                'delivery_days' => $plan->delivery_days,
                'delivery_days_labels' => $plan->delivery_days_labels,
                'bread_quantity' => $plan->bread_quantity,
                'bread_types' => $plan->bread_types,
                'bread_types_string' => $plan->bread_types_string,
                'is_active' => $plan->is_active,
                'sort_order' => $plan->sort_order,
                'active_subscriptions_count' => $plan->activeSubscriptions()->count(),
                'total_subscriptions_count' => $plan->subscriptions()->count(),
            ]
        ]);
    }

    /**
     * Store a newly created plan (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:plans',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'frequency' => ['required', Rule::in(['daily', 'alternate_days', 'weekends', 'weekly', 'monthly'])],
            'delivery_days' => 'nullable|array',
            'delivery_days.*' => 'integer|between:0,6',
            'bread_quantity' => 'required|integer|min:1',
            'bread_types' => 'nullable|array',
            'bread_types.*' => 'string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $plan = Plan::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plano criado com sucesso',
            'data' => $plan
        ], 201);
    }

    /**
     * Update the specified plan (Admin only).
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:plans,name,' . $plan->id,
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0.01',
            'frequency' => ['required', Rule::in(['daily', 'alternate_days', 'weekends', 'weekly', 'monthly'])],
            'delivery_days' => 'nullable|array',
            'delivery_days.*' => 'integer|between:0,6',
            'bread_quantity' => 'required|integer|min:1',
            'bread_types' => 'nullable|array',
            'bread_types.*' => 'string|max:100',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Plano atualizado com sucesso',
            'data' => $plan->fresh()
        ]);
    }

    /**
     * Remove the specified plan (Admin only).
     */
    public function destroy(Plan $plan): JsonResponse
    {
        // Check if plan has active subscriptions
        if ($plan->activeSubscriptions()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Não é possível excluir um plano com assinaturas ativas'
            ], 422);
        }

        // Soft delete by marking as inactive
        $plan->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Plano desativado com sucesso'
        ]);
    }

    /**
     * Get plan statistics (Admin only).
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_plans' => Plan::count(),
            'active_plans' => Plan::active()->count(),
            'inactive_plans' => Plan::where('is_active', false)->count(),
            'plans_with_subscriptions' => Plan::whereHas('subscriptions')->count(),
            'most_popular_plan' => null,
        ];

        // Get most popular plan
        $popularPlan = Plan::withCount('activeSubscriptions')
            ->active()
            ->orderBy('active_subscriptions_count', 'desc')
            ->first();

        if ($popularPlan) {
            $stats['most_popular_plan'] = [
                'id' => $popularPlan->id,
                'name' => $popularPlan->name,
                'active_subscriptions' => $popularPlan->active_subscriptions_count,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get available frequencies for plans.
     */
    public function frequencies(): JsonResponse
    {
        $frequencies = [
            'daily' => 'Diário',
            'alternate_days' => 'Dias alternados',
            'weekends' => 'Fins de semana',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
        ];

        return response()->json([
            'success' => true,
            'data' => $frequencies
        ]);
    }

    /**
     * Get available bread types.
     */
    public function breadTypes(): JsonResponse
    {
        $breadTypes = [
            'Pão Francês',
            'Pão de Forma',
            'Pão Integral',
            'Pão de Centeio',
            'Pão Doce',
            'Pão de Açúcar',
            'Pão de Leite',
            'Pão Australiano',
            'Pão de Hambúrguer',
            'Pão de Hot Dog',
            'Baguete',
            'Ciabatta',
            'Pão de Alho',
            'Pão de Queijo',
            'Croissant',
        ];

        return response()->json([
            'success' => true,
            'data' => $breadTypes
        ]);
    }
}
