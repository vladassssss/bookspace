<?php
// public/order.php

require_once __DIR__ . '/../app/bootstrap.php';

// --- ВИКОРИСТАННЯ ПРОСТОРІВ ІМЕН ---
// Імпортуємо тільки ті класи, які використовуються за короткими іменами безпосередньо в цьому файлі.
use App\Database\Connection;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository; // Потрібен для створення екземпляра UserRepository
use App\Repositories\BookstoreRepository;
use App\Services\OrderService;
use App\Services\BookstoreService;
use App\Controllers\OrderController;

try {
    // Ініціалізація залежностей
    $db = Connection::getInstance()->getConnection();
    $orderRepo = new OrderRepository($db);

    // ПОМИЛКА БУЛА ТУТ: Ви повинні створити $userRepo ПЕРЕД тим, як передавати його в OrderService
    $userRepo = new UserRepository($db); // <--- ЦЕЙ РЯДОК ПОТРІБНО ДОДАТИ АБО РОЗКОМЕНТУВАТИ!

    $orderService = new OrderService($orderRepo, $userRepo); // Тепер $userRepo існує і є об'єктом
    $bookstoreRepo = new BookstoreRepository($db);
    $bookstoreService = new BookstoreService($bookstoreRepo);
    $orderController = new OrderController($orderService, $bookstoreService);

    // Обробка вхідних даних (POST/GET)
    $input = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $json = file_get_contents('php://input');
        $input = json_decode($json, true);

        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
    } else {
        $input = $_GET;
    }

    $action = $input['action'] ?? null; // Отримуємо дію з розпарсеного входу

    // Маршрутизація запитів
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'place_order') {
        $orderController->placeOrderAction();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'confirm') {
        $orderController->displayOrderConfirmation();
    } else {
        // Якщо дія невідома або метод запиту не відповідає
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Невірний запит для оформлення замовлення або невідома дія.']);
    }

} catch (Exception $e) {
    // Обробка винятків
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Внутрішня помилка сервера: ' . $e->getMessage()]);
    // Логуємо помилку для налагодження
    error_log("General error in public/order.php: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile());
}
// order.php
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data && isset($data['action']) && $data['action'] === 'place_order') {
    // ... отримайте book_id, quantity, phone, delivery_address_id з $data['cart_items'][0] та $data['phone']
    // Передайте їх в orderController->placeOrder()
    // Поверніть JSON-відповідь: echo json_encode(['success' => true, 'message' => 'Замовлення успішно оформлено!', 'order_id' => $orderId]);
} else {
    echo json_encode(['success' => false, 'message' => 'Невірний запит.']);
}
?>
