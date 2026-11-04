<?php

namespace App\Services\Backup;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * age authenticated encryption (X25519 recipients, ChaCha20-Poly1305,
 * age spec v1 — https://age-encryption.org/v1).
 *
 * The backup process only ever holds the PUBLIC recipient key (age1...);
 * decryption requires the owner-retained private identity (AGE-SECRET-KEY-1...)
 * which never enters Railway or the backup bucket.
 */
class AgeEncryptor
{
    public const FORMAT_MAGIC = 'age-encryption.org/v1';

    public function __construct(
        private ?string $binary = null,
        private ?int $timeout = null,
    ) {}

    public function encryptFile(string $sourcePath, string $targetPath, string $recipient): void
    {
        if (trim($recipient) === '' || ! str_starts_with(trim($recipient), 'age1')) {
            throw new RuntimeException('Invalid age recipient: expected an age1... public key.');
        }

        $binary = $this->binary ?? (string) config('backup.age_binary', 'age');
        $timeout = $this->timeout ?? (int) config('backup.process_timeout', 900);

        $process = new Process(
            [$binary, '--recipient', trim($recipient), '--output', $targetPath, $sourcePath],
            null,
            null,
            null,
            $timeout,
        );
        $process->run();

        if ($process->getExitCode() !== 0) {
            throw new RuntimeException('age encryption failed (exit '.$process->getExitCode().'): '
                .substr(trim($process->getErrorOutput() ?: $process->getOutput()), 0, 400));
        }

        $this->assertEncrypted($targetPath);
    }

    public function assertEncrypted(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('age produced no output.');
        }

        $handle = fopen($path, 'rb');
        $header = (string) fread($handle, strlen(self::FORMAT_MAGIC));
        fclose($handle);

        if ($header !== self::FORMAT_MAGIC) {
            throw new RuntimeException('Encrypted archive is missing the age v1 header.');
        }
    }
}
