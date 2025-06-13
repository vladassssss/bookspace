<?php
namespace App\Repositories;

interface IReviewRepository {
    // Якщо працюємо глобально (без book_id), можна додати цей метод:
    public function getAllReviews();

    // Для відгуків по конкретній сутності (якщо потрібно)
    public function getReviewsByBookId($bookId);

    public function addReview($bookId, $userName, $reviewText, $rating);
}
