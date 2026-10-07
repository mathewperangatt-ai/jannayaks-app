<?php

namespace App\Contracts;

/**
 * Independent image-enhancement abstraction (never coupled to the editorial
 * AI client). Implementations receive the OPTIMIZED source JPEG bytes and
 * must return enhanced image bytes on success; any failure throws.
 */
interface PhotoEnhancementClient
{
    public function providerName(): string;

    public function modelName(): string;

    /**
     * Enhance an optimized customer portrait.
     *
     * @param  string  $sourceJpegBytes  the stored optimized source image
     * @param  string  $prompt           the master enhancement direction
     * @return string enhanced image bytes (any common raster format; the
     *                service normalizes/encodes before storage)
     *
     * @throws \RuntimeException on provider/configuration failure
     */
    public function enhance(string $sourceJpegBytes, string $prompt): string;
}
