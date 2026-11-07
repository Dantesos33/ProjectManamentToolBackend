<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'tenant_id' => fn(array $attrs) => Project::withoutGlobalScopes()->find($attrs['project_id'])->tenant_id,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(array_column(TaskStatus::cases(), 'value')),
            'priority' => fake()->randomElement(array_column(TaskPriority::cases(), 'value')),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+2 months'),
            'position' => fake()->randomFloat(4, 1, 100),
        ];
    }
}
