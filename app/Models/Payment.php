<?php

namespace App\Models;

use App\Support\PricingAmounts;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_PAID = 'paid';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const ITEM_MEMBERSHIP = 'membership';

    public const ITEM_IN_MEMORIAM = 'in_memoriam';

    public const ITEM_PROFILE_PACKAGE = 'profile_package';

    public const ITEM_DISTINGUISHED_INTERVIEW_ADDON = 'distinguished_interview_addon';

    public const ITEM_APPLICATION_PAYMENT = 'application_payment';

    public const ITEM_REFUND = 'refund';

    public const ITEM_OTHER = 'other';

    public const EVENT_LINK_PAID = 'payment_link.paid';

    public const EVENT_PAYMENT_CAPTURED = 'payment.captured';

    public const EVENT_PAYMENT_FAILED = 'payment.failed';

    public const EVENT_REFUND_PROCESSED = 'refund.processed';

    public const EVENT_REFUND_FAILED = 'refund.failed';

    public const EVENT_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const EVENT_WAIVER = 'waiver';

    public const GATEWAY_RAZORPAY = 'razorpay';

    public const GATEWAY_UNKNOWN = 'unknown';

    public const GATEWAY_MANUAL = 'manual';

    public const GATEWAY_WAIVER = 'waiver';

    private const STATE_RANK = [
        self::STATUS_PENDING => 1,
        self::STATUS_INITIATED => 2,
        self::STATUS_FAILED => 3,
        self::STATUS_CANCELLED => 3,
        self::STATUS_EXPIRED => 3,
        self::STATUS_PAID => 10,
        self::STATUS_CAPTURED => 11,
        self::STATUS_SUCCESS => 12,
        self::STATUS_PARTIALLY_REFUNDED => 20,
        self::STATUS_REFUNDED => 21,
    ];

    protected $fillable = [
        'membership_id',
        'application_id',
        'in_memoriam_profile_id',
        'profile_id',
        'payable_type',
        'payable_id',
        'transaction_reference',
        'gateway',
        'item_type',
        'amount',
        'currency',
        'status',
        'paid_at',
        'captured_at',
        'method',
        'gateway_event_id',
        'gateway_payment_id',
        'payment_method_type',
        'card_last4',
        'razorpay_link_id',
        'razorpay_link_url',
        'razorpay_order_id',
        'event_type',
        'base_amount',
        'taxable_amount',
        'gst_rate_percent',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'error_code',
        'error_message',
        'waiver_reason',
        'waived_by_user_id',
        'waived_at',
        'refund_amount',
        'refunded_at',
        'refund_gateway_id',
        'refund_note',
        'invoice_number',
        'invoice_issued_at',
        'tax_invoice_number',
        'tax_invoice_issued_at',
        'credit_note_number',
        'credit_note_issued_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'gst_rate_percent' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'captured_at' => 'datetime',
            'waived_at' => 'datetime',
            'refunded_at' => 'datetime',
            'invoice_issued_at' => 'datetime',
            'tax_invoice_issued_at' => 'datetime',
            'credit_note_issued_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function inMemoriamProfile(): BelongsTo
    {
        return $this->belongsTo(InMemoriamProfile::class, 'in_memoriam_profile_id');
    }

    public function waivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by_user_id');
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function canTransitionTo(string $newStatus): bool
    {
        if (! isset(self::STATE_RANK[$newStatus])) {
            return false;
        }
        if (! isset(self::STATE_RANK[$this->status])) {
            return true;
        }

        $currentRank = self::STATE_RANK[$this->status];
        $newRank = self::STATE_RANK[$newStatus];

        if ($newRank >= 10 && $currentRank >= 20) {
            return false;
        }

        if ($newRank >= 10 && $currentRank >= 10 && $newRank < $currentRank) {
            return false;
        }

        if ($currentRank >= 10 && in_array($newStatus, [self::STATUS_FAILED, self::STATUS_CANCELLED, self::STATUS_EXPIRED], true)) {
            return false;
        }

        if ($newStatus === self::STATUS_PENDING && $currentRank > 2) {
            return false;
        }

        // Superseded / terminal failure attempts must never resurrect to paid.
        if ($newRank >= 10 && in_array($this->status, [
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ], true)) {
            return false;
        }

        return true;
    }

    public function isActiveAttempt(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_INITIATED,
        ], true);
    }

    public function isSettled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_CAPTURED,
            self::STATUS_SUCCESS,
            self::STATUS_PARTIALLY_REFUNDED,
            self::STATUS_REFUNDED,
        ], true);
    }

    public function isPaidOrBetter(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_CAPTURED,
            self::STATUS_SUCCESS,
        ], true);
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function markInitiated(?string $gateway = null, ?DateTimeInterface $at = null): self
    {
        $target = self::STATUS_INITIATED;
        if (! $this->canTransitionTo($target)) {
            return $this;
        }
        $this->status = $target;
        if ($gateway !== null) {
            $this->gateway = $gateway;
        }
        if ($at !== null) {
            // no initiated_at column; leave timestamps alone
        }
        $this->save();

        return $this;
    }

    public function markPaidOrCaptured(
        string $targetStatus,
        ?string $gatewayPaymentId = null,
        ?string $gatewayEventId = null,
        ?string $eventType = null,
        ?DateTimeInterface $paidAt = null,
        ?DateTimeInterface $capturedAt = null,
    ): self {
        if (! in_array($targetStatus, [self::STATUS_PAID, self::STATUS_CAPTURED, self::STATUS_SUCCESS], true)) {
            throw new InvalidArgumentException('Invalid settled status: '.$targetStatus);
        }
        if (! $this->canTransitionTo($targetStatus)) {
            return $this;
        }
        $now = now();
        $this->status = $targetStatus;
        if ($gatewayPaymentId !== null && $gatewayPaymentId !== '') {
            $this->gateway_payment_id = $gatewayPaymentId;
        }
        if ($gatewayEventId !== null && $gatewayEventId !== '') {
            $this->gateway_event_id = $gatewayEventId;
        }
        if ($eventType !== null && $eventType !== '') {
            $this->event_type = $eventType;
        }
        if ($this->paid_at === null) {
            $this->paid_at = $paidAt ?? $now;
        }
        if ($targetStatus === self::STATUS_CAPTURED && $this->captured_at === null) {
            $this->captured_at = $capturedAt ?? $now;
        } elseif ($targetStatus === self::STATUS_SUCCESS && $this->captured_at === null) {
            $this->captured_at = $capturedAt ?? $now;
        }
        $this->save();

        return $this;
    }

    public function markFailedExpiredOrCancelled(
        string $targetStatus,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        ?string $gatewayEventId = null,
        ?string $eventType = null,
    ): self {
        if (! in_array($targetStatus, [self::STATUS_FAILED, self::STATUS_EXPIRED, self::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException('Invalid failed-type status: '.$targetStatus);
        }
        if (! $this->canTransitionTo($targetStatus)) {
            return $this;
        }
        $this->status = $targetStatus;
        if ($errorCode !== null && $errorCode !== '') {
            $this->error_code = $errorCode;
        }
        if ($errorMessage !== null && $errorMessage !== '') {
            $this->error_message = mb_substr($errorMessage, 0, 255);
        }
        if ($gatewayEventId !== null && $gatewayEventId !== '') {
            $this->gateway_event_id = $gatewayEventId;
        }
        if ($eventType !== null && $eventType !== '') {
            $this->event_type = $eventType;
        }
        $this->save();

        return $this;
    }

    public function markRefunded(
        int|float|string $refundAmount,
        ?string $refundGatewayId = null,
        ?string $refundNote = null,
        ?DateTimeInterface $refundedAt = null,
    ): self {
        if ($this->isRefunded()) {
            return $this;
        }
        if (! $this->isPaidOrBetter()) {
            throw new InvalidArgumentException('Cannot refund a non-settled payment.');
        }
        $this->status = self::STATUS_REFUNDED;
        $this->refund_amount = $refundAmount;
        $this->refunded_at = $refundedAt ?? now();
        if ($refundGatewayId !== null && $refundGatewayId !== '') {
            $this->refund_gateway_id = $refundGatewayId;
        }
        if ($refundNote !== null && $refundNote !== '') {
            $this->refund_note = mb_substr($refundNote, 0, 255);
        }
        $this->save();

        return $this;
    }

    public function scopeByApplication(Builder $query, int $applicationId): Builder
    {
        return $query->where('application_id', $applicationId);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->whereHas('application', function (Builder $sub) use ($userId) {
            $sub->where('user_id', $userId);
        });
    }

    public function scopeActiveAttempts(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_INITIATED]);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PAID,
            self::STATUS_CAPTURED,
            self::STATUS_SUCCESS,
            self::STATUS_PARTIALLY_REFUNDED,
            self::STATUS_REFUNDED,
        ]);
    }

    public function scopeAwaitingReconciliation(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_PAID, self::STATUS_CAPTURED, self::STATUS_SUCCESS])
            ->whereNull('invoice_number');
    }

    public function scopeUnpaidOverdue(Builder $query, int $olderThanHours = 48): Builder
    {
        $cutoff = now()->subHours($olderThanHours);

        return $query
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_INITIATED])
            ->where('created_at', '<', $cutoff);
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REFUNDED);
    }

    public function scopeGateway(Builder $query, string $gateway): Builder
    {
        return $query->where('gateway', $gateway);
    }

    public function totalPaise(): int
    {
        $amount = (string) $this->amount;
        if ($amount === '' || $amount === null) {
            return 0;
        }
        if (! is_numeric($amount)) {
            return 0;
        }

        return (int) round((float) $amount * PricingAmounts::PAISE_PER_RUPEE);
    }

    public function currencyIsInr(): bool
    {
        return strtoupper((string) $this->currency) === PricingAmounts::CURRENCY;
    }
}
