<?php
header('Content-Type: application/json');

// Підключаємо файли вручну:
require_once __DIR__ . '/../../app/Database/Connection.php';
require_once __DIR__ . '/../../app/Models/Book.php';
require_once __DIR__ . '/../../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../../app/Services/BookstoreService.php';
require_once __DIR__ . '/../../app/Controllers/ApiBookstoreController.php';

use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Services\BookstoreService;
use App\Controllers\ApiBookstoreController;

$db = Connection::getInstance()->getConnection();
$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);
$apiController = new ApiBookstoreController($bookstoreService);

$limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
$genre = isset($_GET['genre']) ? $_GET['genre'] : null;

// Викликаємо API-метод:
$apiController->getBooks($limit, $genre);
