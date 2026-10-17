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

    public function test_authorized_gateway_payment_stays_uncertain_until_captured(): void
    {
        $app = $this->application();
        $stale = $this->staleAttempt($app);

        $this->enableGateway();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/payments/pay_old_authorized')) {
                return Http::response([
                    'id' => 'pay_old_authorized',
                    'amount' => 354000,
                    'currency' => 'INR',
                    'status' => 'authorized',
                ], 200);
            }

            return Http::response([
                'id' => 'plink_old',
                'status' => 'paid',
                'amount' => 354000,
                'currency' => 'INR',
                'payments' => ['pay_old_authorized'],
            ], 200);
        });

        try {
            app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
            $this->fail('An authorized payment must not settle or be replaced.');
        } catch (PaymentLinkStateUncertainException) {
        }

        $stale->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $stale->status);
        $this->assertFalse($stale->isPaidOrBetter());
        $this->assertFalse($app->fresh()->isPaymentSettled());
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
    }

    public function test_ordinary_authorization_does_not_settle_until_a_verified_capture(): void
    {
        Config::set('services.razorpay.enabled', false);
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $payment->forceFill([
            'razorpay_link_id' => 'plink_current',
            'razorpay_link_url' => 'https://rzp.io/i/current',
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        $this->postWebhook($payment, 472000, 'INR', 'plink_current', 'pay_current_auth', 'evt_name_only', 'payment.captured', '')
            ->assertStatus(503)
            ->assertJson(['paid' => false, 'capture_confirmed' => false, 'retryable' => true]);

        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_current', 'pay_current_auth', 'evt_current_auth', 'payment.authorized', 'authorized')
            ->assertOk()
            ->assertJson(['authorized' => true, 'paid' => false]);
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_current', 'pay_current_auth', 'evt_current_auth', 'payment.authorized', 'authorized')
            ->assertOk()
            ->assertJson(['ok' => true, 'idempotent' => true]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertSame('pay_current_auth', $payment->gateway_payment_id);
        $this->assertNull($payment->invoice_number);
        $this->assertNull($payment->captured_at);
        $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));

        $this->postWebhook($payment, 472000, 'INR', 'plink_current', 'pay_current_auth', 'evt_current_captured', 'payment.captured', 'captured')
            ->assertOk()
            ->assertJson(['paid' => true]);
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_current', 'pay_current_auth', 'evt_current_captured', 'payment.captured', 'captured')
            ->assertOk()
            ->assertJson(['ok' => true, 'idempotent' => true]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertNotNull($payment->invoice_number);
        $this->assertSame(1, Payment::query()->whereNotNull('invoice_number')->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
    }

    public function test_missing_status_capture_settles_only_when_gateway_confirms_it(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $payment->forceFill([
            'razorpay_link_id' => 'plink_recovered',
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        $this->enableGateway();
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/payments/pay_recovered')) {
                return Http::response([
                    'id' => 'pay_recovered',
                    'amount' => 472000,
                    'currency' => 'INR',
                    'status' => 'captured',
                ], 200);
            }
            if (str_contains($url, '/payment_links/plink_recovered')) {
                return Http::response([
                    'id' => 'plink_recovered',
                    'status' => 'paid',
                    'amount' => 472000,
                    'currency' => 'INR',
                    'payments' => [['id' => 'pay_recovered', 'amount' => 472000, 'status' => 'captured']],
                ], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $this->postWebhook($payment, 472000, 'INR', 'plink_recovered', 'pay_recovered', 'evt_recovered', 'payment.captured', '')
            ->assertOk()
            ->assertJson(['paid' => true]);
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_recovered', 'pay_recovered', 'evt_recovered', 'payment.captured', '')
            ->assertOk()
            ->assertJson(['idempotent' => true]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame('pay_recovered', $payment->gateway_payment_id);
        $this->assertSame(1, Payment::query()->whereNotNull('invoice_number')->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
    }

    public function test_missing_status_capture_stays_unpaid_when_gateway_verification_fails(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $payment->forceFill([
            'razorpay_link_id' => 'plink_unconfirmed',
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        $this->enableGateway();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $this->postWebhook($payment, 472000, 'INR', 'plink_unconfirmed', 'pay_unconfirmed', 'evt_unconfirmed', 'payment.captured', '')
            ->assertStatus(503)
            ->assertJson(['paid' => false, 'capture_confirmed' => false, 'retryable' => true]);
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_unconfirmed', 'pay_unconfirmed', 'evt_unconfirmed', 'payment.captured', '')
            ->assertStatus(503);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertNull($payment->gateway_event_id);
        $this->assertNull($payment->invoice_number);
        $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));

        $this->replaceHttpFake(function ($request) {
            if (str_contains($request->url(), '/payments/pay_inconsistent')) {
                return Http::response([
                    'id' => 'pay_inconsistent',
                    'amount' => 472000,
                    'currency' => 'INR',
                    'status' => 'authorized',
                ], 200);
            }

            return Http::response([
                'id' => 'plink_unconfirmed',
                'status' => 'paid',
                'amount' => 472000,
                'payments' => [['id' => 'pay_inconsistent']],
            ], 200);
        });

        $payment->forceFill(['razorpay_link_id' => 'plink_unconfirmed'])->save();
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_unconfirmed', 'pay_inconsistent', 'evt_inconsistent', 'payment.captured', '')
            ->assertStatus(503)
            ->assertJson(['retryable' => true, 'paid' => false]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertNull($payment->gateway_event_id);
        $this->assertNull($payment->invoice_number);
        $this->assertFalse($app->fresh()->isPaymentSettled());
    }

    public function test_unconfirmed_link_settlement_events_settle_when_gateway_confirms_capture(): void
    {
        Config::set('services.razorpay.enabled', false);
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');

        foreach (['payment.paid' => 'payment_paid', 'payment_link.paid' => 'link_paid', 'order.paid' => 'order_paid'] as $event => $suffix) {
            Config::set('services.razorpay.enabled', false);
            $app = $this->application();
            $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
            $linkId = 'plink_'.$suffix;
            $payId = 'pay_'.$suffix;
            $eventId = 'evt_'.$suffix;
            $payment->forceFill([
                'razorpay_link_id' => $linkId,
                'status' => Payment::STATUS_INITIATED,
            ])->save();

            $this->enableGateway();
            $this->fakeConfirmedCapture($payId, $linkId, 472000);
            $this->postWebhook($payment, 472000, 'INR', $linkId, $payId, $eventId, $event, '')
                ->assertOk()
                ->assertJson(['paid' => true]);

            $this->replaceHttpFake(function () {
                throw new \Illuminate\Http\Client\ConnectionException('gateway down on retry');
            });
            $this->postWebhook($payment->fresh(), 472000, 'INR', $linkId, $payId, $eventId, $event, '')
                ->assertOk()
                ->assertJson(['idempotent' => true]);

            $payment->refresh();
            $this->assertSame(Payment::STATUS_PAID, $payment->status);
            $this->assertSame($payId, $payment->gateway_payment_id);
            $this->assertSame(1, Payment::query()->where('application_id', $app->id)->whereNotNull('invoice_number')->count());
            $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
        }
    }

    public function test_unconfirmed_link_settlement_events_stay_unpaid_when_gateway_verification_fails(): void
    {
        Config::set('services.razorpay.enabled', false);
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');

        foreach (['payment.paid' => 'payment_paid', 'payment_link.paid' => 'link_paid', 'order.paid' => 'order_paid'] as $event => $suffix) {
            Config::set('services.razorpay.enabled', false);
            $app = $this->application();
            $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
            $linkId = 'plink_fail_'.$suffix;
            $payment->forceFill([
                'razorpay_link_id' => $linkId,
                'status' => Payment::STATUS_INITIATED,
            ])->save();

            $this->enableGateway();
            $this->replaceHttpFake(function () {
                throw new \Illuminate\Http\Client\ConnectionException('timed out');
            });
            $this->postWebhook($payment, 472000, 'INR', $linkId, 'pay_fail_'.$suffix, 'evt_fail_'.$suffix, $event, '')
                ->assertStatus(503)
                ->assertJson(['paid' => false, 'capture_confirmed' => false, 'retryable' => true]);
            $this->postWebhook($payment->fresh(), 472000, 'INR', $linkId, 'pay_fail_'.$suffix, 'evt_fail_'.$suffix, $event, '')
                ->assertStatus(503);

            $payment->refresh();
            $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
            $this->assertNull($payment->gateway_event_id);
            $this->assertNull($payment->invoice_number);
            $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));

            $this->replaceHttpFake(function ($request) use ($suffix, $linkId) {
                if (str_contains($request->url(), '/payments/pay_inconsistent_'.$suffix)) {
                    return Http::response([
                        'id' => 'pay_inconsistent_'.$suffix,
                        'amount' => 472000,
                        'currency' => 'INR',
                        'status' => 'authorized',
                    ], 200);
                }

                return Http::response([
                    'id' => $linkId,
                    'status' => 'paid',
                    'amount' => 472000,
                    'currency' => 'INR',
                    'payments' => [['id' => 'pay_inconsistent_'.$suffix]],
                ], 200);
            });

            $this->postWebhook($payment->fresh(), 472000, 'INR', $linkId, 'pay_inconsistent_'.$suffix, 'evt_inconsistent_'.$suffix, $event, '')
                ->assertStatus(503)
                ->assertJson(['retryable' => true, 'paid' => false]);

            $payment->refresh();
            $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
            $this->assertNull($payment->gateway_event_id);
            $this->assertNull($payment->invoice_number);
            $this->assertFalse($app->fresh()->isPaymentSettled());
        }
    }

    public function test_payment_link_paid_without_payment_id_settles_from_the_gateway_link(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $payment->forceFill([
            'razorpay_link_id' => 'plink_resolved',
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        $this->enableGateway();
        $this->fakeConfirmedCapture('pay_resolved', 'plink_resolved', 472000);

        $this->postWebhook($payment, 472000, 'INR', 'plink_resolved', '', 'evt_resolved', 'payment_link.paid', '')
            ->assertOk()
            ->assertJson(['paid' => true]);
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_resolved', '', 'evt_resolved', 'payment_link.paid', '')
            ->assertOk()
            ->assertJson(['idempotent' => true]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('pay_resolved', $payment->gateway_payment_id);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->whereNotNull('invoice_number')->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
    }

    public function test_order_paid_without_a_link_entity_uses_the_stored_link(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $payment->forceFill([
            'razorpay_link_id' => 'plink_order_stored',
            'status' => Payment::STATUS_INITIATED,
        ])->save();

        $this->enableGateway();
        $this->fakeConfirmedCapture('pay_order_stored', 'plink_order_stored', 472000);

        $this->postWebhook($payment, 472000, 'INR', 'plink_order_stored', 'pay_order_stored', 'evt_order_stored', 'order.paid', '', false)
            ->assertOk()
            ->assertJson(['paid' => true]);
        $this->postWebhook($payment->fresh(), 472000, 'INR', 'plink_order_stored', 'pay_order_stored', 'evt_order_stored', 'order.paid', '', false)
            ->assertOk()
            ->assertJson(['idempotent' => true]);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('pay_order_stored', $payment->gateway_payment_id);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->whereNotNull('invoice_number')->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
    }

    public function test_authorized_webhook_does_not_settle_a_withdrawn_link(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_corrections');
        $app = $this->application();
        $withdrawn = $this->staleAttempt($app);
        $withdrawn->markFailedExpiredOrCancelled(Payment::STATUS_CANCELLED, 'SUPERSEDED', 'Withdrawn after confirmation.');

        $this->postWebhook($withdrawn, 354000, 'INR', 'plink_old', 'pay_auth_only', 'evt_auth_only', 'payment.authorized', 'authorized')
            ->assertOk()
            ->assertJson(['authorized' => true, 'paid' => false]);

        $withdrawn->refresh();
        $this->assertSame(Payment::STATUS_CANCELLED, $withdrawn->status);
        $this->assertSame('pay_auth_only', $withdrawn->gateway_payment_id);
        $this->assertNull($withdrawn->invoice_number);
        $this->assertFalse($app->fresh()->isPaymentSettled());

        $this->postWebhook($withdrawn, 354000, 'INR', 'plink_old', 'pay_auth_only', 'evt_auth_captured', 'payment.captured', 'captured')
            ->assertOk()
            ->assertJson(['paid' => true]);
        $this->postWebhook($withdrawn->fresh(), 354000, 'INR', 'plink_old', 'pay_auth_only', 'evt_auth_captured', 'payment.captured', 'captured')
            ->assertOk();
        $this->assertTrue($withdrawn->fresh()->isPaidOrBetter());
        $this->assertSame(1, Payment::query()->whereNotNull('invoice_number')->count());
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
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

    private function replaceHttpFake(\Closure $callback): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($callback);
    }

    private function fakeConfirmedCapture(string $payId, string $linkId, int $amountPaise): void
    {
        $this->replaceHttpFake(function ($request) use ($payId, $linkId, $amountPaise) {
            $url = $request->url();
            if (str_contains($url, '/payments/'.$payId)) {
                return Http::response([
                    'id' => $payId,
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'status' => 'captured',
                ], 200);
            }
            if (str_contains($url, '/payment_links/'.$linkId)) {
                return Http::response([
                    'id' => $linkId,
                    'status' => 'paid',
                    'amount' => $amountPaise,
                    'currency' => 'INR',
                    'payments' => [['id' => $payId, 'amount' => $amountPaise, 'status' => 'captured']],
                ], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });
    }

    private function postWebhook(
        Payment $payment,
        int $amountPaise,
        string $currency,
        string $linkId,
        string $gatewayPaymentId,
        string $eventId,
        string $event = 'payment.captured',
        string $paymentStatus = 'captured',
        bool $includeLink = true,
    ) {
        $payloadBody = [
            'payment' => [
                'entity' => [
                    'id' => $gatewayPaymentId,
                    'status' => $paymentStatus,
                    'amount' => $amountPaise,
                    'currency' => $currency,
                    'notes' => [
                        'payment_id' => (string) $payment->id,
                        'application_id' => (string) $payment->application_id,
                    ],
                ],
            ],
        ];
        if ($includeLink) {
            $payloadBody['payment_link'] = [
                'entity' => [
                    'id' => $linkId,
                    'amount' => $amountPaise,
                    'currency' => $currency,
                    'reference_id' => $payment->transaction_reference,
                ],
            ];
        }

        $payload = json_encode([
            'event' => $event,
            'event_id' => $eventId,
            'created_at' => time(),
            'payload' => $payloadBody,
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
