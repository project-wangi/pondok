<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JadwalPeminatan;
use Illuminate\Auth\Access\HandlesAuthorization;

class JadwalPeminatanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JadwalPeminatan');
    }

    public function view(AuthUser $authUser, JadwalPeminatan $jadwalPeminatan): bool
    {
        return $authUser->can('View:JadwalPeminatan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JadwalPeminatan');
    }

    public function update(AuthUser $authUser, JadwalPeminatan $jadwalPeminatan): bool
    {
        return $authUser->can('Update:JadwalPeminatan');
    }

    public function delete(AuthUser $authUser, JadwalPeminatan $jadwalPeminatan): bool
    {
        return $authUser->can('Delete:JadwalPeminatan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JadwalPeminatan');
    }

    public function restore(AuthUser $authUser, JadwalPeminatan $jadwalPeminatan): bool
    {
        return $authUser->can('Restore:JadwalPeminatan');
    }

    public function forceDelete(AuthUser $authUser, JadwalPeminatan $jadwalPeminatan): bool
    {
        return $authUser->can('ForceDelete:JadwalPeminatan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JadwalPeminatan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JadwalPeminatan');
    }

    public function replicate(AuthUser $authUser, JadwalPeminatan $jadwalPeminatan): bool
    {
        return $authUser->can('Replicate:JadwalPeminatan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JadwalPeminatan');
    }

}