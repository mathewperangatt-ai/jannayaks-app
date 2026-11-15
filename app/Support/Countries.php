<?php

namespace App\Support;

/**
 * ISO 3166-1 alpha-2 country catalog for the country-of-residence selector.
 * The country of residence determines the pricing category (India INR /
 * international USD) and is stored as a stable two-letter code. It is
 * never inferred from IP address, telephone country code or browser
 * locale, and it is never shown on a public profile.
 */
class Countries
{
    public const INDIA = 'IN';

    /**
     * The full approved selector list (ISO 3166-1 alpha-2 → English name),
     * India first as the home market.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'IN' => 'India',
            'AE' => 'United Arab Emirates',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BR' => 'Brazil',
            'CA' => 'Canada',
            'CN' => 'China',
            'DK' => 'Denmark',
            'EG' => 'Egypt',
            'FI' => 'Finland',
            'FR' => 'France',
            'DE' => 'Germany',
            'GR' => 'Greece',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'JO' => 'Jordan',
            'KE' => 'Kenya',
            'KW' => 'Kuwait',
            'LV' => 'Latvia',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'MT' => 'Malta',
            'MX' => 'Mexico',
            'MC' => 'Monaco',
            'MA' => 'Morocco',
            'MM' => 'Myanmar',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NG' => 'Nigeria',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PH' => 'Philippines',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'QA' => 'Qatar',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'SA' => 'Saudi Arabia',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'ZA' => 'South Africa',
            'KR' => 'South Korea',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'TW' => 'Taiwan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TR' => 'Türkiye',
            'UA' => 'Ukraine',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UZ' => 'Uzbekistan',
            'VN' => 'Vietnam',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ];
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && array_key_exists(strtoupper($code), self::all());
    }

    public static function normalize(?string $code): ?string
    {
        if (! self::isValid($code)) {
            return null;
        }

        return strtoupper((string) $code);
    }

    public static function name(string $code): string
    {
        return self::all()[strtoupper($code)] ?? $code;
    }

    /**
     * True when the code places the applicant in the international
     * (USD) pricing category. A missing/unknown code is NOT treated as
     * international: legacy rows without a country keep the existing
     * INR pricing behaviour until an administrator records a country.
     */
    public static function isInternational(?string $code): bool
    {
        $normalized = self::normalize($code);

        return $normalized !== null && $normalized !== self::INDIA;
    }

    /**
     * @return string
     */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', array_keys(self::all()));
    }
}
