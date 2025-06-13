<?php

namespace App\Repositories;

use PDO;
use App\Models\WishlistItem; // Можливо, вам більше не потрібен WishlistItem тут, якщо повертаємо Book
use App\Models\Book; // Обов'язково додайте це!
use PDOException;

class WishlistRepository implements IWishlistRepository
{
    private $connection;
    private $bookstoreRepository; // Зберігаємо це, якщо воно використовується для addItem

    public function __construct(PDO $connection, IBookstoreRepository $bookstoreRepository)
    {
        $this->connection = $connection;
        $this->bookstoreRepository = $bookstoreRepository;
    }

    /**
     * Повертає список улюблених книг для користувача як масив об'єктів Book.
     *
     * @param int $userId ID користувача
     * @return array Масив об'єктів App\Models\Book
     */
    public function getUserWishlist(int $userId): array
    {
        error_log("WishlistRepository::getUserWishlist - Fetching books for user ID: " . $userId);
        try {
            $sql = "
                SELECT 
                    b.id, 
                    b.title, 
                    b.author, 
                    b.genre,        -- Залишаємо genre, оскільки ви сказали, що воно містить назву жанру
                    b.price, 
                    b.cover_image, 
                    b.description,  -- Додаємо description сюди
                    b.language,     -- Додайте, якщо вони є у вашій таблиці bookshop_book
                    b.popularity,
                    b.discount,
                    b.quantity
                    -- Додайте інші поля, якщо вони є у вашій таблиці bookshop_book
                    -- і ви хочете їх використовувати в конструкторі Book
                FROM wishlist wl
                JOIN bookshop_book b ON wl.book_id = b.id 
                WHERE wl.user_id = :user_id
                ORDER BY b.title ASC
            ";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $books = [];
            foreach ($booksData as $bookData) {
                // ПЕРЕКОНАЙТЕСЯ, ЩО ПОРЯДОК АРГУМЕНТІВ ВІДПОВІДАЄ ВАШОМУ КОНСТРУКТОРУ App\Models\Book
                $books[] = new Book(
                    $bookData['id'],
                    $bookData['title'],
                    $bookData['author'],
                    $bookData['genre'],        // <--- ТЕПЕР ЖАНР ПЕРЕДАЄТЬСЯ НА 4-Е МІСЦЕ
                    $bookData['price'],
                    $bookData['cover_image'],
                    $bookData['description'],  // <--- ОПИС ПЕРЕДАЄТЬСЯ НА 7-Е МІСЦЕ
                    $bookData['language'] ?? null, // Передайте значення, якщо воно є у $bookData, інакше null
                    $bookData['popularity'] ?? null,
                    $bookData['discount'] ?? null,
                    $bookData['quantity'] ?? null
                    // Додайте тут інші аргументи, якщо вони є в конструкторі Book
                    // Передайте також moodsList, якщо ви його вибираєте з БД або він ініціалізується порожнім масивом
                    // array_key_exists('moods_list', $bookData) ? json_decode($bookData['moods_list'], true) : []
                );
            }
            error_log("WishlistRepository::getUserWishlist - Found " . count($books) . " books.");
            return $books;
        } catch (PDOException $e) {
            error_log("WishlistRepository::getUserWishlist - Database error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            return [];
        }
    }
    /**
     * Додає книгу до списку бажань користувача.
     * Повертає WishlistItem, якщо успішно, або null, якщо книга вже є або сталася помилка.
     *
     * @param int $userId ID користувача
     * @param int $bookId ID книги
     * @return WishlistItem|null
     */
    public function addItem(int $userId, int $bookId): ?WishlistItem
    {
        error_log("WishlistRepository::addItem - Attempting to add book ID: " . $bookId . " for user ID: " . $userId);
        try {
            // Перевіряємо, чи існує книга (використовуємо BookstoreRepository)
            $book = $this->bookstoreRepository->getBookById($bookId);
            if (!$book instanceof Book) {
                error_log("WishlistRepository::addItem - Book with ID " . $bookId . " does not exist. Cannot add to wishlist.");
                return null;
            }
            error_log("WishlistRepository::addItem - Book exists: " . $book->getTitle());

            // Перевіряємо, чи книга вже є у вішлисті
            if ($this->isBookInWishlist($userId, $bookId)) {
                error_log("WishlistRepository::addItem - Book ID " . $bookId . " already in wishlist for user ID: " . $userId);
                return null; // Книга вже в списку бажань
            }

            $stmt = $this->connection->prepare("INSERT INTO wishlist (user_id, book_id) VALUES (:user_id, :book_id)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $executed = $stmt->execute();

            error_log("WishlistRepository::addItem - Insert query executed: " . ($executed ? 'TRUE' : 'FALSE'));

            if ($executed) {
                $itemId = $this->connection->lastInsertId();
                error_log("WishlistRepository::addItem - Last Insert ID: " . $itemId);
                if ($itemId > 0) {
                    // Тепер повертаємо WishlistItem, який містить об'єкт Book
                    return new WishlistItem($itemId, $userId, $bookId, $book);
                } else {
                    error_log("WishlistRepository::addItem - Insert executed, but lastInsertId returned 0. Check 'wishlist' table auto-increment.");
                    return null;
                }
            } else {
                error_log("WishlistRepository::addItem - Insert query failed for unknown reason.");
                error_log(print_r($stmt->errorInfo(), true)); // Виводимо детальну помилку PDO
                return null;
            }
        } catch (PDOException $e) {
            error_log("WishlistRepository::addItem - Database error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            return null;
        }
    }

    /**
     * Видаляє елемент зі списку бажань за ID елемента списку.
     *
     * @param int $itemId ID елемента списку бажань
     * @return bool Успіх операції
     */
    public function removeItem(int $itemId): bool
    {
        error_log("WishlistRepository::removeItem - Attempting to remove wishlist item ID: " . $itemId);
        try {
            $stmt = $this->connection->prepare("DELETE FROM wishlist WHERE id = :id");
            $stmt->bindParam(':id', $itemId, PDO::PARAM_INT);
            $executed = $stmt->execute();
            error_log("WishlistRepository::removeItem - Delete by item ID query executed: " . ($executed ? 'TRUE' : 'FALSE') . ", Rows affected: " . $stmt->rowCount());
            return $executed;
        } catch (PDOException $e) {
            error_log("WishlistRepository::removeItem - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Перевіряє, чи книга є у списку бажань користувача.
     *
     * @param int $userId ID користувача
     * @param int $bookId ID книги
     * @return bool True, якщо книга у списку, false в іншому випадку
     */
    public function isBookInWishlist(int $userId, int $bookId): bool
    {
        error_log("WishlistRepository::isBookInWishlist - Checking if book ID: " . $bookId . " is in wishlist for user ID: " . $userId);
        try {
            $stmt = $this->connection->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            error_log("WishlistRepository::isBookInWishlist - Count for book " . $bookId . " for user " . $userId . ": " . $count);
            return $count > 0;
        } catch (PDOException $e) {
            error_log("WishlistRepository::isBookInWishlist - Database error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Видаляє книгу зі списку бажань користувача за ID книги та ID користувача.
     *
     * @param int $userId ID користувача
     * @param int $bookId ID книги
     * @return bool Успіх операції
     */
    public function removeItemByBookAndUser(int $userId, int $bookId): bool
    {
        error_log("WishlistRepository::removeItemByBookAndUser - Attempting to remove book ID: " . $bookId . " for user ID: " . $userId);
        try {
            $stmt = $this->connection->prepare("DELETE FROM wishlist WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $executed = $stmt->execute();
            error_log("WishlistRepository::removeItemByBookAndUser - Delete by user/book query executed: " . ($executed ? 'TRUE' : 'FALSE') . ", Rows affected: " . $stmt->rowCount());
            return $executed;
        } catch (PDOException $e) {
            error_log("WishlistRepository::removeItemByBookAndUser - Database error: " . $e->getMessage());
            return false;
        }
    }
}