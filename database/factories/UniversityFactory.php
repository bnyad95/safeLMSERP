<?php

namespace Database\Factories;

use App\Models\University;
use Illuminate\Database\Eloquent\Factories\Factory;

class UniversityFactory extends Factory
{
    protected $model = University::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'code' => strtoupper($this->faker->unique()->bothify('???#')),
            'institution_type' => 'university',
            'expected_stage_count' => 4,
            'expected_semesters_per_year' => 2,
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
        ];
    }
}
