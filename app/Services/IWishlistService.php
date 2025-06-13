<?php

namespace App\Services;

use App\Models\WishlistItem; // Якщо WishlistItem використовується як тип повернення

interface IWishlistService
{
    public function getUserWishlist(int $userId): array;
    public function addItem(int $userId, int $bookId): ?WishlistItem; // Можливо, тут був bool
    public function removeItem(int $itemId): bool;

    // ДОДАЙТЕ ЦІ РЯДКИ
    public function isBookInWishlist(int $userId, int $bookId): bool;
    public function removeItemByBookAndUser(int $userId, int $bookId): bool;
}