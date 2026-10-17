<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SiteSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'ViewAny');
    }

    public function view(User $user, SiteSetting $siteSetting): bool
    {
        return (int) $siteSetting->getKey() === 1 && $this->allowed($user, 'View');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'Create') && ! SiteSetting::query()->exists();
    }

    public function update(User $user, SiteSetting $siteSetting): bool
    {
        return (int) $siteSetting->getKey() === 1 && $this->allowed($user, 'Update');
    }

    public function delete(User $user, SiteSetting $siteSetting): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, SiteSetting $siteSetting): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, SiteSetting $siteSetting): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, SiteSetting $siteSetting): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function allowed(User $user, string $ability): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin'])
            || $user->checkPermissionTo($ability.':SiteSetting');
    }
}
