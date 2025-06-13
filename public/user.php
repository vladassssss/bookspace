<?php
session_start();

// Перевірка, чи користувач увійшов у систему
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Перенаправлення на сторінку входу, якщо не авторизований
    exit();
}

// Підключення файлів (як і на інших сторінках)
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/IUserRepository.php'; 
require_once __DIR__ . '/../app/Repositories/UserRepository.php'; // Припустимо, є репозиторій для користувачів
require_once __DIR__ . '/../app/Services/UserService.php'; // Припустимо, є сервіс для користувачів
require_once __DIR__ . '/../app/Repositories/OrderRepositoryInterface.php';
require_once __DIR__ . '/../app/Repositories/OrderRepository.php'; // Припустимо, є репозиторій для замовлень
require_once __DIR__ . '/../app/Services/OrderServiceInterface.php';
require_once __DIR__ . '/../app/Services/OrderService.php'; // Припустимо, є сервіс для замовлень
require_once __DIR__ . '/../app/Repositories/IWishlistRepository.php';
require_once __DIR__ . '/../app/Repositories/WishlistRepository.php'; // Припустимо, є репозиторій для списку бажань
require_once __DIR__ . '/../app/Services/IWishlistService.php'; // Припустимо, є сервіс для списку бажань
require_once __DIR__ . '/../app/Services/WishlistService.php'; // Припустимо, є сервіс для списку бажань
require_once __DIR__ . '/../app/Models/CartItem.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php'; // Припустимо, є репозиторій для кошика
require_once __DIR__ . '/../app/Services/CartService.php'; // Припустимо, є сервіс для кошика

use App\Database\Connection;
use App\Repositories\IBookstoreRepository;
use App\Repositories\BookstoreRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use App\Repositories\WishlistRepository;
use App\Services\WishlistService;
use App\Repositories\CartRepository;
use App\Services\CartService;

$db = Connection::getInstance()->getConnection();
$userRepository = new UserRepository($db);
$userService = new UserService($userRepository);
$orderRepository = new OrderRepository($db);
$orderService = new OrderService($orderRepository);
$bookstoreRepository = new BookstoreRepository($db); // Створення об'єкта BookstoreRepository
$wishlistRepository = new WishlistRepository($db, $bookstoreRepository); // Передача об'єкта BookstoreRepository
$wishlistService = new WishlistService($wishlistRepository);
$cartRepository = new CartRepository($db);
$cartService = new CartService($cartRepository);

$userId = $_SESSION['user_id'];

// Отримання інформації про користувача
$user = $userService->getUserById($userId);

// Отримання історії замовлень користувача
$orders = $orderService->getUserOrders($userId);

// Отримання списку бажань користувача
$wishlistItems = $wishlistService->getUserWishlist($userId);

// Отримання товарів у кошику користувача
$cartItems = $cartService->getUserCart($userId);
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Профіль користувача</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Додайте свої стилі тут */
        .user-profile {
            padding: 20px;
            margin: 20px auto;
            max-width: 960px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .section-title {
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.5rem;
            color: #333;
        }
        /* Стилі для історії замовлень, списку бажань, кошика */
        /* ... */
    </style>
</head>
<body>
    <header>
        <div class="container nav-container">
            <nav>
                <ul class="nav-left">
                    <li><a href="index.php">Головна</a></li>
                    <li><a href="allbooks.php">Усі книги</a></li>
                    <li><a href="popular.php">Популярне</a></li>
                    <li><a href="discounts.php">Знижки</a></li>
                </ul>
                <div class="nav-right">
                    <div class="auth-section">
                        <span>Вітаємо, <?= htmlspecialchars($user->getUsername()); ?>!</span>
                        <a href="cart.php" class="cart-link">🛒 (<span id="cart-count"><?= count($cartItems); ?></span>)</a>
                        <a href="user.php" class="active">Профіль</a>
                        <button class="logout" onclick="window.location.href='logout.php'">Вийти</button>
                    </div>
                </div>
            </nav>
        </div>
    </header>
    <main class="container user-profile">
        <section>
            <h2 class="section-title">Інформація про користувача</h2>
            <?php if ($user): ?>
                <p>Ім'я: <?= htmlspecialchars($user->getUsername()); ?></p>
                <p>Email: <?= htmlspecialchars($user->getEmail()); ?></p>
                <p><a href="edit_profile.php">Редагувати профіль</a></p>
            <?php else: ?>
                <p>Інформація про користувача недоступна.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2 class="section-title">Історія замовлень</h2>
            <?php if (!empty($orders)): ?>
                <ul>
                    <?php foreach ($orders as $order): ?>
                        <li>
                            Замовлення №<?= htmlspecialchars($order->getId()); ?> від <?= htmlspecialchars($order->getOrderDate()); ?> - Статус: <?= htmlspecialchars($order->getStatus()); ?> - Сума: <?= htmlspecialchars($order->getTotalAmount()); ?> грн
                            <a href="order_details.php?id=<?= htmlspecialchars($order->getId()); ?>">Деталі</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Немає попередніх замовлень.</p>
            <?php endif; ?>
        </section>

        <section>
            <h2 class="section-title">Список бажань</h2>
            <?php if (!empty($wishlistItems)): ?>
                <div class="wishlist-grid">
                    <?php foreach ($wishlistItems as $item): ?>
                        <div class="wishlist-item">
                            <a href="book.php?id=<?= htmlspecialchars($item->getBook()->getId()); ?>">
                                <img src="images/<?= htmlspecialchars($item->getBook()->getCoverImage()); ?>" alt="<?= htmlspecialchars($item->getBook()->getTitle()); ?>">
                            </a>
                            <p><?= htmlspecialchars($item->getBook()->getTitle()); ?></p>
                            <button class="add-to-cart" data-book-id="<?= htmlspecialchars($item->getBook()->getId()); ?>">До кошика</button>
                            <button class="remove-from-wishlist" data-item-id="<?= htmlspecialchars($item->getId()); ?>">Видалити</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Ваш список бажань порожній.</p>
            <?php endif; ?>
        </section>

      

        </main>
    <footer>
        © 2025 Книгарня
    </footer>
    <script>
        // JavaScript для обробки дій (додавання до кошика, видалення з бажань/кошика, зміна кількості)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('add-to-cart')) {
                const bookId = e.target.dataset.bookId;
                // AJAX-запит для додавання до кошика
                fetch('/bookshop/bookshop/public/add_to_cart.php', { // Шлях може бути іншим
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: bookId, quantity: 1 })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Книга додана до кошика!');
                        // Оновіть відображення кошика на сторінці (кількість)
                        const cartCountElement = document.getElementById('cart-count');
                        if (cartCountElement) {
                            cartCountElement.textContent = parseInt(cartCountElement.textContent) + 1;
                        }
                    } else {
                        alert('Помилка: ' + data.error);
                    }
                });
            } else if (e.target.classList.contains('remove-from-wishlist')) {
                const itemId = e.target.dataset.itemId;
                // AJAX-запит для видалення зі списку бажань
                fetch('/bookshop/bookshop/public/remove_from_wishlist.php', { // Шлях може бути іншим
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: itemId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        e.target.closest('.wishlist-item').remove();
                        if (document.querySelectorAll('.wishlist-item').length === 0) {
                            document.querySelector('.wishlist-grid').innerHTML = '<p>Ваш список бажань порожній.</p>';
                        }
                    } else {
                        alert('Помилка: ' + data.error);
                    }
                });
            } else if (e.target.classList.contains('remove-from-cart')) {
                const itemId = e.target.dataset.itemId;
                // AJAX-запит для видалення з кошика
                fetch('/bookshop/bookshop/public/remove_from_cart.php', { // Шлях може бути іншим
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: itemId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        e.target.closest('tr').remove();
                        // Оновіть загальну суму кошика
                        fetch('/bookshop/bookshop/public/get_cart_total.php') // Шлях може бути іншим
                            .then(res => res.json())
                            .then(totalData => {
                                const totalElement = document.querySelector('.user-profile tfoot td:last-child');
                                if (totalElement) {
                                    totalElement.textContent = totalData.total + ' грн';
                                }
                                // Оновіть кількість в іконці кошика
                                const cartCountElement = document.getElementById('cart-count');
                                if (cartCountElement) {
                                    cartCountElement.textContent = parseInt(cartCountElement.textContent) - 1;
                                    if (parseInt(cartCountElement.textContent) === 0) {
                                        document.querySelector('.user-profile section:last-child').innerHTML = '<p>Ваш кошик порожній.</p>';
                                    }
                                }
                            });
                    } else {
                        alert('Помилка: ' + data.error);
                    }
                });
            }
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('cart-quantity')) {
                const itemId = e.target.dataset.itemId;
                const quantity = e.target.value;
                // AJAX-запит для оновлення кількості в кошику
                fetch('/bookshop/bookshop/public/update_cart_quantity.php', { // Шлях може бути іншим
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: itemId, quantity: quantity })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Оновіть суму рядка та загальну суму кошика
                        const row = e.target.closest('tr');
                        const price = parseFloat(row.querySelector('td:nth-child(3)').textContent);
                        row.querySelector('td:nth-child(4)').textContent = (price * quantity).toFixed(2) + ' грн';
                        fetch('/bookshop/bookshop/public/get_cart_total.php') // Шлях може бути іншим
                            .then(res => res.json())
                            .then(totalData => {
                                const totalElement = document.querySelector('.user-profile tfoot td:last-child');
                                if (totalElement) {
                                    totalElement.textContent = totalData.total + ' грн';
                                }
                            });
                    } else {
                        alert('Помилка: ' + data.error);
                        e.target.dataset.originalValue = quantity; // Збережіть поточне значення для можливого відкату
            }
        });
    </script>
</body>
</html>