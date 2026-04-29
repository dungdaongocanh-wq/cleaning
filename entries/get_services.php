<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

$customerId = (int)($_GET['customer_id'] ?? 0);
$date       = $_GET['date'] ?? date('Y-m-d');

if (!$customerId) { echo json_encode([]); exit; }

$pdo  = getDB();
$stmt = $pdo->prepare("
    SELECT cm.id, cm.model_code, cm.model_name, cm.unit
    FROM customer_models cm
    WHERE cm.customer_id = ? AND cm.is_active = 1
    ORDER BY cm.sort_order, cm.model_code
");
$stmt->execute([$customerId]);
$models = $stmt->fetchAll();

$result = [];
foreach ($models as $m) {
    $price = getPriceAtDate((int)$m['id'], $date);
    $result[] = [
        'id'         => $m['id'],
        'model_code' => $m['model_code'],
        'model_name' => $m['model_name'],
        'unit'       => $m['unit'],
        'unit_price' => $price,
    ];
}
echo json_encode($result);