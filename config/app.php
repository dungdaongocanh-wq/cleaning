<?php
declare(strict_types=1);

// ── Cấu hình ứng dụng ──────────────────────────────────────────
define('APP_NAME', 'Cleaning App');

// BASE_URL: chỉnh lại nếu thư mục khác tên cleaning_app
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/\\'));

// ── Session ─────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
