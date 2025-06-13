<?php
namespace App\Models;

class Review {
    private ?int $id;
    private int $bookId;
    private int $userId;
    private string $userName;
    private int $rating;
    private string $reviewText;
    private string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int $id,
        int $bookId,
        int $userId,
        string $userName,
        int $rating,
        string $reviewText,
        string $createdAt,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->bookId = $bookId;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->rating = $rating;
        $this->reviewText = $reviewText;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getBookId(): int { return $this->bookId; }
    public function getUserId(): int { return $this->userId; }
    public function getUserName(): ?string { return $this->userName; } 
    public function setUserName(string $userName): void { $this->userName = $userName; }
    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): void { $this->rating = $rating; }
    public function getReviewText(): string { return $this->reviewText; }
    public function setReviewText(string $reviewText): void { $this->reviewText = $reviewText; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $updatedAt): void { $this->updatedAt = $updatedAt; }
}
