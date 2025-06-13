<?php

namespace App\Services;

use App\Models\WishlistItem;
use App\Repositories\IWishlistRepository;

class WishlistService implements IWishlistService
{
    private $wishlistRepository;

    public function __construct(IWishlistRepository $wishlistRepository)
    {
        $this->wishlistRepository = $wishlistRepository;
    }

    public function getUserWishlist(int $userId): array
    {
        return $this->wishlistRepository->getUserWishlist($userId);
    }

    public function addItem(int $userId, int $bookId): ?WishlistItem
    {
        // Переконайтеся, що ваш репозиторій addItem повертає WishlistItem або null
        return $this->wishlistRepository->addItem($userId, $bookId);
    }

    // НОВИЙ МЕТОД: Перевірка, чи книга є у вішлисті
    public function isBookInWishlist(int $userId, int $bookId): bool
    {
        // Цей метод викликає відповідний метод у репозиторії
        return $this->wishlistRepository->isBookInWishlist($userId, $bookId);
    }

    // НОВИЙ МЕТОД: Видалення за ID книги та ID користувача
    public function removeItemByBookAndUser(int $userId, int $bookId): bool
    {
        // Цей метод викликає відповідний метод у репозиторії
        return $this->wishlistRepository->removeItemByBookAndUser($userId, $bookId);
    }

    // Залишаємо старий removeItem, якщо він використовується деінде,
    // але для AJAX-обробки ми будемо використовувати removeItemByBookAndUser
    public function removeItem(int $itemId): bool
    {
        return $this->wishlistRepository->removeItem($itemId);
    }
}