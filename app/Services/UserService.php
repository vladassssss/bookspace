<?php

namespace App\Services;

use App\Repositories\IUserRepository;
use App\Models\User;

class UserService {
    private $userRepository;

    public function __construct(IUserRepository $userRepository) {
        $this->userRepository = $userRepository;
    }

    public function registerUser($username, $password, $email, $role) {
        // Перевірка обов'язкових полів
        if (empty(trim($username)) || empty(trim($password)) || empty(trim($email))) {
            throw new \Exception("Всі поля є обов'язковими");
        }
        // Перевірка на існування користувача
        $existingUser = $this->userRepository->getUserByUsername($username);
        if ($existingUser !== null) {
            throw new \Exception("Користувач з таким ім'ям вже існує");
        }
        // Хешування пароля (bcrypt)
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $userId = $this->userRepository->createUser($username, $hashedPassword, $email, $role);
        if (!$userId) {
            throw new \Exception("Реєстрація не вдалася");
        }
        return $userId;
    }

    public function loginUser($username, $password) {
        $user = $this->userRepository->getUserByUsername($username);
        if ($user === null) {
            throw new \Exception("Невірне ім'я користувача або пароль");
        }
        if (!password_verify($password, $user->getPassword())) {
            throw new \Exception("Невірне ім'я користувача або пароль");
        }
        return $user;
    }
    public function getAllUsers() {
        return $this->userRepository->getAllUsers(); // Виклик методу репозиторію
    }
    public function getUserCount() {
    return $this->userRepository->getUserCount(); // Виклик методу репозиторію
}
 public function getUserById(int $id): ?User // Change the return type here
    {
        return $this->userRepository->getUserById($id);
    }
}

