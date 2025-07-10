<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'frequency',
        'delivery_days',
        'bread_quantity',
        'bread_types',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'delivery_days' => 'array',
        'bread_types' => 'array',
        'is_active' => 'boolean',
        'bread_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get active subscriptions for this plan.
     */
    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->where('status', 'active');
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    /**
     * Get frequency label.
     */
    public function getFrequencyLabelAttribute(): string
    {
        $labels = [
            'daily' => 'Diário',
            'alternate_days' => 'Dias alternados',
            'weekends' => 'Fins de semana',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
        ];

        return $labels[$this->frequency] ?? $this->frequency;
    }

    /**
     * Get delivery days labels.
     */
    public function getDeliveryDaysLabelsAttribute(): array
    {
        if (!$this->delivery_days) {
            return [];
        }

        $dayLabels = [
            0 => 'Domingo',
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
        ];

        return array_map(function ($day) use ($dayLabels) {
            return $dayLabels[$day] ?? $day;
        }, $this->delivery_days);
    }

    /**
     * Get bread types as string.
     */
    public function getBreadTypesStringAttribute(): string
    {
        if (!$this->bread_types) {
            return 'Não especificado';
        }

        return implode(', ', $this->bread_types);
    }

    /**
     * Check if plan is available for subscription.
     */
    public function isAvailable(): bool
    {
        return $this->is_active;
    }

    /**
     * Get next delivery date based on frequency and delivery days.
     */
    public function getNextDeliveryDate(\DateTime $startDate = null): ?\DateTime
    {
        $startDate = $startDate ?: new \DateTime();
        
        switch ($this->frequency) {
            case 'daily':
                return (clone $startDate)->modify('+1 day');
                
            case 'alternate_days':
                return (clone $startDate)->modify('+2 days');
                
            case 'weekly':
                return (clone $startDate)->modify('+1 week');
                
            case 'monthly':
                return (clone $startDate)->modify('+1 month');
                
            case 'weekends':
                $next = clone $startDate;
                while (!in_array($next->format('w'), [0, 6])) { // 0 = Sunday, 6 = Saturday
                    $next->modify('+1 day');
                }
                return $next;
                
            default:
                if ($this->delivery_days) {
                    $next = clone $startDate;
                    $next->modify('+1 day');
                    
                    while (!in_array($next->format('w'), $this->delivery_days)) {
                        $next->modify('+1 day');
                    }
                    
                    return $next;
                }
                
                return (clone $startDate)->modify('+1 day');
        }
    }

    /**
     * Calculate monthly price based on frequency.
     */
    public function getMonthlyPriceAttribute(): float
    {
        switch ($this->frequency) {
            case 'daily':
                return $this->price * 30;
            case 'alternate_days':
                return $this->price * 15;
            case 'weekends':
                return $this->price * 8; // ~8 weekends per month
            case 'weekly':
                return $this->price * 4;
            case 'monthly':
                return $this->price;
            default:
                if ($this->delivery_days) {
                    $deliveriesPerWeek = count($this->delivery_days);
                    return $this->price * $deliveriesPerWeek * 4;
                }
                return $this->price * 30;
        }
    }
}
