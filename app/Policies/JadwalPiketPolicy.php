<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JadwalPiket;
use Illuminate\Auth\Access\HandlesAuthorization;

class JadwalPiketPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JadwalPiket');
    }

    public function view(AuthUser $authUser, JadwalPiket $jadwalPiket): bool
    {
        return $authUser->can('View:JadwalPiket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JadwalPiket');
    }

    public function update(AuthUser $authUser, JadwalPiket $jadwalPiket): bool
    {
        return $authUser->can('Update:JadwalPiket');
    }

    public function delete(AuthUser $authUser, JadwalPiket $jadwalPiket): bool
    {
        return $authUser->can('Delete:JadwalPiket');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JadwalPiket');
    }

    public function restore(AuthUser $authUser, JadwalPiket $jadwalPiket): bool
    {
        return $authUser->can('Restore:JadwalPiket');
    }

    public function forceDelete(AuthUser $authUser, JadwalPiket $jadwalPiket): bool
    {
        return $authUser->can('ForceDelete:JadwalPiket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JadwalPiket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JadwalPiket');
    }

    public function replicate(AuthUser $authUser, JadwalPiket $jadwalPiket): bool
    {
        return $authUser->can('Replicate:JadwalPiket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JadwalPiket');
    }

}