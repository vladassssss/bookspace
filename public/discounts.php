<?php
session_start();
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';

// Додаємо необхідні файли для рейтингу
require_once __DIR__ . '/../app/Services/IRatingService.php';
require_once __DIR__ . '/../app/Services/RatingService.php';
require_once __DIR__ . '/../app/Repositories/IRatingRepository.php';
require_once __DIR__ . '/../app/Repositories/RatingRepository.php';
require_once __DIR__ . '/../app/Models/Rating.php'; 
require_once __DIR__ . '/../app/Models/WishlistItem.php';
require_once __DIR__ . '/../app/Repositories/IWishlistRepository.php';
require_once __DIR__ . '/../app/Repositories/WishlistRepository.php'; 
require_once __DIR__ . '/../app/Services/IWishlistService.php';
require_once __DIR__ . '/../app/Services/WishlistService.php';

use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Services\BookstoreService;
use App\Controllers\BookstoreController;
use App\Services\RatingService;
use App\Repositories\RatingRepository;
use App\Models\WishlistItem;
use App\Repositories\IWishlistRepository;
use App\Repositories\WishlistRepository;
use App\Services\IWishlistService;
use App\Services\WishlistService;

$db = Connection::getInstance()->getConnection();

// Ініціалізація BookstoreService
$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);

// Ініціалізація RatingService
$ratingRepository = new RatingRepository($db);
$ratingService = new RatingService($ratingRepository);

$bookstoreController = new BookstoreController($bookstoreService, $ratingService);

// Отримуємо список книг зі знижками
$discountedBooks = $bookstoreController->getDiscountedBooks();

$actualDiscountedBooks = array_filter($discountedBooks, function ($book) {
    return $book->getDiscount() > 0;
});

// --- Ініціалізація Wishlist репозиторію та сервісу (ви вже маєте це) ---
$wishlistRepository = new WishlistRepository($db, $bookstoreRepository);
$wishlistService = new WishlistService($wishlistRepository);

// Отримання даних кошика (вже є)
require_once __DIR__ . '/../app/Models/CartItem.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Controllers/CartController.php';

use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Controllers\CartController;

$cartItems = []; // Ініціалізація для кошика
if (isset($_SESSION['user_id'])) {
    try {
        $cartRepository = new CartRepository($db);
        $cartService = new CartService($cartRepository, $bookstoreRepository);
        $cartController = new CartController($cartService);
        $cartItems = $cartController->fetchUserCart($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Помилка отримання кошика на discounts.php: " . $e->getMessage());
    }
}

// --- ІНІЦІАЛІЗАЦІЯ $favoriteBookIds (ЦЕЙ БЛОК ВИРІШУЄ ВАШУ ПРОБЛЕМУ) ---
$favoriteBookIds = []; // <--- Ось тут ініціалізуємо змінну як порожній масив

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    error_log("DEBUG: User is logged in. User ID: " . $userId);

    $userFavoriteBooks = $wishlistService->getUserWishlist($userId);
    error_log("DEBUG: getUserWishlist returned " . count($userFavoriteBooks) . " App\\Models\\Book items.");

    foreach ($userFavoriteBooks as $book) {
        if ($book instanceof App\Models\Book && method_exists($book, 'getId')) {
            $favoriteBookIds[] = $book->getId();
        } else {
            error_log("DEBUG: Unexpected object type in userFavoriteBooks or missing getId method. Object type: " . (is_object($book) ? get_class($book) : gettype($book)));
        }
    }
    // Рядок 103 (або подібний) - тепер він буде безпечним
    // Важливо: для використання в SQL IN() операторі, якщо список порожній, краще передати щось типу 'NULL'
    // або змінити запит, щоб він не вимагав IN() для порожнього списку.
    // Якщо ви використовуєте це для налагодження, це безпечно.
    error_log("DEBUG: favoriteBookIds after processing: " . implode(', ', $favoriteBookIds));
} else {
    error_log("DEBUG: User not logged in. Skipping wishlist fetch.");
}

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Книги зі знижками</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
</head>
<body>
    <header>
        <div class="container nav-container">
            <nav>
                <ul class="nav-left">
                    <li>
                        <button id="toggleSidebar" class="category-button">Категорії книг</button>
                    </li>
                    <li><a href="index.php">Головна</a></li>
                    <li><a href="popular.php">Популярне</a></li>
                    <li><a href="recommendation_test.php">Підбір книги</a></li> </ul>
                <div class="nav-right">
                    <form class="search-form" method="GET" action="search.php">
                        <input type="text" name="query" placeholder="Знайти книжку..." value="">
                        <button type="submit">🔍</button>
                    </form>

                    <?php
                    // Припускаємо, що $cartItems ініціалізовано десь вище в PHP-скрипті
                    $cartItemCount = isset($cartItems) ? count($cartItems) : 0;
                    ?>
                    <a href="cart.php" class="cart-link" title="Мій кошик">
                        🛒<span id="cart-count"><?= $cartItemCount; ?></span>
                    </a>

                    <div class="auth-section">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="profile.php" class="profile-link" title="Мій профіль">
                                <svg class="profile-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
                                </svg>
                                <span class="username-display"><?= htmlspecialchars($_SESSION['username'] ?? 'Користувач'); ?></span>
                            </a>
                            <button class="logout-btn" onclick="window.location.href='logout.php'">Вийти</button>
                        <?php else: ?>
                            <button class="login-btn" onclick="window.location.href='login.php'">Увійти</button>
                            <button class="register-btn" onclick="window.location.href='register.php'">Зареєструватися</button>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>

            <div id="sidebar" class="sidebar hidden">
                <ul>
                    <li><a href="allbooks.php">Усі</a></li>
                    <li><a href="allbooks.php?genre=Детектив">Детектив</a></li>
                    <li><a href="allbooks.php?genre=Фантастика">Фантастика</a></li>
                    <li><a href="allbooks.php?genre=Наукова фантастика">Наукова фантастика</a></li>
                    <li><a href="allbooks.php?genre=Жахи">Жахи</a></li>
                    <li><a href="allbooks.php?genre=Психологія">Психологія</a></li>
                    <li><a href="allbooks.php?genre=Белетристика">Белетристика</a></li>
                    <li><a href="allbooks.php?genre=Антиутопія">Антиутопія</a></li>
                    <li><a href="allbooks.php?genre=Історичний роман">Історичний роман</a></li>
                    <li><a href="allbooks.php?genre=Фентезі">Фентезі</a></li>
                    <li><a href="allbooks.php?genre=Казка">Казка</a></li>
                    <li><a href="allbooks.php?genre=Притча">Притча</a></li>
                    <li><a href="allbooks.php?genre=Роман">Роман</a></li>
                    <li><a href="allbooks.php?genre=Наука">Наука</a></li>
                    <li><a href="allbooks.php?genre=Пригоди">Пригоди</a></li>
                    <li><a href="allbooks.php?genre=Підлітковий">Підлітковий</a></li>
                    <li><a href="allbooks.php?genre=Класика">Класика</a></li>
                    <li><a href="allbooks.php?genre=Романтика">Романтика</a></li>
                    <li><a href="allbooks.php?genre=Драма">Драма</a></li>
                </ul>
            </div>
        </div>
    </header>

    <main class="container" style="margin-top: 100px;"> <h1>Книги зі знижками</h1>
        <?php if (empty($actualDiscountedBooks)): ?>
            <p>Наразі немає книг зі знижками.</p>
        <?php else: ?>
            <div class="discounted-books-grid">
                <?php foreach ($actualDiscountedBooks as $book): ?>
                    <div class="discounted-book-item book"> <div class="discounted-book-cover-container">
                            <?php
                            // Припускаємо, що $favoriteBookIds - це масив ID книг, які є в списку бажань користувача
                            // Якщо змінна не існує, ініціалізуємо як порожній масив, щоб уникнути помилок
                            $favoriteBookIds = $favoriteBookIds ?? [];
                            $isFavorite = in_array($book->getId(), $favoriteBookIds);
                            ?>
                            
                            <button class="wishlist-button <?= $isFavorite ? 'active-favorite' : ''; ?>" data-id="<?= htmlspecialchars($book->getId()); ?>" title="Додати до улюблених">
                                <svg viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                            </button>
                            
                            <?php if (!isset($_SESSION['user_id'])): ?>
                            <span class="wishlist-text-login">
                                <a href="login.php">Увійдіть</a>, щоб додати до улюблених
                            </span>
                            <?php endif; ?>

                            <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>" class="book-link">
                                <img src="images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>">
                            </a>
                            <?php if ($book->getDiscount() > 0): ?>
                                <span class="discount-badge">-<?= htmlspecialchars($book->getDiscount()); ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="discounted-book-info">
                            <h3 class="discounted-book-title"><?= htmlspecialchars($book->getTitle()); ?></a></h3>
                            <p class="discounted-book-author"><?= htmlspecialchars($book->getAuthor()); ?></p>
                            <div class="book-prices">
                                <?php if ($book->getDiscount() > 0): ?>
                                    <span class="original-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</span>
                                    <span class="sale-price"><?= htmlspecialchars(number_format($book->getPrice() * (1 - $book->getDiscount() / 100), 2)); ?> грн</span>
                                <?php else: ?>
                                    <span class="book-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</span>
                                <?php endif; ?>
                            </div>
                            <div class="availability-status">
                                <?php
                                $quantity = $book->getQuantity(); // Припускаємо, що цей метод існує
                                if ($quantity > 0):
                                ?>
                                    <span class="status-in-stock">
                                        <i class="fas fa-check-circle"></i> В наявності
                                        <?php if ($quantity <= 5 && $quantity > 0): ?>
                                            <span class="low-stock-warning">(Залишилось: <?= $quantity ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-out-of-stock">
                                        <i class="fas fa-times-circle"></i> Немає в наявності
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="discounted-book-actions book-buttons"> <button class="order-button" data-id="<?= htmlspecialchars($book->getId()); ?>"
                                <?php if ($quantity <= 0): ?>disabled title="Немає в наявності"<?php endif; ?>>
                                До кошика
                            </button>
                            </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
<footer class="footer">
  <div class="footer-content">
    <div class="social-icons">
      <a href="https://facebook.com" target="_blank" aria-label="Facebook">
        <svg width="30" height="30" fill="white" viewBox="0 0 24 24">
          <path d="M22.675 0H1.325C.593 0 0 .593 0 1.326v21.348C0 
          23.406.593 24 1.325 24H12.82v-9.294H9.692V11.01h3.128V8.413c0-3.1 
          1.893-4.788 4.659-4.788 1.325 0 2.464.099 
          2.795.143v3.24l-1.918.001c-1.504 
          0-1.796.715-1.796 1.763v2.313h3.59l-.467 
          3.696h-3.123V24h6.116C23.407 24 24 
          23.407 24 22.674V1.326C24 .593 23.407 
          0 22.675 0z"/>
        </svg>
      </a>
      <a href="https://instagram.com" target="_blank" aria-label="Instagram">
        <svg width="30" height="30" fill="white" viewBox="0 0 24 24">
          <path d="M12 2.2c3.2 0 3.584.012 4.85.07 1.17.056 1.96.24 
          2.416.403a4.92 4.92 0 011.768 1.01 4.92 4.92 0 
          011.01 1.768c.163.456.347 1.246.403 2.416.058 
          1.266.07 1.65.07 4.85s-.012 3.584-.07 
          4.85c-.056 1.17-.24 1.96-.403 2.416a4.92 
          4.92 0 01-1.01 1.768 4.92 4.92 0 
          01-1.768 1.01c-.456.163-1.246.347-2.416.403-1.266.058-1.65.07-4.85.07s-3.584-.012-4.85-.07c-1.17-.056-1.96-.24-2.416-.403a4.92 
          4.92 0 01-1.768-1.01 4.92 4.92 0 
          01-1.01-1.768c-.163-.456-.347-1.246-.403-2.416C2.212 
          15.784 2.2 15.4 2.2 12s.012-3.584.07-4.85c.056-1.17.24-1.96.403-2.416a4.92 
          4.92 0 011.01-1.768 4.92 4.92 0 
          011.768-1.01c.456-.163 1.246-.347 
          2.416-.403C8.416 2.212 8.8 2.2 12 
          2.2zm0-2.2C8.735 0 8.332.014 7.052.072 5.774.129 4.672.348 
          3.758.735A7.15 7.15 0 001.443 
          1.443 7.15 7.15 0 00.735 
          3.758C.348 4.672.129 5.774.072 7.052.014 
          8.332 0 8.735 0 12c0 3.265.014 3.668.072 
          4.948.057 1.278.276 2.38.663 
          3.294.387.914.908 1.68 1.715 
          2.487a7.15 7.15 0 002.487 1.715c.914.387 
          2.016.606 3.294.663C8.332 23.986 8.735 24 
          12 24s3.668-.014 4.948-.072c1.278-.057 
          2.38-.276 3.294-.663a7.15 7.15 0 
          002.487-1.715 7.15 7.15 0 
          001.715-2.487c.387-.914.606-2.016.663-3.294.058-1.28.072-1.683.072-4.948 
          0-3.265-.014-3.668-.072-4.948-.057-1.278-.276-2.38-.663-3.294a7.15 
          7.15 0 00-1.715-2.487A7.15 7.15 0 
          0020.242.735c-.914-.387-2.016-.606-3.294-.663C15.668.014 
          15.265 0 12 0zm0 5.838a6.162 6.162 0 100 
          12.324 6.162 6.162 0 000-12.324zm0 
          10.162a4 4 0 110-8 4 4 0 010 
          8zm6.406-11.845a1.44 1.44 0 11-2.88 0 1.44 
          1.44 0 012.88 0z"/>
        </svg>
      </a>
    </div>

    <div class="footer-info">
      <p>📞 Телефон: +380 12 345 6789</p>
      <p>✉️ Email: info@shop.com</p>
    </div>

        <p class="copyright">© 2025 Магазин. Всі права захищено.</p>
  </div>
</footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const cartCountSpan = document.getElementById('cart-count');

            // Обробка кліків по кнопці "До кошика"
            document.querySelectorAll('.order-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const bookId = button.getAttribute('data-id');
                    if (!bookId) {
                        alert('Помилка: Не вдалося отримати ID книги.');
                        return;
                    }

                    button.disabled = true;
                    button.textContent = 'Додаємо...';

                    try {
                        const response = await fetch('add_to_cart.php', { // Шлях до скрипту
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: bookId, quantity: 1 })
                        });

                        if (!response.ok) {
                            const errorText = await response.text();
                            alert(`Помилка сервера: ${response.status} - ${response.statusText}. Деталі: ${errorText.substring(0, 100)}...`);
                            return;
                        }

                        let result;
                        try {
                            result = await response.json();
                        } catch (jsonError) {
                            alert('Помилка обробки даних від сервера. Не вдалося розібрати відповідь.');
                            return;
                        }

                        if (result.success) {
                            alert(result.message || 'Книга додана до кошика!');
                            if (cartCountSpan) {
                                cartCountSpan.textContent = result.cart_total_items; // Оновлюємо лічильник з відповіді сервера
                            }
                            const cartLink = document.querySelector('.cart-link');
                            if (cartLink) {
                                cartLink.classList.add('bump');
                                setTimeout(() => {
                                    cartLink.classList.remove('bump');
                                }, 300);
                            }
                        } else if (result.error === 'login_required') {
                            alert('Будь ласка, увійдіть, щоб додати книгу до кошика.');
                            window.location.href = 'login.php';
                        } else {
                            alert('Помилка: ' + (result.message || result.error || 'Невідома помилка.'));
                        }
                    } catch (error) {
                        alert('Щось пішло не так під час виконання запиту до сервера...');
                    } finally {
                        setTimeout(() => {
                            if (document.body.contains(button)) { // Перевірка, чи кнопка все ще в DOM
                                button.disabled = false;
                                button.textContent = 'До кошика';
                            }
                        }, 500);
                    }
                });
            });

            // Код для бокової панелі
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');
            if (sidebar && toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                });
                document.addEventListener('click', function(e){
                    if(!sidebar.contains(e.target) && !toggleBtn.contains(e.target)){
                        sidebar.classList.remove('show');
                    }
                });
                sidebar.addEventListener('click', function(e){
                    e.stopPropagation();
                });
            }

            // JavaScript для кнопок списку бажань
            document.querySelectorAll('.wishlist-button').forEach(button => {
                const bookId = button.getAttribute('data-id');

                button.addEventListener('click', async (event) => {
                    event.preventDefault(); // Запобігти переходу, якщо кнопка знаходиться всередині посилання
                    event.stopPropagation(); // Зупинити розповсюдження події, якщо кнопка всередині іншого клікабельного елемента

                    // Отримуємо статус авторизації з PHP
                    const isLoggedIn = <?= json_encode(isset($_SESSION['user_id'])); ?>;

                    if (!isLoggedIn) {
                        alert('Будь ласка, увійдіть, щоб додати книгу до списку бажань.');
                        window.location.href = 'login.php'; // Перенаправлення на сторінку входу
                        return;
                    }

                    const isCurrentlyFavorite = button.classList.contains('active-favorite');
                    const action = isCurrentlyFavorite ? 'remove_from_wishlist' : 'add_to_wishlist';

                    try {
                        const response = await fetch('add_to_favorites.php', { // Шлях до скрипту
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ book_id: bookId, action: action })
                        });
                        const result = await response.json();

                        if (result.success) {
                            if (result.action === 'added') {
                                alert('Книга додана до списку бажань!');
                                button.classList.add('active-favorite');
                            } else if (result.action === 'removed') {
                                alert('Книга видалена зі списку бажань!');
                                button.classList.remove('active-favorite');
                            } else if (result.action === 'already_added') {
                                alert(result.message); // Повідомлення, якщо книга вже додана
                            }
                        } else {
                            alert('Помилка при роботі зі списком бажань: ' + (result.message || result.error || 'Невідома помилка.'));
                        }
                    } catch (error) {
                        alert('Щось пішло не так при роботі зі списком бажань...');
                    }
                });
            });
        });
    </script>
</body>
</html>