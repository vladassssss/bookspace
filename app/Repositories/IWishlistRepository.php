<?php

namespace App\Repositories;

use App\Models\WishlistItem;

interface IWishlistRepository
{
    public function getUserWishlist(int $userId): array;
    public function addItem(int $userId, int $bookId): ?WishlistItem;
    public function removeItem(int $itemId): bool;
    public function isBookInWishlist(int $userId, int $bookId): bool;
    // Додайте інші необхідні метод
    public function removeItemByBookAndUser(int $userId, int $bookId): bool;

}