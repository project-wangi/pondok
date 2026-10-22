<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class GuruPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Guru') || $this->isTeacher($authUser);
    }

    public function view(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser->can('View:Guru') || $this->ownsProfile($authUser, $guru);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Guru');
    }

    public function update(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser->can('Update:Guru') || $this->ownsProfile($authUser, $guru);
    }

    public function delete(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser->can('Delete:Guru');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Guru');
    }

    public function restore(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser->can('Restore:Guru');
    }

    public function forceDelete(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser->can('ForceDelete:Guru');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Guru');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Guru');
    }

    public function replicate(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser->can('Replicate:Guru');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Guru');
    }

    private function isTeacher(AuthUser $authUser): bool
    {
        return $authUser instanceof User && $authUser->guru()->exists();
    }

    private function ownsProfile(AuthUser $authUser, Guru $guru): bool
    {
        return $authUser instanceof User
            && $authUser->guru()->whereKey($guru->getKey())->exists();
    }
}
