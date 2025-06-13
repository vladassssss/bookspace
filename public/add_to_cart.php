<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$logFile = __DIR__ . '/add_to_cart_log.txt';
$timestamp = date("Y-m-d H:i:s");

// Початковий лог нового запиту
file_put_contents($logFile, "\n[$timestamp] === NEW REQUEST (START) ===\n", FILE_APPEND);
file_put_contents($logFile, "[$timestamp] Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
$rawDataInput = file_get_contents('php://input');
file_put_contents($logFile, "[$timestamp] Raw POST Data (php://input): " . ($rawDataInput ?: 'N/A') . "\n", FILE_APPEND);
file_put_contents($logFile, "[$timestamp] Session User ID: " . ($_SESSION['user_id'] ?? 'N/A') . "\n", FILE_APPEND);

$response = ['success' => false, 'message' => 'Невідома помилка сервера.', 'error' => 'unknown_error'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = ['error' => 'invalid_request_method', 'message' => 'Некоректний метод запиту.'];
    file_put_contents($logFile, "[$timestamp] === END REQUEST (Invalid Method) ===\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for invalid method.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
}
file_put_contents($logFile, "[$timestamp] Checkpoint: Request method is POST.\n", FILE_APPEND);

if (!isset($_SESSION['user_id'])) {
    $response = ['error' => 'login_required', 'message' => 'Будь ласка, увійдіть, щоб замовити.'];
    file_put_contents($logFile, "[$timestamp] === END REQUEST (Login Required) ===\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for login required.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
}
file_put_contents($logFile, "[$timestamp] Checkpoint: User is logged in. Session User ID: " . ($_SESSION['user_id'] ?? 'N/A') . "\n", FILE_APPEND);

$data = json_decode($rawDataInput, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['id']) || !isset($data['quantity'])) {
    $response = ['error' => 'invalid_json_input', 'message' => 'Некоректні вхідні дані JSON або відсутні обов\'язкові поля (id, quantity).'];
    file_put_contents($logFile, "[$timestamp] === END REQUEST (Invalid JSON) ===\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for invalid JSON input.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
}
file_put_contents($logFile, "[$timestamp] Checkpoint: JSON data parsed. Book ID: " . $data['id'] . ", Quantity: " . $data['quantity'] . "\n", FILE_APPEND);

$bookId = (int)$data['id'];
$quantityToAdd = (int)$data['quantity'];
$userId = $_SESSION['user_id'];

// --- ВКЛЮЧЕННЯ НЕОБХІДНИХ ФАЙЛІВ ВРУЧНУ (БЕЗ COMPOSER) ---
file_put_contents($logFile, "[$timestamp] Checkpoint: Attempting to require files...\n", FILE_APPEND);

// Додаємо логування навколо кожного require_once
file_put_contents($logFile, "[$timestamp] Requiring: Connection.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Database/Connection.php';
file_put_contents($logFile, "[$timestamp] Required: Connection.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: Book.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Models/Book.php';
file_put_contents($logFile, "[$timestamp] Required: Book.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: CartItem.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Models/CartItem.php';
file_put_contents($logFile, "[$timestamp] Required: CartItem.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: IBookstoreRepository.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
file_put_contents($logFile, "[$timestamp] Required: IBookstoreRepository.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: BookstoreRepository.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
file_put_contents($logFile, "[$timestamp] Required: BookstoreRepository.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: ICartRepository.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
file_put_contents($logFile, "[$timestamp] Required: ICartRepository.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: CartRepository.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
file_put_contents($logFile, "[$timestamp] Required: CartRepository.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Requiring: CartService.php\n", FILE_APPEND);
require_once __DIR__ . '/../app/Services/CartService.php';
file_put_contents($logFile, "[$timestamp] Required: CartService.php\n", FILE_APPEND);

file_put_contents($logFile, "[$timestamp] Checkpoint: All files required.\n", FILE_APPEND);


// --- ВИКОРИСТАННЯ ПРОСТОРІВ ІМЕН ---
use App\Database\Connection;
use App\Repositories\CartRepository;
use App\Repositories\BookstoreRepository;
use App\Services\CartService;

try {
    file_put_contents($logFile, "[$timestamp] Checkpoint: Inside try block. Establishing DB connection.\n", FILE_APPEND);
    $db = Connection::getInstance()->getConnection();
    file_put_contents($logFile, "[$timestamp] Checkpoint: DB connection established.\n", FILE_APPEND);

    file_put_contents($logFile, "[$timestamp] Initializing CartRepository...\n", FILE_APPEND);
    $cartRepository = new CartRepository($db);
    file_put_contents($logFile, "[$timestamp] CartRepository initialized.\n", FILE_APPEND);

    file_put_contents($logFile, "[$timestamp] Initializing BookstoreRepository...\n", FILE_APPEND);
    $bookstoreRepository = new BookstoreRepository($db);
    file_put_contents($logFile, "[$timestamp] BookstoreRepository initialized.\n", FILE_APPEND);

    file_put_contents($logFile, "[$timestamp] Initializing CartService...\n", FILE_APPEND);
    $cartService = new CartService($cartRepository, $bookstoreRepository);
    file_put_contents($logFile, "[$timestamp] CartService initialized.\n", FILE_APPEND);

    file_put_contents($logFile, "[$timestamp] Checkpoint: Repositories and Service initialized.\n", FILE_APPEND);

    $serviceResult = $cartService->addItem($userId, $bookId, $quantityToAdd);
    file_put_contents($logFile, "[$timestamp] Checkpoint: CartService->addItem called. Result: " . json_encode($serviceResult, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

    if ($serviceResult['success']) {
        $totalItemsInCart = $cartService->getTotalItemsInCart($userId);
        $_SESSION['cart_count'] = $totalItemsInCart;
        file_put_contents($logFile, "[$timestamp] Checkpoint: Item added successfully. Total in cart: " . $totalItemsInCart . "\n", FILE_APPEND);

        $response = [
            'success' => true,
            'message' => $serviceResult['message'] ?? 'Книга успішно додана до кошика або кількість оновлена.',
            'cart_total_items' => $totalItemsInCart
        ];
    } else {
        $response = [
            'success' => false,
            'message' => $serviceResult['message'] ?? 'Не вдалося додати книгу до кошика.',
            'error' => $serviceResult['error'] ?? 'add_item_failed'
        ];
        file_put_contents($logFile, "[$timestamp] Checkpoint: Item addition failed by service logic.\n", FILE_APPEND);
    }
    // ... ваш існуючий код до моменту формування $response ...

// Логування заголовків, які будуть відправлені
$headersSent = headers_list();
file_put_contents($logFile, "[$timestamp] Headers to be sent: " . json_encode($headersSent) . "\n", FILE_APPEND);

// Після цього йде `echo json_encode($response, JSON_UNESCAPED_UNICODE);`
echo json_encode($response, JSON_UNESCAPED_UNICODE);
file_put_contents($logFile, "[$timestamp] Response echoed for successful/service fail path.\n", FILE_APPEND);
exit;
   

} catch (\InvalidArgumentException $e) {
    $response = ['success' => false, 'error' => 'invalid_request', 'message' => $e->getMessage()];
    file_put_contents($logFile, "[$timestamp] Catch: InvalidArgumentException: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[$timestamp] Final Response (InvalidArgumentException): " . json_encode($response, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for InvalidArgumentException.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
} catch (\PDOException $e) {
    $response = ['success' => false, 'error' => 'database_error', 'message' => 'Помилка бази даних: ' . $e->getMessage()];
    file_put_contents($logFile, "[$timestamp] Catch: PDOException: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[$timestamp] Final Response (PDOException): " . json_encode($response, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for PDOException.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
} catch (\Exception $e) {
    error_log("Помилка при додаванні до кошика: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'server_error', 'message' => 'Виникла невідома помилка на сервері: ' . $e->getMessage()];
    file_put_contents($logFile, "[$timestamp] Catch: Generic Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[$timestamp] Final Response (Generic Exception): " . json_encode($response, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for Generic Exception.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
} catch (\Throwable $e) {
    error_log("Критична помилка при додаванні до кошика: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile());
    $response = [
        'success' => false,
        'error' => 'critical_error_details',
        'message' => 'Критична помилка сервера: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ];
    file_put_contents($logFile, "[$timestamp] Catch: Critical Throwable: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[$timestamp] Final Response (Critical Throwable): " . json_encode($response, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($logFile, "[$timestamp] Response echoed for Critical Throwable.\n", FILE_APPEND); // НОВИЙ ЛОГ
    exit;
}