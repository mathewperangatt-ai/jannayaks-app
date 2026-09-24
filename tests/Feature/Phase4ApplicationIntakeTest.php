<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\InterviewAnswer;
use App\Models\SourceMaterial;
use App\Models\User;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase4ApplicationIntakeTest extends TestCase
{
    use RefreshDatabase;

    /* 1. Application creation for authenticated user. */
    public function test_authenticated_user_can_create_application(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $res = $this->actingAs($user)->postJson(route('applications.store'), [
            'package_tier'   => 'accomplished',
            'source_method'  => 'online_interview',
            'full_name'      => 'Alice Example',
            'preferred_slug' => 'alice-example',
            'contact_email'  => 'alice@example.com',
        ]);

        $res->assertCreated()->assertJson(['ok' => true, 'package_tier' => 'accomplished']);
        $this->assertStringContainsString('/payment', (string) $res->json('redirect_to'));
        $this->assertDatabaseHas('applications', [
            'user_id'        => $user->id,
            'package_tier'   => 'accomplished',
            'source_method'  => 'online_interview',
            'full_name'      => 'Alice Example',
        ]);
        $app = Application::query()->latest('id')->first();
        $this->assertNotNull($app->intake_started_at);
        $this->assertSame('Alice Example', $app->preferred_display_name);
    }

    /* 2. Tier persistence */
    public function test_tier_persists_for_emerging_accomplished_distinguished(): void
    {
        foreach (['emerging', 'accomplished', 'distinguished'] as $tier) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $this->actingAs($user)->postJson(route('applications.store'), [
                'package_tier'   => $tier,
                'source_method'  => 'online_interview',
                'full_name'      => $tier.' Person',
                'contact_email'  => $tier.'@example.com',
            ])->assertCreated();
            $this->assertSame($tier, (string) Application::query()->latest('id')->first()->package_tier);
        }
    }

    /* 3. Unrestricted tier selection (no profession gate, no status gate). */
    public function test_tier_selection_is_unrestricted_no_gate(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        // Distinguished with no verification / no achievements provided:
        $res = $this->actingAs($user)->postJson(route('applications.store'), [
            'package_tier'  => 'distinguished',
            'source_method' => 'online_interview',
            'full_name'     => 'No Achievements Provided',
            'contact_email' => 'no.achievements@example.com',
        ]);
        $res->assertCreated();
        $app = Application::query()->latest('id')->first();
        $this->assertSame('distinguished', (string) $app->package_tier);
    }

    /* 4. Answer persistence + original answer not overwritten (5). */
    public function test_answers_persist_and_preserve_original_text(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Bob Original',
        ])->paid()->create();

        $original = 'ഇത് ഒരു മലയാളം ഉത്തരമാണ്. Mixed with English and Manglish words like Ayal Njan koode undaayirunnu.';

        $res = $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $app->id]), [
            'question_id' => 'q1',
            'answer'      => $original,
        ]);
        $res->assertOk()->assertJson(['ok' => true, 'answered' => 1]);

        $row = InterviewAnswer::query()
            ->where('application_id', $app->id)
            ->where('question_id', 'q1')
            ->firstOrFail();

        $this->assertSame($original, $row->original_answer);
        $this->assertNotNull($row->answered_at);
        $this->assertSame((int) $user->id, (int) $row->user_id);
    }

    /* 6. Correct progress math */
    public function test_progress_reports_required_and_total(): void
    {
        $emerging = OnlineInterviewCatalog::questionsForTier('emerging');
        $progressAllBlank = OnlineInterviewCatalog::progress('emerging', []);
        $this->assertSame(0, $progressAllBlank['answered']);
        $this->assertSame(count($emerging), $progressAllBlank['total']);
        $this->assertGreaterThan(0, $progressAllBlank['required_total']);
        $this->assertNotEmpty($progressAllBlank['missing_required']);

        $emergingRequiredIds = array_values(array_filter(array_map(
            static fn (array $q): ?string => !empty($q['required']) ? (string) ($q['id'] ?? '') : null,
            $emerging
        )));
        sort($emergingRequiredIds);
        $missing = $progressAllBlank['missing_required'];
        sort($missing);
        $this->assertSame($emergingRequiredIds, $missing);

        // When answered, missing_required decreases by that required id.
        $progressPartial = OnlineInterviewCatalog::progress('emerging', [$emergingRequiredIds[0] => 'x']);
        $this->assertSame(1, $progressPartial['answered']);
        $this->assertNotContains($emergingRequiredIds[0], $progressPartial['missing_required'], true);
    }

    /* 7. Progressive unlocking per tier */
    public function test_progressive_unlocking_matches_tier(): void
    {
        $emerging = OnlineInterviewCatalog::idsForTier('emerging');
        $accomp   = OnlineInterviewCatalog::idsForTier('accomplished');
        $dist     = OnlineInterviewCatalog::idsForTier('distinguished');

        // Emerging: no contrib, experience, recog, person
        $this->assertFalse(in_array('q5', $emerging, true));
        $this->assertFalse(in_array('q9', $emerging, true));

        // Accomplished adds contrib + experience but not recog/person
        $this->assertTrue(in_array('q5', $accomp, true));
        $this->assertTrue(in_array('q7', $accomp, true));
        $this->assertFalse(in_array('q9', $accomp, true));
        $this->assertFalse(in_array('q11', $accomp, true));

        // Distinguished adds recog + person (all 14 + closing)
        $this->assertTrue(in_array('q9', $dist, true));
        $this->assertTrue(in_array('q11', $dist, true));
        $this->assertCount(15, $dist); // 14 required/optionals + closing_other
    }

    /* 8. Save incomplete interview (return, reopen, read answers) */
    public function test_incomplete_interview_save_and_resume(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Incomplete Saver',
        ])->paid()->create();

        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $app->id]), [
            'question_id' => 'q1',
            'answer'      => 'Saved draft answer q1.',
        ])->assertOk();

        $res = $this->actingAs($user)->getJson(route('online-interview.show', ['application' => $app->id]));
        $res->assertOk()->assertJson([
            'ok'        => true,
            'read_only' => false,
            'progress'  => ['answered' => 1],
        ]);
        $answers = $res->json('answers');
        $this->assertSame('Saved draft answer q1.', (string) ($answers['q1'] ?? ''));
    }

    /* 9. Submit completed required interview */
    public function test_submit_accepted_when_required_complete(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Completed Submission',
        ])->paid()->create();

        // Emerging required: q1, q2, q3, q13, q14 (per OnlineInterviewCatalog questions emerging-only section lock)
        $requiredIds = OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $answers = [];
        foreach ($requiredIds as $id) {
            $answers[$id] = 'Answer for '.$id;
        }

        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $app->id]), [
            'answers' => $answers,
        ])->assertOk();

        $res = $this->actingAs($user)->postJson(route('online-interview.submit', ['application' => $app->id]));
        $res->assertOk()->assertJson(['ok' => true, 'next_step' => 'editorial_processing']);

        $app->refresh();
        $this->assertNotNull($app->online_interview_completed_at);
    }

    /* 10. Reject incomplete required submission */
    public function test_submit_rejected_when_required_incomplete(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Incomplete',
        ])->paid()->create();

        $res = $this->actingAs($user)->postJson(route('online-interview.submit', ['application' => $app->id]));
        $res->assertStatus(422)->assertJson(['ok' => false, 'error' => 'Please complete all required questions before submitting.']);
        $this->assertArrayHasKey('missing_required', $res->json());
        $app->refresh();
        $this->assertNull($app->online_interview_completed_at);
    }

    /* 11. Cross-user application access denied */
    public function test_cross_user_application_access_denied(): void
    {
        $alice = User::factory()->create(['email_verified_at' => now()]);
        $bob   = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $alice->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Only Alice',
        ])->paid()->create();

        $this->actingAs($bob)->getJson(route('applications.show', ['application' => $app->id]))->assertForbidden();
        $this->actingAs($bob)->getJson(route('online-interview.show', ['application' => $app->id]))->assertForbidden();
    }

    /* 12. Cross-user answer modification denied (save endpoint) */
    public function test_cross_user_answer_modification_denied(): void
    {
        $alice = User::factory()->create(['email_verified_at' => now()]);
        $bob   = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $alice->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Alice App',
        ])->paid()->create();

        $this->actingAs($bob)->patchJson(route('online-interview.save', ['application' => $app->id]), [
            'question_id' => 'q1',
            'answer'      => 'Bob writing into Alice interview.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('interview_answers', [
            'application_id' => $app->id,
            'original_answer' => 'Bob writing into Alice interview.',
        ]);
    }

    /* 13. Duplicate submission prevented (idempotent second submit returns already_submitted) */
    public function test_duplicate_submit_is_idempotent_and_prevents_state_change(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'Dup Submit',
        ])->paid()->create();
        $ids = OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $answers = [];
        foreach ($ids as $id) { $answers[$id] = 'a'; }
        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $app->id]), ['answers' => $answers]);

        $first = $this->actingAs($user)->postJson(route('online-interview.submit', ['application' => $app->id]));
        $first->assertOk();
        $t1 = (string) $first->json('submitted_at');

        $second = $this->actingAs($user)->postJson(route('online-interview.submit', ['application' => $app->id]));
        $second->assertOk()->assertJson(['ok' => true, 'already_submitted' => true]);
        $this->assertSame($t1, (string) $second->json('submitted_at'));
    }

    /* 14. Source material relationship */
    public function test_source_material_linked_to_application_and_user(): void
    {
        Storage::fake('private_uploads');
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'accomplished',
            'source_method' => 'direct_submission',
            'full_name'     => 'Direct Uploader',
        ])->paid()->create();

        $file = UploadedFile::fake()->create('biography.pdf', 64, 'application/pdf');

        $res = $this->actingAs($user)->postJson(route('applications.upload.material', ['application' => $app->id]), [
            'material'      => $file,
            'material_type' => 'biography',
        ]);

        $res->assertCreated()->assertJson(['ok' => true, 'material_type' => 'biography']);
        $mat = SourceMaterial::query()->latest('id')->first();
        $this->assertSame((int) $app->id, (int) $mat->application_id);
        $this->assertSame((int) $user->id, (int) $mat->user_id);
        $this->assertSame('biography', (string) $mat->material_type);
        $this->assertNotNull($mat->uploaded_at);
        $this->assertNull($mat->purged_at);
        Storage::disk('private_uploads')->assertExists($mat->storage_path);
    }

    /* 14b. Configurable durable disk: uploads land on the configured disk and the row records it. */
    public function test_source_material_can_be_stored_on_alternate_configured_disk(): void
    {
        Storage::fake('source_materials');
        config(['online_interview.uploads.disk' => 'source_materials']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'accomplished',
            'source_method' => 'direct_submission',
            'full_name'     => 'Alternate Disk Uploader',
        ])->paid()->create();

        $file = UploadedFile::fake()->create('notes.pdf', 32, 'application/pdf');

        $res = $this->actingAs($user)->postJson(route('applications.upload.material', ['application' => $app->id]), [
            'material'      => $file,
            'material_type' => 'personal_notes',
        ]);

        $res->assertCreated()->assertJson(['ok' => true]);
        $mat = SourceMaterial::query()->latest('id')->first();
        $this->assertSame('source_materials', (string) $mat->storage_disk);
        Storage::disk('source_materials')->assertExists($mat->storage_path);
    }

    /* 14c. Default disk remains private_uploads when SOURCE_MATERIALS_DISK is not set. */
    public function test_source_material_defaults_to_private_uploads_disk(): void
    {
        Storage::fake('private_uploads');
        config(['online_interview.uploads.disk' => env('SOURCE_MATERIALS_DISK', 'private_uploads')]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'direct_submission',
            'full_name'     => 'Default Disk Uploader',
        ])->paid()->create();

        $file = UploadedFile::fake()->create('story.txt', 8, 'text/plain');

        $res = $this->actingAs($user)->postJson(route('applications.upload.material', ['application' => $app->id]), [
            'material'      => $file,
            'material_type' => 'biography',
        ]);

        $res->assertCreated();
        $mat = SourceMaterial::query()->latest('id')->first();
        $this->assertSame('private_uploads', (string) $mat->storage_disk);
        Storage::disk('private_uploads')->assertExists($mat->storage_path);
    }

    /* 15. Application / Profile distinction (no automatic Profile publication yet) */
    public function test_application_submission_does_not_create_or_publish_profile(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory([
            'user_id'       => $user->id,
            'package_tier'  => 'emerging',
            'source_method' => 'online_interview',
            'full_name'     => 'No Profile Yet',
        ])->paid()->create();
        $ids = OnlineInterviewCatalog::progress('emerging', [])['missing_required'];
        $answers = []; foreach ($ids as $id) { $answers[$id] = 'answer'; }
        $this->actingAs($user)->patchJson(route('online-interview.save', ['application' => $app->id]), ['answers' => $answers]);
        $this->actingAs($user)->postJson(route('online-interview.submit', ['application' => $app->id]))->assertOk();
        $app->refresh();
        $this->assertNull($app->profile_id);
        $this->assertSame(0, \App\Models\Profile::query()->count());
    }

    /* 16. Existing Phase 1–3 tests remain green (smoke check): integrity TCs still exist and reference tables still exist. */
    public function test_existing_phase_1_3_tables_still_intact(): void
    {
        // These tables from Phase 1–3 foundation must still be present after Phase 4 migrations:
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('users'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('profiles'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('applications'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('geo_states'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('geo_wards'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('payments'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('memberships'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('consent_records'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('editorial_contents'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('in_memoriam_profiles'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('slug_redirects'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('interview_answers'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('source_materials'));
    }
}
