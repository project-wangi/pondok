<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Donasi;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DonasiPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser)
            || $authUser->can('ViewAny:Donasi')
            || $this->isTeacher($authUser);
    }

    public function view(AuthUser $authUser, Donasi $donasi): bool
    {
        return $this->isAdmin($authUser)
            || $authUser->can('View:Donasi')
            || $this->ownsRecord($authUser, $donasi);
    }

    public function create(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser)
            || $authUser->can('Create:Donasi')
            || $this->isTeacher($authUser);
    }

    public function update(AuthUser $authUser, Donasi $donasi): bool
    {
        return $this->isAdmin($authUser)
            || $authUser->can('Update:Donasi')
            || $this->ownsRecord($authUser, $donasi);
    }

    public function delete(AuthUser $authUser, Donasi $donasi): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('Delete:Donasi');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('DeleteAny:Donasi');
    }

    public function restore(AuthUser $authUser, Donasi $donasi): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('Restore:Donasi');
    }

    public function forceDelete(AuthUser $authUser, Donasi $donasi): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('ForceDelete:Donasi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('ForceDeleteAny:Donasi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('RestoreAny:Donasi');
    }

    public function replicate(AuthUser $authUser, Donasi $donasi): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('Replicate:Donasi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $this->isAdmin($authUser) || $authUser->can('Reorder:Donasi');
    }

    private function isAdmin(AuthUser $authUser): bool
    {
        return $authUser instanceof User && $authUser->isAdministrator();
    }

    private function isTeacher(AuthUser $authUser): bool
    {
        return $authUser instanceof User && $authUser->guru()->exists();
    }

    private function ownsRecord(AuthUser $authUser, Donasi $donasi): bool
    {
        return $authUser instanceof User
            && $authUser->guru()->whereKey($donasi->guru_id)->exists();
    }
}
