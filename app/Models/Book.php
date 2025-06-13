<?php

namespace App\Models;

class Book
{
    private ?int $id;
    private ?string $title;
    private ?string $author;
    private ?string $genre;
    private ?float $price;
    private ?string $cover_image;
    private string $description;
    private ?string $language;
    private ?int $popularity;
    private ?float $discount;
    private ?int $quantity;
    private array $moodsList; // <--- ОСЬ ЦЕЙ РЯДОК ПОТРІБНО ЗМІНИТИ!
    private ?int $wishlistCount = null;
    private ?float $averageRating = null;
    private ?int $totalOrderedQuantity = null;

    public function __construct(
        ?int $id = null,
        ?string $title = null,
        ?string $author = null,
        ?string $genre = null,
        ?float $price = null,
        ?string $cover_image = null,
        string $description = '',
        ?string $language = null,
        ?int $popularity = null,
        ?float $discount = null,
        ?int $quantity = null,
        array $moodsList = [] // Цей параметр вже правильний
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->author = $author;
        $this->genre = $genre;
        $this->price = $price;
        $this->cover_image = $cover_image;
        $this->description = $description;
        $this->language = $language;
        $this->popularity = $popularity;
        $this->discount = $discount;
        $this->quantity = $quantity;
        $this->moodsList = $moodsList; // Це присвоєння буде працювати коректно
    }

    // --- ГЕТТЕРИ ---
    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function getAuthor(): ?string { return $this->author; }
    public function getGenre(): ?string { return $this->genre; }
    public function getPrice(): ?float { return $this->price; }
    public function getCoverImage(): ?string { return $this->cover_image; }
    public function getDescription(): string { return $this->description; }
    public function getLanguage(): ?string { return $this->language; }
    public function getPopularity(): ?int { return $this->popularity; }
    public function getDiscount(): ?float { return $this->discount; }
    public function getQuantity(): ?int { return $this->quantity; }
    public function getWishlistCount(): ?int { return $this->wishlistCount; }
    public function getAverageRating(): ?float { return $this->averageRating; }
    public function getTotalOrderedQuantity(): ?int { return $this->totalOrderedQuantity; }

    // Метод для встановлення настроїв (правильно приймає масив)
    public function setMoodsList(array $moodsList): void {
        $this->moodsList = $moodsList;
    }

    // Метод для отримання настроїв (правильно повертає масив)
    public function getMoodsList(): array {
        return $this->moodsList;
    }

    // --- СЕТТЕРИ ДЛЯ ІНШИХ ВЛАСТИВОСТЕЙ ---
    public function setWishlistCount(?int $wishlistCount): void {
        $this->wishlistCount = $wishlistCount;
    }

    public function setAverageRating(?float $averageRating): void {
        $this->averageRating = $averageRating;
    }

    public function setTotalOrderedQuantity(?int $totalOrderedQuantity): void {
        $this->totalOrderedQuantity = $totalOrderedQuantity;
    }

    // --- Методи для зміни кількості ---
    public function decreaseQuantity(int $amount): void {
        if ($this->getAvailableQuantity() < $amount) {
            throw new \Exception("Не можна додати більше, ніж є в наявності");
        }
        $this->quantity -= $amount;
    }

    public function getAvailableQuantity(): int {
        return $this->quantity ?? 0;
    }

    // Метод для повернення основних даних книги (корисний для CartItem)
    public function getBookData(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'genre' => $this->genre,
            'price' => $this->price,
            'cover_image' => $this->cover_image,
            'description' => $this->description,
            'language' => $this->language,
            'popularity' => $this->popularity,
            'discount' => $this->discount,
            'quantity' => $this->quantity,
            'moods_list' => $this->moodsList, // Включаємо moodsList
            'wishlistCount' => $this->wishlistCount,
            'averageRating' => $this->averageRating,
            'totalOrderedQuantity' => $this->totalOrderedQuantity,
        ];
    }
}