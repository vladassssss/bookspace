<?php
// C:\Users\Vlada\xampp\htdocs\bookshop\bookshop\public\book.php

require_once __DIR__ . '/../app/bootstrap.php';

use App\Database\Connection;
use App\Controllers\BookstoreController;
use App\Controllers\ReviewController;
use App\Controllers\OrderController;
use App\Repositories\BookstoreRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\UserRepository;
use App\Repositories\RatingRepository;
use App\Repositories\CartRepository;
use App\Services\BookstoreService;
use App\Services\OrderService;
use App\Services\ReviewService;
use App\Services\RatingService;


session_start();

try {
    $db = Connection::getInstance()->getConnection();
} catch (Exception $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die("Помилка підключення до бази даних: " . $e->getMessage());
}

// --- Ініціалізація РЕПОЗИТОРІЇВ ---
$bookstoreRepository = new BookstoreRepository($db);
$reviewRepository = new ReviewRepository($db);
$userRepository = new UserRepository($db);
$ratingRepository = new RatingRepository($db);
$cartRepository = new CartRepository($db);
$orderRepository = new OrderRepository($db);

// --- Ініціалізація СЕРВІСІВ ---
$ratingService = new RatingService($ratingRepository);
$bookstoreService = new BookstoreService($bookstoreRepository);
$reviewService = new ReviewService($reviewRepository, $userRepository);

$orderService = new OrderService(
    $orderRepository,
    $cartRepository,
    $userRepository,
    $bookstoreRepository
);

// --- Ініціалізація КОНТРОЛЕРІВ ---
$bookstoreController = new BookstoreController($bookstoreService, $ratingService);
$reviewController = new ReviewController($reviewService, $userRepository);
$orderController = new OrderController($orderService, $bookstoreService);

$bookId = $_GET['id'] ?? null;
if (!$bookId || !is_numeric($bookId)) {
    error_log("BOOK.PHP: Invalid or missing book ID: " . ($bookId ?? 'NULL'));
    die("Некоректний або відсутній ідентифікатор книги.");
}
$bookId = (int) $bookId;

// Отримуємо книгу та її кількість на складі
$book = $bookstoreController->getBookById($bookId);

if (!$book) {
    error_log("BOOK.PHP: Book not found for ID: " . $bookId);
    die("Книгу з ідентифікатором {$bookId} не знайдено.");
}

// <--- ЗМІНЕНО: Отримання кількості книг на складі ---
$quantityInStock = $book->getQuantity(); // Змінено на getQuantity()
$isAvailable = $quantityInStock > 0;
// --- КІНЕЦЬ ЗМІНЕНОГО БЛОКУ ---

$loggedInUsername = $_SESSION['username'] ?? null;
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

error_log("BOOK.PHP: Current user session - User ID: " . ($userId ?? 'N/A') . ", Username: " . ($loggedInUsername ?? 'N/A'));


// --- БЛОК ОБРОБКИ POST-ЗАПИТІВ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("BOOK.PHP: Received POST request. Action: " . ($_POST['action'] ?? 'N/A'));
    error_log("BOOK.PHP: Full POST data: " . print_r($_POST, true));

    // Обробка видалення відгуку
    if (isset($_POST['action']) && $_POST['action'] === 'delete_review' && isset($_POST['review_id']) && is_numeric($_POST['review_id']) && $loggedInUsername) {
        $reviewIdToDelete = (int) $_POST['review_id'];
        error_log("BOOK.PHP: Deleting review ID: {$reviewIdToDelete} by user: {$loggedInUsername}");
        if ($reviewController->deleteReview($reviewIdToDelete, $loggedInUsername)) {
            error_log("BOOK.PHP: Review ID {$reviewIdToDelete} deleted successfully. Redirecting.");
            header("Location: book.php?id={$bookId}");
            exit();
        } else {
            error_log("BOOK.PHP: Failed to delete review. Review ID: {$reviewIdToDelete}, User: {$loggedInUsername}");
            echo "<p class='error-message'>Неможливо видалити відгук. Перевірте свої права або чи існує відгук.</p>";
        }
    }

    // Обробка надсилання відредагованого відгуку
    elseif (isset($_POST['action']) && $_POST['action'] === 'edit_comment_submit' && isset($_POST['review_id']) && is_numeric($_POST['review_id']) && $loggedInUsername) {
        $reviewIdToUpdate = (int) $_POST['review_id'];
        $comment = trim($_POST['comment'] ?? "");
        $rating = (int)($_POST['rating'] ?? 0);

        error_log("BOOK.PHP: Editing comment: ID {$reviewIdToUpdate}, Comment: '{$comment}', Rating: {$rating}. By user: {$loggedInUsername}");

        if ($reviewController->updateReview($reviewIdToUpdate, $loggedInUsername, $rating, $comment)) {
            error_log("BOOK.PHP: Comment ID {$reviewIdToUpdate} updated successfully. Redirecting.");
            header("Location: book.php?id={$bookId}");
            exit();
        } else {
            error_log("BOOK.PHP: Failed to update comment: ID {$reviewIdToUpdate}, User: {$loggedInUsername}");
            echo "<p class='error-message'>Неможливо оновити коментар. Перевірте свої права або чи існує відгук.</p>";
        }
    }

    // Обробка додавання нового коментаря
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_comment') {
        $comment = trim($_POST['comment'] ?? "");
        error_log("BOOK.PHP: Handling 'add_comment' action. Received comment: '{$comment}'.");

        if ($userId) {
            error_log("BOOK.PHP: User ID detected for adding comment: {$userId}");

            $ratingForComment = (int)($_POST['rating_for_comment'] ?? 0);
            if ($ratingForComment < 1 || $ratingForComment > 5) {
                $ratingForComment = 1;
                error_log("BOOK.PHP: Invalid rating received for comment. Defaulting to 1.");
            }

            $reviewAdded = $reviewController->addReview(
                $bookId,
                $userId,
                $ratingForComment,
                $comment
            );

            error_log("BOOK.PHP: Review added status: " . ($reviewAdded ? 'true' : 'false'));

            if ($reviewAdded) {
                error_log("BOOK.PHP: Successfully added comment. Redirecting.");
                header("Location: book.php?id={$bookId}");
                exit();
            } else {
                error_log("BOOK.PHP: Failed to add comment.");
                echo "<p class='error-message'>Помилка при додаванні коментаря.</p>";
            }

        } else {
            error_log("BOOK.PHP: Attempt to add comment by unauthenticated user. Redirecting to login. Book ID: {$bookId}");
            header("Location: login.php");
            exit();
        }
    }
    // Обробка замовлення
    elseif (isset($_POST['action']) && $_POST['action'] === 'place_order') {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $userId = $_SESSION['user_id'] ?? null;
        $bookIdFromPost = $_POST['book_id'] ?? null;
        $priceAtPurchase = $_POST['price_at_purchase'] ?? null;
        $shippingAddress = $_POST['shipping_address'] ?? null;
        $paymentMethod   = $_POST['payment_method']   ?? null;


        error_log("BOOK.PHP: Handling 'place_order' action. Quantity: {$quantity}, User ID: {$userId}, Book ID (POST): {$bookIdFromPost}");

        if ($userId && $bookIdFromPost) {
            $bookIdInt = (int)$bookIdFromPost;
            $quantityInt = (int)$quantity;
            $priceAtPurchaseFloat = (float)$priceAtPurchase;

            // <--- ЗМІНЕНО: Перевірка наявності книги перед замовленням ---
            $currentBook = $bookstoreController->getBookById($bookIdInt);
            if (!$currentBook || $currentBook->getQuantity() < $quantityInt) { // Змінено на getQuantity()
                error_log("BOOK.PHP: Not enough stock for book ID {$bookIdInt}. Requested: {$quantityInt}, Available: " . ($currentBook ? $currentBook->getQuantity() : 'N/A')); // Змінено на getQuantity()
                echo "<p class='error-message'>На жаль, на складі недостатньо книг для вашого замовлення. Доступно: " . ($currentBook ? $currentBook->getQuantity() : 0) . "</p>"; // Змінено на getQuantity()
                exit();
            }
            // --- КІНЕЦЬ ЗМІНЕНОГО БЛОКУ ---

            $orderId = $orderController->placeSingleBookOrder($userId, $bookIdInt, $quantityInt, $shippingAddress, $paymentMethod);

            if ($orderId) {
                error_log("BOOK.PHP: Order placed successfully! Order ID: {$orderId}, User ID: {$userId}. Redirecting.");
                error_log("Attempting redirect to: /bookshop/bookshop/public/order_confirmation.php?order_id=" . $orderId);
                header("Location: /bookshop/bookshop/public/order_confirmation.php?order_id=" . $orderId);
                exit();
            } else {
                error_log("BOOK.PHP: Failed to place order for book ID: {$bookIdFromPost}, User ID: {$userId}. OrderId was null.");
                echo "<p class='error-message'>Не вдалося оформити замовлення.</p>";
            }
        } else {
            error_log("BOOK.PHP: Attempt to place order by unauthenticated user or missing book ID. User ID: " . ($userId ?? 'NULL') . ", Book ID: " . ($bookIdFromPost ?? 'NULL'));
            echo "<p class='error-message'>Вам необхідно увійти в систему, щоб зробити замовлення.</p>";
        }
    }
}
// --- КІНЕЦЬ БЛОКУ ОБРОБКИ POST-ЗАПИТІВ ---

// Обробка запиту на редагування КОМЕНТАРЯ (відображення форми)
$editReview = null;
if (isset($_GET['edit_comment']) && is_numeric($_GET['edit_comment'])) {
    $reviewIdToEdit = (int)$_GET['edit_comment'];
    error_log("BOOK.PHP: Request to edit comment ID: {$reviewIdToEdit}");

    if (isset($reviewRepository)) {
        $editReview = $reviewRepository->findReviewById($reviewIdToEdit);

        if ($editReview) {
            error_log("BOOK.PHP: Found review for editing. Review ID: " . $editReview->getId() . ", User ID: " . $editReview->getUsername() . ", Session User ID: " . ($_SESSION['user_id'] ?? 'N/A'));
            if (isset($_SESSION['user_id']) && $editReview->getUsername() !== $_SESSION['user_id']) {
                $editReview = null;
                error_log("BOOK.PHP: Attempted to edit review by wrong user. Resetting editReview to null.");
            }
        } else {
            error_log("BOOK.PHP: Review to edit not found for ID: {$reviewIdToEdit}");
        }
    }
}

// Отримання відгуків (коментарів) з даними користувача
$reviews = $reviewController->fetchReviewsWithUsers($book->getId());
error_log("BOOK.PHP: Fetched reviews for book ID {$book->getId()}. Total reviews: " . count($reviews));

// Середній рейтинг тепер розраховуємо на основі рейтингів КОМЕНТАРІВ
$averageRating = null;
if (!empty($reviews)) {
    $totalRating = 0;
    foreach ($reviews as $review) {
        $totalRating += $review->getRating();
        error_log("BOOK.PHP: Review ID: " . ($review->getId() ?? 'N/A') . ", Rating: " . ($review->getRating() ?? 'N/A') . ", Comment: " . ($review->getReviewText() ?? 'N/A'));
    }
    $averageRating = $totalRating / count($reviews);
    error_log("BOOK.PHP: Calculated average rating: " . number_format($averageRating, 2));
} else {
    error_log("BOOK.PHP: No reviews found for book ID {$book->getId()}. Average rating is null.");
}


// Функція для генерації зіркового рейтингу (для перевикористання)
function generateStarRatingHtml(string $inputName, int $currentRating = 0, string $prefix = ''): string
{
    $html = '<div class="star-rating">';
    for ($i = 5; $i >= 1; $i--) {
        $checked = ($currentRating == $i) ? 'checked' : '';
        $html .= "<input type=\"radio\" id=\"{$prefix}star{$i}\" name=\"{$inputName}\" value=\"{$i}\" {$checked} required />";
        $html .= "<label for=\"{$prefix}star{$i}\" title=\"{$i} зірок\">★</label>";
    }
    $html .= '</div>';
    return $html;
}

// Функція для відображення зірок (не інтерактивних)
function displayStars(int $rating): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="star ' . ($i <= $rating ? 'filled' : '') . '">★</span>';
    }
    return $html;
}

// --- РЕНДЕРИНГ HTML ---
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($book->getTitle()) ?> – Опис та відгуки</title>
    <link rel="stylesheet" href="style2.css">
     <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        
        body {
    font-family: 'Roboto', sans-serif; /* Встановлюємо Roboto як основний шрифт */
    line-height: 1.6; /* Забезпечує хорошу читабельність між рядками */
    color: #333; /* Задаємо стандартний колір тексту */
    font-size: 16px; /* Базовий розмір шрифту */
}
        </style>
        

</head>

<body>
    <div class="book-detail">
        <div class="main-content">
            <div class="book-header">
                <img src="images/<?= htmlspecialchars($book->getCoverImage()) ?>" alt="<?= htmlspecialchars($book->getTitle()) ?>">
                <div class="book-info">
                    <h1><?= htmlspecialchars($book->getTitle()) ?></h1>
                    <p><strong>Автор:</strong> <?= htmlspecialchars($book->getAuthor()) ?></p>
                    <p><strong>Жанр:</strong> <?php if ($book->getGenre()): ?>
                        <a href="allbooks.php?genre=<?= urlencode($book->getGenre()) ?>"><?= htmlspecialchars($book->getGenre()) ?></a>
                    <?php else: ?>
                        Не вказано
                    <?php endif; ?></p>
                    <p class="price-section">
                        <strong>Ціна:</strong>
                        <?php if ($book->getDiscount() > 0): ?>
                            <span class="original-price"><?= htmlspecialchars($book->getPrice()) ?> ₴</span>
                            <?php
                            $discountedPrice = $book->getPrice() * (1 - $book->getDiscount() / 100);
                            ?>
                            <span class="discounted-price"><?= htmlspecialchars(round($discountedPrice, 2)) ?> ₴</span>
                        <?php else: ?>
                            <?= htmlspecialchars($book->getPrice()) ?> ₴
                        <?php endif; ?>
                    </p>
                    <p><strong>Опис:</strong><br><?= nl2br(htmlspecialchars($book->getDescription())); ?></p>

                    <?php if ($averageRating !== null && $averageRating > 0): ?>
                        <p class="average-rating-display">
                            <strong>Середня оцінка (на основі коментарів):</strong>
                            <span class="star-display">
                                <?= displayStars(round($averageRating)) ?>
                                (<?= number_format($averageRating, 1); ?> з 5)
                            </span>
                        </p>
                    <?php endif; ?>

                    <p>
                        <strong>Кількість на складі:</strong>
                        <span id="stock_quantity" class="<?= $isAvailable ? 'in-stock' : 'out-of-stock' ?>">
                            <?= htmlspecialchars($quantityInStock) ?>
                        </span>
                        (<?= $isAvailable ? 'В наявності' : 'Немає в наявності' ?>)
                    </p>
                </div>
            </div>

            <div class="reviews">
                <h2>Коментарі</h2>
                <?php if (empty($reviews)): ?>
                    <p>Коментарі відсутні. Будьте першим, хто залишить коментар!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review">
                            <div class="review-header">
                                <span class="review-author">
                                    <?= htmlspecialchars($review->getUsername() ?? 'Гість') ?>
                                    <span class="star-display">
                                        <?= displayStars($review->getRating()) ?>
                                    </span>
                                </span>
                            </div>
                            <div class="review-content">
                                <div class="review-comment"><?= nl2br(htmlspecialchars($review->getReviewText())) ?></div>
                                <div class="review-date"><?= htmlspecialchars($review->getCreatedAt()) ?></div>
                            </div>
                            <div class="review-actions">
                                <?php if (isset($loggedInUsername) && ($review->getUsername() ?? 'Гість') === $loggedInUsername): ?>
                                    
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_review">
                                        <input type="hidden" name="review_id" value="<?= $review->getId() ?>">
                                        <button type="submit" onclick="return confirm('Ви впевнені, що хочете видалити цей коментар?')">Видалити</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (isset($editReview) && $editReview): ?>
                <div class="review-form edit-form-container">
                    <h3>Редагувати коментар</h3>
                    <form action="book.php?id=<?= $bookId ?>" method="POST">
                        <input type="hidden" name="action" value="edit_comment_submit">
                        <input type="hidden" name="review_id" value="<?= $editReview->getId() ?>">
                        <div class="order-form-group">
                            <label for="comment_edit">Коментар:</label>
                            <textarea name="comment" id="comment_edit" required><?= htmlspecialchars($editReview->getReviewText()) ?></textarea>
                        </div>
                        <div class="order-form-group">
                            <label for="edit_star5">Оцінка:</label>
                            <?= generateStarRatingHtml('rating', $editReview->getRating(), 'edit_') ?>
                        </div>
                        <button type="submit">Зберегти зміни</button>
                    </form>
                </div>
            <?php elseif ($userId): ?>
                <div class="review-form">
                    <h3>Додати коментар</h3>
                    <form action="book.php?id=<?= $bookId ?>" method="post">
                        <input type="hidden" name="action" value="add_comment">
                        <div class="order-form-group">
                            <label for="comment_add">Ваш коментар:</label>
                            <textarea name="comment" id="comment_add" placeholder="Напишіть свій коментар..." required></textarea>
                        </div>
                        <div class="order-form-group">
                            <label for="comment_star5">Оцінка:</label>
                            <?= generateStarRatingHtml('rating_for_comment', 5, 'comment_') ?>
                        </div>
                        <button type="submit">Надіслати коментар</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="login-message">
                    <p>Щоб залишити коментар, будь ласка, <a href="login.php">увійдіть</a> або <a href="register.php">зареєструйтеся</a>.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($loggedInUsername): ?>
            <div class="order-section">
                <h3>
                    Оформити замовлення
                    <button type="button" class="toggle-button" id="toggleOrderSection">▼</button>
                </h3>
                <div class="order-section-content" id="orderSectionContent">
                    <form id="orderForm">
                        <input type="hidden" id="book_id_order_form" value="<?= htmlspecialchars($book->getId()) ?>">
                        <input type="hidden" id="book_price" value="<?= htmlspecialchars($book->getPrice() * (1 - $book->getDiscount() / 100)) ?>">
                        <input type="hidden" id="max_quantity_in_stock" value="<?= htmlspecialchars($quantityInStock) ?>"> <div class="order-form-group">
                            <label for="quantity">Кількість:</label>
                            <input type="number" id="quantity" name="quantity" min="1" value="1" required oninput="updateTotal()"
                            <?php if (!$isAvailable): ?> disabled <?php endif; ?> >
                        </div>

                     
                        <div class="order-form-group">
                            <label for="phone_input">Номер телефону:</label>
                            <input type="tel" id="phone_input" name="phone" placeholder="+380XXXXXXXXX" pattern="\+380[0-9]{9}" title="Формат: +380XXXXXXXXX" required
                            <?php if (!$isAvailable): ?> disabled <?php endif; ?> >
                        </div>

                        <p class="order-total">Загальна сума: <span id="total_price">0.00</span> ₴</p>

                        <?php if ($isAvailable): ?>
                            <button type="submit">Замовити</button>
                        <?php else: ?>
                            <button type="button" disabled>Немає в наявності</button>
                        <?php endif; ?>

                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
       document.addEventListener('DOMContentLoaded', function() {
    updateTotal();

    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
        orderForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const bookId = document.getElementById('book_id_order_form').value;
            const quantityInput = document.getElementById('quantity');
            const quantity = parseInt(quantityInput.value);
            const maxQuantityInStock = parseInt(document.getElementById('max_quantity_in_stock').value);
            const phone = document.getElementById('phone_input').value;
            // const shippingAddress = document.getElementById('shipping_address_input').value; // <--- ЗАКОМЕНТОВАНО
            // const paymentMethod = document.getElementById('payment_method_input').value;   // <--- ЗАКОМЕНТОВАНО
            const bookPrice = parseFloat(document.getElementById('book_price').value);

            if (!quantity || quantity < 1) {
                alert('Будь ласка, вкажіть дійсну кількість (мінімум 1).');
                return;
            }
            if (quantity > maxQuantityInStock) {
                alert(`Ви не можете замовити більше ${maxQuantityInStock} книг, доступних на складі.`);
                quantityInput.value = maxQuantityInStock;
                updateTotal();
                return;
            }

            if (!phone || !phone.match(/^\+380[0-9]{9}$/)) {
                alert('Будь ласка, введіть дійсний номер телефону у форматі +380XXXXXXXXX.');
                return;
            }
            

            const orderData = {
                action: 'place_order',
                book_id: parseInt(bookId),
                quantity: quantity,
                phone: phone,
                // shipping_address: shippingAddress, // <--- ЗАКОМЕНТОВАНО
                // payment_method: paymentMethod,   // <--- ЗАКОМЕНТОВАНО
                price_at_purchase: bookPrice
            };

            // Якщо вам потрібні значення за замовчуванням для цих полів на бекенді,
            // ви можете передавати їх тут. Наприклад:
            orderData.shipping_address = 'Не вказано'; // Або пустий рядок, або інше значення
            orderData.payment_method = 'Cash_on_delivery'; // Або інший спосіб оплати за замовчуванням

            try {
                const response = await fetch('http://localhost/bookshop/bookshop/public/book.php?id=<?= $bookId ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams(orderData).toString()
                });

                if (response.redirected) {
                    window.location.href = response.url;
                } else {
                    const result = await response.text();
                    if (result) {
                        try {
                            const jsonResult = JSON.parse(result);
                            alert('Помилка: ' + jsonResult.message);
                        } catch (e) {
                            alert('Помилка: ' + result);
                        }
                    } else {
                        alert('Невідома помилка при обробці замовлення.');
                    }
                }
            } catch (error) {
                console.error('Помилка AJAX запиту:', error);
                alert('Помилка зв\'язку з сервером. Спробуйте ще раз.');
            }
        });
    }

    const toggleButton = document.getElementById('toggleOrderSection');
    const orderSectionContent = document.getElementById('orderSectionContent');
    if (toggleButton && orderSectionContent) {
        toggleButton.addEventListener('click', function() {
            orderSectionContent.classList.toggle('hidden');
            if (orderSectionContent.classList.contains('hidden')) {
                toggleButton.textContent = '▼';
            } else {
                toggleButton.textContent = '▲';
            }
        });
    }

    const starRatingContainers = document.querySelectorAll('.star-rating');

    starRatingContainers.forEach(container => {
        const radios = container.querySelectorAll('input[type="radio"]');
        const labels = container.querySelectorAll('label');

        function updateStars(checkedValue) {
            labels.forEach(label => {
                const starValue = parseInt(label.getAttribute('for').replace(/[^0-9]/g, ''));
                if (starValue <= checkedValue) {
                    label.style.color = '#ffc107';
                    label.style.textShadow = '0 0 8px rgba(255, 193, 7, 0.7)';
                } else {
                    label.style.color = '#ccc';
                    label.style.textShadow = 'none';
                }
            });
        }

        let initialChecked = container.querySelector('input[type="radio"]:checked');
        if (initialChecked) {
            updateStars(parseInt(initialChecked.value));
        }

        labels.forEach(label => {
            label.addEventListener('mouseover', () => {
                const hoverValue = parseInt(label.getAttribute('for').replace(/[^0-9]/g, ''));
                labels.forEach(l => {
                    const val = parseInt(l.getAttribute('for').replace(/[^0-9]/g, ''));
                    if (val <= hoverValue) {
                        l.style.color = '#ffc107';
                        l.style.textShadow = '0 0 8px rgba(255, 193, 7, 0.7)';
                    } else {
                        l.style.color = '#ccc';
                        l.style.textShadow = 'none';
                    }
                });
            });

            label.addEventListener('mouseout', () => {
                let checkedRadio = container.querySelector('input[type="radio"]:checked');
                if (checkedRadio) {
                    updateStars(parseInt(checkedRadio.value));
                } else {
                    labels.forEach(l => {
                        l.style.color = '#ccc';
                        l.style.textShadow = 'none';
                    });
                }
            });

            label.addEventListener('click', () => {
                const clickedValue = parseInt(label.getAttribute('for').replace(/[^0-9]/g, ''));
                const radioToCheck = container.querySelector(`input[value="${clickedValue}"]`);
                if (radioToCheck) {
                    radioToCheck.checked = true;
                    updateStars(clickedValue);
                }
            });
        });
    });
});

function updateTotal() {
    const quantityInput = document.getElementById('quantity');
    const bookPriceInput = document.getElementById('book_price');
    const totalPriceSpan = document.getElementById('total_price');
    const maxQuantityInStock = parseInt(document.getElementById('max_quantity_in_stock').value);

    let quantity = parseInt(quantityInput.value);
    const bookPrice = parseFloat(bookPriceInput.value);

    if (quantity > maxQuantityInStock) {
        quantity = maxQuantityInStock;
        quantityInput.value = maxQuantityInStock;
    }
    if (quantity < 1) {
        quantity = 1;
        quantityInput.value = 1;
    }

    if (!isNaN(quantity) && quantity >= 1 && !isNaN(bookPrice)) {
        const total = quantity * bookPrice;
        totalPriceSpan.textContent = total.toFixed(2);
    } else {
        totalPriceSpan.textContent = '0.00';
    }
}
    </script>
</body>
</html>