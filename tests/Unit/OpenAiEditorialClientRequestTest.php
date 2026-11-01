<?php

namespace Tests\Unit;

use App\Services\Ai\OpenAiEditorialClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiEditorialClientRequestTest extends TestCase
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const SUCCESS_BODY = [
        'choices' => [
            [
                'message' => [
                    'content' => '{"title":"Sample title","summary":"Sample summary","body":"Sample body.","claims":[],"flags":[]}',
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('jannayaks.ai.openai.api_key', 'test-key-not-live');
        Config::set('jannayaks.ai.openai.endpoint', self::ENDPOINT);
        Config::set('jannayaks.ai.openai.timeout_seconds', 30);
    }

    public function test_gpt_4_1_mini_request_includes_temperature_and_omits_reasoning_effort(): void
    {
        Config::set('jannayaks.ai.openai.model', 'gpt-4.1-mini');
        Http::fake([
            self::ENDPOINT => Http::response(self::SUCCESS_BODY, 200),
        ]);

        $result = app(OpenAiEditorialClient::class)->generateEditorial([
            'system' => 'system prompt',
            'user' => 'user payload',
            'purpose' => 'malayalam',
        ]);

        $this->assertSame('Sample title', $result['title']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === self::ENDPOINT
                && ($data['model'] ?? null) === 'gpt-4.1-mini'
                && ($data['temperature'] ?? null) === 0.2
                && ! array_key_exists('reasoning_effort', $data)
                && ($data['response_format']['type'] ?? null) === 'json_object'
                && ($data['messages'][0]['role'] ?? null) === 'system'
                && ($data['messages'][1]['role'] ?? null) === 'user';
        });
    }

    public function test_gpt_6_luna_request_includes_reasoning_effort_and_omits_temperature(): void
    {
        Config::set('jannayaks.ai.openai.model', 'gpt-6-luna');
        Http::fake([
            self::ENDPOINT => Http::response(self::SUCCESS_BODY, 200),
        ]);

        $result = app(OpenAiEditorialClient::class)->generateEditorial([
            'system' => 'system prompt',
            'user' => 'user payload',
            'purpose' => 'english',
        ]);

        $this->assertSame('Sample body.', $result['body']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === self::ENDPOINT
                && ($data['model'] ?? null) === 'gpt-6-luna'
                && ($data['reasoning_effort'] ?? null) === 'medium'
                && ! array_key_exists('temperature', $data)
                && ($data['response_format']['type'] ?? null) === 'json_object'
                && ($data['messages'][0]['content'] ?? null) === 'system prompt'
                && ($data['messages'][1]['content'] ?? null) === 'user payload';
        });
    }
}
