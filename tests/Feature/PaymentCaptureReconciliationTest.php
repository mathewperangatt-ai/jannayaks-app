<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Services\ApplicationPaymentStateService;
use App\Services\InvoiceService;
use App\Services\RazorpayPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentCaptureReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_command_is_scheduled_every_fifteen_minutes_without_overlapping(): void
    {
        Artisan::call('schedule:list');

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'jannayaks:reconcile-payment-captures'));

        $this->assertSame(1, $events->count());
        $event = $events->first();
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertTrue((bool) $event->withoutOverlapping);
    }

    public function test_a_captured_payment_is_recovered_once_after_webhook_retries_stop(): void
    {
        $payment = $this->openAttempt('plink_recovered', 'pay_recovered');
        $this->enableGateway();
        $this->fakeLink('plink_recovered', 'paid', [
            $this->paymentBody('pay_recovered', 'captured', 472000),
        ]);

        $this->assertSame(RazorpayPaymentService::OUTCOME_SETTLED, $this->reconcile($payment));
        $this->assertSame(RazorpayPaymentService::OUTCOME_SKIPPED, $this->reconcile($payment->fresh()));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame('pay_recovered', $payment->gateway_payment_id);
        $this->assertNull($payment->razorpay_link_url);
        $this->assertSame(1, $this->invoiceCount($payment));
        $this->assertSame('payment_complete_awaiting_interview', $payment->application->fresh()->status);
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($payment->application->fresh()));
        Http::assertNotSent(fn ($request): bool => $request->method() === 'POST');
    }

    public function test_an_authorised_payment_stays_unsettled(): void
    {
        $payment = $this->openAttempt('plink_auth', 'pay_auth');
        $this->enableGateway();
        $this->fakeLink('plink_auth', 'created', [
            $this->paymentBody('pay_auth', 'authorized', 472000),
        ]);

        $this->assertSame(RazorpayPaymentService::OUTCOME_PENDING, $this->reconcile($payment));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertSame('https://rzp.io/i/plink_auth', $payment->razorpay_link_url);
        $this->assertSame(0, $this->invoiceCount($payment));
        $this->assertNull($payment->captured_at);
    }

    public function test_timeout_and_disabled_gateway_do_not_change_the_attempt(): void
    {
        $payment = $this->openAttempt('plink_timeout', 'pay_timeout');
        $this->enableGateway();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        $this->assertSame(RazorpayPaymentService::OUTCOME_PENDING, $this->reconcile($payment));
        $sent = count(Http::recorded());
        $this->assertSame(RazorpayPaymentService::OUTCOME_SKIPPED, $this->reconcile($payment->fresh()));
        $this->assertCount($sent, Http::recorded());

        $payment->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertSame(1, $payment->gateway_check_count);
        $this->assertSame(0, $this->invoiceCount($payment));

        Config::set('services.razorpay.enabled', false);
        $young = $this->openAttempt('plink_disabled', 'pay_disabled');
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('must not be called');
        });
        Artisan::call('jannayaks:reconcile-payment-captures');
        Http::assertNothingSent();
        $this->assertSame(0, $young->fresh()->gateway_check_count);
    }

    public function test_cancelled_and_expired_links_close_only_when_no_unresolved_payment_remains(): void
    {
        $expired = $this->openAttempt('plink_expired_clear', 'pay_expired_failed');
        $cancelled = $this->openAttempt('plink_cancelled_clear', 'pay_cancelled_failed');
        $expiredAuthorized = $this->openAttempt('plink_expired_auth', 'pay_expired_auth');
        $this->enableGateway();

        $this->fakeLink('plink_expired_clear', 'expired', [
            $this->paymentBody('pay_expired_failed', 'failed', 472000),
        ]);
        $this->assertSame(RazorpayPaymentService::OUTCOME_CLOSED, $this->reconcile($expired));
        $expired->refresh();
        $this->assertSame(Payment::STATUS_EXPIRED, $expired->status);
        $this->assertNull($expired->razorpay_link_url);
        $this->assertSame(0, $this->invoiceCount($expired));

        $this->fakeLink('plink_cancelled_clear', 'cancelled', [
            $this->paymentBody('pay_cancelled_failed', 'failed', 472000),
        ]);
        $this->assertSame(RazorpayPaymentService::OUTCOME_CLOSED, $this->reconcile($cancelled));
        $this->assertSame(Payment::STATUS_CANCELLED, $cancelled->fresh()->status);

        $this->fakeLink('plink_expired_auth', 'expired', [
            $this->paymentBody('pay_expired_auth', 'authorized', 472000),
        ]);
        $this->assertSame(RazorpayPaymentService::OUTCOME_PENDING, $this->reconcile($expiredAuthorized));
        $expiredAuthorized->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $expiredAuthorized->status);
        $this->assertNull($expiredAuthorized->razorpay_link_url);
        $this->assertSame(0, $this->invoiceCount($expiredAuthorized));
    }

    public function test_a_captured_payment_on_a_cancelled_link_is_recorded_on_the_original_attempt(): void
    {
        $payment = $this->openAttempt('plink_cancelled_paid', 'pay_cancelled_paid');
        $this->enableGateway();
        $this->fakeLink('plink_cancelled_paid', 'cancelled', [
            $this->paymentBody('pay_cancelled_paid', 'captured', 472000),
        ]);

        $this->assertSame(RazorpayPaymentService::OUTCOME_SETTLED, $this->reconcile($payment));
        $payment->refresh();
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame(1, $this->invoiceCount($payment));
        $this->assertSame('payment_complete_awaiting_interview', $payment->application->fresh()->status);
    }

    public function test_amount_currency_and_link_mismatches_are_not_settled(): void
    {
        $amount = $this->openAttempt('plink_amount', 'pay_amount');
        $currency = $this->openAttempt('plink_currency', 'pay_currency');
        $link = $this->openAttempt('plink_link', 'pay_link');
        $this->enableGateway();

        $this->fakeLink('plink_amount', 'paid', [
            $this->paymentBody('pay_amount', 'captured', 100),
        ]);
        $this->assertSame(RazorpayPaymentService::OUTCOME_MISMATCH, $this->reconcile($amount));

        $this->fakeLink('plink_currency', 'paid', [
            $this->paymentBody('pay_currency', 'captured', 472000, 'USD'),
        ]);
        $this->assertSame(RazorpayPaymentService::OUTCOME_MISMATCH, $this->reconcile($currency));

        $this->replaceHttpFake(function ($request) {
            if (str_contains($request->url(), '/payment_links/plink_link')) {
                return Http::response([
                    'id' => 'plink_other',
                    'status' => 'paid',
                    'amount' => 472000,
                    'currency' => 'INR',
                    'payments' => [['id' => 'pay_link']],
                ], 200);
            }

            return Http::response($this->paymentBody('pay_link', 'captured', 472000), 200);
        });
        $this->assertSame(RazorpayPaymentService::OUTCOME_MISMATCH, $this->reconcile($link));

        foreach ([$amount, $currency, $link] as $payment) {
            $payment->refresh();
            $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
            $this->assertSame(RazorpayPaymentService::CODE_RECONCILIATION_MISMATCH, $payment->error_code);
            $this->assertNull($payment->razorpay_link_url);
            $this->assertSame(0, $this->invoiceCount($payment));
        }
    }

    public function test_repeated_runs_do_not_call_razorpay_again_before_the_backoff_interval(): void
    {
        $payment = $this->openAttempt('plink_repeat', 'pay_repeat');
        $this->enableGateway();
        $this->fakeLink('plink_repeat', 'created', [
            $this->paymentBody('pay_repeat', 'authorized', 472000),
        ]);

        Artisan::call('jannayaks:reconcile-payment-captures');
        $sent = count(Http::recorded());
        $this->assertGreaterThan(0, $sent);
        Artisan::call('jannayaks:reconcile-payment-captures');
        $this->assertCount($sent, Http::recorded());
        $this->assertSame(1, $payment->fresh()->gateway_check_count);

        $this->travel(2)->hours();
        Artisan::call('jannayaks:reconcile-payment-captures');
        $this->assertSame(2, $payment->fresh()->gateway_check_count);
        $this->assertSame(Payment::STATUS_INITIATED, $payment->fresh()->status);
    }

    public function test_a_concurrent_run_does_not_settle_the_same_attempt_twice(): void
    {
        $payment = $this->openAttempt('plink_concurrent', 'pay_concurrent');
        $this->enableGateway();
        Http::fake(function ($request) use ($payment) {
            app(RazorpayPaymentService::class)->reconcileOpenAttempt($payment->fresh());

            return $this->gatewayResponse($request->url(), 'plink_concurrent', 'paid', [
                $this->paymentBody('pay_concurrent', 'captured', 472000),
            ]);
        });

        $this->assertSame(RazorpayPaymentService::OUTCOME_SETTLED, $this->reconcile($payment));
        $this->assertSame(1, $this->invoiceCount($payment->fresh()));
        $this->assertSame(1, Payment::query()->where('application_id', $payment->application_id)->whereNotNull('invoice_number')->count());
    }

    public function test_a_webhook_settlement_during_reconciliation_is_kept(): void
    {
        $payment = $this->openAttempt('plink_race', 'pay_race');
        $this->enableGateway();
        Http::fake(function ($request) use ($payment) {
            $current = $payment->fresh();
            if (! $current->isPaidOrBetter()) {
                $current->markPaidOrCaptured(
                    targetStatus: Payment::STATUS_CAPTURED,
                    gatewayPaymentId: 'pay_race',
                    gatewayEventId: 'evt_webhook_race',
                    eventType: Payment::EVENT_PAYMENT_CAPTURED,
                    paidAt: now(),
                    capturedAt: now(),
                );
                app(InvoiceService::class)->assignSettlementDocuments($current);
                app(ApplicationPaymentStateService::class)->afterSettled($current->fresh());
            }

            return $this->gatewayResponse($request->url(), 'plink_race', 'paid', [
                $this->paymentBody('pay_race', 'captured', 472000),
            ]);
        });

        $this->assertSame(RazorpayPaymentService::OUTCOME_ALREADY_SETTLED, $this->reconcile($payment));
        $payment->refresh();
        $this->assertSame(Payment::STATUS_CAPTURED, $payment->status);
        $this->assertSame('evt_webhook_race', $payment->gateway_event_id);
        $this->assertSame(1, $this->invoiceCount($payment));
        $this->assertSame('payment_complete_awaiting_interview', $payment->application->fresh()->status);
    }

    public function test_a_second_capture_after_a_newer_payment_settled_is_flagged_without_another_invoice(): void
    {
        $first = $this->openAttempt('plink_first', 'pay_first');
        $first->markPaidOrCaptured(
            targetStatus: Payment::STATUS_CAPTURED,
            gatewayPaymentId: 'pay_first',
            eventType: Payment::EVENT_PAYMENT_CAPTURED,
            paidAt: now(),
            capturedAt: now(),
        );
        app(InvoiceService::class)->assignSettlementDocuments($first);
        app(ApplicationPaymentStateService::class)->afterSettled($first->fresh());

        $second = $this->openAttempt('plink_second', 'pay_second', $first->application);
        $this->enableGateway();
        $this->fakeLink('plink_second', 'paid', [
            $this->paymentBody('pay_second', 'captured', 472000),
        ]);

        $this->assertSame(RazorpayPaymentService::OUTCOME_DUPLICATE, $this->reconcile($second));
        $second->refresh();
        $this->assertTrue($second->isPaidOrBetter());
        $this->assertSame(RazorpayPaymentService::CODE_DUPLICATE_CAPTURE, $second->error_code);
        $this->assertNull($second->invoice_number);
        $this->assertSame(1, Payment::query()->where('application_id', $first->application_id)->whereNotNull('invoice_number')->count());
        $this->assertSame('payment_complete_awaiting_interview', $first->application->fresh()->status);
    }

    public function test_retry_limit_flags_an_unresolved_attempt_and_stops_automatic_calls(): void
    {
        Config::set('jannayaks.payment_reconciliation.max_checks', 2);
        $payment = $this->openAttempt('plink_limit', 'pay_limit');
        $this->enableGateway();
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timed out');
        });

        Artisan::call('jannayaks:reconcile-payment-captures');
        $this->travel(2)->hours();
        Artisan::call('jannayaks:reconcile-payment-captures');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_INITIATED, $payment->status);
        $this->assertSame(2, $payment->gateway_check_count);
        $this->assertSame(RazorpayPaymentService::CODE_RECONCILIATION_UNRESOLVED, $payment->error_code);
        $this->assertNotNull($payment->error_message);

        $sent = count(Http::recorded());
        $this->travel(2)->days();
        Artisan::call('jannayaks:reconcile-payment-captures');
        $this->assertCount($sent, Http::recorded());
        $this->assertSame(2, $payment->fresh()->gateway_check_count);
    }

    public function test_a_staff_check_uses_the_same_verification_and_a_short_lease(): void
    {
        $payment = $this->openAttempt('plink_staff', 'pay_staff');
        $payment->forceFill(['created_at' => now()])->save();
        $this->enableGateway();
        $this->fakeLink('plink_staff', 'created', [
            $this->paymentBody('pay_staff', 'authorized', 472000),
        ]);

        $this->assertSame(RazorpayPaymentService::OUTCOME_SKIPPED, $this->reconcile($payment));
        $this->assertSame(
            RazorpayPaymentService::OUTCOME_PENDING,
            app(RazorpayPaymentService::class)->reconcileOpenAttempt($payment->fresh(), true),
        );
        $this->assertSame(
            RazorpayPaymentService::OUTCOME_SKIPPED,
            app(RazorpayPaymentService::class)->reconcileOpenAttempt($payment->fresh(), true),
        );
        $this->assertSame(Payment::STATUS_INITIATED, $payment->fresh()->status);
        $this->assertSame(0, $this->invoiceCount($payment));
    }

    public function test_each_run_processes_at_most_twenty_attempts(): void
    {
        $ids = [];
        for ($i = 0; $i < 21; $i++) {
            $ids[] = $this->openAttempt('plink_batch_'.$i, 'pay_batch_'.$i)->id;
        }
        $this->enableGateway();
        Http::fake(fn () => Http::response([
            'status' => 'created',
            'amount' => 472000,
            'currency' => 'INR',
            'payments' => [],
        ], 200));

        Artisan::call('jannayaks:reconcile-payment-captures');

        $checked = Payment::query()->whereIn('id', $ids)->where('gateway_check_count', 1)->count();
        $this->assertSame(20, $checked);
        $this->assertSame(1, Payment::query()->whereIn('id', $ids)->where('gateway_check_count', 0)->count());
    }

    private function reconcile(Payment $payment): string
    {
        return app(RazorpayPaymentService::class)->reconcileOpenAttempt($payment->fresh());
    }

    private function openAttempt(string $linkId, string $payId, ?Application $application = null): Payment
    {
        Config::set('services.razorpay.enabled', false);
        $application ??= Application::factory()->create([
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
            'status' => 'intake_in_progress',
        ]);
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($application);
        $payment->forceFill([
            'razorpay_link_id' => $linkId,
            'razorpay_link_url' => 'https://rzp.io/i/'.$linkId,
            'status' => Payment::STATUS_INITIATED,
            'created_at' => now()->subHour(),
        ])->save();

        return $payment->fresh();
    }

    private function enableGateway(): void
    {
        Config::set('services.razorpay.enabled', true);
        Config::set('services.razorpay.mode', 'test');
        Config::set('services.razorpay.key_id', 'rzp_test_reconcile');
        Config::set('services.razorpay.key_secret', 'secret_reconcile');
        Config::set('services.razorpay.webhook_secret', 'whsec_reconcile');
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function replaceHttpFake(\Closure $callback): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake($callback);
    }

    private function fakeLink(string $linkId, string $linkStatus, array $payments): void
    {
        $this->replaceHttpFake(function ($request) use ($linkId, $linkStatus, $payments) {
            return $this->gatewayResponse($request->url(), $linkId, $linkStatus, $payments);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $payments
     */
    private function gatewayResponse(string $url, string $linkId, string $linkStatus, array $payments)
    {
        foreach ($payments as $payment) {
            if (str_contains($url, '/payments/'.$payment['id'])) {
                return Http::response($payment, 200);
            }
        }
        if (str_contains($url, '/payment_links/'.$linkId)) {
            return Http::response([
                'id' => $linkId,
                'status' => $linkStatus,
                'amount' => 472000,
                'currency' => 'INR',
                'payments' => array_map(fn (array $payment): array => ['id' => $payment['id']], $payments),
            ], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentBody(string $id, string $status, int $amount, string $currency = 'INR'): array
    {
        return [
            'id' => $id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
        ];
    }

    private function invoiceCount(Payment $payment): int
    {
        return Payment::query()
            ->where('application_id', $payment->application_id)
            ->whereNotNull('invoice_number')
            ->count();
    }
}
