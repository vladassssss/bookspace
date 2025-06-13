<?php
// app/config.php

// Конфігураційні налаштування бази даних
define('DB_HOST', 'localhost');
define('DB_NAME', 'social_metwork_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Автозавантаження класів за стандартом PSR-4
spl_autoload_register(function ($class) {
    // Простір імен має починатися з "App\"
    $prefix = 'App\\';
    // Базова тека для ваших класів
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Не наш клас
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
