<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Profile;

class ApplicationLocationService
{
    /**
     * Copy the application location into an empty geography label.
     * An existing locality is left unchanged.
     */
    public function copyBroadLocationIfEmpty(Application $application, Profile $profile): void
    {
        $location = trim((string) $application->broad_location);
        if ($location === '') {
            return;
        }

        $geography = $profile->ensureMandatoryGeography();
        if (filled($geography->locality_place)) {
            return;
        }

        $geography->forceFill(['locality_place' => $location])->save();
    }
}
