<?php
namespace App\Controllers;

use App\Services\OrderServiceInterface;
use App\Services\IBookstoreService; // Використовуємо інтерфейс

class OrderController
{
    private OrderServiceInterface $orderService;
    private IBookstoreService $bookstoreService; // Використовуємо інтерфейс

    public function __construct(OrderServiceInterface $orderService, IBookstoreService $bookstoreService)
    {
        $this->orderService = $orderService;
        $this->bookstoreService = $bookstoreService;
    }

    /**
     * Обробляє розміщення замовлення з кошика.
     * @param int $userId ID користувача.
     * @param array $cartItemsData Масив елементів кошика з даними про книги, кількість та ціну.
     * @return array Результат операції (success, message, order_id).
     */
public function placeSingleBookOrder(int $userId, int $bookId, int $quantity): ?int
{
    try {
        $book = $this->bookstoreService->getBookById($bookId);
        if (!$book) {
            error_log("OrderController: Book not found for ID: " . $bookId);
            // Було: return ['success' => false, 'message' => 'Книга не знайдена.'];
            return null; // Повертаємо null, оскільки очікуємо ?int
        }

        $priceAtPurchase = $book->getPrice();

        $cartItems = [
            [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price_at_purchase' => $priceAtPurchase
            ]
        ];

        // Тут ви передавали null, null як $shippingAddress, $paymentMethod до placeOrder
        // Це не зовсім вірно, оскільки OrderService::placeOrder приймає лише $userId, $cartItems, $phone.
        // Я бачу, що ви передали null, null до placeOrder, але OrderService::placeOrder очікує 3 аргумент $phone, а 4-5 немає.
        // Видаляємо зайві аргументи, якщо placeOrder їх не приймає
        $phone = $_POST['phone'] ?? null; // Отримайте телефон з POST, якщо потрібно.
        $orderId = $this->orderService->placeOrder($userId, $cartItems, $phone); // Виклик placeOrder з OrderService

        if ($orderId) {
            // Було: return ['success' => true, 'order_id' => $orderId, 'message' => 'Замовлення успішно оформлено!'];
            return $orderId; // Повертаємо саме orderId
        } else {
            // Було: return ['success' => false, 'message' => 'Не вдалося оформити замовлення.'];
            return null; // Повертаємо null
        }

    } catch (Exception $e) {
        error_log("Order placement error in OrderController::placeSingleBookOrder: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        // Було: return ['success' => false, 'message' => 'Помилка при оформленні замовлення: ' . $e->getMessage()];
        return null; // Повертаємо null у разі помилки
    }
}

    /**
     * Обробляє розміщення замовлення з кошика (якщо це окрема функціональність).
     *
     * @param int $userId ID користувача.
     * @param array $cartItemsData Масив елементів кошика у форматі, очікуваному OrderService.
     * @return array Результат операції (success, message, order_id).
     */
     public function placeOrderFromCart(int $userId, array $cartItemsData): array // <--- ЗМІНЕНО
    {
        try {
            // Викликаємо OrderService без адреси та методу оплати
            $orderId = $this->orderService->placeOrder(
                $userId,
                $cartItemsData
            );

            if ($orderId) {
                // Логіка зменшення кількості книг тепер у OrderService, тому тут нічого не потрібно
                return [
                    'success' => true,
                    'message' => 'Замовлення успішно оформлено! ID замовлення: ' . $orderId,
                    'order_id' => $orderId
                ];
            } else {
                return ['success' => false, 'message' => 'Не вдалося оформити замовлення.'];
            }
        } catch (\Exception $e) {
            error_log("Order placement failed in controller: " . $e->getMessage());
            return ['success' => false, 'message' => 'Помилка під час оформлення замовлення: ' . $e->getMessage()];
        }
    }

    /**
     * Отримує деталі замовлення за його ідентифікатором (з товарами, користувачем тощо).
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @param int $userId ID користувача (для перевірки доступу).
     * @return \App\Models\Order|null Об'єкт замовлення з деталями або null, якщо не знайдено.
     */
    public function getOrderDetails(int $orderId, int $userId): ?\App\Models\Order
    {
        try {
            // Викликаємо метод сервісу для отримання деталей замовлення
           // C:\Users\Vlada\xampp\htdocs\bookshop\bookshop\app\Controllers\OrderController.php, рядок 114
$order = $this->orderService->getOrderDetailsById($orderId); // userId не потрібен в цьому методі OrderService

            if ($order) {
                // Завантажуємо деталі книг для кожного елемента замовлення
                foreach ($order->getOrderItems() as $orderItem) {
                    $book = $this->bookstoreService->getBookById($orderItem->getBookId());
                    if ($book) {
                        $orderItem->setBookTitle($book->getTitle());
                        $orderItem->setBookAuthor($book->getAuthor());
                        $orderItem->setCoverImage($book->getCoverImage()); // Якщо є така властивість у Book
                    } else {
                        // Обробка випадку, коли книга не знайдена
                        $orderItem->setBookTitle('Невідома книга');
                        $orderItem->setBookAuthor('');
                        $orderItem->setCoverImage('');
                    }
                }
            }
            return $order;
        } catch (Exception $e) {
            error_log("OrderController Error fetching order details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Отримує всі замовлення для певного користувача.
     *
     * @param int $userId ID користувача.
     * @return array Масив об'єктів замовлень.
     */
    public function getUserOrders(int $userId): array
    {
        try {
            // Викликаємо метод сервісу для отримання замовлень
            $orders = $this->orderService->getUserOrdersWithItems($userId);

            // Для кожного замовлення завантажуємо деталі книг
            foreach ($orders as $order) {
                foreach ($order->getOrderItems() as $orderItem) {
                    $book = $this->bookstoreService->getBookById($orderItem->getBookId());
                    if ($book) {
                        $orderItem->setBookTitle($book->getTitle());
                        $orderItem->setBookAuthor($book->getAuthor());
                        $orderItem->setCoverImage($book->getCoverImage());
                    } else {
                        $orderItem->setBookTitle('Невідома книга');
                        $orderItem->setBookAuthor('');
                        $orderItem->setCoverImage('');
                    }
                }
            }
            return $orders;
        } catch (Exception $e) {
            error_log("OrderController Error fetching user orders: " . $e->getMessage());
            return [];
        }
    }


    // Якщо є інші методи, які ви хочете зберегти або адаптувати, додайте їх тут.
}