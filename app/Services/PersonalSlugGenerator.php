<?php

namespace App\Services;

use Illuminate\Support\Str;

class PersonalSlugGenerator
{
    /**
     * Optional professional title prefixes. Never auto-suggested.
     *
     * @var list<string>
     */
    public const OPTIONAL_TITLES = ['dr', 'adv', 'prof'];

    /**
     * @var array<string, list<string>>
     */
    private const TITLE_PROFESSION_HINTS = [
        'dr' => ['doctor', 'dr', 'physician', 'surgeon', 'mbbs', 'md', 'medical'],
        'adv' => ['advocate', 'adv', 'lawyer', 'counsel', 'attorney', 'legal'],
        'prof' => ['professor', 'prof', 'lecturer', 'academic'],
    ];

    /**
     * @return list<string>
     */
    public function tokenizeName(string $fullName): array
    {
        $normalized = Str::lower(Str::ascii($fullName));
        $normalized = preg_replace('/[^a-z0-9\s]+/', ' ', $normalized) ?? '';
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || in_array($part, self::OPTIONAL_TITLES, true)) {
                continue;
            }
            $tokens[] = $part;
        }

        return array_values($tokens);
    }

    /**
     * Legitimate name-derived combinations. Titles are never included.
     *
     * @return list<string>
     */
    public function generateCombinations(string $fullName): array
    {
        $tokens = $this->tokenizeName($fullName);
        if ($tokens === []) {
            return [];
        }

        $n = count($tokens);
        $out = [];

        $out[] = implode('.', $tokens);
        $out[] = implode('', $tokens);

        if ($n >= 2) {
            $out[] = $tokens[0].'.'.$tokens[1];
            $out[] = $tokens[0].$tokens[1];
            $out[] = $tokens[0].'.'.$tokens[$n - 1];
            $out[] = $tokens[0].$tokens[$n - 1];
        }

        if ($n >= 3) {
            $middleInitials = [];
            for ($i = 1; $i < $n - 1; $i++) {
                $middleInitials[] = substr($tokens[$i], 0, 1);
            }
            $out[] = $tokens[0].'.'.implode('.', $middleInitials).'.'.$tokens[$n - 1];
        }

        if (strlen($tokens[0]) >= 3) {
            $out[] = $tokens[0];
        }

        $unique = [];
        foreach ($out as $candidate) {
            if ($this->isValidPersonalBody($candidate) && ! in_array($candidate, $unique, true)) {
                $unique[] = $candidate;
            }
        }

        return $unique;
    }

    public function isNameDerived(string $slug, string $fullName): bool
    {
        $parsed = $this->parsePersonalSlug($slug);
        if ($parsed === null) {
            return false;
        }

        $tokens = $this->tokenizeName($fullName);
        if ($tokens === []) {
            return false;
        }

        return $this->bodyMatchesTokens($parsed['body'], $tokens);
    }

    public function titleIsConsistent(?string $title, ?string $profession): bool
    {
        if ($title === null) {
            return true;
        }

        if (! in_array($title, self::OPTIONAL_TITLES, true)) {
            return false;
        }

        $profession = Str::lower(trim((string) $profession));
        if ($profession === '') {
            return true;
        }

        foreach (self::TITLE_PROFESSION_HINTS[$title] as $hint) {
            if (str_contains($profession, $hint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{title: ?string, body: string, suffix: ?string}|null
     */
    public function parsePersonalSlug(string $slug): ?array
    {
        $normalized = Str::lower(trim($slug));
        if ($normalized === '') {
            return null;
        }

        $title = null;
        foreach (self::OPTIONAL_TITLES as $prefix) {
            if (str_starts_with($normalized, $prefix.'.') || str_starts_with($normalized, $prefix.'-')) {
                $title = $prefix;
                $normalized = substr($normalized, strlen($prefix) + 1);
                break;
            }
        }

        $suffix = null;
        if (preg_match('/^([a-z0-9]+(?:[.\-][a-z0-9]+)*)(\d{2,4})$/', $normalized, $matches) === 1) {
            $normalized = $matches[1];
            $suffix = $matches[2];
        }

        if (! $this->isValidPersonalBody($normalized)) {
            return null;
        }

        return [
            'title' => $title,
            'body' => $normalized,
            'suffix' => $suffix,
        ];
    }

    public function collisionCandidate(string $base, int $number): string
    {
        return $base.$number;
    }

    /**
     * Two-digit collision suffixes that are not a profile database id.
     *
     * @return list<int>
     */
    public function collisionSuffixes(?int $profileId = null): array
    {
        $suffixes = [];
        for ($n = 10; $n <= 99; $n++) {
            if ($profileId !== null && $n === $profileId) {
                continue;
            }
            $suffixes[] = $n;
        }

        return $suffixes;
    }

    private function isValidPersonalBody(string $body): bool
    {
        if (preg_match('/^[a-z0-9]+(?:[.\-][a-z0-9]+)*$/', $body) !== 1) {
            return false;
        }

        if (strlen($body) < 3 || strlen($body) > 64) {
            return false;
        }

        if (preg_match('/^[0-9]+$/', $body) === 1) {
            return false;
        }

        return true;
    }

    private function bodyMatchesTokens(string $body, array $tokens): bool
    {
        if (! str_contains($body, '.') && ! str_contains($body, '-')) {
            return $this->consumeConcatenation($body, 0, $tokens, 0);
        }

        $parts = preg_split('/[.\-]+/', $body) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        return $this->partsMatchTokensInOrder($parts, $tokens);
    }

    /**
     * @param  list<string>  $parts
     * @param  list<string>  $tokens
     */
    private function partsMatchTokensInOrder(array $parts, array $tokens): bool
    {
        $tokenIndex = 0;
        foreach ($parts as $part) {
            $matched = false;
            while ($tokenIndex < count($tokens)) {
                $token = $tokens[$tokenIndex];
                $tokenIndex++;
                if ($part === $token) {
                    $matched = true;
                    break;
                }
                if (strlen($part) === 1 && $part === substr($token, 0, 1)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        return $parts !== [];
    }

    /**
     * @param  list<string>  $tokens
     */
    private function consumeConcatenation(string $body, int $position, array $tokens, int $tokenIndex): bool
    {
        if ($position === strlen($body)) {
            return $position > 0;
        }

        if ($tokenIndex >= count($tokens)) {
            return false;
        }

        if ($this->consumeConcatenation($body, $position, $tokens, $tokenIndex + 1)) {
            return true;
        }

        $token = $tokens[$tokenIndex];
        $length = strlen($token);
        if ($length > 0 && substr($body, $position, $length) === $token) {
            return $this->consumeConcatenation($body, $position + $length, $tokens, $tokenIndex + 1);
        }

        return false;
    }
}
