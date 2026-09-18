<?php

namespace Database\Factories;

use App\Models\InMemoriamProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InMemoriamProfile>
 */
class InMemoriamProfileFactory extends Factory
{
    protected $model = InMemoriamProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'status' => InMemoriamProfile::STATUS_DRAFT,
            'commissioner_contact_name' => fake()->name(),
            'commissioner_contact_mobile' => '9'.fake()->numerify('#########'),
            'commissioner_contact_email' => fake()->safeEmail(),
            'commissioner_relation' => 'Family',
            'commissioner_display_consent' => false,
            'deceased_full_name' => $name,
            'deceased_display_name' => $name,
            'verification_status' => InMemoriamProfile::VERIFICATION_UNVERIFIED,
            'profession' => fake()->jobTitle(),
            'bio_headline' => fake()->sentence(5),
            'commission_currency' => 'INR',
            'is_sealed' => false,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
            'commission_paid_at' => now(),
        ]);
    }

    public function published(): static
    {
        $years = max(1, (int) config('jannayaks.tier_pricing.in_memoriam.hosting_years', 5));
        $tz = (string) config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata');
        $starts = now($tz)->toDateString();

        return $this->state(fn () => [
            'status' => InMemoriamProfile::STATUS_PUBLISHED_ARCHIVED,
            'slug' => 'memorial-'.fake()->unique()->bothify('????-####'),
            'slug_generated_at' => now(),
            'published_at' => now(),
            'hosting_starts_on' => $starts,
            'hosting_ends_on' => now($tz)->addYears($years)->toDateString(),
            'commission_paid_at' => now()->subDay(),
            'is_sealed' => true,
            'verification_status' => InMemoriamProfile::VERIFICATION_VERIFIED,
            'verification_method' => 'death_certificate_inspection',
            'verified_at' => now()->subDays(2),
        ]);
    }
}
