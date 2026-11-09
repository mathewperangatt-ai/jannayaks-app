<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\SlugReservation;
use App\Models\SourceMaterial;
use App\Models\StaffActionLog;
use App\Support\InterviewSubmissionDeadline;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Day-11 erasure of incomplete Online Interview applications.
 *
 * Day 1 is the business-timezone calendar date of the customer's first
 * questionnaire start (InterviewSubmissionDeadline is the single source of
 * truth for every date). Day 10 is the last day to submit; from Day 11 an
 * unsubmitted application is erased with its working records. Payment or
 * waiver status never protects an application; submitted, published, inactive,
 * In Memoriam (classified in its own in_memoriam_* tables and never reached by
 * this flow), demo and admin-test/seed records are always protected.
 *
 * Retained by design: the customer account, payments and all invoice/tax
 * references (detached via the nullable foreign keys), consent evidence and
 * staff audit logs — including the audit entry written before deletion.
 */
class IntakeExpiryService
{
    public const EXPIRED_ACTION = 'application.intake_expired';

    public const PENDING_ACTION = 'application.intake_expiry_files_pending';

    public const CLEARED_ACTION = 'application.intake_expiry_files_cleared';

    public function __construct(private StaffAuditLogger $auditLogger) {}

    /**
     * Applications eligible for erasure as of the given date (defaults to now).
     * The per-application re-check inside expire() remains authoritative.
     *
     * @return \Illuminate\Support\Collection<int, Application>
     */
    public function eligibleApplications(?CarbonImmutable $asOf = null): \Illuminate\Support\Collection
    {
        return Application::query()
            ->whereNotNull('online_interview_started_at')
            ->whereNull('online_interview_completed_at')
            ->where('source_method', 'online_interview')
            ->orderBy('id')
            ->get()
            ->filter(fn (Application $application) => $this->isEligible($application, $asOf))
            ->values();
    }

    public function isEligible(Application $application, ?CarbonImmutable $asOf = null): bool
    {
        // In Memoriam records are classified in their own tables and flow; the
        // tier value here is a legacy classification kept for compatibility.
        // Safeguards act on the actual record classification: intake source,
        // tier value and any in_memoriam-sourced reservation.
        if ($application->package_tier === 'in_memoriam'
            || $application->source_method !== 'online_interview'
            || $application->online_interview_started_at === null
            || $application->isInterviewSubmitted()
            || $application->status === Application::STATUS_PUBLISHED) {
            return false;
        }

        if (SlugReservation::query()
            ->where('application_id', $application->id)
            ->where('source', SlugReservation::SOURCE_IN_MEMORIAM)
            ->exists()) {
            return false;
        }

        $profile = $application->profile_id !== null
            ? Profile::query()->find($application->profile_id)
            : null;

        // Living profiles never carry a memorial status; a published or
        // inactive living profile is likewise protected.
        if ($profile instanceof Profile
            && in_array($profile->status, ['published', 'inactive', 'archived'], true)) {
            return false;
        }

        return InterviewSubmissionDeadline::isDueForErasure($application, $asOf);
    }

    /**
     * Dry-run description: who would be erased and exactly what would go.
     *
     * @return array<string, mixed>
     */
    public function describe(Application $application, ?CarbonImmutable $asOf = null): array
    {
        $manifest = $this->fileManifest($application);

        return [
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'full_name' => $application->full_name,
            'payment_status' => $application->payment_status,
            'status' => $application->status,
            'started_at' => $application->online_interview_started_at?->toIso8601String(),
            'first_day' => InterviewSubmissionDeadline::startDay($application)?->toDateString(),
            'last_submission_day' => InterviewSubmissionDeadline::lastSubmissionDay($application)?->toDateString(),
            'erasure_day' => InterviewSubmissionDeadline::erasureDay($application)?->toDateString(),
            'interview_answers' => $application->interviewAnswers()->count(),
            'source_materials' => $manifest['source_materials'],
            'profile_id' => $application->profile_id,
            'editorial_versions' => $application->profile_id !== null
                ? EditorialContent::query()->where('profile_id', $application->profile_id)->count()
                : 0,
            'media_items' => $manifest['media_items'],
            'slug_reservations_released' => SlugReservation::query()->where('application_id', $application->id)->count(),
            'files' => $manifest['files'],
        ];
    }

    /**
     * Erase one application inside a locked transaction. The submitted state
     * is re-checked under lock immediately before deletion, so a customer who
     * submits concurrently is never erased.
     *
     * @return array{deleted: bool, reason: ?string, audit_id: ?int, manifest: array<string, mixed>}
     */
    public function expire(Application $application, ?CarbonImmutable $asOf = null): array
    {
        return DB::transaction(function () use ($application, $asOf): array {
            /** @var Application $locked */
            $locked = Application::query()->whereKey($application->id)->lockForUpdate()->first();

            if (! $locked instanceof Application) {
                return ['deleted' => false, 'reason' => 'already_erased', 'audit_id' => null, 'manifest' => []];
            }

            if ($locked->isInterviewSubmitted()) {
                return ['deleted' => false, 'reason' => 'submitted', 'audit_id' => null, 'manifest' => []];
            }

            if (! $this->isEligible($locked, $asOf)) {
                return ['deleted' => false, 'reason' => 'no_longer_eligible', 'audit_id' => null, 'manifest' => []];
            }

            $profile = $locked->profile_id !== null
                ? Profile::query()->whereKey($locked->profile_id)->lockForUpdate()->first()
                : null;
            $manifest = $this->fileManifest($locked);

            $this->auditLogger->log(
                action: self::EXPIRED_ACTION,
                subject: $locked,
                before: [
                    'application_id' => $locked->id,
                    'user_id' => $locked->user_id,
                    'full_name' => $locked->full_name,
                    'status' => $locked->status,
                    'payment_status' => $locked->payment_status,
                    'source_method' => $locked->source_method,
                    'online_interview_started_at' => $locked->online_interview_started_at?->toIso8601String(),
                    'profile_id' => $locked->profile_id,
                    'interview_answers' => $locked->interviewAnswers()->count(),
                    'source_materials' => $manifest['source_materials'],
                    'media_items' => $manifest['media_items'],
                ],
                after: [
                    'reason' => 'Online Interview not submitted within ten calendar days of first start.',
                    'erasure_day' => InterviewSubmissionDeadline::erasureDay($locked)?->toDateString(),
                    'files' => $manifest['files'],
                ],
                actor: null,
            );

            // Photo/enhancement rows are polymorphic (no cascade) — explicit,
            // after the manifest is recorded. Their runs cascade or detach.
            if ($profile instanceof Profile) {
                MediaItem::query()
                    ->where('mediable_type', $profile->getMorphClass())
                    ->where('mediable_id', $profile->id)
                    ->delete();
            }

            // Explicitly releases the preferred URL reservation.
            SlugReservation::query()->where('application_id', $locked->id)->delete();

            // Cascades interview answers, source-material rows, AI runs,
            // claim traces, revision requests and customer approvals; detaches
            // payments and consent records via the nullable foreign keys.
            $locked->delete();

            if ($profile instanceof Profile) {
                // Cascades editorial contents, geographies, public offices,
                // verifications, external links, integrity snapshots/incidents
                // and public engagement; detaches memberships.
                $profile->delete();
            }

            return ['deleted' => true, 'reason' => null, 'audit_id' => null, 'manifest' => $manifest];
        });
    }

    /**
     * Delete the physical files recorded in a manifest. Returns the entries
     * that could not be removed (recorded for reconciliation, never silent).
     *
     * @param  array{files: array<int, array{disk: string, path: string, kind: string, id: int|string}>}  $manifest
     * @return array<int, array{disk: string, path: string, kind: string, id: int|string}>
     */
    public function deleteManifestFiles(array $manifest): array
    {
        $failed = [];

        foreach ($manifest['files'] as $file) {
            try {
                if (Storage::disk($file['disk'])->delete($file['path']) !== true) {
                    $failed[] = $file;
                }
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $file;
            }
        }

        return $failed;
    }

    /**
     * Physical files belonging to the application: uploaded source materials
     * and every profile media row (photographs and private enhancement
     * candidates). Database cascades never remove files.
     *
     * @return array{source_materials: int, media_items: int, files: array<int, array{disk: string, path: string, kind: string, id: int|string}>}
     */
    public function fileManifest(Application $application): array
    {
        $files = [];

        SourceMaterial::query()
            ->where('application_id', $application->id)
            ->orderBy('id')
            ->get(['id', 'storage_disk', 'storage_path'])
            ->each(function (SourceMaterial $material) use (&$files) {
                if (filled($material->storage_disk) && filled($material->storage_path)) {
                    $files[] = ['disk' => $material->storage_disk, 'path' => $material->storage_path, 'kind' => 'source_material', 'id' => $material->id];
                }
            });

        if ($application->profile_id !== null) {
            $profile = Profile::query()->find($application->profile_id);
            if ($profile instanceof Profile) {
                MediaItem::query()
                    ->where('mediable_type', $profile->getMorphClass())
                    ->where('mediable_id', $profile->id)
                    ->orderBy('id')
                    ->get(['id', 'disk', 'storage_path_key'])
                    ->each(function (MediaItem $media) use (&$files) {
                        if (filled($media->disk) && filled($media->storage_path_key)) {
                            $files[] = ['disk' => $media->disk, 'path' => $media->storage_path_key, 'kind' => 'media_item', 'id' => $media->id];
                        }
                    });
            }
        }

        return [
            'source_materials' => $files === [] ? 0 : count(array_filter($files, fn (array $f) => $f['kind'] === 'source_material')),
            'media_items' => count(array_filter($files, fn (array $f) => $f['kind'] === 'media_item')),
            'files' => $files,
        ];
    }

    /**
     * Reconcile physical files for erased applications whose files were not
     * confirmed deleted. The source of truth is each committed
     * application.intake_expired record: it is written inside the deletion
     * transaction, so its file manifest survives even if the process stopped
     * after the database deletion committed and before any pending-files
     * entry was written. Applications already marked cleared are skipped, and
     * files already gone are not deleted again; each application is marked
     * cleared exactly once.
     *
     * @return array{cleared: int, failed: array<int, array{disk: string, path: string}>}
     */
    public function retryPendingFiles(): array
    {
        $cleared = 0;
        $failed = [];

        $expiredRecords = StaffActionLog::query()
            ->where('action', self::EXPIRED_ACTION)
            ->orderBy('id')
            ->get();

        foreach ($expiredRecords as $record) {
            $applicationId = (int) ($record->subject_id ?? ($record->before['application_id'] ?? 0));
            if ($applicationId <= 0 || $this->filesCleared($applicationId)) {
                continue;
            }

            $remaining = [];
            foreach ((array) ($record->after['files'] ?? []) as $file) {
                $file = (array) $file;
                $disk = (string) ($file['disk'] ?? '');
                $path = (string) ($file['path'] ?? '');
                if ($disk === '' || $path === '') {
                    continue;
                }

                try {
                    if (! Storage::disk($disk)->exists($path)) {
                        continue;
                    }
                    if (Storage::disk($disk)->delete($path) === true && ! Storage::disk($disk)->exists($path)) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }

                $remaining[] = ['disk' => $disk, 'path' => $path];
            }

            if ($remaining === []) {
                $this->markFilesCleared($applicationId, count((array) ($record->after['files'] ?? [])));
                $cleared++;
            } else {
                $failed = array_merge($failed, $remaining);
            }
        }

        return ['cleared' => $cleared, 'failed' => $failed];
    }

    /** True once the application's physical files are confirmed deleted. */
    public function filesCleared(int $applicationId): bool
    {
        return StaffActionLog::query()
            ->where('action', self::CLEARED_ACTION)
            ->where('subject_type', (new Application)->getMorphClass())
            ->where('subject_id', $applicationId)
            ->exists();
    }

    /** Records, once per application, that every physical file is deleted. */
    public function markFilesCleared(int $applicationId, int $fileCount): void
    {
        if ($this->filesCleared($applicationId)) {
            return;
        }

        $this->auditLogger->log(
            action: self::CLEARED_ACTION,
            subject: (new Application)->forceFill(['id' => $applicationId]),
            after: ['application_id' => $applicationId, 'files' => $fileCount],
            actor: null,
        );
    }

    /**
     * Record physical files that could not be deleted after a successful
     * database erasure, for operator visibility. Reconciliation itself reads
     * the committed application.intake_expired manifest (retryPendingFiles).
     *
     * @param  array<int, array{disk: string, path: string, kind: string, id: int|string}>  $failed
     */
    public function recordPendingFiles(int $applicationId, array $failed): void
    {
        $this->auditLogger->log(
            action: self::PENDING_ACTION,
            subject: null,
            after: [
                'application_id' => $applicationId,
                'paths' => array_map(fn (array $file) => ['disk' => $file['disk'], 'path' => $file['path']], $failed),
            ],
            actor: null,
        );
    }
}
