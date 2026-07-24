<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Student;
use App\Models\University;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'university_id' => University::factory(),
            'department_id' => Department::factory(),
            'student_id' => $this->faker->unique()->numerify('STU#####'),
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'status' => 'Active',
        ];
    }
}
