<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\InterviewAnswer;
use App\Models\User;
use App\Services\Ai\FakeEditorialAiClient;
use App\Services\EditorialGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Acceptance tests mandated by the Master Editorial + API Specification §49.
 * Uses the fake AI provider so the SYSTEM'S rules (prompt contract, payload
 * discipline, flag persistence, workflow gating) are what is asserted.
 */
class EditorialSpecificationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private FakeEditorialAiClient $fakeAi;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('jannayaks.ai.provider', 'fake');
        Config::set('jannayaks.ai.malayalam_pipeline_enabled', true);
        $this->fakeAi = new FakeEditorialAiClient;
        $this->app->instance(\App\Contracts\EditorialAiClient::class, $this->fakeAi);
    }

    // TEST 1 — Recognised depth: concise but complete, human, factual, not a résumé.
    public function test_1_recognised_depth_is_concise_but_complete(): void
    {
        [, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Retired electrical supervisor coordinating a neighbourhood forum in Kollam.',
            'q3' => 'Began with a flooded road; documented the problem until drainage work happened.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Recognised: a concise but complete editorial portrait', $system);
        $this->assertStringContainsString('Do not turn the profile into a résumé', $system);
    }

    // TEST 2 — Acclaimed depth: meaningfully deeper story, not merely more words.
    public function test_2_acclaimed_depth_is_substantially_developed(): void
    {
        [, , $run] = $this->generateForTier('accomplished', [
            'q1' => 'Teacher who built an adult literacy initiative.',
            'q3' => 'Started volunteering at an evening tuition programme.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Acclaimed: a substantially developed public-life feature', $system);
        $this->assertStringContainsString('more editorial depth, NOT more words', $system);
    }

    // TEST 3 — Distinguished depth: deeper interpretation and selective detail, not a fact dump.
    public function test_3_distinguished_depth_is_long_form_not_a_fact_dump(): void
    {
        [, , $run] = $this->generateForTier('distinguished', [
            'q1' => 'Public administrator whose career ran from municipal council to state cabinet.',
            'q3' => 'A drinking-water problem in several wards brought him into public life.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Distinguished: a deeply developed long-form profile', $system);
        $this->assertStringContainsString('never a dense sequential fact dump', $system);
    }

    // TEST 4 — Incomplete source material: missing information remains missing; no invention.
    public function test_4_incomplete_source_keeps_missing_information_missing(): void
    {
        [, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Community volunteer in Kozhikode.',
            // Every other question deliberately unanswered.
        ]);

        $this->assertTrue($run->isSuccessful());
        $user = $this->fakeAi->requests[0]['user'];
        $this->assertStringContainsString('Community volunteer in Kozhikode.', $user);
        $this->assertStringNotContainsString('"question_id":"q2"', str_replace(' ', '', $user));
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('If information is missing, it remains missing', $system);
    }

    // TEST 5 — Contradictory dates: SOURCE_CONFLICT / UNCERTAIN_DATE flags and human review.
    public function test_5_contradictory_dates_produce_source_conflict_flag(): void
    {
        $this->fakeAi->englishFlags = ['SOURCE_CONFLICT', 'UNCERTAIN_DATE'];

        [$editorial, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Entered cooperative movement.',
            'q14' => 'President of the cooperative since 1998.',
            'q22' => 'Joined the cooperative in 2001.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $this->assertContains('SOURCE_CONFLICT', $editorial->review_flags);
        $this->assertContains('UNCERTAIN_DATE', $editorial->review_flags);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $editorial->status);
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('do NOT silently reconcile', $system);
    }

    // TEST 6 — Unsupported achievement: CLAIM_REVIEW or exclusion until verified.
    public function test_6_unsupported_achievement_produces_claim_review(): void
    {
        $this->fakeAi->englishFlags = ['CLAIM_REVIEW'];

        [$editorial, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Small-trading businessman active in the local traders association.',
            'q13' => 'Received the state award for entrepreneurship.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $this->assertContains('CLAIM_REVIEW', $editorial->review_flags);
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Use ONLY facts present in the provided source data', $system);
    }

    // TEST 7 — Political source: facts remain, no persuasion or campaign advocacy.
    public function test_7_political_material_stays_factual_and_neutral(): void
    {
        [, , $run] = $this->generateForTier('accomplished', [
            'q1' => 'Municipal councillor and later MLA.',
            'q7' => 'Joined a political party after two terms as an independent councillor.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $user = $this->fakeAi->requests[0]['user'];
        $this->assertStringContainsString('Joined a political party', $user); // political facts are not stripped
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('no vote appeals', $system);
        $this->assertStringContainsString('Never infer political beliefs', $system);
    }

    // TEST 8 — Identity information: include only when supplied and relevant; never infer identity.
    public function test_8_identity_only_when_supplied_never_inferred(): void
    {
        [, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Community organiser.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Never infer religion, caste, community, social status or economic status', $system);
        $this->assertStringContainsString('Religion may appear only as factual biographical context', $system);
    }

    // TEST 9 — Family member sensitive information: THIRD_PARTY_PRIVACY_REVIEW or omission.
    public function test_9_family_privacy_produces_third_party_flag(): void
    {
        $this->fakeAi->englishFlags = ['THIRD_PARTY_PRIVACY_REVIEW'];
        $this->fakeAi->malayalamFlags = ['SENSITIVE_PERSONAL_CONTENT'];

        [$english, $malayalam, $run] = $this->generateForTier('emerging', [
            'q1' => 'Youth development organiser.',
            'q17' => 'My spouse is undergoing medical treatment.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $this->assertContains('THIRD_PARTY_PRIVACY_REVIEW', $english->review_flags);
        $this->assertContains('SENSITIVE_PERSONAL_CONTENT', $malayalam->review_flags);
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Protect third parties', $system);
    }

    // TEST 10 — Domicile distinct from birthplace and work location.
    public function test_10_domicile_distinguished_from_birthplace_and_work(): void
    {
        [, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Cultural organiser.',
            'q2' => 'Grew up in Kozhikode.',
            'q3' => 'Currently live in Thrissur; most of my cultural work happens in Palakkad.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $user = $this->fakeAi->requests[0]['user'];
        $this->assertStringContainsString('Currently live in Thrissur', $user);
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Current residence is NOT the same as birthplace', $system);
        $this->assertStringContainsString('Never invent or infer a residence', $system);
    }

    // TEST 11 — English master produced first; Malayalam is an editorial adaptation.
    public function test_11_english_master_first_then_malayalam_adaptation(): void
    {
        [, $malayalam, $run] = $this->generateForTier('emerging', [
            'q1' => 'Literacy initiative founder.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $this->assertCount(2, $this->fakeAi->requests);
        $this->assertSame('english', $this->fakeAi->requests[0]['purpose']);
        $this->assertSame('malayalam', $this->fakeAi->requests[1]['purpose']);
        $this->assertStringContainsString('english_master', $this->fakeAi->requests[1]['user']);
        $system = $this->fakeAi->requests[1]['system'];
        $this->assertStringContainsString('editorial adaptation, not a literal machine translation', $system);
        $this->assertNotNull($malayalam->source_editorial_content_id);
    }

    // TEST 12 — Manglish source understood as source material.
    public function test_12_manglish_source_is_accepted_material(): void
    {
        [, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Nattil engane work cheyyunnavaranennu parayam — Kollathe oru forum coordinate cheyyunnu.',
            'q3' => 'Oru flooded roadinte problem aanu thudangi.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $user = $this->fakeAi->requests[0]['user'];
        $this->assertStringContainsString('Kollathe oru forum coordinate cheyyunnu', $user);
    }

    // TEST 13 — In Memoriam generation through the living-profile API is rejected.
    public function test_13_in_memoriam_generation_is_rejected(): void
    {
        [$editor, $application] = $this->makeEditorialReadyApplication(['q1' => 'Teacher.'], 'emerging');
        $application->forceFill(['package_tier' => 'in_memoriam'])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('In Memoriam applications are excluded');

        app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);
    }

    // TEST 14 — Fictional achievement not in source: blocked or flagged.
    public function test_14_fictional_achievement_is_flagged_for_review(): void
    {
        $this->fakeAi->englishFlags = ['STRONG_CLAIM'];

        [$editorial, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Runs a small bookshop.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $this->assertContains('STRONG_CLAIM', $editorial->review_flags);
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('INVENTION IS PROHIBITED', $system);
    }

    // TEST 15 — Prohibited Malayalam word never used; neutral wording required.
    public function test_15_prohibited_malayalam_word_is_banned(): void
    {
        [, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Community volunteer with deep family roots in the area.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $system = $this->fakeAi->requests[1]['system'];
        $this->assertStringContainsString('NEVER use the Malayalam word "തറവാട്"', $system);
        $this->assertStringContainsString('neutral family-background wording', $system);
    }

    // TEST 16 — Benchmark philosophy: continuous prose, human review, no auto-publication.
    public function test_16_generated_output_enters_human_review_without_publication(): void
    {
        [$english, , $run] = $this->generateForTier('emerging', [
            'q1' => 'Coastal neighbourhood forum coordinator.',
            'q3' => 'A flooded road started it.',
        ]);

        $this->assertTrue($run->isSuccessful());
        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status);
        $this->assertTrue($english->ai_generated);
        $this->assertSame(
            Application::STATUS_IN_EDITORIAL_REVIEW,
            Application::query()->findOrFail($run->application_id)->status,
        );
        $this->assertNull($english->profile->published_at ?? null);
        $system = $this->fakeAi->requests[0]['system'];
        $this->assertStringContainsString('Do NOT use questionnaire-derived section headings', $system);
        $this->assertStringContainsString('Early Life / Education / Career / Achievements / Family / Future Plans', $system);
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{\App\Models\EditorialContent, \App\Models\EditorialContent, \App\Models\AiEditorialRun}
     */
    private function generateForTier(string $tier, array $answers): array
    {
        [$editor, $application] = $this->makeEditorialReadyApplication($answers, $tier);
        $run = app(EditorialGenerationService::class)->generateForApplication($application, $editor);

        $english = EditorialContent::query()->findOrFail($run->english_editorial_content_id);
        $malayalam = EditorialContent::query()->findOrFail($run->malayalam_editorial_content_id);

        return [$english, $malayalam, $run];
    }

    /**
     * @param  array<string, string>  $answers
     * @return array{User, Application}
     */
    private function makeEditorialReadyApplication(array $answers, string $tier): array
    {
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Acceptance Test Person',
            'preferred_display_name' => 'Acceptance Test Person',
            'package_tier' => $tier,
            'source_method' => 'online_interview',
        ]);
        $application->forceFill(['status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW])->save();

        foreach ($answers as $questionId => $text) {
            InterviewAnswer::query()->create([
                'application_id' => $application->id,
                'user_id' => $member->id,
                'question_id' => $questionId,
                'original_answer' => $text,
                'answered_at' => now(),
            ]);
        }

        return [$editor, $application->fresh()];
    }
}
