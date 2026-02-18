<?php

namespace App\Factories;

use App\Models\User;

class UserFactory
{
    public static function create(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }
}
