<?php

session_start();
ini_set('display_errors', 1); // Показувати помилки
ini_set('display_startup_errors', 1); // Показувати помилки при старті
error_reporting(E_ALL); // Всі помилки

// --- Підключення необхідних файлів ---
require_once __DIR__ . '/../app/Database/Connection.php';

// Моделі
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Models/Rating.php';
require_once __DIR__ . '/../app/Models/WishlistItem.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/CartItem.php';

// Репозиторії
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/IRatingRepository.php';
require_once __DIR__ . '/../app/Repositories/RatingRepository.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Repositories/IWishlistRepository.php';
require_once __DIR__ . '/../app/Repositories/WishlistRepository.php';
require_once __DIR__ . '/../app/Repositories/ProfileRepository.php'; // Використовуємо для отримання улюблених книг

// Сервіси
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Services/IRatingService.php';
require_once __DIR__ . '/../app/Services/RatingService.php';
require_once __DIR__ . '/../app/Services/IWishlistService.php';
require_once __DIR__ . '/../app/Services/WishlistService.php';
require_once __DIR__ . '/../app/Services/ProfileService.php'; // Використовуємо для отримання улюблених книг

// Контролери
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';
require_once __DIR__ . '/../app/Controllers/CartController.php';

// Утиліти
require_once __DIR__ . '/auth_utils.php'; // Для is_logged_in() та is_admin()

// --- Використання класів та інтерфейсів (use statements) ---
use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Repositories\RatingRepository;
use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Repositories\WishlistRepository;
use App\Repositories\IBookstoreRepository;
use App\Repositories\IRatingRepository;
use App\Repositories\IWishlistRepository;
use App\Services\BookstoreService;
use App\Services\RatingService;
use App\Services\WishlistService;
use App\Services\IBookstoreService;
use App\Services\IRatingService;
use App\Services\IWishlistService;
use App\Controllers\BookstoreController;
use App\Controllers\CartController;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Rating;
use App\Models\WishlistItem;
use App\Repositories\ProfileRepository;
use App\Services\ProfileService;


// --- Ініціалізація об'єктів ---
try {
    $db = Connection::getInstance()->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("set names utf8");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Помилка підключення до бази даних: " . $e->getMessage());
}

$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);

$ratingRepository = new RatingRepository($db);
$ratingService = new RatingService($ratingRepository);

$wishlistRepository = new WishlistRepository($db, $bookstoreRepository); // Потребує BookstoreRepository
$wishlistService = new WishlistService($wishlistRepository);

$profileRepository = new ProfileRepository($db);
$profileService = new ProfileService($profileRepository);

$controller = new BookstoreController($bookstoreService, $ratingService);

// --- Перевірка на AJAX-запит та перенаправлення ---
// Якщо це POST-запит, імовірно, це AJAX-запит для додавання/видалення з улюблених
// Важливо: popular.php більше не обробляє AJAX-запити для wishlist.
// Ці запити тепер обробляються файлом add_to_favorites.php.
// Цей блок лише для прикладу, якщо б popular.php мав обробляти інші POST-запити.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Якщо ви відправляєте AJAX-запит на popular.php для чогось ІНШОГО, ніж wishlist,
    // то обробляйте його тут. В іншому випадку, цей блок може бути видалений,
    // або тут має бути логіка перенаправлення на add_to_favorites.php якщо action === wishlist
    // АБО, якщо це просто "помилковий" POST-запит без відповідної AJAX-логіки.

    // Для уникнення помилок в консолі при некоректних POST-запитах:
    error_log("Unhandled POST request to popular.php. Request data: " . file_get_contents('php://input'));
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Невідомий POST-запит до popular.php.']);
    exit();
}


// --- Логіка сторінки (для звичайного завантаження HTML) ---
// Цей код виконується ТІЛЬКИ якщо запит не був AJAX POST.

// Перевірка ролі користувача (якщо потрібно)
if (is_logged_in() && is_admin()) {
    header("Location: admin_panel.php");
    exit;
}

$limit = 10;
$orderBy = $_GET['sort_by'] ?? 'orders';
$validSortOptions = ['wishlist', 'ratings', 'orders'];
if (!in_array($orderBy, $validSortOptions)) {
    $orderBy = 'orders';
}

try {
    $popularBooks = $controller->showPopularBooks($limit, $orderBy);
} catch (Exception $e) {
    error_log("Error loading popular books: " . $e->getMessage());
    die("Помилка при завантаженні популярних книг: " . $e->getMessage());
}

// Отримання списку бажань користувача для відображення HTML-розмітки
$userWishlistItems = [];
$bookIdsInWishlist = [];
$phpUserId = $_SESSION['user_id'] ?? null; // Завжди визначаємо $phpUserId тут

if ($phpUserId !== null) {
    try {
        // ВИКОРИСТОВУЄМО WishlistService для отримання списку бажань
        $userWishlistItems = $wishlistService->getUserWishlist($phpUserId);
        $bookIdsInWishlist = array_map(function($item) { return $item->getId(); }, $userWishlistItems);
    } catch (PDOException $e) {
        error_log("Database error fetching user wishlist on popular page: " . $e->getMessage());
        // Можна ігнорувати або показати повідомлення користувачеві
    }
}
$cartItems = [];
if (isset($_SESSION['user_id'])) {
    try {
        $cartRepository = new CartRepository($db);
        $cartService = new CartService($cartRepository, $bookstoreRepository);
        $cartController = new CartController($cartService);
        $cartItems = $cartController->fetchUserCart($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Помилка отримання кошика на search.php: " . $e->getMessage());
        // Можливо, варто показати якесь повідомлення користувачеві
    }
}

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Популярні книги</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style> main.container {
            padding-top: 15px;
        }
       
        .htext {
            margin-top: 100px;
            text-align: center;
            margin-bottom: 20px;
        }
        .books-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .popular-book-item.book {
            flex: 0 0 auto;
            width: calc(25% - 15px);
            box-sizing: border-box;
            min-height: 500px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .popular-book-item.book:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* --- СТИЛІ ДЛЯ ЦЕНТРУВАННЯ ЦІНИ --- */
        .book-prices {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        /* Стилі для обкладинки книги */
        .book-cover-container {
            position: relative;
            width: 100%;
            overflow: hidden;
        }

        /* Стилі для інформаційного блоку книги */
        .book-info {
            text-align: center;
            align-items: center;
        }

        /* --- НОВІ СТИЛІ ДЛЯ ПЛАВНОЇ ПОЯВИ КНОПКИ --- */
        .book-actions {
            /* Початковий стан: приховано */
            opacity: 0;
            transform: translateY(10px); /* Зміщення кнопки вниз */
            transition: opacity 0.3s ease, transform 0.3s ease; /* Плавний перехід */
            padding-bottom: 15px; /* Забезпечуємо відступ знизу, коли кнопка з'явиться */
            width: 100%; /* Щоб кнопка займала всю ширину картки для центрування */
            display: flex;
            justify-content: center; /* Центруємо кнопку по горизонталі */
        }

        .popular-book-item.book:hover .book-actions {
            /* При наведенні: показуємо та піднімаємо */
            opacity: 1;
            transform: translateY(0);
        }

        .order-button {
            /* Додаткові стилі для самої кнопки, якщо потрібно */
            background-color: #007bff; /* Приклад кольору */
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s ease;
        }

        .order-button:hover {
            background-color: #0056b3;
        }

        .order-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        /* --- КІНЕЦЬ НОВИХ СТИЛІВ --- */

       
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars(json_encode($phpUserId)); ?>">
<header>
    <div class="container nav-container">
        <nav>
            <ul class="nav-left">
                <li class="categories-dropdown">
                    <button id="toggleSidebar" class="category-button">Категорії книг</button>
                    <div id="sidebar" class="sidebar">
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
                </li>
                <li><a href="index.php">Головна</a></li>
                <li><a href="discounts.php">Знижки</a></li>
                <li><a href="recommendation_test.php">Підбір книги</a></li>
            </ul>
            <div class="nav-right">
                <form class="search-form" method="GET" action="search.php">
                    <input type="text" name="query" placeholder="Знайти книжку...">
                    <button type="submit">🔍</button>
                </form>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cart.php" class="cart-link">
                        🛒<span id="cart-count"><?= count($cartItems); ?></span>
                    </a>
                    <div class="auth-section">
                        <a href="profile.php" class="profile-link" title="Мій профіль">
                            <svg class="profile-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
                            </svg>
                            <span class="username-display"><?= htmlspecialchars($_SESSION['username'] ?? 'Користувач'); ?></span>
                        </a>
                        <button class="logout-btn" onclick="window.location.href='logout.php'">Вийти</button>
                    </div>
                <?php else: ?>
                    <div class="auth-section">
                        <button class="login-btn" onclick="window.location.href='login.php'">Увійти</button>
                        <button class="register-btn" onclick="window.location.href='register.php'">Зареєструватися</button>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<main class="container">
    <h1 class="htext">Популярні книги</h1>

    <div class="sort-options">
        Сортувати за:
        <a href="?sort_by=orders" class="<?= ($orderBy === 'orders') ? 'active' : ''; ?>">Кількістю замовлень</a>
        <a href="?sort_by=ratings" class="<?= ($orderBy === 'ratings') ? 'active' : ''; ?>">Оцінками</a>
        <a href="?sort_by=wishlist" class="<?= ($orderBy === 'wishlist') ? 'active' : ''; ?>">Списком бажань</a>
    </div>

    <div class="books-grid">
        <?php if (empty($popularBooks)): ?>
            <p>Немає популярних книг.</p>
        <?php else: ?>
            <?php foreach ($popularBooks as $book): ?>
                <div class="popular-book-item book">
                    <div class="book-cover-container">
                        <?php
                        $bookIdsInWishlist = $bookIdsInWishlist ?? [];
                        $isFavorite = in_array($book->getId(), $bookIdsInWishlist);
                        ?>

                        <?php if ($phpUserId !== null): ?>
                            <button class="wishlist-button <?= $isFavorite ? 'active-favorite' : ''; ?>" data-id="<?= htmlspecialchars($book->getId()); ?>" title="<?= $isFavorite ? 'Видалити зі списку бажань' : 'Додати до списку бажань'; ?>">
                                <svg viewBox="0 0 24 24" style="width: 24px; height: 24px;">
                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                            </button>
                        <?php else: ?>
                            <span class="wishlist-text-login">
                                <a href="login.php">Увійдіть</a>, щоб додати до улюблених
                            </span>
                        <?php endif; ?>

                        <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>" class="book-link">
                            <img src="images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>">
                        </a>
                        <?php if (method_exists($book, 'getDiscount') && $book->getDiscount() > 0): ?>
                            <span class="discount-badge">-<?= htmlspecialchars($book->getDiscount()); ?>%</span>
                        <?php endif; ?>
                    </div>
                    <div class="book-info">
                        <h3 class="book-title"><a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>"><?= htmlspecialchars($book->getTitle()); ?></a></h3>
                        <p class="book-author"><?= htmlspecialchars($book->getAuthor()); ?></p>
                        <div class="book-prices">
                            <?php if (method_exists($book, 'getDiscount') && $book->getDiscount() > 0): ?>
                                <span class="original-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</span>
                                <span class="sale-price"><?= htmlspecialchars(number_format($book->getPrice() * (1 - $book->getDiscount() / 100), 2)); ?> грн</span>
                            <?php else: ?>
                                <span class="book-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</span>
                            <?php endif; ?>
                        </div>
                        <div class="availability-status">
                            <?php
                            $quantity = method_exists($book, 'getQuantity') ? $book->getQuantity() : 10;
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
                    <div class="book-stat-group">
                        <div class="book-stat">Рейтинг: <?= number_format($book->getAverageRating(), 1); ?></div>
                        <div class="book-stat">Замовлено: <?= htmlspecialchars($book->getTotalOrderedQuantity()); ?></div>
                        <div class="book-stat">У бажаннях: <?= htmlspecialchars($book->getWishlistCount()); ?></div>
                    </div>
                    <div class="book-actions">
                        <button class="order-button" data-id="<?= htmlspecialchars($book->getId()); ?>"
                                <?= ($quantity <= 0) ? 'disabled title="Немає в наявності"' : ''; ?>>
                            До кошика
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
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
    let userId = null;
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded fired.');

        const toggleSidebarBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');

        if (toggleSidebarBtn && sidebar) {
            function toggleSidebar() {
                sidebar.classList.toggle('show');
            }

            toggleSidebarBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                toggleSidebar();
            });

            document.addEventListener('click', function(event) {
                if (!sidebar.contains(event.target) && !toggleSidebarBtn.contains(event.target)) {
                    if (sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });

            sidebar.addEventListener('click', function(event) {
                event.stopPropagation();
            });

        } else {
            console.error('ERROR: Sidebar elements (toggleSidebarBtn or sidebar) NOT found. Check IDs and HTML structure.');
        }

        const userIdRaw = document.body.dataset.userId;
        try {
            if (userIdRaw) {
                userId = JSON.parse(userIdRaw);
            }
        } catch (e) {
            console.error('Failed to parse userId from data-attribute:', e);
            userId = null;
        }

        console.log('JS userId (popular.php):', userId);

        const wishlistButtons = document.querySelectorAll('.wishlist-button');
        const orderButtons = document.querySelectorAll('.order-button');

        orderButtons.forEach(button => {
            button.addEventListener('click', async () => {
                const bookId = button.dataset.id;
                try {
                    const response = await fetch('add_to_cart.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: bookId, quantity: 1 })
                    });
                    const result = await response.json();
                    if (result.success) {
                        alert('Книга додана до кошика!');
                        const cartCount = document.getElementById('cart-count');
                        if (cartCount) {
                            cartCount.textContent = parseInt(cartCount.textContent, 10) + 1;
                        }
                    } else if (result.error === 'login_required') {
                        alert('Будь ласка, увійдіть, щоб додати книгу до кошика.');
                        window.location.href = 'login.php';
                    } else {
                        alert('Помилка: ' + (result.message || result.error || 'Невідома помилка'));
                    }
                } catch (error) {
                    console.error('Fetch error (add_to_cart):', error);
                    alert('Щось пішло не так при додаванні до кошика.');
                }
            });
        });

        wishlistButtons.forEach(button => {
            if (userId === null || userId === 'null' || userId === undefined) {
                button.disabled = true;
                button.style.opacity = '0.5';
                button.style.cursor = 'not-allowed';
                return;
            }

            button.addEventListener('click', async function() {
                const bookId = this.dataset.id;
                const currentButton = this;
                const action = currentButton.classList.contains('active-favorite') ? 'remove_from_wishlist' : 'add_to_wishlist';

                try {
                    const response = await fetch('add_to_favorites.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ book_id: bookId, action: action })
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert(result.message);
                        location.reload();
                    } else if (result.error_code === 'login_required') {
                        alert(result.message);
                        window.location.href = 'login.php';
                    } else {
                        alert('Помилка: ' + (result.message || 'Невідома помилка'));
                    }
                } catch (error) {
                    console.error('Fetch error (wishlist):', error);
                    alert('Щось пішло не так: ' + error.message);
                }
            });
        });
    });
</script>
</body>
</html>