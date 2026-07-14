<?php

namespace App\Policies;

use App\Models\DeliveryZone;
use App\Models\User;

class DeliveryZonePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return ($user->isAdmin() || $user->isStaff()) && $deliveryZone->orders()->doesntExist();
    }
}
