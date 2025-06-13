<?php
namespace App\Controllers;

use App\Services\BookstoreService; // Видаліть це, якщо використовуєте IBookstoreService
use App\Models\Book;
use App\Services\IBookstoreService; // Важливо!
use App\Services\IRatingService;   // Важливо!
use App\Specifications\AndSpecification;
use App\Specifications\GenreSpecification;
use App\Specifications\MoodSpecification;
use App\Specifications\DescriptionSearchSpecification;
use App\Specifications\CompositeSpecification; // Можливо, вам потрібен цей імпорт для getRecommendations
use Exception;


class BookstoreController {
    private IBookstoreService $bookstoreService;
    private IRatingService $ratingService;

    public function __construct(IBookstoreService $bookstoreService, IRatingService $ratingService) {
        $this->bookstoreService = $bookstoreService;
        $this->ratingService = $ratingService;
    }

    public function showBooksPage(?int $limit = null, ?string $genre = null): array {
        error_log("Контролер: showBooksPage - Genre: " . print_r($genre, true) . ", Type: " . gettype($genre));
        return $this->bookstoreService->getAllBooks($limit, $genre);
    }

    public function getBookById(int $bookId) {
        return $this->bookstoreService->getBookById($bookId);
    }

    public function showPopularBooks(int $limit, string $orderBy): array
    {
        $popularBooks = $this->bookstoreService->getPopularBooks($limit, $orderBy);
        return $popularBooks;
    }

    public function searchBooks(string $query) {
        return $this->bookstoreService->searchBooks($query);
    }

    public function getDiscountedBooks(): array { // Додано тип повернення
        try {
            return $this->bookstoreService->getDiscountedBooks();
        } catch (Exception $e) {
            error_log("Помилка при отриманні книг зі знижками: " . $e->getMessage());
            return [];
        }
    }

    public function addBook(
        string $title,
        string $author,
        string $genre,
        float $price,
        string $image,
        string $description,
        string $language,
        int $discount,
        int $popularity
    ): bool {
        try {
            error_log("Спроба додати книгу: Title='$title', Author='$author'");

            // Важливо: у конструкторі Book тепер є параметр 'moods'
            // Вам потрібно буде передати пустий масив, якщо настроїв немає
            // Або отримати їх з форми/іншого джерела, якщо книга додається з настроями
            $book = new Book(
                null,
                $title,
                $author,
                $genre,
                $price,
                $image,
                $description,
                $language,
                $popularity,
                $discount,
                [] // Передаємо порожній масив для настроїв, якщо вони не вказуються при додаванні книги
            );

            return $this->bookstoreService->addBook($book);

        } catch (\Throwable $e) {
            error_log("Помилка при додаванні книги: " . $e->getMessage());
            return false;
        }
    }

    public function deleteBook(int $id): bool
    {
        return $this->bookstoreService->deleteBook($id);
    }

    /**
     * Отримує рекомендації книг на основі наданих критеріїв.
     *
     * @param ?string $genre Жанр книги (необов'язково).
     * @param ?string $mood Настрій книги (необов'язково).
     * @param ?string $searchQuery Пошуковий запит за описом (необов'язково).
     * @return array<Book> Масив об'єктів рекомендованих книг.
     */
   public function getRecommendations($genre = '', $descriptionSearch = null, $mood = '') {
        error_log("DEBUG: BookstoreController::getRecommendations called.");
        error_log("DEBUG: Controller - Received Genre: " . var_export($genre, true));
        error_log("DEBUG: Controller - Received Description Search: " . var_export($descriptionSearch, true));
        error_log("DEBUG: Controller - Received Mood: " . var_export($mood, true));
        $specifications = [];

        // 1. Додаємо GenreSpecification, якщо жанр надано
        if (!empty($genre) && $genre !== 'all') {
            $specifications[] = new GenreSpecification($genre);
            error_log("Controller: Added GenreSpecification for: " . $genre);
        }

        // 2. Додаємо MoodSpecification, якщо настрій надано
        if (!empty($mood) && $mood !== 'all') {
            $specifications[] = new MoodSpecification($mood);
            error_log("Controller: Added MoodSpecification for: " . $mood);
        }

        // 3. Додаємо DescriptionSearchSpecification, якщо пошуковий запит за описом надано
        // ВИПРАВЛЕНО: Використовуємо $descriptionSearch замість $searchQuery/$titleAuthorQuery
        if (!empty($descriptionSearch)) {
            $specifications[] = new DescriptionSearchSpecification($descriptionSearch);
            error_log("Controller: Added DescriptionSearchSpecification for: " . $descriptionSearch); // Оновлено текст логу
        } else {
            error_log("Controller: Description search query is empty or not recognized."); // Додано лог, якщо запит порожній
        }

        $filteredBooks = [];

        if (empty($specifications)) {
            // Якщо жодна специфікація не надана, повертаємо всі книги або якусь стандартну підбірку
            error_log("Controller: No specifications provided. Returning all books.");
            $filteredBooks = $this->bookstoreService->getAllBooks();
        } else {
            // Якщо є специфікації, об'єднуємо їх за допомогою AndSpecification
            // та передаємо до сервісу.
            $compositeSpec = new AndSpecification(...$specifications);
            error_log("Controller: Created CompositeSpecification. Calling service with " . count($specifications) . " specifications.");
            // Переконайтеся, що ваш сервіс (BookstoreService) має метод getBooksBySpecification,
            // який приймає CompositeSpecification.
            $filteredBooks = $this->bookstoreService->getBooksBySpecification($compositeSpec);
        }

        return $filteredBooks;
    }
}