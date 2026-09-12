<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Application extends Model
{
    use HasFactory;

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
    ];

    protected function casts(): array
    {
        return [
            'distinguished_interview_addon' => 'boolean',
            'intake_started_at' => 'datetime',
            'online_interview_completed_at' => 'datetime',
            'direct_submission_received_at' => 'datetime',
            'converted_to_profile_at' => 'datetime',
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
}
