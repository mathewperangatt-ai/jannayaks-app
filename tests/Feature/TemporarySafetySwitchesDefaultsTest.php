<?php

namespace Tests\Feature;

use App\Services\ApplicationPaymentStateService;
use App\Support\EditorialTestBypass;
use App\Support\PublicationTestBypass;
use Tests\TestCase;

/**
 * Smoke: temporary test switches stay off under the test harness defaults
 * (phpunit.xml + config/jannayaks.php env defaults). Does not override config.
 */
class TemporarySafetySwitchesDefaultsTest extends TestCase
{
    public function test_temporary_safety_switches_are_off_by_default(): void
    {
        $this->assertFalse(
            ApplicationPaymentStateService::testingModeActive(),
            'JANNAYAKS_PAYMENTS_TESTING_MODE must remain off in the test harness.',
        );

        $editorial = app(EditorialTestBypass::class);
        $this->assertFalse($editorial->enabled());
        $this->assertSame([], $editorial->referenceCodes());

        $publication = app(PublicationTestBypass::class);
        $this->assertFalse($publication->enabled());
        $this->assertSame([], $publication->referenceCodes());
    }
}
