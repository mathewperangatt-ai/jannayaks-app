<?php

namespace Database\Factories;

use App\Models\ReservedSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservedSlug>
 */
class ReservedSlugFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'category' => ReservedSlug::CATEGORY_OTHER,
            'reason' => 'Protected name',
        ];
    }
}
