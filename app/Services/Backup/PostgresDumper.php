<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Consistent PostgreSQL dumps via pg_dump (custom format, restorable with
 * pg_restore). Credentials are taken from the application's configured
 * connection and passed to the subprocess through its environment
 * (PGPASSWORD) — never on the command line.
 */
class PostgresDumper
{
    public function __construct(
        private ?int $timeout = null,
        private ?string $binary = null,
    ) {}

    public function dump(string $targetPath): void
    {
        $cfg = DB::connection()->getConfig();
        $binary = $this->binary ?? (string) config('backup.pg_dump_binary', 'pg_dump');
        $timeout = $this->timeout ?? (int) config('backup.process_timeout', 900);

        $command = [
            $binary,
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--file='.$targetPath,
            (string) ($cfg['database'] ?? ''),
        ];

        $env = [
            'PGHOST' => (string) ($cfg['host'] ?? ''),
            'PGPORT' => (string) ($cfg['port'] ?? 5432),
            'PGDATABASE' => (string) ($cfg['database'] ?? ''),
            'PGUSER' => (string) ($cfg['username'] ?? ''),
            'PGPASSWORD' => (string) ($cfg['password'] ?? ''),
            'PGSSLMODE' => (string) ($cfg['sslmode'] ?? 'prefer'),
        ];

        $process = new Process($command, null, $env, null, $timeout);
        $process->run();

        if ($process->getExitCode() !== 0) {
            throw new RuntimeException('pg_dump failed (exit '.$process->getExitCode().'): '
                .substr(trim($process->getErrorOutput()), 0, 500));
        }

        $this->assertValidCustomDump($targetPath);
    }

    /**
     * Custom-format dumps start with the 5-byte "PGDMP" magic.
     */
    private function assertValidCustomDump(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('pg_dump produced no output.');
        }

        $handle = fopen($path, 'rb');
        $magic = (string) fread($handle, 5);
        fclose($handle);

        if ($magic !== 'PGDMP') {
            throw new RuntimeException('pg_dump output is not a valid custom-format archive.');
        }
    }
}
