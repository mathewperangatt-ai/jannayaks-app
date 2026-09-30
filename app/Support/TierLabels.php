<?php

namespace App\Support;

/**
 * Single source of truth mapping internal tier identifiers to the approved
 * customer-facing tier names (Master Editorial Specification §12).
 *
 * Internal storage keys (emerging/accomplished/distinguished/in_memoriam) are
 * retained to avoid schema migration risk; every customer-facing, editorial
 * and panel surface must render names through this map.
 */
final class TierLabels
{
    /** @var array<string, string> */
    public const LABELS = [
        'emerging' => 'Recognised',
        'accomplished' => 'Acclaimed',
        'distinguished' => 'Distinguished',
        'in_memoriam' => 'In Memoriam',
    ];

    public static function label(?string $tierKey): string
    {
        $key = (string) $tierKey;

        // Config labels are the source of truth; static map is the fallback.
        $configured = (string) config('jannayaks.tier_pricing.packages.'.$key.'.label', '');
        if ($configured !== '') {
            return $configured;
        }
        if ($key === 'in_memoriam') {
            return (string) config('jannayaks.tier_pricing.in_memoriam.label', self::LABELS['in_memoriam']);
        }

        return self::LABELS[$key] ?? ucfirst($key);
    }

    /**
     * @return array<string, string>
     */
    public static function forLivingTiers(): array
    {
        return [
            'emerging' => self::label('emerging'),
            'accomplished' => self::label('accomplished'),
            'distinguished' => self::label('distinguished'),
        ];
    }
}
