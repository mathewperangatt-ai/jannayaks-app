<?php

namespace App\Services;

use App\Models\Profile;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;
use InvalidArgumentException;

class ProfileQrCodeService
{
    public function __construct(private ProfileUrlService $profileUrls) {}

    /**
     * Build a PNG QR encoding only the current public canonical HTTPS/HTTP URL.
     */
    public function buildForProfile(Profile $profile): ResultInterface
    {
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            throw new InvalidArgumentException('QR codes are only available for published profiles.');
        }

        $url = $this->profileUrls->canonicalPublicUrl($profile);
        if ($url === null) {
            throw new InvalidArgumentException('This profile does not have a canonical public URL yet.');
        }

        $this->assertPublicCanonicalPayload($url);

        return Builder::create()
            ->writer(new PngWriter)
            ->writerOptions([])
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(280)
            ->margin(12)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->validateResult(false)
            ->build();
    }

    public function pngBytes(Profile $profile): string
    {
        return $this->buildForProfile($profile)->getString();
    }

    public function encodedPayload(Profile $profile): string
    {
        $url = $this->profileUrls->canonicalPublicUrl($profile);
        if ($url === null) {
            throw new InvalidArgumentException('This profile does not have a canonical public URL yet.');
        }

        $this->assertPublicCanonicalPayload($url);

        return $url;
    }

    private function assertPublicCanonicalPayload(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/p/')) {
            throw new InvalidArgumentException('QR payload must be a public /p/{slug} profile URL.');
        }

        $lower = strtolower($url);
        foreach (['/admin', '/staff', '/applications/', '/filament', '/livewire', '/preview'] as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                throw new InvalidArgumentException('QR payload must not encode internal or preview URLs.');
            }
        }
    }
}
