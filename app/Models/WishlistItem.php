<?php

namespace App\Models;

class WishlistItem
{
    private $id;
    private $userId;
    private $bookId;
    private $book; // Можемо зберігати об'єкт книги для зручності

    public function __construct(int $id, int $userId, int $bookId, ?Book $book = null)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->bookId = $bookId;
        $this->book = $book;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getBookId(): int
    {
        return $this->bookId;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(?Book $book): void
    {
        $this->book = $book;
    }
}