<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\JadwalPelajaran;
use Illuminate\Auth\Access\HandlesAuthorization;

class JadwalPelajaranPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:JadwalPelajaran');
    }

    public function view(AuthUser $authUser, JadwalPelajaran $jadwalPelajaran): bool
    {
        return $authUser->can('View:JadwalPelajaran');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:JadwalPelajaran');
    }

    public function update(AuthUser $authUser, JadwalPelajaran $jadwalPelajaran): bool
    {
        return $authUser->can('Update:JadwalPelajaran');
    }

    public function delete(AuthUser $authUser, JadwalPelajaran $jadwalPelajaran): bool
    {
        return $authUser->can('Delete:JadwalPelajaran');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:JadwalPelajaran');
    }

    public function restore(AuthUser $authUser, JadwalPelajaran $jadwalPelajaran): bool
    {
        return $authUser->can('Restore:JadwalPelajaran');
    }

    public function forceDelete(AuthUser $authUser, JadwalPelajaran $jadwalPelajaran): bool
    {
        return $authUser->can('ForceDelete:JadwalPelajaran');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:JadwalPelajaran');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:JadwalPelajaran');
    }

    public function replicate(AuthUser $authUser, JadwalPelajaran $jadwalPelajaran): bool
    {
        return $authUser->can('Replicate:JadwalPelajaran');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:JadwalPelajaran');
    }

}