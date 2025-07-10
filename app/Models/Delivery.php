<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'delivery_person_id',
        'scheduled_date',
        'scheduled_time_start',
        'scheduled_time_end',
        'status',
        'delivered_at',
        'delivery_notes',
        'customer_feedback',
        'rating',
        'items_delivered',
        'delivery_photo',
        'delivery_latitude',
        'delivery_longitude',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time_start' => 'datetime:H:i',
        'scheduled_time_end' => 'datetime:H:i',
        'delivered_at' => 'datetime',
        'items_delivered' => 'array',
        'rating' => 'integer',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
    ];

    /**
     * Get the subscription for this delivery.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the customer for this delivery.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the delivery person for this delivery.
     */
    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_person_id');
    }

    /**
     * Scope to get scheduled deliveries.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get deliveries in route.
     */
    public function scopeInRoute(Builder $query): Builder
    {
        return $query->where('status', 'in_route');
    }

    /**
     * Scope to get delivered deliveries.
     */
    public function scopeDelivered(Builder $query): Builder
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope to get failed deliveries.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get cancelled deliveries.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get deliveries for a specific date.
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('scheduled_date', $date);
    }

    /**
     * Scope to get deliveries for today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('scheduled_date', Carbon::today());
    }

    /**
     * Scope to get deliveries assigned to a delivery person.
     */
    public function scopeAssignedTo(Builder $query, int $deliveryPersonId): Builder
    {
        return $query->where('delivery_person_id', $deliveryPersonId);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'scheduled' => 'Agendada',
            'in_route' => 'Em rota',
            'delivered' => 'Entregue',
            'failed' => 'Falhou',
            'cancelled' => 'Cancelada',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get formatted scheduled time.
     */
    public function getFormattedScheduledTimeAttribute(): string
    {
        if (!$this->scheduled_time_start) {
            return 'Não definido';
        }

        $start = $this->scheduled_time_start->format('H:i');
        $end = $this->scheduled_time_end ? $this->scheduled_time_end->format('H:i') : '';

        return $end ? "{$start} - {$end}" : $start;
    }

    /**
     * Check if delivery is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if delivery is in route.
     */
    public function isInRoute(): bool
    {
        return $this->status === 'in_route';
    }

    /**
     * Check if delivery is completed.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if delivery failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if delivery is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if delivery is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->isScheduled() && $this->scheduled_date->isPast();
    }

    /**
     * Check if delivery is due today.
     */
    public function isDueToday(): bool
    {
        return $this->scheduled_date->isToday();
    }

    /**
     * Mark delivery as in route.
     */
    public function markAsInRoute(): void
    {
        $this->update(['status' => 'in_route']);
    }

    /**
     * Mark delivery as delivered.
     */
    public function markAsDelivered(array $data = []): void
    {
        $updateData = array_merge([
            'status' => 'delivered',
            'delivered_at' => now(),
        ], $data);

        $this->update($updateData);
    }

    /**
     * Mark delivery as failed.
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'delivery_notes' => $reason,
        ]);
    }

    /**
     * Cancel delivery.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'delivery_notes' => $reason,
        ]);
    }

    /**
     * Assign delivery to a delivery person.
     */
    public function assignTo(User $deliveryPerson): void
    {
        if (!$deliveryPerson->isDeliveryPerson()) {
            throw new \InvalidArgumentException('User is not a delivery person');
        }

        $this->update(['delivery_person_id' => $deliveryPerson->id]);
    }

    /**
     * Get delivery address from subscription.
     */
    public function getDeliveryAddressAttribute(): ?Address
    {
        return $this->subscription->address ?? null;
    }

    /**
     * Get customer name.
     */
    public function getCustomerNameAttribute(): string
    {
        return $this->customer->name ?? 'N/A';
    }

    /**
     * Get delivery person name.
     */
    public function getDeliveryPersonNameAttribute(): string
    {
        return $this->deliveryPerson->name ?? 'Não atribuído';
    }

    /**
     * Check if delivery has coordinates.
     */
    public function hasDeliveryCoordinates(): bool
    {
        return !is_null($this->delivery_latitude) && !is_null($this->delivery_longitude);
    }

    /**
     * Get rating stars.
     */
    public function getRatingStarsAttribute(): string
    {
        if (!$this->rating) {
            return 'Não avaliado';
        }

        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Check if delivery can be rated.
     */
    public function canBeRated(): bool
    {
        return $this->isDelivered() && is_null($this->rating);
    }

    /**
     * Rate the delivery.
     */
    public function rate(int $rating, string $feedback = null): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        if (!$this->canBeRated()) {
            throw new \InvalidArgumentException('Delivery cannot be rated');
        }

        $this->update([
            'rating' => $rating,
            'customer_feedback' => $feedback,
        ]);
    }
}
