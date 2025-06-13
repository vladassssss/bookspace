<?php

namespace App\Controllers;

use App\Services\ProfileService;

class ProfileController
{
    private $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function getUserProfileData(int $userId): array
    {
        return $this->profileService->getUserProfileData($userId);
    }

    public function addFavoriteBook(int $userId, int $bookId): array
    {
        if ($this->profileService->isFavoriteBook($userId, $bookId)) {
            return ['success' => false, 'message' => 'Книга вже є у вашому списку бажань.'];
        }
        $success = $this->profileService->addFavoriteBook($userId, $bookId);
        return ['success' => $success];
    }

    public function removeFavoriteBook(int $userId, int $bookId): array
    {
        $success = $this->profileService->removeFavoriteBook($userId, $bookId);
        return ['success' => $success];
    }
}