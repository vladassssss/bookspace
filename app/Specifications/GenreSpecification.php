<?php

namespace App\Specifications;

use App\Models\Book; 
// require_once __DIR__ . '/SpecificationInterface.php'; // Цей рядок потрібно видалити, якщо використовуєте Composer

class GenreSpecification implements SpecificationInterface
{
    private string $genre;

    public function __construct(string $genre)
    {
        $this->genre = $genre;
    }

    public function isSatisfiedBy(Book $book): bool
    {
        return $book->getGenre() === $this->genre;
    }

    // ВИДАЛІТЬ ЦЕЙ МЕТОД, якщо toQueryCriteria() відсутній у SpecificationInterface.
    /*
    public function toQueryCriteria(): array
    {
        return [
            'clause' => 'b.genre = :genre',
            'params' => [':genre' => $this->genre],
        ];
    }
    */
}