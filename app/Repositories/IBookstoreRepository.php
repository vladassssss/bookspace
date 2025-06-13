<?php
namespace App\Repositories;

use App\Models\Book; // Переконайтеся, що Book модель імпортована

interface IBookstoreRepository {
    public function getBookById(int $id): ?Book;
    public function getBooksByGenre(string $genre): array;
    public function getPopularBooks(int $limit, string $orderBy = 'orders'): array;
     public function findAll(?int $limit = null, ?string $genre = null): array;
    public function findBySpecifications(array $specifications): array; // Цей метод потребує реалізації
    public function getDiscountedBooks(): array;
    public function addBook(Book $book): bool;
    public function deleteBook(int $id): bool;
    public function getAvailableQuantity(int $bookId): int;
    public function setBookQuantity(int $bookId, int $newQuantity): bool; // НОВИЙ МЕТОД

    // public function updateQuantity(int $bookId, int $newQuantity): bool; // <--- Цей метод потрібно ВИДАЛИТИ або ЗМІНИТИ
}