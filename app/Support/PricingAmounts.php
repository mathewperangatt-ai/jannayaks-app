<?php

namespace App\Support;

use InvalidArgumentException;

class PricingAmounts
{
    public const CURRENCY = 'INR';

    public const PAISE_PER_RUPEE = 100;

    /** Editorial-depth key stored on newly created living applications. */
    public const STANDARD_INTERNAL_TIER = 'distinguished';

    /**
     * @return list<string>
     */
    public static function livingTierKeys(): array
    {
        return ['emerging', 'accomplished', 'distinguished'];
    }

    public static function isLivingTier(?string $tierKey): bool
    {
        return in_array((string) $tierKey, self::livingTierKeys(), true);
    }

    /**
     * Current standard-profile charge. Historical tier keys resolve here so
     * checkout cannot keep a hidden tier price.
     *
     * @return array<string, mixed>
     */
    public static function forStandardProfile(): array
    {
        $cfg = (array) config('jannayaks.tier_pricing.standard_profile', []);
        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);
        $launchActive = (bool) ($cfg['launch_active'] ?? true);
        $launchBase = (int) ($cfg['launch_base_amount'] ?? 0);
        $standardBase = (int) ($cfg['standard_base_amount'] ?? 0);
        $chargeBase = ($launchActive && $launchBase > 0) ? $launchBase : $standardBase;

        $quote = self::buildPlusGst(
            baseRupees: $chargeBase,
            gstRate: $gstRate,
            label: (string) ($cfg['label'] ?? 'Jannayaks profile'),
            description: (string) ($cfg['description'] ?? 'Profile creation and the first year of publication and hosting.'),
            itemKey: self::STANDARD_INTERNAL_TIER,
        );

        $showCompare = $launchActive && $standardBase > $chargeBase;
        $quote['launch_active'] = $launchActive;
        $quote['compare_at_base_rupees'] = $showCompare ? $standardBase : null;
        $quote['compare_at_base_formatted'] = $showCompare
            ? self::formatMoneyInr(self::rupeesToPaise($standardBase))
            : null;

        return $quote;
    }

    public static function forTier(string $tierKey): array
    {
        if (self::isLivingTier($tierKey)) {
            return self::forStandardProfile();
        }

        $packages = (array) config('jannayaks.tier_pricing.packages', []);
        if (! isset($packages[$tierKey])) {
            throw new InvalidArgumentException('Unknown package tier: '.$tierKey);
        }
        $pkg = $packages[$tierKey];

        return self::buildPlusGst(
            baseRupees: (int) $pkg['base_amount'],
            gstRate: (float) config('jannayaks.tier_pricing.gst_percent', 18),
            label: (string) $pkg['label'],
            description: (string) ($pkg['description'] ?? 'Profile package'),
            itemKey: $tierKey,
        );
    }

    /**
     * Single annual renewal for every living profile. The configured amount
     * is inclusive of applicable GST.
     *
     * @return array<string, mixed>
     */
    public static function forTierRenewal(string $tierKey): array
    {
        if (! self::isLivingTier($tierKey)) {
            throw new InvalidArgumentException('Unknown package tier: '.$tierKey);
        }

        $cfg = (array) config('jannayaks.tier_pricing.renewal', []);
        $inclusiveRupees = (int) ($cfg['amount_incl_rupees'] ?? 0);
        if ($inclusiveRupees <= 0) {
            throw new InvalidArgumentException('Invalid renewal amount.');
        }

        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);
        $inclusivePaise = self::rupeesToPaise($inclusiveRupees);
        $split = self::splitInclusiveTotal($inclusivePaise, $gstRate);
        $cgst = intdiv($split['gst_paise'], 2);
        $sgst = $split['gst_paise'] - $cgst;
        $label = (string) ($cfg['label'] ?? 'Annual renewal');

        return [
            'currency' => self::CURRENCY,
            'tier_key' => 'membership_renewal',
            'label' => $label,
            'description' => (string) ($cfg['description'] ?? 'Annual renewal of publication and hosting, inclusive of applicable GST.'),
            'gst_inclusive' => true,
            'gst_rate_percent' => $gstRate,
            'amount_incl_paise' => $inclusivePaise,
            'amount_incl_rupees' => $inclusiveRupees,
            'base_paise' => $split['base_paise'],
            'gst_paise' => $split['gst_paise'],
            'cgst_paise' => $cgst,
            'sgst_paise' => $sgst,
            'igst_paise' => null,
            'amount_incl_formatted' => self::formatMoneyInr($inclusivePaise),
            'base_formatted' => self::formatMoneyInr($split['base_paise']),
            'gst_formatted' => self::formatMoneyInr($split['gst_paise']),
            'cgst_formatted' => self::formatMoneyInr($cgst),
            'sgst_formatted' => self::formatMoneyInr($sgst),
            'igst_formatted' => null,
        ];
    }

    /**
     * Complete application package amount for a living tier.
     * The Direct Personal Interview add-on was removed as a product; no
     * interview add-on may be priced or charged.
     *
     * @return array<string, mixed>
     */
    public static function forApplicationPackage(string $tierKey): array
    {
        if (! self::isLivingTier($tierKey)) {
            throw new InvalidArgumentException('Unknown package tier: '.$tierKey);
        }

        return self::forStandardProfile();
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

    public static function forRevisionUpdate(): array
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
            $cgst = null;
            $sgst = null;
            if ($split['gst_paise'] > 0) {
                $cgst = intdiv($split['gst_paise'], 2);
                $sgst = $split['gst_paise'] - $cgst;
            }

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
                'cgst_paise' => $cgst,
                'sgst_paise' => $sgst,
                'igst_paise' => null,
                'amount_incl_formatted' => self::formatMoneyInr($inclusivePaise),
                'base_formatted' => self::formatMoneyInr($split['base_paise']),
                'gst_formatted' => self::formatMoneyInr($split['gst_paise']),
                'cgst_formatted' => $cgst !== null ? self::formatMoneyInr($cgst) : null,
                'sgst_formatted' => $sgst !== null ? self::formatMoneyInr($sgst) : null,
                'igst_formatted' => null,
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

    private static function forPlusGstItem(array $cfg, float $gstRate, string $fallbackLabel, string $itemKey = 'item'): array
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
        );
    }

    private static function buildPlusGst(
        int $baseRupees,
        float $gstRate,
        string $label,
        string $description,
        string $itemKey,
    ): array {
        $basePaise = self::rupeesToPaise($baseRupees);
        $gstPaise = (int) round($basePaise * ($gstRate / 100));
        $totalPaise = $basePaise + $gstPaise;
        if ($totalPaise <= 0) {
            throw new InvalidArgumentException('Invalid computed amount for '.$itemKey);
        }

        $cgst = null;
        $sgst = null;
        $igst = null;
        if ($gstPaise > 0) {
            $cgst = intdiv($gstPaise, 2);
            $sgst = $gstPaise - $cgst;
        }

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
            'cgst_paise' => $cgst,
            'sgst_paise' => $sgst,
            'igst_paise' => $igst,
            'amount_incl_formatted' => self::formatMoneyInr($totalPaise),
            'base_formatted' => self::formatMoneyInr($basePaise),
            'gst_formatted' => self::formatMoneyInr($gstPaise),
            'cgst_formatted' => $cgst !== null ? self::formatMoneyInr($cgst) : null,
            'sgst_formatted' => $sgst !== null ? self::formatMoneyInr($sgst) : null,
            'igst_formatted' => $igst !== null ? self::formatMoneyInr($igst) : null,
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
