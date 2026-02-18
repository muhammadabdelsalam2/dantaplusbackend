<?php

namespace App\Repositories\Auth;

use App\Factories\UserFactory;
use App\Models\User;

class AuthRepository
{
    /**
     *  DB Logic For Creation New User
     */
    public function createUser(array $data): User
    {
        return UserFactory::create($data);
    }
}
