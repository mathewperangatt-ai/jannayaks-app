<?php

namespace App\Support;

use Illuminate\Support\Collection;

class OnlineInterviewCatalog
{
    /**
     * All living tiers draw on the SAME comprehensive question pool
     * (Master Editorial Specification §17). Tier controls editorial
     * depth, never access to questions. Signatures keep the historical
     * package-tier parameter for callers; it no longer filters.
     *
     * @return list<string>
     */
    public static function sectionsForTier(?string $packageTier): array
    {
        return array_keys(config('online_interview.sections', []));
    }

    public static function all(): array
    {
        return config('online_interview.questions', []);
    }

    public static function questionsForTier(?string $packageTier): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $q): bool => (string) ($q['id'] ?? '') !== ''
        ));
    }

    public static function idsForTier(?string $packageTier): array
    {
        return array_map(
            static fn (array $q): string => (string) ($q['id'] ?? ''),
            self::questionsForTier($packageTier)
        );
    }

    /**
     * @param  array<string,mixed>  $answers
     * @return array{answered: int, total: int, required_total: int, required_answered: int, missing_required: list<string>}
     */
    public static function progress(?string $packageTier, array $answers): array
    {
        $questions = self::questionsForTier($packageTier);
        $answered = 0;
        $requiredTotal = 0;
        $requiredAnswered = 0;
        $missingRequired = [];

        foreach ($questions as $q) {
            $id = (string) ($q['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $has = self::isAnswered($answers[$id] ?? null);
            if ($has) {
                $answered++;
            }
            if (! empty($q['required'])) {
                $requiredTotal++;
                if ($has) {
                    $requiredAnswered++;
                } else {
                    $missingRequired[] = $id;
                }
            }
        }

        return [
            'answered'          => $answered,
            'total'             => count($questions),
            'required_total'    => $requiredTotal,
            'required_answered' => $requiredAnswered,
            'missing_required'  => $missingRequired,
        ];
    }

    public static function isAnswered(mixed $value): bool
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        return trim((string) $value) !== '';
    }

    /**
     * @param  array<string,mixed>  $answers
     * @return list<string>
     */
    public static function missingRequired(?string $packageTier, array $answers): array
    {
        return self::progress($packageTier, $answers)['missing_required'] ?? [];
    }
}
