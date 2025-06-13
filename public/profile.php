<?php
session_start();
error_log("Profile Page: User ID from session: " . ($_SESSION['user_id'] ?? 'NULL'));
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Manual includes for critical classes to ensure they are available
require_once __DIR__ . '/../app/Database/Connection.php';

// Profile related classes (for ordered books and genre stats)
require_once __DIR__ . '/../app/Repositories/ProfileRepository.php';
require_once __DIR__ . '/../app/Services/ProfileService.php';
// You might remove ProfileController if it's not used elsewhere or its responsibilities are split
// require_once __DIR__ . '/../app/Controllers/ProfileController.php'; 

// Wishlist related classes (for favorite books)
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php'; // Required by WishlistRepository
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php'; // Required by WishlistRepository
require_once __DIR__ . '/../app/Repositories/IWishlistRepository.php';
require_once __DIR__ . '/../app/Repositories/WishlistRepository.php';
require_once __DIR__ . '/../app/Services/IWishlistService.php';
require_once __DIR__ . '/../app/Services/WishlistService.php';

// Models
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Models/WishlistItem.php'; // Make sure this is included for Wishlist logic

require_once __DIR__ . '/../app/Models/CartItem.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Controllers/CartController.php';

use App\Repositories\CartRepository;
use App\Services\CartService;
use App\Controllers\CartController;

// Автозавантаження класів (якщо воно працює коректно, більшість з require_once вище стануть надлишковими)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("Autoloader: File not found: " . $file);
    }
});

use App\Database\Connection;
use App\Repositories\ProfileRepository;
use App\Services\ProfileService;
// use App\Controllers\ProfileController; // Only if you still use it for non-wishlist profile data

use App\Repositories\BookstoreRepository;
use App\Repositories\WishlistRepository;
use App\Services\WishlistService;
// use App\Models\WishlistItem; // Not directly used here, but good to ensure it's available

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect if user is not authenticated
    exit;
}

$userId = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

$orderedBooks = [];
$genreStats = [];
$favoriteBooks = [];
$favoriteGenreStatsForJs = [];
$mostFrequentGenre = 'default';

try {
    $db = Connection::getInstance()->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("set names utf8");

    // Initialize ProfileService for ordered books and genre stats
    $profileRepository = new ProfileRepository($db);
    $profileService = new ProfileService($profileRepository);
    // If you used ProfileController, you'd call it here:
    // $profileController = new ProfileController($profileService);
    // $profileData = $profileController->getUserProfileData($userId);

    // Assuming ProfileService can directly give ordered books and genre stats
    // You might need to adjust methods in ProfileService to directly return these
    $profileData = $profileService->getUserProfileData($userId); // Get all profile data
    $orderedBooks = $profileData['ordered_books'] ?? [];
    $genreStats = $profileData['genre_stats'] ?? [];


    // Initialize WishlistService for favorite books
    $bookstoreRepository = new BookstoreRepository($db); // WishlistRepository requires BookstoreRepository
    $wishlistRepository = new WishlistRepository($db, $bookstoreRepository);
    $wishlistService = new WishlistService($wishlistRepository);

    // Get favorite books from WishlistService
    $favoriteBooks = $wishlistService->getUserWishlist($userId);


    // --- Aggregation for favorite books genre stats ---
    $favoriteGenreStats = [];
    foreach ($favoriteBooks as $book) { // $book is expected to be a Book object
        $genre = $book->getGenre();
        if (!isset($favoriteGenreStats[$genre])) {
            $favoriteGenreStats[$genre] = 0;
        }
        $favoriteGenreStats[$genre]++;
    }
    // Convert associative array to indexed for JavaScript
    foreach ($favoriteGenreStats as $genre => $count) {
        $favoriteGenreStatsForJs[] = [
            'genre' => $genre,
            'count' => $count
        ];
    }
    // --- End Aggregation ---

    // Determine the most frequent genre for background (from ordered books)
    $maxQuantity = 0;
    foreach ($genreStats as $genreInfo) {
        if ($genreInfo['total_quantity'] > $maxQuantity) {
            $maxQuantity = $genreInfo['total_quantity'];
            $mostFrequentGenre = strtolower(str_replace(' ', '-', $genreInfo['genre']));
        }
    }

} catch (PDOException $e) {
    error_log("Database error on profile page: " . $e->getMessage());
    die("Помилка при завантаженні даних профілю: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Error on profile page: " . $e->getMessage());
    die("Помилка: " . $e->getMessage());
}
$cartItems = [];
if (isset($_SESSION['user_id'])) {
    try {
        $cartRepository = new CartRepository($db);
        // Ensure $bookstoreRepository is initialized BEFORE this line.
        // It is, which is good!
        $cartService = new CartService($cartRepository, $bookstoreRepository); // <-- FIX APPLIED HERE!
        $cartController = new CartController($cartService);
        $cartItems = $cartController->fetchUserCart($_SESSION['user_id']);
    } catch (Exception $e) {
        error_log("Помилка отримання кошика на discounts.php: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Профіль користувача - <?= $username ?></title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
     <style>
        /* Загальні стилі */
        

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            background-color: #333;
            color: white;
            padding: 10px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
        }

        nav ul.nav-left li {
            margin-right: 20px;
            position: relative; /* Для сайдбару */
        }

        nav ul.nav-left li a {
            color: white;
            text-decoration: none;
            padding: 5px 0;
            transition: color 0.3s ease;
        }

        nav ul.nav-left li a:hover {
            color: #007bff;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        /* Кнопка категорій (для сайдбару) */
        .category-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .category-button:hover {
            background-color: #0056b3;
        }

        /* Сайдбар */
        .sidebar {
            position: absolute;
            top: 100%; /* Розміщуємо під кнопкою */
            left: 0;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 180px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0s linear 0.3s;
        }

        .sidebar.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            transition-delay: 0s;
        }

        .sidebar ul {
            flex-direction: column;
            padding: 10px 0;
        }

        .sidebar ul li {
            margin: 0;
            width: 100%;
        }

        .sidebar ul li a {
            display: block;
            padding: 10px 20px;
            color: #333;
            text-decoration: none;
            white-space: nowrap;
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .sidebar ul li a:hover {
            background-color: #e2e6ea;
            color: #007bff;
        }

        /* Пошукова форма */
        .search-form {
            display: flex;
            border-radius: 5px;
            overflow: hidden;
            background-color: white;
        }

        .search-form input {
            border: none;
            padding: 8px 10px;
            font-size: 0.9em;
            outline: none;
            flex-grow: 1;
        }

        .search-form button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .search-form button:hover {
            background-color: #0056b3;
        }

        /* Секція авторизації */
        .auth-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .auth-section button {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }

        .auth-section button.login-btn { background-color: #007bff; }
        .auth-section button.login-btn:hover { background-color: #0056b3; }
        .auth-section button.register-btn { background-color: #6c757d; }
        .auth-section button.register-btn:hover { background-color: #5a6268; }
        .auth-section button.logout-btn { background-color: #dc3545; }
        .auth-section button.logout-btn:hover { background-color: #c82333; }

        /* Іконка кошика */
        .cart-link {
            color: white;
            text-decoration: none;
            font-size: 1.2em;
            position: relative;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .cart-link:hover {
            color: #007bff;
        }

        #cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: red;
            color: white;
            font-size: 0.7em;
            border-radius: 50%;
            padding: 2px 5px;
            line-height: 1;
            min-width: 15px;
            text-align: center;
        }

        /* Анімація кошика */
        .cart-link.bump {
            animation: bump 0.3s ease-out;
        }

        @keyframes bump {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Іконка профілю */
        .profile-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .profile-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .profile-icon {
            width: 24px;
            height: 24px;
            vertical-align: middle;
        }

        .username-display {
            font-size: 1em;
        }

        /* Hero Section */
        .hero {
            position: relative;
            width: 100%;
            height: 300px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
        }

        .hero .background-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -1;
            filter: brightness(60%);
        }

        .hero-text {
            z-index: 1;
            padding: 20px;
            max-width: 800px;
        }

        .hero-text h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .hero-text h2 {
            font-size: 1.2em;
            font-weight: normal;
        }

        .htext {
            text-align: center;
            font-size: 2em;
            margin: 30px 0 20px;
            color: #333;
        }

        /* Carousel */
        .carousel-container {
            position: relative;
            width: 90%;
            max-width: 1200px;
            margin: 0 auto 50px;
            overflow: hidden;
            padding: 20px 0;
        }

        .slider-track {
            display: flex;
            transition: transform 0.5s ease-in-out;
            gap: 20px; /* Відстань між книгами */
            padding-bottom: 20px; /* Для тіней */
        }

        .book {
            flex: 0 0 auto; /* Не стискати, не розтягувати, авто ширина */
            width: 220px; /* Фіксована ширина для книг */
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 15px;
            text-align: center;
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .book:hover {
            transform: translateY(-5px);
        }

        .book img {
            max-width: 100%;
            height: 250px; /* Фіксована висота для обкладинок */
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .book h3 {
            font-size: 1.2em;
            margin-bottom: 5px;
            color: #333;
        }

        .book p {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .book-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }

        .book-buttons button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
        }

        .order-button {
            background-color: #28a745;
            color: white;
        }

        .order-button:hover {
            background-color: #218838;
        }

        .details-button {
            background-color: #007bff;
            color: white;
        }

        .details-button:hover {
            background-color: #0056b3;
        }

        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 2em;
            border-radius: 50%;
            z-index: 10;
            transition: background-color 0.3s ease;
        }

        .slider-btn:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }

        .slider-btn.prev {
            left: -25px;
        }

        .slider-btn.next {
            right: -25px;
        }

        .view-all-container {
            text-align: center;
            margin-top: 30px;
        }

        #viewAllBooks {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
        }

        #viewAllBooks:hover {
            background-color: #0056b3;
        }

        /* Footer */
        footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: auto; /* Притискає футер до низу сторінки */
        }

        /* СТИЛІ ДЛЯ СТОРІНКИ ПРОФІЛЮ */
        body.genre-default {
            background-color: #f0f0f0;
            /* background-image: url('images/default_bg.jpg'); */ /* Замініть на реальні шляхи */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: #333;
        }
        body.genre-детектив { /* Changed to lowercase */
            background-image: url('images/backgrounds/detective_bg.jpg'); /* Ваш файл */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: #eee; /* Можливо, змінити на світлий, якщо фон темний */
        }
        body.genre-фантастика { /* Changed to lowercase */
            background-image: url('images/backgrounds/fantasy_bg.jpg'); /* Ваш файл */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: #eee;
        }
        body.genre-наукова-фантастика { /* Changed to lowercase and hyphenated */
            background-image: url('images/backgrounds/sci-fi_bg.jpg'); /* Ваш файл */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: #eee;
        }
        body.genre-жахи { /* Changed to lowercase */
            background-image: url('images/backgrounds/horror_bg.jpg'); /* Ваш файл */
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            color: #eee;
        }
        .profile-container {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            max-width: 1200px;
            margin: 30px auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .profile-section {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            grid-column: 1 / -1; /* Займає всю ширину */
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-header h1 {
            color: #007bff;
            font-size: 2.5em;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .profile-section h2 {
            color: #333;
            margin-bottom: 20px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .profile-section p {
            color: #555;
        }

        .ordered-books-list, .favorite-books-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .book-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .book-item img {
            max-width: 100px;
            height: auto;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .book-item h3 {
            font-size: 1em;
            margin-bottom: 5px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .book-item p {
            font-size: 0.85em;
            color: #666;
        }
        .chart-container {
            width: 100%;
            height: 350px; /* Фіксована висота для діаграми */
            margin: 20px auto 0;
            display: flex; /* Для центрування */
            justify-content: center;
            align-items: center;
        }
        .no-data-message {
            text-align: center;
            color: #777;
            padding: 20px;
            font-style: italic;
        }
        .remove-favorite-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
            font-size: 0.8em;
            transition: background-color 0.3s ease;
        }
        .remove-favorite-btn:hover {
            background-color: #c82333;
        }
        /* Вже існуючі стилі для wishlist-button, їх можна перенести в styles.css */
        .wishlist-button {
            position: absolute;
            top: 5px;
            right: 5px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            z-index: 10;
            opacity: 0.7;
            transition: opacity 0.3s ease, transform 0.2s ease;
        }

        .wishlist-button:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .wishlist-button svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: #888;
            stroke-width: 2;
            transition: fill 0.2s ease, stroke 0.2s ease;
        }

        .wishlist-button.active-favorite svg {
            fill: #e74c3c;
            stroke: #e74c3c;
        }

        .wishlist-button:hover:not(.active-favorite) svg {
            stroke: #e74c3c;
            fill: rgba(231, 76, 60, 0.2);
        }
        .wishlist-text-login {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.75em;
            color: #555;
            text-align: center;
            line-height: 1.3;
            padding: 5px;
            background: rgba(255, 255, 255, 0.85);
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            z-index: 10;
            max-width: 90px;
            pointer-events: none;
        }
        .wishlist-text-login a {
            color: #3498db;
            text-decoration: underline;
            font-weight: bold;
            pointer-events: auto;
        }
        /* Рекламні блоки */
.ad-section {
    grid-column: 1 / -1; /* Займає всю ширину */
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    justify-content: center;
    margin: 40px 0;
}

.ad-banner {
    background-color: #e9f5ff; /* Легкий блакитний фон для акценту */
    border: 1px solid #cce5ff;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 15px;
    max-width: 550px; /* Обмеження ширини для банерів */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    text-decoration: none; /* Щоб посилання не було підкреслено */
    color: inherit; /* Успадковує колір тексту */
}

.ad-banner:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.ad-banner.vertical {
    flex-direction: column;
    text-align: center;
    max-width: 250px; /* Менша ширина для вертикального банера */
}

.ad-banner img {
    max-width: 150px;
    height: auto;
    border-radius: 8px;
    margin-right: 15px;
    object-fit: cover;
}

.ad-banner.vertical img {
    margin-right: 0;
    margin-bottom: 15px;
    max-width: 100%;
    height: 180px; /* Фіксована висота для вертикального зображення */
}

.ad-content {
    flex-grow: 1;
}

.ad-content h3 {
    color: #007bff;
    font-size: 1.4em;
    margin-top: 0;
    margin-bottom: 10px;
}

.ad-content p {
    font-size: 0.95em;
    color: #555;
    line-height: 1.4;
    margin-bottom: 10px;
}

.ad-content .ad-button {
    display: inline-block;
    background-color: #28a745;
    color: white;
    padding: 8px 15px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s ease;
}

.ad-content .ad-button:hover {
    background-color: #218838;
}

/* Стиль для кнопки "До кошика" */
.order-button {
    display: inline-block; /* Щоб можна було застосовувати ширину/висоту та відступи */
    padding: 10px 20px; /* Внутрішні відступи */
    margin-top: 10px; /* Відступ зверху від інших елементів */
    background-color: #4CAF50; /* Приємний зелений колір (можна змінити) */
    color: white; /* Колір тексту білий */
    border: none; /* Без рамки */
    border-radius: 5px; /* Закруглені кути */
    cursor: pointer; /* Зміна курсору при наведенні */
    font-size: 16px; /* Розмір шрифту */
    font-weight: bold; /* Жирний шрифт */
    text-align: center; /* Вирівнювання тексту по центру */
    text-decoration: none; /* Без підкреслення (якщо це посилання) */
    transition: background-color 0.3s ease, transform 0.2s ease; /* Плавний перехід при наведенні */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); /* Легка тінь для об'ємності */
}

.order-button:hover {
    background-color: #45a049; /* Темніший зелений при наведенні */
    transform: translateY(-2px); /* Трохи піднімається при наведенні */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3); /* Тінь стає більшою */
}

.order-button:active {
    background-color: #3e8e41; /* Ще темніший зелений при натисканні */
    transform: translateY(0); /* Повертається на місце */
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2); /* Тінь зменшується */
}

/* Якщо кнопка має бути відключена (наприклад, книги немає в наявності) */
.order-button:disabled {
    background-color: #cccccc; /* Сірий колір */
    cursor: not-allowed; /* Заборонений курсор */
    transform: none; /* Без ефектів при наведенні */
    box-shadow: none; /* Без тіні */
    opacity: 0.7; /* Зробити напівпрозорою */
}
    </style>
</head>
<body class="genre-<?= $mostFrequentGenre ?>">
    <header>
        <div class="container nav-container">
            <nav>
                <ul class="nav-left">
                    <li>
                        <button id="toggleSidebar" class="category-button">Категорії книг</button>
                    </li>
                    <li>
                        <div id="sidebar" class="sidebar hidden">
                            <ul>
                                <li><a href="index.php">Усі</a></li>
                                <li><a href="index.php?genre=Детектив">Детектив</a></li>
                                <li><a href="index.php?genre=Фантастика">Фантастика</a></li>
                                <li><a href="index.php?genre=Наукова фантастика">Наукова фантастика</a></li>
                                <li><a href="index.php?genre=Жахи">Жахи</a></li>
                            </ul>
                        </div>
                    </li>
                    <li><a href="popular.php">Популярне</a></li>
                    <li><a href="discounts.php">Знижки</a></li>
                    <li><a href="recommendation_test.php">Підбір книги</a></li>
                </ul>
                <div class="nav-right">
                    <form class="search-form" method="GET" action="search.php">
                        <input type="text" name="query" placeholder="Знайти книжку...">
                        <button type="submit">🔍</button>
                    </form>
                    <div class="auth-section">
                        <a href="profile.php" class="profile-link" title="Мій профіль">
                            <svg class="profile-icon" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path>
                            </svg>
                            <span class="username-display"><?= $username ?></span>
                        </a>
                        <?php
                    // Припускаємо, що $cartItems ініціалізовано десь вище в PHP-скрипті
                    $cartItemCount = isset($cartItems) ? count($cartItems) : 0;
                    ?>
                       <a href="cart.php" class="cart-link" title="Мій кошик">
                        🛒<span id="cart-count"><?= $cartItemCount; ?></span>
                    </a>
                        <button class="logout-btn" onclick="window.location.href='logout.php'">Вийти</button>
                    </div>
                </div>
            </nav>
        </div>
    </header>

   <main>
    <div class="profile-container">
        <div class="profile-header">
            <h1>Мій Профіль: <?= $username ?></h1>
        </div>

        <div class="profile-section order-stats">
            <h2>Статистика замовлень по жанрах</h2>
            <?php if (!empty($genreStats)): ?>
                <div class="chart-container">
                    <canvas id="genreChart"></canvas>
                </div>
            <?php else: ?>
                <p class="no-data-message">Ви ще не замовляли книги.</p>
            <?php endif; ?>
        </div>

        <div class="profile-section favorite-books">
            <h2>Мої улюблені книги</h2>
            <?php if (!empty($favoriteBooks)): ?>
                <div class="chart-container">
                    <canvas id="favoriteGenreChart"></canvas>
                </div>
                <div class="favorite-books-list">
                    <?php foreach ($favoriteBooks as $book): ?>
                        <div class="book-item">
                            <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>">
                                <img src="images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>">
                            </a>
                            <h3><?= htmlspecialchars($book->getTitle()); ?></h3>
                            <p><?= htmlspecialchars($book->getAuthor()); ?></p>
                            <button class="remove-favorite-btn" data-id="<?= htmlspecialchars($book->getId()); ?>">Видалити</button>
                             <button class="order-button" data-id="<?= htmlspecialchars($book->getId()); ?>">
                                До кошика
                            </button>
                            
                        </div>
                    <?php endforeach; ?>
                   
                </div>
            <?php else: ?>
                <p class="no-data-message">У вас ще немає улюблених книг.</p>
            <?php endif; ?>
        </div>

        <div class="ad-section">
            <a href="https://prom.ua/ua/" target="_blank" class="ad-banner">
                <img src="images/ad_prom.jpg" alt="Реклама Prom.ua">
                <div class="ad-content">
                    <h3>Знайди все, що потрібно, на Prom.ua!</h3>
                    <p>Мільйони товарів від перевірених продавців. Швидка доставка та найкращі ціни.</p>
                    <span class="ad-button">Перейти на Prom.ua</span>
                </div>
            </a>

            <a href="https://rozetka.com.ua/" target="_blank" class="ad-banner vertical">
                <img src="images/ad_rozetka.jpg" alt="Реклама Rozetka">
                <div class="ad-content">
                    <h3>Розетка: Твій інтернет-магазин</h3>
                    <p>Широкий асортимент електроніки, побутової техніки та багато іншого. Замовляй зараз!</p>
                    <span class="ad-button">Купити на Rozetka</span>
                </div>
            </a>
            
            <a href="https://knigolove.ua/" target="_blank" class="ad-banner">
                <img src="images/ad_knigolove.jpg" alt="Реклама Книголав">
                <div class="ad-content">
                    <h3>Новинки від "Книголав"</h3>
                    <p>Відкрий для себе захопливі історії та улюблених авторів. Ексклюзивні видання вже чекають!</p>
                    <span class="ad-button">Дивитись книги</span>
                </div>
            </a>

            </div>
        <div class="profile-section ordered-books">
            <h2>Історія замовлень</h2>
            <?php if (!empty($orderedBooks)): ?>
                <div class="ordered-books-list">
                    <?php foreach ($orderedBooks as $item): ?>
                        <?php $book = $item['book']; ?>
                        <div class="book-item">
                            <a href="book.php?id=<?= htmlspecialchars($book->getId()); ?>">
                                <img src="images/<?= htmlspecialchars($book->getCoverImage()); ?>" alt="<?= htmlspecialchars($book->getTitle()); ?>">
                            </a>
                            <h3><?= htmlspecialchars($book->getTitle()); ?></h3>
                            <p><?= htmlspecialchars($book->getAuthor()); ?></p>
                            <p>Кількість: <?= htmlspecialchars($item['quantity']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data-message">Ви ще не робили замовлень.</p>
            <?php endif; ?>
        </div>
    </div>
</main>

    <footer class="footer">
  <div class="footer-content">
    <div class="social-icons">
      <a href="https://facebook.com" target="_blank" aria-label="Facebook">
        <svg width="30" height="30" fill="white" viewBox="0 0 24 24">
          <path d="M22.675 0H1.325C.593 0 0 .593 0 1.326v21.348C0 
          23.406.593 24 1.325 24H12.82v-9.294H9.692V11.01h3.128V8.413c0-3.1 
          1.893-4.788 4.659-4.788 1.325 0 2.464.099 
          2.795.143v3.24l-1.918.001c-1.504 
          0-1.796.715-1.796 1.763v2.313h3.59l-.467 
          3.696h-3.123V24h6.116C23.407 24 24 
          23.407 24 22.674V1.326C24 .593 23.407 
          0 22.675 0z"/>
        </svg>
      </a>
      <a href="https://instagram.com" target="_blank" aria-label="Instagram">
        <svg width="30" height="30" fill="white" viewBox="0 0 24 24">
          <path d="M12 2.2c3.2 0 3.584.012 4.85.07 1.17.056 1.96.24 
          2.416.403a4.92 4.92 0 011.768 1.01 4.92 4.92 0 
          011.01 1.768c.163.456.347 1.246.403 2.416.058 
          1.266.07 1.65.07 4.85s-.012 3.584-.07 
          4.85c-.056 1.17-.24 1.96-.403 2.416a4.92 
          4.92 0 01-1.01 1.768 4.92 4.92 0 
          01-1.768 1.01c-.456.163-1.246.347-2.416.403-1.266.058-1.65.07-4.85.07s-3.584-.012-4.85-.07c-1.17-.056-1.96-.24-2.416-.403a4.92 
          4.92 0 01-1.768-1.01 4.92 4.92 0 
          01-1.01-1.768c-.163-.456-.347-1.246-.403-2.416C2.212 
          15.784 2.2 15.4 2.2 12s.012-3.584.07-4.85c.056-1.17.24-1.96.403-2.416a4.92 
          4.92 0 011.01-1.768 4.92 4.92 0 
          011.768-1.01c.456-.163 1.246-.347 
          2.416-.403C8.416 2.212 8.8 2.2 12 
          2.2zm0-2.2C8.735 0 8.332.014 7.052.072 5.774.129 4.672.348 
          3.758.735A7.15 7.15 0 001.443 
          1.443 7.15 7.15 0 00.735 
          3.758C.348 4.672.129 5.774.072 7.052.014 
          8.332 0 8.735 0 12c0 3.265.014 3.668.072 
          4.948.057 1.278.276 2.38.663 
          3.294.387.914.908 1.68 1.715 
          2.487a7.15 7.15 0 002.487 1.715c.914.387 
          2.016.606 3.294.663C8.332 23.986 8.735 24 
          12 24s3.668-.014 4.948-.072c1.278-.057 
          2.38-.276 3.294-.663a7.15 7.15 0 
          002.487-1.715 7.15 7.15 0 
          001.715-2.487c.387-.914.606-2.016.663-3.294.058-1.28.072-1.683.072-4.948 
          0-3.265-.014-3.668-.072-4.948-.057-1.278-.276-2.38-.663-3.294a7.15 
          7.15 0 00-1.715-2.487A7.15 7.15 0 
          0020.242.735c-.914-.387-2.016-.606-3.294-.663C15.668.014 
          15.265 0 12 0zm0 5.838a6.162 6.162 0 100 
          12.324 6.162 6.162 0 000-12.324zm0 
          10.162a4 4 0 110-8 4 4 0 010 
          8zm6.406-11.845a1.44 1.44 0 11-2.88 0 1.44 
          1.44 0 012.88 0z"/>
        </svg>
      </a>
    </div>

    <div class="footer-info">
      <p>📞 Телефон: +380 12 345 6789</p>
      <p>✉️ Email: info@shop.com</p>
    </div>

        <p class="copyright">© 2025 Магазин. Всі права захищено.</p>
  </div>
</footer>

    <script>
         document.querySelectorAll('.order-button').forEach(button => {
                button.addEventListener('click', async () => {
                    const bookId = button.getAttribute('data-id');
                    if (!bookId) {
                        alert('Помилка: Не вдалося отримати ID книги.');
                        return;
                    }

                    button.disabled = true;
                    button.textContent = 'Додаємо...';

                    try {
                        const response = await fetch('add_to_cart.php', { // Шлях до скрипту
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: bookId, quantity: 1 })
                        });

                        if (!response.ok) {
                            const errorText = await response.text();
                            alert(`Помилка сервера: ${response.status} - ${response.statusText}. Деталі: ${errorText.substring(0, 100)}...`);
                            return;
                        }

                        let result;
                        try {
                            result = await response.json();
                        } catch (jsonError) {
                            alert('Помилка обробки даних від сервера. Не вдалося розібрати відповідь.');
                            return;
                        }

                        if (result.success) {
                            alert(result.message || 'Книга додана до кошика!');
                            if (cartCountSpan) {
                                cartCountSpan.textContent = result.cart_total_items; // Оновлюємо лічильник з відповіді сервера
                            }
                            const cartLink = document.querySelector('.cart-link');
                            if (cartLink) {
                                cartLink.classList.add('bump');
                                setTimeout(() => {
                                    cartLink.classList.remove('bump');
                                }, 300);
                            }
                        } else if (result.error === 'login_required') {
                            alert('Будь ласка, увійдіть, щоб додати книгу до кошика.');
                            window.location.href = 'login.php';
                        } else {
                            alert('Помилка: ' + (result.message || result.error || 'Невідома помилка.'));
                        }
                    } catch (error) {
                        alert('Щось пішло не так під час виконання запиту до сервера...');
                    } finally {
                        setTimeout(() => {
                            if (document.body.contains(button)) { // Перевірка, чи кнопка все ще в DOM
                                button.disabled = false;
                                button.textContent = 'До кошика';
                            }
                        }, 500);
                    }
                });
            });
        document.addEventListener('DOMContentLoaded', function() {
            // Dynamic background
            document.body.classList.add('genre-<?= $mostFrequentGenre ?>');

            // --- Ordered Books Genre Chart ---
            const genreStats = <?= json_encode($genreStats); ?>;
            if (genreStats.length > 0) {
                const labels = genreStats.map(stat => stat.genre);
                const data = genreStats.map(stat => stat.total_quantity);

                const backgroundColors = [
                    'rgba(255, 99, 132, 0.7)', // Red
                    'rgba(54, 162, 235, 0.7)', // Blue
                    'rgba(255, 206, 86, 0.7)', // Yellow
                    'rgba(75, 192, 192, 0.7)', // Green
                    'rgba(153, 102, 255, 0.7)', // Purple
                    'rgba(255, 159, 64, 0.7)', // Orange
                    'rgba(199, 199, 199, 0.7)' // Grey
                ];
                const borderColors = [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)'
                ];

                const ctx = document.getElementById('genreChart').getContext('2d');
                new Chart(ctx, {
    type: 'pie',
    data: {
        labels: labels,
        datasets: [{
            label: 'Кількість замовлених книг',
            data: data,
            backgroundColor: backgroundColors.slice(0, labels.length),
            borderColor: borderColors.slice(0, labels.length),
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    color: '#333'
                }
            },
            title: {
                display: true,
                text: 'Розподіл замовлень по жанрах',
                color: '#333'
            },
            tooltip: { // <-- ДОДАЙТЕ АБО ЗМІНІТЬ ЦЕЙ БЛОК
                callbacks: {
                    label: function(context) {
                        // context.label - це мітка з labels (тобто жанр)
                        // context.parsed - це значення (тобто кількість)
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed !== null) {
                            label += context.parsed + ' книг';
                        }
                        return label;
                    }
                }
            }
        }
    }
});

            }

            // --- Favorite Books Genre Chart ---
            const favoriteGenreStats = <?= json_encode($favoriteGenreStatsForJs); ?>;
            const favoriteBooksPresent = <?= json_encode(!empty($favoriteBooks)); ?>;

            if (favoriteBooksPresent && favoriteGenreStats.length > 0) {
                const favLabels = favoriteGenreStats.map(stat => stat.genre);
                const favData = favoriteGenreStats.map(stat => stat.count);

                const favBackgroundColors = [
                    'rgba(255, 159, 64, 0.7)', // Orange
                    'rgba(75, 192, 192, 0.7)', // Green
                    'rgba(54, 162, 235, 0.7)', // Blue
                    'rgba(153, 102, 255, 0.7)', // Purple
                    'rgba(255, 99, 132, 0.7)', // Red
                    'rgba(201, 203, 207, 0.7)', // Grey
                    'rgba(255, 206, 86, 0.7)' // Yellow
                ];
                const favBorderColors = [
                    'rgba(255, 159, 64, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(201, 203, 207, 1)',
                    'rgba(255, 206, 86, 1)'
                ];

                const favCtx = document.getElementById('favoriteGenreChart').getContext('2d');
               new Chart(favCtx, {
    type: 'pie',
    data: {
        labels: favLabels,
        datasets: [{
            label: 'Кількість улюблених книг',
            data: favData,
            backgroundColor: favBackgroundColors.slice(0, favLabels.length),
            borderColor: favBorderColors.slice(0, favLabels.length),
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: {
                    color: '#333'
                }
            },
            title: {
                display: true,
                text: 'Розподіл улюблених книг по жанрах',
                color: '#333'
            },
            tooltip: { // <-- ДОДАЙТЕ АБО ЗМІНІТЬ ЦЕЙ БЛОК
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        if (label) {
                            label += ': ';
                        }
                        if (context.parsed !== null) {
                            label += context.parsed + ' книг';
                        }
                        return label;
                    }
                }
            }
        }
    }
});
            }

            // --- Handle removing from favorites ---
            document.querySelectorAll('.remove-favorite-btn').forEach(button => {
                button.addEventListener('click', async () => {
                    const bookId = button.getAttribute('data-id');
                    if (confirm('Ви впевнені, що хочете видалити цю книгу зі списку бажань?')) {
                        try {
                            // Ensure the path and action match add_to_favorites.php
                            const response = await fetch('add_to_favorites.php', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ book_id: bookId, action: 'remove_from_wishlist' })
                            });
                            const result = await response.json();
                            if (result.success) {
                                alert('Книга видалена зі списку бажань!');
                                button.closest('.book-item').remove();
                                const favoriteList = document.querySelector('.favorite-books-list');
                                if (favoriteList && favoriteList.children.length === 0) {
                                    const noDataMsg = document.createElement('p');
                                    noDataMsg.classList.add('no-data-message');
                                    noDataMsg.textContent = 'У вас ще немає улюблених книг.';
                                    favoriteList.parentNode.appendChild(noDataMsg);
                                }
                                // Reload to update charts and display "no books" message correctly
                                location.reload();
                            } else {
                                alert('Помилка: ' + (result.message || result.error));
                            }
                        } catch (error) {
                            console.error('Fetch error:', error);
                            alert('Щось пішло не так при видаленні з улюблених...');
                        }
                    }
                });
            });

            // Sidebar functionality (as in index.php)
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');
            if (sidebar && toggleBtn) {
                sidebar.classList.remove('hidden'); // Ensure visibility on load
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                });
                document.addEventListener('click', function(e){
                    if(!sidebar.contains(e.target) && !toggleBtn.contains(e.target)){
                        sidebar.classList.remove('show');
                    }
                });
                sidebar.addEventListener('click', function(e){
                    e.stopPropagation();
                });
            }
        });
    </script>
</body>
</html>