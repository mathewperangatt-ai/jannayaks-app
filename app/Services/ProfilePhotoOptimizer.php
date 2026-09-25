<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

class ProfilePhotoOptimizer
{
    /**
     * Resize and compress an uploaded photograph; store only the processed JPEG.
     * The original oversized upload is never persisted to the media disk.
     *
     * Config (jannayaks.media.optimization):
     * - max_edge_px: longest side after scale-down (default 1600)
     * - jpeg_quality: JPEG quality 0–100 (default 82)
     *
     * @return array{path: string, mime_type: string, size_bytes: int, width: int, height: int}
     */
    public function processAndStore(UploadedFile $file, string $disk, string $objectKey): array
    {
        $maxEdge = max(320, (int) config('jannayaks.media.optimization.max_edge_px', 1600));
        $jpegQuality = min(95, max(40, (int) config('jannayaks.media.optimization.jpeg_quality', 82)));

        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());
        $image->orient();
        $image->scaleDown(width: $maxEdge, height: $maxEdge);

        $encoded = $image->encode(new JpegEncoder(quality: $jpegQuality));
        $bytes = (string) $encoded;

        // Integrity hash of the EXACT bytes written to storage and later served
        // publicly. Captured here — the single point where the final JPEG exists.
        $sha256 = hash('sha256', $bytes);

        $finalKey = preg_replace('/\.[^.]+$/', '.jpg', $objectKey) ?: ($objectKey.'.jpg');
        $written = Storage::disk($disk)->put($finalKey, $bytes);
        if ($written !== true) {
            throw new RuntimeException('Failed to store the optimized photograph.');
        }

        return [
            'path' => $finalKey,
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'width' => $image->width(),
            'height' => $image->height(),
            'sha256' => $sha256,
        ];
    }
}
