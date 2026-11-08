<?php

namespace App\Events;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class TaskChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $action,          // created | updated | moved | deleted
        public int $tenantId,
        public int $projectId,
        public int $taskId,
        public ?array $task = null,     // full task payload, null when deleted
    ) {
        // Never echo back to the browser that caused the change (reads the X-Socket-ID header)
        $this->dontBroadcastToCurrentUser();
    }

    public static function for(string $action, Task $task): self
    {
        $payload = $action === 'deleted'
            ? null
            : (new TaskResource($task->loadMissing('assignee:id,name')))->resolve();

        return new self($action, $task->tenant_id, $task->project_id, $task->id, $payload);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("tenant.{$this->tenantId}")];
    }

    public function broadcastAs(): string
    {
        return 'task.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'task' => $this->task,
        ];
    }
}