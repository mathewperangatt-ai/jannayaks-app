<?php

namespace Tests\Unit;

use App\Services\Ai\AnthropicEditorialClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AnthropicEditorialClientRequestTest extends TestCase
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('jannayaks.ai.anthropic.api_key', 'test-key-not-live');
        Config::set('jannayaks.ai.anthropic.endpoint', self::ENDPOINT);
        Config::set('jannayaks.ai.anthropic.model', 'claude-sonnet-5-5');
        Config::set('jannayaks.ai.anthropic.timeout_seconds', 30);
        Config::set('jannayaks.ai.anthropic.max_tokens', 8000);
    }

    public function test_claude_messages_request_is_parsed_into_the_editorial_contract(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'text',
                    'text' => '{"title":"മാതൃകാ തലക്കെട്ട്","summary":"ചുരുക്കം","body":"മാതൃകാ ലേഖനം.","claims":[{"excerpt":"2012ൽ പ്രവർത്തനം തുടങ്ങി","question_id":"q1"}],"flags":[]} ',
                ]],
                'stop_reason' => 'end_turn',
            ], 200),
        ]);

        $client = app(AnthropicEditorialClient::class);
        $result = $client->generateEditorial([
            'system' => 'journalistic Malayalam system prompt',
            'user' => 'source payload',
            'purpose' => 'malayalam',
        ]);

        $this->assertSame('claude', $client->providerName());
        $this->assertSame('claude-sonnet-5-5', $client->modelName());
        $this->assertSame('മാതൃകാ തലക്കെട്ട്', $result['title']);
        $this->assertSame('മാതൃകാ ലേഖനം.', $result['body']);
        $this->assertSame('q1', $result['claims'][0]['question_id']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === self::ENDPOINT
                && $request->hasHeader('x-api-key', 'test-key-not-live')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && ($data['model'] ?? null) === 'claude-sonnet-5-5'
                && ($data['max_tokens'] ?? null) === 8000
                && ($data['system'] ?? null) === 'journalistic Malayalam system prompt'
                && ($data['messages'][0]['role'] ?? null) === 'user'
                && ($data['messages'][0]['content'] ?? null) === 'source payload';
        });
    }

    public function test_missing_anthropic_key_fails_clearly(): void
    {
        Config::set('jannayaks.ai.anthropic.api_key', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Anthropic API key is not configured.');

        app(AnthropicEditorialClient::class)->generateEditorial([
            'system' => 'system',
            'user' => 'payload',
            'purpose' => 'english',
        ]);
    }

    public function test_http_failure_does_not_return_a_partial_draft(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response(['error' => ['message' => 'unavailable']], 503),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI provider request failed.');

        app(AnthropicEditorialClient::class)->generateEditorial([
            'system' => 'system',
            'user' => 'payload',
            'purpose' => 'english',
        ]);
    }
}
