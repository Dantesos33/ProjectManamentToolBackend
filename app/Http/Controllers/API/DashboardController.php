<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $open = Task::where('status', '!=', 'completed');

        return response()->json([
            'data' => [
                'projects' => Project::count(),
                'open_tasks' => (clone $open)->count(),
                'completed_tasks' => Task::where('status', 'completed')->count(),
                'overdue_tasks' => (clone $open)->whereDate('due_date', '<', now()->toDateString())->count(),
            ]
        ]);
    }
}
