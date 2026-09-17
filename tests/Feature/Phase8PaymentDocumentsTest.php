<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\InvoiceService;
use App\Services\RazorpayPaymentService;
use App\Services\RazorpayWebhookVerifier;
use App\Services\RefundService;
use App\Support\PricingAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase8PaymentDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinguished_addon_increases_payable_amount(): void
    {
        $base = PricingAmounts::forApplicationPackage('distinguished', false);
        $withAddon = PricingAmounts::forApplicationPackage('distinguished', true);

        $this->assertSame(2500000, $base['amount_incl_paise']);
        $this->assertSame(3500000, $withAddon['amount_incl_paise']);
        $this->assertTrue($withAddon['includes_addon']);
        $this->assertSame(1000000, $withAddon['addon']['amount_incl_paise']);
    }

    public function test_payment_initiation_charges_addon_when_flagged(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'distinguished_interview_addon' => true,
        ]);
        Config::set('services.razorpay.enabled', false);

        $res = $this->actingAs($user)->postJson(route('applications.payment.initiate', $app));
        $res->assertStatus(201);

        $payment = Payment::query()->where('application_id', $app->id)->latest('id')->firstOrFail();
        $this->assertSame(3500000, $payment->totalPaise());
    }

    public function test_payment_initiation_without_addon_charges_base_only(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'distinguished_interview_addon' => false,
        ]);
        Config::set('services.razorpay.enabled', false);

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $app))->assertStatus(201);
        $payment = Payment::query()->where('application_id', $app->id)->latest('id')->firstOrFail();
        $this->assertSame(2500000, $payment->totalPaise());
    }

    public function test_changing_addon_before_pay_replaces_stale_pending_amount(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'distinguished_interview_addon' => false,
        ]);
        Config::set('services.razorpay.enabled', false);

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $app))->assertStatus(201);
        $this->assertSame(2500000, Payment::query()->where('application_id', $app->id)->activeAttempts()->firstOrFail()->totalPaise());

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $app), [
            'distinguished_interview_addon' => true,
        ])->assertStatus(201);

        $active = Payment::query()->where('application_id', $app->id)->activeAttempts()->latest('id')->firstOrFail();
        $this->assertSame(3500000, $active->totalPaise());
        $this->assertTrue((bool) $app->fresh()->distinguished_interview_addon);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->activeAttempts()->count());
        $this->assertSame(
            0,
            Payment::query()
                ->where('application_id', $app->id)
                ->activeAttempts()
                ->where('id', '!=', $active->id)
                ->count()
        );
        $this->assertTrue(
            Payment::query()
                ->where('application_id', $app->id)
                ->where('status', Payment::STATUS_CANCELLED)
                ->exists()
        );
    }

    public function test_webhook_assigns_distinct_receipt_and_tax_invoice_numbers(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_phase8_test');
        Config::set('services.razorpay.enabled', false);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create(['package_tier' => 'emerging']);
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $payment->forceFill([
            'razorpay_link_id' => 'plink_phase8_1',
            'amount' => '3000.00',
        ])->save();

        $payload = json_encode([
            'event' => 'payment_link.paid',
            'event_id' => 'evt_phase8_docs_1',
            'created_at' => time(),
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_phase8_1',
                        'amount' => 300000,
                        'currency' => 'INR',
                        'order_id' => null,
                        'notes' => [
                            'payment_id' => (string) $payment->id,
                            'application_id' => (string) $app->id,
                        ],
                    ],
                ],
                'payment_link' => [
                    'entity' => [
                        'id' => 'plink_phase8_1',
                        'amount' => 300000,
                        'currency' => 'INR',
                        'reference_id' => $payment->transaction_reference,
                        'notes' => [
                            'payment_id' => (string) $payment->id,
                            'application_id' => (string) $app->id,
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $sig = hash_hmac('sha256', $payload, 'whsec_phase8_test');
        $res = $this->call(
            'POST',
            route('payments.razorpay.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', RazorpayWebhookVerifier::SIGNATURE_HEADER)) => $sig,
            ],
            $payload,
        );
        $res->assertOk();

        $payment->refresh();
        $this->assertNotNull($payment->invoice_number);
        $this->assertNotNull($payment->tax_invoice_number);
        $this->assertStringStartsWith(InvoiceService::RECEIPT_PREFIX, (string) $payment->invoice_number);
        $this->assertStringStartsWith(InvoiceService::INVOICE_PREFIX, (string) $payment->tax_invoice_number);
        $this->assertNotSame($payment->invoice_number, $payment->tax_invoice_number);
    }

    public function test_owner_can_view_receipt_and_tax_invoice_after_settlement(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging']);
        $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $payment->refresh();

        $this->actingAs($user)->get(route('payments.receipt', $payment))
            ->assertOk()
            ->assertSee('Payment Receipt', false)
            ->assertSee($payment->invoice_number, false)
            ->assertDontSee('GST TAX INVOICE', false);

        $this->actingAs($user)->get(route('payments.tax-invoice', $payment))
            ->assertOk()
            ->assertSee('GST Tax Invoice', false)
            ->assertSee($payment->tax_invoice_number, false)
            ->assertSee('distinct from the payment receipt', false);
    }

    public function test_cross_user_cannot_view_payment_documents(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($owner)->paid()->create();
        $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
        app(InvoiceService::class)->assignSettlementDocuments($payment);

        $this->actingAs($other)->get(route('payments.receipt', $payment))->assertForbidden();
        $this->actingAs($other)->get(route('payments.tax-invoice', $payment))->assertForbidden();
    }

    public function test_unsettled_payment_documents_are_unavailable(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create(['package_tier' => 'emerging']);
        Config::set('services.razorpay.enabled', false);
        $payment = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);

        $this->actingAs($user)->get(route('payments.receipt', $payment))->assertNotFound();
        $this->actingAs($user)->get(route('payments.tax-invoice', $payment))->assertNotFound();
    }

    public function test_refund_records_credit_note_without_gateway_api(): void
    {
        Config::set('jannayaks.refund.before_publication_percent', 60);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create(['package_tier' => 'emerging']);
        $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
        app(InvoiceService::class)->assignSettlementDocuments($payment);

        $refunded = app(RefundService::class)->initiateRefund($payment, 'Customer requested refund before publication.');
        $this->assertNotNull($refunded);
        $this->assertTrue($refunded->isRefunded());
        $this->assertNotNull($refunded->credit_note_number);
        $this->assertStringStartsWith(InvoiceService::CREDIT_NOTE_PREFIX, (string) $refunded->credit_note_number);
        $this->assertSame(180000, (int) round((float) $refunded->refund_amount * 100));

        $summary = app(RefundService::class)->refundSummary($refunded);
        $this->assertFalse($summary['gateway_refund_executed']);

        $this->actingAs($user)->get(route('payments.credit-note', $refunded))
            ->assertOk()
            ->assertSee('Credit Note', false)
            ->assertSee($refunded->credit_note_number, false)
            ->assertSee($payment->invoice_number, false);
    }

    public function test_tax_invoice_does_not_invent_seller_gstin(): void
    {
        Config::set('jannayaks.tier_pricing.billing.gstin', '');
        Config::set('jannayaks.tier_pricing.billing.legal_name', '');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create();
        $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
        $details = app(InvoiceService::class)->taxInvoiceDetails(
            app(InvoiceService::class)->assignSettlementDocuments($payment)
        );

        $this->assertNull($details['seller_gstin']);
        $this->assertFalse($details['seller_details_configured']);
    }

    public function test_forged_payment_status_still_cannot_unlock_after_p8(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        DB::table('applications')->where('id', $app->id)->update([
            'payment_status' => 'paid',
            'payment_settled_at' => now(),
        ]);

        $this->actingAs($user)->get(route('online-interview.show', $app))->assertRedirect();
        $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
    }

    public function test_receipt_and_tax_invoice_prefixes_remain_unique(): void
    {
        $svc = app(InvoiceService::class);
        $refs = [];
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create(['email_verified_at' => now()]);
            $app = Application::factory()->for($user)->paid()->create();
            $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
            $svc->assignSettlementDocuments($payment);
            $payment->refresh();
            $refs[] = $payment->invoice_number;
            $refs[] = $payment->tax_invoice_number;
        }
        $this->assertSame(count($refs), count(array_unique($refs)));
    }

    /** A. Cancelled/superseded success webhook cannot pay or unlock. */
    public function test_cancelled_payment_success_webhook_cannot_settle_or_unlock(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_phase8_super');
        Config::set('services.razorpay.enabled', false);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'distinguished_interview_addon' => false,
            'source_method' => 'online_interview',
        ]);

        $old = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $old->forceFill(['razorpay_link_id' => 'plink_old_super'])->save();
        app(RazorpayPaymentService::class)->supersedeAttempt($old, 'Test supersession.');
        $old->refresh();
        $this->assertSame(Payment::STATUS_CANCELLED, $old->status);

        $payload = json_encode([
            'event' => 'payment_link.paid',
            'event_id' => 'evt_super_ignored',
            'created_at' => time(),
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_super_old',
                        'amount' => 2500000,
                        'currency' => 'INR',
                        'notes' => [
                            'payment_id' => (string) $old->id,
                            'application_id' => (string) $app->id,
                        ],
                    ],
                ],
                'payment_link' => [
                    'entity' => [
                        'id' => 'plink_old_super',
                        'amount' => 2500000,
                        'currency' => 'INR',
                        'reference_id' => $old->transaction_reference,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $sig = hash_hmac('sha256', $payload, 'whsec_phase8_super');
        $res = $this->call(
            'POST',
            route('payments.razorpay.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.strtoupper(str_replace('-', '_', RazorpayWebhookVerifier::SIGNATURE_HEADER)) => $sig,
            ],
            $payload,
        );
        $res->assertStatus(409)->assertJson(['ignored_superseded' => true]);

        $old->refresh();
        $this->assertSame(Payment::STATUS_CANCELLED, $old->status);
        $this->assertFalse($old->isPaidOrBetter());
        $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app->fresh()));
        $this->assertNull($old->invoice_number);
    }

    /** B. After addon change, zero other active attempts remain. */
    public function test_addon_change_leaves_zero_other_active_attempts(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'distinguished_interview_addon' => false,
        ]);
        Config::set('services.razorpay.enabled', false);

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $app))->assertStatus(201);
        $firstId = Payment::query()->where('application_id', $app->id)->activeAttempts()->value('id');

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $app), [
            'distinguished_interview_addon' => true,
        ])->assertStatus(201);

        $actives = Payment::query()->where('application_id', $app->id)->activeAttempts()->get();
        $this->assertCount(1, $actives);
        $this->assertSame(3500000, $actives->first()->totalPaise());
        $this->assertNotSame($firstId, $actives->first()->id);
        $this->assertSame(Payment::STATUS_CANCELLED, Payment::query()->findOrFail($firstId)->status);
    }

    /** C. Razorpay-enabled amount change supersedes old link and creates correct new amount. */
    public function test_razorpay_enabled_amount_change_supersedes_old_link(): void
    {
        Config::set('services.razorpay.enabled', true);
        Config::set('services.razorpay.key_id', 'rzp_test_phase8key');
        Config::set('services.razorpay.key_secret', 'phase8secret');
        Config::set('services.razorpay.mode', 'test');
        Config::set('services.razorpay.require_test_prefix', true);

        Http::fake([
            'api.razorpay.com/v1/payment_links' => Http::sequence()
                ->push(['id' => 'plink_first', 'short_url' => 'https://rzp.io/first'], 200)
                ->push(['id' => 'plink_second', 'short_url' => 'https://rzp.io/second'], 200),
            'api.razorpay.com/v1/payment_links/plink_first/cancel' => Http::response(['id' => 'plink_first'], 200),
        ]);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->create([
            'package_tier' => 'distinguished',
            'distinguished_interview_addon' => false,
        ]);

        $first = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app);
        $this->assertSame('plink_first', $first->razorpay_link_id);
        $this->assertSame(2500000, $first->totalPaise());

        $app->forceFill(['distinguished_interview_addon' => true])->save();
        $second = app(RazorpayPaymentService::class)->createApplicationPaymentLink($app->fresh());

        $this->assertSame(3500000, $second->totalPaise());
        $this->assertSame('plink_second', $second->razorpay_link_id);
        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->activeAttempts()->count());
        $this->assertSame(Payment::STATUS_CANCELLED, $first->fresh()->status);
        $this->assertNull($first->fresh()->razorpay_link_url);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/payment_links/plink_first/cancel');
        });
    }

    /** D. Non-distinguished addon input is forced false and never affects amount. */
    public function test_non_distinguished_addon_is_forced_false_and_does_not_affect_amount(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Config::set('services.razorpay.enabled', false);

        $res = $this->actingAs($user)->postJson(route('applications.store'), [
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Addon Abuse',
            'contact_email' => 'addon-abuse@example.com',
            'distinguished_interview_addon' => true,
        ]);
        $res->assertCreated();
        $app = Application::query()->findOrFail($res->json('application_id'));
        $this->assertFalse((bool) $app->distinguished_interview_addon);

        $this->actingAs($user)->postJson(route('applications.payment.initiate', $app), [
            'distinguished_interview_addon' => true,
        ])->assertStatus(201);

        $payment = Payment::query()->where('application_id', $app->id)->activeAttempts()->firstOrFail();
        $this->assertSame(300000, $payment->totalPaise());
        $this->assertFalse((bool) $app->fresh()->distinguished_interview_addon);
    }

    /** E. Refunded payment cannot unlock interview/uploads. */
    public function test_refunded_payment_cannot_unlock_interview_or_uploads(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($user)->paid()->create([
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);
        $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
        $this->assertTrue(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app));

        app(RefundService::class)->initiateRefund($payment, 'Pre-publication refund.');
        $app = $app->fresh();

        $this->assertFalse(app(ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($app));
        $this->actingAs($user)->get(route('online-interview.show', $app))->assertRedirect();
        Storage::fake('private_uploads');
        $file = UploadedFile::fake()->create('bio.pdf', 100, 'application/pdf');
        $this->actingAs($user)->postJson(route('applications.upload.material', $app), [
            'material' => $file,
            'material_type' => 'biography',
        ])->assertForbidden();
    }

    /** F. Credit-note IDOR for another member's payment. */
    public function test_credit_note_idor_denied_for_other_member(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);
        $app = Application::factory()->for($owner)->paid()->create();
        $payment = Payment::query()->where('application_id', $app->id)->firstOrFail();
        app(InvoiceService::class)->assignSettlementDocuments($payment);
        $refunded = app(RefundService::class)->initiateRefund($payment, 'Refund for IDOR test.');

        $this->actingAs($other)->get(route('payments.credit-note', $refunded))->assertForbidden();
        $this->actingAs($owner)->get(route('payments.credit-note', $refunded))->assertOk();
    }
}
