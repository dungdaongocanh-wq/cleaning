<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { redirect(BASE_URL . '/entries/index.php'); }

// Query header + customer
$stmt = $pdo->prepare("
    SELECT eh.*, c.name AS cust_name, c.address AS cust_address,
           c.tax_code AS cust_tax, c.contact_name, c.contact_phone,
           c.bank_name AS cust_bank, c.bank_account AS cust_account
    FROM entry_headers eh
    JOIN customers c ON c.id = eh.customer_id
    WHERE eh.id = ?
");
$stmt->execute([$id]);
$header = $stmt->fetch();
if (!$header) { redirect(BASE_URL . '/entries/index.php'); }

// Query lines
$stmt2 = $pdo->prepare("
    SELECT el.qty, el.unit_price_snapshot, el.line_total,
           cm.model_code, cm.model_name, cm.unit
    FROM entry_lines el
    JOIN customer_models cm ON cm.id = el.customer_model_id
    WHERE el.entry_header_id = ?
    ORDER BY cm.sort_order, cm.model_code
");
$stmt2->execute([$id]);
$lines      = $stmt2->fetchAll();
$grandTotal = array_sum(array_column($lines, 'line_total'));

$bbNumber = 'BBGH-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
$bbDate   = date('d/m/Y', strtotime($header['entry_date']));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Biên bản giao hàng <?= e($bbNumber) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #f0f0f0; font-family: 'Times New Roman', Times, serif; }
    .page {
      background: #fff;
      width: 210mm;
      min-height: 297mm;
      margin: 20px auto;
      padding: 20mm 20mm 15mm;
      box-shadow: 0 0 10px rgba(0,0,0,.15);
      position: relative;
    }
    .doc-title { font-size: 22px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .doc-meta   { font-size: 14px; color: #444; }
    .party-label { font-weight: bold; font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #333; padding-bottom: 4px; margin-bottom: 8px; }
    .party-info p { margin: 3px 0; font-size: 13px; }
    table.goods { width: 100%; border-collapse: collapse; font-size: 13px; }
    table.goods th, table.goods td { border: 1px solid #555; padding: 6px 8px; }
    table.goods thead th { background: #f5f5f5; text-align: center; font-weight: bold; }
    table.goods td.num   { text-align: center; }
    table.goods td.right { text-align: right; }
    .total-row td { font-weight: bold; background: #fafafa; }
    .sign-section { margin-top: 30px; }
    .sign-box { text-align: center; }
    .sign-box .sign-title { font-weight: bold; font-size: 13px; text-transform: uppercase; }
    .sign-box .sign-hint  { font-size: 11px; color: #888; font-style: italic; }
    .sign-space { height: 70px; }
    .footer-note { font-size: 11px; color: #888; text-align: center; margin-top: 20px; }
    .no-print-bar { background: #fff; padding: 10px 0; text-align: center; border-bottom: 1px solid #ddd; }

    @media print {
      .no-print { display: none !important; }
      body { background: #fff; margin: 0; }
      .page { margin: 0; box-shadow: none; padding: 15mm 20mm; width: auto; min-height: auto; }
    }
    @page { size: A4; margin: 0; }
  </style>
</head>
<body>

<!-- Toolbar (ẩn khi in) -->
<div class="no-print no-print-bar">
  <button class="btn btn-primary me-2" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>In biên bản
  </button>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Quay lại
  </a>
</div>

<div class="page">

  <!-- ── Tiêu đề ─────────────────────────────────────── -->
  <div class="text-center mb-3">
    <div class="doc-title">Biên bản giao hàng</div>
    <div class="doc-meta mt-1">
      Số: <strong><?= e($bbNumber) ?></strong>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      Ngày: <strong><?= e($bbDate) ?></strong>
    </div>
  </div>

  <!-- ── Bên giao / Bên nhận ─────────────────────────── -->
  <div class="row mb-3">
    <div class="col-6 pe-4">
      <div class="party-label">Bên giao</div>
      <div class="party-info">
        <p><strong><?= e(COMPANY_NAME) ?></strong></p>
        <p>Địa chỉ: <?= e(COMPANY_ADDRESS) ?></p>
        <p>MST: <?= e(COMPANY_TAX) ?></p>
        <p>Điện thoại: <?= e(COMPANY_PHONE) ?></p>
        <p>Ngân hàng: <?= e(COMPANY_BANK) ?></p>
        <p>Số TK: <?= e(COMPANY_ACCOUNT) ?></p>
      </div>
    </div>
    <div class="col-6 ps-4" style="border-left:1px solid #ccc">
      <div class="party-label">Bên nhận</div>
      <div class="party-info">
        <p><strong><?= e($header['cust_name']) ?></strong></p>
        <p>Địa chỉ: <?= e($header['cust_address'] ?? '') ?></p>
        <p>MST: <?= e($header['cust_tax'] ?? '') ?></p>
        <p>Liên hệ: <?= e($header['contact_name'] ?? '') ?>
          <?php if (!empty($header['contact_phone'])): ?> — <?= e($header['contact_phone']) ?><?php endif; ?></p>
        <p>Ngân hàng: <?= e($header['cust_bank'] ?? '') ?></p>
        <p>Số TK: <?= e($header['cust_account'] ?? '') ?></p>
      </div>
    </div>
  </div>

  <!-- ── Bảng hàng hóa ──────────────────────────────── -->
  <table class="goods mb-2">
    <thead>
      <tr>
        <th style="width:4%">#</th>
        <th style="width:12%">Mã hàng</th>
        <th>Tên hàng</th>
        <th style="width:7%">ĐVT</th>
        <th style="width:9%">Số lượng</th>
        <th style="width:13%">Đơn giá (đ)</th>
        <th style="width:14%">Thành tiền (đ)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $i => $line): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td><?= e($line['model_code']) ?></td>
        <td><?= e($line['model_name']) ?></td>
        <td class="num"><?= e($line['unit'] ?? '') ?></td>
        <td class="num"><?= formatNum((float)$line['qty']) ?></td>
        <td class="right"><?= formatVND((float)$line['unit_price_snapshot']) ?></td>
        <td class="right"><?= formatVND((float)$line['line_total']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td colspan="6" class="right">TỔNG CỘNG</td>
        <td class="right"><?= formatVND((float)$grandTotal) ?></td>
      </tr>
    </tbody>
  </table>

  <?php if (!empty($header['note'])): ?>
  <p class="mb-3" style="font-size:13px"><em>Ghi chú: <?= e($header['note']) ?></em></p>
  <?php endif; ?>

  <!-- ── Chữ ký ──────────────────────────────────────── -->
  <div class="sign-section row">
    <div class="col-6 sign-box">
      <div class="sign-title">Đại diện bên giao</div>
      <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
      <div class="sign-space"></div>
    </div>
    <div class="col-6 sign-box">
      <div class="sign-title">Đại diện bên nhận</div>
      <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
      <div class="sign-space"></div>
    </div>
  </div>

  <div class="footer-note">Biên bản được lập thành 02 bản, mỗi bên giữ 01 bản.</div>

</div><!-- /.page -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
