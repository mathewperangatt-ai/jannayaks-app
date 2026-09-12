<?php

namespace App\Models;

use App\Services\ApplicationPaymentStateService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Application extends Model
{
    use HasFactory;

    public const STATUS_INTAKE_IN_PROGRESS = 'intake_in_progress';

    public const STATUS_INTERVIEW_IN_PROGRESS = 'interview_in_progress';

    public const STATUS_INTERVIEW_SUBMITTED = 'interview_submitted';

    public const STATUS_DIRECT_SUBMITTED = 'direct_submitted';

    public const STATUS_PAYMENT_PENDING = 'payment_pending';

    public const STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW = 'payment_complete_awaiting_interview';

    public const STATUS_AWAITING_EDITORIAL_REVIEW = 'awaiting_editorial_review';

    public const STATUS_IN_EDITORIAL_REVIEW = 'in_editorial_review';

    public const STATUS_EDITORIAL_APPROVED = 'editorial_approved';

    public const STATUS_EDITORIAL_REVISION_REQUESTED = 'editorial_revision_requested';

    public const STATUS_AWAITING_PUBLICATION = 'awaiting_publication';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_PENDING = 'pending';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_WAIVED = 'waived';

    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $fillable = [
        'user_id',
        'profile_id',
        'source_method',
        'package_tier',
        'full_name',
        'preferred_display_name',
        'preferred_slug',
        'distinguished_interview_addon',
        'preferred_contact_email',
        'preferred_contact_mobile',
        'direct_submission_note',
        'admin_demo_audit_note',
        'waived_by_user_id',
        'intake_started_at',
        'online_interview_completed_at',
        'direct_submission_received_at',
        'converted_to_profile_at',
        'payment_status',
        'payment_settled_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'distinguished_interview_addon' => 'boolean',
            'intake_started_at'             => 'datetime',
            'online_interview_completed_at' => 'datetime',
            'direct_submission_received_at' => 'datetime',
            'converted_to_profile_at'       => 'datetime',
            'payment_settled_at'            => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by_user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'id', 'profile_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function directPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'application_id');
    }

    public function interviewAnswers(): HasMany
    {
        return $this->hasMany(InterviewAnswer::class);
    }

    public function sourceMaterials(): HasMany
    {
        return $this->hasMany(SourceMaterial::class);
    }

    public function isInterviewSubmitted(): bool
    {
        return $this->online_interview_completed_at !== null;
    }

    public function isDirectSubmitted(): bool
    {
        return $this->direct_submission_received_at !== null;
    }

    public function isPaymentSettled(): bool
    {
        return app(ApplicationPaymentStateService::class)->isPaymentSettled($this);
    }

    public function isPublished(): bool
    {
        return $this->profile_id !== null || $this->converted_to_profile_at !== null;
    }
}
