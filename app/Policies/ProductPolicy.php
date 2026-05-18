<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function update(User $user, Product $product): Response
    {
        return Response::allow();
    }

    public function delete(User $user, Product $product): Response
    {
        if ($user->is_super_admin) {
            return Response::allow();
        }

        return Response::deny('Only superadmin can delete this product.');
    }

    public function deleteAny(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function restore(User $user, Product $product): bool
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
