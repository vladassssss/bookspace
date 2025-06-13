<?php
namespace App\Repositories;

use App\Models\Review;
use PDO;
use PDOException;

class ReviewRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findReviewById(int $reviewId): ?\App\Models\Review
{
    try {
        $stmt = $this->db->prepare("
            SELECT
                r.id,
                r.book_id,
                r.user_id,
                u.username AS author_name,
                r.rating,          -- ДОДАНО ЦЕЙ РЯДОК
                r.review_text AS comment,
                r.created_at,
                r.updated_at
            FROM
                reviews AS r
            JOIN
                users AS u ON r.user_id = u.id
            WHERE
                r.id = :id
            LIMIT 1
        ");
        $stmt->bindParam(':id', $reviewId, PDO::PARAM_INT);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_OBJ);

        if ($data) {
            return new \App\Models\Review( // Переконайтеся, що ви використовуєте повне ім'я класу Review
                $data->id,
                $data->book_id,
                $data->user_id,
                $data->author_name,
                $data->rating,      // ПЕРЕДАЙТЕ ЗНАЧЕННЯ В КОНСТРУКТОР
                $data->comment,
                $data->created_at,
                $data->updated_at ?? null
            );
        }
        return null;
    } catch (PDOException $e) {
        error_log("ReviewRepository::findReviewById DB error: " . $e->getMessage());
        return null;
    }
}
    
    public function findByBookId(int $bookId): array
    {
        // Якщо ви використовуєте FETCH_CLASS, Review має бути сумісним.
        // Переконайтеся, що вибираєте user_name, якщо модель його очікує.
        $stmt = $this->db->prepare("SELECT id, book_id, user_name, rating, review_text AS comment, created_at, updated_at FROM reviews WHERE book_id = :book_id ORDER BY created_at DESC");
        $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_CLASS, Review::class); // Цей метод потребує, щоб Review::class міг бути інстанційований з цих колонок
    }
    
    public function findReviewsWithUsersByBookId(int $bookId): array
{
    try {
        $stmt = $this->db->prepare("
            SELECT
                r.id,
                r.book_id,
                r.user_id,
                u.username AS author_name,
                r.rating,          -- ДОДАНО ЦЕЙ РЯДОК
                r.review_text AS comment,
                r.created_at,
                r.updated_at
            FROM
                reviews AS r
            JOIN
                users AS u ON r.user_id = u.id
            WHERE
                r.book_id = :book_id
            ORDER BY
                r.created_at DESC
        ");
        $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
        $stmt->execute();

        $reviews = [];
        while ($data = $stmt->fetch(PDO::FETCH_OBJ)) {
            $reviews[] = new \App\Models\Review( // Переконайтеся, що ви використовуєте повне ім'я класу Review
                $data->id,
                $data->book_id,
                $data->user_id,
                $data->author_name,
                $data->rating,      // ПЕРЕДАЙТЕ ЗНАЧЕННЯ В КОНСТРУКТОР
                $data->comment,
                $data->created_at,
                $data->updated_at ?? null
            );
        }
        return $reviews;

    } catch (PDOException $e) {
        error_log("ReviewRepository::findReviewsWithUsersByBookId DB error: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
        return [];
    }
}
   // ...
// app\Repositories\ReviewRepository.php
  public function save(Review $review): bool // <--- ЗМІНЕНО СИГНАТУРУ
    {
        try {
            // ЗМІНА ТУТ: SQL-запит для вставки user_id замість user_name
            // (ЦЕЙ SQL-ЗАПИТ ВЖЕ ПРАВИЛЬНИЙ, якщо user_id є в таблиці reviews)
            $stmt = $this->db->prepare(
                "INSERT INTO reviews (book_id, user_id, rating, review_text, created_at)
                 VALUES (:book_id, :user_id, :rating, :review_text, NOW())"
            );

            // Отримуємо дані з об'єкта Review
            $bookId = $review->getBookId();
            $userId = $review->getUserId(); // !!! ПЕРЕКОНАЙТЕСЯ, ЩО В Review Є getUserID() !!!
            $rating = $review->getRating();
            $comment = $review->getReviewText(); // Або getComment(), залежить від вашої моделі

            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':rating', $rating, PDO::PARAM_INT);
            $stmt->bindParam(':review_text', $comment, PDO::PARAM_STR);

            $executed = $stmt->execute();

            if (!$executed) {
                error_log("ReviewRepository: SQL execute failed. ErrorInfo: " . print_r($stmt->errorInfo(), true));
            }
            return $executed;

        } catch (PDOException $e) {
            error_log("ReviewRepository::save caught PDOException: " . $e->getMessage() . " SQLSTATE: " . $e->getCode() . " on line " . $e->getLine() . " in " . $e->getFile());
            // Для логування параметрів, можливо, доведеться витягти їх з об'єкта Review
            error_log("ReviewRepository: Failed to save review object: " . print_r($review, true));
            return false;
        }
    }

// ...
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM reviews WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();
            if (!$result) {
                error_log("ReviewRepository::delete - SQL Error: " . print_r($stmt->errorInfo(), true));
            }
            return $result;
        } catch (PDOException $e) {
            error_log("ReviewRepository::delete DB error: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            return false;
        }
    }

    // ЗМІНЕНО: addReview тепер приймає userName замість userId
    public function addReview(int $bookId, string $userName, int $rating, string $comment): bool { // ЗМІНЕНО: $userId на $userName
        try {
            // ЗМІНЕНО: SQL-запит для вставки user_name
            $sql = "INSERT INTO reviews (book_id, user_name, rating, review_text, created_at) VALUES (:book_id, :user_name, :rating, :review_text, NOW())";
            $stmt = $this->db->prepare($sql);

            $bindBookId = $bookId;
            $bindUserName = $userName; // ЗМІНЕНО: userName
            $bindRating = $rating;
            $bindComment = $comment;

            $stmt->bindParam(':book_id', $bindBookId, PDO::PARAM_INT);
            $stmt->bindParam(':user_name', $bindUserName, PDO::PARAM_STR); // ЗМІНЕНО
            $stmt->bindParam(':rating', $bindRating, PDO::PARAM_INT);
            $stmt->bindParam(':review_text', $bindComment, PDO::PARAM_STR);

            $result = $stmt->execute();

            if (!$result) {
                error_log("ReviewRepository::addReview - SQL Error: " . print_r($stmt->errorInfo(), true));
            }
            return $result;
        } catch (PDOException $e) {
            error_log("ReviewRepository::addReview DB error: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            return false;
        }
    }

    // ЗМІНЕНО: updateReview тепер приймає userName
    public function updateReview(int $reviewId, string $userName, int $rating, string $comment): bool {
        try {
            // Оскільки reviews.user_id не існує, вам потрібно буде оновлювати за user_name,
            // якщо ви хочете перевіряти, що користувач оновлює свій відгук.
            // Якщо у вашій таблиці `reviews` немає user_id, то ця логіка не буде працювати.
            // Якщо ж `reviews` має стовпець `user_name`, то можна використовувати його.
            // Я припускаю, що reviews має `user_name`.
            $sql = "UPDATE reviews SET rating = :rating, review_text = :review_text, updated_at = NOW() WHERE id = :review_id AND user_name = :user_name"; // ЗМІНЕНО: user_id на user_name
            $stmt = $this->db->prepare($sql);

            $bindReviewId = $reviewId;
            $bindRating = $rating;
            $bindComment = $comment;
            $bindUserNameForUpdate = $userName; // ЗМІНЕНО

            $stmt->bindParam(':rating', $bindRating, PDO::PARAM_INT);
            $stmt->bindParam(':review_text', $bindComment, PDO::PARAM_STR);
            $stmt->bindParam(':review_id', $bindReviewId, PDO::PARAM_INT);
            $stmt->bindParam(':user_name', $bindUserNameForUpdate, PDO::PARAM_STR); // ЗМІНЕНО

            $result = $stmt->execute();

            if (!$result) {
                error_log("ReviewRepository::updateReview - SQL Error: " . print_r($stmt->errorInfo(), true));
            }
            return $result;
        } catch (PDOException $e) {
            error_log("ReviewRepository::updateReview DB error: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            return false;
        }
    }
}