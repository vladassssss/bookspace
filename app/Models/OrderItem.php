<?php
namespace App\Models;

class OrderItem
{
    private $id;
    private $orderId;
    private $bookId;
    private $quantity;
    private $priceAtPurchase;
    private $bookTitle;  // Додайте це поле для назви книги
    private $bookAuthor;
     private ?string $coverImage = null; // Додайте це поле для автора книги (опціонально)

    public function __construct(int $orderId, int $bookId, int $quantity, float $priceAtPurchase)
    {
        $this->orderId = $orderId;
        $this->bookId = $bookId;
        $this->quantity = $quantity;
        $this->priceAtPurchase = $priceAtPurchase;
    }

    // --- Гетери та Сетери ---

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): void { $this->id = $id; }

    public function getOrderId(): int { return $this->orderId; }
    public function setOrderId(int $orderId): void { $this->orderId = $orderId; }

    public function getBookId(): int { return $this->bookId; }
    public function setBookId(int $bookId): void { $this->bookId = $bookId; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): void { $this->quantity = $quantity; }

    public function getPriceAtPurchase(): float { return $this->priceAtPurchase; }
    public function setPriceAtPurchase(float $priceAtPurchase): void { $this->priceAtPurchase = $priceAtPurchase; }
     public function getCoverImage(): ?string // <--- ДОДАЙТЕ ЦЕЙ ГЕТТЕР
    {
        return $this->coverImage;
    }

    // Додані гетери/сетери для bookTitle та bookAuthor
    public function getBookTitle(): ?string { return $this->bookTitle; }
    public function setBookTitle(string $bookTitle): void { $this->bookTitle = $bookTitle; }

    public function getBookAuthor(): ?string { return $this->bookAuthor; }
    public function setBookAuthor(string $bookAuthor): void { $this->bookAuthor = $bookAuthor; }

    public function setCoverImage(string $coverImage): void // <--- ДОДАЙТЕ ЦЕЙ СЕТТЕР
    {
        $this->coverImage = $coverImage;
    }
}