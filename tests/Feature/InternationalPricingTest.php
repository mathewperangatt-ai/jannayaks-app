<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Support\PricingAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase 3 approved pricing policy:
 * - India residents: ₹5,000 creation / ₹1,500 renewal, charged in INR.
 * - Every other country: US$50 creation / US$15 renewal, charged in USD.
 * The recorded country of residence is authoritative; exchange rates never
 * recalculate the tariff; no silent INR substitution for international
 * applicants; historical rows and classifications are untouched.
 */
class InternationalPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_india_residents_receive_the_inr_tariff(): void
    {
        $quote = PricingAmounts::forStandardProfile(null, 'IN');
        $this->assertSame('INR', $quote['currency']);
        $this->assertSame(500000, $quote['amount_incl_paise']);
        $this->assertSame('₹5,000.00', $quote['amount_incl_formatted']);

        $renewal = PricingAmounts::forTierRenewal('distinguished', null, 'IN');
        $this->assertSame('INR', $renewal['currency']);
        $this->assertSame(150000, $renewal['amount_incl_paise']);
        $this->assertSame('₹1,500.00', $renewal['amount_incl_formatted']);
    }

    public function test_international_residents_receive_the_flat_usd_tariff(): void
    {
        foreach (['US', 'AE', 'GB', 'DE'] as $country) {
            $quote = PricingAmounts::forStandardProfile(null, $country);
            $this->assertSame('USD', $quote['currency'], $country);
            $this->assertSame(5000, $quote['amount_incl_paise'], $country.' — $50.00 in cents, never converted.');
            $this->assertSame('$50.00', $quote['amount_incl_formatted'], $country);
            // No invented tax figures for international sales.
            $this->assertSame(0, $quote['gst_paise'], $country);
            $this->assertNull($quote['cgst_paise'], $country);
            $this->assertNull($quote['igst_paise'], $country);

            $renewal = PricingAmounts::forTierRenewal('distinguished', null, $country);
            $this->assertSame('USD', $renewal['currency'], $country);
            $this->assertSame(1500, $renewal['amount_incl_paise'], $country.' — $15.00 in cents.');
            $this->assertSame('$15.00', $renewal['amount_incl_formatted'], $country);
        }
    }

    public function test_missing_country_keeps_the_legacy_inr_category(): void
    {
        // Existing applications without a country are never auto-classified
        // as international; they keep the legacy INR behaviour.
        $quote = PricingAmounts::forStandardProfile(null, null);
        $this->assertSame('INR', $quote['currency']);
        $this->assertSame(500000, $quote['amount_incl_paise']);

        // Historical pricing-generation rows always stay INR at their stored amounts.
        $historical = new Application([
            'package_tier' => 'distinguished',
            'pricing_generation' => null,
            'country_of_residence' => 'US',
        ]);
        $this->assertSame('INR', PricingAmounts::forApplication($historical)['currency']);
        $this->assertSame(1062000, PricingAmounts::forApplication($historical)['amount_incl_paise']);
    }

    public function test_country_of_residence_is_the_authoritative_pricing_signal(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'DE',
        ]);

        $quote = PricingAmounts::forApplication($application);
        $this->assertSame('USD', $quote['currency']);
        $this->assertSame(5000, $quote['amount_incl_paise']);
    }

    public function test_international_application_creates_a_usd_payment_link(): void
    {
        Config::set('services.razorpay.enabled', true);
        Config::set('services.razorpay.mode', 'test');
        Config::set('services.razorpay.key_id', 'rzp_test_usd');
        Config::set('services.razorpay.key_secret', 'secret_usd');

        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'US',
            'preferred_contact_email' => 'us.member@example.com',
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/payment_links' => Http::response([
                'id' => 'plink_usd_1',
                'short_url' => 'https://rzp.io/i/plink_usd_1',
                'amount' => 5000,
                'currency' => 'USD',
                'status' => 'created',
            ], 201),
        ]);

        $payment = app(\App\Services\RazorpayPaymentService::class)->createApplicationPaymentLink($application);

        $this->assertSame('USD', $payment->currency);
        $this->assertSame(5000, $payment->totalPaise(), 'USD amount stored in cents ($50.00).');
        $this->assertSame('50.00', (string) $payment->base_amount);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return str_contains($request->url(), '/payment_links')
                && ($data['currency'] ?? null) === 'USD'
                && ($data['amount'] ?? null) === 5000;
        });
    }

    public function test_india_application_keeps_the_inr_link_payload(): void
    {
        Config::set('services.razorpay.enabled', true);
        Config::set('services.razorpay.mode', 'test');
        Config::set('services.razorpay.key_id', 'rzp_test_inr');
        Config::set('services.razorpay.key_secret', 'secret_inr');

        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'IN',
            'preferred_contact_email' => 'in.member@example.com',
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/payment_links' => Http::response([
                'id' => 'plink_inr_1',
                'short_url' => 'https://rzp.io/i/plink_inr_1',
                'amount' => 500000,
                'currency' => 'INR',
                'status' => 'created',
            ], 201),
        ]);

        $payment = app(\App\Services\RazorpayPaymentService::class)->createApplicationPaymentLink($application);

        $this->assertSame('INR', $payment->currency);
        $this->assertSame(500000, $payment->totalPaise());

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return ($data['currency'] ?? null) === 'INR'
                && ($data['amount'] ?? null) === 500000;
        });
    }

    public function test_usd_capture_settles_a_usd_obligation_through_the_webhook(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_usd_test');

        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'US',
        ]);

        $payment = Payment::query()->create([
            'application_id' => $application->id,
            'transaction_reference' => 'JNK-PAY-USDT-0001',
            'gateway' => Payment::GATEWAY_RAZORPAY,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '50.00',
            'currency' => 'USD',
            'status' => Payment::STATUS_INITIATED,
            'event_type' => 'application_package',
            'razorpay_link_id' => 'plink_usd_capture',
            'razorpay_link_url' => 'https://rzp.io/i/usd',
        ]);

        $payload = json_encode([
            'event' => 'payment_link.paid',
            'event_id' => 'evt_usd_capture_1',
            'created_at' => time(),
            'payload' => [
                'payment' => ['entity' => [
                    'id' => 'pay_usd_1',
                    'status' => 'captured',
                    'amount' => 5000,
                    'currency' => 'USD',
                    'order_id' => null,
                    'notes' => [
                        'payment_id' => (string) $payment->id,
                        'application_id' => (string) $application->id,
                    ],
                ]],
                'payment_link' => ['entity' => [
                    'id' => 'plink_usd_capture',
                    'amount' => 5000,
                    'currency' => 'USD',
                    'reference_id' => $payment->transaction_reference,
                    'notes' => ['payment_id' => (string) $payment->id],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'whsec_usd_test');
        $this->call('POST', route('payments.razorpay.webhook'), [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature,
        ], $payload)->assertOk();

        $payment = $payment->fresh();
        $this->assertTrue($payment->isPaidOrBetter(), 'The USD capture settles the USD obligation.');
        $this->assertSame('pay_usd_1', $payment->gateway_payment_id);
        $this->assertSame('USD', $payment->currency, 'Currency is never rewritten.');
    }

    public function test_inr_capture_cannot_settle_a_usd_obligation(): void
    {
        Config::set('services.razorpay.webhook_secret', 'whsec_usd_mismatch');

        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'US',
        ]);

        $payment = Payment::query()->create([
            'application_id' => $application->id,
            'transaction_reference' => 'JNK-PAY-USDT-0002',
            'gateway' => Payment::GATEWAY_RAZORPAY,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '50.00',
            'currency' => 'USD',
            'status' => Payment::STATUS_INITIATED,
            'event_type' => 'application_package',
            'razorpay_link_id' => 'plink_usd_mismatch',
        ]);

        $payload = json_encode([
            'event' => 'payment_link.paid',
            'event_id' => 'evt_usd_mismatch_1',
            'created_at' => time(),
            'payload' => [
                'payment' => ['entity' => [
                    'id' => 'pay_inr_for_usd',
                    'status' => 'captured',
                    // An INR capture of an equal numeric amount must never settle USD.
                    'amount' => 500000,
                    'currency' => 'INR',
                    'order_id' => null,
                    'notes' => ['payment_id' => (string) $payment->id],
                ]],
                'payment_link' => ['entity' => [
                    'id' => 'plink_usd_mismatch',
                    'amount' => 500000,
                    'currency' => 'INR',
                    'reference_id' => $payment->transaction_reference,
                    'notes' => ['payment_id' => (string) $payment->id],
                ]],
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = hash_hmac('sha256', $payload, 'whsec_usd_mismatch');
        $this->call('POST', route('payments.razorpay.webhook'), [], [], [], [
            'HTTP_X-Razorpay-Signature' => $signature,
        ], $payload)->assertStatus(409);

        $this->assertFalse($payment->fresh()->isPaidOrBetter(), 'No silent INR substitution for a USD obligation.');
    }

    public function test_international_renewal_uses_the_usd_tariff_from_the_recorded_country(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Intl Renewal Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->for($member)->create([
            'profile_id' => $profile->id,
            'package_tier' => 'distinguished',
            'country_of_residence' => 'GB',
        ]);
        $membership = Membership::query()->create([
            'profile_id' => $profile->id,
            'user_id' => $member->id,
            'status' => 'active',
            'tier' => 'basic',
            'starts_on' => now()->subYear(),
            'ends_on' => now()->addMonth(),
            'renewal_due_on' => now()->addDays(7),
            'auto_renew' => false,
        ]);

        $this->assertSame('GB', PricingAmounts::countryForMembership($membership));
        $renewal = PricingAmounts::forTierRenewal('distinguished', null, PricingAmounts::countryForMembership($membership));
        $this->assertSame('USD', $renewal['currency']);
        $this->assertSame(1500, $renewal['amount_incl_paise']);
        $this->assertSame('$15.00', $renewal['amount_incl_formatted']);
        $this->assertSame($application->id, Application::query()
            ->where('profile_id', $profile->id)
            ->orderByDesc('id')
            ->value('id'));
    }

    public function test_legacy_membership_without_country_renews_in_inr(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Legacy Renewal Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $membership = Membership::query()->create([
            'profile_id' => $profile->id,
            'user_id' => $member->id,
            'status' => 'active',
            'tier' => 'basic',
            'starts_on' => now()->subYear(),
            'ends_on' => now()->addMonth(),
            'renewal_due_on' => now()->addDays(7),
            'auto_renew' => false,
        ]);

        $this->assertNull(PricingAmounts::countryForMembership($membership));
        $renewal = PricingAmounts::forTierRenewal('distinguished', null, PricingAmounts::countryForMembership($membership));
        $this->assertSame('INR', $renewal['currency'], 'Legacy renewals keep the INR category until a country is recorded.');
        $this->assertSame(150000, $renewal['amount_incl_paise']);
    }

    public function test_existing_issued_amounts_are_never_repriced(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->paid()->for($member)->create([
            'package_tier' => 'distinguished',
            'country_of_residence' => 'IN',
        ]);
        $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();
        $this->assertSame('3000.00', (string) $payment->amount);

        // An administrator correcting the country afterwards must not touch
        // the already-issued payment obligation.
        $application->forceFill(['country_of_residence' => 'US'])->save();

        $payment = $payment->fresh();
        $this->assertSame('3000.00', (string) $payment->amount);
        $this->assertSame('INR', $payment->currency, 'Historical transaction amounts and classifications are preserved.');
    }
}
