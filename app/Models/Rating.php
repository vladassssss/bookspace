<?php

namespace App\Models;

class Rating {
    private ?int $id;
    private int $bookId;
    private int $userId;
    private int $rating;
    private ?string $ratedAt;

    public function __construct(int $bookId, int $userId, int $rating, ?int $id = null, ?string $ratedAt = null) {
        $this->id = $id;
        $this->bookId = $bookId;
        $this->userId = $userId;
        $this->rating = $rating;
        $this->ratedAt = $ratedAt;
    }

    // Геттери
    public function getId(): ?int { return $this->id; }
    public function getBookId(): int { return $this->bookId; }
    public function getUserId(): int { return $this->userId; }
    public function getRating(): int { return $this->rating; }
    public function getRatedAt(): ?string { return $this->ratedAt; }

    // Сеттери (якщо потрібно)
    public function setRating(int $rating): void {
        if ($rating >= 1 && $rating <= 5) {
            $this->rating = $rating;
        } else {
            throw new \InvalidArgumentException("Оцінка повинна бути від 1 до 5.");
        }
    }
}