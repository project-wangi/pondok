<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Jabatan;
use Illuminate\Auth\Access\HandlesAuthorization;

class JabatanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Jabatan');
    }

    public function view(AuthUser $authUser, Jabatan $jabatan): bool
    {
        return $authUser->can('View:Jabatan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Jabatan');
    }

    public function update(AuthUser $authUser, Jabatan $jabatan): bool
    {
        return $authUser->can('Update:Jabatan');
    }

    public function delete(AuthUser $authUser, Jabatan $jabatan): bool
    {
        return $authUser->can('Delete:Jabatan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Jabatan');
    }

    public function restore(AuthUser $authUser, Jabatan $jabatan): bool
    {
        return $authUser->can('Restore:Jabatan');
    }

    public function forceDelete(AuthUser $authUser, Jabatan $jabatan): bool
    {
        return $authUser->can('ForceDelete:Jabatan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Jabatan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Jabatan');
    }

    public function replicate(AuthUser $authUser, Jabatan $jabatan): bool
    {
        return $authUser->can('Replicate:Jabatan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Jabatan');
    }

}