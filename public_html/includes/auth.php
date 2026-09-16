<?php
require_once __DIR__ . '/db.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $params = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => defined('FORCE_HTTPS_COOKIES') && FORCE_HTTPS_COOKIES,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    session_set_cookie_params($params);
    session_start();
}

function current_user(): ?array
{
    start_secure_session();
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function require_platform_admin(): array
{
    $user = require_login();
    if (empty($user['is_platform_admin'])) {
        http_response_code(403);
        die('Access denied.');
    }
    return $user;
}

function login_user(array $userRow): void
{
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$userRow['id'],
        'company_id' => (int)$userRow['company_id'],
        'full_name' => $userRow['full_name'],
        'email' => $userRow['email'],
        'role' => $userRow['role'],
        'is_platform_admin' => (int)$userRow['is_platform_admin'],
    ];
}

function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void
{
    start_secure_session();
    $token = $_POST['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}
