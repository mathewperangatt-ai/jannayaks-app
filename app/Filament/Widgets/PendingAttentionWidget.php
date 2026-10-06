<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use App\Models\MediaItem;
use App\Models\Profile;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin dashboard "Pending attention" queue.
 *
 * Surfaces genuinely actionable workflow state that already exists:
 *   - customer media awaiting approval (MediaItem pending);
 *   - open customer revision requests (EditorialRevisionRequest);
 *   - open post-publication maintenance requests;
 *   - applications awaiting publication (admin action);
 *   - applications awaiting editorial preparation/review;
 *   - profiles awaiting customer review (informational).
 *
 * Presentation only — no new workflow semantics, no notification framework.
 * Each category shows its TRUE outstanding count (never capped) while
 * listing at most 10 items; when more exist, a "View all" link opens the
 * Applications list with the matching filter pre-applied.
 */
class PendingAttentionWidget extends Widget
{
    protected string $view = 'filament.widgets.pending-attention';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected const VISIBLE_PER_CATEGORY = 10;

    /** @var list<array<string, mixed>> */
    public array $items = [];

    /** @var list<array<string, mixed>> */
    public array $categories = [];

    public function mount(): void
    {
        $this->categories = $this->buildCategories();
        $this->items = array_merge(
            ...array_map(fn (array $category): array => $category['items'], $this->categories),
        );
    }

    /** @return list<array<string, mixed>> */
    private function buildCategories(): array
    {
        $categories = [];

        // 1. Customer-submitted photographs awaiting admin approval.
        $mediaQuery = MediaItem::query()
            ->where('mediable_type', (new Profile)->getMorphClass())
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->where('review_status', MediaItem::REVIEW_PENDING)
            ->whereNotNull('storage_path_key');

        $mediaItems = [];
        $mediaQuery->clone()
            ->with('mediable')
            ->orderByDesc('id')
            ->limit(self::VISIBLE_PER_CATEGORY)
            ->get()
            ->each(function (MediaItem $media) use (&$mediaItems): void {
                $profile = $media->mediable;
                $applicationId = $profile?->application?->id;

                $mediaItems[] = [
                    'kind' => 'media',
                    'title' => 'Photograph awaiting approval',
                    'subject' => $profile?->display_name ?: $profile?->full_name ?? ('Profile #'.$media->mediable_id),
                    'meta' => 'Uploaded '.($media->created_at?->format('d M Y, H:i') ?? '—'),
                    'url' => $applicationId ? ApplicationResource::getUrl('view', ['record' => $applicationId]) : null,
                    'tone' => '#b3261e',
                ];
            });

        $categories[] = [
            'key' => 'media',
            'label' => 'Photographs awaiting approval',
            'total' => $mediaQuery->clone()->toBase()->count(),
            'viewAllUrl' => self::filteredApplicationsUrl(['photo_queue' => ['value' => 'pending']]),
            'items' => $mediaItems,
        ];

        // 2. Open pre-publication customer revision requests.
        $revisionQuery = EditorialRevisionRequest::query()
            ->whereIn('status', [EditorialRevisionRequest::STATUS_SUBMITTED, EditorialRevisionRequest::STATUS_IN_PROGRESS])
            ->where('request_type', '!=', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE);

        $revisionItems = [];
        $revisionQuery->clone()
            ->with('application')
            ->orderByDesc('id')
            ->limit(self::VISIBLE_PER_CATEGORY)
            ->get()
            ->each(function (EditorialRevisionRequest $revision) use (&$revisionItems): void {
                $revisionItems[] = [
                    'kind' => 'revision',
                    'title' => 'Customer revision request — Round '.((int) $revision->round_number),
                    'subject' => $revision->application?->full_name ?? ('Application #'.$revision->application_id),
                    'meta' => 'Requested '.($revision->created_at?->format('d M Y, H:i') ?? '—'),
                    'url' => ApplicationResource::getUrl('view', ['record' => $revision->application_id]),
                    'tone' => '#b3261e',
                ];
            });

        $categories[] = [
            'key' => 'revision',
            'label' => 'Pre-publication revision requests',
            'total' => $revisionQuery->clone()->toBase()->count(),
            'viewAllUrl' => self::filteredApplicationsUrl(['status' => ['values' => [Application::STATUS_EDITORIAL_REVISION_REQUESTED]]]),
            'items' => $revisionItems,
        ];

        // 3. Open post-publication maintenance requests (Pass 1/2 lifecycle).
        $maintenanceQuery = EditorialRevisionRequest::query()
            ->where('request_type', EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)
            ->whereIn('status', [
                EditorialRevisionRequest::STATUS_SUBMITTED,
                EditorialRevisionRequest::STATUS_IN_PROGRESS,
                EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW,
                EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED,
            ]);

        $maintenanceItems = [];
        $maintenanceQuery->clone()
            ->with('application')
            ->orderByDesc('id')
            ->limit(self::VISIBLE_PER_CATEGORY)
            ->get()
            ->each(function (EditorialRevisionRequest $maintenance) use (&$maintenanceItems): void {
                $classification = $maintenance->billing_classification === EditorialRevisionRequest::BILLING_COMPLIMENTARY ? 'Complimentary' : 'Paid';
                $title = match ($maintenance->status) {
                    EditorialRevisionRequest::STATUS_CUSTOMER_PREVIEW => 'Maintenance preview awaiting customer approval — '.$classification,
                    EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED => 'Maintenance update customer-approved — ready to publish',
                    default => filled($maintenance->customer_correction_text)
                        ? 'Maintenance correction requested — '.$classification
                        : 'Published-profile update request — '.$classification,
                };

                $maintenanceItems[] = [
                    'kind' => 'maintenance',
                    'title' => $title,
                    'subject' => $maintenance->application?->full_name ?? ('Application #'.$maintenance->application_id),
                    'meta' => 'Updated '.($maintenance->updated_at?->format('d M Y, H:i') ?? '—')
                        .($maintenance->next_eligible_on?->format(' d M Y') ? ' · next complimentary window '.$maintenance->next_eligible_on->format('d M Y') : ''),
                    'url' => ApplicationResource::getUrl('view', ['record' => $maintenance->application_id]),
                    'tone' => $maintenance->status === EditorialRevisionRequest::STATUS_CUSTOMER_APPROVED ? '#c0762e' : '#6f8075',
                ];
            });

        $categories[] = [
            'key' => 'maintenance',
            'label' => 'Published-profile updates',
            'total' => $maintenanceQuery->clone()->toBase()->count(),
            'viewAllUrl' => self::filteredApplicationsUrl(['maintenance_stage' => ['value' => 'open']]),
            'items' => $maintenanceItems,
        ];

        // 4. Approved and waiting for the admin publication action.
        $categories[] = $this->applicationStatusCategory(
            key: 'publish',
            label: 'Profiles awaiting publication',
            statuses: [Application::STATUS_AWAITING_PUBLICATION],
            orderColumn: 'customer_approved_at',
            itemTitle: 'Profile approved — awaiting publication',
            itemMeta: fn (Application $application): string => 'Customer approved '.($application->customer_approved_at?->format('d M Y, H:i') ?? '—'),
            tone: '#c0762e',
        );

        // 5. Editorial preparation / review in progress.
        $categories[] = $this->applicationStatusCategory(
            key: 'editorial',
            label: 'Profiles awaiting editorial preparation',
            statuses: [Application::STATUS_AWAITING_EDITORIAL_REVIEW, Application::STATUS_IN_EDITORIAL_REVIEW],
            orderColumn: 'online_interview_completed_at',
            itemTitle: 'Profile awaiting editorial preparation',
            itemMeta: fn (Application $application): string => 'Interview submitted '.($application->online_interview_completed_at?->format('d M Y, H:i') ?? '—'),
            tone: '#6f8075',
        );

        // 6. Released to the customer — informational.
        $categories[] = $this->applicationStatusCategory(
            key: 'customer',
            label: 'Awaiting customer review (pre-publication)',
            statuses: [Application::STATUS_EDITORIAL_APPROVED],
            orderColumn: 'customer_preview_released_at',
            itemTitle: 'Awaiting customer review',
            itemMeta: fn (Application $application): string => 'Released '.($application->customer_preview_released_at?->format('d M Y, H:i') ?? '—'),
            tone: '#52758a',
            extraWhere: fn (Builder $query): Builder => $query->whereNotNull('customer_preview_released_at'),
        );

        return array_values(array_filter(
            $categories,
            fn (array $category): bool => $category['total'] > 0,
        ));
    }

    /**
     * @param  list<string>  $statuses
     * @param  callable(Application): string  $itemMeta
     * @param  (callable(Builder): Builder)|null  $extraWhere
     * @return array<string, mixed>
     */
    private function applicationStatusCategory(
        string $key,
        string $label,
        array $statuses,
        string $orderColumn,
        string $itemTitle,
        callable $itemMeta,
        string $tone,
        ?callable $extraWhere = null,
    ): array {
        $query = Application::query()->whereIn('status', $statuses);
        if ($extraWhere !== null) {
            $query = $extraWhere($query);
        }

        $items = [];
        $query->clone()
            ->orderByDesc($orderColumn)
            ->limit(self::VISIBLE_PER_CATEGORY)
            ->get()
            ->each(function (Application $application) use (&$items, $itemTitle, $itemMeta, $tone): void {
                $items[] = [
                    'kind' => $this->kindForLabel($itemTitle),
                    'title' => $itemTitle,
                    'subject' => $application->full_name,
                    'meta' => $itemMeta($application),
                    'url' => ApplicationResource::getUrl('view', ['record' => $application->id]),
                    'tone' => $tone,
                ];
            });

        return [
            'key' => $key,
            'label' => $label,
            'total' => $query->clone()->toBase()->count(),
            'viewAllUrl' => self::filteredApplicationsUrl(['status' => ['values' => $statuses]]),
            'items' => $items,
        ];
    }

    private function kindForLabel(string $itemTitle): string
    {
        return match ($itemTitle) {
            'Profile approved — awaiting publication' => 'publish',
            'Profile awaiting editorial preparation' => 'editorial',
            default => 'customer',
        };
    }

    /**
     * URL to the existing Applications list with table filters pre-applied.
     * ListRecords binds the table state to the URL as `filters`/`search`.
     *
     * @param  array<string, mixed>  $filters
     */
    private static function filteredApplicationsUrl(array $filters): string
    {
        return ApplicationResource::getUrl('index').'?'.http_build_query(['filters' => $filters]);
    }

    /** @return list<array<string, mixed>> */
    private function pendingItems(): array
    {
        return $this->items;
    }

    public function render(): View
    {
        return view($this->view, ['items' => $this->items, 'categories' => $this->categories]);
    }
}
