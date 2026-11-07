<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;

class ProjectController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Project::class);

        return ProjectResource::collection(
            Project::withCount('tasks')->latest()->paginate(15)
        );
    }

    public function store(StoreProjectRequest $request)
    {
        $project = Project::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,   // from the token, never from the body
        ]);

        return new ProjectResource($project);   // Laravel answers 201 for a new model
    }

    public function show(Project $project)
    {
        $this->authorize('view', $project);

        return new ProjectResource($project->loadCount('tasks'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project->update($request->validated());

        return new ProjectResource($project->loadCount('tasks'));
    }

    public function destroy(Project $project)
    {
        $this->authorize('delete', $project);

        $project->delete();   // tasks go with it (FK cascade)

        return response()->noContent();
    }
}