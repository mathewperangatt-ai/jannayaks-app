<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialMalayalamConsent;
use App\Models\GeoState;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\ProfileGeography;
use App\Support\ProfileEntitlements;
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
     *     articleHasBody: bool,
     *     articleParagraphs: list<string>,
     *     featurePhoto: ?MediaItem,
     *     featureCopy: ?string,
     *     showFeatureColumn: bool,
     *     galleryEntries: Collection,
     *     galleryHasPhotograph: bool,
     *     videoLinks: Collection,
     *     language: string,
     *     activeEditorial: ?EditorialContent,
     *     referenceCode: ?string
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
            'user',
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
        $articleHasBody = $active instanceof EditorialContent && filled($active->body);
        $additionalPhotos = $photos
            ->reject(fn (MediaItem $item): bool => $photo instanceof MediaItem && (int) $item->id === (int) $photo->id)
            ->values();
        $composition = $this->magazineComposition($articleHasBody, $additionalPhotos, $active);

        return [
            'profile' => $profile,
            'canonicalUrl' => $canonicalUrl,
            'displayName' => $this->displayName($profile),
            'profession' => filled($profile->profession) ? (string) $profile->profession : null,
            'headline' => filled($profile->bio_headline) ? (string) $profile->bio_headline : null,
            'locationLabel' => $this->publicLocationLabel($profile),
            'locationCaption' => $this->locationCaption($profile),
            'publicOffices' => $profile->publicOffices,
            'english' => $english,
            'malayalam' => $malayalam,
            'photo' => $photo,
            'photos' => $photos,
            'articleHasBody' => $articleHasBody,
            'articleParagraphs' => $composition['articleParagraphs'],
            'featurePhoto' => $composition['featurePhoto'],
            'featureCopy' => $composition['featureCopy'],
            'showFeatureColumn' => $composition['showFeatureColumn'],
            'galleryEntries' => $composition['galleryEntries'],
            'galleryHasPhotograph' => $composition['galleryHasPhotograph'],
            'videoLinks' => $this->publicVideoLinks($profile),
            'language' => $language === 'ml' && $malayalam ? 'ml' : 'en',
            'activeEditorial' => $active,
            // Support/editorial reference only — never a routing key, never
            // an internal database id.
            'referenceCode' => filled($profile->reference_code) ? (string) $profile->reference_code : null,
        ];
    }

    public function displayName(Profile $profile): string
    {
        if (filled($profile->display_name)) {
            return (string) $profile->display_name;
        }

        return (string) ($profile->full_name ?: 'Jannayaks profile');
    }

    /**
     * Public location line for a profile. Demonstration profiles carry only
     * the mandatory default state record (no source-supported place), so
     * the line is withheld for them unless a district, local body, ward or
     * locality has actually been recorded.
     */
    public function publicLocationLabel(Profile $profile): ?string
    {
        $geography = $profile->geography;

        if ($this->isDemonstration($profile) && ! $this->hasSpecificPlace($geography)) {
            return null;
        }

        return $this->locationLabel($geography);
    }

    public function isDemonstration(Profile $profile): bool
    {
        return str_ends_with(strtolower((string) $profile->user?->email), SitemapService::DEMO_EMAIL_DOMAIN);
    }

    private function hasSpecificPlace(?ProfileGeography $geography): bool
    {
        return $geography !== null
            && (filled($geography->locality_place)
                || $geography->ward_id !== null
                || $geography->local_body_id !== null
                || $geography->district_id !== null);
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
        $countryName = trim((string) $geography->country_name);
        $countryCode = strtoupper((string) $geography->country_code);
        if ($countryName !== '' && strcasecmp($countryName, GeoState::currentCountryName()) !== 0) {
            $parts[] = $countryName;
        } elseif ($countryCode !== '' && $countryCode !== 'IN' && $countryCode !== 'ZZ') {
            $parts[] = $countryCode;
        }

        $parts = array_values(array_unique(array_filter($parts)));

        return $parts === [] ? null : implode(', ', $parts);
    }

    public function locationCaption(Profile $profile): string
    {
        if ($this->publicLocationLabel($profile) === null) {
            return 'PUBLIC LIFE';
        }

        $geography = $profile->geography;
        $state = trim((string) $geography?->state_region_name);
        $country = strtoupper((string) $geography?->country_code);
        if (strcasecmp($state, GeoState::currentDisplayName()) === 0 && $country === GeoState::currentCountryCode()) {
            return 'KERALAM · INDIA';
        }

        return 'BROAD LOCATION';
    }

    /**
     * A missing additional photograph is filled with the next unused paragraph
     * of the approved story. The summary is already the introduction and the
     * article quotation, so it is not repeated as a filler. When no further
     * distinct paragraph remains, that position is omitted and the article
     * keeps the text.
     *
     * @param  Collection<int, MediaItem>  $additionalPhotos
     * @return array{
     *     articleParagraphs: list<string>,
     *     featurePhoto: ?MediaItem,
     *     featureCopy: ?string,
     *     showFeatureColumn: bool,
     *     galleryEntries: Collection<int, array{position: int, photo: ?MediaItem, copy: ?string}>,
     *     galleryHasPhotograph: bool
     * }
     */
    private function magazineComposition(bool $articleHasBody, Collection $additionalPhotos, ?EditorialContent $active): array
    {
        $photosByPosition = [];
        foreach ($additionalPhotos->take(3)->values() as $index => $item) {
            $photosByPosition[$index + 1] = $item;
        }

        $paragraphs = $this->editorialParagraphs($active);
        $summary = $active instanceof EditorialContent ? trim((string) $active->summary) : '';
        $emptyPositions = array_values(array_filter(
            [1, 2, 3],
            fn (int $position): bool => ! array_key_exists($position, $photosByPosition),
        ));
        $articleParagraphs = $paragraphs;
        $assigned = [];

        if ($articleHasBody && $paragraphs !== [] && $emptyPositions !== []) {
            $kept = max(1, count($paragraphs) - count($emptyPositions));
            $articleParagraphs = array_slice($paragraphs, 0, $kept);
            $overflow = array_slice($paragraphs, $kept);
            $overflowIndex = 0;

            foreach ($emptyPositions as $position) {
                if (! array_key_exists($overflowIndex, $overflow)) {
                    break;
                }

                $candidate = $overflow[$overflowIndex];
                $overflowIndex++;
                if ($summary !== '' && $candidate === $summary) {
                    $articleParagraphs[] = $candidate;

                    continue;
                }

                $assigned[$position] = $candidate;
            }

            if ($overflowIndex < count($overflow)) {
                array_push($articleParagraphs, ...array_slice($overflow, $overflowIndex));
            }
        }

        $featurePhoto = $articleHasBody ? ($photosByPosition[1] ?? null) : null;
        $featureCopy = $featurePhoto instanceof MediaItem ? null : ($assigned[1] ?? null);
        $galleryPositions = $articleHasBody ? [2, 3] : [1, 2, 3];
        $galleryEntries = collect();

        foreach ($galleryPositions as $position) {
            $galleryPhoto = $photosByPosition[$position] ?? null;
            $copy = $assigned[$position] ?? null;
            if (! $galleryPhoto instanceof MediaItem && $copy === null) {
                continue;
            }

            $galleryEntries->push([
                'position' => $position,
                'photo' => $galleryPhoto instanceof MediaItem ? $galleryPhoto : null,
                'copy' => $galleryPhoto instanceof MediaItem ? null : $copy,
            ]);
        }

        return [
            'articleParagraphs' => array_values($articleParagraphs),
            'featurePhoto' => $featurePhoto instanceof MediaItem ? $featurePhoto : null,
            'featureCopy' => filled($featureCopy) ? $featureCopy : null,
            'showFeatureColumn' => $featurePhoto instanceof MediaItem || filled($featureCopy),
            'galleryEntries' => $galleryEntries->values(),
            'galleryHasPhotograph' => $galleryEntries->contains(
                fn (array $entry): bool => $entry['photo'] instanceof MediaItem,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function editorialParagraphs(?EditorialContent $active): array
    {
        if (! $active instanceof EditorialContent || ! filled($active->body)) {
            return [];
        }

        $parts = preg_split('/\n\s*\n/u', trim((string) $active->body)) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $paragraph): string => trim($paragraph),
            $parts,
        ), static fn (string $paragraph): bool => $paragraph !== ''));
    }

    public function publicProfilePhoto(Profile $profile): ?MediaItem
    {
        return $this->publicProfilePhotos($profile)->first(
            fn (MediaItem $item): bool => (bool) $item->is_primary
        ) ?? $this->publicProfilePhotos($profile)->first();
    }

    /**
     * Approved public photographs, capped at one primary plus three additional.
     *
     * Legacy rows beyond that cap stay in storage. The public selection keeps
     * the primary photograph, then fills the remaining places by display_order
     * and then id. A primary that is not itself publicly eligible is omitted,
     * and the next eligible photographs fill up to four places in that same order.
     *
     * @return Collection<int, MediaItem>
     */
    public function publicProfilePhotos(Profile $profile): Collection
    {
        return $profile->media
            ->filter(fn (MediaItem $item): bool => $item->isApprovedForPublicDisplay())
            ->sortBy([
                fn (MediaItem $left, MediaItem $right): int => ((int) $right->is_primary) <=> ((int) $left->is_primary),
                fn (MediaItem $left, MediaItem $right): int => ((int) $left->display_order) <=> ((int) $right->display_order),
                fn (MediaItem $left, MediaItem $right): int => $left->id <=> $right->id,
            ])
            ->take(ProfileEntitlements::CUSTOMER_VISIBLE_PHOTO_LIMIT)
            ->values();
    }

    /**
     * @return Collection<int, ProfileExternalLink>
     */
    public function publicVideoLinks(Profile $profile): Collection
    {
        // Stored historical links stay in the database for audit. They are
        // not part of the public profile.
        return collect();
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

        if (! $bound instanceof EditorialContent) {
            return null;
        }

        $hasConsentRecord = EditorialMalayalamConsent::query()
            ->where('application_id', $application->id)
            ->where('malayalam_editorial_content_id', $bound->id)
            ->exists();
        if (! $hasConsentRecord) {
            return $bound;
        }

        $consent = EditorialMalayalamConsent::query()
            ->where('application_id', $application->id)
            ->where('malayalam_editorial_content_id', $bound->id)
            ->whereNull('invalidated_at')
            ->first();
        if ($consent instanceof EditorialMalayalamConsent && $consent->matches($bound)) {
            return $bound;
        }

        return null;
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
