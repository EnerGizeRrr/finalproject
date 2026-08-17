<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Task; // Импортируем модель Task
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CommentPolicy
{
    /**
     * Determine whether the user can view any models.
     * Для комментариев к задаче, список обычно запрашивается через задачу.
     * Проверка доступа к задаче и её проекту происходит раньше.
     * Этот метод может быть менее релевантен при использовании shallow apiResource.
     * Но для полноты, можно разрешить, если пользователь является членом проекта задачи.
     * Однако, если не используется напрямую, можно оставить как false или адаптировать.
     * Для текущего сценария с GET /tasks/{task}/comments, важен метод view().
     */
    public function viewAny(User $user): bool
    {
        // Пока оставим как false, так как прямой доступ к списку всех комментариев не предполагается.
        // Доступ к списку комментариев к задаче регулируется через доступ к самой задаче и метод view() для каждого комментария.
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Comment $comment): bool
    {
        // Доступ к комментарию автору ИЛИ владельцу проекта задачи
        return $comment->user_id === $user->id || $comment->task->project->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Task $task): bool // Изменяем сигнатуру
    {
        // Проверяем, является ли пользователь владельцем проекта или участником проекта задачи
        return $task->project->owner_id === $user->id || $task->project->members->contains($user->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Comment $comment): bool
    {
        // Обновление комментария автором ИЛИ владельцем проекта задачи
        return $comment->user_id === $user->id || $comment->task->project->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Удаление комментария автором ИЛИ владельцем проекта задачи
        return $comment->user_id === $user->id || $comment->task->project->owner_id === $user->id;
    }
}