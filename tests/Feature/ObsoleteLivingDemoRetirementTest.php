<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Services\ObsoleteLivingDemoRetirementService;
use App\Support\ObsoleteLivingDemos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ObsoleteLivingDemoRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_retires_only_exact_obsolete_demo_identities(): void
    {
        $targets = [];
        foreach (ObsoleteLivingDemos::targets() as $i => $target) {
            $targets[] = $this->seedPublishedDemo(
                $target['email'],
                $target['full_name'],
                'obsolete'.$i,
            );
        }

        $kept = $this->seedPublishedDemo(
            'demo.t.gopalakrishnan@jannayaks.internal',
            'T. Gopalakrishnan',
            't.gopalakrishnan',
        );
        $member = $this->seedPublishedDemo(
            'member.real@example.com',
            'Real Member',
            'real.member',
        );

        Artisan::call('jannayaks:retire-obsolete-living-demos');

        foreach ($targets as $profile) {
            $profile->refresh();
            $this->assertSame('inactive', $profile->status, $profile->full_name);
            $this->assertNotNull($profile->unpublished_at, $profile->full_name);
        }

        $kept->refresh();
        $this->assertSame('published', $kept->status);
        $this->assertNull($kept->unpublished_at);

        $member->refresh();
        $this->assertSame('published', $member->status);
        $this->assertNull($member->unpublished_at);

        $retained = app(ObsoleteLivingDemoRetirementService::class)->retainedLivingDemoProfiles();
        $this->assertTrue($retained->contains(fn (Profile $p): bool => $p->id === $kept->id));
        $this->assertFalse($retained->contains(fn (Profile $p): bool => in_array($p->id, array_map(fn ($p) => $p->id, $targets), true)));
    }

    public function test_name_mismatch_and_already_retired_are_skipped_and_command_is_idempotent(): void
    {
        $match = $this->seedPublishedDemo('demo.r.leelamma@jannayaks.internal', 'R. Leelamma', 'r.leelamma');
        $mismatch = $this->seedPublishedDemo('demo.a.mariamma@jannayaks.internal', 'Not Mariamma', 'notmariamma');

        $service = app(ObsoleteLivingDemoRetirementService::class);
        $first = $service->retire(false);
        $this->assertNotEmpty(array_filter($first['retired'], fn ($row) => $row['full_name'] === 'R. Leelamma'));
        $this->assertNotEmpty(array_filter($first['skipped'], fn ($row) => ($row['reason'] ?? '') === 'name mismatch'));

        $second = $service->retire(false);
        $this->assertSame([], $second['retired']);
        $this->assertNotEmpty(array_filter($second['skipped'], fn ($row) => ($row['reason'] ?? '') === 'already retired'));

        $match->refresh();
        $mismatch->refresh();
        $this->assertSame('inactive', $match->status);
        $this->assertSame('published', $mismatch->status);
    }

    public function test_dry_run_does_not_change_status(): void
    {
        $profile = $this->seedPublishedDemo('demo.c.manoj.kumar@jannayaks.internal', 'C. Manoj Kumar', 'c.manoj.kumar');

        Artisan::call('jannayaks:retire-obsolete-living-demos', ['--dry-run' => true]);

        $profile->refresh();
        $this->assertSame('published', $profile->status);
        $this->assertNull($profile->unpublished_at);
    }

    private function seedPublishedDemo(string $email, string $fullName, string $slug): Profile
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $fullName,
            'email_verified_at' => now(),
        ]);

        return Profile::query()->create([
            'user_id' => $user->id,
            'status' => 'published',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => 'Demonstration',
            'slug' => $slug,
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
    }
}
