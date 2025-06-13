<?php
// auth_utils.php

/**
 * Перевіряє, чи користувач залогінений.
 *
 * @return bool True, якщо користувач залогінений, false в іншому випадку.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Перевіряє, чи користувач є адміністратором.
 *
 * @return bool True, якщо користувач є адміністратором, false в іншому випадку.
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * Перевіряє, чи користувач має певну роль.
 *
 * @param string $role Роль для перевірки.
 * @return bool True, якщо користувач має вказану роль, false в іншому випадку.
 */
function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == $role;
}

/**
 * Перенаправляє користувача на вказану сторінку, якщо він не має прав.
 *
 * @param string $required_role Необхідна роль для доступу.
 * @param string $redirect_url URL для перенаправлення.
 */
function check_permission($required_role, $redirect_url = 'unauthorized.php') {
    if (!has_role($required_role)) {
        header("Location: " . $redirect_url);
        exit;
    }
}
?>