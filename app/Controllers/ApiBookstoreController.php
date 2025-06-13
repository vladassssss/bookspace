<?php
namespace App\Controllers;

use App\Services\BookstoreService;

class ApiBookstoreController {
    private $bookstoreService;

    public function __construct(BookstoreService $bookstoreService) {
        $this->bookstoreService = $bookstoreService;
    }

    public function getBooks($limit, $genre = null) {
        if (!empty($genre)) {
            $books = $this->bookstoreService->getBooksByGenre($genre);
        } else {
            $books = $this->bookstoreService->getAllBooks($limit);
        }
        header('Content-Type: application/json');
        echo json_encode($books);
    }
}
