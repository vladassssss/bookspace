<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use PDO;
use PDOException;
use Exception;

class OrderRepository implements OrderRepositoryInterface
{
    private PDO $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // --- Методи для керування транзакціями ---
    public function startTransaction(): void
    {
        if (!$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
        }
    }

    public function commitTransaction(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->commit();
        }
    }

    public function rollbackTransaction(): void
    {
        if ($this->connection->inTransaction()) {
            $this->connection->rollBack();
        }
    }

    /**
     * Створює новий запис замовлення в базі даних (лише основне замовлення, без елементів).
     *
     * @param int $userId ID користувача.
     * @param float $totalAmount Загальна сума замовлення.
     * @return int|null ID створеного замовлення або null у разі помилки.
     * @throws Exception У разі помилок бази даних.
     */
    public function createOrder(int $userId, float $totalAmount): ?int // <--- ЗМІНЕНО: прибрано $shippingAddress, $paymentMethod
    {
        try {
            // SQL-запит оновлено: видалено shipping_address та payment_method
            $stmt = $this->connection->prepare("INSERT INTO orders (user_id, order_date, status, total_amount) VALUES (:user_id, NOW(), 'pending', :total_amount)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':total_amount', $totalAmount, PDO::PARAM_STR); // Використовуйте PARAM_STR для float

            $stmt->execute();

            $orderId = (int)$this->connection->lastInsertId();

            if ($orderId === 0) {
                throw new Exception("Не вдалося отримати ID нового замовлення після вставки.");
            }
            return $orderId;

        } catch (PDOException $e) {
            error_log("PDO Error creating order: " . $e->getMessage());
            throw new Exception("Помилка бази даних при створенні замовлення. Спробуйте ще раз.");
        }
    }

    /**
     * Додає окремий товар до замовлення.
     * @param int $orderId ID замовлення, до якого додається товар.
     * @param int $bookId ID книги, яка додається.
     * @param int $quantity Кількість книги.
     * @param float $priceAtPurchase Ціна книги на момент покупки.
     * @return bool True у разі успішного додавання, false в іншому випадку.
     * @throws Exception У разі невірних даних або помилок бази даних.
     */
    public function addOrderItem(int $orderId, int $bookId, int $quantity, float $priceAtPurchase): bool
    {
        try {
            $stmt = $this->connection->prepare("INSERT INTO order_items (order_id, book_id, quantity, price_at_purchase) VALUES (:order_id, :book_id, :quantity, :price_at_purchase)");

            $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->bindParam(':book_id', $bookId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':price_at_purchase', $priceAtPurchase, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("PDO Error adding order item for order_id $orderId, book_id $bookId: " . $e->getMessage());
            throw new Exception("Помилка бази даних при додаванні товару до замовлення.");
        }
    }

    /**
     * Знаходить замовлення за його унікальним ідентифікатором (без завантаження елементів замовлення).
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @return Order|null Об'єкт замовлення (модель Order) або null, якщо замовлення не знайдено.
     */
    public function find(int $orderId): ?Order
    {
        try {
            // SQL-запит оновлено: прибрано delivery_address_id, phone, shipping_address, payment_method
            $stmt = $this->connection->prepare("
                SELECT o.id, o.user_id, o.order_date, o.total_amount, o.status,
                       u.username as user_name
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = :order_id
            ");
            $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();
            $orderData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderData) {
                return null; // Замовлення не знайдено
            }

            // Створення об'єкта Order з отриманих даних (без order_items, delivery_address_id, phone)
            // Припускаємо, що ваш конструктор Order може приймати менше аргументів або має значення за замовчуванням
            $order = new Order(
                $orderData['user_id'],
                $orderData['total_amount'],
                null, // shippingAddress (або null)
                null, // paymentMethod (або null)
                $orderData['status']
            );
            $order->setId($orderData['id']);
            $order->setOrderDate($orderData['order_date']);
            $order->setUserName($orderData['user_name']);

            return $order;

        } catch (PDOException $e) {
            error_log("Database error in OrderRepository::find(orderId): " . $e->getMessage());
            throw new Exception("Помилка бази даних при отриманні замовлення за ID: " . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            error_log("Application error in OrderRepository::find(orderId): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Знаходить замовлення за його ідентифікатором разом з усіма позиціями замовлення (товарами).
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @return Order|null Об'єкт замовлення (модель Order) з наповненими OrderItems, або null, якщо замовлення не знайдено.
     */
    public function findOrderWithItemsById(int $orderId): ?Order
    {
        try {
            // Отримання основних даних замовлення
            $order = $this->find($orderId); // Використовуємо метод find для отримання базового об'єкта Order
            if (!$order) {
                return null; // Якщо базове замовлення не знайдено, повертаємо null
            }

            // Отримання елементів замовлення (order_items)
            $itemsStmt = $this->connection->prepare(
                "SELECT oi.id, oi.order_id, oi.book_id, oi.quantity, oi.price_at_purchase, " .
                "b.title as book_title, b.author as book_author, b.cover_image as book_cover_image " .
                "FROM order_items oi " .
                "JOIN bookshop_book b ON oi.book_id = b.id " . // Припускаємо, що bookshop_book - це таблиця книг
                "WHERE oi.order_id = :order_id"
            );
            $itemsStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $itemsStmt->execute();
            $orderItemsData = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Заповнення масиву OrderItem та присвоєння його об'єкту Order
            $orderItems = [];
            foreach ($orderItemsData as $itemData) {
                $orderItem = new OrderItem(
                    (int)$itemData['order_id'],
                    (int)$itemData['book_id'],
                    (int)$itemData['quantity'],
                    (float)$itemData['price_at_purchase']
                );
                $orderItem->setId((int)$itemData['id']);
                $orderItem->setBookTitle($itemData['book_title']);
                $orderItem->setBookAuthor($itemData['book_author']);
                $orderItem->setCoverImage($itemData['book_cover_image']);
                $orderItems[] = $orderItem;
            }
            $order->setOrderItems($orderItems);

            return $order;

        } catch (PDOException $e) {
            error_log("Database error in findOrderWithItemsById: " . $e->getMessage());
            throw new Exception("Помилка бази даних при отриманні деталей замовлення за ID: " . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            error_log("Application error in findOrderWithItemsById: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Знаходить усі замовлення, пов'язані з певним користувачем.
     *
     * @param int $userId Ідентифікатор користувача.
     * @return array Масив об'єктів замовлень (моделей Order). Повертає порожній масив, якщо замовлень не знайдено.
     */
    public function findByUser(int $userId): array
    {
        try {
            // SQL-запит оновлено: прибрано delivery_address_id, phone
            $stmt = $this->connection->prepare("SELECT id, user_id, order_date, status, total_amount FROM orders WHERE user_id = :user_id ORDER BY order_date DESC");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $ordersData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $orders = [];
            foreach ($ordersData as $orderData) {
                // Оновлено: передаємо null для shippingAddress та paymentMethod до конструктора Order
                $order = new Order(
                    $orderData['user_id'],
                    $orderData['total_amount'],
                    null, // shippingAddress
                    null, // paymentMethod
                    $orderData['status']
                );
                $order->setId($orderData['id']);
                $order->setOrderDate($orderData['order_date']);

                // Отримання елементів замовлення для кожного замовлення (може бути оптимізовано, якщо не завжди потрібно)
                $itemsStmt = $this->connection->prepare("SELECT oi.id, oi.order_id, oi.book_id, oi.quantity, oi.price_at_purchase FROM order_items WHERE order_id = :order_id");
                $itemsStmt->bindParam(':order_id', $orderData['id'], PDO::PARAM_INT);
                $itemsStmt->execute();
                $orderItemsData = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                $orderItems = [];
                foreach ($orderItemsData as $itemData) {
                    $orderItem = new OrderItem(
                        (int)$itemData['order_id'],
                        (int)$itemData['book_id'],
                        (int)$itemData['quantity'],
                        (float)$itemData['price_at_purchase']
                    );
                    $orderItem->setId((int)$itemData['id']);
                    // Тут ви можете також отримати інформацію про книгу (title, author, cover_image)
                    $orderItems[] = $orderItem;
                }
                $order->setOrderItems($orderItems);
                $orders[] = $order;
            }
            return $orders;
        } catch (PDOException $e) {
            error_log("Database error in findByUser: " . $e->getMessage());
            throw new Exception("Помилка бази даних при отриманні замовлень користувача: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Оновлює статус замовлення.
     *
     * @param int $orderId ID замовлення.
     * @param string $status Новий статус.
     * @return bool True у разі успішного оновлення, false в іншому випадку.
     */
    public function updateStatus(int $orderId, string $status): bool
    {
        try {
            $stmt = $this->connection->prepare("UPDATE orders SET status = :status WHERE id = :order_id");
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database error in updateStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Видаляє замовлення за його ID.
     *
     * @param int $orderId ID замовлення.
     * @return bool True у разі успішного видалення, false в іншому випадку.
     */
    public function delete(int $orderId): bool
    {
        try {
            $this->startTransaction(); 
            
            // Видаляємо дочірні записи спочатку
            $stmtItems = $this->connection->prepare("DELETE FROM order_items WHERE order_id = :order_id");
            $stmtItems->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $stmtItems->execute();

            // Потім видаляємо основний запис замовлення
            $stmtOrder = $this->connection->prepare("DELETE FROM orders WHERE id = :order_id");
            $stmtOrder->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $result = $stmtOrder->execute();

            $this->commitTransaction();
            return $result;
        } catch (PDOException $e) {
            $this->rollbackTransaction();
            error_log("Database error in delete order: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            $this->rollbackTransaction();
            error_log("Application error in delete order: " . $e->getMessage());
            return false;
        }
    }
}