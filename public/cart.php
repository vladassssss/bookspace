<?php

session_set_cookie_params([
    'path'     => '/',
    'httponly' => true
]);
session_start();

require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Models/CartItem.php';
require_once __DIR__ . '/../app/Repositories/ICartRepository.php';
require_once __DIR__ . '/../app/Repositories/CartRepository.php';
require_once __DIR__ . '/../app/Services/CartService.php';
require_once __DIR__ . '/../app/Controllers/CartController.php';
require_once __DIR__ . '/../app/Repositories/IBookstoreRepository.php';
require_once __DIR__ . '/../app/Repositories/BookstoreRepository.php';
require_once __DIR__ . '/auth_utils.php';

use App\Database\Connection;
use App\Repositories\ICartRepository;
use App\Repositories\CartRepository;
use App\Repositories\BookstoreRepository;
use App\Services\CartService;
use App\Controllers\CartController;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = Connection::getInstance()->getConnection();

$cartRepository = new CartRepository($db);
$bookstoreRepository = new BookstoreRepository($db); // Правильне розташування

$cartService = new CartService($cartRepository, $bookstoreRepository);
$cartController = new CartController($cartService);
$userId = $_SESSION['user_id'];

// Отримуємо дані кошика
$cartItems = $cartController->fetchUserCart($userId);

// Ініціалізація змінної $totalPrice перед початком циклу
$totalPrice = 0;

if (!empty($cartItems)) {
    foreach ($cartItems as $item) { // Змінна циклу називається $item
        // Отримуємо об'єкт Book (або масив даних книги) з CartItem
        // Рядок 51: Використовуйте $item, а не $cartItem
        $book = $item->getBookData(); // Змінено: $cartItem на $item

        // Перевіряємо, чи є дані про книгу і чи містять вони 'price' і 'discount'
        if (isset($book['price']) && isset($book['discount'])) { // Змінено: $bookData на $book
            $originalPrice = $book['price']; 
            $discount = $book['discount']; 
            
            $discountedPrice = $originalPrice * (1 - $discount / 100);
            $totalPrice += $discountedPrice * $item->getQuantity();
        } else {
            error_log("Помилка: Відсутні дані про ціну або знижку для книги в кошику (Book ID: " . $item->getBookId() . ")");
        }
    }
}


?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Кошик</title>
    <link rel="stylesheet" href="styles.css"> <style>
        /* Стилі для основного контенту кошика */
        .cart-container {
            max-width: 1200px;
            margin: 100px auto 40px; /* Відступ зверху для хедера */
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .cart-container h1 {
            font-size: 2.2em;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            color: #333;
        }
        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s ease;
            position: relative; /* Для позиціонування кнопки видалення */
        }
        .cart-item:hover {
            background-color: #f9f9f9;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-item img {
            width: 100px; /* Зменшив розмір зображення для кращого вигляду в кошику */
            height: 150px;
            object-fit: cover;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 20px;
            flex-shrink: 0; /* Запобігає зменшенню зображення */
        }
        .cart-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .cart-details p {
            margin: 3px 0;
            font-size: 0.95em;
            color: #555;
        }
        .cart-details .item-title {
            font-size: 1.2em;
            font-weight: 700;
            margin-bottom: 5px;
            color: #333;
        }
        .cart-details .item-price {
            font-weight: bold;
            color: #333;
        }
        .cart-details .item-price .original-price {
            text-decoration: line-through;
            color: #777;
            margin-right: 5px;
        }
        .cart-details .item-price .discounted-price {
            color: #dc3545; /* Червоний для ціни зі знижкою */
        }

        /* Кнопка видалення */
        .remove-from-cart {
            background-color: #dc3545; /* Червоний колір */
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease;
            margin-left: 20px; /* Відступ від деталей */
            flex-shrink: 0; /* Запобігає зменшенню кнопки */
        }
        .remove-from-cart:hover {
            background-color: #c82333;
        }

        /* Загальна сума та кнопка оформлення */
        .cart-summary {
            text-align: right;
            padding: 20px;
            font-size: 1.3em;
            font-weight: bold;
            border-top: 1px solid #eee;
            margin-top: 20px;
            color: #333;
        }
        .cart-summary p {
            margin-bottom: 15px;
        }
        .checkout-button {
            background-color: #28a745; /* Зелений колір */
            color: white;
            border: none;
            padding: 12px 25px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 1.1em;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }
        .checkout-button:hover {
            background-color: #218838;
        }
        .empty-cart {
            font-size: 1.2em;
            color: #777;
            text-align: center;
            padding: 50px 0;
        }

        /* Стилі для навігації (якщо styles.css не підключений або недостатній) */
      
        .quantity-control {
    display: flex;
    align-items: center;
    margin: 15px 0;
    background-color: #f8f8f8;
    border-radius: 8px;
    overflow: hidden; /* Обрізає все, що виходить за межі */
    border: 1px solid #ddd;
    width: fit-content; /* Підлаштовується під вміст */
}

.quantity-control button {
    background-color: #3498db; /* Акцентний синій */
    color: white;
    border: none;
    padding: 10px 15px;
    font-size: 1.2em;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.1s ease;
    min-width: 45px;
    text-align: center;
    outline: none; /* Прибираємо обведення при фокусі */
}

.quantity-control button:hover {
    background-color: #2980b9;
    transform: translateY(-1px);
}

.quantity-control button:active {
    transform: translateY(0);
}

.quantity-control input.item-quantity {
    width: 70px; /* Ширина поля вводу */
    padding: 10px 5px;
    border: none; /* Прибираємо межу */
    text-align: center;
    font-size: 1.2em;
    color: #333;
    font-weight: 500;
    background-color: #ffffff;
    -moz-appearance: textfield; /* Прибираємо стандартні стрілки для Firefox */
}

/* Прибираємо стандартні стрілки для Chrome/Safari/Edge */
.quantity-control input.item-quantity::-webkit-outer-spin-button,
.quantity-control input.item-quantity::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* --- Стилі для кнопки повного видалення --- */
.remove-item-full {
    background-color: #e74c3c; /* Виразний червоний */
    color: white;
    border: none;
    padding: 12px 20px;
    font-size: 1.1em;
    cursor: pointer;
    border-radius: 8px; /* М'якші кути */
    transition: background-color 0.3s ease, transform 0.2s ease;
    margin-left: auto; /* Відсуває кнопку вправо, якщо достатньо місця */
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.remove-item-full:hover {
    background-color: #c0392b;
    transform: translateY(-2px);
}

.remove-item-full:active {
    transform: translateY(0);
}

    </style>
</head>
<body>
    <header>
 
    <div class="container nav-container">
            <nav>
                <ul class="nav-left">
                   
                    
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
                            <span class="username-display"><?= htmlspecialchars($_SESSION['username']); ?></span>
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

   <div class="cart-container">
    <h1>Ваш кошик</h1>
    <?php if (empty($cartItems)): ?>
        <p class="empty-cart">Кошик порожній.</p>
    <?php else: ?>
        <?php foreach ($cartItems as $item): ?>
            <?php
                // Get the book data array from the CartItem object
                $bookData = $item->getBookData(); // This returns an array like ['price' => ..., 'discount' => ...]

                // Ensure price and discount exist in the bookData array before using them
                $discount = $bookData['discount'] ?? 0; // Use null coalescing to provide a default if missing
                $originalPrice = $bookData['price'] ?? 0.0;

                $discountedPrice = $originalPrice * (1 - $discount / 100);
                $itemCurrentQuantity = $item->getQuantity(); // Отримуємо поточну кількість книги в кошику
            ?>
            <div class="cart-item" data-book-id="<?= htmlspecialchars($item->getBookId()) ?>">
                <a href="book.php?id=<?= htmlspecialchars($item->getBookId()); ?>">
                    <img src="images/<?= htmlspecialchars($bookData['cover_image'] ?? 'default_book.jpg'); ?>" alt="<?= htmlspecialchars($bookData['title'] ?? 'Назва книги'); ?>">
                </a>
                <div class="cart-details">
                    <p class="item-title"><?= htmlspecialchars($bookData['title'] ?? 'Назва невідома') ?></p>
                    <p>Автор: <?= htmlspecialchars($bookData['author'] ?? 'Невідомий') ?></p>
                    <p class="item-price">
                        Ціна:
                        <?php if ($discount > 0): ?>
                            <span class="original-price"><?= htmlspecialchars(number_format($originalPrice, 2)) ?> ₴</span>
                            <span class="discounted-price"><?= htmlspecialchars(number_format($discountedPrice, 2)) ?> ₴</span>
                        <?php else: ?>
                            <?= htmlspecialchars(number_format($originalPrice, 2)) ?> ₴
                        <?php endif; ?>
                    </p>

                    <div class="quantity-control">
                        <button class="decrease-quantity" data-book-id="<?= htmlspecialchars($item->getBookId()) ?>">-</button>
                        <input type="number" class="item-quantity" value="<?= htmlspecialchars($itemCurrentQuantity) ?>" min="0" data-book-id="<?= htmlspecialchars($item->getBookId()) ?>">
                        <button class="increase-quantity" data-book-id="<?= htmlspecialchars($item->getBookId()) ?>">+</button>
                       <p class="item-quantity-text" data-book-id="<?= htmlspecialchars($item->getBookId()) ?>">Кількість у кошику: <span class="current-quantity-value"><?= htmlspecialchars($item->getQuantity()) ?></span></p>
                    </div>
                    <p>Сума: <span class="item-total-price">
                        <?= htmlspecialchars(number_format($discountedPrice * $itemCurrentQuantity, 2)) ?>
                    </span> ₴</p>
                    
                    <button class="remove-item-full" data-book-id="<?= htmlspecialchars($item->getBookId()) ?>">Видалити повністю</button>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="cart-summary">
            <p>Загальна вартість: <strong id="total-price-display"><?= htmlspecialchars(number_format($totalPrice, 2)) ?></strong> ₴</p>
            <a href="checkout.php" class="checkout-button">Оформити замовлення</a>
        </div>
    <?php endif; ?>
</div>
    </div>
    
     <script>
document.addEventListener('DOMContentLoaded', () => {
    const cartCountElement = document.getElementById('cart-count');
    const totalPriceDisplay = document.getElementById('total-price-display');
    const cartItemsContainer = document.querySelector('.cart-container');

    if (!cartItemsContainer) {
        return;
    }

    cartItemsContainer.addEventListener('click', async (event) => {
        const target = event.target;
        const bookId = target.closest('.cart-item')?.dataset.bookId;

        if (!bookId) return;

        let quantityInput = target.closest('.quantity-control')?.querySelector('.item-quantity');
        let quantityTextSpan = target.closest('.cart-item')?.querySelector('.item-quantity-text .current-quantity-value');
        
        let newQuantity = null;
        let url = '';
        let isFullRemoval = false;

        if (target.classList.contains('increase-quantity')) {
            newQuantity = parseInt(quantityInput.value) + 1;
            quantityInput.value = newQuantity;
            url = './update_cart_quantity.php';
        } else if (target.classList.contains('decrease-quantity')) {
            newQuantity = parseInt(quantityInput.value) - 1;
            if (newQuantity < 0) newQuantity = 0;
            quantityInput.value = newQuantity;
            url = './update_cart_quantity.php';
            if (newQuantity === 0) {
                isFullRemoval = true;
                url = './remove_from_cart.php';
            }
        } else if (target.classList.contains('remove-item-full')) {
            newQuantity = 0;
            isFullRemoval = true;
            url = './remove_from_cart.php';
        } else {
            return;
        }

        try {
            const bodyData = { book_id: bookId };
            if (!isFullRemoval) {
                bodyData.quantity = newQuantity;
            }

            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bodyData)
            });

            const responseText = await response.text(); 

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                console.error(`[JS ERROR] Failed to parse JSON. Error: ${jsonError.message}. Raw text: ${responseText}`);
                alert('Виникла помилка: недійсний формат відповіді сервера.');
                return; 
            }

            if (result && result.success) { 
                alert(`Успіх: ${result.message}`); 

                if (cartCountElement) {
                    cartCountElement.textContent = result.cart_count;
                    const cartLink = document.querySelector('.cart-link');
                    if (cartLink) {
                        cartLink.classList.add('bump');
                        setTimeout(() => cartLink.classList.remove('bump'), 300);
                    }
                }

                if (totalPriceDisplay) {
                    totalPriceDisplay.textContent = result.total_price.toFixed(2);
                }

                const cartItemElement = target.closest('.cart-item');
                if (isFullRemoval || newQuantity === 0) {
                    if (cartItemElement) {
                        cartItemElement.remove();
                    }
                    const remainingCartItems = document.querySelectorAll('.cart-item');
                    if (remainingCartItems.length === 0) {
                        cartItemsContainer.innerHTML = '<h1>Ваш кошик</h1><p class="empty-cart">Кошик порожній.</p>';
                    }
                } else {
                    if (cartItemElement && result.item_total_price_for_book !== undefined) {
                        const itemTotalPriceSpan = cartItemElement.querySelector('.item-total-price');
                        if (itemTotalPriceSpan) {
                            itemTotalPriceSpan.textContent = result.item_total_price_for_book.toFixed(2);
                        }
                        // ОНОВЛЕННЯ ТЕКСТОВОГО ВІДОБРАЖЕННЯ КІЛЬКОСТІ
                        if (quantityTextSpan) {
                            quantityTextSpan.textContent = newQuantity;
                        }
                    }
                }

            } else {
                // Обробка помилок
                alert(`Помилка: ${result ? (result.message || 'Невідома помилка від сервера.') : 'Невідома помилка або недійсний формат відповіді.'}`);
                // Відкат значення input, якщо запит не вдався
                if (quantityInput) {
                    quantityInput.value = quantityTextSpan.textContent; // Відновлюємо попереднє значення
                }
            }
        } catch (error) {
            console.error(`[JS CATCH ERROR] Fetch error: ${error.message}`); 
            alert('Виникла невідома помилка при обробці запиту.');
        }
    });

    // --- Обробка зміни кількості в полі input (вручну) ---
    cartItemsContainer.addEventListener('change', async (event) => {
        const target = event.target;
        if (target.classList.contains('item-quantity')) {
            const bookId = target.closest('.cart-item')?.dataset.bookId;
            if (!bookId) return;

            let newQuantity = parseInt(target.value);
            if (isNaN(newQuantity) || newQuantity < 0) {
                newQuantity = 0;
                target.value = 0;
            }

            let quantityTextSpan = target.closest('.cart-item')?.querySelector('.item-quantity-text .current-quantity-value');

            let url = './update_cart_quantity.php';
            let isFullRemoval = false;
            if (newQuantity === 0) {
                isFullRemoval = true;
                url = './remove_from_cart.php';
            }

            try {
                const bodyData = { book_id: bookId };
                if (!isFullRemoval) {
                    bodyData.quantity = newQuantity;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(bodyData)
                });
                
                const responseText = await response.text(); 

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error(`[JS ERROR - CHANGE] Failed to parse JSON. Error: ${jsonError.message}. Raw text: ${responseText}`);
                    alert('Виникла помилка: недійсний формат відповіді сервера.');
                    return;
                }

                if (result && result.success) {
                    alert(`Успіх (зміна вручну): ${result.message}`);

                    if (cartCountElement) {
                        cartCountElement.textContent = result.cart_count;
                        const cartLink = document.querySelector('.cart-link');
                        if (cartLink) {
                            cartLink.classList.add('bump');
                            setTimeout(() => cartLink.classList.remove('bump'), 300);
                        }
                    }
                    if (totalPriceDisplay) {
                        totalPriceDisplay.textContent = result.total_price.toFixed(2);
                    }

                    const cartItemElement = target.closest('.cart-item');
                    if (isFullRemoval || newQuantity === 0) {
                        if (cartItemElement) {
                            cartItemElement.remove();
                        }
                        const remainingCartItems = document.querySelectorAll('.cart-item');
                        if (remainingCartItems.length === 0) {
                            cartItemsContainer.innerHTML = '<h1>Ваш кошик</h1><p class="empty-cart">Кошик порожній.</p>';
                        }
                    } else {
                        if (cartItemElement && result.item_total_price_for_book !== undefined) {
                            const itemTotalPriceSpan = cartItemElement.querySelector('.item-total-price');
                            if (itemTotalPriceSpan) {
                                itemTotalPriceSpan.textContent = result.item_total_price_for_book.toFixed(2);
                            }
                            if (quantityTextSpan) {
                                quantityTextSpan.textContent = newQuantity;
                            }
                        }
                    }

                } else {
                    alert(`Помилка (зміна вручну): ${result ? (result.message || 'Невідома помилка від сервера.') : 'Невідома помилка або недійсний формат відповіді.'}`);
                    // Відкат значення input, якщо запит не вдався
                    target.value = quantityTextSpan.textContent; // Відновлюємо попереднє значення
                }
            } catch (error) {
                console.error(`[JS CATCH ERROR - CHANGE] Fetch error: ${error.message}`);
                alert('Виникла невідома помилка при обробці запиту.');
            }
        }
    });

});

    </script>
</body>
</html>