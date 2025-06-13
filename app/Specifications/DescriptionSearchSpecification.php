<?php
namespace App\Specifications;

use App\Models\Book;
use App\Specifications\SpecificationInterface;

class DescriptionSearchSpecification implements SpecificationInterface
{
    private string $searchQuery;

    public function __construct(string $searchQuery)
    {
        // Додаємо лог у конструктор
        error_log("DEBUG: DescriptionSearchSpecification constructor called with searchQuery: " . var_export($searchQuery, true));
        $this->searchQuery = $searchQuery;
    }

    public function isSatisfiedBy(Book $book): bool
    {
        // Можна додати лог тут, якщо хочете відстежувати фільтрацію в пам'яті
        // error_log("DEBUG: DescriptionSearchSpecification::isSatisfiedBy checking book: " . $book->getTitle());

        if (empty($this->searchQuery)) {
            return true;
        }

        $lowerCaseDescription = mb_strtolower($book->getDescription());
        $lowerCaseSearchQuery = mb_strtolower($this->searchQuery);

        return str_contains($lowerCaseDescription, $lowerCaseSearchQuery);
    }
}