<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Address;

class AddressPolicy
{
    /**
     * Determine whether the user can update the address.
     */
    public function update(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }

    /**
     * Determine whether the user can delete the address.
     */
    public function delete(User $user, Address $address): bool
    {
        return $user->id === $address->user_id;
    }
}
