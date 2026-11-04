<?php

namespace App\Services\Backup;

/**
 * Pure retention selection over backup object keys.
 *
 * Expected key layout inside the dedicated backup bucket:
 *   {daily|weekly|monthly}/{slug}-YYYYMMDD-HHMMSS.tar.gz[.age]
 *
 * Rules:
 * - Keep the newest N keys per tier (by the timestamp embedded in the key).
 * - Only keys that exactly match the expected pattern for THIS application's
 *   slug are ever eligible for deletion; anything else in the bucket is
 *   untouchable.
 */
class RetentionSelector
{
    /**
     * @param  list<string>  $keys        all object keys present in the backup bucket
     * @param  array{daily:int,weekly:int,monthly:int}  $retention
     * @param  string  $slug        application archive slug
     * @return list<string> keys safe to delete
     */
    public static function selectForPrune(array $keys, array $retention, string $slug): array
    {
        $byTier = ['daily' => [], 'weekly' => [], 'monthly' => []];

        foreach ($keys as $key) {
            if (($stamp = self::parseStamp((string) $key, $slug)) !== null) {
                $tier = explode('/', $key, 2)[0];
                $byTier[$tier][$stamp][] = (string) $key;
            }
        }

        $delete = [];
        foreach ($byTier as $tier => $grouped) {
            $keep = max(0, (int) ($retention[$tier] ?? 0));
            $stamps = array_keys($grouped);
            rsort($stamps); // newest first

            $kept = 0;
            foreach ($stamps as $stamp) {
                foreach ($grouped[$stamp] as $key) {
                    if ($kept < $keep) {
                        $kept++;
                    } else {
                        $delete[] = $key;
                    }
                }
            }
        }

        sort($delete);

        return $delete;
    }

    /**
     * Returns the sortable "YYYYMMDDHHMMSS" stamp when the key matches the
     * strict per-application archive pattern; null otherwise.
     */
    public static function parseStamp(string $key, string $slug): ?string
    {
        $escaped = preg_quote($slug, '#');

        if (preg_match('#^(daily|weekly|monthly)/'.$escaped.'-(\d{8})-(\d{6})\.tar\.gz(\.age)?$#', $key, $m) !== 1) {
            return null;
        }

        $date = $m[2];
        if (! checkdate((int) substr($date, 4, 2), (int) substr($date, 6, 2), (int) substr($date, 0, 4))) {
            return null;
        }

        return $date.$m[3];
    }
}
