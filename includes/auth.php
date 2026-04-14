<?php
session_start();

require_once __DIR__ . '/helpers.php';

/**
 * Check if a user is logged in.
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user']);
}

/**
 * Attempt to login a user.
 *
 * @param string $username
 * @param string $password
 * @return bool
 */
function login($username, $password) {
    $users = loadJson('users');
    foreach ($users as $user) {
        if ($user['username'] === $username && password_verify($password, $user['password'])) {
            unset($user['password']); // Don't store password in session
            $_SESSION['user'] = $user;
            return true;
        }
    }
    return false;
}

/**
 * Logout the current user.
 */
function logout() {
    unset($_SESSION['user']);
    session_destroy();
}

/**
 * Check if the logged-in user has a specific role.
 *
 * @param string|array $role
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn()) return false;
    $userRole = $_SESSION['user']['role'];
    if (is_array($role)) {
        return in_array($userRole, $role);
    }
    return $userRole === $role;
}

/**
 * Require the user to be logged in, otherwise redirect to login.
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php?action=login');
    }
}
