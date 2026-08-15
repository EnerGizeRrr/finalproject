<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Восстанавливаю импорт
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB; // Добавляю импорт DB facade
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedProjects()
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function memberProjects()
    {
        return $this->belongsToMany(Project::class, 'project_users', 'user_id', 'project_id');
    }

    public function accessibleProjects()
    {
        // Создаем подзапросы для ID проектов
        $ownedSubquery = $this->ownedProjects()->select('id');
        $memberSubquery = $this->memberProjects()->select('projects.id');

        // Используем UNION для объединения ID
        $unionQuery = $ownedSubquery->union($memberSubquery);

        // Возвращаем связь, которая использует объединённый запрос для фильтрации
        return Project::whereIn('id', $unionQuery);
    }
}
