<?php

namespace App\Specifications;

use App\Models\Book;

class MoodSpecification implements SpecificationInterface
{
    private ?string $mood;

    public function __construct(?string $mood)
    {
        $this->mood = $mood;
    }

    public function isSatisfiedBy(Book $book): bool
    {
        // Якщо настрій не вибрано, вважаємо, що специфікація задоволена (не фільтруємо за настроєм).
        if ($this->mood === null || $this->mood === '') {
            return true;
        }

        // Отримуємо масив настроїв книги
        // У MoodSpecification.php, метод isSatisfiedBy
// ...
$bookMoods = $book->getMoodsList(); // <-- ВИПРАВЛЕНО!
// ...

        // Перетворюємо всі настрої книги та шуканий настрій до нижнього регістру для порівняння без урахування регістру.
        $lowerCaseBookMoods = array_map('mb_strtolower', $bookMoods);
        $lowerCaseSearchMood = mb_strtolower($this->mood);

        // Перевіряємо, чи в масиві настроїв книги є вибраний настрій.
        return in_array($lowerCaseSearchMood, $lowerCaseBookMoods);
    }
}