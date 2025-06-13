<?php
namespace App\Services;

use App\Models\Review;
use App\Repositories\UserRepository;
use App\Repositories\ReviewRepository;
// use App\Repositories\UserRepository; // Більше не потрібен, якщо userName передається з контролера
use Exception;

class ReviewService
{
    private ReviewRepository $reviewRepository;
    private $userRepository;
    // private UserRepository $userRepository; // ВИДАЛИТИ, якщо не використовується

    public function __construct(ReviewRepository $reviewRepository , UserRepository $userRepository )
    {
        $this->reviewRepository = $reviewRepository;
        $this->userRepository = $userRepository; 
        // $this->userRepository = $userRepository; // ВИДАЛИТИ
    }

    // ЗМІНЕНО: addReview тепер приймає $userName (як ми вирішили раніше)
  // ...
// app\Services\ReviewService.php
// app\Services\ReviewService.php
  public function addReview(int $bookId, int $userId, int $rating, string $comment): bool
    {
        error_log("ReviewService: Attempting to add review for bookId: $bookId, userId: $userId, rating: $rating, comment: $comment");

        // Цей рядок викликав помилку, бо $this->userRepository був null
        $user = $this->userRepository->getUserById($userId);
        $userName = $user ? $user->getUsername() : 'Анонім';

        $review = new Review(
            null,
            $bookId,
            $userId,
            $userName, // Цей параметр потрібен, якщо ваш конструктор Review його очікує
            $rating,
            $comment,
            date('Y-m-d H:i:s')
        );

        $result = $this->reviewRepository->save($review);

        return $result;
    }
    public function getReviewById(int $reviewId): ?Review
    {
        return $this->reviewRepository->find($reviewId);
    }

    public function fetchReviewsWithUsers(int $bookId): array
    {
        return $this->reviewRepository->findReviewsWithUsersByBookId($bookId);
    }

    public function deleteReview(int $reviewId, string $userName): bool
    {
        // Якщо Review модель не має userId, то перевірка за userName буде такою:
        $review = $this->reviewRepository->findReviewById($reviewId);
        if (!$review || $review->getUserName() !== $userName) {
            return false;
        }
        return $this->reviewRepository->delete($reviewId);
    }

    public function updateReview(int $reviewId, string $userName, int $rating, string $comment): bool
    {
        return $this->reviewRepository->updateReview($reviewId, $userName, $rating, $comment);
    }
}