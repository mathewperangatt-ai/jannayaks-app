<?php

namespace Tests\Feature;

use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\InMemoriamLifecycleService;
use App\Services\InMemoriamUrlService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MemorialLateSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_appends_late_once_and_a_retry_does_not_double_it(): void
    {
        $admin = User::factory()->admin()->create();
        $urls = app(InMemoriamUrlService::class);
        $memorial = $this->memorial('John Mathew');

        $this->assertSame('late.john.mathew', $urls->withLatePrefix('john.mathew'));
        $this->assertSame('late.john.mathew', $urls->withLatePrefix('late.john.mathew'));
        $this->assertSame('late.john.mathew', $urls->withLatePrefix('late.late.john.mathew'));
        $this->assertSame('late.john.mathew', $urls->withLatePrefix('John.Mathew.LATE.late'));

        $assigned = $urls->assignSlug($memorial, 'john.mathew', $admin);
        $this->assertSame('late.john.mathew', $assigned->slug);

        $urls->assignSlug($assigned->fresh(), 'late.john.mathew', $admin);
        $urls->assignSlug($assigned->fresh(), 'late.late.john.mathew', $admin);

        $this->assertSame('late.john.mathew', $assigned->fresh()->slug);
        $this->assertSame(
            1,
            StaffActionLog::query()->where('action', 'in_memoriam.slug_assigned')->where('subject_id', $memorial->id)->count()
        );
    }

    public function test_a_taken_late_slug_uses_another_available_name_form_and_blocks_a_direct_collision(): void
    {
        $admin = User::factory()->admin()->create();
        $urls = app(InMemoriamUrlService::class);
        $first = $this->memorial('John Mathew');
        $urls->assignSlug($first, 'john.mathew', $admin);

        $second = $this->memorial('John Mathew');
        try {
            $urls->assignSlug($second, 'john.mathew', $admin);
            $this->fail('A colliding memorial slug must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }

        $generated = $urls->generateCanonicalSlug($second->fresh());
        $this->assertMatchesRegularExpression('/^late\.john\.mathew\d{2}$/', $generated);
        $this->assertSame(1, substr_count($generated, 'late.'));
        $this->assertNotSame('late.john.mathew', $generated);
        $this->assertFalse($urls->validateSlugCandidate('admin')['ok']);
    }

    public function test_publish_generates_a_late_slug_and_does_not_rewrite_an_existing_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $lifecycle = app(InMemoriamLifecycleService::class);

        $generated = $lifecycle->publish($this->memorial('John Mathew'), $admin);
        $this->assertSame('late.john.mathew', $generated->slug);

        $existing = $this->memorial('K V Mathew', 'k.v.mathew');
        $published = $lifecycle->publish($existing, $admin);
        $this->assertSame('k.v.mathew', $published->slug);
    }

    public function test_memorial_suffix_does_not_apply_to_living_profiles_and_respects_their_slugs(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Ratish Ravindra',
            'display_name' => 'Ratish Ravindra',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $living = app(ProfileUrlService::class)->assignInitialCanonicalSlug($profile, 'distinguished', $admin);
        $this->assertSame('ratish.ravindra', $living->slug);

        $blocking = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'published',
            'full_name' => 'John Mathew',
            'display_name' => 'John Mathew',
            'profession' => '',
            'slug' => 'late.john.mathew',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->assertFalse(app(InMemoriamUrlService::class)->validateSlugCandidate('john.mathew')['ok']);
        $this->assertSame('late.john.mathew', $blocking->fresh()->slug);
        $this->assertSame('ratish.ravindra', $living->fresh()->slug);
        $this->assertFalse(str_starts_with((string) $living->fresh()->slug, 'late.'));
    }

    private function memorial(string $name, ?string $slug = null): InMemoriamProfile
    {
        return InMemoriamProfile::factory()->paid()->create([
            'deceased_full_name' => $name,
            'deceased_display_name' => $name,
            'slug' => $slug,
            'status' => InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
        ]);
    }
}
