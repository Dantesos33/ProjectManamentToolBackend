<?php

namespace App\Support;

use App\Events\TaskChanged;
use App\Models\Task;

class Realtime
{
    public static function task(string $action, Task $task): void
    {
        try {
            event(TaskChanged::for($action, $task));
        } catch (\Throwable $e) {
            report($e);   // logged, but the user's action still succeeds
        }
    }
}