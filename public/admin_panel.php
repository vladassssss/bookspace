<?php
session_start();

require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Controllers/UserController.php';
require_once __DIR__ . '/../app/Controllers/BookstoreController.php';
require_once __DIR__ . '/../app/Services/UserService.php';
require_once __DIR__ . '/../app/Repositories/IUserRepository.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Models/Book.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/../app/Services/IBookstoreService.php';
require_once __DIR__ . '/../app/Services/BookstoreService.php';

require_once 'auth_utils.php';

use App\Database\Connection;
use App\Controllers\UserController;
use App\Services\UserService;
use App\Repositories\UserRepository;
use App\Controllers\BookstoreController; // Import the BookstoreController
use App\Controllers\OrderController;
use App\Services\OrderService;
use App\Repositories\OrderRepository;
use App\Models\Book; // Added for the new error.  Important!
use App\Repositories\BookstoreRepository;
use App\Services\BookstoreService;

if (!is_admin()) {
    header("Location: unauthorized.php");
    exit;
}

$db = Connection::getInstance()->getConnection();
$userRepository = new UserRepository($db);
$userService = new UserService($userRepository);
$userController = new UserController($userService);

$bookstoreRepository = new BookstoreRepository($db);
$bookstoreService = new BookstoreService($bookstoreRepository);
$bookController = new BookstoreController($bookstoreService); // Create an instance of BookstoreController

$stmt = $db->prepare("SELECT o.*, b.title AS book_title FROM orders o JOIN bookshop_book b ON o.book_id = b.id");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$users = $userController->getAllUsers();
$allBooks = $bookController->showBooksPage();
// Видалення книги
if (isset($_GET['delete_book_id'])) {
    $bookIdToDelete = intval($_GET['delete_book_id']);
    $bookController->deleteBook($bookIdToDelete);
    header("Location: ".$_SERVER['PHP_SELF']); // щоб уникнути повторного submit
    exit;
}

// Додавання книги
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $title = $_POST['title'] ?? '';
$author = $_POST['author'] ?? '';
$genre = $_POST['genre'] ?? '';
$price = floatval($_POST['price'] ?? 0);
$description = $_POST['description'] ?? '';
$language = $_POST['language'] ?? '';
$popularity = intval($_POST['popularity'] ?? 0);
$discount = isset($_POST['discount']) ? floatval($_POST['discount']) : null;

$coverImagePath = null;
if (isset($_FILES['cover_image'])) {
    error_log("Помилка завантаження файлу: " . $_FILES['cover_image']['error']);
    if ($_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $uploadsDir = __DIR__ . 'images/';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        $filename = basename($_FILES['cover_image']['name']);
        error_log("Згенероване ім'я файлу: " . $filename);
        $targetFile = $uploadsDir . $filename;
        if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetFile)) {
           $coverImagePath = $filename;
        } else {
            error_log("Не вдалося перемістити завантажений файл."); // Залогуйте невдалу спробу переміщення
        }
    }
} else {
    error_log("Файл обкладинки не було завантажено."); // Залогуйте, якщо файл взагалі не передано
}
error_log("POST запит на додавання книги...");
error_log("Назва: $title, Автор: $author, Ціна: $price");
error_log("Trying to add book: $title, $author, $genre, $price");

$bookController->addBook(



    $title,
    $author,
    $genre,
    $price,
    $coverImagePath,
    $description,
    $language,
    $popularity,
    $discount
);
error_log("Книга надіслана на додавання.");
header("Location: ".$_SERVER['PHP_SELF']);
exit;

}

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Адмін-панель</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .btn {
            padding: 5px 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .btn-edit {
            background-color: #008CBA;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Адмін-панель</h1>

    <h2>Список замовлень</h2>
    <table>
        <thead>
            <tr>
                <th>ID замовлення</th>
                <th>ID користувача</th>
                <th>Назва книги</th>
                <th>Кількість</th>
                <th>Дата замовлення</th>
                <th>Статус</th>
                <th>Телефон</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= $order['id']; ?></td>
                    <td><?= $order['user_id']; ?></td>
                    <td><?= $order['book_title']; ?></td>
                    <td><?= $order['quantity']; ?></td>
                    <td><?= $order['order_date']; ?></td>
                    <td><?= $order['status']; ?></td>
                    <td><?= $order['phone']; ?></td>
                    <td>
                        <a href="edit_order.php?id=<?= $order['id']; ?>" class="btn btn-edit">Редагувати</a>
                        <a href="delete_order.php?id=<?= $order['id']; ?>" class="btn btn-danger">Видалити</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Список користувачів</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Ім'я користувача</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Дії</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user->getId(); ?></td>
                    <td><?= $user->getUsername(); ?></td>
                    <td><?= $user->getEmail(); ?></td>
                    <td><?= $user->getRole(); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $user->getId(); ?>" class="btn btn-edit">Редагувати</a>
                        <a href="delete_user.php?id=<?= $user->getId(); ?>" class="btn btn-danger">Видалити</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (isset($bookController)): ?>
<h2>Список книг</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Назва</th>
            <th>Автор</th>
            <th>Ціна</th>
            <th>Дії</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $books = $bookController->showBooksPage();
    foreach ($books as $book): ?>
        <tr>
            <td><?= htmlspecialchars($book->getId()); ?></td>
            <td><?= htmlspecialchars($book->getTitle()); ?></td>
            <td><?= htmlspecialchars($book->getAuthor()); ?></td>
            <td><?= htmlspecialchars($book->getPrice()); ?></td>
            <td>
                <a href="edit_book.php?id=<?= $book->getId(); ?>" class="btn btn-edit">Редагувати</a>
                <a href="?delete_book_id=<?= $book->getId(); ?>" class="btn btn-danger" onclick="return confirm('Ви впевнені, що хочете видалити цю книгу?');">Видалити</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>


<h3>Додати нову книгу</h3>
<form method="post" action="" enctype="multipart/form-data">
    <input type="text" name="title" placeholder="Назва книги" required>
    <input type="text" name="author" placeholder="Автор" required>
    <input type="text" name="genre" placeholder="Жанр">
    <input type="number" step="0.01" name="price" placeholder="Ціна" required>
    <input type="file" name="cover_image" accept="image/*">
    <textarea name="description" placeholder="Опис книги"></textarea>
    <input type="text" name="language" placeholder="Мова">
    <input type="number" name="popularity" placeholder="Популярність">
    <input type="number" step="0.01" name="discount" placeholder="Знижка (%)">
    <button type="submit" name="add_book">Додати книгу</button>
</form>

<?php endif; ?>

<h2>Статистика</h2>
<p>Кількість зареєстрованих користувачів: <?= $userController->getUserCount(); ?></p>
<?php if (isset($bookController)): ?>
    <?php
    $books = $bookController->showBooksPage();
    ?>
    <p>Кількість книг: <?= count($books); ?></p>
<?php endif; ?>
</div>
</body>
</html>


