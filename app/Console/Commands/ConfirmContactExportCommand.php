<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ContactExportLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Second phase of the two-step export programme: after the owner has verified
 * receipt of a delivered CSV, this command removes the exported phone/WhatsApp
 * values from the live database and marks the batch confirmed.
 *
 * Safety: only rows claimed by the batch are touched; the update is guarded by
 * the batch id; a repeated run is a no-op (idempotent); nothing is deleted —
 * only the two contact columns are cleared. Run WITHOUT confirmation flags
 * only after the CSV has been verified.
 */
class ConfirmContactExportCommand extends Command
{
    protected $signature = 'jannayaks:confirm-contact-export
        {batch_id? : Batch id to confirm (defaults to the latest awaiting batch)}';

    protected $description = 'Confirm a delivered contact-numbers export and remove the exported numbers from the live database (owner action after verifying the CSV).';

    public function handle(): int
    {
        $batchId = (string) $this->argument('batch_id');

        $log = $batchId !== ''
            ? ContactExportLog::query()->where('batch_id', $batchId)->first()
            : ContactExportLog::query()->where('status', ContactExportLog::STATUS_AWAITING_CONFIRMATION)->orderByDesc('id')->first();

        if ($log === null) {
            $this->error($batchId !== ''
                ? 'No export batch found for "'.$batchId.'".'
                : 'No awaiting export batches found.');

            return self::FAILURE;
        }

        $batchId = $log->batch_id;

        if ($log->status === ContactExportLog::STATUS_CONFIRMED) {
            $this->warn('Batch '.substr($batchId, 0, 8).' was already confirmed. Nothing to do.');

            return self::SUCCESS;
        }

        if ($log->status !== ContactExportLog::STATUS_AWAITING_CONFIRMATION) {
            $this->error('Batch '.substr($batchId, 0, 8).' is not awaiting confirmation (status: '.$log->status.').');

            return self::FAILURE;
        }

        // Remove the exported numbers. Guarded by the batch id and wrapped in
        // a transaction so the marker and the cleared values move together.
        DB::transaction(function () use ($batchId): void {
            Application::query()
                ->where('contact_export_batch_id', $batchId)
                ->update([
                    'preferred_contact_mobile' => null,
                    'preferred_whatsapp_mobile' => null,
                    'contact_exported_at' => now(),
                ]);

            ContactExportLog::query()
                ->where('batch_id', $batchId)
                ->update(['status' => ContactExportLog::STATUS_CONFIRMED]);
        });

        $count = Application::query()->where('contact_export_batch_id', $batchId)->count();

        $this->info('Batch '.substr($batchId, 0, 8).' confirmed: contact values removed from '.$count.' application(s).');
        $this->line('CSV file retained privately at: '.$log->file_path);

        return self::SUCCESS;
    }
}
