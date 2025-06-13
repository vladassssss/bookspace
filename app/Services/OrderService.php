<?php

namespace App\Services;

use App\Repositories\OrderRepositoryInterface;
use App\Repositories\ICartRepository;
use App\Repositories\IUserRepository;
use App\Repositories\IBookstoreRepository; // <--- ЗМІНЕНО: Використовуємо ваш інтерфейс IBookstoreRepository
use App\Models\Order;
use Exception;

class OrderService implements OrderServiceInterface
{
    private OrderRepositoryInterface $orderRepository;
    private ICartRepository $cartRepository;
    private IUserRepository $userRepository;
    private IBookstoreRepository $bookRepository; // <--- ЗМІНЕНО: Тип власності тепер IBookstoreRepository

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        ICartRepository $cartRepository,
        IUserRepository $userRepository,
        IBookstoreRepository $bookRepository // <--- ЗМІНЕНО: Тип аргументу тепер IBookstoreRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->userRepository = $userRepository;
        $this->bookRepository = $bookRepository;
    }

    /**
     * Розміщує нове замовлення.
     *
     * @param int $userId ID користувача, який розміщує замовлення.
     * @param array $cartItemsData Масив елементів кошика з даними (book_id, quantity, price_at_purchase).
     * @return int|null ID створеного замовлення або null у разі невдачі.
     * @throws \Exception У разі невдачі розміщення замовлення.
     */
    public function placeOrder(int $userId, array $cartItemsData): ?int // <--- ЗМІНЕНО: прибрано $shippingAddress, $paymentMethod. Додано ?int.
    {
        error_log("OrderService::placeOrder - UserID: $userId"); // <--- ЗМІНЕНО: прибрано shipping та payment з логування

        if (empty($cartItemsData)) {
            throw new Exception("Кошик порожній, неможливо створити замовлення.");
        }

        $totalAmount = 0.0;
        $this->orderRepository->startTransaction(); // Починаємо транзакцію тут

        try {
            foreach ($cartItemsData as $item) {
                // Перевірка коректності даних елемента кошика
                if (!isset($item['price_at_purchase']) || !isset($item['quantity']) || !isset($item['book_id']) ||
                    !is_numeric($item['price_at_purchase']) || !is_numeric($item['quantity']) || !is_numeric($item['book_id']) ||
                    (float)$item['price_at_purchase'] < 0 || (int)$item['quantity'] <= 0 || (int)$item['book_id'] <= 0) {
                    error_log("Invalid item data in cart for user $userId: " . print_r($item, true));
                    throw new Exception("Некоректні дані товару в кошику для розміщення замовлення.");
                }

                // Додаткова перевірка наявності книги та достатньої кількості
                $availableQuantity = $this->bookRepository->getAvailableQuantity((int)$item['book_id']);
                if ($availableQuantity < (int)$item['quantity']) {
                    throw new Exception("Недостатня кількість книги ID: " . $item['book_id'] . ". Доступно: " . $availableQuantity . ", Замовлено: " . $item['quantity']);
                }

                $totalAmount += (float)$item['price_at_purchase'] * (int)$item['quantity'];
            }

            // 1. Створити основний запис замовлення в таблиці 'orders'
            // <--- ЗМІНЕНО: виклик OrderRepository::createOrder без $shippingAddress та $paymentMethod
            $orderId = $this->orderRepository->createOrder($userId, $totalAmount);

            if (!$orderId) {
                throw new Exception("Не вдалося створити основний запис замовлення.");
            }

            // 2. Додати кожен елемент кошика як елемент замовлення в таблицю 'order_items'
            foreach ($cartItemsData as $itemData) {
                $success = $this->orderRepository->addOrderItem(
                    $orderId,
                    (int)$itemData['book_id'],
                    (int)$itemData['quantity'],
                    (float)$itemData['price_at_purchase']
                );
                if (!$success) {
                    throw new Exception("Не вдалося додати товар (Book ID: {$itemData['book_id']}) до замовлення.");
                }
                
                // Зменшуємо кількість книг у наявності
               // Зменшуємо кількість книг у наявності за допомогою методу decreaseBookQuantity()
$this->bookRepository->decreaseBookQuantity((int)$itemData['book_id'], (int)$itemData['quantity']);

            }

            // 3. Очистити кошик користувача після успішного оформлення
            $clearCartSuccess = $this->cartRepository->clearCart($userId);
            if (!$clearCartSuccess) {
                error_log("Попередження: Не вдалося очистити кошик користувача $userId після оформлення замовлення $orderId.");
            }

            // Якщо все пройшло успішно, підтверджуємо транзакцію
            $this->orderRepository->commitTransaction();

            return $orderId;

        } catch (Exception $e) {
            // Якщо сталася помилка, відкатуємо всі зміни в транзакції
            $this->orderRepository->rollbackTransaction();
            error_log("Помилка при розміщенні замовлення для користувача $userId: " . $e->getMessage());
            throw new Exception("Не вдалося розмістити замовлення: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Отримує замовлення за його ID.
     */
    public function getOrderById(int $orderId): ?Order
    {
        try {
            return $this->orderRepository->find($orderId);
        } catch (Exception $e) {
            error_log("Помилка при отриманні замовлення ID $orderId: " . $e->getMessage());
            throw new Exception("Не вдалося отримати замовлення: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Отримує деталі замовлення за його ідентифікатором (з товарами, користувачем тощо).
     */
    public function getOrderDetailsById(int $orderId): ?Order
    {
        try {
            return $this->orderRepository->findOrderWithItemsById($orderId);
        } catch (Exception $e) {
            error_log("Помилка при отриманні деталей замовлення ID $orderId: " . $e->getMessage());
            throw new Exception("Не вдалося отримати деталі замовлення: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Отримує всі замовлення для певного користувача.
     */
    public function getOrdersByUserId(int $userId): array
    {
        try {
            return $this->orderRepository->findByUser($userId);
        } catch (Exception $e) {
            error_log("Помилка при отриманні замовлень користувача $userId: " . $e->getMessage());
            throw new Exception("Не вдалося отримати замовлення користувача: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Оновлює статус замовлення.
     */
    public function updateOrderStatus(int $orderId, string $newStatus): bool
    {
        $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new Exception("Неприпустимий статус замовлення: $newStatus");
        }

        try {
            return $this->orderRepository->updateStatus($orderId, $newStatus);
        } catch (Exception $e) {
            error_log("Помилка при оновленні статусу замовлення ID $orderId: " . $e->getMessage());
            throw new Exception("Не вдалося оновити статус замовлення: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Скасовує замовлення.
     */
    public function cancelOrder(int $orderId): bool
    {
        try {
            $order = $this->orderRepository->find($orderId);
            if (!$order) {
                throw new Exception("Замовлення ID $orderId не знайдено для скасування.");
            }

            if ($order->getStatus() === 'completed' || $order->getStatus() === 'cancelled') {
                throw new Exception("Неможливо скасувати замовлення зі статусом '{$order->getStatus()}'.");
            }

            return $this->orderRepository->updateStatus($orderId, 'cancelled');
        } catch (Exception $e) {
            error_log("Помилка при скасуванні замовлення ID $orderId: " . $e->getMessage());
            throw new Exception("Не вдалося скасувати замовлення: " . $e->getMessage(), 0, $e);
        }
    }
}