<?php

namespace App\Support;

use App\Models\Application;

/**
 * Photo and video entitlements for living profiles.
 * Every living profile, including historical package rows, may show four
 * photographs and no external video links. Stored media is not deleted when
 * an older profile already has more photographs or a video link on file.
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
        return false;
    }
}
