<?php

namespace App\Hosting;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\ProfilePublicOffice;
use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use App\Services\ProfileUrlService;
use App\Services\SitemapService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Allowlisted JSON mapping for IconsHerald Hosting. Uses existing presentation
 * and media approval rules; never embeds Eloquent models or private fields.
 */
class HostingProfilePayloadBuilder
{
    public function __construct(
        private ProfileUrlService $profileUrls,
        private PublicProfilePresentationService $presentation,
        private PublicProfileSearchService $profileSearch,
    ) {}

    /**
     * Resolve a slug to an eligible IconsHerald-hosted public profile, or null.
     */
    public function findEligibleBySlug(string $slug): ?Profile
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '' || str_contains($slug, '/') || str_contains($slug, '\\') || str_contains($slug, '..')) {
            return null;
        }

        $profile = $this->eligibleBaseQuery()
            ->whereRaw('LOWER(slug) = ?', [$normalized])
            ->with([
                'application',
                'geography.district.state',
                'geography.localBody',
                'geography.ward',
                'publicOffices',
                'media',
                'externalLinks',
            ])
            ->first();

        if ($profile === null || ! $this->isEligible($profile)) {
            return null;
        }

        return $profile;
    }

    /**
     * Shared eligibility: publicly visible, IconsHerald destination, not a demo.
     * Demo rule matches SitemapService / PublicProfilePresentationService
     * (@jannayaks.internal owner email).
     */
    public function isEligible(Profile $profile): bool
    {
        return $this->profileUrls->isPubliclyVisible($profile)
            && $this->profileUrls->isHostedOnIconsHerald($profile)
            && filled($profile->slug)
            && ! $this->isDemoProfile($profile);
    }

    /**
     * Sitemap listing query — same visibility base as public search, plus IH
     * destination and demo exclusion. Does not eager-load media or editorial.
     *
     * @return Builder<Profile>
     */
    public function eligibleSitemapQuery(): Builder
    {
        return $this->eligibleBaseQuery();
    }

    /**
     * @return Builder<Profile>
     */
    private function eligibleBaseQuery(): Builder
    {
        return $this->profileSearch
            ->publishedBaseQuery()
            ->whereHas(
                'application',
                fn ($q) => $q->where('hosting_destination', Application::HOSTING_DESTINATION_ICONSHERALD)
            )
            ->whereDoesntHave(
                'user',
                fn ($q) => $q->where('email', 'like', '%'.SitemapService::DEMO_EMAIL_DOMAIN)
            );
    }

    /**
     * Authoritative demo check without requiring a preloaded user relation.
     */
    private function isDemoProfile(Profile $profile): bool
    {
        if ($profile->relationLoaded('user')) {
            return $this->presentation->isDemonstration($profile);
        }

        return $profile->user()
            ->where('email', 'like', '%'.SitemapService::DEMO_EMAIL_DOMAIN)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildProfilePayload(Profile $profile): array
    {
        $english = $this->presentation->resolveApprovedEnglish($profile);
        $malayalam = $this->presentation->resolveApprovedMalayalam($profile, $english);
        $photos = $this->presentation->publicProfilePhotos($profile);
        $videos = $this->presentation->publicVideoLinks($profile);

        $payload = [
            'slug' => (string) $profile->slug,
            'canonical_url' => (string) $this->profileUrls->canonicalPublicUrl($profile),
            'display_name' => $this->presentation->displayName($profile),
            'profession' => filled($profile->profession) ? (string) $profile->profession : null,
            'headline' => filled($profile->bio_headline) ? (string) $profile->bio_headline : null,
            'location_label' => $this->presentation->publicLocationLabel($profile),
            'editorial' => [
                'en' => $this->mapEditorial($english),
                'ml' => $this->mapEditorial($malayalam),
            ],
            'offices' => $this->mapOffices($profile->publicOffices),
            'videos' => $this->mapVideos($videos),
            'photos' => $this->mapPhotos($profile, $photos),
            'published_at' => $profile->published_at?->toAtomString(),
        ];

        // Already shown on the existing public Jannayaks profile page.
        if (filled($profile->reference_code)) {
            $payload['reference_code'] = (string) $profile->reference_code;
        }

        return $payload;
    }

    /**
     * @return array{slug: string, canonical_url: string, published_at: string|null}
     */
    public function buildSitemapEntry(Profile $profile): array
    {
        return [
            'slug' => (string) $profile->slug,
            'canonical_url' => (string) $this->profileUrls->canonicalPublicUrl($profile),
            'published_at' => $profile->published_at?->toAtomString(),
        ];
    }

    /**
     * @return array{title: string, summary: string, body: string}|null
     */
    private function mapEditorial(?EditorialContent $editorial): ?array
    {
        if (! $editorial instanceof EditorialContent) {
            return null;
        }

        return [
            'title' => (string) ($editorial->title ?? ''),
            'summary' => (string) ($editorial->summary ?? ''),
            'body' => (string) ($editorial->body ?? ''),
        ];
    }

    /**
     * @param  Collection<int, ProfilePublicOffice>  $offices
     * @return list<array{office_name: string, where_location: string|null, term_summary: string|null, is_current: bool}>
     */
    private function mapOffices(Collection $offices): array
    {
        return $offices
            // Composite sort key (asort compares arrays element-wise):
            // sort_order asc, then id as the tie-breaker.
            ->sortBy(fn (ProfilePublicOffice $office): array => [$office->sort_order, $office->id])
            ->values()
            ->map(fn (ProfilePublicOffice $office): array => [
                'office_name' => (string) $office->office_name,
                'where_location' => filled($office->where_location) ? (string) $office->where_location : null,
                'term_summary' => filled($office->term_summary) ? (string) $office->term_summary : null,
                'is_current' => (bool) $office->is_current,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, ProfileExternalLink>  $videos
     * @return list<array{label: string|null, url: string}>
     */
    private function mapVideos(Collection $videos): array
    {
        return $videos
            ->map(fn (ProfileExternalLink $link): array => [
                'label' => filled($link->label) ? (string) $link->label : null,
                'url' => (string) $link->url,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MediaItem>  $photos
     * @return list<array{url: string, alt_text: string|null, is_primary: bool, display_order: int}>
     */
    private function mapPhotos(Profile $profile, Collection $photos): array
    {
        return $photos
            ->map(fn (MediaItem $photo): array => [
                'url' => route('profiles.public.photo', [$profile, $photo]),
                'alt_text' => filled($photo->alt_text) ? (string) $photo->alt_text : null,
                'is_primary' => (bool) $photo->is_primary,
                'display_order' => (int) $photo->display_order,
            ])
            ->values()
            ->all();
    }
}
