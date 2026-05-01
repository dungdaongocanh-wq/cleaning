<?php
declare(strict_types=1);

// ── Cấu hình ứng dụng ──────────────────────────────────────────
define('APP_NAME', 'Cleaning App');

// Tự động tính BASE_URL từ vị trí file config/app.php
// dirname(__DIR__) = thư mục gốc của app (cleaning_app)
$_appRoot  = str_replace('\\', '/', dirname(__DIR__));
$_docRoot  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
$_basePath = $_docRoot !== '' ? str_replace($_docRoot, '', $_appRoot) : '';
$_scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

define('BASE_URL', $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_basePath);

unset($_appRoot, $_docRoot, $_basePath, $_scheme);

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
