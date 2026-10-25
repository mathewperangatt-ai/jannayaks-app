<?php

namespace App\Support;

use App\Models\Application;

/**
 * Photo and video entitlements for living profiles.
 * Every living profile, including historical package rows, has the same
 * customer-visible photograph limit: one primary photograph and up to three
 * additional photographs. Stored package_tier still selects editorial depth
 * for historical rows. It does not change the photograph limit, and it does
 * not enable video links.
 */
class ProfileEntitlements
{
    public const CUSTOMER_VISIBLE_PHOTO_LIMIT = 4;

    public static function photoSlots(Application $application): int
    {
        return self::CUSTOMER_VISIBLE_PHOTO_LIMIT;
    }

    public static function allowsVideoLink(Application $application): bool
    {
        // A living profile does not offer video links. The stored package,
        // including a historical tier, cannot turn the feature back on.
        return false;
    }
}
