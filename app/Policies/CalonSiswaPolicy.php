<?php

namespace App\Policies;

use App\Models\CalonSiswa;
use App\Models\User;

class CalonSiswaPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->can('ViewAny:CalonSiswa');
    }

    public function view(User $user, CalonSiswa $calonSiswa): bool
    {
        return $this->isAdmin($user) || $user->can('View:CalonSiswa');
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user) || $user->can('Create:CalonSiswa');
    }

    public function update(User $user, CalonSiswa $calonSiswa): bool
    {
        return $this->isAdmin($user) || $user->can('Update:CalonSiswa');
    }

    public function delete(User $user, CalonSiswa $calonSiswa): bool
    {
        return $this->isAdmin($user) || $user->can('Delete:CalonSiswa');
    }

    public function deleteAny(User $user): bool
    {
        return $this->isAdmin($user) || $user->can('DeleteAny:CalonSiswa');
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(['admin', config('filament-shield.super_admin.name', 'super_admin')]);
    }
}
