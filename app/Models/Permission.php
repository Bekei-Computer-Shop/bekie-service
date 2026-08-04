<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Permission extends SpatiePermission
{
    use SoftDeletes;

    /**
     * Default guard name for newly created permissions.
     *
     * @var string
     */
    protected $guard_name = 'api';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Roles that directly grant this permission.
     *
     * @return BelongsToMany<Role>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('permission.table_names.role_has_permissions'),
            'permission_id',
            'role_id',
        );
    }
}
