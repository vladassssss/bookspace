<?php
error_log("DEBUG: order_confirmation.php START");
session_set_cookie_params([
    'path'     => '/',
    'httponly' => true
]);
session_start();

// --- Підключення файлів ---
// Моделі
require_once __DIR__ . '/../app/Models/Order.php';
require_once __DIR__ . '/../app/Models/OrderItem.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/CartItem.php';

// Інтерфейси репозиторіїв
require_once __DIR__ . '/../app/Repositories/OrderRepositoryInterface.php';
require_once __DIR__ . '/../app/Repositories/IUserRepository.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';

// Репозиторії
require_once __DIR__ . '/../app/Repositories/OrderRepository.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';

// Інтерфейси сервісів
require_once __DIR__ . '/../app/Services/OrderServiceInterface.php';
require_once __DIR__ . '/../app/Services/IBookstoreService.php';

// Сервіси
require_once __DIR__ . '/../app/Services/OrderService.php';
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';

// Контролери
require_once __DIR__ . '/../app/Controllers/OrderController.php';

// Утиліти
require_once __DIR__ . '/auth_utils.php';
require_once __DIR__ . '/../app/Database/Connection.php';

// --- Використання класів (use statements) ---
use App\Database\Connection;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Repositories\CartRepository;
use App\Repositories\BookstoreRepository;
use App\Services\OrderService;
use App\Services\CartService;
use App\Services\BookstoreService;
use App\Controllers\OrderController;

// --- Перевірка авторизації та підключення до БД ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = Connection::getInstance()->getConnection();

if (!$db) {
    die("Помилка: Не вдалося підключитися до бази даних.");
}

$orderId = $_GET['order_id'] ?? null;
if (!$orderId) {
    $_SESSION['message'] = "Не вказано ID замовлення.";
    exit();
}

$userId = $_SESSION['user_id'];

// --- Ініціалізація репозиторіїв ---
$orderRepository = new OrderRepository($db);
$userRepository = new UserRepository($db);
$cartRepository = new CartRepository($db);
$bookstoreRepository = new BookstoreRepository($db);

// --- Ініціалізація сервісів ---
$orderService = new OrderService(
    $orderRepository,  // <-- OrderRepositoryInterface
    $cartRepository,   // <-- ICartRepository
    $userRepository,   // <-- IUserRepository
    $bookstoreRepository // <-- IBookstoreRepository
);
$bookstoreService = new BookstoreService($bookstoreRepository);

// --- Ініціалізація контролера ---
$orderController = new OrderController($orderService, $bookstoreService);

// --- Отримання деталей замовлення через контролер ---
$order = $orderController->getOrderDetails((int)$orderId, $userId);

if (!$order) {
    $_SESSION['message'] = "Замовлення не знайдено або у вас немає доступу до нього.";
    header('Location: /bookshop/bookshop/public/index.php'); // Оновлений шлях
    exit();
}

// --- Відображення HTML-сторінки ---
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Підтвердження замовлення #<?php echo htmlspecialchars($order->getId()); ?> | BookShop</title>
    <link rel="stylesheet" href="style4.css"> <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="main-header">
        <div class="header-content container">
            <a href="/bookshop/bookshop/public/index.php" class="logo">BookShop</a>
            <nav class="main-nav">
                <ul>
                    <li><a href="/bookshop/bookshop/public/index.php">Головна</a></li>
                    <li><a href="/bookshop/bookshop/public/books.php">Книги</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="/bookshop/bookshop/public/cart.php">Кошик</a></li>
                        <li><a href="/bookshop/bookshop/public/user_orders.php">Мої замовлення</a></li>
                        <li><a href="/bookshop/bookshop/public/logout.php" class="btn-orange">Вихід</a></li>
                    <?php else: ?>
                        <li><a href="/bookshop/bookshop/public/login.php" class="btn-orange">Вхід</a></li>
                        <li><a href="/bookshop/bookshop/public/register.php">Реєстрація</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="order-confirmation-main">
        <div class="container order-summary-card">
            <h1 class="order-title">Підтвердження замовлення <span class="order-id">#<?php echo htmlspecialchars($order->getId()); ?></span></h1>
            <p class="order-message">Ваше замовлення було успішно оформлено! Дякуємо за покупку.</p>

            <div class="order-details-summary">
                <div class="detail-item">
                    <span>Дата замовлення:</span>
                    <strong><?php echo htmlspecialchars($order->getOrderDate()); ?></strong>
                </div>
                <div class="detail-item">
                    <span>Статус:</span>
                    <strong class="status-<?php echo htmlspecialchars($order->getStatus()); ?>">
                        <?php echo htmlspecialchars(ucfirst($order->getStatus())); ?>
                    </strong>
                </div>
                <div class="detail-item total-amount">
                    <span>Загальна сума:</span>
                    <strong><?php echo htmlspecialchars(number_format($order->getTotalAmount(), 2)); ?> грн</strong>
                </div>
            </div>
        </div>

        <div class="container order-items-card">
            <h2>Деталі замовлення:</h2>
            <div class="order-items-table">
                <table>
                    <thead>
                        <tr>
                            <th>Книга</th>
                            <th>Автор</th>
                            <th class="text-right">Ціна за одиницю</th>
                            <th class="text-center">Кількість</th>
                            <th class="text-right">Сума</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->getOrderItems() as $item): ?>
                        <tr>
                            <td>
                            <span class="book-title"><?php echo htmlspecialchars($item->getBookTitle()); ?></span>
                        </td>
                            <td><?php echo htmlspecialchars($item->getBookAuthor()); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars(number_format($item->getPriceAtPurchase(), 2)); ?> грн</td>
                            <td class="text-center"><?php echo htmlspecialchars($item->getQuantity()); ?></td>
                            <td class="text-right"><?php echo htmlspecialchars(number_format($item->getPriceAtPurchase() * $item->getQuantity(), 2)); ?> грн</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="total-label"><strong>Загальна сума замовлення:</strong></td>
                            <td class="total-value"><strong><?php echo htmlspecialchars(number_format($order->getTotalAmount(), 2)); ?> грн</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="actions-section">
                <a href="user_orders.php" class="btn btn-primary">Переглянути мої замовлення</a>
                <a href="index.php" class="btn btn-secondary">Повернутись на головну</a>
            </div>
        </div>
    </main>

    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> BookShop. Усі права захищені.</p>
        </div>
    </footer>
</body>
</html>