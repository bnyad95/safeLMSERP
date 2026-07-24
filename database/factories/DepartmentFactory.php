<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\University;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'university_id' => University::factory(),
            'name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->unique()->bothify('??##')),
        ];
    }
}
