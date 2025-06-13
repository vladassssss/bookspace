<?php

namespace App\Specifications;

use App\Models\Book; // Переконайтеся, що шлях до вашої моделі Book правильний

class ThemeSpecification implements SpecificationInterface
{
    private string $theme;

    public function __construct(string $theme)
    {
        $this->theme = $theme;
    }

    public function isSatisfiedBy(Book $book): bool
    {
        // Якщо тема порожня, ця специфікація не фільтрує, завжди задовольняє.
        if (empty($this->theme)) {
            return true;
        }

        // !!! ДУЖЕ ВАЖЛИВО !!!
        // Ця логіка залежить від того, як у вашій моделі Book зберігаються теми.
        // Припустимо, у Book є метод getThemes(), який повертає:
        // 1. Рядок з темами через кому (наприклад, "космос, пригоди, наука")
        // 2. Або масив рядків (наприклад, ['космос', 'пригоди', 'наука'])

        $bookThemesData = $book->getThemes(); // Отримуємо теми книги

        // Перетворюємо шукану тему до нижнього регістру для порівняння без урахування регістру.
        $searchThemeLower = mb_strtolower($this->theme);

        // Якщо теми книги зберігаються як рядок через кому:
        if (is_string($bookThemesData)) {
            $themesArray = array_map('trim', explode(',', mb_strtolower($bookThemesData)));
            return in_array($searchThemeLower, $themesArray);
        }

        // Якщо теми книги зберігаються як масив:
        if (is_array($bookThemesData)) {
            $lowerCaseBookThemes = array_map('mb_strtolower', $bookThemesData);
            return in_array($searchThemeLower, $lowerCaseBookThemes);
        }

        // Якщо невідомий формат, або теми відсутні, повертаємо false (або true, залежить від бажаної поведінки)
        return false;
    }
}