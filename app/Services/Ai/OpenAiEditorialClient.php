<?php

namespace App\Services\Ai;

use App\Contracts\EditorialAiClient;
use App\Support\EditorialPlainTextSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiEditorialClient implements EditorialAiClient
{
    public function __construct(private EditorialPlainTextSanitizer $sanitizer) {}

    public function providerName(): string
    {
        return 'gpt';
    }

    public function modelName(): string
    {
        return (string) config('jannayaks.ai.openai.model', 'gpt-4.1-mini');
    }

    public function generateEditorial(array $request): array
    {
        $apiKey = (string) config('jannayaks.ai.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $endpoint = (string) config('jannayaks.ai.openai.endpoint', 'https://api.openai.com/v1/chat/completions');
        $timeout = (int) config('jannayaks.ai.openai.timeout_seconds', 60);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->acceptJson()
                ->post($endpoint, [
                    'model' => $this->modelName(),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => (string) $request['system']],
                        ['role' => 'user', 'content' => (string) $request['user']],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('editorial.ai.timeout', [
                'provider' => $this->providerName(),
                'purpose' => $request['purpose'] ?? null,
            ]);
            throw new RuntimeException('AI provider connection failed.', 0, $e);
        }

        if ($response->status() === 429) {
            throw new RuntimeException('AI provider rate limited.');
        }

        if (! $response->successful()) {
            Log::warning('editorial.ai.http_error', [
                'provider' => $this->providerName(),
                'status' => $response->status(),
                'purpose' => $request['purpose'] ?? null,
            ]);
            throw new RuntimeException('AI provider request failed.');
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return $this->parseJsonContent($content);
    }

    /**
     * @return array{title: string, summary: string, body: string, claims: list<array{excerpt: string, question_id: ?string}>}
     */
    private function parseJsonContent(string $content): array
    {
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI provider returned malformed JSON.');
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $summary = trim((string) ($decoded['summary'] ?? ''));
        $body = trim((string) ($decoded['body'] ?? ''));

        if ($title === '' || $body === '') {
            throw new RuntimeException('AI provider response missing required fields.');
        }

        // Contract: plain text only. Sanitise before returning to the generation service.
        $title = $this->sanitizer->sanitizeTitle($title);
        $summary = $this->sanitizer->sanitizeSummary($summary);
        $body = $this->sanitizer->sanitizeBody($body);

        if ($title === '' || $body === '') {
            throw new RuntimeException('AI provider response missing required fields after sanitisation.');
        }

        $claims = [];
        foreach ((array) ($decoded['claims'] ?? []) as $claim) {
            if (! is_array($claim)) {
                continue;
            }
            $excerpt = $this->sanitizer->sanitizeClaimExcerpt((string) ($claim['excerpt'] ?? ''));
            if ($excerpt === '') {
                continue;
            }
            $qid = $claim['question_id'] ?? null;
            $claims[] = [
                'excerpt' => mb_substr($excerpt, 0, 500),
                'question_id' => is_string($qid) && $qid !== '' ? $qid : null,
            ];
        }

        return [
            'title' => $title,
            'summary' => $summary,
            'body' => $body,
            'claims' => $claims,
        ];
    }
}
