<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Role extends SpatieRole
{
    use SoftDeletes;

    /**
     * Default guard name for newly created roles. The Spatie Permission package
     * looks at this property when no explicit guard is supplied.
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
     * Users (direct relationship, bypassing Spatie's morphTo to keep eager-loading simple).
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            'role_id',
            config('permission.column_names.model_morph_key'),
        );
    }
}
