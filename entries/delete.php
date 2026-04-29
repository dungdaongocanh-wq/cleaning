<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM entry_headers WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $pdo->prepare("DELETE FROM entry_headers WHERE id=?")->execute([$id]);
        auditLog('delete','entry_headers',$id,$row,null,'Xóa phiếu ngày '.$row['entry_date']);
        setFlash('success','Đã xóa phiếu nhập.');
    }
}
redirect(BASE_URL . '/entries/index.php');