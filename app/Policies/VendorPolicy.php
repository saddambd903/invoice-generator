<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Access\Response;

/** Every user sees and changes only their own vendors. */
class VendorPolicy
{
    /** Owner, or a super admin (read-only: update/delete stay owner-only). */
    public function view(User $user, Vendor $vendor): bool
    {
        return $vendor->user_id === $user->id || $user->isSuperAdmin();
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $vendor->user_id === $user->id;
    }

    /** A vendor can only be deleted while they have no invoices. */
    public function delete(User $user, Vendor $vendor): Response
    {
        if ($vendor->user_id !== $user->id) {
            return Response::deny();
        }

        return $vendor->invoices()->exists()
            ? Response::deny("{$vendor->name} has invoices, so they can't be deleted.")
            : Response::allow();
    }
}
