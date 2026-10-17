<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'ViewAny');
    }

    public function view(User $user, Post $post): bool
    {
        return $this->allowed($user, 'View');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'Create');
    }

    public function update(User $user, Post $post): bool
    {
        return $this->allowed($user, 'Update');
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->allowed($user, 'Delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user, 'DeleteAny');
    }

    public function restore(User $user, Post $post): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Post $post): bool
    {
        return $this->allowed($user, 'Replicate') && $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function allowed(User $user, string $ability): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin'])
            || $user->checkPermissionTo($ability.':Post');
    }
}
