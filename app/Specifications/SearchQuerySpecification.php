<?php
namespace App\Specifications;

use App\Models\Book;

class SearchQuerySpecification implements SpecificationInterface
{
    private string $query;

    public function __construct(string $query)
    {
        $this->query = $query;
    }

    public function isSatisfiedBy(Book $book): bool
    {
        // Фільтрація в пам'яті за назвою або автором
        $title = $book->getTitle();
        $author = $book->getAuthor();
        $lowerQuery = mb_strtolower($this->query);

        return (mb_stripos($title, $lowerQuery) !== false || mb_stripos($author, $lowerQuery) !== false);
    }

    // Якщо ви хочете, щоб ця специфікація фільтрувала на рівні БД, додайте toQueryCriteria
    public function toQueryCriteria(): array
    {
        $searchParam = '%' . $this->query . '%';
        return [
            'clause' => '(title LIKE :search_query OR author LIKE :search_query)',
            'params' => [':search_query' => $searchParam]
        ];
    }
}