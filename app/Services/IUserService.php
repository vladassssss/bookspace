<?php
namespace App\Services;

use App\Models\User;

interface IUserService
{
    public function getUserById(int $id);
    public function getUserByEmail(string $email);
    public function createUser(string $username, string $email, string $password);
    // Додайте оголошення інших методів сервісу
}
