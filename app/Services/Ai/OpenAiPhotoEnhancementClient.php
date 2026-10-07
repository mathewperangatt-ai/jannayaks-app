<?php

namespace App\Services\Ai;

use App\Contracts\PhotoEnhancementClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * OpenAI image enhancement via the Images API (`/v1/images/edits`),
 * following the same plain-HTTP conventions as the editorial client:
 * bearer key from the shared OPENAI_API_KEY secret, configurable
 * model/endpoint/timeout, source image supplied as multipart bytes —
 * never via a public URL.
 *
 * The response for image models is `{ "data": [ { "b64_json": "..." } ] }.
 */
class OpenAiPhotoEnhancementClient implements PhotoEnhancementClient
{
    public function providerName(): string
    {
        return 'openai';
    }

    public function modelName(): string
    {
        return (string) config('jannayaks.ai.image_enhancement.openai.model', 'gpt-image-1');
    }

    public function enhance(string $sourceJpegBytes, string $prompt): string
    {
        $apiKey = (string) config('jannayaks.ai.image_enhancement.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $endpoint = (string) config('jannayaks.ai.image_enhancement.openai.endpoint', 'https://api.openai.com/v1/images/edits');
        $timeout = max(30, (int) config('jannayaks.ai.image_enhancement.openai.timeout_seconds', 180));
        $size = (string) config('jannayaks.ai.image_enhancement.openai.size', '1024x1024');

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->attach('image', $sourceJpegBytes, 'source.jpg', ['Content-Type' => 'image/jpeg'])
                ->post($endpoint, [
                    'model' => $this->modelName(),
                    'prompt' => $prompt,
                    'size' => $size,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('OpenAI image request failed: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            $detail = (string) $response->json('error.message', $response->body());
            throw new RuntimeException('OpenAI image enhancement failed ('.$response->status().'): '.mb_substr($detail, 0, 500));
        }

        $b64 = (string) $response->json('data.0.b64_json', '');
        if ($b64 === '') {
            throw new RuntimeException('OpenAI returned no image data.');
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false || strlen($bytes) < 64) {
            throw new RuntimeException('OpenAI returned unusable image data.');
        }

        return $bytes;
    }
}
