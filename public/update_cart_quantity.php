<?php
session_set_cookie_params([
    'path'     => '/',
    'httponly' => true
]);
session_start();

require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Models/CartItem.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Services/CartService.php';

use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Repositories\BookstoreRepository;
use App\Services\CartService;

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Невідома помилка.'];

$input = json_decode(file_get_contents('php://input'), true);
error_log("update_cart_quantity.php: Received input data: " . var_export($input, true));

$bookId = $input['book_id'] ?? null;
$newQuantity = $input['quantity'] ?? null;

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Користувач не авторизований.';
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['user_id'];

if ($bookId === null || !is_numeric($newQuantity)) {
    $response['message'] = 'Некоректні вхідні дані (ID книги або кількість).';
    echo json_encode($response);
    exit();
}
$newQuantity = (int)$newQuantity;

try {
    $db = Connection::getInstance()->getConnection();
    $cartRepository = new CartRepository($db);
    $bookstoreRepository = new BookstoreRepository($db);
    $cartService = new CartService($cartRepository, $bookstoreRepository);

   

    // Викликаємо метод updateCartItemQuantity
    $updateResult = $cartService->updateCartItemQuantity($userId, $bookId, $newQuantity);
    
    if (is_array($updateResult) && isset($updateResult['success'])) {
        $response['success'] = $updateResult['success'];
        $response['message'] = $updateResult['message'];
        
        if (isset($updateResult['error'])) {
            $response['error'] = $updateResult['error'];
        }
        if (isset($updateResult['available_quantity'])) {
            $response['available_quantity'] = $updateResult['available_quantity'];
        }
        if (isset($updateResult['action'])) {
            $response['action'] = $updateResult['action'];
        }
    } else {
        $response['message'] = 'Помилка оновлення кількості: невірний формат відповіді сервісу.';
        error_log("update_cart_quantity.php: updateCartItemQuantity returned unexpected format. Result: " . var_export($updateResult, true));
    }

    $totalItemsInCart = $cartService->getTotalItemsInCart($userId);
    $totalCartPrice = $cartService->getTotalCartPrice($userId);

    $response['cart_count'] = $totalItemsInCart;
    $response['total_price'] = $totalCartPrice;

    if ($response['success'] && $newQuantity > 0) {
        $updatedCartItem = $cartService->getCartItemByBookIdAndUserId($userId, $bookId);
        if ($updatedCartItem) {
            $bookData = $updatedCartItem->getBookData(); 
            if ($bookData && isset($bookData['price']) && isset($bookData['discount'])) {
                $originalPrice = (float)($bookData['price'] ?? 0.0);
                $discount = (int)($bookData['discount'] ?? 0);
                $discountedPrice = $originalPrice * (1 - $discount / 100);
                $response['item_total_price_for_book'] = $discountedPrice * $updatedCartItem->getQuantity();
            } else {
                error_log("update_cart_quantity.php: Book data missing for updated item Book ID: " . $bookId);
                $response['message'] .= ' (Помилка даних книги для оновленого елемента)';
            }
        } else {
            error_log("update_cart_quantity.php: Updated CartItem not found for Book ID: " . $bookId);
            $response['message'] .= ' (Елемент кошика не знайдено після оновлення)';
        }
    } else if ($response['success'] && $newQuantity === 0) {
        $response['item_total_price_for_book'] = 0; 
        $response['action'] = 'removed';
    }

} catch (Exception $e) {
    error_log("Error in update_cart_quantity.php: " . $e->getMessage() . " on line " . $e->getLine());
    $response['message'] = 'Помилка сервера: ' . $e->getMessage();
    $response['debug_info'] = $e->getMessage() . " on line " . $e->getLine();
}

echo json_encode($response);
exit;
