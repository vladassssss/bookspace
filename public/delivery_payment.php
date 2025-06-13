<?php

session_set_cookie_params([
    'path'     => '/',
    'httponly' => true
]);
session_start();

// --- Підключення файлів (якщо потрібно для заголовків/підвалу) ---
// Моделі (можливо, не всі потрібні, якщо лише виводиться повідомлення)
require_once __DIR__ . '/../app/Models/User.php'; // Якщо потрібно для заголовка

// Утиліти
require_once __DIR__ . '/auth_utils.php';

// --- Змінні для повідомлення ---
$orderId = $_GET['order_id'] ?? null; // Отримуємо ID замовлення з URL, якщо передано
$message = "Ваше замовлення успішно оформлено!";
if ($orderId) {
    $message .= " Номер вашого замовлення: #" . htmlspecialchars($orderId);
}

// Якщо повідомлення про успіх вже є в сесії (наприклад, з попередньої сторінки)
if (isset($_SESSION['message'])) {
    $message = htmlspecialchars($_SESSION['message']);
    unset($_SESSION['message']); // Видаляємо з сесії після відображення
}

?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Замовлення оформлено!</title>
    <link rel="stylesheet" href="style5.css">
</head>
<body>
    <header class="main-header">
        <div class="header-content">
            <a href="index.php" class="logo">Bookstore</a>
            <nav class="main-nav">
                <ul>
                    <li><a href="index.php">Головна</a></li>
                    <li><a href="cart.php">Кошик</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php">Профіль</a></li>
                        <li><a href="logout.php">Вийти</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Увійти</a></li>
                        <li><a href="register.php">Реєстрація</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container order-confirmation-page">
        <div class="card confirmation-box">
            <h1>Дякуємо за ваше замовлення!</h1>
            <p class="confirmation-message"><?php echo $message; ?></p>
            <p>Ми цінуємо вашу довіру. Ви можете переглянути деталі свого замовлення у розділі "Мої замовлення" в профілі.</p>
            <div class="confirmation-actions">
                <a href="index.php" class="button primary-button">Повернутися на головну</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php?tab=orders" class="button secondary-button">Мої замовлення</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Bookstore. Усі права захищені.</p>
            <nav class="footer-nav">
                <ul>
                    <li><a href="#">Політика конфіденційності</a></li>
                    <li><a href="#">Умови використання</a></li>
                </ul>
            </nav>
        </div>
    </footer>
</body>
</html>