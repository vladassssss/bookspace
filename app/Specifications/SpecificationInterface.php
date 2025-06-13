<?php

namespace App\Specifications;

use App\Models\Book; // Переконайтеся, що шлях до вашої моделі Book правильний

interface SpecificationInterface
{
    /**
     * Перевіряє, чи задовольняє об'єкт даній специфікації.
     *
     * @param Book $book Об'єкт книги для перевірки.
     * @return bool True, якщо об'єкт задовольняє специфікації, інакше false.
     */
    public function isSatisfiedBy(Book $book): bool;
    // ВИДАЛИТИ: public function toQueryCriteria(): array;
}