<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseMaterialFactory extends Factory
{
    protected $model = CourseMaterial::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'file_path' => null,
            'file_type' => $this->faker->randomElement(['pdf', 'doc', 'video', 'image', 'presentation', 'other']),
            'visibility' => $this->faker->randomElement(['draft', 'published']),
            'uploaded_by' => User::factory(),
        ];
    }
}
