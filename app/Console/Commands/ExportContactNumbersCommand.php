<?php

namespace App\Console\Commands;

use App\Mail\ContactNumbersExportMail;
use App\Models\Application;
use App\Models\ContactExportLog;
use App\Services\ApplicationPaymentStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * TEMPORARY weekly programme (approved while phone-number storage is being
 * minimised): export paid applications' contact/WhatsApp numbers to a private
 * CSV, email it to the owner, then remove the numbers from the live database
 * ONLY after the mail transport accepts the message.
 *
 * Safety properties (see tests/Feature/ContactNumbersExportTest.php):
 * - only applications whose payment is settled by the authoritative check are
 *   eligible; pending/failed/waived rows are never exported or nulled;
 * - rows are claimed with a batch id inside a transaction; a build or delivery
 *   failure releases the claim and keeps every phone value;
 * - exported rows keep batch id + exported_at for traceability;
 * - no phone number, email address or CSV content is ever logged;
 * - In Memoriam commissioner numbers are outside this programme entirely.
 */
class ExportContactNumbersCommand extends Command
{
    protected $signature = 'jannayaks:export-contact-numbers
        {--status : Show recent batch log entries (no personal data)}';

    protected $description = 'Weekly: export paid applications\' contact/WhatsApp numbers to the owner by email, then remove them from the live database.';

    private const DISK = 'private_uploads';

    public function handle(ApplicationPaymentStateService $stateService): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        $recipient = (string) config('jannayaks.contact.phone_export_recipient');
        if ($recipient === '') {
            $this->error('No export recipient configured (jannayaks.contact.phone_export_recipient).');

            return self::FAILURE;
        }

        $candidates = Application::query()
            ->whereNull('contact_export_batch_id')
            ->where(function ($query): void {
                $query->whereNotNull('preferred_contact_mobile')
                    ->orWhereNotNull('preferred_whatsapp_mobile');
            })
            ->orderBy('id')
            ->get();

        // Authoritative paid-status check only — never infer settlement from
        // the mere existence of a payment row.
        $eligible = $candidates->filter(
            fn (Application $application): bool => $stateService->isPaymentSettled($application)
        )->values();

        $batchId = (string) Str::uuid();

        if ($eligible->isEmpty()) {
            ContactExportLog::create([
                'batch_id' => $batchId,
                'recipient' => $recipient,
                'row_count' => 0,
                'status' => ContactExportLog::STATUS_EMPTY,
                'created_at' => now(),
            ]);
            $this->info('No eligible paid applications with contact numbers this week.');

            return self::SUCCESS;
        }

        // Claim the batch inside a transaction so two runs cannot both export
        // the same rows.
        DB::transaction(function () use ($eligible, $batchId): void {
            Application::query()
                ->whereIn('id', $eligible->pluck('id'))
                ->whereNull('contact_export_batch_id')
                ->lockForUpdate()
                ->update(['contact_export_batch_id' => $batchId]);
        });

        try {
            [$path, $fileHash] = $this->buildCsv($eligible, $batchId);
            $this->deliver($batchId, (int) $eligible->count(), $path, $recipient);
        } catch (Throwable $e) {
            // Release the claim and keep every phone value — a failed build or
            // delivery must never lose data.
            Application::query()
                ->where('contact_export_batch_id', $batchId)
                ->update(['contact_export_batch_id' => null]);

            ContactExportLog::create([
                'batch_id' => $batchId,
                'recipient' => $recipient,
                'row_count' => 0,
                'status' => ContactExportLog::STATUS_FAILED,
                'error_message' => 'Export failed: '.$e->getMessage(),
                'created_at' => now(),
            ]);
            $this->error('Export failed before delivery; phone values retained. ('.$e->getMessage().')');

            return self::FAILURE;
        }

        // Delivery accepted by the transport. Two-phase programme: the source
        // values are RETAINED until the owner runs
        // jannayaks:confirm-contact-export for this batch — deletion never
        // happens automatically and contact_exported_at is only stamped by the
        // confirmation command.
        ContactExportLog::create([
            'batch_id' => $batchId,
            'recipient' => $recipient,
            'row_count' => $eligible->count(),
            'file_path' => $path,
            'file_hash' => $fileHash,
            'status' => ContactExportLog::STATUS_AWAITING_CONFIRMATION,
            'created_at' => now(),
        ]);

        $this->info('Export batch '.$batchId.' delivered ('.$eligible->count().' applications) and marked AWAITING CONFIRMATION.');
        $this->line('Run jannayaks:confirm-contact-export '.$batchId.' after verifying the CSV to remove the exported numbers.');

        return self::SUCCESS;
    }

    public function buildCsv($applications, string $batchId): array
    {
        $directory = 'phone-exports';
        $path = $directory.'/'.$batchId.'.csv';

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'application_reference',
            'customer_name',
            'profile_url',
            'phone',
            'whatsapp',
            'payment_reference',
            'collected_at',
        ]);

        foreach ($applications as $application) {
            fputcsv($handle, [
                $this->formulaSafe((string) $application->id),
                $this->formulaSafe((string) $application->full_name),
                $this->formulaSafe((string) $this->applicationReference($application)),
                $this->formulaSafe((string) ($application->preferred_contact_mobile ?? '')),
                $this->formulaSafe((string) ($application->preferred_whatsapp_mobile ?? '')),
                $this->formulaSafe((string) $application->created_at?->toDateTimeString()),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        Storage::disk(self::DISK)->put($path, $csv);

        return [$path, hash('sha256', $csv)];
    }

    public function deliver(string $batchId, int $rowCount, string $path, string $recipient): void
    {
        Mail::to($recipient)->send(new ContactNumbersExportMail($batchId, $rowCount, $path));
    }

    /**
     * Neutralise spreadsheet formula injection (=, +, -, @, tab, CR prefixes).
     */
    private function formulaSafe(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function applicationReference(Application $application): string
    {
        $profile = $application->profile;

        if ($profile !== null && app(\App\Services\ProfileUrlService::class)->isPubliclyVisible($profile)) {
            return rtrim(url('/'), '/').'/'.$profile->slug;
        }

        return route('applications.show', ['application' => $application]);
    }

    private function showStatus(): int
    {
        $rows = ContactExportLog::query()->orderByDesc('id')->limit(12)->get();

        if ($rows->isEmpty()) {
            $this->info('No export batches recorded yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['batch_id', 'status', 'rows', 'created_at'],
            $rows->map(fn (ContactExportLog $row): array => [
                substr($row->batch_id, 0, 8),
                $row->status,
                (string) $row->row_count,
                (string) $row->created_at,
            ])->all()
        );

        return self::SUCCESS;
    }
}
