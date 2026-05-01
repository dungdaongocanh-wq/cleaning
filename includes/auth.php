<?php
declare(strict_types=1);

/**
 * Kiểm tra đã đăng nhập chưa
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Kiểm tra có phải admin không
 */
function isAdmin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Lấy thông tin user hiện tại từ session
 */
function currentUser(): array
{
    return [
        'id'        => $_SESSION['user_id']   ?? 0,
        'username'  => $_SESSION['username']  ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'user',
    ];
}

/**
 * Bắt buộc đăng nhập, nếu chưa → redirect login
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * Bắt buộc quyền admin, nếu không → redirect dashboard
 */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        redirect(BASE_URL . '/dashboard.php');
    }
}

/**
 * Redirect và dừng script
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
