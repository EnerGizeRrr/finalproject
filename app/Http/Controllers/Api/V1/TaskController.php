<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTaskRequest;
use App\Http\Requests\Api\V1\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; 
class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        // Получаем ID проектов, к которым у пользователя есть доступ
        $accessibleProjectIds = Auth::user()->accessibleProjects()->pluck('id');

        $query = Task::with(['project'])
            ->whereIn('project_id', $accessibleProjectIds);

        // Фильтр по статусу
        $query->when($request->status, function ($q, $status) {
            return $q->where('status', $status);
        });

        // Фильтр по приоритету
        $query->when($request->priority, function ($q, $priority) {
            return $q->where('priority', $priority);
        });

        // Поиск по названию
        $query->when($request->search, function ($q, $search) {
            return $q->where('title', 'like', "%{$search}%");
        });

        // Фильтр по диапазону дат due_date
        $query->when($request->due_date_from && $request->due_date_to, function ($q) use ($request) {
            return $q->whereBetween('due_date', [$request->due_date_from, $request->due_date_to]);
        });

        return TaskResource::collection($query->paginate(10));
    }

    /**
     * Display a listing of tasks for a specific project.
     */
    public function indexForProject(Request $request, Project $project)
    {
        // Авторизация: пользователь должен иметь доступ к просмотру проекта
        $this->authorize('view', $project);

        $query = $project->tasks()->with(['project']);

        // Фильтры можно применить здесь, если нужно, но они будут только для задач конкретного проекта
        // Фильтр по статусу
        $query->when($request->status, function ($q, $status) {
            return $q->where('status', $status);
        });

        // Фильтр по приоритету
        $query->when($request->priority, function ($q, $priority) {
            return $q->where('priority', $priority);
        });

        // Поиск по названию
        $query->when($request->search, function ($q, $search) {
            return $q->where('title', 'like', "%{$search}%");
        });

        // Фильтр по диапазону дат due_date
        $query->when($request->due_date_from && $request->due_date_to, function ($q) use ($request) {
            return $q->whereBetween('due_date', [$request->due_date_from, $request->due_date_to]);
        });

        return TaskResource::collection($query->paginate(10));
    }

    /**
     * Display a listing of the resource using cursor pagination.
     */
    public function indexByCursor(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $limit = $request->get('limit', 10);
        $lastId = $request->get('last_id', 0);

        $tasks = Task::with(['project'])
            ->where('project_id', $project->id)
            ->where('id', '>', $lastId)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $nextLastId = $tasks->count() > 0 ? $tasks->last()->id : null;

        return response()->json([
            'data' => TaskResource::collection($tasks),
            'links' => [
                'next' => $nextLastId ? route('tasks.indexByCursor', ['project' => $project->id, 'limit' => $limit, 'last_id' => $nextLastId]) : null,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request, Project $project = null)
    {
        // Если project передан через URL (маршрут /projects/{project}/tasks), используем его.
        // Иначе, если это общий маршрут /tasks, используем project_id из запроса.
        if (!$project) {
            $project = Project::with('owner')->findOrFail($request->project_id);
        }
        $this->authorize('create', [Task::class, $project]);

        $task = Task::create($request->validated());

        return TaskResource::make($task->load('project')); 
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $this->authorize('view', $task);
       
        $task->load(['project.owner', 'comments.user']);
        return TaskResource::make($task);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task)
    {
        Log::info('TaskController@update: Method entered.', ['task_id' => $task->id, 'user_id' => auth()->id()]);
        Log::info('TaskController@update: Validated data.', $request->validated());
        Log::info('TaskController@update: Task before authorization check.', ['status_before' => $task->status->value, 'project_owner_id' => $task->project->owner_id]);

        $this->authorize('update', $task);
        Log::info('TaskController@update: Authorization passed.');

        Log::info('TaskController@update: Calling $task->update().');
        $task->update($request->validated());
        Log::info('TaskController@update: $task->update() finished.', ['status_after' => $task->status->value, 'is_dirty' => $task->isDirty(), 'changes' => $task->getChanges()]);

        return TaskResource::make($task->load('project')); 
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->noContent();
    }
}
