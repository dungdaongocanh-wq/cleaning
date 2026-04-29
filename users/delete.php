<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $current = currentUser();
    if ($id && $id !== $current['id']) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
            auditLog('delete','users',$id,$row,null,'Xóa user '.$row['username']);
            setFlash('success','Đã xóa người dùng "'.$row['full_name'].'"');
        }
    } else {
        setFlash('danger','Không thể xóa tài khoản đang đăng nhập.');
    }
}
redirect(BASE_URL . '/users/index.php');