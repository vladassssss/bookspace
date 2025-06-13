<?php

namespace App\Specifications;

use App\Models\Book; // Переконайтеся, що Book знаходиться в цьому просторі імен

/**
 * CompositeSpecification - об'єднує кілька специфікацій.
 * Зазвичай використовує логіку "AND" (тобто об'єкт повинен задовольняти ВСІМ специфікаціям).
 */
class CompositeSpecification implements SpecificationInterface
{
    /**
     * @var SpecificationInterface[] // Виправлено: Specification -> SpecificationInterface
     */
    private array $specifications;

    /**
     * @param SpecificationInterface[] $specifications Масив специфікацій, які потрібно об'єднати.
     */
    public function __construct(array $specifications)
    {
        // ВАЖЛИВО: Додаємо фільтрацію, щоб переконатися, що всі елементи є інстансами SpecificationInterface
        $this->specifications = array_filter($specifications, function($spec) {
            return $spec instanceof SpecificationInterface;
        });
    }

    /**
     * Перевіряє, чи задовольняє книга всім вкладеним специфікаціям.
     *
     * @param Book $book Об'єкт книги для перевірки.
     * @return bool True, якщо книга задовольняє всім специфікаціям, інакше false.
     */
    public function isSatisfiedBy(Book $book): bool
    {
        foreach ($this->specifications as $specification) {
            // Якщо хоча б одна специфікація не задоволена, вся композитна специфікація не задоволена.
            if (!$specification->isSatisfiedBy($book)) {
                return false;
            }
        }
        // Якщо всі специфікації задоволені, повертаємо true.
        return true;
    }
}