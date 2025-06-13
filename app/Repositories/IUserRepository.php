<?php

namespace App\Repositories;

use App\Models\User; // Переконайтеся, що ви використовуєте модель User

interface IUserRepository
{
    public function createUser($username, $hashedPassword, $email, $role);

    public function getUserByUsername($username);

    public function getAllUsers();

    public function getUserCount();

    public function getUserById(int $id): ?User;
}