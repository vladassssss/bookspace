<?php
// public/recommendations.php

// Підключаємо необхідні файли
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';

// --- Специфікації ---
require_once __DIR__ . '/../app/Specifications/SpecificationInterface.php';
require_once __DIR__ . '/../app/Specifications/GenreSpecification.php';
require_once __DIR__ . '/../app/Specifications/MoodSpecification.php';
require_once __DIR__ . '/../app/Specifications/AndSpecification.php';
// require_once __DIR__ . '/../app/Specifications/SearchQuerySpecification.php'; // Закоментуйте або видаліть, якщо не використовуєте
require_once __DIR__ . '/../app/Specifications/DescriptionSearchSpecification.php'; // Переконайтеся, що цей файл існує

// --- Інші сервіси/репозиторії ---
require_once __DIR__ . '/../app/Services/IRatingService.php';
require_once __DIR__ . '/../app/Services/RatingService.php';
require_once __DIR__ . '/../app/Repositories/IRatingRepository.php';
require_once __DIR__ . '/../app/Repositories/RatingRepository.php';

use App\Database\Connection;
use App\Repositories\BookstoreRepository;
use App\Services\BookstoreService;
use App\Controllers\BookstoreController;
use App\Services\RatingService;
use App\Repositories\RatingRepository;


try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("Помилка підключення до бази даних: " . $e->getMessage());
    die("Помилка підключення до бази даних.");
}

$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);
$ratingRepository = new RatingRepository($db);
$ratingService = new RatingService($ratingRepository);
$controller = new BookstoreController($bookstoreService, $ratingService);

// Отримуємо дані з POST-запиту
$genre = $_POST['genre'] ?? null;
$mood = $_POST['mood'] ?? null;
$searchQuery = $_POST['search_query'] ?? null; // Припустимо, ім'я поля у формі 'search_query'

// --- ВИПРАВЛЕНО: ЗМІНЕНО $descriptionKeywords НА $searchQuery ---
error_log("DEBUG: recommendations.php - Genre: " . var_export($genre, true));
error_log("DEBUG: recommendations.php - Search Query (Description Keywords): " . var_export($searchQuery, true)); // Змінено тут
error_log("DEBUG: recommendations.php - Mood: " . var_export($mood, true));

// !!! Видаліть ці рядки, якщо тем більше немає !!!
// $theme_keywords = $_POST['theme'] ?? '';
// $themes = array_filter(array_map('trim', explode(',', $theme_keywords)));

// Викликаємо метод getRecommendations з правильними параметрами
// ПЕРЕВІРТЕ ПОРЯДОК АРГУМЕНТІВ: getRecommendations($genre, $mood, $searchQuery);
// Якщо у вашому контролері метод getRecommendations визначений як
// public function getRecommendations($genre = '', $descriptionSearch = null, $mood = '')
// Тоді порядок має бути: $controller->getRecommendations($genre, $searchQuery, $mood);
$recommended_books = $controller->getRecommendations($genre, $searchQuery, $mood); // Змінено порядок аргументів

?>


<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Рекомендації книг</title>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #1e3659, #0b132b);
            color: #f8f8f2;
            margin: 0;
            padding: 60px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        h1 {
            color: #ff79c6;
            margin-bottom: 40px;
            font-size: 3em;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.6);
        }
        .books-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            width: 95%;
            max-width: 1600px;
        }
        .book {
            background-color: #282a36;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            position: relative;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .book:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.6);
        }
        .book::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #bd93f9, #ff79c6, #8be9fd);
            border-radius: 8px 8px 0 0;
        }
        .book img {
            width: 180px;
            height: 240px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
            align-self: center;
            box-shadow: 3px 3px 8px rgba(0,0,0,0.3);
            transition: transform 0.3s;
        }
        .book:hover img {
            transform: scale(1.08);
        }
        .book h3 {
            color: #f8f8f2;
            font-size: 1.6em;
            margin: 15px 0 10px;
            text-align: center;
        }
        .book p {
            color: #d4d4d4;
            font-size: 1em;
            margin-bottom: 12px;
            text-align: center;
        }
        .book p.genre {
            font-style: italic;
            color: #ccc;
        }
        .book .description {
            color: #aaa;
            font-size: 0.95em;
            margin-bottom: 20px;
            text-align: center;
        }
        .book a {
            color: #8be9fd;
            text-decoration: none;
            font-weight: bold;
            display: block;
            text-align: center;
            border: 2px solid #8be9fd;
            border-radius: 8px;
            padding: 12px 20px;
            margin-top: 20px;
            transition: 0.3s;
        }
        .book a:hover {
            color: #282a36;
            background-color: #8be9fd;
            box-shadow: 0 4px 8px rgba(139,233,253,0.5);
        }
        p.no-results {
            color: #ffb86c;
            font-size: 1.3em;
            margin-top: 60px;
            text-align: center;
        }
        p.try-again {
            margin-top: 40px;
            text-align: center;
        }
        p.try-again a {
            color: #f1fa8c;
            text-decoration: none;
            font-weight: bold;
        }
        p.try-again a:hover {
            color: #ffb86c;
        }
    </style>
</head>
<body>
    <h1>Рекомендовані книги</h1>

    <?php if (!empty($recommended_books)): ?>
        <div class="books-container">
            <?php foreach ($recommended_books as $book): ?>
                <div class="book">
                    <img src="/bookshop/bookshop/public/images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>">
                    <h3><?= htmlspecialchars($book->getTitle()); ?></h3>
                    <p class="author">Автор: <?= htmlspecialchars($book->getAuthor()); ?></p>
                    <p class="genre"><?= htmlspecialchars($book->getGenre()); ?></p>
                    <p class="description"><?= htmlspecialchars(mb_substr($book->getDescription(), 0, 150, 'UTF-8')); ?>...</p>
                    <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>">Детальніше</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="no-results">На жаль, немає книг за обраними критеріями.</p>
        <p class="try-again"><a href="index.php">Спробувати ще раз</a></p>
    <?php endif; ?>
</body>
</html>