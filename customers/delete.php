<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$id]);
        auditLog('delete', 'customers', $id, $row, null, 'Xóa khách hàng '.$row['name']);
        setFlash('success', 'Đã xóa khách hàng "' . $row['name'] . '"');
    }
}
redirect(BASE_URL . '/customers/index.php');