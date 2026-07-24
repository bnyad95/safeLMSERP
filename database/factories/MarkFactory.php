<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Mark;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarkFactory extends Factory
{
    protected $model = Mark::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'course_id' => Course::factory(),
            'assignments' => $this->faker->numberBetween(0, 10),
            'quizzes' => $this->faker->numberBetween(0, 10),
            'midterm' => $this->faker->numberBetween(0, 20),
            'practical' => $this->faker->numberBetween(0, 10),
            'final_exam' => $this->faker->numberBetween(0, 50),
            'final_mark' => $this->faker->numberBetween(0, 100),
            'status' => 'completed',
            'submission_status' => 'draft',
            'visibility_status' => 'draft',
        ];
    }

    public function withoutFinalMark()
    {
        return $this->state(fn (array $attributes) => [
            'final_mark' => 0,
            'submission_status' => 'draft',
        ]);
    }
}
