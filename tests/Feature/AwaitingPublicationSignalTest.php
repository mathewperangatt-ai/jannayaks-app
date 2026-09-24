<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwaitingPublicationSignalTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_badge_counts_awaiting_publication_applications(): void
    {
        $member = \App\Models\User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Approved Leader',
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        // paid() is an afterCreating hook that resets status; set the workflow status last.
        $application->forceFill(['status' => Application::STATUS_AWAITING_PUBLICATION])->save();

        $this->assertSame('1', ApplicationResource::getNavigationBadge());
    }

    public function test_navigation_badge_is_null_when_queue_is_empty(): void
    {
        $this->assertNull(ApplicationResource::getNavigationBadge());
    }
}
