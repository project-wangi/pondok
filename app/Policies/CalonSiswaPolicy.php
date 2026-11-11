<?php

namespace App\Policies;

use App\Models\CalonSiswa;
use App\Models\User;

/**
 * Akses calon siswa sepenuhnya mengikuti izin Shield (menu Role & Permission).
 */
class CalonSiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:CalonSiswa');
    }

    public function view(User $user, CalonSiswa $calonSiswa): bool
    {
        return $user->can('View:CalonSiswa');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:CalonSiswa');
    }

    public function update(User $user, CalonSiswa $calonSiswa): bool
    {
        return $user->can('Update:CalonSiswa');
    }

    public function delete(User $user, CalonSiswa $calonSiswa): bool
    {
        return $user->can('Delete:CalonSiswa');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:CalonSiswa');
    }
}
