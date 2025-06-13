<?php
// C:\Users\Vlada\xampp\htdocs\bookshop\bookshop\public\remove_from_cart.php

session_set_cookie_params([
    'path'     => '/',
    'httponly' => true
]);
session_start();

header('Content-Type: application/json');

// --- Правильний порядок підключень та імпорт класів ---
// Інтерфейси мають бути підключені ДО їх реалізацій
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php'; // Інтерфейс має бути першим
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php'; // Потрібно для CartService
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php'; // Потрібно для CartService
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Models/CartItem.php'; // Можливо, також потрібно для коректної роботи


// --- Використання просторів імен ---
use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Repositories\BookstoreRepository; // Додано: BookstoreRepository потрібен для CartService
use App\Services\CartService;
// use App\Models\CartItem; // Можливо, також потрібно

$response = ['success' => false, 'message' => 'Невідома помилка.'];

if (!isset($_SESSION['user_id'])) {
    $response['error'] = 'login_required';
    $response['message'] = 'Будь ласка, увійдіть, щоб керувати кошиком.';
    echo json_encode($response);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$bookId = $input['book_id'] ?? null;

if (empty($bookId)) {
    $response['error'] = 'invalid_book_id';
    $response['message'] = 'Невірний ID книги.';
    echo json_encode($response);
    exit;
}

try {
    // Отримання з'єднання з базою даних (використовуємо Connection класу)
    $db = Connection::getInstance()->getConnection();

    // Ініціалізація репозиторіїв та сервісів
    $cartRepository = new CartRepository($db); // Правильне використання $db
    $bookstoreRepository = new BookstoreRepository($db); // Додано: BookstoreRepository потрібен для CartService
    $cartService = new CartService($cartRepository, $bookstoreRepository); // Передача обох репозиторіїв

    // Логіка видалення
    // Змінено: метод removeItemFromCart() скоріш за все є в CartRepository, а не в CartService.
    // У CartService у вас є removeItem($userId, $bookId).
    if ($cartService->removeItem($userId, $bookId)) { // Змінено на $cartService->removeItem()
        // Оновлення лічильників після успішного видалення
        // $cartItems = $cartService->getCartItemsByUserId($userId); // Цей метод відсутній у CartService
        $cartItems = $cartService->getCartItems($userId); // Змінено на існуючий метод CartService

        // Важливо: $cartItems — це масив об'єктів CartItem.
        // Якщо getTotalItemsInCart() вже є у CartService, використовуйте його.
        // Якщо потрібно вручну рахувати кількість, то:
        $totalItemsInCart = 0;
        foreach ($cartItems as $item) {
            $totalItemsInCart += $item->getQuantity();
        }
        // Або, якщо у вас вже є метод в CartService, що повертає загальну кількість:
        // $totalItemsInCart = $cartService->getTotalItemsInCart($userId); 
        
        $totalCartPrice = $cartService->getTotalCartPrice($userId);

        $response['success'] = true;
        $response['message'] = 'Книгу успішно видалено з кошика.';
        $response['cart_count'] = $totalItemsInCart;
        $response['total_price'] = $totalCartPrice;
    } else {
        // Якщо removeItem повернув false, можливо книги не було в кошику
        $response['error'] = 'not_found';
        $response['message'] = 'Не вдалося видалити книгу з кошика. Можливо, її там немає.';
    }

} catch (Exception $e) {
    error_log("Error in remove_from_cart.php: " . $e->getMessage() . " on line " . $e->getLine());
    $response['message'] = 'Помилка сервера: ' . $e->getMessage();
    $response['error_code'] = $e->getCode(); // Додайте код помилки для дебагу
}

echo json_encode($response);
exit;