<?php

namespace App\Repositories;

use App\Models\Order; // Припускаємо, у вас є модель Order

interface OrderRepositoryInterface
{
    public function startTransaction(): void;
    public function commitTransaction(): void;
    public function rollbackTransaction(): void;

    /**
     * Створює новий запис замовлення в базі даних.
     * @param int $userId
     * @param float $totalAmount
     * @return int|null ID новоствореного замовлення, або null у разі невдачі.
     */
    public function createOrder(int $userId, float $totalAmount): ?int; // <--- ЗМІНЕНО

    /**
     * Додає товар до існуючого замовлення.
     * @param int $orderId ID замовлення.
     * @param int $bookId ID книги.
     * @param int $quantity Кількість книги.
     * @param float $priceAtPurchase Ціна книги на момент покупки.
     * @return bool True у разі успіху, false у разі невдачі.
     */
    public function addOrderItem(int $orderId, int $bookId, int $quantity, float $priceAtPurchase): bool;

    /**
     * Знаходить замовлення за його ID.
     * @param int $id
     * @return Order|null
     */
    public function find(int $id): ?Order;

    /**
     * Знаходить замовлення за його ID та включає його товари.
     * @param int $orderId
     * @return Order|null
     */
    public function findOrderWithItemsById(int $orderId): ?Order;


    /**
     * Знаходить усі замовлення для конкретного користувача.
     * @param int $userId
     * @return array<Order>
     */
    public function findByUser(int $userId): array;

    /**
     * Оновлює статус замовлення.
     * @param int $orderId
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $orderId, string $status): bool;

    /**
     * Видаляє замовлення за його ID.
     * @param int $orderId
     * @return bool
     */
    public function delete(int $orderId): bool;
}