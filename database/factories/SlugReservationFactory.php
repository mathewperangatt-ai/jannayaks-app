<?php

namespace Database\Factories;

use App\Models\SlugReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SlugReservation>
 */
class SlugReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'profile_id' => null,
            'application_id' => null,
            'source' => SlugReservation::SOURCE_ASSIGNED,
        ];
    }
}
