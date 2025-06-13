<?php
namespace App\Services;

use App\Models\Book;
use App\Specifications\SpecificationInterface; // Додайте цей рядок для імпорту інтерфейсу

interface IBookstoreService {
    /**
     * Отримує всі книги з обмеженням за кількістю.
     *
     * @param int $limit Максимальна кількість книг для отримання.
     * @return array<Book> Масив об'єктів книг.
     */
    public function getAllBooks(int $limit): array;

    /**
     * Отримує книгу за її ідентифікатором.
     *
     * @param int $id Ідентифікатор книги.
     * @return ?Book Об'єкт книги або null, якщо не знайдено.
     */
    public function getBookById(int $id): ?Book;

    /**
     * Отримує книги за їхнім жанром.
     *
     * @param string $genre Жанр книги.
     * @return array<Book> Масив об'єктів книг.
     */
    public function getBooksByGenre(string $genre): array;

    /**
     * Отримує популярні книги з обмеженням за кількістю.
     *
     * @param int $limit Максимальна кількість популярних книг для отримання.
     * @return array<Book> Масив об'єктів книг.
     */
    public function getPopularBooks(int $limit, string $orderBy): array;

    /**
     * Здійснює пошук книг за назвою або автором.
     *
     * @param string $query Пошуковий запит.
     * @return array<Book> Масив об'єктів книг, що відповідають запиту.
     */
    public function searchBooks(string $query): array;

    /**
     * Отримує книги, які задовольняють заданій специфікації.
     *
     * @param SpecificationInterface $spec Об'єкт специфікації для фільтрації.
     * @return array<Book> Масив об'єктів книг, що відповідають специфікації.
     */
    public function getBooksBySpecification(SpecificationInterface $spec): array;
}