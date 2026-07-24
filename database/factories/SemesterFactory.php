<?php

namespace Database\Factories;

use App\Models\Semester;
use App\Models\University;
use Illuminate\Database\Eloquent\Factories\Factory;

class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        return [
            'university_id' => University::factory(),
            'name' => 'Semester '.$this->faker->numberBetween(1, 8),
            'academic_year' => '2025-2026',
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
        ];
    }
}
