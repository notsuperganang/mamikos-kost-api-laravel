<?php

namespace App\Policies;

use App\Models\Kost;
use App\Models\User;

class KostPolicy
{
    public function update(User $user, Kost $kost): bool
    {
        return $user->isOwner() && $kost->isOwnedBy($user);
    }

    public function delete(User $user, Kost $kost): bool
    {
        return $user->isOwner() && $kost->isOwnedBy($user);
    }
}
