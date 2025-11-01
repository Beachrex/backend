<?php

namespace Database\Factories;

use App\Models\Beach;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Beach>
 */
class BeachFactory extends Factory
{
    protected $model = Beach::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'status_id' => 1,
        ];
    }
}

