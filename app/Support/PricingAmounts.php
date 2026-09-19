<?php

namespace App\Support;

use InvalidArgumentException;

class PricingAmounts
{
    public const CURRENCY = 'INR';

    public const PAISE_PER_RUPEE = 100;

    public static function forTier(string $tierKey): array
    {
        $packages = (array) config('jannayaks.tier_pricing.packages', []);
        if (! isset($packages[$tierKey])) {
            throw new InvalidArgumentException('Unknown package tier: '.$tierKey);
        }
        $pkg = $packages[$tierKey];

        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);
        $inclusiveRupees = (int) $pkg['base_amount'];
        $inclusivePaise = self::rupeesToPaise($inclusiveRupees);

        $split = self::splitInclusiveTotal($inclusivePaise, $gstRate);

        $cgst = null;
        $sgst = null;
        $igst = null;
        if ($split['gst_paise'] > 0) {
            $cgst = intdiv($split['gst_paise'], 2);
            $sgst = $split['gst_paise'] - $cgst;
        }

        return [
            'currency' => self::CURRENCY,
            'tier_key' => $tierKey,
            'label' => (string) $pkg['label'],
            'description' => (string) ($pkg['description'] ?? 'Profile package'),
            'gst_inclusive' => true,
            'gst_rate_percent' => $gstRate,
            'amount_incl_paise' => $inclusivePaise,
            'amount_incl_rupees' => $inclusiveRupees,
            'base_paise' => $split['base_paise'],
            'gst_paise' => $split['gst_paise'],
            'cgst_paise' => $cgst,
            'sgst_paise' => $sgst,
            'igst_paise' => $igst,
            'amount_incl_formatted' => self::formatMoneyInr($inclusivePaise),
            'base_formatted' => self::formatMoneyInr($split['base_paise']),
            'gst_formatted' => self::formatMoneyInr($split['gst_paise']),
            'cgst_formatted' => $cgst !== null ? self::formatMoneyInr($cgst) : null,
            'sgst_formatted' => $sgst !== null ? self::formatMoneyInr($sgst) : null,
            'igst_formatted' => $igst !== null ? self::formatMoneyInr($igst) : null,
            'includes_addon' => false,
            'addon' => null,
        ];
    }

    /**
     * Distinguished optional in-person interview add-on.
     * Amount is the configured sticker price; GST-inclusive treatment is provisional.
     *
     * @return array<string, mixed>
     */
    public static function forDistinguishedInterviewAddon(): array
    {
        $cfg = (array) config('jannayaks.tier_pricing.addons.distinguished_in_person_interview', []);
        $inclusiveRupees = (int) ($cfg['base_amount'] ?? 0);
        if ($inclusiveRupees <= 0) {
            throw new InvalidArgumentException('Invalid distinguished interview add-on amount.');
        }

        $gstInclusive = (bool) ($cfg['gst_inclusive'] ?? true);
        $gstRate = (float) config('jannayaks.tier_pricing.gst_percent', 18);

        if ($gstInclusive) {
            $inclusivePaise = self::rupeesToPaise($inclusiveRupees);
            $split = self::splitInclusiveTotal($inclusivePaise, $gstRate);
            $cgst = null;
            $sgst = null;
            $igst = null;
            if ($split['gst_paise'] > 0) {
                $cgst = intdiv($split['gst_paise'], 2);
                $sgst = $split['gst_paise'] - $cgst;
            }

            return [
                'currency' => self::CURRENCY,
                'tier_key' => 'distinguished_in_person_interview',
                'label' => (string) ($cfg['label'] ?? 'Distinguished In-Person Journalist Interview'),
                'description' => 'Optional add-on; not included in the base Distinguished package.',
                'gst_inclusive' => true,
                'gst_rate_percent' => $gstRate,
                'amount_incl_paise' => $inclusivePaise,
                'amount_incl_rupees' => $inclusiveRupees,
                'base_paise' => $split['base_paise'],
                'gst_paise' => $split['gst_paise'],
                'cgst_paise' => $cgst,
                'sgst_paise' => $sgst,
                'igst_paise' => $igst,
                'amount_incl_formatted' => self::formatMoneyInr($inclusivePaise),
                'base_formatted' => self::formatMoneyInr($split['base_paise']),
                'gst_formatted' => self::formatMoneyInr($split['gst_paise']),
                'cgst_formatted' => $cgst !== null ? self::formatMoneyInr($cgst) : null,
                'sgst_formatted' => $sgst !== null ? self::formatMoneyInr($sgst) : null,
                'igst_formatted' => $igst !== null ? self::formatMoneyInr($igst) : null,
            ];
        }

        return self::buildPlusGst(
            baseRupees: $inclusiveRupees,
            gstRate: $gstRate,
            label: (string) ($cfg['label'] ?? 'Distinguished In-Person Journalist Interview'),
            description: 'Optional add-on; not included in the base Distinguished package.',
            itemKey: 'distinguished_in_person_interview',
        );
    }

    /**
     * Combined application package (+ optional Distinguished interview add-on).
     *
     * @return array<string, mixed>
     */
    public static function forApplicationPackage(string $tierKey, bool $includeDistinguishedAddon = false): array
    {
        $package = self::forTier($tierKey);
        $addon = null;

        if ($includeDistinguishedAddon) {
            if ($tierKey !== 'distinguished') {
                throw new InvalidArgumentException('In-person interview add-on is only available for Distinguished.');
            }
            $addon = self::forDistinguishedInterviewAddon();
        }

        if ($addon === null) {
            return $package;
        }

        $totalPaise = (int) $package['amount_incl_paise'] + (int) $addon['amount_incl_paise'];
        $basePaise = (int) $package['base_paise'] + (int) $addon['base_paise'];
        $gstPaise = (int) $package['gst_paise'] + (int) $addon['gst_paise'];
        $cgst = ((int) ($package['cgst_paise'] ?? 0)) + ((int) ($addon['cgst_paise'] ?? 0));
        $sgst = ((int) ($package['sgst_paise'] ?? 0)) + ((int) ($addon['sgst_paise'] ?? 0));
        $igstPaise = null;
        if ($package['igst_paise'] !== null || $addon['igst_paise'] !== null) {
            $igstPaise = ((int) ($package['igst_paise'] ?? 0)) + ((int) ($addon['igst_paise'] ?? 0));
        }

        return [
            'currency' => self::CURRENCY,
            'tier_key' => $tierKey,
            'label' => $package['label'].' + '.$addon['label'],
            'description' => 'Package plus optional Distinguished in-person interview add-on.',
            'gst_inclusive' => true,
            'gst_rate_percent' => $package['gst_rate_percent'],
            'amount_incl_paise' => $totalPaise,
            'amount_incl_rupees' => (int) round($totalPaise / self::PAISE_PER_RUPEE),
            'base_paise' => $basePaise,
            'gst_paise' => $gstPaise,
            'cgst_paise' => $cgst > 0 ? $cgst : null,
            'sgst_paise' => $sgst > 0 ? $sgst : null,
            'igst_paise' => $igstPaise,
            'amount_incl_formatted' => self::formatMoneyInr($totalPaise),
            'base_formatted' => self::formatMoneyInr($basePaise),
            'gst_formatted' => self::formatMoneyInr($gstPaise),
            'cgst_formatted' => $cgst > 0 ? self::formatMoneyInr($cgst) : null,
            'sgst_formatted' => $sgst > 0 ? self::formatMoneyInr($sgst) : null,
            'igst_formatted' => $igstPaise !== null ? self::formatMoneyInr($igstPaise) : null,
            'includes_addon' => true,
            'addon' => $addon,
            'package' => $package,
        ];
    }

    public static function forAnnualMembership(): array
    {
        return self::forPlusGstItem(
            (array) config('jannayaks.tier_pricing.membership', []),
            (float) config('jannayaks.tier_pricing.gst_percent', 18),
            'Annual Membership',
        );
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
