<?php
// C:\Users\Vlada\xampp\htdocs\bookshop\bookshop\app\Repositories\CartRepository.php

namespace App\Repositories;

use App\Models\CartItem; // Переконайтеся, що CartItem підключено
use PDO;
use PDOException;

/**
 * Клас CartRepository відповідає за взаємодію з базою даних
 * для операцій, пов'язаних з кошиком користувача.
 */
class CartRepository implements ICartRepository // <<< ЗВЕРНІТЬ УВАГУ: implements ICartRepository
{
    private PDO $db;

    /**
     * Конструктор CartRepository.
     * @param PDO $db Об'єкт PDO для підключення до бази даних.
     */
    public function __construct(PDO $db)
    {
        $this->db = $db;
        error_log("CartRepository: Constructor called."); // Лог: конструктор викликано
    }

    /**
     * Знаходить елемент кошика за ID користувача та ID книги.
     * Цей метод об'єднує дані книги для повної інформації.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @return CartItem|null Об'єкт CartItem, якщо знайдено, інакше null.
     */
   public function findItem(int $userId, int $bookId): ?CartItem
    {
        error_log("CartRepository: findItem called. User ID: " . $userId . ", Book ID: " . $bookId);
        try {
            $stmt = $this->db->prepare("
                SELECT
                    ci.id AS cart_item_id,      -- Додайте аліас, якщо це ID елемента кошика
                    ci.user_id,
                    ci.book_id,
                    ci.quantity AS cart_quantity, -- !!! Обов'язково дайте аліас для кількості в кошику
                    ci.price_at_addition,
                    ci.created_at,              -- Додайте, якщо потрібні дати створення/оновлення
                    ci.updated_at,              -- Додайте, якщо потрібні дати створення/оновлення
                    b.title,
                    b.author,
                    b.price AS book_current_price,
                    b.discount,
                    b.quantity AS book_available_quantity, -- !!! Обов'язково дайте аліас для кількості книги
                    b.cover_image
                FROM cart_items ci
                JOIN bookshop_book b ON ci.book_id = b.id
                WHERE ci.user_id = :user_id AND ci.book_id = :book_id LIMIT 1
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                // Додайте лог, щоб переконатися, що тепер ви отримуєте правильні дані
                error_log("CartRepository: Raw data from DB for findItem (after fix): " . print_r($data, true));

                // Переконайтеся, що CartItem::fromArray очікує 'cart_quantity'
                // Або зробіть перетворення тут:
                $data['quantity'] = $data['cart_quantity']; // Обов'язково! Переписуємо 'quantity' на правильне значення
                
                $cartItem = CartItem::fromArray($data);
                error_log("CartRepository: findItem found item for User ID: " . $userId . ", Book ID: " . $bookId . " with quantity " . $cartItem->getQuantity());
                return $cartItem;
            }
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in findItem: " . $e->getMessage());
        }
        error_log("CartRepository: findItem did NOT find item for User ID: " . $userId . ", Book ID: " . $bookId);
        return null;
    }


    /**
     * Додає новий елемент до кошика або оновлює його кількість.
     * Використовує ON DUPLICATE KEY UPDATE для зручності.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @param int $quantity Кількість книги.
     * @param float $priceAtAddition Ціна книги на момент додавання.
     * @return bool True, якщо додавання/оновлення успішне, false інакше.
     */
    public function add(int $userId, int $bookId, int $quantity, float $priceAtAddition): bool
    {
        error_log("CartRepository: add called. User ID: " . $userId . ", Book ID: " . $bookId . ", Quantity: " . $quantity . ", Price: " . $priceAtAddition);
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO cart_items (user_id, book_id, quantity, price_at_addition, created_at, updated_at)
                VALUES (:user_id, :book_id, :quantity, :price_at_addition, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    updated_at = NOW()"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':price_at_addition', $priceAtAddition, PDO::PARAM_STR);
            $success = $stmt->execute();
            if ($success) {
                error_log("CartRepository: Successfully added/updated item to cart.");
            } else {
                error_log("CartRepository: Failed to add/update item to cart. ErrorInfo: " . print_r($stmt->errorInfo(), true));
            }
            return $success;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in add: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Оновлює кількість конкретного елемента в кошику до заданого значення.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @param int $newQuantity Нова кількість.
     * @return bool True, якщо оновлення успішне, false інакше.
     */
    // C:\Users\Vlada\xampp\htdocs\bookshop\bookshop\app\Repositories\CartRepository.php
public function updateItemQuantity(int $userId, int $bookId, int $newQuantity): bool
{
    try {
        $stmt = $this->db->prepare(
            "UPDATE cart_items SET quantity = :quantity WHERE user_id = :user_id AND book_id = :book_id"
        );
        $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);

        $success = $stmt->execute();

        // Логування
        if ($success && $stmt->rowCount() > 0) {
            error_log("CartRepository: Updated quantity for User ID: " . $userId . ", Book ID: " . $bookId . " to " . $newQuantity);
        } else if ($success && $stmt->rowCount() === 0) { // ДОДАЙТЕ ЦЕЙ БЛОК
            error_log("CartRepository: Quantity for User ID: " . $userId . ", Book ID: " . $bookId . " is ALREADY " . $newQuantity . ". No change made.");
        } else {
            error_log("CartRepository: Failed to update quantity or no rows affected for User ID: " . $userId . ", Book ID: " . $bookId . ". Success: " . var_export($success, true) . ", Rows affected: " . $stmt->rowCount());
        }

        // ЗМІНІТЬ ЦЕЙ РЯДОК:
        return $success; // Повертаємо true, якщо запит виконався без помилок, навіть якщо не було змін
    } catch (\PDOException $e) {
        error_log("CartRepository: PDOException in updateItemQuantity: " . $e->getMessage() . " on line " . $e->getLine());
        return false;
    }
}
    /**
     * Видаляє елемент з кошика користувача.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @return bool True, якщо видалення успішне, false інакше.
     */
    public function removeItemFromCart(int $userId, int $bookId): bool
    {
        error_log("CartRepository: removeItemFromCart called. User ID: " . $userId . ", Book ID: " . $bookId);
        try {
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE user_id = :user_id AND book_id = :book_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $success = $stmt->execute();
            if ($success) {
                error_log("CartRepository: Successfully removed item from cart. Rows affected: " . $stmt->rowCount());
            } else {
                error_log("CartRepository: Failed to remove item from cart. ErrorInfo: " . print_r($stmt->errorInfo(), true));
            }
            return $success;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in removeItemFromCart: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Перевіряє, чи є книга в кошику користувача.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @return bool True, якщо книга в кошику, false інакше.
     */
    public function isBookInCart(int $userId, int $bookId): bool
    {
        error_log("CartRepository: Checking if book is in cart. User ID: " . $userId . ", Book ID: " . $bookId);
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id AND book_id = :book_id"
            );
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->execute();
            $count = $stmt->fetchColumn();
            error_log("CartRepository: isBookInCart result: " . ($count ? 'true' : 'false'));
            return (bool) $count;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in isBookInCart: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Отримує загальну кількість елементів у кошику користувача.
     * @param int $userId ID користувача.
     * @return int Загальна кількість елементів.
     */
    public function getTotalItemsInCart(int $userId): int
    {
        error_log("CartRepository: getTotalItemsInCart called for User ID: " . $userId);
        try {
            $stmt = $this->db->prepare("SELECT SUM(quantity) AS total_quantity FROM cart_items WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)($result['total_quantity'] ?? 0);
            error_log("CartRepository: getTotalItemsInCart returned " . $total);
            return $total;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in getTotalItemsInCart: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Отримує всі елементи кошика для певного користувача.
     * Цей метод об'єднує дані книги, щоб CartService міг отримати повну інформацію.
     * @param int $userId ID користувача.
     * @return array Масив об'єктів CartItem.
     */
   public function getItemsByUserId(int $userId): array
    {
        error_log("CartRepository: getItemsByUserId called for User ID: " . $userId);
        $cartItems = [];
        try {
            $sql = "
                SELECT
                    ci.id AS cart_item_id,
                    ci.user_id,
                    ci.book_id,
                    ci.quantity AS cart_quantity, -- !!! ЗМІНЕНО: ДОДАНО АЛІАС ДЛЯ КІЛЬКОСТІ З ТАБЛИЦІ cart_items
                    ci.price_at_addition,
                    b.title,
                    b.author,
                    b.price AS book_current_price,
                    b.discount,
                    b.quantity AS book_available_quantity, -- !!! ЗМІНЕНО: ДОДАНО АЛІАС ДЛЯ КІЛЬКОСТІ З ТАБЛИЦІ bookshop_book
                    b.cover_image
                FROM
                    cart_items ci
                JOIN
                    bookshop_book b ON ci.book_id = b.id
                WHERE
                    ci.user_id = :user_id
                ORDER BY ci.created_at DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                error_log("CartRepository: Fetched row for Book ID: " . $row['book_id'] .
                          ", Cart Quantity (from DB): " . $row['cart_quantity'] .
                          ", Book Available Quantity (from DB): " . $row['book_available_quantity']);

                // !!! ЗМІНЕНО: Переприсвоюємо ключ 'quantity' в масиві $row,
                //             щоб CartItem::fromArray отримував правильну кількість кошика.
                //             Це важливо, якщо CartItem::fromArray очікує ключ 'quantity'.
                $row['quantity'] = $row['cart_quantity']; 

                $cartItems[] = CartItem::fromArray($row);
            }
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in getItemsByUserId: " . $e->getMessage());
        }
        error_log("CartRepository: getItemsByUserId fetched " . count($cartItems) . " items.");
        return $cartItems;
    }
    /**
     * Отримує "сирі" дані кошика (без деталей книги) для користувача.
     * @param int $userId ID користувача.
     * @return array Масив об'єктів CartItem.
     */
    public function getCartByUserId(int $userId): array
    {
        error_log("CartRepository: Fetching raw cart data for User ID: " . $userId);
        try {
            $stmt = $this->db->prepare("SELECT * FROM cart_items WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $itemsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("CartRepository: getCartByUserId fetched " . count($itemsData) . " raw items.");

            $cartItems = [];
            foreach ($itemsData as $item) {
                error_log("CartRepository: Creating CartItem object for ID: " . $item['id']);
                $cartItems[] = new CartItem(
                    $item['id'],
                    $item['user_id'],
                    $item['book_id'],
                    $item['quantity'],
                    $item['price_at_addition'] ?? 0.0,
                    $item['created_at'] ?? null,
                    $item['updated_at'] ?? null
                );
            }
            return $cartItems;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in getCartByUserId: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Повністю очищає кошик користувача.
     * @param int $userId ID користувача.
     * @return bool True, якщо очищення успішне, false інакше.
     */
    public function clearCart(int $userId): bool
    {
        error_log("CartRepository: Attempting to clear cart for User ID: " . $userId);
        try {
            $stmt = $this->db->prepare("DELETE FROM cart_items WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $result = $stmt->execute();
            if ($result) {
                error_log("CartRepository: clearCart successful. Affected rows: " . $stmt->rowCount());
            } else {
                error_log("CartRepository: clearCart failed. No rows affected or query error.");
            }
            return $result;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in clearCart: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Отримує загальну вартість товарів у кошику користувача.
     * @param int $userId
     * @return float
     */
    public function getTotalPrice(int $userId): float
    {
        error_log("CartRepository: getTotalPrice called for User ID: " . $userId);
        try {
            $stmt = $this->db->prepare("SELECT SUM(quantity * price_at_addition) as total_price FROM cart_items WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = (float)($result['total_price'] ?? 0.0);
            error_log("CartRepository: getTotalPrice returned " . $total);
            return $total;
        } catch (PDOException $e) {
            error_log("CartRepository: PDOException in getTotalPrice: " . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Знаходить елемент кошика за ID користувача та ID книги.
     * Делегує виклик до findItem для уникнення дублювання.
     * @param int $userId ID користувача.
     * @param int $bookId ID книги.
     * @return CartItem|null Об'єкт CartItem, якщо знайдено, інакше null.
     */
    public function findByUserIdAndBookId(int $userId, int $bookId): ?\App\Models\CartItem {
        error_log("CartRepository: findByUserIdAndBookId called for User ID: " . $userId . ", Book ID: " . $bookId);
        return $this->findItem($userId, $bookId);
    }
}