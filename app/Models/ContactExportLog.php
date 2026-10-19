<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit record for weekly contact-number export batches. Deliberately contains
 * no phone numbers, names or emails beyond the recipient address — the CSV
 * itself carries the personal data, encrypted in transit and stored privately.
 */
class ContactExportLog extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EMPTY = 'empty';

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'recipient',
        'row_count',
        'file_path',
        'file_hash',
        'status',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
