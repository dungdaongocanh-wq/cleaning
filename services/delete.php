<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$id = (int)($_POST['id'] ?? 0);
$customerId = (int)($_POST['customer_id'] ?? 0);
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM customer_models WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if ($row) {
    $pdo->prepare("DELETE FROM customer_models WHERE id=?")->execute([$id]);
    auditLog('delete', 'customer_models', $id, $row, null, 'Xóa mã hàng '.$row['model_code']);
    setFlash('success', 'Đã xóa mã hàng "' . $row['model_code'] . '"');
}
redirect(BASE_URL . '/services/index.php?customer_id=' . $customerId);