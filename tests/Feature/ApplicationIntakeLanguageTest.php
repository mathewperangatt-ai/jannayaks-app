<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Support\OnlineInterviewCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationIntakeLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    /**
     * @return array<string, string>
     */
    private function intake(array $overrides = []): array
    {
        return array_merge([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Arun Kumar',
            'contact_email' => 'arun.kumar@example.test',
            'contact_mobile' => '9876543210',
        ], $overrides);
    }

    public function test_intake_form_renders_in_malayalam_by_default(): void
    {
        $this->actingAs($this->verifiedUser())->get(route('apply'))
            ->assertOk()
            ->assertSee('ഉള്ളടക്കം സമർപ്പിക്കുന്ന രീതി')
            ->assertSee('ഓൺലൈൻ അഭിമുഖം (ശുപാർശ ചെയ്യുന്നത്)')
            ->assertSee('നേരിട്ടുള്ള സമർപ്പണം')
            ->assertSee('പൂർണ്ണനാമം (എഡിറ്റോറിയൽ പ്രൊഫൈലിൽ പ്രദർശിപ്പിക്കേണ്ടതുപോലെ)')
            ->assertSee('ബന്ധപ്പെടാനുള്ള ഇമെയിൽ')
            ->assertSee('ബന്ധപ്പെടാനുള്ള മൊബൈൽ നമ്പർ (ഓപ്ഷണൽ)')
            ->assertSee('പിന്നോട്ട്')
            ->assertSee('പണമടയ്ക്കുന്നതിലേക്ക് തുടരുക →')
            ->assertSee(json_encode('ബന്ധപ്പെടാനുള്ള വിവരങ്ങൾ രേഖപ്പെടുത്തി. എഡിറ്റോറിയൽ പരിശോധനയ്ക്കും തയ്യാറാക്കിയ പ്രൊഫൈൽ സ്ഥിരീകരിക്കുന്നതിനുമായി Jannayaks നിങ്ങളുമായി ബന്ധപ്പെടും.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false)
            ->assertSee(route('apply', ['lang' => 'en']), false)
            ->assertDontSee('Intake source')
            ->assertDontSee('Continue to payment →');
    }

    public function test_intake_form_renders_in_english_when_selected(): void
    {
        $this->actingAs($this->verifiedUser())->get(route('apply', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Intake source')
            ->assertSee('Online Interview (recommended)')
            ->assertSee('Direct submission')
            ->assertSee('Continue to payment →')
            ->assertSee(json_encode('Contact information captured. You can add more or update them later in the application dashboard.', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false)
            ->assertDontSee('ഉള്ളടക്കം സമർപ്പിക്കുന്ന രീതി')
            ->assertSee(route('apply', ['lang' => 'ml']), false);
    }

    public function test_unknown_language_falls_back_to_malayalam(): void
    {
        $this->actingAs($this->verifiedUser())->get(route('apply', ['lang' => 'fr']))
            ->assertOk()
            ->assertSee('ഉള്ളടക്കം സമർപ്പിക്കുന്ന രീതി');
    }

    public function test_selected_language_persists_from_intake_to_payment_page(): void
    {
        $user = $this->verifiedUser();
        $this->actingAs($user)->get(route('apply', ['lang' => 'en']))->assertOk();

        $this->actingAs($user)->post(route('apply.intent'), $this->intake())
            ->assertRedirect();

        $application = Application::query()->where('user_id', $user->id)->sole();

        $this->actingAs($user)->get(route('applications.payment', $application))
            ->assertOk()
            ->assertSee('Payment Status')
            ->assertDontSee('പേയ്‌മെൻ്റ് സ്റ്റാറ്റസ്');
    }

    public function test_online_interview_intake_creates_one_application_and_continues_to_payment(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)->post(route('apply.intent'), $this->intake());

        $application = Application::query()->where('user_id', $user->id)->sole();
        $response->assertRedirect(route('applications.payment', $application));
        $this->assertSame('online_interview', $application->source_method);
    }

    public function test_direct_submission_intake_creates_one_application_and_continues_to_payment(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)->post(route('apply.intent'), $this->intake([
            'source_method' => 'direct_submission',
        ]));

        $application = Application::query()->where('user_id', $user->id)->sole();
        $response->assertRedirect(route('applications.payment', $application));
        $this->assertSame('direct_submission', $application->source_method);
        $this->assertSame(Application::STATUS_PAYMENT_PENDING, $application->status);
    }

    public function test_entered_intake_data_is_stored_on_the_application(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)->post(route('apply.intent'), $this->intake([
            'package_tier' => 'accomplished',
            'source_method' => 'direct_submission',
        ]));

        $application = Application::query()->where('user_id', $user->id)->sole();
        $this->assertSame('accomplished', $application->package_tier);
        $this->assertSame('Arun Kumar', $application->full_name);
        $this->assertSame('arun.kumar@example.test', $application->preferred_contact_email);
        $this->assertNotNull($application->preferred_contact_mobile);

        $this->actingAs($user)->get(route('applications.payment', $application))
            ->assertOk()
            ->assertSee('Arun Kumar')
            ->assertSee('നേരിട്ടുള്ള സമർപ്പണം');
    }

    public function test_missing_tier_shows_an_error_keeps_input_and_creates_no_application(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)
            ->from(route('apply'))
            ->followingRedirects()
            ->post(route('apply.intent'), $this->intake([
                'package_tier' => '',
                'source_method' => 'direct_submission',
            ]))
            ->assertOk()
            ->assertSee('ദയവായി ഒരു അംഗത്വ ശ്രേണി തിരഞ്ഞെടുക്കുക.')
            ->assertSee('value="Arun Kumar"', false);

        $this->assertMatchesRegularExpression('/value="direct_submission"\s+checked/', $response->getContent());
        $this->assertSame(0, Application::query()->where('user_id', $user->id)->count());
    }

    public function test_required_field_messages_use_the_supplied_malayalam(): void
    {
        $this->actingAs($this->verifiedUser())
            ->from(route('apply'))
            ->post(route('apply.intent'), $this->intake([
                'package_tier' => '',
                'source_method' => '',
                'full_name' => '',
                'contact_email' => '',
                'contact_mobile' => '',
            ]))
            ->assertSessionHasErrors([
                'package_tier' => 'ദയവായി ഒരു അംഗത്വ ശ്രേണി തിരഞ്ഞെടുക്കുക.',
                'source_method' => 'നിങ്ങളുടെ ഉള്ളടക്കം എങ്ങനെ സമർപ്പിക്കണമെന്ന് ദയവായി തിരഞ്ഞെടുക്കുക.',
                'full_name' => 'പൂർണ്ണനാമം നൽകേണ്ടതാണ്.',
                'contact_email' => 'ബന്ധപ്പെടാനുള്ള മൊബൈൽ നമ്പർ നൽകിയിട്ടില്ലെങ്കിൽ ഇമെയിൽ നൽകേണ്ടതാണ്.',
                'contact_mobile' => 'ഇമെയിൽ നൽകിയിട്ടില്ലെങ്കിൽ ബന്ധപ്പെടാനുള്ള മൊബൈൽ നമ്പർ നൽകേണ്ടതാണ്.',
            ]);
    }

    public function test_missing_tier_and_source_messages_in_english(): void
    {
        $user = $this->verifiedUser();
        $this->actingAs($user)->get(route('apply', ['lang' => 'en']))->assertOk();

        $this->actingAs($user)
            ->from(route('apply'))
            ->post(route('apply.intent'), $this->intake([
                'package_tier' => '',
                'source_method' => '',
            ]))
            ->assertSessionHasErrors([
                'package_tier' => 'Please select a membership tier.',
                'source_method' => 'Please select how you would like to submit your content.',
            ]);
    }

    public function test_online_interview_stays_locked_until_payment_and_cannot_be_forged(): void
    {
        $user = $this->verifiedUser();

        $this->actingAs($user)->post(route('apply.intent'), $this->intake([
            'payment_status' => Application::PAYMENT_STATUS_PAID,
        ]));

        $application = Application::query()->where('user_id', $user->id)->sole();
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);

        $this->actingAs($user)->get(route('online-interview.show', $application))
            ->assertRedirect(route('applications.payment', $application));
    }

    public function test_online_interview_catalogue_still_has_the_approved_22_questions(): void
    {
        $expected = array_map(fn (int $n): string => 'q'.$n, range(1, 22));

        foreach (['emerging', 'accomplished', 'distinguished'] as $tier) {
            $this->assertSame($expected, OnlineInterviewCatalog::idsForTier($tier));
        }
    }

    public function test_payment_page_renders_in_malayalam_by_default(): void
    {
        $user = $this->verifiedUser();
        $application = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);

        $this->actingAs($user)->get(route('applications.payment', $application))
            ->assertOk()
            ->assertSee('പേയ്‌മെൻ്റ് & ബില്ലിംഗ് — അപ്ലിക്കേഷൻ')
            ->assertSee('ചെലവ് വിഭജനം')
            ->assertSee('അടിസ്ഥാന നിരക്ക് + ജിഎസ്ടി (പണമടയ്ക്കുമ്പോൾ ചേർക്കും)')
            ->assertSee('% ജിഎസ്ടി പണമടയ്ക്കുമ്പോൾ ചേർക്കും')
            ->assertSee('പേയ്‌മെൻ്റ് സ്റ്റാറ്റസ്')
            ->assertSee('ഇപ്പോൾ പേയ്‌മെൻ്റ് ചെയ്യുക')
            ->assertSee('ഉള്ളടക്കം സമർപ്പിക്കുന്ന രീതി')
            ->assertSee('ഓൺലൈൻ അഭിമുഖം')
            ->assertSee(route('applications.payment', ['application' => $application, 'lang' => 'en']), false)
            ->assertDontSee('Pay Now')
            ->assertDontSee('Payment Status');
    }

    public function test_payment_page_renders_in_english_when_selected(): void
    {
        $user = $this->verifiedUser();
        $application = Application::factory()->for($user)->create(['package_tier' => 'emerging']);

        $this->actingAs($user)->get(route('applications.payment', ['application' => $application, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Payment Status')
            ->assertSee('Pay Now')
            ->assertDontSee('ഇപ്പോൾ പേയ്‌മെൻ്റ് ചെയ്യുക');
    }
}
