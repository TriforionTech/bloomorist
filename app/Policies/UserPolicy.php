<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        // Hanya Super Admin yang boleh buat user baru
        return $user->is_super_admin;
    }

    // Digunakan jika ada user nakal yg update manual melalui url
    public function update(User $user, User $model): Response
    {
        // Superadmin boleh edit dirinya sendiri
        if ($user->is_super_admin && $user->id === $model->id) {
            return Response::allow();
        }

        // Superadmin TIDAK boleh edit Superadmin lain
        if ($user->is_super_admin && $model->is_super_admin) {
            return Response::deny('Anda tidak dapat mengedit Superadmin lain.');
        }

        // Superadmin boleh edit Admin biasa
        if ($user->is_super_admin) {
            return Response::allow();
        }

        // Admin biasa TIDAK BOLEH edit Super Admin
        if ($model->is_super_admin) {
            return Response::deny('Hanya Superadmin yang dapat mengedit akun ini.');
        }

        // Admin biasa hanya boleh edit dirinya sendiri
        if ($user->id !== $model->id) {
            return Response::deny('Anda hanya dapat mengedit akun Anda sendiri.');
        }

        return Response::allow();
    }

    // Digunakan jika ada user nakal yg delete manual melalui url
    public function delete(User $user, User $model): Response
    {
        // Tidak boleh menghapus diri sendiri (berlaku utk superadmin juga)
        if ($user->id === $model->id) {
            return Response::deny('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        // Hanya superadmin yang boleh delete
        if (! $user->is_super_admin) {
            return Response::deny('Hanya Superadmin yang dapat menghapus pengguna.');
        }

        // // Superadmin tidak boleh delete superadmin lain
        // if ($model->is_super_admin) {
        //     return Response::deny('You cannot delete other Superadmins.');
        // }

        return Response::allow();
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function restore(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function restoreAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }
}
