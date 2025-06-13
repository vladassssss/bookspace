<?php
namespace App\Models;

class Review {
    private $id;
    private $bookId;      // Ідентифікатор книги, до якої належить відгук
    private $userName;
    private $reviewText;
    private $rating;
    private $createdAt;

    public function __construct($id, $bookId, $userName, $reviewText, $rating, $createdAt) {
        $this->id         = $id;
        $this->bookId     = $bookId;
        $this->userName   = $userName;
        $this->reviewText = $reviewText;
        $this->rating     = $rating;
        $this->createdAt  = $createdAt;
    }

    // Геттери для доступу до властивостей
    public function getId() {
        return $this->id;
    }

    public function getBookId() {
        return $this->bookId;
    }

    public function getUserName() {
        return $this->userName;
    }

    public function getReviewText() {
        return $this->reviewText;
    }

    public function getRating() {
        return $this->rating;
    }

    public function getCreatedAt() {
        return $this->createdAt;
    }
}
