<?php

namespace Tests\Unit;

use App\Services\Backup\RetentionSelector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Retention selection demonstrated against synthetic key sets — the
 * "prove pruning before enabling it" requirement.
 */
class RetentionSelectorTest extends TestCase
{
    private string $slug = 'jannayaks';

    #[Test]
    public function keeps_newest_n_daily_archives_and_selects_older_ones(): void
    {
        $keys = [
            'daily/jannayaks-20261101-031500.tar.gz.age',
            'daily/jannayaks-20261102-031500.tar.gz.age',
            'daily/jannayaks-20261103-031500.tar.gz.age',
        ];

        $delete = RetentionSelector::selectForPrune($keys, ['daily' => 2, 'weekly' => 4, 'monthly' => 6], $this->slug);

        $this->assertSame(['daily/jannayaks-20261101-031500.tar.gz.age'], $delete);
    }

    #[Test]
    public function tiers_are_pruned_independently(): void
    {
        $keys = [
            'daily/jannayaks-20261101-031500.tar.gz.age',
            'daily/jannayaks-20261102-031500.tar.gz.age',
            'weekly/jannayaks-20261004-031500.tar.gz.age',
            'weekly/jannayaks-20261011-031500.tar.gz.age',
            'weekly/jannayaks-20261018-031500.tar.gz.age',
            'monthly/jannayaks-20260601-031500.tar.gz.age',
        ];

        $delete = RetentionSelector::selectForPrune($keys, ['daily' => 1, 'weekly' => 2, 'monthly' => 6], $this->slug);

        $this->assertSame([
            'daily/jannayaks-20261101-031500.tar.gz.age',
            'weekly/jannayaks-20261004-031500.tar.gz.age',
        ], $delete);
    }

    #[Test]
    public function never_selects_foreign_slugs_unexpected_keys_or_other_prefixes(): void
    {
        $keys = [
            'daily/iconsherald-20261101-031500.tar.gz.age',   // other application
            'daily/jannayaks-not-a-date.tar.gz.age',            // malformed stamp
            'daily/jannayaks-2026111-031500.tar.gz.age',        // short date
            'daily/jannayaks-20261101-0315.tar.gz.age',         // short time
            'daily/jannayaks-20261301-031500.tar.gz.age',       // month 13
            'daily/subdir/jannayaks-20261101-031500.tar.gz.age',// nested path
            'elsewhere/jannayaks-20261101-031500.tar.gz.age',   // unknown tier
            'daily/jannayaks-20261101-031500.tar.gz',           // plaintext variant — still ours
            'README.md',
        ];

        // Only the valid plaintext entry matches; it is the single newest so
        // with retention 0 it is deleted, everything else untouched.
        $delete = RetentionSelector::selectForPrune($keys, ['daily' => 0, 'weekly' => 0, 'monthly' => 0], $this->slug);

        $this->assertSame(['daily/jannayaks-20261101-031500.tar.gz'], $delete);
    }

    #[Test]
    public function keeps_nothing_when_bucket_has_no_our_archives(): void
    {
        $this->assertSame(
            [],
            RetentionSelector::selectForPrune(['other/thing.txt', 'daily/x.tar.gz'], ['daily' => 7, 'weekly' => 4, 'monthly' => 6], $this->slug),
        );
    }
}
