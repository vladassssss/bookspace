<?php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Підключення файлів ---
require_once __DIR__ . '/../app/Database/Connection.php';

// Моделі
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Models/Rating.php';
require_once __DIR__ . '/../app/Models/WishlistItem.php';

// Інтерфейси та їх імплементації в папці Repositories
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/IRatingRepository.php';
require_once __DIR__ . '/../app/Repositories/RatingRepository.php';
require_once __DIR__ . '/../app/Repositories/IWishlistRepository.php';
require_once __DIR__ . '/../app/Repositories/WishlistRepository.php';

// Інтерфейси та їх імплементації в папці Services
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Services/IRatingService.php';
require_once __DIR__ . '/../app/Services/RatingService.php';
require_once __DIR__ . '/../app/Services/IWishlistService.php';
require_once __DIR__ . '/../app/Services/WishlistService.php';

// Контролери
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';

// --- Використання класів та інтерфейсів (use statements) ---
use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Repositories\RatingRepository;
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
use App\Models\Book;
use App\Models\Rating;
use App\Models\WishlistItem;


// --- Ініціалізація об'єктів ---
$db = Connection::getInstance()->getConnection();

$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);

$ratingRepository = new RatingRepository($db);
$ratingService = new RatingService($ratingRepository);

$wishlistRepository = new WishlistRepository($db, $bookstoreRepository);
$wishlistService = new WishlistService($wishlistRepository);

$controller = new BookstoreController($bookstoreService, $ratingService);

// --- Обробка логіки Wishlist (для AJAX-запитів) ---
if (isset($_SESSION['user_id']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_wishlist') {
    error_log("AJAX request received: User ID=" . $_SESSION['user_id'] . ", Book ID=" . ($_POST['book_id'] ?? 'N/A') . ", Action=" . ($_POST['action'] ?? 'N/A')); // ЛОГ 1
    $userId = $_SESSION['user_id'];
    $bookIdToToggle = $_POST['book_id'] ?? null;

    header('Content-Type: application/json');

    if ($bookIdToToggle) {
        error_log("Processing book ID: " . $bookIdToToggle); // ЛОГ 2
        try {
            $isBookInWishlist = $wishlistService->isBookInWishlist($userId, (int)$bookIdToToggle);
            error_log("Result of isBookInWishlist: " . ($isBookInWishlist ? 'TRUE' : 'FALSE')); // ЛОГ 3

            if ($isBookInWishlist) {
                $removed = $wishlistService->removeItemByBookAndUser($userId, (int)$bookIdToToggle);
                error_log("Attempted to remove book. Result: " . ($removed ? 'SUCCESS' : 'FAILURE')); // ЛОГ 4
                echo json_encode(['success' => $removed, 'action' => 'removed']);
            } else {
                $added = $wishlistService->addItem($userId, (int)$bookIdToToggle);
                error_log("Attempted to add book. Result: " . ($added ? 'SUCCESS' : 'FAILURE')); // ЛОГ 5
                echo json_encode(['success' => (bool)$added, 'action' => 'added']);
            }
            exit();
        } catch (Exception $e) {
            error_log("CATCH BLOCK - Error in wishlist toggle: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine()); // ЛОГ 6
            echo json_encode(['success' => false, 'message' => 'Помилка на сервері: ' . $e->getMessage()]);
            exit();
        }
    }
    error_log("Book ID was missing in AJAX request."); // ЛОГ 7
    echo json_encode(['success' => false, 'message' => 'Не вдалося обробити запит: Book ID відсутній.']);
    exit();
}

// --- Логіка сторінки (для звичайного завантаження HTML) ---
$limit = 10;
$orderBy = $_GET['sort_by'] ?? 'orders';
$validSortOptions = ['wishlist', 'ratings', 'orders'];
if (!in_array($orderBy, $validSortOptions)) {
    $orderBy = 'orders';
}

try {
    $popularBooks = $controller->showPopularBooks($limit, $orderBy);
} catch (Exception $e) {
    die("Помилка при завантаженні популярних книг: " . $e->getMessage());
}

// Отримання списку бажань користувача для відображення HTML-розмітки
$userWishlistItems = [];
$bookIdsInWishlist = [];
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userWishlistItems = $wishlistRepository->getUserWishlist($userId);
    $bookIdsInWishlist = array_map(function($item) { return $item->getBookId(); }, $userWishlistItems);
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Популярні книги</title>
    <link rel="stylesheet" href="styles1.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body data-user-id="<?php echo htmlspecialchars(json_encode($phpUserId)); ?>">
    <header>
        <div class="nav-container">
            <nav>
                <ul class="nav-left">
                    <li><a href="index.php">Головна</a></li>
                    <li><a href="allbooks.php">Усі книги</a></li>
                    <li><a href="allbooks.php?genre=Детектив">Детектив</a></li>
                    <li><a href="allbooks.php?genre=Фантастика">Фантастика</a></li>
                    <li><a href="allbooks.php?genre=Наукова фантастика">Наукова фантастика</a></li>
                    <li><a href="allbooks.php?genre=Жахи">Жахи</a></li>
                    <li><a href="popular.php">Популярні</a></li>
                    <li><a href="discounted.php">Знижки</a></li>
                </ul>
                <div class="nav-right">
                    <form class="search-form" action="allbooks.php" method="get">
                        <input type="text" name="search" placeholder="Знайти книжку...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    <a href="cart.php" class="cart-link">
                        <i class="fas fa-shopping-cart"></i> Кошик
                        <span id="cart-count">0</span>
                    </a>
                    <div class="auth-section">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="profile.php" class="profile-link">
                                <svg class="profile-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                Привіт, <?= htmlspecialchars($_SESSION['username'] ?? 'Користувач'); ?>!
                            </a>
                            <button class="logout-btn" onclick="location.href='logout.php'">Вийти</button>
                        <?php else: ?>
                            <button class="login-btn" onclick="location.href='login.php'">Увійти</button>
                            <button class="register-btn" onclick="location.href='register.php'">Зареєструватися</button>
                        <?php endif; ?>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main>
        <h1 class="htext">Популярні книги</h1>

        <div class="sort-options">
            Сортувати за:
            <a href="?sort_by=orders" class="<?= ($orderBy === 'orders') ? 'active' : ''; ?>">Кількістю замовлень</a>
            <a href="?sort_by=ratings" class="<?= ($orderBy === 'ratings') ? 'active' : ''; ?>">Оцінками</a>
            <a href="?sort_by=wishlist" class="<?= ($orderBy === 'wishlist') ? 'active' : ''; ?>">Списком бажань</a>
        </div>

        <div class="books-container">
            <?php if (empty($popularBooks)): ?>
                <p>Немає популярних книг.</p>
            <?php else: ?>
                <?php foreach ($popularBooks as $book): ?>
                    <div class="book">
                        <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>" class="book-link">
                            <img src="images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>">
                            <div class="book-title"><?= htmlspecialchars($book->getTitle()); ?></div>
                            <div class="book-author"><?= htmlspecialchars($book->getAuthor()); ?></div>
                        </a>
                        <div class="book-price"><?= htmlspecialchars($book->getPrice()); ?> грн</div>

                        <div class="book-stat">Рейтинг: <?= number_format($book->averageRating ?? 0, 1); ?></div>
                        <div class="book-stat">Замовлено: <?= htmlspecialchars($book->totalOrderedQuantity ?? 0); ?></div>
                        <div class="book-stat">У бажаннях: <?= htmlspecialchars($book->wishlistCount ?? 0); ?></div>

                        <div class="book-buttons">
                            <button class="order-button" data-book-id="<?= htmlspecialchars($book->getId()); ?>">До кошика</button>
                            <?php if (isset($_SESSION['user_id'])): ?>
                     <button class="wishlist-button <?php echo (in_array($book->getId(), $bookIdsInWishlist) ? 'active' : ''); ?>" data-book-id="<?= htmlspecialchars($book->getId()); ?>" title="<?= (in_array($book->getId(), $bookIdsInWishlist) ? 'Видалити зі списку бажань' : 'Додати до списку бажань'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                            </button>
                            <?php else: ?>
                                <span class="wishlist-text-login">Увійдіть, щоб додати до улюблених <a href="login.php">Увійти</a></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        © 2025 Книгарня
    </footer>
<?php
// ... ваш PHP-код ...
$userId = $_SESSION['user_id'] ?? 'null';
?>
<script>
// <--- ЗМІНЕНО ТУТ: Читаємо userId з data-атрибута body
const userId = JSON.parse(document.body.dataset.userId); // userId буде null або число
console.log('JS userId:', userId);

document.addEventListener('DOMContentLoaded', function() {
    const wishlistButtons = document.querySelectorAll('.wishlist-button');

    // --- ОСНОВНИЙ КОД ДЛЯ КНОПОК-СЕРДЕЦЬ ---
    wishlistButtons.forEach(button => {
        // Перевіряємо, чи користувач увійшов
        if (userId === null || userId === 'null') { // Перевірка на null або рядок "null"
            // Якщо користувач не увійшов, відключаємо кнопки або додаємо клас для візуального відключення
            button.disabled = true;
            button.title = 'Увійдіть, щоб додати до списку бажань';
            // Можливо, додати клас для візуального відключення, якщо це необхідно
            // button.classList.add('disabled-wishlist-button');
            return; // Виходимо, щоб не додавати обробник подій
        }

        button.addEventListener('click', async function() {
            const bookId = this.dataset.bookId;
            const currentButton = this;

            const action = currentButton.classList.contains('active') ? 'remove_from_wishlist' : 'add_to_wishlist';

            try {
                // Шлях до popular.php, оскільки він сам обробляє AJAX-запит
                const response = await fetch('popular.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', // Відправляємо JSON
                    },
                    body: JSON.stringify({ // Відправляємо дані як JSON
                        action: 'toggle_wishlist', // PHP-логіка очікує 'toggle_wishlist'
                        user_id: userId, // Передаємо userId з JS
                        book_id: bookId,
                        current_action: action // Передаємо поточну дію
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Оновити вигляд кнопки
                    currentButton.classList.toggle('active');
                    if (result.action === 'added') { // Використовуємо result.action з відповіді сервера
                        currentButton.title = 'Видалити зі списку бажань';
                    } else { // result.action === 'removed'
                        currentButton.title = 'Додати до списку бажань';
                    }
                    alert(result.message); // Виводимо повідомлення від сервера
                } else {
                    alert('Помилка: ' + (result.message || 'Невідома помилка'));
                }
            } catch (error) {
                console.error('Fetch error:', error);
                alert('Щось пішло не так: ' + error.message);
            }
        });
    });
});
</script>
</body>
</html>