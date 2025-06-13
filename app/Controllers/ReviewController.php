<?php
namespace App\Controllers;

use App\Services\ReviewService;
use App\Models\User; // Якщо вам потрібен доступ до моделі User
use App\Repositories\UserRepository; // Якщо вам потрібен UserRepository тут

class ReviewController
{
    private ReviewService $reviewService;
    // private UserRepository $userRepository; // Можливо, вам потрібен UserRepository тут

    public function __construct(ReviewService $reviewService /* , UserRepository $userRepository */)
    {
        $this->reviewService = $reviewService;
        // $this->userRepository = $userRepository;
    }

    public function addReview(int $bookId, int $userId, int $rating, string $comment): bool
    {
        // ОТРИМУЄМО USERNAME ТУТ, ПЕРЕД ТИМ, ЯК ПЕРЕДАВАТИ В СЕРВІС
        // Цей userName, як ви бачите, вже не потрібен в параметрах addReview сервісу.
        // Сервіс сам визначає userName за $userId, який ви йому передаєте.
        // $userName = $_SESSION['user_name'] ?? 'Анонім'; 
        // АБО: якщо у вас єUserRepository з методом findByUsername або подібним:
        // $user = $this->userRepository->find($userId); // Якщо такий метод існує і працює
        // $userName = $user ? $user->getUsername() : 'Анонім';

        // Викликаємо метод addReview сервісу, передаючи bookId, userId, rating та comment.
        // Зверніть увагу на порядок аргументів.
        return $this->reviewService->addReview($bookId, $userId, $rating, $comment); 
    }


    public function deleteReview(int $reviewId, string $authorName): bool
    {
        return $this->reviewService->deleteReview($reviewId, $authorName);
    }

    public function updateReview(int $reviewId, string $authorName, int $rating, string $comment): bool
    {
        return $this->reviewService->updateReview($reviewId, $authorName, $rating, $comment);
    }
    public function fetchReviewsWithUsers(int $bookId): array
    {
        return $this->reviewService->fetchReviewsWithUsers($bookId);
    }
}