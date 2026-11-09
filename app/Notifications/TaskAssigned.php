<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification
{
    private array $payload;

    public function __construct(Task $task, User $assigner)
    {
        // Plain values only, so nothing depends on models being reloadable later
        $this->payload = [
            'type' => 'task.assigned',
            'task_id' => $task->id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'assigned_by' => $assigner->name,
            'message' => "{$assigner->name} assigned you \"{$task->title}\"",
        ];
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}