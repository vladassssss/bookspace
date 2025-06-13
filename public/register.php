<?php
session_start();
require_once __DIR__ . '/../app/Database/Connection.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Repositories/IUserRepository.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Services/UserService.php';
require_once __DIR__ . '/../app/Controllers/UserController.php';

use App\Database\Connection;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Controllers\UserController;

$db = Connection::getInstance()->getConnection();

$userRepository = new UserRepository($db);
$userService = new UserService($userRepository);
$userController = new UserController($userService);

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $email = trim($_POST["email"]);

    try {
        $role = 'client';
        $userId = $userController->register($username, $password, $email, $role);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Реєстрація — Книгарня</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500&family=Roboto&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background: url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=1950&q=80') no-repeat center center fixed;
            background-size: cover;
            backdrop-filter: blur(6px);
        }

        .overlay {
            background-color: rgba(0, 0, 0, 0.6);
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 420px;
            margin: 80px auto;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 0 30px rgba(0,0,0,0.3);
            backdrop-filter: blur(14px);
            color: #fff;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            text-align: center;
            font-size: 32px;
            margin-bottom: 30px;
            color: #f5f5f5;
        }

        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 14px;
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input::placeholder {
            color: #ddd;
        }

        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.25);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #caa472, #5d473a);
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Playfair Display', serif;
        }

        button:hover {
            background: linear-gradient(135deg, #5d473a, #caa472);
            transform: scale(1.02);
            box-shadow: 0 0 15px rgba(202, 164, 114, 0.6);
        }

        .error {
            color: #ffdddd;
            background: rgba(255, 0, 0, 0.2);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        p {
            text-align: center;
            color: #ddd;
            margin-top: 20px;
        }

        a {
            color: #ffc98b;
            text-decoration: none;
            font-weight: bold;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="overlay"></div>
<div class="container">
    <h1>Реєстрація</h1>
    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form action="register.php" method="post">
        <input type="text" name="username" placeholder="Ім'я користувача" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <button type="submit">Зареєструватися</button>
    </form>
    <p>Вже маєте акаунт? <a href="login.php">Увійдіть</a></p>
</div>
</body>
</html>
