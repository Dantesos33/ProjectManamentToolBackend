<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    // database/seeders/DatabaseSeeder.php
    public function run(): void
    {
        foreach (['acme', 'globex'] as $slug) {
            $tenant = Tenant::create(['name' => ucfirst($slug) . ' Inc', 'domain' => $slug]);

            $users = [];
            foreach (UserRole::cases() as $role) {
                $users[$role->value] = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => ucfirst($role->value) . ' ' . ucfirst($slug),
                    'email' => "{$role->value}@{$slug}.test",
                    'password' => 'password',
                    'role' => $role,
                ]);
            }

            Project::factory(2)
                ->create(['tenant_id' => $tenant->id, 'created_by' => $users['manager']->id])
                ->each(fn(Project $p) => Task::factory(8)->create([
                    'project_id' => $p->id,
                    'tenant_id' => $tenant->id,
                    'assigned_to' => $users['developer']->id,
                ]));
        }
    }
}
