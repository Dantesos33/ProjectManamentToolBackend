<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // A Kanban board needs every card of the project, so this is not paginated
    public function index(Request $request, Project $project)
    {
        $this->authorize('viewAny', Task::class);

        $tasks = $project->tasks()
            ->with('assignee:id,name')
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('assigned_to'), fn($q, $a) => $q->where('assigned_to', $a))
            ->orderBy('position')
            ->get();

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, Project $project)
    {
        $data = $request->validated();
        $data['status'] ??= TaskStatus::Todo->value;

        // New cards go to the bottom of their column
        $data['position'] = (float) $project->tasks()->where('status', $data['status'])->max('position') + 1;

        $task = $project->tasks()->create($data);   // tenant_id is stamped by the trait

        return new TaskResource($task->load('assignee:id,name'));
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load('assignee:id,name'));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task->update($request->validated());

        return new TaskResource($task->load('assignee:id,name'));
    }

    // Developers and up: used by the Kanban drag and drop
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task)
    {
        $task->update($request->validated());

        // Week 5: broadcast(new TaskStatusUpdated($task))->toOthers();

        return new TaskResource($task);
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }
}