<?php

namespace App\Security;

/**
 * Automated image-safety screening for user-submitted photograph
 * replacements (Wave 3).
 *
 * Deterministic checks only — this service never "approves" content. It
 * classifies a submission as 'clear' or 'flagged'; FLAGGED submissions can
 * never be auto-published and require the reviewing Security Administrator to
 * explicitly confirm manual examination before approval becomes possible.
 */
class PhotoSafetyScreeningService
{
    public const VERDICT_CLEAR = 'clear';

    public const VERDICT_FLAGGED = 'flagged';

    /** @return array{verdict: string, reasons: list<string>, width: ?int, height: ?int} */
    public function screen(string $absolutePath, string $clientMimeType, int $sizeBytes): array
    {
        $reasons = [];

        // Extension/MIME agreement.
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($clientMimeType, $allowedMimes, true)) {
            $reasons[] = 'unsupported_mime_type';
        }

        // Real image content check (decodes the actual bytes).
        $width = null;
        $height = null;
        try {
            $info = @getimagesize($absolutePath);
            if ($info === false) {
                $reasons[] = 'not_a_decodable_image';
            } else {
                [, , $mime] = $info;
                $width = (int) $info[0];
                $height = (int) $info[1];

                if (isset($info['mime']) && ! in_array((string) $info['mime'], $allowedMimes, true)) {
                    $reasons[] = 'decoded_mime_not_allowed';
                }
                if ($width < 400 || $height < 300) {
                    $reasons[] = 'dimensions_below_minimum';
                }
                $aspect = $height > 0 ? $width / $height : 0;
                if ($aspect < 0.45 || $aspect > 2.4) {
                    $reasons[] = 'extreme_aspect_ratio';
                }
            }
        } catch (\Throwable) {
            $reasons[] = 'image_decode_error';
        }

        // Size sanity: at least 10 KB (avoids near-empty files), at most 10 MB.
        if ($sizeBytes < 10 * 1024) {
            $reasons[] = 'file_suspiciously_small';
        }
        if ($sizeBytes > 10 * 1024 * 1024) {
            $reasons[] = 'file_exceeds_maximum';
        }

        return [
            'verdict' => $reasons === [] ? self::VERDICT_CLEAR : self::VERDICT_FLAGGED,
            'reasons' => $reasons,
            'width' => $width,
            'height' => $height,
        ];
    }
}
