<?php
namespace App\Repositories;

use PDO;
use PDOException;
use App\Models\Rating; // Переконайтеся, що ви імпортуєте модель Rating, якщо вона використовується в цьому файлі

// Припускається, що у вас є інтерфейс IRatingRepository
use App\Repositories\IRatingRepository; // Якщо інтерфейс є, розкоментуйте цей рядок

class RatingRepository implements IRatingRepository // Якщо інтерфейс є, розкоментуйте цей рядок
{
    private PDO $connection; // Оголошення властивості для зберігання PDO з'єднання

    public function __construct(PDO $connection)
    {
        // Логування для діагностики (можна прибрати після виправлення)
        error_log("RatingRepository::__construct() ENTERED. Type of received \$connection parameter: " . gettype($connection) . (is_object($connection) ? " Class: " . get_class($connection) : ""));

        // Присвоєння об'єкта PDO, переданого через конструктор, властивості класу
        $this->connection = $connection;

        // Логування для діагностики (можна прибрати після виправлення)
        error_log("RatingRepository::__construct() EXITED. Type of \$this->connection after assignment: " . gettype($this->connection) . (is_object($this->connection) ? " Class: " . get_class($this->connection) : ""));
    }

    /**
     * Знаходить існуючу оцінку книги користувачем.
     */
    public function findRatingByBookAndUser(int $bookId, int $userId): ?Rating
    {
        error_log("RatingRepository::findRatingByBookAndUser() ENTERED. Searching for bookId: $bookId, userId: $userId.");
        try {
            // Використовуємо $this->connection для підготовки запиту
            $stmt = $this->connection->prepare("SELECT id, book_id, user_id, rating, created_at, updated_at FROM ratings WHERE book_id = :book_id AND user_id = :user_id");
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                error_log("RatingRepository::findRatingByBookAndUser() - Found rating. ID: " . $data['id'] . ", Rating: " . $data['rating']);
                return new Rating(
                    $data['id'],
                    $data['book_id'],
                    $data['user_id'],
                    $data['rating'],
                    $data['created_at'],
                    $data['updated_at'] ?? null
                );
            } else {
                error_log("RatingRepository::findRatingByBookAndUser() - No rating found for bookId: $bookId, userId: $userId.");
            }
        } catch (PDOException $e) {
            error_log("RatingRepository::findRatingByBookAndUser DB error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Створює нову оцінку для книги.
     */
    public function createRating(int $bookId, int $userId, int $ratingValue): bool
    {
        error_log("RatingRepository::createRating() - Attempting to create new rating for bookId: $bookId, userId: $userId, rating: $ratingValue");
        try {
            $sql = "INSERT INTO ratings (book_id, user_id, rating, created_at) VALUES (:book_id, :user_id, :rating, NOW())";
            // Використовуємо $this->connection для підготовки запиту
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':rating', $ratingValue, PDO::PARAM_INT);
            $result = $stmt->execute();
            if (!$result) {
                error_log("RatingRepository::createRating - SQL execute failed: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("RatingRepository::createRating - Successfully created. Rows affected: " . $stmt->rowCount());
            }
            return $result;
        } catch (PDOException $e) {
            error_log("RatingRepository::createRating caught PDOException: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            return false;
        }
    }

    /**
     * Оновлює існуючу оцінку.
     */
    public function updateRating(int $ratingId, int $ratingValue): bool
    {
        error_log("RatingRepository::updateRating() - Attempting to update rating ID: $ratingId to value: $ratingValue.");
        try {
            $sql = "UPDATE ratings SET rating = :rating, updated_at = NOW() WHERE id = :id";
            // Використовуємо $this->connection для підготовки запиту
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':rating', $ratingValue, PDO::PARAM_INT);
            $stmt->bindParam(':id', $ratingId, PDO::PARAM_INT);
            $result = $stmt->execute();
            if (!$result) {
                error_log("RatingRepository::updateRating - SQL execute failed: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("RatingRepository::updateRating - Successfully updated. Rows affected: " . $stmt->rowCount());
            }
            return $result;
        } catch (PDOException $e) {
            error_log("RatingRepository::updateRating caught PDOException: " . $e->getMessage() . " SQLSTATE: " . $e->getCode());
            return false;
        }
    }

    /**
     * Зберігає або оновлює оцінку книги користувачем.
     */
    public function saveRating(int $bookId, int $userId, int $ratingValue): bool
    {
        error_log("RatingRepository::saveRating() called for BookId: $bookId, UserId: $userId, RatingValue: $ratingValue");
        $existingRating = $this->findRatingByBookAndUser($bookId, $userId);

        if ($existingRating) {
            error_log("RatingRepository: Found existing rating ID: " . $existingRating->getId() . ". Attempting to UPDATE rating for bookId: $bookId, userId: $userId to $ratingValue.");
            $result = $this->updateRating($existingRating->getId(), $ratingValue);
            error_log("RatingRepository: updateRating result: " . ($result ? 'true' : 'false'));
            return $result;
        } else {
            error_log("RatingRepository: No existing rating found. Attempting to CREATE new rating for bookId: $bookId, userId: $userId with $ratingValue.");
            $result = $this->createRating($bookId, $userId, $ratingValue);
            error_log("RatingRepository: createRating result: " . ($result ? 'true' : 'false'));
            return $result;
        }
    }

    /**
     * Отримує середній рейтинг для конкретної книги з таблиці 'reviews'.
     */
    public function getAverageRatingForBook(int $bookId): ?float
    {
        // Змінено: використовуємо 'reviews' для розрахунку середнього рейтингу
        $sql = "SELECT AVG(rating) FROM reviews WHERE book_id = :book_id";
        try {
            // Використовуємо $this->connection для підготовки запиту
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchColumn();
            // Повертаємо float або null, якщо результат false (немає відгуків)
            return $result !== false ? (float)$result : null;
        } catch (PDOException $e) {
            error_log("RatingRepository::getAverageRatingForBook DB error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отримує оцінку, яку поставив поточний користувач для конкретної книги.
     */
    public function getUserRatingForBook(int $userId, int $bookId): ?int {
        try {
            // Використовуємо $this->connection для підготовки запиту
            $stmt = $this->connection->prepare("SELECT rating FROM ratings WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['rating'])) {
                return (int) $result['rating'];
            }
            return null;
        } catch (PDOException $e) {
            error_log("RatingRepository::getUserRatingForBook DB error: " . $e->getMessage());
            return null;
        }
    }
}