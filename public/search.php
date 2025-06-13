<?php
session_start();
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';

// НОВІ ІМПОРТИ ДЛЯ RATING SERVICE/REPOSITORY
require_once __DIR__ . '/../app/Repositories/IRatingRepository.php'; // Якщо у вас є інтерфейс
require_once __DIR__ . '/../app/Repositories/RatingRepository.php';
require_once __DIR__ . '/../app/Services/IRatingService.php'; // Якщо у вас є інтерфейс
require_once __DIR__ . '/../app/Services/RatingService.php';
require_once __DIR__ . '/../app/Models/Review.php'; // Ймовірно, потрібна модель Review для RatingService

use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Services\BookstoreService;
use App\Controllers\BookstoreController;

// НОВІ USE ІМПОРТИ
use App\Repositories\RatingRepository;
use App\Services\RatingService;
use App\Models\Review; // Якщо Review використовується в RatingService

$db = Connection::getInstance()->getConnection();

// Ініціалізація BookstoreService
$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);

// НОВА ІНІЦІАЛІЗАЦІЯ RATINGSERVICE
$ratingRepository = new RatingRepository($db); // Припускаємо, що RatingRepository потребує $db
$ratingService = new RatingService($ratingRepository); // Припускаємо, що RatingService потребує RatingRepository

// РЯДОК 19 - ЗМІНЕНО: Тепер передаємо обидва необхідні сервіси
$bookstoreController = new BookstoreController($bookstoreService, $ratingService);

// ------------------------------
// Код для отримання даних кошика (додайте цей блок)
// ------------------------------
require_once __DIR__ . '/../app/Models/CartItem.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Controllers/CartController.php';

use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Controllers\CartController;

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

// ------------------------------

$query = $_GET['query'] ?? '';
$books = $bookstoreController->searchBooks($query);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Результати пошуку</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Базові стилі для body */
    

        /* Стилі для хедера - можливо, ці стилі вже є у вашому styles.css, перенесіть їх туди */
        header {
            background-color: #333;
            color: white;
            padding: 10px 0;
            position: fixed; /* Фіксований хедер */
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000; /* Перекриває інший вміст */
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
        }

        nav ul.nav-left li {
            margin-right: 20px;
        }

        nav ul li a,
        nav button {
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        nav ul li a:hover,
        nav button:hover {
            background-color: #555;
        }

        .search-form {
            display: flex;
        }

        .search-form input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px 0 0 4px;
            outline: none;
        }

        .search-form button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .search-form button:hover {
            background-color: #0056b3;
        }

        .auth-section {
            display: flex;
            align-items: center;
            margin-left: 20px;
        }

        .auth-section span {
            color: white;
            margin-right: 15px;
        }

        .auth-section .cart-link {
            font-size: 1.2em;
            position: relative;
            margin-right: 15px;
        }

        .auth-section #cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7em;
            line-height: 1;
        }

        .auth-section .login,
        .auth-section .register,
        .auth-section .logout {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-left: 10px;
        }

        .auth-section .login:hover,
        .auth-section .register:hover,
        .auth-section .logout:hover {
            background-color: #218838;
        }

        .sidebar {
            position: absolute;
            top: 100%; /* Розміщуємо під кнопкою */
            left: 0;
            background-color: #444;
            color: white;
            padding: 10px 0;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            display: none; /* За замовчуванням прихований */
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            min-width: 150px;
        }

        .sidebar.show {
            display: block; /* Показати, коли active */
            opacity: 1;
            transform: translateY(0);
        }

        .sidebar ul {
            flex-direction: column;
            align-items: flex-start;
        }

        .sidebar ul li {
            width: 100%;
            margin: 0;
        }

        .sidebar ul li a {
            display: block;
            padding: 8px 20px;
            width: 100%;
            box-sizing: border-box;
            text-align: left;
        }

        .sidebar ul li a:hover {
            background-color: #666;
        }

        /* --- ОСНОВНИЙ КОНТЕНТ --- */
        /* Додаємо відступ зверху, щоб уникнути перекриття навігатором */
        main.container.content {
            padding-top: 80px; /* Припустимо, висота хедера ~70px. Збільшіть, якщо потрібно. */
            max-width: 1200px;
            margin: 0 auto;
            padding-left: 20px;
            padding-right: 20px;
            padding-bottom: 40px; /* Додайте відступ знизу */
            justify-content: center;
        }

        /* Заголовок результатів пошуку */
        main h1 {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 30px;
            color: #333;
            font-size: 2em;
        }

        /* Якщо нічого не знайдено */
        main p {
            text-align: center;
            font-size: 1.2em;
            color: #777;
            margin-top: 10px;
        }

        /* --- Оновлені стилі для відображення книг (активні) --- */
        

        .book-item {
            flex: 0 0 auto;
            
            box-sizing: border-box;
            
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
    width: calc(23% - 15px); /* зменшено ширину */
    min-height: 460px; /* зменшено загальну висоту */

        }
        .book-info {

    padding-bottom: 65px; /* Приблизно висота кнопки + трохи відступу */
}

        .book-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

        .book-cover-container {
   position: relative;
   width: 100%;
   height: 300px; /* Фіксована менша висота */
   overflow: hidden;
   background-color: #e9e9e9; /* Placeholder background */
   margin-bottom: 5px; /* Додайте відступ між обкладинкою та текстом */
}

        .cover-image-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: transform 0.3s ease-in-out;
        }

        .book-item:hover .cover-image-wrapper {
            transform: scale(1.05);
        }

        .cover-image-wrapper img {
            display: block;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; /* Зберігає співвідношення сторін зображення */
            border-radius: 4px;
        }

        .book-info {
            text-align: center;
            display: flex; /* Для вирівнювання вмісту всередині info */
            flex-direction: column;
            flex-grow: 1; /* Дозволяє блоку займати весь доступний простір */
            justify-content: space-between; /* Розтягує вміст по вертикалі */
        }


        .add-to-cart-button {
            background-color: #007bff; /* Синій колір */
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.2s ease-in-out;
            width: 100%; /* Забезпечує повну ширину кнопки */
            box-sizing: border-box; /* Враховує padding у ширині */
            margin-top: auto; /* Притискає кнопку до низу, якщо book-info flex */
        }

        .add-to-cart-button:hover {
            background-color: #0056b3;
        }

        
        /* Якщо в результаті лише одна книга — центр її */
.book-grid:only-child,
.book-grid:has(.book-item:only-child) {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
}

.add-to-cart-button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.9em;
    width: 100%;
    box-sizing: border-box;
    margin-top: auto;

    /* --- НОВІ ВЛАСТИВОСТІ ДЛЯ ПЛАВНОГО З'ЯВЛЕННЯ --- */
    opacity: 0; /* Приховати кнопку за замовчуванням */
    transform: translateY(10px); /* Зсунути трохи вниз для ефекту появи */
    transition: opacity 0.3s ease, transform 0.3s ease, background-color 0.2s ease-in-out; /* Плавний перехід для opacity, transform та background-color */
    pointer-events: none; /* Забороняємо взаємодію з кнопкою, коли вона прихована */
    position: absolute; /* Позиціонуємо абсолютно всередині book-item */
    bottom: 15px; /* Відступ від нижнього краю book-item */
    left: 50%; /* Центруємо по горизонталі */
    transform: translateX(-50%) translateY(10px); /* Комбінований transform для центрування та початкового зсуву */
    width: calc(100% - 30px); /* Ширина кнопки з урахуванням padding book-info */
    z-index: 2; /* Щоб кнопка була над іншими елементами, якщо потрібно */
}

.book-item:hover .add-to-cart-button {
    opacity: 1; /* Показати кнопку при наведенні на .book-item */
    transform: translateX(-50%) translateY(0); /* Повернути на місце */
    pointer-events: auto; /* Дозволяємо взаємодію з кнопкою */
}

.add-to-cart-button:hover {
    background-color: #0056b3;
}

        /* Медіа-запити для адаптивності */
       
    </style>
</head>
<body>
    <header>
        <div class="container nav-container">
            <nav>
                <ul class="nav-left">
                <li><a href="index.php">Головна</a></li>
                    
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
                </div>
            </nav>
        </div>
    </header>

    <main class="container content">
        <h1>Результати пошуку для "<?= htmlspecialchars($query) ?>"</h1>

        <?php if (empty($books)): ?>
            <p>За вашим запитом нічого не знайдено.</p>
        <?php else: ?>
            <div class="book-grid">
                <?php foreach ($books as $book): ?>
                    <div class="book-item">
                        <div class="book-cover-container">
                            <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>" class="book-link">
                                <div class="cover-image-wrapper">
                                    <img src="images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>" loading="lazy">
                                </div>
                            </a>
                            <?php if (method_exists($book, 'getDiscount') && $book->getDiscount() > 0): ?>
                            <span class="discount-badge">-<?= htmlspecialchars($book->getDiscount()); ?>%</span>
                        <?php endif; ?>
                        </div>
                        <div class="book-info">
                            <h3 class="book-title"><?= htmlspecialchars($book->getTitle()); ?></h3>
                            <p class="book-author"><?= htmlspecialchars($book->getAuthor()); ?></p>
                            <div class="book-price">
                            <?php if (method_exists($book, 'getDiscount') && $book->getDiscount() > 0): ?>
                                <span class="original-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</span>
                                <span class="sale-price"><?= htmlspecialchars(number_format($book->getPrice() * (1 - $book->getDiscount() / 100), 2)); ?> грн</span>
                            <?php else: ?>
                                <span class="book-price"><?= htmlspecialchars(number_format($book->getPrice(), 2)); ?> грн</span>
                            <?php endif; ?>
                        </div>
                            <button class="add-to-cart-button" data-id="<?= htmlspecialchars($book->getId()); ?>">До кошика</button>
                       
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Обробка кліків по кнопці "До кошика" (перейменував з order-button, оскільки у вас add-to-cart-button)
        document.querySelectorAll('.add-to-cart-button').forEach(button => {
            button.addEventListener('click', async () => {
                const bookId = button.getAttribute('data-id');
                try {
                    const response = await fetch('/bookshop/bookshop/public/add_to_cart.php', {
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
                        const cartLink = document.querySelector('.cart-link');
                        if (cartLink) {
                            cartLink.classList.add('bump');
                            setTimeout(() => {
                                cartLink.classList.remove('bump');
                            }, 300);
                        }
                    } else if (result.error === 'login_required') {
                        alert('Будь ласка, увійдіть, щоб додати до кошика.');
                        window.location.href = 'login.php';
                    } else if (result.error === 'already_in_cart') {
                        alert(result.message);
                    } else {
                        alert('Помилка: ' + result.error);
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    alert('Щось пішло не так...');
                }
            });
        });

        // Код для бокової панелі
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');
        // sidebar.classList.remove('hidden'); // Цей рядок може конфліктувати, якщо 'hidden' вже застосовує display: none.
                                           // Краще керувати відображенням лише через toggle.
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
    </script>
</body>
</html>