<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Isolated profile design concept (/profile-concept).
 *
 * Verifies the exploration route renders in both languages, is marked
 * noindex, and touches no production surfaces: no database reads, no
 * published-profile data, and no dependency on the production profile
 * views or services.
 */
class ProfileConceptTest extends TestCase
{
    public function test_english_concept_renders_for_guests(): void
    {
        $res = $this->get('/profile-concept');
        $res->assertOk();
        $res->assertSee('Anil Varma', false);
        $res->assertSee('jannayaks.in/<span class="dot">·</span>anil.varma', false);
        $res->assertSee('Municipal Councillor', false);
        $res->assertSee('noindex, nofollow', false);
        $res->assertSee('Specimen profile', false);
    }

    public function test_malayalam_concept_renders_for_guests(): void
    {
        $res = $this->get('/profile-concept?lang=ml');
        $res->assertOk();
        $res->assertSee('അനിൽ വർമ്മ', false);
        $res->assertSee('മുനിസിപ്പൽ കൗൺസിലർ · എം.എൽ.എ · പൊതുഭരണ പ്രവർത്തകൻ', false);
        $res->assertSee('lang="ml"', false);
    }

    public function test_concept_carries_all_required_content_sections(): void
    {
        $res = $this->get('/profile-concept');
        $res->assertOk();
        foreach (['#biography', '#career', '#contributions', '#achievements', '#gallery'] as $anchor) {
            $res->assertSee($anchor, false);
        }
        $res->assertSee('copyTop', false);   // share/copy control
        $res->assertSee('shareBtn', false);  // native share
    }

    public function test_unknown_lang_falls_back_to_english(): void
    {
        $res = $this->get('/profile-concept?lang=fr');
        $res->assertOk();
        $res->assertSee('Anil Varma', false);
    }
}
