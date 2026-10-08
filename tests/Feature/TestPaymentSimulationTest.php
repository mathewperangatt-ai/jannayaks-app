<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Razorpay pre-launch state:
 *  - the payment page's live-payment actions are disabled with a
 *    "coming shortly" notice (Malayalam default, English via the switch);
 *  - allowlisted test accounts can be settled through the temporary artisan
 *    command, reaching the SAME post-payment state a genuine settlement
 *    produces (marked simulated, no invented gateway IDs, no invoices).
 */
class TestPaymentSimulationTest extends TestCase
{
    use RefreshDatabase;

    private function makeApplicationFor(User $user): Application
    {
        $this->actingAs($user)->post(route('applications.store'), [
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'full_name' => 'Pre-Launch Workflow Test',
            'contact_email' => 'prelaunch.test@example.com',
        ])->assertRedirect();

        /** @var Application $app */
        $app = Application::query()->latest('id')->first();
        $this->assertSame('payment_pending', (string) $app->status);

        return $app;
    }

    /* 1. Disabled live-payment UI (Malayalam default + English switch). */
    public function test_payment_actions_are_disabled_with_coming_soon_notice(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $app = $this->makeApplicationFor($user);

        // Malayalam is the flow default.
        $this->actingAs($user)
            ->get(route('applications.payment', ['application' => $app->id]))
            ->assertOk()
            ->assertSee('ഓൺലൈൻ പേയ്‌മെൻ്റ് ഉടൻ ലഭ്യമാകും')
            ->assertSee('disabled');

        // English via the existing language switch.
        $this->actingAs($user)
            ->get(route('applications.payment', ['application' => $app->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Online payment will be available shortly.')
            ->assertSee('disabled');
    }

    /* 2. Allowlisted email: settles via the genuine primitives, marked simulated. */
    public function test_simulation_settles_allowlisted_account_and_unlocks_interview(): void
    {
        $user = User::factory()->create([
            'email' => 'ravison05@gmail.com',
            'email_verified_at' => now(),
        ]);
        $app = $this->makeApplicationFor($user);

        $this->artisan('payments:simulate-test-payment', ['user' => 'ravison05@gmail.com'])
            ->assertExitCode(0);

        $payment = Payment::query()->where('application_id', $app->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(Payment::STATUS_PAID, (string) $payment->status);
        $this->assertSame('test_simulated', (string) $payment->method);
        $this->assertNull($payment->gateway_payment_id, 'No Razorpay transaction ID may be invented.');
        $this->assertNull($payment->razorpay_link_id);
        $this->assertNull($payment->razorpay_order_id);
        $this->assertNull($payment->invoice_number, 'Simulated payments must not produce invoice documents.');

        $app->refresh();
        $this->assertSame('paid', (string) $app->payment_status);
        $this->assertSame(Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW, (string) $app->status);
        $this->assertNotNull($app->payment_settled_at);
        $this->assertStringContainsString('SIMULATED TEST PAYMENT', (string) $app->admin_demo_audit_note);

        // The complete post-payment workflow is reachable: the Online
        // Interview gate opens exactly as it would after a real payment.
        $this->actingAs($user)
            ->get(route('online-interview.show', ['application' => $app->id]))
            ->assertOk();
    }

    /* 3. Username allowlist entry works the same way. */
    public function test_simulation_accepts_the_username_allowlist_entry(): void
    {
        $user = User::factory()->create([
            'username' => 'mathewin',
            'email_verified_at' => now(),
        ]);
        $app = $this->makeApplicationFor($user);

        $this->artisan('payments:simulate-test-payment', ['user' => 'mathewin'])
            ->assertExitCode(0);

        $this->assertSame(Payment::STATUS_PAID, (string) Payment::query()
            ->where('application_id', $app->id)->first()->status);
        $this->assertSame(Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW, (string) $app->fresh()->status);
    }

    /* 4. Ordinary (non-allowlisted) accounts can never invoke it. */
    public function test_simulation_refuses_non_allowlisted_accounts(): void
    {
        $user = User::factory()->create([
            'email' => 'someone.else@example.com',
            'email_verified_at' => now(),
        ]);
        $app = $this->makeApplicationFor($user);

        $this->artisan('payments:simulate-test-payment', ['user' => 'someone.else@example.com'])
            ->assertExitCode(1);

        $this->assertSame(0, Payment::query()->where('application_id', $app->id)->count());
        $this->assertSame('payment_pending', (string) $app->fresh()->status);
    }

    /* 5. Self-disabling: refuses once Razorpay is enabled (launch safety). */
    public function test_simulation_refuses_to_run_once_razorpay_is_enabled(): void
    {
        config(['services.razorpay' => [
            'enabled' => true,
            'mode' => 'test',
            'key_id' => 'rzp_test_XXXXXXXXXXXX',
            'key_secret' => 'secret',
        ]]);

        $user = User::factory()->create([
            'email' => 'terry.perangat@gmail.com',
            'email_verified_at' => now(),
        ]);
        $app = $this->makeApplicationFor($user);

        $this->artisan('payments:simulate-test-payment', ['user' => 'terry.perangat@gmail.com'])
            ->assertExitCode(1);

        $this->assertSame(0, Payment::query()->where('application_id', $app->id)->count());
    }

    /* 6. Re-running never creates a second settled payment. */
    public function test_simulation_is_idempotent_for_already_settled_applications(): void
    {
        $user = User::factory()->create([
            'email' => 'sreejas.tvm@gmail.com',
            'email_verified_at' => now(),
        ]);
        $app = $this->makeApplicationFor($user);

        $this->artisan('payments:simulate-test-payment', ['user' => 'sreejas.tvm@gmail.com'])->assertExitCode(0);
        $this->artisan('payments:simulate-test-payment', ['user' => 'sreejas.tvm@gmail.com'])
            ->assertExitCode(0)
            ->expectsOutputToContain('already has a settled payment');

        $this->assertSame(1, Payment::query()->where('application_id', $app->id)->count());
        $this->assertSame(Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW, (string) $app->fresh()->status);
    }
}
