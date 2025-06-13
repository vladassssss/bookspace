<?php

namespace App\Services;

use App\Models\Rating;
use App\Repositories\IRatingRepository; // Переконайтеся, що цей інтерфейс існує

class RatingService implements IRatingService { // Переконайтеся, що IRatingService існує та оголошує всі ці методи
    private IRatingRepository $ratingRepository;

    public function __construct(IRatingRepository $ratingRepository) {
        $this->ratingRepository = $ratingRepository;
    }

    /**
     * Додає або оновлює оцінку книги. Цей метод використовується для збереження оцінки користувача.
     * Він замінює попередній `rateBook` та `addOrUpdateRating` для консистентності.
     * @param int $bookId ID книги.
     * @param int $userId ID користувача.
     * @param int $ratingValue Оцінка (1-5).
     * @return bool True, якщо успішно.
     */

    public function addOrUpdateRating(int $bookId, int $userId, int $ratingValue): bool
    {
        error_log("RatingService::addOrUpdateRating() called for BookId: $bookId, UserId: $userId, Rating: $ratingValue");
        try {
            $result = $this->ratingRepository->saveRating($bookId, $userId, $ratingValue);
            error_log("RatingService::addOrUpdateRating() - result from saveRating: " . ($result ? 'true' : 'false'));
            return $result;
        } catch (Exception $e) {
            error_log("RatingService::addOrUpdateRating error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
            return false;
        }
    }

    /**
     * Отримує об'єкт оцінки користувача для конкретної книги.
     * @param int $bookId ID книги.
     * @param int $userId ID користувача.
     * @return Rating|null Об'єкт оцінки, якщо знайдено, або null.
     */
    public function getRatingByBookAndUser(int $bookId, int $userId): ?Rating {
        return $this->ratingRepository->findRatingByBookAndUser($bookId, $userId);
    }

    /**
     * Отримує середній рейтинг для книги.
     * @param int $bookId ID книги.
     * @return float|null Середній рейтинг або null.
     */
    public function getBookAverageRating(int $bookId): ?float {
        return $this->ratingRepository->getAverageRatingForBook($bookId);
    }

    /**
     * Отримує числове значення оцінки користувача для книги.
     * Цей метод може бути видалений, якщо getRatingByBookAndUser достатньо.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @return int|null Оцінка або null.
     */
    public function getUserRatingForBook(int $userId, int $bookId): ?int {
        // Якщо getRatingByBookAndUser вже повертає об'єкт Rating,
        // то можна використати його:
        $rating = $this->getRatingByBookAndUser($bookId, $userId);
        return $rating ? $rating->getRating() : null;

        // Або, якщо ви хочете, щоб репозиторій робив це напряму:
        // return $this->ratingRepository->getUserRatingForBook($userId, $bookId);
    }
}