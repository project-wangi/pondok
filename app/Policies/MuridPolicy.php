<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Murid;
use Illuminate\Auth\Access\HandlesAuthorization;

class MuridPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Murid');
    }

    public function view(AuthUser $authUser, Murid $murid): bool
    {
        return $authUser->can('View:Murid');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Murid');
    }

    public function update(AuthUser $authUser, Murid $murid): bool
    {
        return $authUser->can('Update:Murid');
    }

    public function delete(AuthUser $authUser, Murid $murid): bool
    {
        return $authUser->can('Delete:Murid');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Murid');
    }

    public function restore(AuthUser $authUser, Murid $murid): bool
    {
        return $authUser->can('Restore:Murid');
    }

    public function forceDelete(AuthUser $authUser, Murid $murid): bool
    {
        return $authUser->can('ForceDelete:Murid');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Murid');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Murid');
    }

    public function replicate(AuthUser $authUser, Murid $murid): bool
    {
        return $authUser->can('Replicate:Murid');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Murid');
    }

}