<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommentRequest;
use App\Http\Requests\Api\V1\UpdateCommentRequest;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Task $task)
    {
        $this->authorize('viewAny', [Comment::class, $task]);
        return CommentResource::collection($task->comments()->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCommentRequest $request, Task $task)
    {
        $this->authorize('create', [Comment::class, $task]);

        $comment = $task->comments()->create(array_merge(
            $request->validated(),
            ['user_id' => Auth::id()]
        ));

        return CommentResource::make($comment);
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        $this->authorize('view', $comment);
        return CommentResource::make($comment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        $this->authorize('update', $comment);

        $comment->update($request->validated());

        return CommentResource::make($comment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
