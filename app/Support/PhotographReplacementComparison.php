<?php

namespace App\Support;

use App\Models\MediaItem;

/**
 * Staff comparison of a customer-visible photograph and a proposed replacement.
 * Rendering this pair does not approve, publish, or replace the current image.
 */
class PhotographReplacementComparison
{
    /**
     * @return array{current: ?MediaItem, proposed: ?MediaItem}
     */
    public static function pair(MediaItem $record): array
    {
        if ($record->review_status === MediaItem::REVIEW_PENDING
            && $record->media_type === MediaItem::TYPE_PROFILE_PHOTO) {
            return [
                'current' => self::currentPublic($record),
                'proposed' => $record,
            ];
        }

        if ($record->review_status === MediaItem::REVIEW_APPROVED
            && $record->privacy === MediaItem::PRIVACY_PUBLIC
            && $record->media_type === MediaItem::TYPE_PROFILE_PHOTO) {
            return [
                'current' => $record,
                'proposed' => self::latestProposed($record),
            ];
        }

        return ['current' => null, 'proposed' => null];
    }

    public static function isAvailable(MediaItem $record): bool
    {
        $pair = self::pair($record);

        return $pair['proposed'] instanceof MediaItem;
    }

    private static function currentPublic(MediaItem $record): ?MediaItem
    {
        $current = MediaItem::query()
            ->where('mediable_type', $record->mediable_type)
            ->where('mediable_id', $record->mediable_id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->where('review_status', MediaItem::REVIEW_APPROVED)
            ->where('privacy', MediaItem::PRIVACY_PUBLIC)
            ->whereKeyNot($record->id)
            ->orderByDesc('is_primary')
            ->orderBy('display_order')
            ->orderBy('id')
            ->first();

        return $current instanceof MediaItem ? $current : null;
    }

    private static function latestProposed(MediaItem $record): ?MediaItem
    {
        $proposed = MediaItem::query()
            ->where('mediable_type', $record->mediable_type)
            ->where('mediable_id', $record->mediable_id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->where('review_status', MediaItem::REVIEW_PENDING)
            ->whereKeyNot($record->id)
            ->orderByDesc('id')
            ->first();

        return $proposed instanceof MediaItem ? $proposed : null;
    }
}
