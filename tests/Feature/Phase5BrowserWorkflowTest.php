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

class Phase5BrowserWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @test §21-1 Intro page shows workflow for guests without auth */
    public function test_intro_shows_to_guests_without_auth(): void
    {
        $res = $this->get(route('apply'));
        $res->assertOk();
        $res->assertSee('Choose a profile tier', false);
        $res->assertSee(route('login'), false);
    }

    /** @test §21-2 Tier select page loads when authenticated */
    public function test_tier_select_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $res = $this->actingAs($user)->get(route('apply'));
        $res->assertOk();
        $res->assertSee('Emerging Leader', false);
        $res->assertSee('Accomplished Leader', false);
        $res->assertSee('Distinguished Leader', false);
        $res->assertSee(route('apply.intent'), false);
    }

    /** @test §21-3 Each of 3 tiers is selectable and POST creates application via form */
    public function test_all_three_tiers_selectable_via_form(): void
    {
        $prices = ['emerging' => '₹3,000', 'accomplished' => '₹8,000', 'distinguished' => '₹25,000'];
        foreach (['emerging', 'accomplished', 'distinguished'] as $tier) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $create = $this->actingAs($user)->get(route('apply'));
            $create->assertSee($prices[$tier], false);

            $res = $this->actingAs($user)->postJson(route('applications.store'), [
                'package_tier'   => $tier,
                'source_method'  => 'online_interview',
                'full_name'      => 'T-'.$tier.' User',
                'contact_email'  => $tier.'@example.test',
            ]);
            $res->assertCreated();
            $app = Application::query()->latest('id')->first();
            $this->assertNotNull($app);
            $this->assertSame($tier, (string) $app->package_tier);

            $show = $this->actingAs($user)->get(route('applications.show', $app));
            $show->assertOk();
        }
    }

    /** @test §21-4 No eligibility gate: Distinguished creates even for a user with zero achievements */
    public function test_no_eligibility_gate_on_tier_select(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $res = $this->actingAs($user)->postJson(route('applications.store'), [
            'package_tier'   => 'distinguished',
            'source_method'  => 'online_interview',
            'full_name'      => 'No Gate Test',
            'contact_email'  => 'nogate@example.test',
        ]);
        $res->assertCreated();
        $app = Application::query()->latest('id')->first();
        $this->assertSame('distinguished', (string) $app->package_tier);
    }

    /** @test §21-5 Dashboard renders and ownership is enforced (showOwn) */
    public function test_dashboard_renders_and_ownership_enforced(): void
    {
        $u1 = User::factory()->create(['email_verified_at' => now()]);
        $u2 = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($u1)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);

        $ok = $this->actingAs($u1)->get(route('applications.show', $app));
        $ok->assertOk();
        $ok->assertSee('APPLICATION DASHBOARD', false);
        $ok->assertSee(e($app->full_name), false);
        $ok->assertSee(route('online-interview.show', $app), false);

        $this->actingAs($u2)->getJson(route('applications.show', $app))->assertForbidden();
    }

    /** @test §21-6 Tier questions load on the interview page */
    public function test_interview_page_loads_with_tier_questions(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'distinguished', 'source_method' => 'online_interview']);
        $res = $this->actingAs($user)->get(route('online-interview.show', $app));
        $res->assertOk();
        $res->assertSee('Your Journey', false);
        $res->assertSee('data-qid="q1"', false);
        $res->assertSee('Save and continue', false);
    }

    /** @test §21-7 Emerging tier does NOT render Contribution/Experience/Recognition/Person sections */
    public function test_emerging_tier_hides_locked_sections(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        $res = $this->actingAs($user)->get(route('online-interview.show', $app));
        $res->assertOk();
        // Match question field markers only — bare "q9" etc. can appear inside CSRF tokens.
        $res->assertDontSee('data-qid="q5"', false);
        $res->assertDontSee('data-qid="q7"', false);
        $res->assertDontSee('data-qid="q9"', false);
        $res->assertDontSee('data-qid="q11"', false);
        $res->assertSee('data-qid="q1"', false);
    }

    /** @test §21-8 Accomplished tier does NOT render Recognition or Person sections */
    public function test_accomplished_tier_hides_recognition_and_person_sections(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'accomplished', 'source_method' => 'online_interview']);
        $res = $this->actingAs($user)->get(route('online-interview.show', $app));
        $res->assertOk();
        $res->assertSee('data-qid="q5"', false);
        $res->assertSee('data-qid="q7"', false);
        $res->assertDontSee('data-qid="q9"', false);
        $res->assertDontSee('data-qid="q11"', false);
    }

    /** @test §21-9 Distinguished tier renders all unlocked sections */
    public function test_distinguished_tier_renders_all_sections(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'distinguished', 'source_method' => 'online_interview']);
        $res = $this->actingAs($user)->get(route('online-interview.show', $app));
        $res->assertOk();
        foreach (['q1','q3','q6','q7','q10','q12'] as $qid) {
            $res->assertSee('data-qid="'.$qid.'"', false);
        }
    }

    /** @test §21-10 Previously saved answers appear verbatim on resume */
    public function test_resume_displays_previously_saved_answers(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        $payload = [
            'answers' => [
                'q1' => 'Saved emerging intro — resume check.',
            ],
        ];
        $this->actingAs($user)->patchJson(route('online-interview.save', $app), $payload)->assertOk();

        $view = $this->actingAs($user)->get(route('online-interview.show', $app));
        $view->assertOk();
        $view->assertSeeText('Saved emerging intro — resume check.', false);
    }

    /** @test §21-11 Save and Continue stores answers in database */
    public function test_save_and_continue_persists_to_database(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'accomplished', 'source_method' => 'online_interview']);
        $payload = [
            'answers' => [
                'q1' => 'A1 save',
                'q5' => 'C1 save',
            ],
        ];
        $this->actingAs($user)->patchJson(route('online-interview.save', $app), $payload)->assertOk();

        $a = InterviewAnswer::query()->where('application_id', $app->id)->where('question_id', 'q1')->first();
        $c = InterviewAnswer::query()->where('application_id', $app->id)->where('question_id', 'q5')->first();
        $this->assertNotNull($a, 'q1 not saved; check OnlineInterviewService allowed tier ids (accomplished).');
        $this->assertNotNull($c, 'q5 not saved; check OnlineInterviewService allowed tier ids (accomplished).');
        $this->assertSame('A1 save', $a->original_answer);
        $this->assertSame('C1 save', $c->original_answer);
    }

    /** @test §21-12 Malayalam/Manglish text remains byte-for-byte unchanged in saved answers */
    public function test_malayalam_and_manglish_survive_roundtrip_unchanged(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        $malayalam = 'ഞാൻ ജീനിയസ് ആയിട്ടുണ്ട്. എനിക്ക് മനസ്സിലായി — ഗ്രാമത്തിനു പകരമെന്താ?';
        $manglish  = "Njan jeevithathil ninnu oru nalla paadam padichu: kaaryangal okke saadhanamaake!";
        $combined  = $malayalam . "\n\n---\n\n" . $manglish;
        $this->actingAs($user)->patchJson(route('online-interview.save', $app), [
            'answers' => ['q3' => $combined],
        ])->assertOk();

        $row = InterviewAnswer::query()->where('application_id', $app->id)->where('question_id', 'q3')->first();
        $this->assertNotNull($row, 'Malayalam q3 not stored for emerging tier.');
        $this->assertSame($combined, $row->original_answer);
        $this->assertSame(md5($combined), md5($row->original_answer));

        $view = $this->actingAs($user)->get(route('online-interview.show', $app));
        $view->assertSeeText($malayalam, false);
        $view->assertSeeText($manglish, false);
    }

    /** @test §21-13 Dashboard progress numbers update after Save and Continue */
    public function test_progress_updates_on_dashboard_after_save(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        $before = $this->actingAs($user)->get(route('applications.show', $app));
        $before->assertSee('Answered <b>0</b> of', false);

        $emergingIds = OnlineInterviewCatalog::idsForTier('emerging');
        $payload = [];
        foreach ($emergingIds as $qid) {
            $payload['answers'][$qid] = 'Answer for '.$qid.'.';
        }
        $this->actingAs($user)->patchJson(route('online-interview.save', $app), $payload)->assertOk();

        $after = $this->actingAs($user)->get(route('applications.show', $app));
        $after->assertOk();
        $after->assertSee('All required questions complete', false);
        $after->assertSee('Answered <b>'.count($emergingIds).'</b> of ', false);
    }

    /** @test §21-14 Submit without required answers returns 302 to back (422 via JSON) and leaves application unsubmitted */
    public function test_submit_rejected_when_required_incomplete_via_html_form(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        $res = $this->actingAs($user)->postJson(route('online-interview.submit', $app));
        $res->assertStatus(422);
        $app->refresh();
        $this->assertNull($app->online_interview_completed_at);
    }

    /** @test §21-15 Submit succeeds when all required answers are present */
    public function test_submit_succeeds_when_required_complete(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'accomplished', 'source_method' => 'online_interview']);
        $ids = OnlineInterviewCatalog::idsForTier('accomplished');
        $payload = [];
        foreach ($ids as $qid) {
            $payload['answers'][$qid] = 'done for '.$qid;
        }
        $this->actingAs($user)->patchJson(route('online-interview.save', $app), $payload)->assertOk();

        $submit = $this->actingAs($user)->postJson(route('online-interview.submit', $app));
        $submit->assertOk();
        $app->refresh();
        $this->assertNotNull($app->online_interview_completed_at);
    }

    /** @test §21-16 After submit, the Interview page shows read-only state with no editable inputs */
    public function test_readonly_state_after_submit(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        foreach (OnlineInterviewCatalog::idsForTier('emerging') as $qid) {
            $this->actingAs($user)->patchJson(route('online-interview.save', $app), ['answers' => [$qid => 'R']])->assertOk();
        }
        $this->actingAs($user)->postJson(route('online-interview.submit', $app))->assertOk();

        $view = $this->actingAs($user)->get(route('online-interview.show', $app));
        $view->assertOk();
        $view->assertSee('SUBMITTED · READ-ONLY', false);
        $view->assertSee('Editorial will prepare a draft', false);
    }

    /** @test §21-17 Cross-user access on dashboard and interview returns 403 */
    public function test_cross_user_dashboard_and_interview_return_403(): void
    {
        $u1 = User::factory()->create(['email_verified_at' => now()]);
        $u2 = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($u1)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);

        $this->actingAs($u2)->getJson(route('applications.show', $app))->assertForbidden();
        $this->actingAs($u2)->getJson(route('online-interview.show', $app))->assertForbidden();
    }

    /** @test §21-18 Upload security: forbidden extensions/sizes/mime types are rejected via HTML upload route */
    public function test_upload_ui_rejects_forbidden_mime_or_extension(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);

        Storage::fake('private_uploads');
        $tmpFile = tempnam(sys_get_temp_dir(), 'jf_').'.php';
        file_put_contents($tmpFile, (string) file_get_contents(base_path('vendor/composer/installed.php')));
        try {
            $badPhp = new \Illuminate\Http\UploadedFile($tmpFile, 'drop.php', 'application/pdf', UPLOAD_ERR_OK, true);
            $r1 = $this->actingAs($user)->postJson(route('applications.upload.material', $app), [
                'material_type' => 'resume',
                'material'      => $badPhp,
            ]);
            $mime422orForbidden = in_array($r1->getStatusCode(), [403, 422], true);
            $this->assertTrue($mime422orForbidden, 'Expected 403 (forbidden ext) or 422 (mime guard), got '.$r1->getStatusCode());
            $this->assertSame(0, SourceMaterial::query()->where('application_id', $app->id)->count());
        } finally {
            @unlink($tmpFile);
        }

        $badSvg = UploadedFile::fake()->create('sheet.pdf', 12, 'image/svg+xml');
        $r2 = $this->actingAs($user)->postJson(route('applications.upload.material', $app), [
            'material_type' => 'resume',
            'material'      => $badSvg,
        ]);
        $r2->assertStatus(422);

        $big = UploadedFile::fake()->create('too-big.pdf', 22000, 'application/pdf');
        $r3 = $this->actingAs($user)->postJson(route('applications.upload.material', $app), [
            'material_type' => 'resume',
            'material'      => $big,
        ]);
        $r3->assertStatus(422);
        $this->assertSame(0, SourceMaterial::query()->where('application_id', $app->id)->count());
    }

    /** @test §21-19 HTML upload UI route accepts valid PDF and lists it sanitized in upload page */
    public function test_upload_ui_accepts_valid_pdf_and_lists_it(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging', 'source_method' => 'online_interview']);
        Storage::fake('private_uploads');

        $file = UploadedFile::fake()->create('curriculum-vitae.pdf', 400, 'application/pdf');
        $up = $this->actingAs($user)->postJson(route('applications.upload.material', $app), [
            'material_type' => 'resume',
            'material'      => $file,
        ]);
        $up->assertCreated();

        $row = SourceMaterial::query()->where('application_id', $app->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('resume', $row->material_type);
        $this->assertSame('curriculum-vitae.pdf', $row->original_filename);
        $this->assertGreaterThan(0, $row->file_bytes);

        $list = $this->actingAs($user)->get(route('applications.upload.show', $app));
        $list->assertOk();
        $list->assertSee(e('curriculum-vitae.pdf'), false);
        $list->assertDontSee('storage/private_uploads', false);
        $list->assertDontSee(e($row->storage_path ?? ''), false);
    }

    /** @test §21-20 Phase 1-3-4 foundation tables and tier config remain intact after RefreshDatabase */
    public function test_existing_foundation_tables_and_config_intact(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'distinguished', 'source_method' => 'online_interview']);
        $this->assertSame('distinguished', (string) $app->package_tier);

        $catalog = config('online_interview.source_material_types');
        $this->assertIsArray($catalog);
        $this->assertArrayHasKey('resume', $catalog);
        $this->assertArrayHasKey('closing', config('online_interview.sections', []));

        $this->assertSame(18, (int) config('jannayaks.tier_pricing.gst_percent'));
        $this->assertSame(3000, (int) config('jannayaks.tier_pricing.packages.emerging.base_amount'));
        $this->assertSame(8000, (int) config('jannayaks.tier_pricing.packages.accomplished.base_amount'));
        $this->assertSame(25000, (int) config('jannayaks.tier_pricing.packages.distinguished.base_amount'));
        $qids = array_map(static fn(array $q): string => (string) ($q['id'] ?? ''), (array) config('online_interview.questions', []));
        $this->assertContains('closing_other', $qids);
    }
}
