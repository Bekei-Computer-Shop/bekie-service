<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $username
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property string $role
 * @property bool $is_active
 * @property bool $is_banned
 * @property bool $is_admin
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'email',
        'phone',
        'role',
        'password',
        'is_active',
        'is_banned',
        'is_admin',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_banned' => 'boolean',
            'is_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @var list<string>
     */
    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
    }

    public function setNameAttribute(string $value): void
    {
        [$firstName, $lastName] = array_pad(explode(' ', $value, 2), 2, '');

        $this->attributes['first_name'] = $firstName;
        $this->attributes['last_name'] = $lastName;
    }

    /**
     * Boot hook: keep the legacy `users.role` column in sync with the
     * highest-privilege Spatie role name. This means callers that still
     * read `User::role` (e.g. AdminResourcePolicy::before) keep working.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            $roles = $user->roles()->pluck('name')->all();

            if ($roles === []) {
                return;
            }

            $ordered = ['admin', 'manager', 'staff'];
            $roleColumn = collect($ordered)
                ->first(fn (string $r) => in_array($r, $roles, true))
                ?? $roles[0];

            if ($user->role !== $roleColumn) {
                $user->newQueryWithoutRelationships()
                    ->where('id', $user->id)
                    ->update(['role' => $roleColumn]);
            }
        });
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function adminTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class)->where('scope', 'admin');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'author_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TeamActivityLog::class, 'actor_id');
    }

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'customer_group_user');
    }

    /**
     * Convenience helper: returns true when the user is the platform-level
     * super-admin (active, not banned, has both `is_admin` and the `admin` role).
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_admin
            && $this->is_active
            && ! $this->is_banned
            && $this->hasRole('admin');
    }

    /**
     * Count active super-admins excluding the given user id.
     *
     * Used by the last-admin guard in the admin user-management endpoints.
     */
    public static function countActiveSuperAdminsExcept(?int $excludeId = null): int
    {
        return static::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->whereNull('deleted_at')
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin')->where('guard_name', 'api'))
            ->count();
    }
}
