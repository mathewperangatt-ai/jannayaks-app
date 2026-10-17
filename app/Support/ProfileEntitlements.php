<?php

namespace App\Support;

use App\Models\Application;

/**
 * Photo and video entitlements for living profiles.
 * New Standard Profile applications (pricing_generation=standard_profile) are
 * capped at three photographs and no external video links, even though they
 * store package_tier=distinguished for editorial depth compatibility.
 * Historical rows (pricing_generation null) keep their package-tier entitlements.
 */
class ProfileEntitlements
{
    public static function photoSlots(Application $application): int
    {
        if ($application->pricing_generation === Application::PRICING_GENERATION_STANDARD) {
            return max(0, (int) config('jannayaks.tier_pricing.standard_profile.photo_slots', 3));
        }

        $tier = (string) $application->package_tier;
        $slots = (int) config('jannayaks.tier_pricing.packages.'.$tier.'.photo_slots', 0);

        return max(0, $slots);
    }

    public static function allowsVideoLink(Application $application): bool
    {
        if ($application->pricing_generation === Application::PRICING_GENERATION_STANDARD) {
            return (bool) config('jannayaks.tier_pricing.standard_profile.includes_video_link', false);
        }

        $tier = (string) $application->package_tier;

        return (bool) config('jannayaks.tier_pricing.packages.'.$tier.'.includes_video_link', false);
    }
}
