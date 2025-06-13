<?php

namespace App\Services;

use App\Models\Order; // Припускаємо, у вас є модель Order

interface OrderServiceInterface
{
    /**
     * Розміщує нове замовлення.
     *
     * @param int $userId ID користувача, який розміщує замовлення.
     * @param array $cartItemsData Масив елементів кошика з даними (book_id, quantity, price_at_purchase).
     * @return int ID створеного замовлення.
     * @throws \Exception У разі невдачі розміщення замовлення.
     */
    public function placeOrder(int $userId, array $cartItemsData): ?int; // <--- ЗМІНЕНО: прибрано $shippingAddress, $paymentMethod. Додано ?int для можливості повернення null.

    /**
     * Отримує замовлення за його ідентифікатором.
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @return Order|null Об'єкт замовлення або null, якщо не знайдено.
     */
    public function getOrderById(int $orderId): ?Order;

    /**
     * Отримує всі замовлення користувача.
     *
     * @param int $userId Ідентифікатор користувача.
     * @return array<Order> Масив об'єктів замовлень.
     */
    public function getOrdersByUserId(int $userId): array;

    /**
     * Оновлює статус замовлення.
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @param string $status Новий статус.
     * @return bool True у разі успішного оновлення, false в іншому випадку.
     */
    public function updateOrderStatus(int $orderId, string $status): bool;

    /**
     * Скасовує замовлення за його ідентифікатором.
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @return bool True у разі успішного скасування, false в іншому випадку.
     */
    public function cancelOrder(int $orderId): bool;

    /**
     * Отримує деталі замовлення за його ідентифікатором.
     *
     * @param int $orderId Ідентифікатор замовлення.
     * @return Order|null Об'єкт замовлення або null, якщо не знайдено.
     */
    public function getOrderDetailsById(int $orderId): ?Order;
}