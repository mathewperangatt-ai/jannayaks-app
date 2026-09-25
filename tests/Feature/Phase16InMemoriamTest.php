<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\InMemoriamEditorialContent;
use App\Models\InMemoriamProfile;
use App\Models\MediaItem;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\EditorialGenerationService;
use App\Services\InMemoriamEditorialService;
use App\Services\InMemoriamLifecycleService;
use App\Services\InMemoriamMediaService;
use App\Services\InMemoriamUrlService;
use App\Services\RazorpayPaymentService;
use App\Support\PricingAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase16InMemoriamTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_memorial_and_unauthorised_cannot_manage(): void
    {
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();

        $memorial = InMemoriamProfile::factory()->create([
            'deceased_full_name' => 'Memorial Person',
        ]);

        $this->assertDatabaseHas('in_memoriam_profiles', [
            'id' => $memorial->id,
            'deceased_full_name' => 'Memorial Person',
        ]);

        $this->actingAs($editor)->get('/admin/in-memoriam-profiles')->assertOk();
        $this->actingAs($editor)->get('/admin/in-memoriam-profiles/create')->assertOk();

        $this->actingAs($member)->get('/admin/in-memoriam-profiles')->assertForbidden();
        $this->assertFalse($member->can('create', InMemoriamProfile::class));
        $this->assertFalse($member->can('update', $memorial));
    }

    public function test_no_family_dashboard_or_online_application_or_online_payment_routes(): void
    {
        $memorial = InMemoriamProfile::factory()->create();

        $this->get('/in-memoriam/'.$memorial->id.'/edit')->assertNotFound();
        $this->get('/memorials/apply')->assertNotFound();
        $this->get('/in-memoriam/apply')->assertNotFound();
        $this->get('/in-memoriam/payment')->assertNotFound();

        $this->assertFalse(method_exists(RazorpayPaymentService::class, 'createInMemoriamPaymentLink'));
    }

    public function test_memorial_does_not_enter_living_or_ai_pipelines(): void
    {
        $memorial = InMemoriamProfile::factory()->create();
        $this->assertDatabaseMissing('profiles', ['full_name' => $memorial->deceased_full_name]);

        $admin = User::factory()->admin()->create();
        $application = Application::factory()->create([
            'package_tier' => 'in_memoriam',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(EditorialGenerationService::class)->generateForApplication($application, $admin);
    }

    public function test_human_editorial_english_and_malayalam(): void
    {
        $editor = User::factory()->editor()->create();
        $memorial = InMemoriamProfile::factory()->create();
        $service = app(InMemoriamEditorialService::class);

        $en = $service->upsertHumanContent($memorial, $editor, 'en', [
            'title' => 'English title',
            'body' => 'Family-supplied English body',
            'status' => InMemoriamEditorialContent::STATUS_APPROVED,
        ]);
        $ml = $service->upsertHumanContent($memorial, $editor, 'ml', [
            'title' => 'മലയാളം',
            'body' => 'കുടുംബം നൽകിയ ടെക്സ്റ്റ്',
            'status' => InMemoriamEditorialContent::STATUS_APPROVED,
        ]);

        $this->assertFalse($en->ai_generated);
        $this->assertFalse($ml->ai_generated);
        $this->assertSame('en', $en->language);
        $this->assertSame('ml', $ml->language);
    }

    public function test_photographs_pending_private_approved_public(): void
    {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $support = User::factory()->support()->create();
        $admin = User::factory()->admin()->create();

        $memorial = InMemoriamProfile::factory()->paid()->create([
            'slug' => 'photo-memorial',
            'status' => InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
        ]);

        $media = app(InMemoriamMediaService::class);
        $pending = $media->uploadPhotograph($memorial, UploadedFile::fake()->image('a.jpg', 400, 500), $editor);
        $this->assertSame(MediaItem::REVIEW_PENDING, $pending->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $pending->privacy);

        try {
            $media->approve($pending, $support);
            $this->fail('Support must not approve');
        } catch (ValidationException) {
            // expected
        }

        // The uploader cannot self-approve; a different reviewer approves.
        $approved = $media->approve($pending, $reviewer);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $approved->privacy);

        app(InMemoriamLifecycleService::class)->publish($memorial->fresh(), $admin);
        $memorial->refresh();

        $this->get(route('in-memoriam.photo', ['slug' => $memorial->slug, 'media' => $approved]))->assertOk();
    }

    public function test_landing_and_public_memorial_pages_and_living_url_isolation(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        $this->get(route('in-memoriam.index'))
            ->assertOk()
            ->assertSee('In Memoriam', false)
            ->assertSee('no online application', false);

        $memorial = InMemoriamProfile::factory()->paid()->create([
            'slug' => 'public-memorial-leader',
            'status' => InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
            'commissioner_display_consent' => false,
            'verification_notes' => 'Certificate examined offline; DoD matches.',
        ]);

        app(InMemoriamEditorialService::class)->upsertHumanContent($memorial, $editor, 'en', [
            'title' => 'A remembered life',
            'body' => 'Public memorial body only',
            'status' => InMemoriamEditorialContent::STATUS_APPROVED,
        ]);

        $published = app(InMemoriamLifecycleService::class)->publish($memorial, $admin);
        $years = (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 5);
        $this->assertSame($years, app(InMemoriamLifecycleService::class)->hostingYears());
        $this->assertEquals(
            $published->hosting_starts_on->copy()->addYears($years)->toDateString(),
            $published->hosting_ends_on->toDateString()
        );

        $this->get(route('in-memoriam.show', ['slug' => $published->slug]))
            ->assertOk()
            ->assertSee('A remembered life', false)
            ->assertDontSee('Certificate examined offline')
            ->assertDontSee($published->commissioner_contact_mobile);

        $this->get(route('profiles.public', ['slug' => $published->slug]))->assertNotFound();

        $living = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'published',
            'full_name' => 'Living Leader',
            'display_name' => 'Living Leader',
            'profession' => 'Leader',
            'slug' => 'living-only-slug',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $this->get(route('in-memoriam.show', ['slug' => $living->slug]))->assertNotFound();
    }

    public function test_memorials_not_in_living_gallery_or_search(): void
    {
        $memorial = InMemoriamProfile::factory()->published()->create([
            'slug' => 'not-in-gallery',
            'deceased_display_name' => 'UniqueMemorialSearchTokenXYZ',
            'deceased_full_name' => 'UniqueMemorialSearchTokenXYZ',
        ]);

        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertDontSee('UniqueMemorialSearchTokenXYZ');

        $this->get(route('search.index', ['q' => 'UniqueMemorialSearchTokenXYZ']))
            ->assertOk()
            ->assertDontSee(route('in-memoriam.show', ['slug' => $memorial->slug]), false);
    }

    public function test_expiry_hides_public_access_but_retains_record(): void
    {
        $lifecycle = app(InMemoriamLifecycleService::class);
        $memorial = InMemoriamProfile::factory()->published()->create(['slug' => 'expired-memorial']);

        $this->assertTrue($lifecycle->isPubliclyVisible($memorial));
        $this->get(route('in-memoriam.show', ['slug' => $memorial->slug]))->assertOk();

        $memorial->forceFill([
            'hosting_ends_on' => now($lifecycle->businessTimezone())->subDay()->toDateString(),
        ])->save();

        $this->assertFalse($lifecycle->isPubliclyVisible($memorial->fresh()));
        $this->get(route('in-memoriam.show', ['slug' => $memorial->slug]))->assertNotFound();
        $this->assertDatabaseHas('in_memoriam_profiles', ['id' => $memorial->id]);
    }

    public function test_offline_payment_uses_authoritative_pricing_and_no_auto_publish(): void
    {
        $admin = User::factory()->admin()->create();
        $memorial = InMemoriamProfile::factory()->create();

        $updated = app(InMemoriamLifecycleService::class)->recordOfflinePackagePaid($memorial, $admin);
        $pricing = PricingAmounts::forInMemoriam5yr();

        $this->assertNotNull($updated->commission_paid_at);
        $this->assertNotSame(InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED, $updated->status);
        $this->assertFalse($updated->is_sealed);
        $this->assertDatabaseHas('payments', [
            'in_memoriam_profile_id' => $updated->id,
            'item_type' => 'in_memoriam',
            'gateway' => 'manual',
            'event_type' => 'in_memoriam_package',
            'amount' => PricingAmounts::paiseToDecimalString((int) $pricing['amount_incl_paise']),
        ]);
    }

    public function test_sealed_correction_admin_only_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $memorial = InMemoriamProfile::factory()->published()->create(['slug' => 'sealed-memorial']);

        $this->assertFalse($editor->can('update', $memorial));
        $this->assertFalse($editor->can('exceptionalCorrection', $memorial));
        $this->assertTrue($admin->can('exceptionalCorrection', $memorial));

        try {
            app(InMemoriamEditorialService::class)->upsertHumanContent($memorial, $editor, 'en', [
                'title' => 'Nope',
                'body' => 'Nope',
            ]);
            $this->fail('Sealed memorial must block routine edits');
        } catch (ValidationException) {
            // expected
        }

        try {
            app(InMemoriamLifecycleService::class)->applyExceptionalCorrection(
                $memorial,
                $editor,
                ['bio_headline' => 'Illegal'],
                'typo',
            );
            $this->fail('Non-admin correction must fail');
        } catch (ValidationException) {
            // expected
        }

        $corrected = app(InMemoriamLifecycleService::class)->applyExceptionalCorrection(
            $memorial,
            $admin,
            ['bio_headline' => 'Corrected headline'],
            'Typographical correction.',
        );

        $this->assertSame('Corrected headline', $corrected->bio_headline);
        $this->assertTrue($corrected->is_sealed);
        $this->assertTrue(StaffActionLog::query()->where('action', 'in_memoriam.admin_exceptional_correction')->exists());
    }

    public function test_slug_collision_and_p15_lifecycle_do_not_affect_memorials(): void
    {
        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Living',
            'display_name' => 'Living',
            'profession' => 'Leader',
            'slug' => 'taken-by-living',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->assertFalse(app(InMemoriamUrlService::class)->validateSlugCandidate('taken-by-living')['ok']);

        $memorial = InMemoriamProfile::factory()->published()->create(['slug' => 'untouched-by-p15']);
        $before = $memorial->only(['status', 'is_sealed', 'hosting_ends_on']);

        $profile = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'published',
            'full_name' => 'Member',
            'display_name' => 'Member',
            'profession' => 'Leader',
            'slug' => 'member-p15',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        Membership::query()->create([
            'profile_id' => $profile->id,
            'status' => 'active',
            'tier' => 'accomplished',
            'starts_on' => now()->subYear()->toDateString(),
            'ends_on' => now()->subDays(10)->toDateString(),
            'renewal_due_on' => now()->subDays(10)->toDateString(),
            'retention_until' => now()->addMonths(6)->toDateString(),
            'auto_renew' => false,
        ]);

        $this->artisan('membership:process-lifecycle')->assertSuccessful();

        $memorial->refresh();
        $this->assertSame($before['status'], $memorial->status);
        $this->assertSame((bool) $before['is_sealed'], (bool) $memorial->is_sealed);
    }

    public function test_support_is_read_only(): void
    {
        $support = User::factory()->support()->create();
        $memorial = InMemoriamProfile::factory()->create();

        $this->assertTrue($support->can('viewAny', InMemoriamProfile::class));
        $this->assertFalse($support->can('create', InMemoriamProfile::class));
        $this->assertFalse($support->can('update', $memorial));
        $this->assertFalse($support->can('publish', $memorial));
        $this->actingAs($support)->get('/admin/in-memoriam-profiles')->assertOk();
        $this->actingAs($support)->get('/admin/in-memoriam-profiles/'.$memorial->id.'/edit')->assertForbidden();
    }

    public function test_pending_photo_on_sealed_memorial_can_be_approved_by_editorial_staff(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $support = User::factory()->support()->create();

        $memorial = InMemoriamProfile::factory()->paid()->create([
            'slug' => 'sealed-pending-photo',
            'status' => InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
        ]);

        $mediaService = app(InMemoriamMediaService::class);
        $pending = $mediaService->uploadPhotograph(
            $memorial,
            UploadedFile::fake()->image('sealed-pending.jpg', 400, 500),
            $editor,
        );

        app(InMemoriamLifecycleService::class)->publish($memorial->fresh(), $admin);
        $memorial->refresh();

        $this->assertTrue($memorial->is_sealed);
        $this->assertSame(InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED, $memorial->status);
        $this->assertFalse($editor->can('manageMedia', $memorial));
        $this->assertTrue($editor->can('approveMedia', $memorial));
        $this->assertFalse($support->can('approveMedia', $memorial));

        $photoUrl = route('in-memoriam.photo', ['slug' => $memorial->slug, 'media' => $pending]);
        $this->get($photoUrl)->assertNotFound();

        try {
            $mediaService->approve($pending, $support);
            $this->fail('Support must not approve sealed memorial photographs');
        } catch (ValidationException) {
            // expected
        }

        $pending->refresh();
        $this->assertSame(MediaItem::REVIEW_PENDING, $pending->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $pending->privacy);
        $this->get($photoUrl)->assertNotFound();

        // The uploader cannot self-approve; a different reviewer approves.
        $approved = $mediaService->approve($pending, $reviewer);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $approved->privacy);
        $this->get($photoUrl)->assertOk();
    }
}
