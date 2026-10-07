<?php

namespace App\Services\Ai;

use App\Contracts\PhotoEnhancementClient;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Deterministic test/local implementation: re-encodes the optimized source
 * through the same Intervention pipeline at a slightly different quality so
 * the candidate is a valid, reproducible JPEG derived from the source.
 * No network calls, no API key.
 */
class FakePhotoEnhancementClient implements PhotoEnhancementClient
{
    public bool $failNext = false;

    public string $failMessage = 'Simulated enhancement failure';

    public function providerName(): string
    {
        return 'fake';
    }

    public function modelName(): string
    {
        return 'fake-photo-enhancement-v1';
    }

    public function enhance(string $sourceJpegBytes, string $prompt): string
    {
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException($this->failMessage);
        }

        $manager = new ImageManager(new Driver);
        $image = $manager->read($sourceJpegBytes);
        $image->orient();

        $quality = (int) config('jannayaks.media.optimization.jpeg_quality', 82);
        $encoded = (string) $image->encode(new JpegEncoder(quality: max(40, $quality - 4)));

        if (strlen($encoded) < 64) {
            throw new RuntimeException('Fake enhancement produced unusable data.');
        }

        return $encoded;
    }
}
