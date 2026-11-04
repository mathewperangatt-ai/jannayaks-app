<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Restore-side helper: fetch one backup archive from the dedicated backup
 * bucket to the local machine, verify its SHA-256, and (optionally) decrypt
 * it with the owner-held age identity. Read-only against the bucket.
 */
class BackupDownloadCommand extends Command
{
    protected $signature = 'backup:download
        {key : Object key inside the backup bucket (e.g. daily/jannayaks-20261105-031500.tar.gz.age)}
        {--output= : Output file path (default: storage/app/backups/<basename>)}
        {--expected-sha256= : Verify the downloaded archive against this checksum}
        {--identity= : age private identity file used to decrypt after download}';

    protected $description = 'Download (and optionally decrypt) a backup archive for restoration';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $destination = Storage::build(config('backup.destination'));

        if (! $destination->exists($key)) {
            $this->error("Object not found in backup bucket: {$key}");

            return self::FAILURE;
        }

        $output = (string) ($this->option('output') !== null
            ? $this->option('output')
            : storage_path('app/backups/'.basename($key)));
        @mkdir(dirname($output), 0775, true);

        $this->line("Downloading {$key}...");
        $stream = $destination->readStream($key);
        if (! is_resource($stream)) {
            $this->error('Cannot read object stream.');

            return self::FAILURE;
        }

        $hash = hash_init('sha256');
        $bytes = 0;
        $local = fopen($output, 'wb');
        if (! $local) {
            fclose($stream);
            $this->error("Cannot write {$output}");

            return self::FAILURE;
        }
        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    break;
                }
                fwrite($local, $chunk);
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
            }
        } finally {
            fclose($stream);
            fclose($local);
        }

        $sha256 = hash_final($hash);
        $this->info(sprintf('Downloaded %d bytes, sha256=%s', $bytes, $sha256));

        if (($expected = (string) $this->option('expected-sha256')) !== '') {
            if ($expected !== $sha256) {
                $this->error('Checksum mismatch — archive corrupted in transit.');

                return self::FAILURE;
            }
            $this->info('Checksum verified.');
        }

        $identity = (string) $this->option('identity');
        if ($identity !== '') {
            $decrypted = preg_replace('/\.age$/', '', $output);
            $this->line("Decrypting to {$decrypted}...");
            try {
                $process = new Process(
                    [(string) config('backup.age_binary', 'age'), '--decrypt', '--identity', $identity, '--output', $decrypted, $output],
                    null,
                    null,
                    null,
                    (int) config('backup.process_timeout', 900),
                );
                $process->run();
                if ($process->getExitCode() !== 0) {
                    throw new RuntimeException(substr(trim($process->getErrorOutput()), 0, 300));
                }
                $this->info('Decrypted archive ready: '.$decrypted);
            } catch (Throwable $e) {
                $this->error('Decryption failed: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
