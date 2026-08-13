<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Requests\Api\V1\UpdateProjectRequest;
use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Project::class);

        $projects = Auth::user()->accessibleProjects()->with(['owner'])->paginate(10);

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request)
    {
        $this->authorize('create', Project::class);

        $project = Project::create(array_merge(
            $request->validated(),
            ['owner_id' => Auth::id()]
        ));

        return ProjectResource::make($project->load('owner')); 
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        $project = Project::with('members')->findOrFail($project->id);
        $this->authorize('view', $project);

        $project->load(['owner', 'tasks']);

        return ProjectResource::make($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, Project $project)
    {
        $project = Project::with('members')->findOrFail($project->id);
        $this->authorize('update', $project);

        $project->update($request->validated());

        return ProjectResource::make($project->load('owner')); 
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project = Project::with('members')->findOrFail($project->id);
        $this->authorize('delete', $project);

        $project->delete();

        return response()->noContent();
    }
}
