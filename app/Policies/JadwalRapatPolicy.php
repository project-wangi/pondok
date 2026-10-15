<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JadwalRapat;
use Illuminate\Auth\Access\HandlesAuthorization;

class JadwalRapatPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JadwalRapat');
    }

    public function view(AuthUser $authUser, JadwalRapat $jadwalRapat): bool
    {
        return $authUser->can('View:JadwalRapat');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JadwalRapat');
    }

    public function update(AuthUser $authUser, JadwalRapat $jadwalRapat): bool
    {
        return $authUser->can('Update:JadwalRapat');
    }

    public function delete(AuthUser $authUser, JadwalRapat $jadwalRapat): bool
    {
        return $authUser->can('Delete:JadwalRapat');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JadwalRapat');
    }

    public function restore(AuthUser $authUser, JadwalRapat $jadwalRapat): bool
    {
        return $authUser->can('Restore:JadwalRapat');
    }

    public function forceDelete(AuthUser $authUser, JadwalRapat $jadwalRapat): bool
    {
        return $authUser->can('ForceDelete:JadwalRapat');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JadwalRapat');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JadwalRapat');
    }

    public function replicate(AuthUser $authUser, JadwalRapat $jadwalRapat): bool
    {
        return $authUser->can('Replicate:JadwalRapat');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JadwalRapat');
    }

}