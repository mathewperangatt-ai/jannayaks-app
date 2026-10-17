<?php

namespace App\Support;

use App\Models\Application;
use InvalidArgumentException;

class PricingAmounts
{
    public const CURRENCY = 'INR';

    public const PAISE_PER_RUPEE = 100;

    public const STANDARD_INTERNAL_TIER = 'distinguished';

    /**
     * @return list<string>
     */
    public static function livingTierKeys(): array
    {
        return ['emerging', 'accomplished', 'distinguished'];
    }

    public static function isLivingTier(string $tierKey): bool
    {
        return in_array($tierKey, self::livingTierKeys(), true);
    }

    /**
     * The single public Standard Profile price. GST is added at payment.
     * There is no comparison or launch price.
     *
     * @return array<string, mixed>
     */
    public static function forStandardProfile(?string $placeOfSupply = null): array
    {
        $cfg = (array) config('jannayaks.tier_pricing.standard_profile', []);

        return self::buildPlusGst(
            baseRupees: (int) ($cfg['base_amount'] ?? 0),
            gstRate: (float) config('jannayaks.tier_pricing.gst_percent', 18),
            label: (string) ($cfg['label'] ?? 'Standard Profile'),
            description: (string) ($cfg['description'] ?? 'Standard Profile fee, exclusive of GST.'),
            itemKey: 'standard_profile',
            placeOfSupply: $placeOfSupply,
        );
    }

    /**
     * Resolve the application package quote for payment initiation and display.
     * New Standard Profile rows use the standard_profile amount; historical
     * rows (pricing_generation null) keep their stored package-tier amounts.
     *
     * @return array<string, mixed>
     */
    public static function forApplication(Application $application, ?string $placeOfSupply = null): array
    {
        if ($application->pricing_generation === Application::PRICING_GENERATION_STANDARD) {
            return self::forStandardProfile($placeOfSupply);
        }

        return self::forTier((string) $application->package_tier, $placeOfSupply);
    }

    /**
     * Package amount for a living or other configured tier key.
     * Living keys use the historical package table amounts — callers that
     * need Standard Profile pricing must use forStandardProfile() or
     * forApplication() with pricing_generation set.
     *
     * @return array<string, mixed>
     */
    public static function forTier(string $tierKey, ?string $placeOfSupply = null): array
    {
        $packages = (array) config('jannayaks.tier_pricing.packages', []);
        if (! isset($packages[$tierKey])) {
            throw new InvalidArgumentException('Unknown package tier: '.$tierKey);
        }
        $pkg = $packages[$tierKey];

        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);

        return self::buildPlusGst(
            baseRupees: (int) $pkg['base_amount'],
            gstRate: $gstRate,
            label: (string) $pkg['label'],
            description: (string) ($pkg['description'] ?? 'Profile package'),
            itemKey: $tierKey,
            placeOfSupply: $placeOfSupply,
        );
    }

    /**
     * One annual renewal price for every living profile. GST is added at payment.
     *
     * @return array<string, mixed>
     */
    public static function forTierRenewal(string $tierKey, ?string $placeOfSupply = null): array
    {
        if (! self::isLivingTier($tierKey)) {
            throw new InvalidArgumentException('Unknown package tier: '.$tierKey);
        }

        $cfg = (array) config('jannayaks.tier_pricing.renewal', []);

        return self::buildPlusGst(
            baseRupees: (int) ($cfg['base_amount'] ?? 0),
            gstRate: (float) config('jannayaks.tier_pricing.gst_percent', 18),
            label: (string) ($cfg['label'] ?? 'Annual renewal'),
            description: (string) ($cfg['description'] ?? 'Annual renewal of publication and hosting, exclusive of GST.'),
            itemKey: 'membership_renewal',
            placeOfSupply: $placeOfSupply,
        );
    }

    /**
     * Complete application package amount for a living tier key.
     * Prefer forApplication(Application) so pricing_generation is respected.
     * The Direct Personal Interview add-on was removed as a product.
     *
     * @return array<string, mixed>
     */
    public static function forApplicationPackage(string $tierKey): array
    {
        return self::forTier($tierKey);
    }

    /**
     * Resolve the living package tier for a membership's profile.
     * Memberships store a legacy 'basic' tier value; the applicable annual
     * tier price comes from the profile's originating application.
     */
    public static function tierKeyForMembership(\App\Models\Membership $membership): string
    {
        $tierKey = \App\Models\Application::query()
            ->where('profile_id', $membership->profile_id)
            ->orderByDesc('id')
            ->value('package_tier');

        $packages = (array) config('jannayaks.tier_pricing.packages', []);

        return isset($packages[$tierKey]) ? (string) $tierKey : 'emerging';
    }

    public static function forRevisionUpdate(?string $placeOfSupply = null): array
    {
        $cfg = (array) config('jannayaks.tier_pricing.revision', []);
        $baseAmount = (int) ($cfg['base_amount'] ?? 2000);
        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);

        return self::buildPlusGst(
            baseRupees: $baseAmount,
            gstRate: $gstRate,
            label: (string) ($cfg['label'] ?? 'Profile Revision / Update'),
            description: (string) ($cfg['description'] ?? 'Interim profile revision or content update.'),
            itemKey: 'revision',
            placeOfSupply: $placeOfSupply,
        );
    }

    public static function forInMemoriam5yr(): array
    {
        $cfg = (array) config('jannayaks.tier_pricing.in_memoriam', []);
        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);
        $label = (string) ($cfg['label'] ?? 'In Memoriam (3 years hosting)');

        // Product sticker is GST-inclusive when configured (customer-facing ₹25,000).
        if ((bool) ($cfg['gst_inclusive'] ?? false)) {
            $inclusiveRupees = (int) ($cfg['base_amount'] ?? 0);
            if ($inclusiveRupees <= 0) {
                throw new InvalidArgumentException('Invalid In Memoriam amount.');
            }

            $inclusivePaise = self::rupeesToPaise($inclusiveRupees);
            $split = self::splitInclusiveTotal($inclusivePaise, $gstRate);
            $components = self::classifyGstComponents($split['gst_paise'], null);

            return [
                'currency' => self::CURRENCY,
                'tier_key' => 'in_memoriam',
                'label' => $label,
                'description' => $label,
                'gst_inclusive' => true,
                'gst_rate_percent' => $gstRate,
                'amount_incl_paise' => $inclusivePaise,
                'amount_incl_rupees' => $inclusiveRupees,
                'base_paise' => $split['base_paise'],
                'gst_paise' => $split['gst_paise'],
                'cgst_paise' => $components['cgst_paise'],
                'sgst_paise' => $components['sgst_paise'],
                'igst_paise' => $components['igst_paise'],
                'gst_classification' => $components['classification'],
                'amount_incl_formatted' => self::formatMoneyInr($inclusivePaise),
                'base_formatted' => self::formatMoneyInr($split['base_paise']),
                'gst_formatted' => self::formatMoneyInr($split['gst_paise']),
                'cgst_formatted' => $components['cgst_paise'] !== null ? self::formatMoneyInr($components['cgst_paise']) : null,
                'sgst_formatted' => $components['sgst_paise'] !== null ? self::formatMoneyInr($components['sgst_paise']) : null,
                'igst_formatted' => $components['igst_paise'] !== null ? self::formatMoneyInr($components['igst_paise']) : null,
            ];
        }

        return self::forPlusGstItem($cfg, $gstRate, $label, 'in_memoriam');
    }

    /**
     * @return array<string, string>
     */
    public static function sellerBillingDetails(): array
    {
        $billing = (array) config('jannayaks.tier_pricing.billing', []);

        return [
            'legal_name' => trim((string) ($billing['legal_name'] ?? '')),
            'gstin' => trim((string) ($billing['gstin'] ?? '')),
            'address' => trim((string) ($billing['address'] ?? '')),
            'state' => trim((string) ($billing['state'] ?? '')),
            'place_of_supply' => trim((string) ($billing['place_of_supply'] ?? '')),
            'support_email' => trim((string) ($billing['support_email'] ?? '')),
        ];
    }

    private static function forPlusGstItem(array $cfg, float $gstRate, string $fallbackLabel, string $itemKey = 'item', ?string $placeOfSupply = null): array
    {
        $baseRupees = (int) ($cfg['base_amount'] ?? 0);
        if ($baseRupees <= 0) {
            throw new InvalidArgumentException('Invalid base amount for '.$itemKey);
        }

        return self::buildPlusGst(
            baseRupees: $baseRupees,
            gstRate: $gstRate,
            label: (string) ($cfg['label'] ?? $fallbackLabel),
            description: (string) ($cfg['description'] ?? $fallbackLabel),
            itemKey: $itemKey,
            placeOfSupply: $placeOfSupply,
        );
    }

    /**
     * Split GST into CGST/SGST or IGST only when both the configured seller
     * state and an explicit recipient place of supply are present. Missing
     * either side leaves the tax unclassified — never invent a split.
     *
     * @return array{classification: string, cgst_paise: int|null, sgst_paise: int|null, igst_paise: int|null}
     */
    public static function classifyGstComponents(int $gstPaise, ?string $placeOfSupply): array
    {
        if ($gstPaise <= 0) {
            return [
                'classification' => 'none',
                'cgst_paise' => null,
                'sgst_paise' => null,
                'igst_paise' => null,
            ];
        }

        $sellerState = self::normalizeStateName(self::sellerBillingDetails()['state'] ?? '');
        $supplyState = self::normalizeStateName($placeOfSupply);

        if ($sellerState === '' || $supplyState === '') {
            return [
                'classification' => 'unclassified',
                'cgst_paise' => null,
                'sgst_paise' => null,
                'igst_paise' => null,
            ];
        }

        if ($sellerState === $supplyState) {
            $cgst = intdiv($gstPaise, 2);
            $sgst = $gstPaise - $cgst;

            return [
                'classification' => 'intra_state',
                'cgst_paise' => $cgst,
                'sgst_paise' => $sgst,
                'igst_paise' => null,
            ];
        }

        return [
            'classification' => 'inter_state',
            'cgst_paise' => null,
            'sgst_paise' => null,
            'igst_paise' => $gstPaise,
        ];
    }

    public static function normalizeStateName(?string $state): string
    {
        $clean = strtolower(trim((string) $state));
        if ($clean === '') {
            return '';
        }

        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

        return match ($clean) {
            'keralam' => 'kerala',
            default => $clean,
        };
    }

    private static function buildPlusGst(
        int $baseRupees,
        float $gstRate,
        string $label,
        string $description,
        string $itemKey,
        ?string $placeOfSupply = null,
    ): array {
        $basePaise = self::rupeesToPaise($baseRupees);
        $gstPaise = (int) round($basePaise * ($gstRate / 100));
        $totalPaise = $basePaise + $gstPaise;
        if ($totalPaise <= 0) {
            throw new InvalidArgumentException('Invalid computed amount for '.$itemKey);
        }

        $components = self::classifyGstComponents($gstPaise, $placeOfSupply);

        return [
            'currency' => self::CURRENCY,
            'tier_key' => $itemKey,
            'label' => $label,
            'description' => $description,
            'gst_inclusive' => false,
            'gst_rate_percent' => $gstRate,
            'amount_incl_paise' => $totalPaise,
            'amount_incl_rupees' => (int) round($totalPaise / self::PAISE_PER_RUPEE),
            'base_paise' => $basePaise,
            'gst_paise' => $gstPaise,
            'cgst_paise' => $components['cgst_paise'],
            'sgst_paise' => $components['sgst_paise'],
            'igst_paise' => $components['igst_paise'],
            'gst_classification' => $components['classification'],
            'amount_incl_formatted' => self::formatMoneyInr($totalPaise),
            'base_formatted' => self::formatMoneyInr($basePaise),
            'gst_formatted' => self::formatMoneyInr($gstPaise),
            'cgst_formatted' => $components['cgst_paise'] !== null ? self::formatMoneyInr($components['cgst_paise']) : null,
            'sgst_formatted' => $components['sgst_paise'] !== null ? self::formatMoneyInr($components['sgst_paise']) : null,
            'igst_formatted' => $components['igst_paise'] !== null ? self::formatMoneyInr($components['igst_paise']) : null,
        ];
    }

    public static function splitInclusiveTotal(int $inclusivePaise, ?float $ratePercent = null): array
    {
        if ($inclusivePaise <= 0) {
            throw new InvalidArgumentException('Inclusive amount must be positive.');
        }
        $rate = $ratePercent ?? (float) config('jannayaks.tier_pricing.gst_percent', 18);
        if ($rate < 0) {
            throw new InvalidArgumentException('GST rate cannot be negative.');
        }

        $divisor = 1 + ($rate / 100);
        $basePaise = (int) round($inclusivePaise / $divisor);
        if ($basePaise < 0) {
            $basePaise = 0;
        }
        $gstPaise = $inclusivePaise - $basePaise;
        if ($gstPaise < 0) {
            $gstPaise = 0;
            $basePaise = $inclusivePaise;
        }

        return [
            'base_paise' => $basePaise,
            'gst_paise' => $gstPaise,
            'rate_percent' => $rate,
            'inclusive_paise' => $inclusivePaise,
        ];
    }

    public static function rupeesToPaise(int $rupees): int
    {
        if ($rupees < 0) {
            throw new InvalidArgumentException('Rupees cannot be negative.');
        }

        return $rupees * self::PAISE_PER_RUPEE;
    }

    public static function paiseToRupees(int $paise): float
    {
        return $paise / self::PAISE_PER_RUPEE;
    }

    public static function paiseToDecimalString(int $paise): string
    {
        $rupees = intdiv($paise, self::PAISE_PER_RUPEE);
        $remainder = $paise % self::PAISE_PER_RUPEE;

        return sprintf('%d.%02d', $rupees, $remainder);
    }

    public static function formatMoneyInr(int $paise): string
    {
        $decimal = self::paiseToDecimalString($paise);
        [$whole, $frac] = explode('.', $decimal, 2) + ['', '00'];
        $sign = '';
        if ($whole !== '' && $whole[0] === '-') {
            $sign = '-';
            $whole = substr($whole, 1);
        }
        $lastThree = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/(\d+?)(?=(\d{2})+(?!\d))/', '$1,', $rest);
            $whole = $rest.','.$lastThree;
        } else {
            $whole = $lastThree;
        }

        return '₹'.$sign.$whole.'.'.$frac;
    }

    public static function assertInr(string $currency): void
    {
        if (strtoupper($currency) !== self::CURRENCY) {
            throw new InvalidArgumentException('Only INR currency is supported.');
        }
    }
}
