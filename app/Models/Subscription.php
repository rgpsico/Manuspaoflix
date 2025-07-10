<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'address_id',
        'asaas_subscription_id',
        'status',
        'start_date',
        'end_date',
        'next_delivery_date',
        'price',
        'preferences',
        'special_instructions',
        'paused_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_delivery_date' => 'date',
        'price' => 'decimal:2',
        'preferences' => 'array',
        'paused_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the plan for this subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the address for this subscription.
     */
    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    /**
     * Get the deliveries for this subscription.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Get the payments for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get paused subscriptions.
     */
    public function scopePaused(Builder $query): Builder
    {
        return $query->where('status', 'paused');
    }

    /**
     * Scope to get cancelled subscriptions.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get subscriptions with pending payments.
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', 'pending_payment');
    }

    /**
     * Scope to get subscriptions due for delivery.
     */
    public function scopeDueForDelivery(Builder $query, Carbon $date = null): Builder
    {
        $date = $date ?: Carbon::today();
        
        return $query->where('status', 'active')
                    ->where('next_delivery_date', '<=', $date);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Ativa',
            'paused' => 'Pausada',
            'cancelled' => 'Cancelada',
            'pending_payment' => 'Pagamento Pendente',
            'expired' => 'Expirada',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is paused.
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if subscription has pending payment.
     */
    public function hasPendingPayment(): bool
    {
        return $this->status === 'pending_payment';
    }

    /**
     * Pause the subscription.
     */
    public function pause(string $reason = null): void
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Resume the subscription.
     */
    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'paused_at' => null,
            'next_delivery_date' => $this->plan->getNextDeliveryDate(new \DateTime()),
        ]);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'end_date' => now()->toDateString(),
        ]);
    }

    /**
     * Mark subscription as pending payment.
     */
    public function markAsPendingPayment(): void
    {
        $this->update(['status' => 'pending_payment']);
    }

    /**
     * Mark subscription as active (payment confirmed).
     */
    public function markAsActive(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Update next delivery date.
     */
    public function updateNextDeliveryDate(): void
    {
        if ($this->isActive()) {
            $nextDate = $this->plan->getNextDeliveryDate($this->next_delivery_date);
            $this->update(['next_delivery_date' => $nextDate]);
        }
    }

    /**
     * Get days until next delivery.
     */
    public function getDaysUntilNextDeliveryAttribute(): int
    {
        if (!$this->next_delivery_date) {
            return 0;
        }

        return Carbon::today()->diffInDays($this->next_delivery_date, false);
    }

    /**
     * Check if subscription is due for delivery today.
     */
    public function isDueToday(): bool
    {
        return $this->isActive() && 
               $this->next_delivery_date && 
               $this->next_delivery_date->isToday();
    }

    /**
     * Check if subscription is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->isActive() && 
               $this->next_delivery_date && 
               $this->next_delivery_date->isPast();
    }

    /**
     * Get total deliveries count.
     */
    public function getTotalDeliveriesAttribute(): int
    {
        return $this->deliveries()->count();
    }

    /**
     * Get completed deliveries count.
     */
    public function getCompletedDeliveriesAttribute(): int
    {
        return $this->deliveries()->where('status', 'delivered')->count();
    }

    /**
     * Get pending deliveries count.
     */
    public function getPendingDeliveriesAttribute(): int
    {
        return $this->deliveries()->whereIn('status', ['scheduled', 'in_route'])->count();
    }

    /**
     * Get subscription duration in days.
     */
    public function getDurationInDaysAttribute(): int
    {
        $endDate = $this->end_date ?: Carbon::today();
        return $this->start_date->diffInDays($endDate);
    }
}
