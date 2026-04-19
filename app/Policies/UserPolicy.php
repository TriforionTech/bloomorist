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
            return Response::deny('You cannot edit other Superadmins.');
        }

        // Superadmin boleh edit Admin biasa
        if ($user->is_super_admin) {
            return Response::allow();
        }

        // Admin biasa TIDAK BOLEH edit Super Admin
        if ($model->is_super_admin) {
            return Response::deny('Only Superadmin can edit this account.');
        }

        // Admin biasa hanya boleh edit dirinya sendiri
        if ($user->id !== $model->id) {
            return Response::deny('You can only edit your own account.');
        }

        return Response::allow();
    }
    
    // Digunakan jika ada user nakal yg delete manual melalui url
    public function delete(User $user, User $model): Response
    {
        // DILARANG KERAS menghapus diri sendiri (berlaku utk superadmin juga)
        if ($user->id === $model->id) {
            return Response::deny('You cannot delete your own account.');
        }

        // Hanya superadmin yang boleh delete
        if (! $user->is_super_admin) {
            return Response::deny('Only Superadmin can delete users.');
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

    public function restoreAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }
}
