<?php

namespace App\Services;

use App\Repositories\ProfileRepository;

class ProfileService
{
    private $profileRepository;

    public function __construct(ProfileRepository $profileRepository)
    {
        $this->profileRepository = $profileRepository;
    }

    public function getUserProfileData(int $userId): array
    {
        $orderedBooks = $this->profileRepository->getUserOrderedBooks($userId);
        $genreStats = $this->profileRepository->getGenreOrderStatistics($userId);
        $favoriteBooks = $this->profileRepository->getUserFavoriteBooks($userId);

        return [
            'ordered_books' => $orderedBooks,
            'genre_stats' => $genreStats,
            'favorite_books' => $favoriteBooks
        ];
    }
    
    public function addFavoriteBook(int $userId, int $bookId): bool
    {
        return $this->profileRepository->addFavoriteBook($userId, $bookId);
    }

    public function removeFavoriteBook(int $userId, int $bookId): bool
    {
        return $this->profileRepository->removeFavoriteBook($userId, $bookId);
    }

    public function isFavoriteBook(int $userId, int $bookId): bool
    {
        return $this->profileRepository->isFavoriteBook($userId, $bookId);
    }
}