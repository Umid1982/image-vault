<?php

namespace App\Repositories;

use App\Models\User;

class UserRepository
{
    /**
     * @param array $userData
     * @return User
     */
    public function createAuthUser(array $userData): User
    {
        return User::query()->create($userData);
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }
}
