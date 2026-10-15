<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JenisPeminatan;
use Illuminate\Auth\Access\HandlesAuthorization;

class JenisPeminatanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JenisPeminatan');
    }

    public function view(AuthUser $authUser, JenisPeminatan $jenisPeminatan): bool
    {
        return $authUser->can('View:JenisPeminatan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JenisPeminatan');
    }

    public function update(AuthUser $authUser, JenisPeminatan $jenisPeminatan): bool
    {
        return $authUser->can('Update:JenisPeminatan');
    }

    public function delete(AuthUser $authUser, JenisPeminatan $jenisPeminatan): bool
    {
        return $authUser->can('Delete:JenisPeminatan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JenisPeminatan');
    }

    public function restore(AuthUser $authUser, JenisPeminatan $jenisPeminatan): bool
    {
        return $authUser->can('Restore:JenisPeminatan');
    }

    public function forceDelete(AuthUser $authUser, JenisPeminatan $jenisPeminatan): bool
    {
        return $authUser->can('ForceDelete:JenisPeminatan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JenisPeminatan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JenisPeminatan');
    }

    public function replicate(AuthUser $authUser, JenisPeminatan $jenisPeminatan): bool
    {
        return $authUser->can('Replicate:JenisPeminatan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JenisPeminatan');
    }

}