<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_id',
        'application_id',
        'in_memoriam_profile_id',
        'profile_id',
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
    ];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:2',
            'base_amount'        => 'decimal:2',
            'taxable_amount'     => 'decimal:2',
            'gst_rate_percent'   => 'decimal:2',
            'cgst_amount'        => 'decimal:2',
            'sgst_amount'        => 'decimal:2',
            'igst_amount'        => 'decimal:2',
            'refund_amount'      => 'decimal:2',
            'paid_at'            => 'datetime',
            'captured_at'        => 'datetime',
            'waived_at'          => 'datetime',
            'refunded_at'        => 'datetime',
            'invoice_issued_at'  => 'datetime',
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
}
