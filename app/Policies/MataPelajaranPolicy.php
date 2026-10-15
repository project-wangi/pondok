<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MataPelajaran;
use Illuminate\Auth\Access\HandlesAuthorization;

class MataPelajaranPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MataPelajaran');
    }

    public function view(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('View:MataPelajaran');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MataPelajaran');
    }

    public function update(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('Update:MataPelajaran');
    }

    public function delete(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('Delete:MataPelajaran');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MataPelajaran');
    }

    public function restore(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('Restore:MataPelajaran');
    }

    public function forceDelete(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('ForceDelete:MataPelajaran');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MataPelajaran');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MataPelajaran');
    }

    public function replicate(AuthUser $authUser, MataPelajaran $mataPelajaran): bool
    {
        return $authUser->can('Replicate:MataPelajaran');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MataPelajaran');
    }

}