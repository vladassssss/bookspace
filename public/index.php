<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
error_log("index.php accessed. User ID in session: " . ($_SESSION['user_id'] ?? 'N/A'));
// --- Включення необхідних файлів вручну ---
// Базові
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/auth_utils.php'; // Переконайтеся, що шлях правильний

// Моделі (Додано Book, Rating, WishlistItem, User - якщо використовуються)
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Models/Rating.php'; // Додано
require_once __DIR__ . '/../app/Models/WishlistItem.php'; // Додано, якщо потрібно для wishlist в index.php

// Репозиторії (додано Rating та Wishlist)
require_once __DIR__ . '/../app/Repositories/ProfileRepository.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/IRatingRepository.php'; // Додано
require_once __DIR__ . '/../app/Repositories/RatingRepository.php';   // Додано
require_once __DIR__ . '/../app/Repositories/IWishlistRepository.php'; // Додано
require_once __DIR__ . '/../app/Repositories/WishlistRepository.php';   // Додано
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';


// Сервіси (додано Rating та Wishlist)
require_once __DIR__ . '/../app/Services/ProfileService.php';
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Services/IRatingService.php';     // Додано
require_once __DIR__ . '/../app/Services/RatingService.php';       // Додано
require_once __DIR__ . '/../app/Services/IWishlistService.php';   // Додано
require_once __DIR__ . '/../app/Services/WishlistService.php';     // Додано
require_once __DIR__ . '/../app/Services/CartService.php';

// Контролери (додано Bookstore, Cart, Profile)
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';
require_once __DIR__ . '/../app/Controllers/CartController.php';
require_once __DIR__ . '/../app/Controllers/ProfileController.php';


// Автозавантаження класів (як було раніше)
// Цей автозавантажувач може частково замінити require_once,
// але для критичних файлів, що викликають проблеми, краще залишити require_once
// або переконатися, що автозавантажувач правильно налаштований для всіх файлів.
// Якщо ви використовуєте PSR-4, цей блок коректний.
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("Autoloader: File not found: " . $file);
    }
});

// --- Використання класів та інтерфейсів (use statements) ---
use App\Database\Connection;
use App\Models\User; // Додано
use App\Models\Book; // Додано
use App\Models\Rating; // Додано
use App\Models\WishlistItem; // Додано

use App\Repositories\BookstoreRepository;
use App\Repositories\IRatingRepository; // Додано
use App\Repositories\RatingRepository;   // Додано
use App\Repositories\IWishlistRepository; // Додано
use App\Repositories\WishlistRepository;   // Додано
use App\Repositories\CartRepository;
use App\Repositories\ProfileRepository;
use App\Repositories\IBookstoreRepository; // Додано (якщо використовується для типування)


use App\Services\BookstoreService;
use App\Services\IRatingService;     // Додано
use App\Services\RatingService;       // Додано
use App\Services\IWishlistService;   // Додано
use App\Services\WishlistService;     // Додано
use App\Services\CartService;
use App\Services\ProfileService;
use App\Services\IBookstoreService; // Додано (якщо використовується для типування)


use App\Controllers\BookstoreController;
use App\Controllers\CartController;
use App\Controllers\ProfileController;


// Підключення до бази даних
// --- Ініціалізація репозиторіїв, сервісів і контролерів ---
$db = null;
$books = [];
$favoriteBookIds = []; // Це наш цільовий масив ID книг
$cartItems = [];

try {
    $db = Connection::getInstance()->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("set names utf8");

    // Ініціалізація репозиторіїв
    $bookstoreRepository = new BookstoreRepository($db);
    $cartRepository = new CartRepository($db);
    $profileRepository = new ProfileRepository($db);
    $ratingRepository = new RatingRepository($db);
    $wishlistRepository = new WishlistRepository($db, $bookstoreRepository);

    // Ініціалізація сервісів
    $bookstoreService = new BookstoreService($bookstoreRepository);
    $cartService = new CartService($cartRepository, $bookstoreRepository);
    $profileService = new ProfileService($profileRepository);
    $ratingService = new RatingService($ratingRepository);
    $wishlistService = new WishlistService($wishlistRepository); // Тепер WishlistService працює з WishlistRepository, який повертає Book

    // Ініціалізація контролерів
    $bookstoreController = new BookstoreController($bookstoreService, $ratingService);
    $cartController = new CartController($cartService);
    $profileController = new ProfileController($profileService);

    // Отримання ID улюблених книг для поточного користувача
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        error_log("DEBUG: User is logged in. User ID: " . $userId);

        // Отримуємо весь список бажань користувача
        // ТЕПЕР wishlistService->getUserWishlist ПОВЕРТАЄ МАСИВ ОБ'ЄКТІВ Book!
        $userFavoriteBooks = $wishlistService->getUserWishlist($userId);
        error_log("DEBUG: getUserWishlist returned " . count($userFavoriteBooks) . " App\\Models\\Book items.");

        // Витягуємо лише ID книг з об'єктів Book
        foreach ($userFavoriteBooks as $book) {
            // Перевіряємо, чи це об'єкт Book і чи має він метод getId()
            if ($book instanceof App\Models\Book && method_exists($book, 'getId')) {
                $favoriteBookIds[] = $book->getId();
            } else {
                // Це не повинно відбуватися, якщо WishlistRepository::getUserWishlist коректний
                error_log("DEBUG: Unexpected object type in userFavoriteBooks or missing getId method. Object type: " . (is_object($book) ? get_class($book) : gettype($book)));
            }
        }
        error_log("DEBUG: favoriteBookIds after processing: " . implode(', ', $favoriteBookIds));
    } else {
        error_log("DEBUG: User not logged in. Skipping wishlist fetch.");
    }

    // Отримання даних кошика
    if (isset($_SESSION['user_id'])) {
        $cartItems = $cartController->fetchUserCart($_SESSION['user_id']);
    }

    // Завантаження книг для відображення на сторінці
    $limit = 10;
    $genre = isset($_GET['genre']) ? $_GET['genre'] : null;
    $books = $bookstoreController->showBooksPage($limit, $genre);

} catch (PDOException $e) {
    error_log("Database error on index page: " . $e->getMessage());
    die("Помилка бази даних: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General error on index page: " . $e->getMessage());
    die("Виникла невідома помилка: " . $e->getMessage());
}

// Перевірка ролі користувача та перенаправлення
if (is_logged_in() && is_admin()) {
    header("Location: admin_panel.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Книгарня</title>
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
                <li>
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
                </li>
                <li><a href="popular.php">Популярне</a></li>
                <li><a href="discounts.php">Знижки</a></li>
                <li><a href="recommendation_test.php">Підбір книги</a></li>
            </ul>
            <div class="nav-right">
                <form class="search-form" method="GET" action="search.php">
                    <input type="text" name="query" placeholder="Знайти книжку...">
                    <button type="submit">🔍</button>
                </form>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="cart.php" class="cart-link" title="Мій кошик">
                        🛒<span id="cart-count"><?= count($cartItems); ?></span>
                    </a>
                    <div class="auth-section">
                        <a href="profile.php" class="profile-link" title="Мій профіль">
                            <svg class="profile-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
                            </svg>
                            <span class="username-display"><?= htmlspecialchars($_SESSION['username']); ?></span>
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
    <div class="hero">
        <img src="images/peo.jpg" alt="Зображення магазину книг" class="background-img">
        <div class="hero-text">
            <h1>Відкрийте для себе безмежний світ книг у нашому магазині!</h1>
            <h2>Наш магазин пропонує широкий вибір книг на будь-який смак. Зручний інтерфейс дозволяє легко знаходити та замовляти улюблені видання.</h2>
        </div>
    </div>
    <div class="htext">Вас може зацікавити</div>
    <div class="carousel-container">
        <div class="slider-track">
        <?php if (empty($books)): ?>
            <p>Книга не знайдена</p>
        <?php else: ?>
            <?php foreach ($books as $book): ?>
                <div class="book">
    <?php if (isset($_SESSION['user_id'])): ?>
        <button class="wishlist-button <?php echo in_array($book->getId(), $favoriteBookIds) ? 'active-favorite' : ''; ?>" data-id="<?= htmlspecialchars($book->getId()); ?>" title="Додати до улюблених">
            <svg viewBox="0 0 24 24" style="width: 20px; height: 20px;">
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
      <h3 style="margin-top: 10px;"><?= htmlspecialchars($book->getTitle()); ?></h3>
                    <p><?= htmlspecialchars($book->getAuthor()); ?></p>
                    <p class="book-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</p> <?php if ($book->getDiscount() > 0): ?>
                        <p class="discount-label">Знижка: <?= htmlspecialchars($book->getDiscount()); ?>%</p>
                        <p class="discounted-price">Ціна зі знижкою: <?= htmlspecialchars(number_format($book->getDiscountedPrice(), 2)); ?> грн</p>
                    <?php endif; ?>

                    <div class="availability-status">
                        <?php
                        $quantity = $book->getQuantity();
                        if ($quantity > 0):
                        ?>
                            <span class="status-in-stock">
                                <i class="fas fa-check-circle"></i> В наявності
                                <?php if ($quantity <= 5 && $quantity > 0): // Можна додати для "мало книг" ?>
                                    <span class="low-stock-warning">(Залишилось: <?= $quantity ?>)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="status-out-of-stock">
                                <i class="fas fa-times-circle"></i> Немає в наявності
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="book-buttons">
                        <button class="order-button" data-id="<?= htmlspecialchars($book->getId()); ?>"
                            <?php if ($quantity <= 0): ?>disabled title="Немає в наявності"<?php endif; ?>>
                            До кошика
                        </button>
    </div>
</div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div class="view-all-container">
            <button id="viewAllBooks" type="button" onclick="window.location.href='allbooks.php'">
                Переглянути всі книги
            </button>
        </div>
        <button class="slider-btn prev" onclick="scrollSlider(-1)">←</button>
        <button class="slider-btn next" onclick="scrollSlider(1)">→</button>
    </div>

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
    // Обробник для кнопки "Переглянути всі книги"
    const viewAllBtn = document.getElementById("viewAllBooks");
    if(viewAllBtn) {
        viewAllBtn.addEventListener("click", function() {
            window.location.href = "allbooks.php";
        });
    }

    // Отримуємо елемент для відображення кількості товарів у кошику
    const cartCountSpan = document.getElementById('cart-count');
    console.log('DOMContentLoaded: Cart count span found:', !!cartCountSpan); // Перевіряємо, чи елемент лічильника знайдено

    // Обробка кліків по кнопці "Замовити" (Додати до кошика)
    document.querySelectorAll('.order-button').forEach(button => {
        button.addEventListener('click', async () => {
            const bookId = button.getAttribute('data-id');
            // Перевірка bookId
            if (!bookId) {
                console.error('Book ID is missing for this button:', button);
                alert('Помилка: Не вдалося отримати ID книги.');
                return;
            }
            console.log(`Order button clicked for Book ID: ${bookId}`);

            // Деактивуємо кнопку, щоб уникнути подвійних кліків
            button.disabled = true;
            button.textContent = 'Додаємо...';

            try {
                const fetchUrl = 'add_to_cart.php'; // Або повний шлях, якщо це не працює: '/bookshop/bookshop/public/add_to_cart.php'
                const requestBody = JSON.stringify({ id: bookId, quantity: 1 });

                console.log(`DEBUG: Final Fetch URL: ${fetchUrl}`);
                console.log(`DEBUG: Request Method: POST`);
                console.log(`DEBUG: Request Headers: {'Content-Type': 'application/json'}`);
                console.log(`DEBUG: Request Body: ${requestBody}`);

                const response = await fetch(fetchUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: requestBody
                });

                console.log('Fetch response received. Status:', response.status, response.statusText);

                // Перевіряємо, чи HTTP-статус відповіді OK (200-299)
                if (!response.ok) {
                    const errorText = await response.text(); // Читаємо відповідь як текст, щоб отримати деталі помилки
                    console.error('HTTP Error Status:', response.status, response.statusText);
                    console.error('HTTP Error Response Text (Raw):', errorText);
                    alert(`Помилка сервера: ${response.status} - ${response.statusText}. Деталі: ${errorText.substring(0, 100)}...`);
                    return; // Зупиняємо виконання, якщо відповідь не ok
                }

                const rawResponseText = await response.text(); // Читаємо відповідь як необроблений текст
                console.log('Raw response text from add_to_cart.php:', rawResponseText);

                let result;
                try {
                    // Спроба розпарсити текст відповіді як JSON
                    result = JSON.parse(rawResponseText);
                    console.log('Successfully parsed JSON result:', result);
                } catch (jsonError) {
                    // Якщо розбір JSON не вдався, виводимо детальну помилку в консоль
                    console.error('JSON parsing error caught:', jsonError);
                    console.error('Problematic JSON string:', rawResponseText);
                    alert('Помилка обробки даних від сервера. Не вдалося розібрати відповідь.');
                    return; // Зупиняємо виконання, якщо JSON не парситься
                }

                // Якщо код дійшов сюди, JSON розпарсено успішно
                if (result.success) {
                    alert(result.message || 'Книга додана до кошика!'); // Використовуємо повідомлення з сервера
                    if (cartCountSpan) { // Перевіряємо, чи існує елемент лічильника кошика
                        cartCountSpan.textContent = result.cart_total_items; // ОНОВЛЮЄМО ЗНАЧЕННЯ З СЕРВЕРА
                        console.log('Cart count updated to:', result.cart_total_items);
                    } else {
                        console.warn('Cart count element (ID "cart-count") not found. Cannot update display.');
                    }
                    // Додаємо анімацію до іконки кошика
                    const cartLink = document.querySelector('.cart-link');
                    if (cartLink) {
                        cartLink.classList.add('bump');
                        setTimeout(() => {
                            cartLink.classList.remove('bump');
                        }, 300);
                    }
                } else if (result.error === 'login_required') {
                    alert('Будь ласка, увійдіть, щоб замовити.');
                    window.location.href = 'login.php';
                } else {
                    // Якщо результат не успішний, показуємо повідомлення про помилку з сервера
                    alert('Помилка: ' + (result.message || result.error || 'Невідома помилка.'));
                    console.error('Server reported an error (result.success is false):', result);
                }
           } catch (error) {
                console.error('Caught an unexpected Fetch operation error:', error ? error.message || error : 'No error details available.');
                alert('Щось пішло не так під час виконання запиту до сервера... Перевірте консоль для деталей.');
    
            } finally {
                // Після завершення запиту, повертаємо кнопку в початковий стан з невеликою затримкою
                setTimeout(() => {
                    // Додаємо перевірку, чи кнопка все ще існує в DOM
                    if (document.body.contains(button)) {
                        button.disabled = false;
                        button.textContent = 'До кошика';
                        console.log('Button state reset successfully.');
                    } else {
                        console.warn('Button no longer exists in DOM, cannot reset its state.');
                    }
                }, 500); // Затримка на 0.5 секунди
            
            }
        });
    });

    // Обробка кліків по кнопці "Додати до списку бажань"
    document.querySelectorAll('.wishlist-button').forEach(button => {
        button.addEventListener('click', async (event) => {
            event.preventDefault(); // Запобігти стандартній поведінці
            event.stopPropagation(); // Запобігти "спливанню" події

            const bookId = button.getAttribute('data-id');
            const isCurrentlyFavorite = button.classList.contains('active-favorite');
            const action = isCurrentlyFavorite ? 'remove_from_wishlist' : 'add_to_wishlist';

            try {
                const response = await fetch('add_to_favorites.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ book_id: bookId, action: action })
                });
                const result = await response.json(); // Очікуємо чистий JSON

                if (result.success) {
                    if (result.action === 'added') { // Використовуємо 'result.action' для точної відповіді від сервера
                        alert('Книга додана до списку бажань!');
                        button.classList.add('active-favorite');
                    } else if (result.action === 'removed') {
                        alert('Книга видалена зі списку бажань!');
                        button.classList.remove('active-favorite');
                    } else if (result.action === 'already_added' || result.action === 'not_found') {
                        alert(result.message); // Повідомлення, що книга вже є або не знайдена
                    }
                } else {
                    console.error('Wishlist server reported an error:', result.message, result.error);
                    alert('Помилка при роботі зі списком бажань: ' + (result.message || result.error || 'Невідома помилка.'));
                }
            } catch (error) {
                console.error('Fetch error for wishlist:', error);
                alert('Щось пішло не так при роботі зі списком бажань...');
            }
        });
    });

    // Код для сайдбару
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    if (sidebar && toggleBtn) { // Перевірка на існування елементів
        sidebar.classList.remove('hidden'); // Забезпечити видимість при завантаженні, якщо це необхідно для анімації
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

    // Код для слайдера
    const track = document.querySelector('.slider-track');
    const prevBtn = document.querySelector('.slider-btn.prev');
    const nextBtn = document.querySelector('.slider-btn.next');
    const books = document.querySelectorAll('.book');
    // Додаємо перевірку, щоб уникнути помилок, якщо книг немає
    const bookWidth = books.length > 0 ? books[0].offsetWidth + 20 : 0; // Додаємо 20px для gap між книгами
    let currentIndex = 0;

    function updateSlider() {
        if (track && books.length > 0) { // Перевірка на існування елементів
            track.style.transform = `translateX(-${bookWidth * currentIndex}px)`;
        }
    }

    if (nextBtn && prevBtn && track && books.length > 0) { // Перевірка на існування елементів
        nextBtn.addEventListener('click', function(){
            // Обмежуємо currentIndex, щоб не виходити за межі слайдера
            // Math.floor(track.offsetWidth / bookWidth) - це приблизна кількість видимих книг
            if(currentIndex < books.length - Math.floor(track.offsetWidth / bookWidth)) {
                currentIndex++;
                updateSlider();
            }
        });
        prevBtn.addEventListener('click', function(){
            if(currentIndex > 0) {
                currentIndex--;
                updateSlider();
            }
        });
    }

    window.addEventListener('resize', updateSlider); // Оновлювати слайдер при зміні розміру вікна
    updateSlider(); // Викликати при завантаженні для початкової позиції
});
</script>
</body>
</html>