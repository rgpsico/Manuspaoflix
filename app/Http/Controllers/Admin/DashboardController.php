<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\Plan;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview statistics.
     */
    public function overview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        $period = $validated['period'] ?? 'month';
        $dateRange = $this->getDateRange($period, $validated);

        $stats = [
            'users' => $this->getUserStats($dateRange),
            'subscriptions' => $this->getSubscriptionStats($dateRange),
            'deliveries' => $this->getDeliveryStats($dateRange),
            'payments' => $this->getPaymentStats($dateRange),
            'revenue' => $this->getRevenueStats($dateRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'period' => $period,
            'date_range' => [
                'start' => $dateRange['start']->format('Y-m-d'),
                'end' => $dateRange['end']->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Get detailed analytics data.
     */
    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric' => 'required|in:revenue,subscriptions,deliveries,customers',
            'period' => 'nullable|in:daily,weekly,monthly',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:365'
        ]);

        $metric = $validated['metric'];
        $period = $validated['period'] ?? 'daily';
        $limit = $validated['limit'] ?? 30;

        $dateRange = $this->getDateRange('custom', $validated);
        
        $data = match($metric) {
            'revenue' => $this->getRevenueAnalytics($dateRange, $period, $limit),
            'subscriptions' => $this->getSubscriptionAnalytics($dateRange, $period, $limit),
            'deliveries' => $this->getDeliveryAnalytics($dateRange, $period, $limit),
            'customers' => $this->getCustomerAnalytics($dateRange, $period, $limit),
        };

        return response()->json([
            'success' => true,
            'data' => $data,
            'metric' => $metric,
            'period' => $period
        ]);
    }

    /**
     * Get top performing plans.
     */
    public function topPlans(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:20',
            'period' => 'nullable|in:week,month,quarter,year'
        ]);

        $limit = $validated['limit'] ?? 10;
        $period = $validated['period'] ?? 'month';
        $dateRange = $this->getDateRange($period);

        $topPlans = Plan::select([
                'plans.*',
                DB::raw('COUNT(subscriptions.id) as subscription_count'),
                DB::raw('SUM(payments.amount) as total_revenue'),
                DB::raw('AVG(CASE WHEN deliveries.customer_rating IS NOT NULL THEN deliveries.customer_rating END) as avg_rating')
            ])
            ->leftJoin('subscriptions', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoin('payments', function($join) use ($dateRange) {
                $join->on('subscriptions.id', '=', 'payments.subscription_id')
                     ->where('payments.status', 'paid')
                     ->whereBetween('payments.payment_date', [$dateRange['start'], $dateRange['end']]);
            })
            ->leftJoin('deliveries', function($join) use ($dateRange) {
                $join->on('subscriptions.id', '=', 'deliveries.subscription_id')
                     ->where('deliveries.status', 'completed')
                     ->whereBetween('deliveries.delivered_at', [$dateRange['start'], $dateRange['end']]);
            })
            ->groupBy('plans.id')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topPlans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => $plan->price,
                    'frequency' => $plan->frequency,
                    'subscription_count' => $plan->subscription_count ?? 0,
                    'total_revenue' => $plan->total_revenue ?? 0,
                    'avg_rating' => $plan->avg_rating ? round($plan->avg_rating, 2) : null,
                    'formatted_revenue' => 'R$ ' . number_format($plan->total_revenue ?? 0, 2, ',', '.'),
                ];
            })
        ]);
    }

    /**
     * Get recent activities.
     */
    public function recentActivities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        $limit = $validated['limit'] ?? 20;

        // Get recent subscriptions
        $recentSubscriptions = Subscription::with(['user', 'plan'])
            ->latest()
            ->limit($limit / 4)
            ->get()
            ->map(function ($subscription) {
                return [
                    'type' => 'subscription',
                    'title' => 'Nova assinatura',
                    'description' => "{$subscription->user->name} assinou o plano {$subscription->plan->name}",
                    'timestamp' => $subscription->created_at,
                    'data' => [
                        'user_id' => $subscription->user_id,
                        'plan_name' => $subscription->plan->name,
                        'amount' => $subscription->plan->price
                    ]
                ];
            });

        // Get recent payments
        $recentPayments = Payment::with(['user', 'subscription.plan'])
            ->where('status', 'paid')
            ->latest('payment_date')
            ->limit($limit / 4)
            ->get()
            ->map(function ($payment) {
                return [
                    'type' => 'payment',
                    'title' => 'Pagamento recebido',
                    'description' => "R$ {$payment->formatted_amount} de {$payment->user->name}",
                    'timestamp' => $payment->payment_date,
                    'data' => [
                        'user_id' => $payment->user_id,
                        'amount' => $payment->amount,
                        'plan_name' => $payment->subscription->plan->name ?? null
                    ]
                ];
            });

        // Get recent deliveries
        $recentDeliveries = Delivery::with(['subscription.user', 'subscription.plan'])
            ->where('status', 'completed')
            ->latest('delivered_at')
            ->limit($limit / 4)
            ->get()
            ->map(function ($delivery) {
                return [
                    'type' => 'delivery',
                    'title' => 'Entrega concluída',
                    'description' => "Entrega para {$delivery->subscription->user->name}",
                    'timestamp' => $delivery->delivered_at,
                    'data' => [
                        'user_id' => $delivery->subscription->user_id,
                        'plan_name' => $delivery->subscription->plan->name,
                        'rating' => $delivery->customer_rating
                    ]
                ];
            });

        // Get recent users
        $recentUsers = User::where('role', 'customer')
            ->latest()
            ->limit($limit / 4)
            ->get()
            ->map(function ($user) {
                return [
                    'type' => 'user',
                    'title' => 'Novo cliente',
                    'description' => "{$user->name} se cadastrou",
                    'timestamp' => $user->created_at,
                    'data' => [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]
                ];
            });

        // Combine and sort all activities
        $activities = collect()
            ->merge($recentSubscriptions)
            ->merge($recentPayments)
            ->merge($recentDeliveries)
            ->merge($recentUsers)
            ->sortByDesc('timestamp')
            ->take($limit)
            ->values();

        return response()->json([
            'success' => true,
            'data' => $activities
        ]);
    }

    /**
     * Export data to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:users,subscriptions,deliveries,payments',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'format' => 'nullable|in:csv,xlsx'
        ]);

        $type = $validated['type'];
        $format = $validated['format'] ?? 'csv';
        $dateRange = $this->getDateRange('custom', $validated);

        // Generate export data based on type
        $data = match($type) {
            'users' => $this->exportUsers($dateRange),
            'subscriptions' => $this->exportSubscriptions($dateRange),
            'deliveries' => $this->exportDeliveries($dateRange),
            'payments' => $this->exportPayments($dateRange),
        };

        // In a real application, you would generate and save the file
        // For now, we'll return the data structure
        return response()->json([
            'success' => true,
            'message' => 'Exportação preparada com sucesso',
            'data' => [
                'type' => $type,
                'format' => $format,
                'records_count' => count($data),
                'date_range' => [
                    'start' => $dateRange['start']->format('Y-m-d'),
                    'end' => $dateRange['end']->format('Y-m-d')
                ],
                'download_url' => "/api/admin/exports/{$type}-" . now()->format('Y-m-d') . ".{$format}"
            ]
        ]);
    }

    /**
     * Get date range based on period.
     */
    private function getDateRange(string $period, array $validated = []): array
    {
        if (isset($validated['start_date']) && isset($validated['end_date'])) {
            return [
                'start' => Carbon::parse($validated['start_date'])->startOfDay(),
                'end' => Carbon::parse($validated['end_date'])->endOfDay()
            ];
        }

        return match($period) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay()
            ],
            'week' => [
                'start' => now()->startOfWeek(),
                'end' => now()->endOfWeek()
            ],
            'month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth()
            ],
            'quarter' => [
                'start' => now()->startOfQuarter(),
                'end' => now()->endOfQuarter()
            ],
            'year' => [
                'start' => now()->startOfYear(),
                'end' => now()->endOfYear()
            ],
            default => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfMonth()
            ]
        };
    }

    /**
     * Get user statistics.
     */
    private function getUserStats(array $dateRange): array
    {
        $totalUsers = User::where('role', 'customer')->count();
        $newUsers = User::where('role', 'customer')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $activeUsers = User::where('role', 'customer')
            ->whereHas('subscriptions', function ($q) {
                $q->where('status', 'active');
            })
            ->count();

        return [
            'total' => $totalUsers,
            'new' => $newUsers,
            'active' => $activeUsers,
            'inactive' => $totalUsers - $activeUsers,
        ];
    }

    /**
     * Get subscription statistics.
     */
    private function getSubscriptionStats(array $dateRange): array
    {
        $total = Subscription::count();
        $active = Subscription::where('status', 'active')->count();
        $suspended = Subscription::where('status', 'suspended')->count();
        $cancelled = Subscription::where('status', 'cancelled')->count();
        
        $newSubscriptions = Subscription::whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'suspended' => $suspended,
            'cancelled' => $cancelled,
            'new' => $newSubscriptions,
        ];
    }

    /**
     * Get delivery statistics.
     */
    private function getDeliveryStats(array $dateRange): array
    {
        $query = Delivery::whereBetween('scheduled_date', [$dateRange['start'], $dateRange['end']]);
        
        $total = $query->count();
        $completed = $query->where('status', 'completed')->count();
        $pending = $query->where('status', 'pending')->count();
        $failed = $query->where('status', 'failed')->count();
        
        $avgRating = Delivery::whereNotNull('customer_rating')
            ->whereBetween('delivered_at', [$dateRange['start'], $dateRange['end']])
            ->avg('customer_rating');

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'failed' => $failed,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'average_rating' => $avgRating ? round($avgRating, 2) : null,
        ];
    }

    /**
     * Get payment statistics.
     */
    private function getPaymentStats(array $dateRange): array
    {
        $query = Payment::whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        
        $total = $query->count();
        $paid = $query->where('status', 'paid')->count();
        $pending = $query->where('status', 'pending')->count();
        $overdue = $query->where('status', 'overdue')->count();
        $failed = $query->where('status', 'failed')->count();

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'overdue' => $overdue,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get revenue statistics.
     */
    private function getRevenueStats(array $dateRange): array
    {
        $paidPayments = Payment::where('status', 'paid')
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']]);
        
        $totalRevenue = $paidPayments->sum('amount');
        $averageTicket = $paidPayments->avg('amount');
        $transactionCount = $paidPayments->count();

        // Previous period comparison
        $previousStart = $dateRange['start']->copy()->subDays($dateRange['start']->diffInDays($dateRange['end']) + 1);
        $previousEnd = $dateRange['start']->copy()->subDay();
        
        $previousRevenue = Payment::where('status', 'paid')
            ->whereBetween('payment_date', [$previousStart, $previousEnd])
            ->sum('amount');

        $growth = $previousRevenue > 0 ? 
            round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 2) : 0;

        return [
            'total' => $totalRevenue,
            'average_ticket' => $averageTicket ? round($averageTicket, 2) : 0,
            'transaction_count' => $transactionCount,
            'growth_percentage' => $growth,
            'formatted_total' => 'R$ ' . number_format($totalRevenue, 2, ',', '.'),
            'formatted_average' => 'R$ ' . number_format($averageTicket ?? 0, 2, ',', '.'),
        ];
    }

    /**
     * Get revenue analytics data.
     */
    private function getRevenueAnalytics(array $dateRange, string $period, int $limit): array
    {
        $groupBy = match($period) {
            'daily' => 'DATE(payment_date)',
            'weekly' => 'YEARWEEK(payment_date)',
            'monthly' => 'DATE_FORMAT(payment_date, "%Y-%m")',
        };

        $data = Payment::select([
                DB::raw($groupBy . ' as period'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as transaction_count')
            ])
            ->where('status', 'paid')
            ->whereBetween('payment_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('period')
            ->orderBy('period')
            ->limit($limit)
            ->get();

        return $data->map(function ($item) {
            return [
                'period' => $item->period,
                'revenue' => $item->revenue,
                'transaction_count' => $item->transaction_count,
                'formatted_revenue' => 'R$ ' . number_format($item->revenue, 2, ',', '.')
            ];
        });
    }

    /**
     * Get subscription analytics data.
     */
    private function getSubscriptionAnalytics(array $dateRange, string $period, int $limit): array
    {
        $groupBy = match($period) {
            'daily' => 'DATE(created_at)',
            'weekly' => 'YEARWEEK(created_at)',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        $data = Subscription::select([
                DB::raw($groupBy . ' as period'),
                DB::raw('COUNT(*) as new_subscriptions'),
                DB::raw('SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_subscriptions')
            ])
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('period')
            ->orderBy('period')
            ->limit($limit)
            ->get();

        return $data;
    }

    /**
     * Get delivery analytics data.
     */
    private function getDeliveryAnalytics(array $dateRange, string $period, int $limit): array
    {
        $groupBy = match($period) {
            'daily' => 'DATE(scheduled_date)',
            'weekly' => 'YEARWEEK(scheduled_date)',
            'monthly' => 'DATE_FORMAT(scheduled_date, "%Y-%m")',
        };

        $data = Delivery::select([
                DB::raw($groupBy . ' as period'),
                DB::raw('COUNT(*) as total_deliveries'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_deliveries'),
                DB::raw('AVG(CASE WHEN customer_rating IS NOT NULL THEN customer_rating END) as avg_rating')
            ])
            ->whereBetween('scheduled_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('period')
            ->orderBy('period')
            ->limit($limit)
            ->get();

        return $data->map(function ($item) {
            return [
                'period' => $item->period,
                'total_deliveries' => $item->total_deliveries,
                'completed_deliveries' => $item->completed_deliveries,
                'completion_rate' => $item->total_deliveries > 0 ? 
                    round(($item->completed_deliveries / $item->total_deliveries) * 100, 2) : 0,
                'avg_rating' => $item->avg_rating ? round($item->avg_rating, 2) : null
            ];
        });
    }

    /**
     * Get customer analytics data.
     */
    private function getCustomerAnalytics(array $dateRange, string $period, int $limit): array
    {
        $groupBy = match($period) {
            'daily' => 'DATE(created_at)',
            'weekly' => 'YEARWEEK(created_at)',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        $data = User::select([
                DB::raw($groupBy . ' as period'),
                DB::raw('COUNT(*) as new_customers')
            ])
            ->where('role', 'customer')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->groupBy('period')
            ->orderBy('period')
            ->limit($limit)
            ->get();

        return $data;
    }

    /**
     * Export users data.
     */
    private function exportUsers(array $dateRange): array
    {
        return User::where('role', 'customer')
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->with(['subscriptions'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'cpf' => $user->cpf,
                    'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                    'active_subscriptions' => $user->subscriptions->where('status', 'active')->count(),
                    'total_subscriptions' => $user->subscriptions->count(),
                ];
            })
            ->toArray();
    }

    /**
     * Export subscriptions data.
     */
    private function exportSubscriptions(array $dateRange): array
    {
        return Subscription::with(['user', 'plan'])
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'user_name' => $subscription->user->name,
                    'user_email' => $subscription->user->email,
                    'plan_name' => $subscription->plan->name,
                    'plan_price' => $subscription->plan->price,
                    'status' => $subscription->status,
                    'created_at' => $subscription->created_at->format('Y-m-d H:i:s'),
                    'next_billing_date' => $subscription->next_billing_date?->format('Y-m-d'),
                ];
            })
            ->toArray();
    }

    /**
     * Export deliveries data.
     */
    private function exportDeliveries(array $dateRange): array
    {
        return Delivery::with(['subscription.user', 'subscription.plan'])
            ->whereBetween('scheduled_date', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'user_name' => $delivery->subscription->user->name,
                    'plan_name' => $delivery->subscription->plan->name,
                    'status' => $delivery->status,
                    'scheduled_date' => $delivery->scheduled_date->format('Y-m-d'),
                    'scheduled_time' => $delivery->scheduled_time,
                    'delivered_at' => $delivery->delivered_at?->format('Y-m-d H:i:s'),
                    'customer_rating' => $delivery->customer_rating,
                    'delivery_notes' => $delivery->delivery_notes,
                ];
            })
            ->toArray();
    }

    /**
     * Export payments data.
     */
    private function exportPayments(array $dateRange): array
    {
        return Payment::with(['user', 'subscription.plan'])
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'user_name' => $payment->user->name,
                    'plan_name' => $payment->subscription->plan->name ?? null,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'billing_type' => $payment->billing_type,
                    'due_date' => $payment->due_date->format('Y-m-d'),
                    'payment_date' => $payment->payment_date?->format('Y-m-d H:i:s'),
                    'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();
    }
}
