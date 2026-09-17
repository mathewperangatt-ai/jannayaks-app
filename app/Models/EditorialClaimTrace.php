<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditorialClaimTrace extends Model
{
    protected $fillable = [
        'editorial_content_id',
        'sort_order',
        'claim_excerpt',
        'question_id',
        'interview_answer_id',
        'source_material_id',
        'mapped_to_source',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'mapped_to_source' => 'bool',
        ];
    }

    /**
     * Internal editorial QA only. mapped_to_source means a source row was found —
     * it is NOT independent verification that the claim is true.
     */
    public function isMappedToSource(): bool
    {
        return (bool) $this->mapped_to_source;
    }

    public function editorialContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class);
    }

    public function interviewAnswer(): BelongsTo
    {
        return $this->belongsTo(InterviewAnswer::class);
    }

    public function sourceMaterial(): BelongsTo
    {
        return $this->belongsTo(SourceMaterial::class);
    }
}
