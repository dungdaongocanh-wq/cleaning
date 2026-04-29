<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(BASE_URL . '/entries/index.php'); }

$pdo        = getDB();
$user       = currentUser();
$id         = (int)($_POST['id'] ?? 0);
$customerId = (int)($_POST['customer_id'] ?? 0);
$entryDate  = trim($_POST['entry_date'] ?? '');
$note       = trim($_POST['note'] ?? '');
$modelIds   = $_POST['model_id']    ?? [];
$qtys       = $_POST['qty']         ?? [];
$unitPrices = $_POST['unit_price']  ?? [];

// Validate
if (!$customerId || !$entryDate) {
    setFlash('danger', 'Thiếu thông tin khách hàng hoặc ngày nhập.');
    redirect(BASE_URL . '/entries/form.php' . ($id ? "?id=$id" : ''));
}

$pdo->beginTransaction();
try {
    if ($id) {
        // Update header
        $before = $pdo->query("SELECT * FROM entry_headers WHERE id=$id")->fetch();
        $stmt = $pdo->prepare("
            UPDATE entry_headers SET customer_id=?,entry_date=?,note=?,updated_by=? WHERE id=?
        ");
        $stmt->execute([$customerId, $entryDate, $note, $user['id'], $id]);
        // Delete old lines
        $pdo->prepare("DELETE FROM entry_lines WHERE entry_header_id=?")->execute([$id]);
        auditLog('update','entry_headers',$id,$before,['customer_id'=>$customerId,'entry_date'=>$entryDate]);
    } else {
        // Insert header
        $stmt = $pdo->prepare("
            INSERT INTO entry_headers (customer_id,entry_date,note,created_by)
            VALUES (?,?,?,?)
        ");
        $stmt->execute([$customerId, $entryDate, $note, $user['id']]);
        $id = (int)$pdo->lastInsertId();
        auditLog('create','entry_headers',$id,null,['customer_id'=>$customerId,'entry_date'=>$entryDate]);
    }

    // Insert lines
    $lineStmt = $pdo->prepare("
        INSERT INTO entry_lines (entry_header_id,customer_model_id,qty,unit_price_snapshot,line_total)
        VALUES (?,?,?,?,?)
    ");
    foreach ($modelIds as $i => $modelId) {
        $modelId   = (int)$modelId;
        $qty       = (float)($qtys[$i] ?? 0);
        $unitPrice = (float)($unitPrices[$i] ?? 0);
        if ($modelId && $qty > 0) {
            $lineTotal = $qty * $unitPrice;
            $lineStmt->execute([$id, $modelId, $qty, $unitPrice, $lineTotal]);
        }
    }

    $pdo->commit();
    setFlash('success', 'Lưu phiếu thành công!');
    redirect(BASE_URL . '/entries/index.php');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('danger', 'Lỗi: ' . $e->getMessage());
    redirect(BASE_URL . '/entries/form.php' . ($id ? "?id=$id" : ''));
}