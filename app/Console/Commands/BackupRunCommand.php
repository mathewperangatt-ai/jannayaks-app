<?php

namespace App\Console\Commands;

use App\Services\Backup\AgeEncryptor;
use App\Services\Backup\PostgresDumper;
use App\Services\Backup\RetentionSelector;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Creates, encrypts, uploads and verifies one full backup archive:
 * PostgreSQL custom-format dump + every object from the configured live R2
 * media buckets + a manifest (keys, sizes, SHA-256 checksums) — tar.gz'd,
 * age-encrypted to the configured public recipient, uploaded to the
 * dedicated backup bucket and verified by streamed read-back.
 *
 * Tiering: the daily run also promotes the verified archive to weekly/ (on
 * Sundays) and monthly/ (on the 1st) via server-side copies, so recovery
 * points are preserved without re-uploading archives.
 */
class BackupRunCommand extends Command
{
    public const LOCK_NAME = 'jannayaks-backup-run';

    protected $signature = 'backup:run
        {--tier= : Override tier selection (daily|weekly|monthly); default: daily + calendar promotions}
        {--force : Run even when BACKUP_ENABLED is false}
        {--prune : Run retention pruning even when BACKUP_PRUNE_ENABLED is false}';

    protected $description = 'Create, encrypt, upload and verify an encrypted R2 backup archive';

    private string $slug;
    private string $tmpDir;
    private int $startedAt;

    public function handle(): int
    {
        $this->slug = (string) config('backup.archive_slug');
        $this->startedAt = time();

        if (! config('backup.enabled') && ! $this->option('force')) {
            $this->info('Backups are disabled (BACKUP_ENABLED=false); nothing to do.');

            return self::SUCCESS;
        }

        $lock = null;
        try {
            $lock = Cache::lock(self::LOCK_NAME, (int) config('backup.process_timeout', 900) + 300);
        } catch (Throwable) {
            // Cache locks unavailable — the scheduler's withoutOverlapping still guards runs.
        }

        if ($lock !== null && ! $lock->get()) {
            $this->warn('A backup is already running; skipping this invocation.');

            return self::SUCCESS;
        }

        try {
            $archiveKey = $this->runBackup();
            $duration = time() - $this->startedAt;
            $this->info("Backup complete in {$duration}s: {$archiveKey}");
            Log::channel(config('logging.default'))->info('backup:run completed', [
                'key' => $archiveKey,
                'duration_seconds' => $duration,
            ]);

            $this->pruneWhenEnabled();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $duration = time() - $this->startedAt;
            $this->error('Backup failed: '.$e->getMessage());
            Log::error('backup:run failed', [
                'error' => $e->getMessage(),
                'duration_seconds' => $duration,
            ]);
            $this->notifyFailure($e, $duration);

            return self::FAILURE;
        } finally {
            $this->cleanup();
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable) {
                }
            }
        }
    }

    private function runBackup(): string
    {
        $this->tmpDir = storage_path('app/backup-tmp/'.uniqid('backup-', true));
        @mkdir($this->tmpDir, 0775, true);
        if (! is_dir($this->tmpDir)) {
            throw new RuntimeException('Cannot create temporary backup directory.');
        }

        $this->line('Dumping database...');
        app(PostgresDumper::class)->dump($this->tmpDir.'/database.pgdump');
        $dbChecksum = $this->sha256File($this->tmpDir.'/database.pgdump');
        $dbBytes = filesize($this->tmpDir.'/database.pgdump') ?: 0;

        $this->line('Copying media objects...');
        $media = $this->copyMediaObjects();

        $manifest = [
            'format_version' => 1,
            'app' => $this->slug,
            'created_at' => Carbon::now()->toIso8601String(),
            'environment' => (string) app()->environment(),
            'database' => [
                'driver' => 'postgresql',
                'file' => 'database.pgdump',
                'format' => 'pg_dump custom (pg_restore compatible)',
                'bytes' => $dbBytes,
                'sha256' => $dbChecksum,
            ],
            'media' => $media,
            'media_totals' => [
                'objects' => count($media),
                'bytes' => array_sum(array_column($media, 'bytes')),
            ],
        ];
        file_put_contents($this->tmpDir.'/manifest.json', json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));

        $this->assertDiskSpaceHeadroom($dbBytes + $manifest['media_totals']['bytes']);

        $this->line('Archiving...');
        // Run tar from the temp directory's parent using relative names:
        // absolute Windows paths (C:\...) are parsed by GNU tar as host:path.
        $parent = dirname($this->tmpDir);
        $base = basename($this->tmpDir);
        $tarPath = $parent.'/'.$base.'.tar.gz';
        $this->runTar($tarPath, $parent, $base);

        $recipient = trim((string) config('backup.age_recipient'));
        $encrypted = $recipient !== '';
        if ($encrypted) {
            $this->line('Encrypting (age)...');
            $finalPath = $tarPath.'.age';
            app(AgeEncryptor::class)->encryptFile($tarPath, $finalPath, $recipient);
            @unlink($tarPath);
        } elseif (config('backup.allow_plaintext')) {
            $finalPath = $tarPath;
            $this->warn('Uploading UNENCRYPTED archive (allow_plaintext) — testing only.');
        } else {
            throw new RuntimeException('No age recipient configured (BACKUP_AGE_RECIPIENT). Refusing unencrypted upload.');
        }

        $archiveSha256 = $this->sha256File($finalPath);
        $archiveBytes = filesize($finalPath) ?: 0;

        $destination = Storage::build(config('backup.destination'));
        $tiers = $this->tiersForToday();
        $stamp = Carbon::now()->format('Ymd-His');
        $extension = $encrypted ? 'tar.gz.age' : 'tar.gz';
        $primaryTier = array_shift($tiers);
        $key = "{$primaryTier}/{$this->slug}-{$stamp}.{$extension}";

        $this->line("Uploading {$key}...");
        $stream = fopen($finalPath, 'rb');
        try {
            $destination->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->line('Verifying uploaded object...');
        if (! $this->verifyUploadedObject($destination, $key, $archiveSha256, $archiveBytes)) {
            try {
                $destination->delete($key);
            } catch (Throwable) {
            }
            throw new RuntimeException("Upload verification failed for {$key}; the incomplete object was removed.");
        }

        foreach ($tiers as $promotionTier) {
            $promotionKey = "{$promotionTier}/{$this->slug}-{$stamp}.{$extension}";
            $this->line("Promoting to {$promotionKey}...");
            $destination->copy($key, $promotionKey);
        }

        return $key;
    }

    /**
     * @return list<array{disk: string, bucket: string|null, key: string, bytes: int, sha256: string}>
     */
    private function copyMediaObjects(): array
    {
        $records = [];
        foreach ((array) config('backup.media_disks') as $diskName) {
            $diskCopied = 0;
            $base = config("filesystems.disks.{$diskName}");
            if (! is_array($base)) {
                throw new RuntimeException("Media disk '{$diskName}' is not configured.");
            }
            $disk = Storage::build(array_merge($base, ['throw' => true]));
            $bucket = $base['bucket'] ?? null;

            foreach ($disk->allFiles() as $objectKey) {
                $target = $this->tmpDir.'/media/'.$diskName.'/'.$objectKey;
                @mkdir(dirname($target), 0775, true);

                $source = $disk->readStream($objectKey);
                if (! is_resource($source)) {
                    throw new RuntimeException("Cannot read media object '{$diskName}/{$objectKey}'.");
                }

                $local = fopen($target, 'wb');
                if (! $local) {
                    fclose($source);
                    throw new RuntimeException("Cannot write temporary copy of '{$objectKey}'.");
                }

                $hash = hash_init('sha256');
                $bytes = 0;
                try {
                    while (! feof($source)) {
                        $chunk = fread($source, 1024 * 1024);
                        if ($chunk === false) {
                            break;
                        }
                        fwrite($local, $chunk);
                        hash_update($hash, $chunk);
                        $bytes += strlen($chunk);
                    }
                } finally {
                    fclose($source);
                    fclose($local);
                }

                $diskCopied++;
                $records[] = [
                    'disk' => (string) $diskName,
                    'bucket' => $bucket,
                    'key' => (string) $objectKey,
                    'bytes' => $bytes,
                    'sha256' => hash_final($hash),
                ];
            }

            $this->line("  {$diskName}: {$diskCopied} objects copied.");
        }

        return $records;
    }

    private function tiersForToday(): array
    {
        if (in_array($this->option('tier'), ['daily', 'weekly', 'monthly'], true)) {
            return [(string) $this->option('tier')];
        }

        /** @var CarbonInterface $now */
        $now = Carbon::now('Asia/Kolkata');
        $tiers = ['daily'];
        if ($now->dayOfWeekIso === 7) {
            $tiers[] = 'weekly';
        }
        if ($now->day === 1) {
            $tiers[] = 'monthly';
        }

        return $tiers;
    }

    private function runTar(string $tarPath, string $workingDir, string $base): void
    {
        $process = new Process(
            ['tar', 'czf', $base.'.tar.gz', '-C', $base, '.'],
            $workingDir,
            null,
            null,
            (int) config('backup.process_timeout', 900),
        );
        $process->run();

        if ($process->getExitCode() !== 0 || ! is_file($tarPath) || filesize($tarPath) === 0) {
            throw new RuntimeException('tar failed (exit '.$process->getExitCode().'): '
                .substr(trim($process->getErrorOutput()), 0, 300));
        }
    }

    private function sha256File(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException("Cannot checksum '{$path}'.");
        }

        return $hash;
    }

    /**
     * Stream the uploaded object back and compare size + SHA-256 without
     * loading either copy fully into memory.
     */
    private function verifyUploadedObject(
        \Illuminate\Filesystem\FilesystemAdapter $destination,
        string $key,
        string $expectedSha256,
        int $expectedBytes,
    ): bool {
        $stream = $destination->readStream($key);
        if (! is_resource($stream)) {
            return false;
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    return false;
                }
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
            }
        } finally {
            fclose($stream);
        }

        return $bytes === $expectedBytes && hash_final($hash) === $expectedSha256;
    }

    private function assertDiskSpaceHeadroom(int $estimatedBytes): void
    {
        $free = @disk_free_space(dirname($this->tmpDir));
        if ($free !== false && $free < $estimatedBytes * 3) {
            throw new RuntimeException(sprintf(
                'Insufficient temporary disk space: %d bytes free, ~%d needed (dump+media+archive+encrypted copy).',
                $free,
                $estimatedBytes * 3,
            ));
        }
    }

    private function pruneWhenEnabled(): void
    {
        if (! config('backup.prune_enabled') && ! $this->option('prune')) {
            $this->line('Pruning disabled (BACKUP_PRUNE_ENABLED=false); skipping.');

            return;
        }

        $destination = Storage::build(config('backup.destination'));
        $retention = (array) config('backup.retention');
        $keys = $destination->allFiles();
        $toDelete = RetentionSelector::selectForPrune($keys, $retention, $this->slug);

        foreach ($toDelete as $key) {
            $destination->delete($key);
            $this->line("Pruned {$key}");
        }

        if ($toDelete === []) {
            $this->line('Pruning: nothing eligible to delete.');
        }

        Log::info('backup:run pruning complete', ['deleted' => count($toDelete)]);
    }

    private function notifyFailure(Throwable $e, int $duration): void
    {
        $email = trim((string) config('backup.notification_email'));
        if ($email === '') {
            return;
        }

        try {
            Mail::raw(
                "Jannayaks automated backup failed after {$duration}s.\n\nError: {$e->getMessage()}\n\n"
                ."No previous backups were modified. Investigate scheduler logs (backup:run).",
                function ($message) use ($email) {
                    $message->to($email)->subject('[jannayaks] Backup FAILED');
                },
            );
        } catch (Throwable $mailError) {
            Log::warning('backup:run failure notification could not be sent', [
                'error' => substr($mailError->getMessage(), 0, 200),
            ]);
        }
    }

    private function cleanup(): void
    {
        if (! isset($this->tmpDir)) {
            return;
        }

        $dir = $this->tmpDir;
        $this->deleteDirRecursive($dir);
        foreach (glob($dir.'*') ?: [] as $leftover) {
            @unlink($leftover);
        }
        // Remove the shared temp root when nothing else is running.
        $root = dirname($dir);
        $remaining = array_diff(scandir($root) ?: [], ['.', '..']);
        if ($remaining === []) {
            @rmdir($root);
        }
    }

    private function deleteDirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDirRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
