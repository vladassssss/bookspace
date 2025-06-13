<?php
require_once '../../config/database.php';
require_once '../../app/Controllers/OrderController.php';

use App\Controllers\OrderController;

header('Content-Type: application/json');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $bookId = $data['book_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $controller = new OrderController();
    $result = $controller->placeOrder($userId, $bookId);

    echo json_encode($result);
}
