<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wave 3 follow-up: name-derived personal URL suggestions on the INITIAL
 * application form. Public endpoint, name-only input, boolean availability,
 * reusing ProfileUrlService::suggestPersonalSlugs (validation, reserved
 * names, uniqueness) — no applicant personal data is exposed.
 */
class UrlSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_up_to_eight_name_derived_suggestions(): void
    {
        $response = $this->getJson(route('apply.url-suggestions', ['name' => 'Anil Kumar Menon']));

        $response->assertOk();
        $suggestions = $response->json('suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertLessThanOrEqual(8, count($suggestions));

        foreach ($suggestions as $item) {
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('available', $item);
            $this->assertIsBool($item['available']);
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:[.\-][a-z0-9]+)*$/', $item['slug']);
        }

        // Name-derived: every suggestion shares at least one name token.
        $generator = new \App\Services\PersonalSlugGenerator();
        foreach ($suggestions as $item) {
            $this->assertTrue(
                $generator->isNameDerived($item['slug'], 'Anil Kumar Menon'),
                'Suggestion '.$item['slug'].' is not derived from the supplied name.',
            );
        }
    }

    public function test_taken_slugs_are_marked_unavailable(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $profile = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'published',
            'full_name' => 'Anil Kumar Menon',
            'display_name' => 'Anil Kumar Menon',
            'slug' => 'anil.kumar',
        ]);
        Application::factory()->create([
            'user_id' => $owner->id,
            'profile_id' => $profile->id,
            'status' => Application::STATUS_PUBLISHED,
        ]);

        $response = $this->getJson(route('apply.url-suggestions', ['name' => 'Anil Kumar Menon']));
        $response->assertOk();

        $slugs = collect($response->json('suggestions'))->pluck('available', 'slug');
        $this->assertSame(false, $slugs->get('anil.kumar'), 'A taken slug must be marked unavailable.');
    }

    public function test_invalid_names_are_rejected(): void
    {
        $this->getJson(route('apply.url-suggestions', ['name' => 'A']))->assertStatus(422);
        $this->getJson(route('apply.url-suggestions'))->assertStatus(422);
        $this->getJson(route('apply.url-suggestions', ['name' => str_repeat('x', 256)]))->assertStatus(422);
    }

    public function test_no_applicant_personal_data_is_returned(): void
    {
        $owner = User::factory()->create([
            'email' => 'secret.owner@example.com',
            'name' => 'Secret Owner Person',
            'role' => User::ROLE_MEMBER,
        ]);
        $profile = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'published',
            'full_name' => 'Anil Kumar',
            'display_name' => 'Anil Kumar',
            'slug' => 'anil.kumar',
        ]);
        Application::factory()->create([
            'user_id' => $owner->id,
            'profile_id' => $profile->id,
            'status' => Application::STATUS_PUBLISHED,
        ]);

        $response = $this->getJson(route('apply.url-suggestions', ['name' => 'Anil Kumar']));
        $payload = $response->getContent();

        $this->assertStringNotContainsString('secret.owner@example.com', $payload);
        $this->assertStringNotContainsString('Secret Owner', $payload);
    }

    public function test_existing_application_url_page_still_works(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $application = Application::factory()->create([
            'user_id' => $member->id,
            'status' => Application::STATUS_INTERVIEW_IN_PROGRESS,
            'full_name' => 'Url Page Person',
        ]);

        $this->actingAs($member)
            ->get(route('applications.profile-url', ['application' => $application->id]))
            ->assertOk();
    }
}
