<?php

namespace Tests\Feature;

use App\Exceptions\PaymentLinkStateUncertainException;
use App\Models\Application;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\InvoiceService;
use App\Services\ProfileUrlService;
use App\Services\RazorpayPaymentService;
use App\Services\RazorpayWebhookVerifier;
use App\Support\EditorialSystemPrompts;
use App\Support\TierLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PricingBranchCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_gateway_cancellation_precedes_a_replacement_link(): void
    {
        $app = $this->application();
        $stale = $this->staleAttempt($app);

        $this->enableGateway();
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_ends_with($url, '/cancel')) {
                return Http::response(['id' => 'plink_old', 'status' => 'cancelled'], 200);
            }
            if (str_contains($url, 'plink_old')) {
                return Http::response(['id' => 'plink_old', 'status' => 'created', 'amount' => 354000, 'currency' => 'INR'], 200);
            }

            return Http::response(['id' => 'plink_new', 'short_url' => 'https://rzp.io/i/current', 'status' => 'created'], 200);
        });

        $created = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);

        $stale->refresh();
        $this->assertSame(Payment::STATUS_CANCELLED, $stale->status);
        $this->assertNull($stale->razorpay_link_url);
        $this->assertNotSame($stale->id, $created->id);
        $this->assertSame('plink_new', $created->razorpay_link_id);
        $this->assertSame(472000, $created->totalPaise());
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->activeAttempts()->count());
    }

    public function test_cancellation_timeout_does_not_open_a_second_link(): void
    {
        $app = $this->application();
        $stale = $this->staleAttempt($app);

        $this->enableGateway();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        try {
            app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
            $this->fail('An uncertain link must not be replaced.');
        } catch (PaymentLinkStateUncertainException) {
        }

        $stale->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $stale->status);
        $this->assertSame('plink_old', $stale->razorpay_link_id);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
    }

    public function test_cancellation_failure_does_not_mark_the_attempt_cancelled(): void
    {
        $app = $this->application();
        $stale = $this->staleAttempt($app);

        $this->enableGateway();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/cancel')) {
                return Http::response(['error' => ['description' => 'unavailable']], 500);
            }

            return Http::response(['id' => 'plink_old', 'status' => 'created', 'amount' => 354000, 'currency' => 'INR'], 200);
        });

        try {
            app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
            $this->fail('A failed cancellation must not be followed by a new link.');
        } catch (PaymentLinkStateUncertainException) {
        }

        $stale->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $stale->status);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
    }

    public function test_gateway_paid_link_is_recorded_against_the_existing_attempt(): void
    {
        $app = $this->application();
        $stale = $this->staleAttempt($app);

        $this->enableGateway();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/payments/pay_old_captured')) {
                return Http::response([
                    'id' => 'pay_old_captured',
                    'amount' => 354000,
                    'currency' => 'INR',
                    'status' => 'captured',
                ], 200);
            }

            return Http::response([
                'id' => 'plink_old',
                'status' => 'paid',
                'amount' => 354000,
                'currency' => 'INR',
                'payments' => ['pay_old_captured'],
            ], 200);
        });

        $recorded = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);

        $this->assertSame($stale->id, $recorded->id);
        $this->assertTrue($recorded->isPaidOrBetter());
        $this->assertSame('pay_old_captured', $recorded->gateway_payment_id);
        $this->assertSame('INR', $recorded->currency);
        $this->assertSame(354000, $recorded->totalPaise());
        $this->assertSame(RazorpayPaymentService::CODE_SETTLED_ON_WITHDRAWN_LINK, $recorded->error_code);
        $this->assertNotNull($recorded->invoice_number);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/payment_links'));
    }

    public function test_paid_link_with_a_mismatched_amount_is_not_settled(): void
    {
        $app = $this->application();
        $stale = $this->staleAttempt($app);

        $this->enableGateway();
        Http::fake(fn () => Http::response([
            'id' => 'plink_old',
            'status' => 'paid',
            'amount' => 100,
            'currency' => 'INR',
            'payments' => ['pay_wrong_amount'],
        ], 200));

        try {
            app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
            $this->fail('An amount mismatch must not settle or be replaced.');
        } catch (PaymentLinkStateUncertainException) {
        }

        $stale->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $stale->status);
        $this->assertFalse($app->fresh()->isPaymentSettled());
    }

    public function test_late_matching_webhook_settles_a_withdrawn_link_once(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $withdrawn = $this->staleAttempt($app);
        $withdrawn->markFailedExpiredOrCancelled(Payment::STATUS_CANCELLED, 'SUPERSEDED', 'Withdrawn after confirmation.');

        $this->postWebhook($withdrawn, 354000, 'INR', 'plink_old', 'pay_late_ok', 'evt_late_ok')->assertOk();
        $this->postWebhook($withdrawn->fresh(), 354000, 'INR', 'plink_old', 'pay_late_ok', 'evt_late_ok')->assertOk();

        $withdrawn->refresh();
        $this->assertTrue($withdrawn->isPaidOrBetter());
        $this->assertSame('pay_late_ok', $withdrawn->gateway_payment_id);
        $this->assertSame(RazorpayPaymentService::CODE_SETTLED_ON_WITHDRAWN_LINK, $withdrawn->error_code);
        $this->assertSame(1, Payment::query()->whereNotNull('invoice_number')->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
    }

    public function test_amount_and_link_mismatches_do_not_settle_a_withdrawn_link(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $withdrawn = $this->staleAttempt($app);
        $withdrawn->markFailedExpiredOrCancelled(Payment::STATUS_CANCELLED, 'SUPERSEDED', 'Withdrawn after confirmation.');

        $this->postWebhook($withdrawn, 100, 'INR', 'plink_old', 'pay_bad_amount', 'evt_bad_amount')->assertStatus(409);
        $this->postWebhook($withdrawn->fresh(), 354000, 'INR', 'plink_other', 'pay_bad_link', 'evt_bad_link')->assertStatus(409);
        $this->postWebhook($withdrawn->fresh(), 354000, 'INR', 'plink_old', 'not_a_payment', 'evt_bad_id')->assertStatus(409);

        $withdrawn->refresh();
        $this->assertSame(Payment::STATUS_CANCELLED, $withdrawn->status);
        $this->assertFalse($app->fresh()->isPaymentSettled());
        $this->assertNull($withdrawn->invoice_number);
    }

    public function test_additional_capture_after_a_newer_payment_is_flagged_without_a_second_invoice(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $current = $this->staleAttempt($app);
        $current->forceFill([
            'amount' => '4720.00',
            'razorpay_link_id' => 'plink_current',
            'razorpay_link_url' => null,
        ])->save();
        $current->markPaidOrCaptured(
            targetStatus: Payment::STATUS_CAPTURED,
            gatewayPaymentId: 'pay_current_ok',
            gatewayEventId: 'evt_current_ok',
            eventType: Payment::EVENT_PAYMENT_CAPTURED,
            paidAt: now(),
            capturedAt: now(),
        );
        app(ApplicationPaymentStateService::class)->afterSettled($current);
        app(InvoiceService::class)->assignSettlementDocuments($current->fresh());
        $settledStatus = $app->fresh()->status;
        $invoice = $current->fresh()->invoice_number;

        $old = Payment::query()->create([
            'application_id' => $app->id,
            'transaction_reference' => 'JNK-PAY-OLDER',
            'gateway' => Payment::GATEWAY_RAZORPAY,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '3540.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_CANCELLED,
            'razorpay_link_id' => 'plink_older',
            'error_code' => 'SUPERSEDED',
        ]);

        $this->postWebhook($old, 354000, 'INR', 'plink_older', 'pay_older_extra', 'evt_older_extra')->assertOk();
        $this->postWebhook($old->fresh(), 354000, 'INR', 'plink_older', 'pay_older_extra', 'evt_older_again')->assertOk();

        $old->refresh();
        $current->refresh();
        $this->assertTrue($old->isPaidOrBetter());
        $this->assertSame('pay_older_extra', $old->gateway_payment_id);
        $this->assertSame(RazorpayPaymentService::CODE_DUPLICATE_CAPTURE, $old->error_code);
        $this->assertNull($old->invoice_number);
        $this->assertNull($old->tax_invoice_number);
        $this->assertSame($invoice, $current->invoice_number);
        $this->assertSame($settledStatus, $app->fresh()->status);
        $this->assertSame(1, Payment::query()->whereNotNull('invoice_number')->count());
    }

    public function test_pricing_generation_marks_only_new_standard_profiles(): void
    {
        $historical = Application::factory()->create(['package_tier' => 'distinguished']);
        $recognised = Application::factory()->create(['package_tier' => 'emerging']);
        $memorial = Application::factory()->create(['package_tier' => 'in_memoriam']);
        $membership = Application::factory()->create(['package_tier' => 'membership']);

        $this->assertNull($historical->pricing_generation);
        $this->assertNull($recognised->pricing_generation);
        $this->assertNull($memorial->pricing_generation);
        $this->assertNull($membership->pricing_generation);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->postJson(route('applications.store'), [
            'source_method' => 'online_interview',
            'full_name' => 'New Standard',
            'contact_email' => 'new-standard@example.com',
        ])->assertCreated();

        $created = Application::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('distinguished', $created->package_tier);
        $this->assertSame(Application::PRICING_GENERATION_STANDARD, $created->pricing_generation);
        $this->assertSame(0, Payment::query()->where('application_id', $historical->id)->count());
    }

    public function test_staff_labels_and_editorial_instructions_distinguish_generations(): void
    {
        $standard = new Application([
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
        ]);
        $this->assertSame('Standard Profile (distinguished)', TierLabels::staffLabel($standard));
        $this->assertSame('Recognised — historical', TierLabels::staffLabel(new Application(['package_tier' => 'emerging'])));
        $this->assertSame('Acclaimed — historical', TierLabels::staffLabel(new Application(['package_tier' => 'accomplished'])));
        $this->assertSame('Distinguished — historical', TierLabels::staffLabel(new Application(['package_tier' => 'distinguished'])));
        $this->assertSame(TierLabels::label('in_memoriam'), TierLabels::staffLabel(new Application(['package_tier' => 'in_memoriam'])));

        $prompt = EditorialSystemPrompts::englishMaster();
        $this->assertStringContainsString('When pricing_generation is "standard_profile"', $prompt);
        $this->assertStringContainsString('Recognised: a concise but complete editorial portrait', $prompt);
        $this->assertStringContainsString('Acclaimed: a substantially developed public-life feature', $prompt);
        $this->assertStringContainsString('Distinguished: a deeply developed long-form profile', $prompt);
        $this->assertStringNotContainsString('More expensive means more editorial depth', $prompt);
    }

    public function test_handle_wording_intake_validation_and_url_safeguards(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Choose a name-based address', false)
            ->assertSee('arun.kumar12', false)
            ->assertDontSee('jj_nemom', false);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->postJson(route('applications.store'), [
            'source_method' => 'online_interview',
            'full_name' => 'Arun Kumar',
            'preferred_slug' => 'arun_kumar',
            'contact_email' => 'arun@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('preferred_slug');

        $urls = app(ProfileUrlService::class);
        $this->assertFalse($urls->validatePersonalSlugCandidate('arun_kumar', null, 'Arun Kumar')['ok']);
        $this->assertFalse($urls->validatePersonalSlugCandidate('jj_nemom', null, 'Arun Kumar')['ok']);
        $this->assertTrue($urls->validatePersonalSlugCandidate('arun.kumar12', null, 'Arun Kumar')['ok']);

        $owner = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Historical Member',
            'display_name' => 'Historical Member',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->create([
            'user_id' => $owner->id,
            'profile_id' => $profile->id,
            'package_tier' => 'emerging',
            'full_name' => 'Historical Member',
        ]);

        $this->actingAs($owner)
            ->get(route('applications.profile-url', $application))
            ->assertOk()
            ->assertSee('This profile has a six-character system address. A personal name-based address is not available for this application.', false);
    }

    public function test_stale_payment_page_hides_the_obsolete_link_and_shows_the_current_amount(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'full_name' => 'Stale Payer',
        ]);
        $this->staleAttempt($app);

        $this->actingAs($user)
            ->get(route('applications.payment', ['application' => $app, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('₹4,720.00', false)
            ->assertSee('An earlier payment link must not be paid.', false)
            ->assertDontSee('https://rzp.io/i/obsolete', false);

        $this->actingAs($user)
            ->getJson(route('applications.payment', $app))
            ->assertOk()
            ->assertJsonPath('payment.quote_is_stale', true)
            ->assertJsonPath('payment.active_payment.razorpay_link_url', null)
            ->assertJsonPath('payment.stale_link_notice', 'An earlier payment link must not be paid. The current amount is shown above.');
    }

    private function application(): Application
    {
        return Application::factory()->create([
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'full_name' => 'Correction Member',
        ]);
    }

    private function staleAttempt(Application $application): Payment
    {
        Config::set('services.razorpay.enabled', false);

        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($application);
        $payment->forceFill([
            'amount' => '3540.00',
            'razorpay_link_id' => 'plink_old',
            'razorpay_link_url' => 'https://rzp.io/i/obsolete',
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        return $payment->fresh();
    }

    private function enableGateway(): void
    {
        Config::set('services.razorpay.enabled', true);
        Config::set('services.razorpay.mode', 'test');
        Config::set('services.razorpay.key_id', 'rzp_test_corrections');
        Config::set('services.razorpay.key_secret', 'secret_corrections');
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
    }

    private function postWebhook(
        Payment $payment,
        int $amountPaise,
        string $currency,
        string $linkId,
        string $gatewayPaymentId,
        string $eventId,
    ) {
        $payload = json_encode([
            'event' => 'payment.captured',
            'event_id' => $eventId,
            'created_at' => time(),
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $gatewayPaymentId,
                        'amount' => $amountPaise,
                        'currency' => $currency,
                        'notes' => [
                            'payment_id' => (string) $payment->id,
                            'application_id' => (string) $payment->application_id,
                        ],
                    ],
                ],
                'payment_link' => [
                    'entity' => [
                        'id' => $linkId,
                        'amount' => $amountPaise,
                        'currency' => $currency,
                        'reference_id' => $payment->transaction_reference,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, (string) config('services.razorpay.webhook_secret'));

        return $this->call(
            'POST',
            route('payments.razorpay.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', RazorpayWebhookVerifier::SIGNATURE_HEADER)) => $signature,
            ],
            $payload,
        );
    }
}
