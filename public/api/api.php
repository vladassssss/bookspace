<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Використовуємо автозавантаження із нашого проекту
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';

use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Services\BookstoreService;
use App\Controllers\BookstoreController;

$db = Connection::getInstance()->getConnection();
$repository = new BookstoreRepository($db);
$service = new BookstoreService($repository);
$controller = new BookstoreController($service);

// Простий роутінг за наявності GET параметрів
if (isset($_GET['id'])) {
    $bookId = (int) $_GET['id'];
    $book = $controller->getBookById($bookId);
    if ($book) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'id'          => $book->getId(),
                'title'       => $book->getTitle(),
                'author'      => $book->getAuthor(),
                'price'       => $book->getPrice(),
                'description' => $book->getDescription(),
                'cover_image' => $book->getCoverImage()
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Книга не знайдена']);
    }
} else {
    $books = $service->getAllBooks(10);
    $data = [];
    foreach ($books as $book) {
        $data[] = [
            'id'          => $book->getId(),
            'title'       => $book->getTitle(),
            'author'      => $book->getAuthor(),
            'price'       => $book->getPrice(),
            'description' => $book->getDescription(),
            'cover_image' => $book->getCoverImage()
        ];
    }
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
}
