<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * @return array<string,mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'               => User::factory(),
            'source_method'         => 'online_interview',
            'package_tier'          => 'emerging',
            'full_name'             => fake()->name(),
            'preferred_display_name' => fake()->name(),
            'preferred_contact_email'  => fake()->unique()->safeEmail(),
            'preferred_contact_mobile' => '9198'.fake()->numerify('########'),
            'intake_started_at'     => now(),
        ];
    }

    public function directSubmission(): self
    {
        return $this->state(fn () => [
            'source_method' => 'direct_submission',
            'direct_submission_note' => 'Factory-generated direct submission.',
        ])->afterCreating(function (Application $application) {
            $application->forceFill([
                'direct_submission_received_at' => now(),
            ])->save();
        });
    }

    public function adminTestDemo(int $waivedByUserId): self
    {
        return $this->state(fn () => [
            'source_method' => 'admin_test_demo',
        ])->afterCreating(function (Application $application) use ($waivedByUserId) {
            $application->forceFill([
                'waived_by_user_id'     => $waivedByUserId,
                'admin_demo_audit_note' => 'Factory-generated admin test demo.',
                'payment_status'        => Application::PAYMENT_STATUS_WAIVED,
            ])->save();
        });
    }

    public function paid(): self
    {
        return $this->afterCreating(function (Application $application) {
            $application->forceFill([
                'payment_status' => Application::PAYMENT_STATUS_PAID,
                'payment_settled_at' => now(),
                'status' => Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW,
            ])->save();

            Payment::query()->create([
                'application_id' => $application->id,
                'transaction_reference' => 'TEST-SETTLED-'.$application->id.'-'.Str::upper(Str::random(8)),
                'gateway' => Payment::GATEWAY_MANUAL,
                'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
                'amount' => '3000.00',
                'currency' => 'INR',
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'event_type' => 'application_package',
            ]);
        });
    }
}
