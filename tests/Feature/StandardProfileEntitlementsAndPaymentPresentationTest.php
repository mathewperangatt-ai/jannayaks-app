<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Services\ExternalVideoLinkService;
use App\Services\ProfileMediaService;
use App\Services\RazorpayPaymentService;
use App\Support\PricingAmounts;
use App\Support\ProfileEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StandardProfileEntitlementsAndPaymentPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('https://www.jannayaks.in');
        URL::forceScheme('https');
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_standard_profile_allows_three_photos_and_rejects_a_fourth(): void
    {
        [$member, $application, $profile] = $this->makeStandardApplication();
        $service = app(ProfileMediaService::class);

        $this->assertSame(3, ProfileEntitlements::photoSlots($application));
        $this->assertFalse(ProfileEntitlements::allowsVideoLink($application));
        $this->assertSame(3, $service->photoSlotLimitForApplication($application));

        for ($i = 0; $i < 3; $i++) {
            $service->uploadProfilePhoto($profile, UploadedFile::fake()->image("s{$i}.jpg", 400, 500), $member);
        }

        try {
            $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('s3.jpg', 400, 500), $member);
            $this->fail('Standard Profile must reject a fourth photograph.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('photo', $e->errors());
        }

        $this->assertSame(3, $service->profilePhotoCount($profile));

        $this->actingAs($member)
            ->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('3', false)
            ->assertDontSee('External video link', false);
    }

    public function test_standard_profile_rejects_video_link_submission(): void
    {
        [$member, , $profile] = $this->makeStandardApplication();

        try {
            app(ExternalVideoLinkService::class)->submit(
                $profile,
                $member,
                'https://www.youtube.com/watch?v=standardBlocked',
            );
            $this->fail('Standard Profile must not allow video links.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('url', $e->errors());
        }
    }

    public function test_historical_distinguished_keeps_five_photos_and_video(): void
    {
        [$member, $application, $profile] = $this->makeHistoricalApplication('distinguished');
        $service = app(ProfileMediaService::class);

        $this->assertNull($application->pricing_generation);
        $this->assertSame(5, ProfileEntitlements::photoSlots($application));
        $this->assertTrue(ProfileEntitlements::allowsVideoLink($application));

        for ($i = 0; $i < 5; $i++) {
            $service->uploadProfilePhoto($profile, UploadedFile::fake()->image("h{$i}.jpg", 400, 500), $member);
        }
        $this->assertSame(5, $service->profilePhotoCount($profile));

        $link = app(ExternalVideoLinkService::class)->submit(
            $profile,
            $member,
            'https://www.youtube.com/watch?v=historicalOk',
        );
        $this->assertNotNull($link->id);
    }

    public function test_payment_page_shows_base_plus_cgst_sgst_not_tax_inclusive_base_line(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
            'full_name' => 'GST Payer',
        ]);

        Config::set('jannayaks.tier_pricing.billing.state', 'Kerala');
        $quote = PricingAmounts::forApplication($app, 'Kerala');
        $this->assertSame(400000, $quote['base_paise']);
        $this->assertSame(36000, $quote['cgst_paise']);
        $this->assertSame(36000, $quote['sgst_paise']);
        $this->assertSame(72000, $quote['gst_paise']);
        $this->assertSame(472000, $quote['amount_incl_paise']);
        $this->assertSame('intra_state', $quote['gst_classification']);
        $this->assertSame(
            $quote['amount_incl_paise'],
            $quote['base_paise'] + $quote['cgst_paise'] + $quote['sgst_paise'],
        );

        // Payment page has no collected place of supply yet: exclusive base + unclassified GST total.
        $html = $this->actingAs($user)
            ->get(route('applications.payment', ['application' => $app, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Package Price Breakdown', false)
            ->assertSee('₹4,000.00', false)
            ->assertSee('GST (added)', false)
            ->assertSee('₹720.00', false)
            ->assertSee('Total (incl. GST)', false)
            ->assertSee('₹4,720.00', false)
            ->assertDontSee('CGST (added)', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '₹4,720.00'));
        $this->assertGreaterThanOrEqual(1, substr_count($html, '₹4,000.00'));
    }

    public function test_new_standard_profile_payment_initiation_uses_standard_amount(): void
    {
        Config::set('services.razorpay.enabled', false);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
        ]);

        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);

        $this->assertSame(472000, $payment->totalPaise());
        $this->assertSame('4000.00', (string) $payment->base_amount);
        // Place of supply is not collected yet — tax is stored unclassified (total GST only).
        $this->assertNull($payment->cgst_amount);
        $this->assertNull($payment->sgst_amount);
        $this->assertNull($payment->igst_amount);
    }

    public function test_historical_unpaid_application_keeps_legacy_amount_and_does_not_replace_matching_link(): void
    {
        Config::set('services.razorpay.enabled', false);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->historical('distinguished')->create([
            'full_name' => 'Legacy Distinguisher',
        ]);

        $first = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $this->assertSame(1062000, $first->totalPaise());
        $this->assertSame('9000.00', (string) $first->base_amount);

        $second = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app->fresh());
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
        $this->assertSame(1062000, $second->totalPaise());
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function makeStandardApplication(): array
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Standard Media '.uniqid(),
            'display_name' => 'Standard Media',
            'profession' => 'Organiser',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        return [$member, $application->fresh(), $profile->fresh()];
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function makeHistoricalApplication(string $tier): array
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Historical Media '.uniqid(),
            'display_name' => 'Historical Media',
            'profession' => 'Organiser',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->historical($tier)->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        return [$member, $application->fresh(), $profile->fresh()];
    }
}
