<?php

namespace Tests\Feature;

use App\Services\Backup\AgeEncryptor;
use App\Services\Backup\PostgresDumper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * End-to-end backup:run tests against local disk destinations and local
 * media-disk fakes. The database dumper is faked for determinism; the real
 * pg_dump path has its own integration test below.
 */
class BackupRunCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $destRoot;
    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->destRoot = storage_path('app/backup-test-dest');
        $this->mediaRoot = storage_path('app/backup-test-media');
        File::ensureDirectoryExists($this->destRoot);
        File::ensureDirectoryExists($this->mediaRoot);

        // Destination: local disk standing in for the R2 backup bucket.
        config()->set('backup.destination', [
            'driver' => 'local',
            'root' => $this->destRoot,
            'throw' => true,
        ]);

        // Media disks: point the configured disk names at local fixtures.
        config()->set('filesystems.disks.r2', [
            'driver' => 'local',
            'root' => $this->mediaRoot.'/media',
            'throw' => true,
        ]);
        config()->set('filesystems.disks.source_materials', [
            'driver' => 'local',
            'root' => $this->mediaRoot.'/sources',
            'throw' => true,
        ]);

        Storage::disk('r2')->put('profile-media/profiles/10/portrait.jpg', 'media-bytes-one');
        Storage::disk('source_materials')->put('source-materials/applications/18/doc.pdf', 'source-bytes-two');

        config()->set('backup.enabled', true);
        config()->set('backup.allow_plaintext', true); // tests exercise encryption separately
        config()->set('backup.age_recipient', '');
        config()->set('backup.notification_email', '');

        // Deterministic dumper stub (valid custom-format magic).
        $fakeDumper = new class
        {
            public function dump(string $targetPath): void
            {
                file_put_contents($targetPath, 'PGDMP'.str_repeat('x', 256));
            }
        };
        app()->instance(PostgresDumper::class, $fakeDumper);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->destRoot);
        File::deleteDirectory($this->mediaRoot);
        File::deleteDirectory(storage_path('app/backup-tmp'));
        parent::tearDown();
    }

    public function test_skips_when_disabled(): void
    {
        config()->set('backup.enabled', false);

        $this->artisan('backup:run')->assertSuccessful()->expectsOutputToContain('disabled');

        $this->assertEmpty(Storage::build(config('backup.destination'))->allFiles());
    }

    public function test_creates_verified_archive_with_dump_media_and_manifest(): void
    {
        config()->set('backup.retention', ['daily' => 7, 'weekly' => 4, 'monthly' => 6]);

        $this->artisan('backup:run --tier=daily')->assertSuccessful();

        $destination = Storage::build(config('backup.destination'));
        $keys = $destination->allFiles();
        $this->assertCount(1, $keys);
        $this->assertMatchesRegularExpression('#^daily/jannayaks-\d{8}-\d{6}\.tar\.gz$#', $keys[0]);

        // Extract and inspect the archive contents. The archive is copied
        // into the extract directory first so tar only sees relative names
        // (avoids the Windows GNU-tar "C:" host-parsing quirk).
        $extract = $this->destRoot.'/extracted';
        File::ensureDirectoryExists($extract.'/'.dirname($keys[0]));
        File::copy($this->destRoot.'/'.$keys[0], $extract.'/'.$keys[0]);
        $extractProcess = new \Symfony\Component\Process\Process(
            ['tar', 'xzf', $keys[0]],
            $extract,
            null,
            null,
            120,
        );
        $extractProcess->run();
        $this->assertSame(0, $extractProcess->getExitCode(), $extractProcess->getErrorOutput());

        $this->assertFileExists($extract.'/database.pgdump');
        $this->assertFileExists($extract.'/media/r2/profile-media/profiles/10/portrait.jpg');
        $this->assertFileExists($extract.'/media/source_materials/source-materials/applications/18/doc.pdf');

        $manifest = json_decode((string) File::get($extract.'/manifest.json'), true);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame('jannayaks', $manifest['app']);
        $this->assertSame(261, $manifest['database']['bytes']);
        $this->assertSame(hash('sha256', 'PGDMP'.str_repeat('x', 256)), $manifest['database']['sha256']);
        $this->assertSame(2, $manifest['media_totals']['objects']);
        $this->assertSame(hash('sha256', 'media-bytes-one'), $manifest['media'][0]['sha256']);
        $this->assertStringNotContainsString('SECRET', (string) File::get($extract.'/manifest.json'));

        File::deleteDirectory($extract);
    }

    public function test_fails_closed_without_recipient_or_plaintext_flag(): void
    {
        config()->set('backup.allow_plaintext', false);

        $this->artisan('backup:run --tier=daily')->assertFailed();

        $this->assertEmpty(Storage::build(config('backup.destination'))->allFiles());
    }

    public function test_failed_backup_removes_incomplete_upload_and_preserves_previous_backups(): void
    {
        $destination = Storage::build(config('backup.destination'));
        $previousKey = 'daily/jannayaks-20261101-031500.tar.gz';
        $destination->put($previousKey, 'previous-complete-archive');

        $explodingDumper = new class
        {
            public function dump(string $targetPath): void
            {
                throw new RuntimeException('pg_dump exploded');
            }
        };
        app()->instance(PostgresDumper::class, $explodingDumper);

        $this->artisan('backup:run --tier=daily')->assertFailed();

        $this->assertTrue($destination->exists($previousKey), 'Previous backups must survive a failure.');
        $this->assertSame([$previousKey], $destination->allFiles(), 'No new archive may remain after failure.');
        $this->assertFileDoesNotExist(storage_path('app/backup-tmp'));
    }

    public function test_prune_respects_retention_and_never_touches_foreign_keys(): void
    {
        config()->set('backup.retention', ['daily' => 2, 'weekly' => 1, 'monthly' => 6]);
        $destination = Storage::build(config('backup.destination'));
        foreach (['20261101', '20261102', '20261103'] as $d) {
            $destination->put("daily/jannayaks-{$d}-031500.tar.gz", 'x');
        }
        $destination->put('weekly/jannayaks-20261011-031500.tar.gz', 'x');
        $destination->put('weekly/jannayaks-20261018-031500.tar.gz', 'x');
        $destination->put('daily/iconsherald-20261101-031500.tar.gz', 'foreign');
        $destination->put('notes.txt', 'untouchable');

        $this->artisan('backup:run --tier=daily --prune')->assertSuccessful();

        $remaining = $destination->allFiles();
        sort($remaining);
        $this->assertContains('daily/iconsherald-20261101-031500.tar.gz', $remaining);
        $this->assertContains('notes.txt', $remaining);
        $this->assertNotContains('daily/jannayaks-20261101-031500.tar.gz', $remaining);
        $this->assertNotContains('daily/jannayaks-20261102-031500.tar.gz', $remaining);
        $this->assertContains('daily/jannayaks-20261103-031500.tar.gz', $remaining);
        $this->assertNotContains('weekly/jannayaks-20261011-031500.tar.gz', $remaining);
        $this->assertContains('weekly/jannayaks-20261018-031500.tar.gz', $remaining);
        // Remaining: 2 newest dailies (today's new archive + 20261103),
        // 1 newest weekly, the foreign-slug archive, notes.txt.
        $this->assertCount(5, $remaining);
    }

    public function test_command_is_registered_and_scheduled(): void
    {
        $this->assertArrayHasKey('backup:run', Artisan::all());
        $this->assertArrayHasKey('backup:download', Artisan::all());

        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $found = false;
        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, 'backup:run')) {
                $found = true;
                $this->assertSame('15 3 * * *', $event->expression);
                $this->assertSame('Asia/Kolkata', (string) $event->timezone);
            }
        }
        $this->assertTrue($found, 'backup:run must be scheduled at 03:15 Asia/Kolkata.');
    }

    /**
     * Real pg_dump integration (skipped when the binary is unavailable).
     */
    public function test_real_pg_dump_produces_valid_custom_format_dump(): void
    {
        $binary = 'C:/Program Files/PostgreSQL/18/bin/pg_dump.exe';
        if (! is_file($binary)) {
            $this->markTestSkipped('pg_dump not available locally.');
        }

        $target = storage_path('app/backup-test-dump.pgdump');
        @unlink($target);

        try {
            (new \App\Services\Backup\PostgresDumper(900, $binary))->dump($target);
            $this->assertFileExists($target);
            $handle = fopen($target, 'rb');
            $this->assertSame('PGDMP', (string) fread($handle, 5));
            fclose($handle);
        } finally {
            @unlink($target);
        }
    }
}
