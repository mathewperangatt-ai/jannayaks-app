<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\EditorialMalayalamConsent;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\MalayalamConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class MalayalamConsentPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_malayalam_can_be_approved_consented_and_attached_without_replacing_english(): void
    {
        [$admin, $editor, $member, $application, $english, $malayalam, $approvedAt] = $this->publishedEnglishWithDraftMalayalam();
        $memberships = Membership::query()->where('profile_id', $application->profile_id)->count();
        $publishedAt = $application->profile->published_at;

        Livewire::actingAs($editor)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('approveMalayalamForConsent')
            ->callAction('approveMalayalamForConsent');

        $malayalam->refresh();
        $application->refresh();
        $this->assertSame(EditorialContent::STATUS_APPROVED, $malayalam->status);
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertSame($approvedAt->format('Y-m-d H:i:s'), $application->customer_approved_at?->format('Y-m-d H:i:s'));
        $this->assertNull($application->published_malayalam_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
        $this->assertTrue(
            StaffActionLog::query()->where('action', 'editorial.malayalam_approved_for_consent')->where('subject_id', $malayalam->id)->exists()
        );

        $this->get('/leela.nair')->assertOk()->assertDontSee('aria-label="Language"', false)->assertDontSee('Approved Malayalam biography.', false);

        $this->actingAs($member)->get(route('applications.malayalam-consent.show', $application))
            ->assertOk()
            ->assertSee('Approved Malayalam biography.', false);

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('attachConsentedMalayalam');

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('applications.malayalam-consent.store', $application), [
            'malayalam_editorial_content_id' => $malayalam->id,
        ])->assertForbidden();

        $this->actingAs($member)->post(route('applications.malayalam-consent.store', $application), [
            'malayalam_editorial_content_id' => $malayalam->id,
        ])->assertRedirect();

        $this->actingAs($member)->get(route('applications.malayalam-consent.show', $application))
            ->assertOk()
            ->assertSee('Your consent for this Malayalam text is recorded.', false)
            ->assertDontSee('I approve this Malayalam text', false);

        $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Your consent is recorded.', false)
            ->assertDontSee('Review and approve the Malayalam version', false);

        $consent = EditorialMalayalamConsent::query()->where('application_id', $application->id)->whereNull('invalidated_at')->firstOrFail();
        $this->assertSame($member->id, (int) $consent->consented_by_user_id);
        $this->assertSame($malayalam->id, (int) $consent->malayalam_editorial_content_id);
        $this->assertSame(3, (int) $consent->version_number);
        $this->assertSame(EditorialMalayalamConsent::fingerprint($malayalam->fresh()), $consent->content_sha256);
        $this->assertNull($application->fresh()->published_malayalam_editorial_content_id);

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('attachConsentedMalayalam');

        try {
            app(MalayalamConsentService::class)->attachToPublishedProfile($application->fresh(), $editor);
            $this->fail('An editor must not attach Malayalam.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Admin', $e->getMessage());
        }

        $attached = app(MalayalamConsentService::class)->attachToPublishedProfile($application->fresh(), $admin);
        $this->assertSame($malayalam->id, $attached->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $attached->published_english_editorial_content_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $attached->status);
        $this->assertSame($application->payment_status, $attached->payment_status);
        $this->assertSame($approvedAt->format('Y-m-d H:i:s'), $attached->customer_approved_at?->format('Y-m-d H:i:s'));
        $this->assertSame($memberships, Membership::query()->where('profile_id', $application->profile_id)->count());
        $this->assertSame($publishedAt?->format('Y-m-d H:i:s'), $application->profile->fresh()->published_at?->format('Y-m-d H:i:s'));
        $this->assertNull(EditorialCustomerApproval::query()->where('application_id', $application->id)->value('invalidated_at'));
        $this->assertTrue(StaffActionLog::query()->where('action', 'editorial.malayalam_attached')->where('subject_id', $application->id)->exists());

        $this->get('/leela.nair')
            ->assertOk()
            ->assertSee('Approved English biography.', false)
            ->assertSee('aria-label="Language"', false)
            ->assertDontSee('ജീവചരിത്രം', false)
            ->assertDontSee('>Biography<', false)
            ->assertSeeInOrder(['Show appreciation', 'Contact']);
        $this->get('/leela.nair?lang=ml')
            ->assertOk()
            ->assertSee('Approved Malayalam biography.', false);
    }

    public function test_attachment_requires_consent_and_rejects_a_stale_version(): void
    {
        [$admin, , $member, $application, $english, $malayalam, $approvedAt] = $this->publishedEnglishWithDraftMalayalam();
        $service = app(MalayalamConsentService::class);

        try {
            $service->attachToPublishedProfile($application, $admin);
            $this->fail('Malayalam must not be attached before customer consent.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('consent', strtolower($e->getMessage()));
        }

        $service->approveForCustomerConsent($application, $malayalam, $admin);
        $service->recordCustomerConsent($application->fresh(), $member, $malayalam->id);

        $malayalam->forceFill(['body' => 'Changed Malayalam biography.'])->save();
        app(CustomerEditorialWorkflowService::class)->invalidateApprovalForEditorialContent($malayalam->fresh(), $admin);

        $application->refresh();
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertSame($approvedAt->format('Y-m-d H:i:s'), $application->customer_approved_at?->format('Y-m-d H:i:s'));
        $this->assertSame(Application::STATUS_PUBLISHED, $application->status);
        $this->assertNotNull(EditorialMalayalamConsent::query()->where('application_id', $application->id)->whereNotNull('invalidated_at')->first());

        try {
            $service->attachToPublishedProfile($application->fresh(), $admin);
            $this->fail('A stale Malayalam consent must not attach a different text.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('consent', strtolower($e->getMessage()));
        }

        $this->assertNull($application->fresh()->published_malayalam_editorial_content_id);
        $this->get('/leela.nair')
            ->assertOk()
            ->assertSee('Approved English biography.', false)
            ->assertDontSee('aria-label="Language"', false);
        $this->get('/leela.nair?lang=ml')
            ->assertOk()
            ->assertDontSee('Changed Malayalam biography.', false);
    }

    public function test_an_attached_malayalam_version_is_withdrawn_when_its_consent_becomes_stale(): void
    {
        [$admin, , $member, $application, $english, $malayalam] = $this->publishedEnglishWithDraftMalayalam();
        $service = app(MalayalamConsentService::class);
        $service->approveForCustomerConsent($application, $malayalam, $admin);
        $service->recordCustomerConsent($application->fresh(), $member, $malayalam->id);
        $service->attachToPublishedProfile($application->fresh(), $admin);

        $malayalam->forceFill(['body' => 'Changed Malayalam biography.'])->save();
        app(CustomerEditorialWorkflowService::class)->invalidateApprovalForEditorialContent($malayalam->fresh(), $admin);

        $application->refresh();
        $this->assertNull($application->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
        $this->assertTrue(
            StaffActionLog::query()->where('action', 'editorial.malayalam_binding_cleared')->where('subject_id', $application->id)->exists()
        );
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->status);

        $application->forceFill(['published_malayalam_editorial_content_id' => $malayalam->id])->save();
        $this->get('/leela.nair')
            ->assertOk()
            ->assertSee('Approved English biography.', false)
            ->assertDontSee('aria-label="Language"', false);
        $this->get('/leela.nair?lang=ml')
            ->assertOk()
            ->assertSee('Approved English biography.', false)
            ->assertDontSee('Changed Malayalam biography.', false);
    }

    public function test_publication_binding_requires_consent_outside_demonstration_profiles(): void
    {
        [, , $member, $application, $english, $malayalam] = $this->publishedEnglishWithDraftMalayalam();
        $service = app(MalayalamConsentService::class);
        $malayalam->forceFill(['status' => EditorialContent::STATUS_APPROVED])->save();
        $member->forceFill(['email' => 'ordinary.staff@jannayaks.internal'])->save();

        $application->forceFill(['source_method' => 'online_interview'])->save();
        $this->assertFalse($service->allowsPublicationBinding($application->fresh(), $malayalam->fresh()));
        $this->assertSame($english->id, $application->fresh()->published_english_editorial_content_id);

        $application->forceFill(['source_method' => 'admin_test_demo'])->save();
        $this->assertTrue($service->allowsPublicationBinding($application->fresh(), $malayalam->fresh()));

        $application->forceFill(['source_method' => 'online_interview'])->save();
        $service->recordCustomerConsent($application->fresh(), $member, $malayalam->id);
        $this->assertTrue($service->allowsPublicationBinding($application->fresh(), $malayalam->fresh()));

        $malayalam->forceFill(['body' => 'Changed Malayalam biography.'])->save();
        $this->assertFalse($service->allowsPublicationBinding($application->fresh(), $malayalam->fresh()));
        $this->assertSame($english->id, $application->fresh()->published_english_editorial_content_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_consent_migration_adds_one_active_consent_index_without_rewriting_profiles(): void
    {
        $profilesBefore = Profile::query()->count();
        $this->assertTrue(Schema::hasTable('editorial_malayalam_consents'));

        $index = DB::selectOne("SELECT indexdef FROM pg_indexes WHERE indexname = 'editorial_malayalam_consents_one_active_per_application'");
        $this->assertNotNull($index);
        $definition = strtolower((string) $index->indexdef);
        $this->assertStringContainsString('unique', $definition);
        $this->assertStringContainsString('invalidated_at is null', $definition);
        $this->assertSame($profilesBefore, Profile::query()->count());
    }

    public function test_malayalam_attention_queues_do_not_duplicate_an_application(): void
    {
        [, $editor, $member, $application, , $malayalam] = $this->publishedEnglishWithDraftMalayalam();
        $service = app(MalayalamConsentService::class);

        $this->assertSame([$application->id], $service->attentionApplicationIds()['awaiting_staff']);
        $this->assertSame([], $service->attentionApplicationIds()['awaiting_consent']);

        $service->approveForCustomerConsent($application, $malayalam, $editor);
        $afterApproval = $service->attentionApplicationIds();
        $this->assertSame([$application->id], $afterApproval['awaiting_consent']);
        $this->assertSame([], $afterApproval['awaiting_staff']);
        $this->assertSame([], $afterApproval['consent_recorded']);

        $service->recordCustomerConsent($application->fresh(), $member, $malayalam->id);
        $afterConsent = $service->attentionApplicationIds();
        $this->assertSame([$application->id], $afterConsent['consent_recorded']);
        $this->assertSame([], $afterConsent['awaiting_consent']);
        $this->assertSame([], $afterConsent['stale_consent']);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Application, 4: EditorialContent, 5: EditorialContent, 6: Carbon}
     */
    private function publishedEnglishWithDraftMalayalam(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Leela Nair',
            'display_name' => 'Leela Nair',
            'profession' => 'Teacher',
            'slug' => 'leela.nair',
            'published_at' => now()->subDay(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 3,
            'title' => 'Leela Nair',
            'summary' => 'Approved English summary.',
            'body' => 'Approved English biography.',
            'source_material' => '',
            'ai_generated' => false,
        ]);
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 3,
            'title' => 'ലീല നായർ',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'body' => 'Approved Malayalam biography.',
            'source_material' => '',
            'source_editorial_content_id' => $english->id,
            'ai_generated' => false,
        ]);
        $approvedAt = now()->subHour()->startOfSecond();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Leela Nair',
            'preferred_display_name' => 'Leela Nair',
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_PUBLISHED,
            'customer_approved_at' => $approvedAt,
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'preview_english_editorial_content_id' => $english->id,
            'preview_malayalam_editorial_content_id' => $malayalam->id,
            'published_english_editorial_content_id' => $english->id,
            'published_malayalam_editorial_content_id' => null,
        ]);
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();
        EditorialCustomerApproval::query()->create([
            'application_id' => $application->id,
            'profile_id' => $profile->id,
            'approved_by_user_id' => $member->id,
            'english_editorial_content_id' => $english->id,
            'malayalam_editorial_content_id' => $malayalam->id,
            'approved_at' => $approvedAt,
        ]);

        return [$admin, $editor, $member, $application->fresh(), $english->fresh(), $malayalam->fresh(), $approvedAt];
    }
}
