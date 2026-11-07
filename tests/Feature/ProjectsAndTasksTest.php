<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

function userWithRole(string $role, ?Tenant $tenant = null): User
{
    return User::factory()->create([
        'tenant_id' => ($tenant ?? Tenant::factory()->create())->id,
        'role' => $role,
    ]);
}

function projectFor(User $user): Project
{
    return Project::factory()->create(['tenant_id' => $user->tenant_id, 'created_by' => $user->id]);
}

it('only lists and shows the current tenants projects', function () {
    $a = userWithRole('manager');
    $b = userWithRole('manager');
    projectFor($a);
    $other = projectFor($b);

    Sanctum::actingAs($a);

    getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
    getJson("/api/projects/{$other->id}")->assertNotFound();
});

it('hides other tenants tasks', function () {
    $a = userWithRole('developer');
    $b = userWithRole('manager');
    $task = Task::factory()->create(['project_id' => projectFor($b)->id]);

    Sanctum::actingAs($a);

    patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed', 'position' => 1])
        ->assertNotFound();
});

it('lets viewers read but not write', function () {
    $viewer = userWithRole('viewer');
    $project = projectFor($viewer);
    $task = Task::factory()->create(['project_id' => $project->id]);

    Sanctum::actingAs($viewer);

    getJson("/api/projects/{$project->id}/tasks")->assertOk();
    patchJson("/api/tasks/{$task->id}/status", ['status' => 'completed', 'position' => 1])->assertForbidden();
    postJson("/api/projects/{$project->id}/tasks", ['title' => 'Nope'])->assertForbidden();
    deleteJson("/api/tasks/{$task->id}")->assertForbidden();
});

it('lets developers move tasks but not create or edit them', function () {
    $dev = userWithRole('developer');
    $project = projectFor($dev);
    $task = Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);

    Sanctum::actingAs($dev);

    patchJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress', 'position' => 2.5])->assertOk();
    expect($task->fresh()->status->value)->toBe('in_progress');

    postJson("/api/projects/{$project->id}/tasks", ['title' => 'Nope'])->assertForbidden();
    patchJson("/api/tasks/{$task->id}", ['title' => 'Renamed'])->assertForbidden();
});

it('lets managers create tasks and appends them to the column', function () {
    $manager = userWithRole('manager');
    $project = projectFor($manager);

    Sanctum::actingAs($manager);

    postJson("/api/projects/{$project->id}/tasks", ['title' => 'First', 'status' => 'todo'])->assertCreated();
    postJson("/api/projects/{$project->id}/tasks", ['title' => 'Second', 'status' => 'todo'])->assertCreated();

    $positions = Task::withoutGlobalScopes()->where('project_id', $project->id)
        ->orderBy('position')->pluck('title')->all();

    expect($positions)->toBe(['First', 'Second']);
});

it('rejects assigning a task to a user from another tenant', function () {
    $manager = userWithRole('manager');
    $outsider = userWithRole('developer');   // different tenant
    $project = projectFor($manager);

    Sanctum::actingAs($manager);

    postJson("/api/projects/{$project->id}/tasks", ['title' => 'X', 'assigned_to' => $outsider->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assigned_to');
});

it('ignores tenant_id and created_by sent by the client', function () {
    $manager = userWithRole('manager');
    $otherTenant = Tenant::factory()->create();

    Sanctum::actingAs($manager);

    $id = postJson('/api/projects', [
        'name' => 'Mine',
        'tenant_id' => $otherTenant->id,
        'created_by' => 999,
    ])->assertCreated()->json('data.id');

    $project = Project::withoutGlobalScopes()->find($id);
    expect($project->tenant_id)->toBe($manager->tenant_id)
        ->and($project->created_by)->toBe($manager->id);
});

it('returns dashboard stats for the current tenant only', function () {
    $a = userWithRole('viewer');
    $b = userWithRole('manager');
    $projectA = projectFor($a);

    Task::factory()->create(['project_id' => $projectA->id, 'status' => 'todo', 'due_date' => now()->subDay()]);
    Task::factory()->create(['project_id' => $projectA->id, 'status' => 'completed', 'due_date' => now()->subDay()]);
    Task::factory()->create(['project_id' => projectFor($b)->id]);   // other tenant

    Sanctum::actingAs($a);

    getJson('/api/dashboard/stats')->assertOk()->assertJson([
        'data' => [
            'projects' => 1,
            'open_tasks' => 1,
            'completed_tasks' => 1,
            'overdue_tasks' => 1,
        ]
    ]);
});

it('lists only members of the current tenant', function () {
    $viewer = userWithRole('viewer');
    $mate = userWithRole('developer', $viewer->tenant);
    userWithRole('admin');   // another tenant

    Sanctum::actingAs($viewer);

    getJson('/api/members')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonMissing(['email' => $mate->email]);
});