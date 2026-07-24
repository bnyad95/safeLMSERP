<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'student_id' => Student::factory(),
            'date' => $this->faker->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['present', 'absent', 'late', 'excused']),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }
}
