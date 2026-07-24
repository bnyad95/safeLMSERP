<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'name' => $this->faker->words(3, true),
            'credits' => $this->faker->randomElement([3, 4, 6]),
            'status' => 'active',
        ];
    }
}
