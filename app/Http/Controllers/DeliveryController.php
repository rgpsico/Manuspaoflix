<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Delivery;
use App\Models\Subscription;
use Carbon\Carbon;

class DeliveryController extends Controller
{
    /**
     * Display deliveries for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Delivery::whereHas('subscription', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['subscription.plan', 'subscription.address']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->where('scheduled_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('scheduled_date', '<=', $request->end_date);
        }

        $deliveries = $query->orderBy('scheduled_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $deliveries->items(),
            'pagination' => [
                'current_page' => $deliveries->currentPage(),
                'last_page' => $deliveries->lastPage(),
                'per_page' => $deliveries->perPage(),
                'total' => $deliveries->total(),
            ]
        ]);
    }

    /**
     * Display the specified delivery.
     */
    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        // Check if user owns this delivery
        if ($delivery->subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Entrega não encontrada'
            ], 404);
        }

        $delivery->load(['subscription.plan', 'subscription.address']);

        return response()->json([
            'success' => true,
            'data' => $this->formatDeliveryResponse($delivery, true)
        ]);
    }

    /**
     * Rate a completed delivery.
     */
    public function rate(Request $request, Delivery $delivery): JsonResponse
    {
        // Check if user owns this delivery
        if ($delivery->subscription->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Entrega não encontrada'
            ], 404);
        }

        if (!$delivery->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas entregas concluídas podem ser avaliadas'
            ], 422);
        }

        if ($delivery->hasRating()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta entrega já foi avaliada'
            ], 422);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'feedback' => 'nullable|string|max:500'
        ]);

        $delivery->update([
            'customer_rating' => $validated['rating'],
            'customer_feedback' => $validated['feedback'] ?? null,
            'rated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avaliação registrada com sucesso',
            'data' => $this->formatDeliveryResponse($delivery->fresh())
        ]);
    }

    /**
     * Get delivery calendar for the authenticated user.
     */
    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|between:2020,2030'
        ]);

        $month = $validated['month'] ?? now()->month;
        $year = $validated['year'] ?? now()->year;

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $deliveries = Delivery::whereHas('subscription', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->whereBetween('scheduled_date', [$startDate, $endDate])
        ->with(['subscription.plan'])
        ->orderBy('scheduled_date')
        ->get();

        // Group deliveries by date
        $calendar = [];
        foreach ($deliveries as $delivery) {
            $date = $delivery->scheduled_date->format('Y-m-d');
            if (!isset($calendar[$date])) {
                $calendar[$date] = [];
            }
            $calendar[$date][] = $this->formatDeliveryResponse($delivery);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month,
                'year' => $year,
                'calendar' => $calendar,
                'total_deliveries' => $deliveries->count(),
                'completed_deliveries' => $deliveries->where('status', 'completed')->count(),
                'pending_deliveries' => $deliveries->where('status', 'pending')->count(),
            ]
        ]);
    }

    /**
     * Admin: Get deliveries for a specific date (route planning).
     */
    public function dailyRoute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'neighborhood' => 'nullable|string',
            'status' => 'nullable|in:pending,in_transit,completed,failed'
        ]);

        $date = Carbon::parse($validated['date'])->format('Y-m-d');

        $query = Delivery::whereDate('scheduled_date', $date)
            ->with(['subscription.plan', 'subscription.address', 'subscription.user']);

        if (isset($validated['neighborhood'])) {
            $query->whereHas('subscription.address', function ($q) use ($validated) {
                $q->where('neighborhood', 'like', '%' . $validated['neighborhood'] . '%');
            });
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $deliveries = $query->orderBy('scheduled_time')
            ->orderBy('created_at')
            ->get();

        // Group by neighborhood for route optimization
        $routes = [];
        foreach ($deliveries as $delivery) {
            $neighborhood = $delivery->subscription->address->neighborhood;
            if (!isset($routes[$neighborhood])) {
                $routes[$neighborhood] = [
                    'neighborhood' => $neighborhood,
                    'deliveries' => [],
                    'total_deliveries' => 0,
                    'completed_deliveries' => 0,
                ];
            }
            
            $routes[$neighborhood]['deliveries'][] = $this->formatDeliveryResponse($delivery, true);
            $routes[$neighborhood]['total_deliveries']++;
            
            if ($delivery->isCompleted()) {
                $routes[$neighborhood]['completed_deliveries']++;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'total_deliveries' => $deliveries->count(),
                'routes' => array_values($routes),
                'summary' => [
                    'pending' => $deliveries->where('status', 'pending')->count(),
                    'in_transit' => $deliveries->where('status', 'in_transit')->count(),
                    'completed' => $deliveries->where('status', 'completed')->count(),
                    'failed' => $deliveries->where('status', 'failed')->count(),
                ]
            ]
        ]);
    }

    /**
     * Admin: Update delivery status.
     */
    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_transit,completed,failed',
            'delivery_notes' => 'nullable|string|max:500',
            'delivered_at' => 'nullable|date',
            'delivery_latitude' => 'nullable|numeric|between:-90,90',
            'delivery_longitude' => 'nullable|numeric|between:-180,180',
            'delivery_photo_url' => 'nullable|url'
        ]);

        $oldStatus = $delivery->status;
        
        $delivery->update($validated);

        // If status changed to completed, set delivered_at if not provided
        if ($validated['status'] === 'completed' && !isset($validated['delivered_at'])) {
            $delivery->update(['delivered_at' => now()]);
        }

        // If status changed to failed, mark subscription as needing attention
        if ($validated['status'] === 'failed') {
            $delivery->subscription->markAsNeedsAttention('Falha na entrega');
        }

        Log::info('Delivery status updated', [
            'delivery_id' => $delivery->id,
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status da entrega atualizado com sucesso',
            'data' => $this->formatDeliveryResponse($delivery->fresh())
        ]);
    }

    /**
     * Admin: Bulk update delivery statuses.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_ids' => 'required|array|min:1',
            'delivery_ids.*' => 'exists:deliveries,id',
            'status' => 'required|in:pending,in_transit,completed,failed',
            'delivery_notes' => 'nullable|string|max:500'
        ]);

        $deliveries = Delivery::whereIn('id', $validated['delivery_ids'])->get();
        
        $updateData = [
            'status' => $validated['status'],
            'delivery_notes' => $validated['delivery_notes'] ?? null,
        ];

        if ($validated['status'] === 'completed') {
            $updateData['delivered_at'] = now();
        }

        $updated = 0;
        foreach ($deliveries as $delivery) {
            $delivery->update($updateData);
            $updated++;
        }

        Log::info('Bulk delivery status update', [
            'delivery_ids' => $validated['delivery_ids'],
            'status' => $validated['status'],
            'updated_count' => $updated,
            'updated_by' => $request->user()->id
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} entregas atualizadas com sucesso"
        ]);
    }

    /**
     * Admin: Get delivery statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'neighborhood' => 'nullable|string'
        ]);

        $query = Delivery::query();

        if (isset($validated['start_date'])) {
            $query->where('scheduled_date', '>=', $validated['start_date']);
        }

        if (isset($validated['end_date'])) {
            $query->where('scheduled_date', '<=', $validated['end_date']);
        }

        if (isset($validated['neighborhood'])) {
            $query->whereHas('subscription.address', function ($q) use ($validated) {
                $q->where('neighborhood', 'like', '%' . $validated['neighborhood'] . '%');
            });
        }

        $stats = [
            'total_deliveries' => $query->count(),
            'completed_deliveries' => $query->where('status', 'completed')->count(),
            'pending_deliveries' => $query->where('status', 'pending')->count(),
            'failed_deliveries' => $query->where('status', 'failed')->count(),
            'in_transit_deliveries' => $query->where('status', 'in_transit')->count(),
            'average_rating' => $query->whereNotNull('customer_rating')->avg('customer_rating'),
            'completion_rate' => 0,
            'on_time_rate' => 0,
        ];

        if ($stats['total_deliveries'] > 0) {
            $stats['completion_rate'] = round(($stats['completed_deliveries'] / $stats['total_deliveries']) * 100, 2);
            
            // Calculate on-time delivery rate (delivered on scheduled date)
            $onTimeDeliveries = $query->where('status', 'completed')
                ->whereRaw('DATE(delivered_at) = DATE(scheduled_date)')
                ->count();
            
            $stats['on_time_rate'] = round(($onTimeDeliveries / $stats['completed_deliveries']) * 100, 2);
        }

        // Get top neighborhoods by delivery count
        $topNeighborhoods = DB::table('deliveries')
            ->join('subscriptions', 'deliveries.subscription_id', '=', 'subscriptions.id')
            ->join('addresses', 'subscriptions.address_id', '=', 'addresses.id')
            ->select('addresses.neighborhood', DB::raw('COUNT(*) as delivery_count'))
            ->groupBy('addresses.neighborhood')
            ->orderBy('delivery_count', 'desc')
            ->limit(5)
            ->get();

        $stats['top_neighborhoods'] = $topNeighborhoods;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Format delivery response.
     */
    private function formatDeliveryResponse(Delivery $delivery, bool $detailed = false): array
    {
        $data = [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'status_label' => $delivery->status_label,
            'scheduled_date' => $delivery->scheduled_date,
            'scheduled_time' => $delivery->scheduled_time,
            'delivered_at' => $delivery->delivered_at,
            'delivery_notes' => $delivery->delivery_notes,
            'customer_rating' => $delivery->customer_rating,
            'customer_feedback' => $delivery->customer_feedback,
            'rated_at' => $delivery->rated_at,
            'is_completed' => $delivery->isCompleted(),
            'is_overdue' => $delivery->isOverdue(),
            'has_rating' => $delivery->hasRating(),
            'days_until_delivery' => $delivery->days_until_delivery,
            'subscription' => [
                'id' => $delivery->subscription->id,
                'plan_name' => $delivery->subscription->plan->name,
                'bread_quantity' => $delivery->subscription->plan->bread_quantity,
                'bread_types' => $delivery->subscription->plan->bread_types,
            ],
            'address' => [
                'full_address' => $delivery->subscription->address->full_address,
                'neighborhood' => $delivery->subscription->address->neighborhood,
                'city' => $delivery->subscription->address->city,
                'reference' => $delivery->subscription->address->reference,
            ]
        ];

        if ($detailed) {
            $data['delivery_latitude'] = $delivery->delivery_latitude;
            $data['delivery_longitude'] = $delivery->delivery_longitude;
            $data['delivery_photo_url'] = $delivery->delivery_photo_url;
            $data['created_at'] = $delivery->created_at;
            $data['updated_at'] = $delivery->updated_at;
            
            $data['subscription']['user'] = [
                'id' => $delivery->subscription->user->id,
                'name' => $delivery->subscription->user->name,
                'phone' => $delivery->subscription->user->phone,
            ];
            
            $data['address']['latitude'] = $delivery->subscription->address->latitude;
            $data['address']['longitude'] = $delivery->subscription->address->longitude;
        }

        return $data;
    }
}
