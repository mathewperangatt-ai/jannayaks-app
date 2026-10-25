<?php

namespace App\Services;

use App\Models\Application;
use App\Models\GeoState;
use App\Models\Profile;
use App\Models\ProfileGeography;

class ApplicationLocationService
{
    /**
     * Copy the location collected once on the application onto the profile.
     * Kerala uses the district list. Other places stay free text and are not
     * forced into a Kerala district, local body or ward.
     */
    public function applyToProfile(Application $application, Profile $profile): ProfileGeography
    {
        $geography = $profile->ensureMandatoryGeography();

        if ($application->location_scope === 'kerala' && $application->location_district_id) {
            $geography->forceFill([
                'district_id' => $application->location_district_id,
                'local_body_id' => null,
                'ward_id' => null,
                'locality_place' => null,
                'state_region_name' => GeoState::currentDisplayName(),
                'country_code' => GeoState::currentCountryCode(),
                'country_name' => GeoState::currentCountryName(),
            ])->save();

            return $geography->fresh() ?? $geography;
        }

        if ($application->location_scope === 'elsewhere' && filled($application->broad_location)) {
            $geography->forceFill([
                'district_id' => null,
                'local_body_id' => null,
                'ward_id' => null,
                'locality_place' => $application->broad_location,
                'state_region_name' => $application->location_state,
                'country_code' => $this->countryCode((string) $application->location_country),
                'country_name' => $application->location_country,
            ])->save();
        }

        return $geography->fresh() ?? $geography;
    }

    public function countryCode(string $raw): string
    {
        $value = trim($raw);
        if (preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
            return strtoupper($value);
        }
        if (strcasecmp($value, GeoState::currentCountryName()) === 0) {
            return GeoState::currentCountryCode();
        }

        return 'ZZ';
    }
}
