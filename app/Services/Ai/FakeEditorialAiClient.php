<?php

namespace App\Services\Ai;

use App\Contracts\EditorialAiClient;
use InvalidArgumentException;
use RuntimeException;

class FakeEditorialAiClient implements EditorialAiClient
{
    /** @var list<array{system: string, user: string, purpose: string}> */
    public array $requests = [];

    public bool $failNext = false;

    public ?string $failOnPurpose = null;

    public string $failMessage = 'Simulated AI failure';

    public bool $includeUnsafeMarkup = false;

    public function providerName(): string
    {
        return 'fake';
    }

    public function modelName(): string
    {
        return 'fake-editorial-v1';
    }

    public function generateEditorial(array $request): array
    {
        $this->requests[] = $request;

        $purpose = (string) ($request['purpose'] ?? '');

        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException($this->failMessage);
        }

        if ($this->failOnPurpose !== null && $purpose === $this->failOnPurpose) {
            throw new RuntimeException($this->failMessage);
        }

        $user = (string) ($request['user'] ?? '');

        // Prompt-injection defence check for tests: source instructions must remain inside DATA boundary.
        if (! str_contains($user, '<<<SOURCE_DATA>>>') || ! str_contains($user, '<<<END_SOURCE_DATA>>>')) {
            throw new InvalidArgumentException('Source payload must be delimited as DATA.');
        }

        if ($purpose === 'malayalam') {
            return [
                'title' => 'മലയാളം പ്രൊഫൈൽ',
                'summary' => 'സ്രോതസ്സ് അടിസ്ഥാനമാക്കിയുള്ള മലയാളം സംഗ്രഹം.',
                'body' => "ഇംഗ്ലീഷ് മാസ്റ്റർ അടിസ്ഥാനമാക്കിയുള്ള മലയാളം എഡിറ്റോറിയൽ ജീവചരിത്രം.\n\nവസ്തുതകൾ മാത്രം ഉപയോഗിച്ചിരിക്കുന്നു.",
                'claims' => [
                    ['excerpt' => 'സ്രോതസ്സ് അടിസ്ഥാന വസ്തുത', 'question_id' => 'q1'],
                ],
            ];
        }

        // Detect if injection text exists — still produce source-grounded output without following it.
        $mentionsInjection = str_contains(strtolower($user), 'ignore previous instructions');

        $body = $mentionsInjection
            ? "Based on the applicant's supplied answers, this draft uses only source facts.\n\nNo campaign language or invented quotations are included."
            : "Based on the applicant's supplied answers, this draft recounts the public journey in plain editorial prose.\n\nNo invented quotations are included.";

        if ($this->includeUnsafeMarkup) {
            $body = "<script>alert('xss')</script><p>Paragraph one.</p>\n\n<strong>Paragraph two</strong> with <b>emphasis</b>.";
        }

        return [
            'title' => $this->includeUnsafeMarkup ? '<em>Editorial</em> Profile Draft' : 'Editorial Profile Draft',
            'summary' => $this->includeUnsafeMarkup
                ? '<img src=x onerror=alert(1)>A concise profile based only on supplied source material.'
                : 'A concise profile based only on supplied source material.',
            'body' => $body,
            'claims' => [
                ['excerpt' => 'Applicant-supplied journey detail', 'question_id' => 'q1'],
                ['excerpt' => 'Contribution mentioned in source answers', 'question_id' => 'q5'],
                ['excerpt' => 'Unmapped AI-asserted excerpt', 'question_id' => 'q_missing'],
            ],
        ];
    }
}
