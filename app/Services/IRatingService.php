<?php
namespace App\Services;

use App\Models\Rating; // Не забудьте імпортувати модель Rating

interface IRatingService {
    public function addOrUpdateRating(int $bookId, int $userId, int $ratingValue): bool;
    public function getRatingByBookAndUser(int $bookId, int $userId): ?Rating;
    public function getBookAverageRating(int $bookId): ?float;
    public function getUserRatingForBook(int $userId, int $bookId): ?int; // Опціонально, якщо ви вирішили його залишити
}