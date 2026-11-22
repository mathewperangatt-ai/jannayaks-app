<?php

namespace App\Services\Ai;

use App\Contracts\EditorialAiClient;
use App\Support\EditorialPlainTextSanitizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnthropicEditorialClient implements EditorialAiClient
{
    public function __construct(private EditorialPlainTextSanitizer $sanitizer) {}

    public function providerName(): string
    {
        return 'claude';
    }

    public function modelName(): string
    {
        return (string) config('jannayaks.ai.anthropic.model', 'claude-sonnet-5-5');
    }

    public function generateEditorial(array $request): array
    {
        $apiKey = (string) config('jannayaks.ai.anthropic.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $endpoint = (string) config('jannayaks.ai.anthropic.endpoint', 'https://api.anthropic.com/v1/messages');
        $timeout = (int) config('jannayaks.ai.anthropic.timeout_seconds', 240);
        $payload = [
            'model' => $this->modelName(),
            'max_tokens' => (int) config('jannayaks.ai.anthropic.max_tokens', 8000),
            'system' => (string) $request['system'],
            'messages' => [
                ['role' => 'user', 'content' => (string) $request['user']],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout($timeout)->acceptJson()->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            Log::warning('editorial.ai.connection_failed', [
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

        $blocks = data_get($response->json(), 'content', []);
        $text = collect(is_array($blocks) ? $blocks : [])
            ->where('type', 'text')
            ->pluck('text')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->implode("\n");

        if (trim($text) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return $this->parseJsonContent($text);
    }

    /**
     * @return array{title: string, summary: string, body: string, claims: list<array{excerpt: string, question_id: ?string}>, flags: list<string>}
     */
    private function parseJsonContent(string $content): array
    {
        $decoded = json_decode(trim($content), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI provider returned malformed JSON.');
        }

        $title = $this->sanitizer->sanitizeTitle((string) ($decoded['title'] ?? ''));
        $summary = $this->sanitizer->sanitizeSummary((string) ($decoded['summary'] ?? ''));
        $body = $this->sanitizer->sanitizeBody((string) ($decoded['body'] ?? ''));

        if ($title === '' || $body === '') {
            throw new RuntimeException('AI provider response missing required fields.');
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

        $flags = [];
        foreach ((array) ($decoded['flags'] ?? []) as $flag) {
            if (is_string($flag) && trim($flag) !== '') {
                $flags[] = trim($flag);
            }
        }

        return compact('title', 'summary', 'body', 'claims', 'flags');
    }
}
