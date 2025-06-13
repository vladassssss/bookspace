<?php

// Приклад для простої самописної системи
$routes = [
    '/' => ['BookstoreController', 'index'],
    '/books' => ['BookstoreController', 'allBooks'],
    '/book/{id}' => ['BookstoreController', 'getBookById'],
    '/cart' => ['CartController', 'index'],
    '/cart/add' => ['CartController', 'add'],
    '/checkout' => ['CheckoutController', 'index'],
    '/checkout/process' => ['CheckoutController', 'process'],
    // ... інші роути ...
];

$requestUri = $_SERVER['REQUEST_URI'];
if (isset($routes[$requestUri])) {
    $controllerName = '\\App\\Controllers\\' . $routes[$requestUri][0];
    $methodName = $routes[$requestUri][1];
    $controller = new $controllerName();
    $controller->$methodName();
}