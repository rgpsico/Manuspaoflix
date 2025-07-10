<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'user_id',
        'asaas_payment_id',
        'asaas_invoice_url',
        'amount',
        'status',
        'billing_type',
        'due_date',
        'payment_date',
        'description',
        'asaas_response',
        'failure_reason',
        'retry_count',
        'last_retry_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
        'asaas_response' => 'array',
        'retry_count' => 'integer',
        'last_retry_at' => 'datetime',
    ];

    /**
     * Get the subscription for this payment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the user for this payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get pending payments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get confirmed payments.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope to get received payments.
     */
    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'received');
    }

    /**
     * Scope to get overdue payments.
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue');
    }

    /**
     * Scope to get refunded payments.
     */
    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', 'refunded');
    }

    /**
     * Scope to get cancelled payments.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to get payments due today.
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', Carbon::today());
    }

    /**
     * Scope to get payments due in the next days.
     */
    public function scopeDueInDays(Builder $query, int $days): Builder
    {
        return $query->whereBetween('due_date', [
            Carbon::today(),
            Carbon::today()->addDays($days)
        ]);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'pending' => 'Pendente',
            'confirmed' => 'Confirmado',
            'received' => 'Recebido',
            'overdue' => 'Vencido',
            'refunded' => 'Reembolsado',
            'cancelled' => 'Cancelado',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get billing type label.
     */
    public function getBillingTypeLabelAttribute(): string
    {
        $labels = [
            'BOLETO' => 'Boleto',
            'CREDIT_CARD' => 'Cartão de Crédito',
            'PIX' => 'PIX',
            'DEBIT_CARD' => 'Cartão de Débito',
        ];

        return $labels[$this->billing_type] ?? $this->billing_type;
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->amount, 2, ',', '.');
    }

    /**
     * Get formatted due date.
     */
    public function getFormattedDueDateAttribute(): string
    {
        return $this->due_date->format('d/m/Y');
    }

    /**
     * Get formatted payment date.
     */
    public function getFormattedPaymentDateAttribute(): string
    {
        return $this->payment_date ? $this->payment_date->format('d/m/Y') : 'Não pago';
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if payment is received.
     */
    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    /**
     * Check if payment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->isPending() && $this->due_date->isPast());
    }

    /**
     * Check if payment is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Check if payment is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if payment is paid (confirmed or received).
     */
    public function isPaid(): bool
    {
        return in_array($this->status, ['confirmed', 'received']);
    }

    /**
     * Check if payment is due today.
     */
    public function isDueToday(): bool
    {
        return $this->due_date->isToday();
    }

    /**
     * Get days until due date.
     */
    public function getDaysUntilDueAttribute(): int
    {
        return Carbon::today()->diffInDays($this->due_date, false);
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdueAttribute(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return $this->due_date->diffInDays(Carbon::today());
    }

    /**
     * Mark payment as confirmed.
     */
    public function markAsConfirmed(Carbon $paymentDate = null): void
    {
        $this->update([
            'status' => 'confirmed',
            'payment_date' => $paymentDate ?: Carbon::today(),
        ]);
    }

    /**
     * Mark payment as received.
     */
    public function markAsReceived(Carbon $paymentDate = null): void
    {
        $this->update([
            'status' => 'received',
            'payment_date' => $paymentDate ?: Carbon::today(),
        ]);
    }

    /**
     * Mark payment as overdue.
     */
    public function markAsOverdue(): void
    {
        $this->update(['status' => 'overdue']);
    }

    /**
     * Mark payment as refunded.
     */
    public function markAsRefunded(string $reason = null): void
    {
        $this->update([
            'status' => 'refunded',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Cancel payment.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Increment retry count.
     */
    public function incrementRetryCount(): void
    {
        $this->update([
            'retry_count' => $this->retry_count + 1,
            'last_retry_at' => now(),
        ]);
    }

    /**
     * Check if payment can be retried.
     */
    public function canBeRetried(): bool
    {
        return $this->isPending() && $this->retry_count < 3;
    }

    /**
     * Update Asaas response.
     */
    public function updateAsaasResponse(array $response): void
    {
        $this->update(['asaas_response' => $response]);
    }

    /**
     * Get Asaas payment URL.
     */
    public function getAsaasPaymentUrlAttribute(): ?string
    {
        if ($this->billing_type === 'BOLETO' && $this->asaas_invoice_url) {
            return $this->asaas_invoice_url;
        }

        if ($this->asaas_response && isset($this->asaas_response['invoiceUrl'])) {
            return $this->asaas_response['invoiceUrl'];
        }

        return null;
    }

    /**
     * Get PIX QR code.
     */
    public function getPixQrCodeAttribute(): ?string
    {
        if ($this->billing_type === 'PIX' && $this->asaas_response) {
            return $this->asaas_response['encodedImage'] ?? null;
        }

        return null;
    }

    /**
     * Get PIX copy and paste code.
     */
    public function getPixCopyPasteAttribute(): ?string
    {
        if ($this->billing_type === 'PIX' && $this->asaas_response) {
            return $this->asaas_response['payload'] ?? null;
        }

        return null;
    }

    /**
     * Check if payment has invoice URL.
     */
    public function hasInvoiceUrl(): bool
    {
        return !is_null($this->getAsaasPaymentUrlAttribute());
    }

    /**
     * Get customer name.
     */
    public function getCustomerNameAttribute(): string
    {
        return $this->user->name ?? 'N/A';
    }

    /**
     * Get subscription plan name.
     */
    public function getPlanNameAttribute(): string
    {
        return $this->subscription->plan->name ?? 'N/A';
    }
}
