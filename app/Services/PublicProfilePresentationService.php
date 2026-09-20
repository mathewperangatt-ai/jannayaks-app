<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\ProfileGeography;
use Illuminate\Support\Collection;

class PublicProfilePresentationService
{
    public function __construct(private ProfileUrlService $profileUrls) {}

    /**
     * @return array{
     *     profile: Profile,
     *     canonicalUrl: string,
     *     displayName: string,
     *     profession: ?string,
     *     headline: ?string,
     *     locationLabel: ?string,
     *     publicOffices: Collection,
     *     english: ?EditorialContent,
     *     malayalam: ?EditorialContent,
     *     photo: ?MediaItem,
     *     photos: Collection,
     *     videoLinks: Collection,
     *     language: string,
     *     activeEditorial: ?EditorialContent
     * }
     */
    public function present(Profile $profile, string $language = 'en'): array
    {
        if (! $this->profileUrls->isPubliclyVisible($profile)) {
            abort(404);
        }

        $profile->loadMissing([
            'geography.district.state',
            'geography.localBody',
            'geography.ward',
            'publicOffices',
            'application',
            'media',
            'externalLinks',
        ]);

        $english = $this->resolveApprovedEnglish($profile);
        $malayalam = $this->resolveApprovedMalayalam($profile, $english);
        $language = in_array($language, ['en', 'ml'], true) ? $language : 'en';
        $active = $language === 'ml' && $malayalam ? $malayalam : $english;

        $canonicalUrl = $this->profileUrls->canonicalPublicUrl($profile);
        if ($canonicalUrl === null) {
            abort(404);
        }

        $photos = $this->publicProfilePhotos($profile);
        $photo = $photos->first(fn (MediaItem $item): bool => (bool) $item->is_primary) ?? $photos->first();

        return [
            'profile' => $profile,
            'canonicalUrl' => $canonicalUrl,
            'displayName' => $this->displayName($profile),
            'profession' => filled($profile->profession) ? (string) $profile->profession : null,
            'headline' => filled($profile->bio_headline) ? (string) $profile->bio_headline : null,
            'locationLabel' => $this->locationLabel($profile->geography),
            'publicOffices' => $profile->publicOffices,
            'english' => $english,
            'malayalam' => $malayalam,
            'photo' => $photo,
            'photos' => $photos,
            'videoLinks' => $this->publicVideoLinks($profile),
            'language' => $language === 'ml' && $malayalam ? 'ml' : 'en',
            'activeEditorial' => $active,
        ];
    }

    public function displayName(Profile $profile): string
    {
        if (filled($profile->display_name)) {
            return (string) $profile->display_name;
        }

        return (string) ($profile->full_name ?: 'Jannayaks profile');
    }

    public function locationLabel(?ProfileGeography $geography): ?string
    {
        if (! $geography) {
            return null;
        }

        $parts = [];
        if (filled($geography->locality_place)) {
            $parts[] = (string) $geography->locality_place;
        }
        if ($geography->ward && filled($geography->ward->name)) {
            $parts[] = (string) $geography->ward->name;
        }
        if ($geography->localBody && filled($geography->localBody->name)) {
            $parts[] = (string) $geography->localBody->name;
        }
        if ($geography->district && filled($geography->district->name)) {
            $parts[] = (string) $geography->district->name;
        }
        if (filled($geography->state_region_name)) {
            $parts[] = (string) $geography->state_region_name;
        } elseif ($geography->district?->state && filled($geography->district->state->name)) {
            $parts[] = (string) $geography->district->state->name;
        }
        if (filled($geography->country_code) && strtoupper((string) $geography->country_code) !== 'IN') {
            $parts[] = strtoupper((string) $geography->country_code);
        }

        $parts = array_values(array_unique(array_filter($parts)));

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function publicProfilePhoto(Profile $profile): ?MediaItem
    {
        return $this->publicProfilePhotos($profile)->first(
            fn (MediaItem $item): bool => (bool) $item->is_primary
        ) ?? $this->publicProfilePhotos($profile)->first();
    }

    /**
     * @return Collection<int, MediaItem>
     */
    public function publicProfilePhotos(Profile $profile): Collection
    {
        return $profile->media
            ->filter(fn (MediaItem $item): bool => $item->isApprovedForPublicDisplay())
            ->sortBy([
                fn (MediaItem $item) => $item->is_primary ? 0 : 1,
                fn (MediaItem $item) => $item->display_order,
                fn (MediaItem $item) => $item->id,
            ])
            ->values();
    }

    /**
     * @return Collection<int, ProfileExternalLink>
     */
    public function publicVideoLinks(Profile $profile): Collection
    {
        return $profile->externalLinks
            ->filter(fn ($link): bool => $link->isActiveVideo())
            ->values();
    }

    public function resolveApprovedEnglish(Profile $profile): ?EditorialContent
    {
        $application = $profile->application;
        if (! $application instanceof Application || $application->published_english_editorial_content_id === null) {
            return null;
        }

        return EditorialContent::query()
            ->whereKey($application->published_english_editorial_content_id)
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->first();
    }

    public function resolveApprovedMalayalam(Profile $profile, ?EditorialContent $english): ?EditorialContent
    {
        $application = $profile->application;
        if (! $application instanceof Application || $application->published_malayalam_editorial_content_id === null) {
            return null;
        }

        $bound = EditorialContent::query()
            ->whereKey($application->published_malayalam_editorial_content_id)
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('status', EditorialContent::STATUS_APPROVED)
            ->first();

        if ($english instanceof EditorialContent
            && $bound instanceof EditorialContent
            && $bound->source_editorial_content_id !== null
            && (int) $bound->source_editorial_content_id !== (int) $english->id) {
            return null;
        }

        return $bound;
    }

    /**
     * Concise gallery/search card descriptor — public fields only.
     */
    public function conciseDescriptor(Profile $profile): ?string
    {
        if (filled($profile->profession)) {
            return (string) $profile->profession;
        }
        if (filled($profile->bio_headline)) {
            return (string) $profile->bio_headline;
        }

        $office = $profile->relationLoaded('publicOffices')
            ? $profile->publicOffices->first()
            : $profile->publicOffices()->orderBy('sort_order')->first();

        if ($office && filled($office->office_name)) {
            return (string) $office->office_name;
        }

        return null;
    }
}
