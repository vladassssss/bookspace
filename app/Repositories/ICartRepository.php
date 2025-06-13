<?php
namespace App\Repositories;

use App\Models\CartItem; // Якщо CartItem використовується в сигнатурах методів

/**
 * Інтерфейс для CartRepository.
 * Визначає контракти для всіх операцій з кошиком.
 */
interface ICartRepository
{
    public function findItem(int $userId, int $bookId): ?CartItem;
    public function add(int $userId, int $bookId, int $quantity, float $priceAtAddition): bool;
    public function updateItemQuantity(int $userId, int $bookId, int $newQuantity): bool;
    public function removeItemFromCart(int $userId, int $bookId): bool;
    public function isBookInCart(int $userId, int $bookId): bool;
    public function getTotalItemsInCart(int $userId): int;
    public function getItemsByUserId(int $userId): array; // Метод, який використовується в CartService
    public function getCartByUserId(int $userId): array;
    public function clearCart(int $userId): bool;
    public function getTotalPrice(int $userId): float;
    public function findByUserIdAndBookId(int $userId, int $bookId): ?CartItem;
}