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

    public const PRICING_GENERATION_STANDARD = 'standard_profile';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const DUPLICATE_EMAIL_NOTICE = 'An application or profile is already associated with this email address. Only one published profile is permitted per email address. For assistance or to discuss an existing application or profile, please contact contactus@jannayaks.in.';

    /**
     * Statuses that are still moving through intake, payment, or editorial work.
     * Email occupancy is separate: any stored application occupies its email,
     * including cancelled, refunded, and archived applications.
     *
     * @return list<string>
     */
    public static function openLifecycleStatuses(): array
    {
        return [
            self::STATUS_INTAKE_IN_PROGRESS,
            self::STATUS_INTERVIEW_IN_PROGRESS,
            self::STATUS_INTERVIEW_SUBMITTED,
            self::STATUS_DIRECT_SUBMITTED,
            self::STATUS_PAYMENT_PENDING,
            self::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW,
            self::STATUS_AWAITING_EDITORIAL_REVIEW,
            self::STATUS_IN_EDITORIAL_REVIEW,
            self::STATUS_EDITORIAL_APPROVED,
            self::STATUS_EDITORIAL_REVISION_REQUESTED,
            self::STATUS_AWAITING_PUBLICATION,
            self::STATUS_PUBLISHED,
        ];
    }

    public static function normalizeApplicantEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * True when this email belongs to another account, or is already stored on
     * an application in any status. The ignored application and user are the
     * record currently being edited, so a person is not blocked by their own account.
     */
    public static function emailIsAssociated(?string $email, ?int $ignoreApplicationId = null, ?int $ignoreUserId = null): bool
    {
        $email = self::normalizeApplicantEmail($email);
        if ($email === null) {
            return false;
        }

        $applications = self::query()->where('applicant_email_normalized', $email);
        if ($ignoreApplicationId !== null) {
            $applications->whereKeyNot($ignoreApplicationId);
        }
        if ($applications->exists()) {
            return true;
        }

        $contact = self::query()->whereRaw('lower(trim(preferred_contact_email)) = ?', [$email]);
        if ($ignoreApplicationId !== null) {
            $contact->whereKeyNot($ignoreApplicationId);
        }
        if ($contact->exists()) {
            return true;
        }

        $users = User::query()->whereRaw('lower(trim(email)) = ?', [$email]);
        if ($ignoreUserId !== null) {
            $users->whereKeyNot($ignoreUserId);
        }

        return $users->exists();
    }

    public const PAYMENT_STATUS_PENDING = 'pending';

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_WAIVED = 'waived';

    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * @return array<string, string>
     */
    public static function workflowStatusLabels(): array
    {
        return [
            self::STATUS_INTAKE_IN_PROGRESS => 'Intake in progress',
            self::STATUS_INTERVIEW_IN_PROGRESS => 'Interview in progress',
            self::STATUS_INTERVIEW_SUBMITTED => 'Interview submitted',
            self::STATUS_DIRECT_SUBMITTED => 'Direct submitted',
            self::STATUS_PAYMENT_PENDING => 'Payment pending',
            self::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW => 'Paid — awaiting interview',
            self::STATUS_AWAITING_EDITORIAL_REVIEW => 'Awaiting editorial',
            self::STATUS_IN_EDITORIAL_REVIEW => 'In editorial review',
            self::STATUS_EDITORIAL_APPROVED => 'Editorial approved',
            self::STATUS_EDITORIAL_REVISION_REQUESTED => 'Editorial revision requested',
            self::STATUS_AWAITING_PUBLICATION => 'Awaiting publication',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_ARCHIVED => 'Archived',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    /**
     * Staff-editable editorial handoff statuses for P9 (no publication workflow).
     *
     * @return list<string>
     */
    public static function editorialHandoffStatuses(): array
    {
        return [
            self::STATUS_AWAITING_EDITORIAL_REVIEW,
            self::STATUS_IN_EDITORIAL_REVIEW,
            self::STATUS_EDITORIAL_APPROVED,
            self::STATUS_EDITORIAL_REVISION_REQUESTED,
        ];
    }

    public function isReadyForEditorialQueue(): bool
    {
        if (in_array($this->status, [
            self::STATUS_AWAITING_EDITORIAL_REVIEW,
            self::STATUS_IN_EDITORIAL_REVIEW,
            self::STATUS_EDITORIAL_APPROVED,
            self::STATUS_EDITORIAL_REVISION_REQUESTED,
        ], true)) {
            return true;
        }

        if ($this->status === self::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW
            && ($this->isInterviewSubmitted() || $this->isDirectSubmitted())) {
            return true;
        }

        return false;
    }

    protected $fillable = [
        'user_id',
        'applicant_email_normalized',
        'source_method',
        'package_tier',
        'pricing_generation',
        'full_name',
        'preferred_display_name',
        'preferred_slug',
        'broad_location',
        'distinguished_interview_addon',
        'preferred_contact_email',
        'preferred_contact_mobile',
        'preferred_whatsapp_mobile',
        'direct_submission_note',
        'intake_started_at',
        'online_interview_completed_at',
        'questionnaire_confirmed_at',
        'questionnaire_version',
        'questionnaire_submission_sha256',
        'first_customer_preview_at',
        'direct_submission_received_at',
    ];

    protected function casts(): array
    {
        return [
            'distinguished_interview_addon' => 'boolean',
            'intake_started_at' => 'datetime',
            'online_interview_completed_at' => 'datetime',
            'questionnaire_confirmed_at' => 'datetime',
            'direct_submission_received_at' => 'datetime',
            'converted_to_profile_at' => 'datetime',
            'payment_settled_at' => 'datetime',
            'included_revision_rounds_used' => 'integer',
            'customer_preview_released_at' => 'datetime',
            'first_customer_preview_at' => 'datetime',
            'customer_approved_at' => 'datetime',
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

    /** @return HasMany<MediaItem> */
    public function profilePhotos(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'mediable_id', 'profile_id')
            ->where('mediable_type', (new Profile)->getMorphClass())
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->orderByDesc('is_primary')
            ->orderBy('display_order');
    }

    /** @return HasMany<ProfileExternalLink> */
    public function profileExternalLinks(): HasMany
    {
        return $this->hasMany(ProfileExternalLink::class, 'profile_id', 'profile_id')
            ->orderByDesc('id');
    }

    /**
     * Malayalam editorial versions for the linked profile only.
     * Empty when the application has no profile_id.
     *
     * @return HasMany<EditorialContent>
     */
    public function malayalamEditorialContents(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'profile_id', 'profile_id')
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->orderByDesc('version_number');
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

    public function editorialRevisionRequests(): HasMany
    {
        return $this->hasMany(EditorialRevisionRequest::class);
    }

    public function editorialCustomerApprovals(): HasMany
    {
        return $this->hasMany(EditorialCustomerApproval::class);
    }

    public function previewEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'preview_english_editorial_content_id');
    }

    public function previewMalayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'preview_malayalam_editorial_content_id');
    }

    public function customerApprovedEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'customer_approved_english_editorial_content_id');
    }

    public function publishedEnglishContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'published_english_editorial_content_id');
    }

    public function publishedMalayalamContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'published_malayalam_editorial_content_id');
    }

    public function interviewIsOpenBeforePayment(): bool
    {
        if (in_array($this->status, [
            self::STATUS_REFUNDED,
            self::STATUS_CANCELLED,
            self::STATUS_ARCHIVED,
        ], true)) {
            return false;
        }

        if ($this->payment_status === self::PAYMENT_STATUS_REFUNDED) {
            return false;
        }

        return $this->source_method === 'online_interview' && ! $this->isInterviewSubmitted();
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

    /**
     * Historical helper: application has been linked to a profile record.
     * Prefer status === STATUS_PUBLISHED for publication-terminal checks.
     */
    public function isPublished(): bool
    {
        return $this->profile_id !== null || $this->converted_to_profile_at !== null;
    }

    public function isWorkflowPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * After final member approval (or publication), the member must not directly
     * alter approved/public profile content. Staff corrections remain separate.
     */
    public function memberDirectEditsLocked(): bool
    {
        if ($this->customer_approved_at !== null) {
            return true;
        }

        return in_array($this->status, [
            self::STATUS_AWAITING_PUBLICATION,
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ], true);
    }
}
