<?php
session_start();

// Підключення необхідних файлів
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';

// Файли для відгуків
require_once __DIR__ . '/../app/Repositories/ReviewRepository.php';
require_once __DIR__ . '/../app/Services/ReviewService.php';
require_once __DIR__ . '/../app/Controllers/ReviewController.php';

use App\Database\Connection;
use App\Controllers\ReviewController;
use App\Controllers\BookstoreController;

// Припустимо, що ми отримали id книги через GET
$bookId = $_GET['id'] ?? null;
if (!$bookId) {
    die("Не вказано id книги.");
}

// Ініціалізація підключення до БД
$db = Connection::getInstance()->getConnection();

// Ініціалізація контролера книг
$bookstoreRepository = new \App\Repositories\BookstoreRepository($db);
$bookstoreService = new \App\Services\BookstoreService($bookstoreRepository);
$bookstoreController = new BookstoreController($bookstoreService);

// Отримання інформації про книгу
$book = $bookstoreController->getBookById($bookId);
if (!$book) {
    die("Книгу не знайдено.");
}

// Ініціалізація контролера відгуків
$reviewRepository = new \App\Repositories\ReviewRepository($db);
$reviewService = new \App\Services\ReviewService($reviewRepository);
$reviewController = new ReviewController($reviewService);

// Обробка додавання відгуку (якщо надсилається форма)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_review') {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = $_POST['comment'] ?? '';
    
    // Перевірка користувача
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    $userId = $_SESSION['user_id'];
    
    // Додаємо відгук
    $reviewController->addReview($bookId, $userId, $rating, $comment);
    
    // Перенаправлення, щоб уникнути повторного відправлення форми 
    header("Location: book.php?id=" . $bookId);
    exit();
}

// Отримання відгуків для книги
$reviews = $reviewController->fetchReviews($bookId);
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($book->getTitle()) ?> - Відгуки</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .review-section {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .review-section h2 {
            margin-bottom: 20px;
        }
        .review {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .review:last-child {
            border-bottom: none;
        }
        .review .rating {
            color: #f39c12;
            font-size: 1.2em;
        }
        .review .comment {
            margin: 5px 0;
        }
        .review-form textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px;
            font-size: 1em;
            margin-bottom: 10px;
        }
        .review-form input[type="number"] {
            width: 60px;
            padding: 5px;
            margin-right: 10px;
        }
        .review-form button {
            padding: 8px 16px;
            font-size: 1em;
            background-color: #333;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .review-form button:hover {
            background-color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Інформація про книгу -->
        <h1><?= htmlspecialchars($book->getTitle()) ?></h1>
        <p>Автор: <?= htmlspecialchars($book->getAuthor()) ?></p>
        <img src="images/<?= htmlspecialchars($book->getCoverImage()) ?>" alt="<?= htmlspecialchars($book->getTitle()) ?>" style="width:180px; height:auto;">
        <p>Ціна: <?= htmlspecialchars($book->getPrice()) ?> ₴</p>
        
        <!-- Секція відгуків -->
        <div class="review-section">
            <h2>Відгуки</h2>
            
            <?php if (empty($reviews)): ?>
                <p>Відгуки відсутні.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review">
                        <div class="rating">Рейтинг: <?= htmlspecialchars($review->getRating()) ?>/5</div>
                        <div class="comment"><?= nl2br(htmlspecialchars($review->getComment())) ?></div>
                        <div class="date" style="font-size:0.9em; color:#777;"><?= htmlspecialchars($review->getCreatedAt()) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Форма додавання відгуку -->
            <div class="review-form">
                <h3>Залишити відгук</h3>
                <form action="book.php?id=<?= $bookId ?>" method="POST">
                    <input type="hidden" name="action" value="add_review">
                    <label for="rating">Оцінка (1-5):</label>
                    <input type="number" id="rating" name="rating" min="1" max="5" required>
                    <br><br>
                    <label for="comment">Коментар:</label><br>
                    <textarea id="comment" name="comment" placeholder="Ваш відгук ..." required></textarea>
                    <br>
                    <button type="submit">Відправити</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
