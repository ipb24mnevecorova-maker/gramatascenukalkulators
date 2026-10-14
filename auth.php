<?php
// Only the admin has a login. Workers use the calculator and the saved tāmes without logging in.
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function admin_login_ok(string $user, string $pass): bool
{
    // Always check the hash, so the response time doesn't reveal whether the username was right.
    return password_verify($pass, ADMIN_HASH) && hash_equals(ADMIN_USER, $user);
}

function require_admin(): void
{
    if (empty($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}