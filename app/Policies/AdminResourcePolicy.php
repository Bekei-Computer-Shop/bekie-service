<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminResourcePolicy
{
    use HandlesAuthorization;

    public function before(User $user): bool|null
    {
        if (! $user->is_active || $user->is_banned || ! $user->is_admin || ! $user->hasRole('admin')) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user): bool
    {
        return true;
    }

    public function delete(User $user): bool
    {
        return true;
    }

    public function approve(User $user): bool
    {
        return true;
    }

    public function reject(User $user): bool
    {
        return true;
    }
}
