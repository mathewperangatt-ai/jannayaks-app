<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'direct_submission_received_at' => now(),
            'direct_submission_note' => 'Factory-generated direct submission.',
        ]);
    }

    public function adminTestDemo(int $waivedByUserId): self
    {
        return $this->state(fn () => [
            'source_method'         => 'admin_test_demo',
            'waived_by_user_id'     => $waivedByUserId,
            'admin_demo_audit_note' => 'Factory-generated admin test demo.',
        ]);
    }
}
