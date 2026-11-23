<?php

namespace App\Security;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Genuine image-content moderation for member-submitted photograph
 * replacements (Wave 3, owner-approved addition).
 *
 * Uses the OpenAI moderation endpoint (omni-moderation-latest — multimodal,
 * free of charge, detects sexual/explicit content including attempts to
 * disguise it). Credentials are the ALREADY-CONFIGURED OpenAI API key; no
 * new provider, account or cost.
 *
 * Fail-safe policy: moderation is a SCREENING stage, never a permission.
 *  - explicit sexual content → flagged (manual examination mandatory)
 *  - API failure, timeout, missing key, disabled → uncertain (manual
 *    examination mandatory)
 * A 'clear' verdict never publishes anything by itself: every replacement
 * still requires explicit Security Administrator approval.
 */
class PhotoModerationService
{
    public const VERDICT_CLEAR = 'clear';

    public const VERDICT_FLAGGED = 'flagged';

    public const VERDICT_UNCERTAIN = 'uncertain';

    /** Category keys treated as hard flags. */
    private const HARD_CATEGORIES = ['sexual', 'sexual/minors'];

    /** Score above this (0-1) without a boolean flag is treated as flagged. */
    private const SCORE_THRESHOLD = 0.45;

    /** @return array{verdict: string, categories: array<string, mixed>, provider_error: ?string} */
    public function moderate(string $absolutePath, string $mimeType): array
    {
        if (! (bool) config('jannayaks.security.photo_moderation.enabled', true)) {
            return ['verdict' => self::VERDICT_UNCERTAIN, 'categories' => [], 'provider_error' => 'moderation_disabled'];
        }

        $apiKey = (string) config('jannayaks.ai.openai.api_key', '');
        if ($apiKey === '') {
            return ['verdict' => self::VERDICT_UNCERTAIN, 'categories' => [], 'provider_error' => 'missing_api_key'];
        }

        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false) {
            return ['verdict' => self::VERDICT_UNCERTAIN, 'categories' => [], 'provider_error' => 'unreadable_file'];
        }

        $endpoint = (string) config('jannayaks.security.photo_moderation.endpoint', 'https://api.openai.com/v1/moderations');
        $model = (string) config('jannayaks.security.photo_moderation.model', 'omni-moderation-latest');
        $timeout = (int) config('jannayaks.security.photo_moderation.timeout_seconds', 30);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])->timeout($timeout)->acceptJson()->post($endpoint, [
                'model' => $model,
                'input' => [
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:'.$mimeType.';base64,'.base64_encode($bytes),
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('photo-moderation: provider unreachable', [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return ['verdict' => self::VERDICT_UNCERTAIN, 'categories' => [], 'provider_error' => 'provider_unreachable'];
        }

        if ($response->status() !== 200) {
            Log::warning('photo-moderation: provider error', ['status' => $response->status()]);

            return ['verdict' => self::VERDICT_UNCERTAIN, 'categories' => [], 'provider_error' => 'provider_status_'.$response->status()];
        }

        $result = $response->json('results.0');
        if (! is_array($result)) {
            return ['verdict' => self::VERDICT_UNCERTAIN, 'categories' => [], 'provider_error' => 'malformed_response'];
        }

        $categories = $result['categories'] ?? [];
        $scores = $result['category_scores'] ?? [];

        $flagged = false;
        foreach (self::HARD_CATEGORIES as $key) {
            if (($categories[$key] ?? false) === true) {
                $flagged = true;
            }
            if (is_numeric($scores[$key] ?? null) && (float) $scores[$key] >= self::SCORE_THRESHOLD) {
                $flagged = true;
            }
        }

        return [
            'verdict' => $flagged ? self::VERDICT_FLAGGED : self::VERDICT_CLEAR,
            'categories' => $categories,
            'provider_error' => null,
        ];
    }
}
