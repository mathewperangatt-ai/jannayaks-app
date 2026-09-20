<?php

namespace App\Models;

use Database\Factories\ReservedSlugFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservedSlug extends Model
{
    /** @use HasFactory<ReservedSlugFactory> */
    use HasFactory;

    public const CATEGORY_POLITICAL = 'political';

    public const CATEGORY_GOVERNMENT = 'government';

    public const CATEGORY_PUBLIC_FIGURE = 'public_figure';

    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'slug',
        'category',
        'reason',
        'created_by',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_POLITICAL => 'Political party',
            self::CATEGORY_GOVERNMENT => 'Government body',
            self::CATEGORY_PUBLIC_FIGURE => 'Public figure',
            self::CATEGORY_OTHER => 'Other',
        ];
    }
}
