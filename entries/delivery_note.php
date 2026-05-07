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
    SELECT el.qty, cm.model_code, cm.model_name, cm.unit
    FROM entry_lines el
    JOIN customer_models cm ON cm.id = el.customer_model_id
    WHERE el.entry_header_id = ?
    ORDER BY cm.sort_order, cm.model_code
");
$stmt2->execute([$id]);
$lines = $stmt2->fetchAll();

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
      padding: 15mm 18mm 15mm;
      box-shadow: 0 0 12px rgba(0,0,0,.18);
      position: relative;
    }

    /* Logo góc phải */
    .page-logo {
      position: absolute;
      top: 12mm;
      right: 18mm;
    }
    .page-logo img {
      height: 60px;
      max-width: 160px;
      object-fit: contain;
    }

    /* Tiêu đề */
    .doc-title   { font-size: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .doc-meta    { font-size: 13px; color: #444; }

    /* Bên giao / bên nhận */
    .party-label { font-weight: bold; font-size: 12px; text-transform: uppercase;
                   border-bottom: 1px solid #333; padding-bottom: 3px; margin-bottom: 6px; }
    .party-info p { margin: 2px 0; font-size: 12px; }

    /* Bảng hàng hóa */
    table.goods { width: 100%; border-collapse: collapse; font-size: 12px; }
    table.goods th, table.goods td {
      border: 1px solid #555; padding: 5px 6px; vertical-align: middle;
    }
    table.goods thead th {
      background: #f0f0f0; text-align: center; font-weight: bold; line-height: 1.4;
    }
    table.goods td.num   { text-align: center; }
    table.goods td.right { text-align: right; }
    .total-row td { font-weight: bold; background: #fafafa; text-align: center; }

    /* Input & textarea trong bảng */
    table.goods input.cell-input,
    table.goods textarea.cell-input {
      border: none; outline: none; background: transparent;
      width: 100%; text-align: left; font-size: 12px;
      font-family: 'Times New Roman', Times, serif;
      resize: vertical;
    }
    table.goods input.cell-input { text-align: center; }
    table.goods input.cell-input:focus,
    table.goods textarea.cell-input:focus { background: #fffde7; }

    /* Tổng pallet */
    #totalPallet { font-weight: bold; }

    /* Phần ký */
    .sign-section { margin-top: 28px; }
    .sign-box { text-align: center; padding: 0 5px; }
    .sign-box .sign-title { font-weight: bold; font-size: 12px; text-transform: uppercase; }
    .sign-box .sign-hint  { font-size: 11px; color: #888; font-style: italic; margin-bottom: 4px; }
    .sign-space { height: 65px; border-bottom: 1px dotted #aaa; margin: 0 10px; }
    .sign-name  { margin-top: 4px; font-size: 12px; height: 18px; }

    .footer-note { font-size: 11px; color: #888; text-align: center; margin-top: 18px; }

    /* Toolbar */
    .toolbar { background: #fff; padding: 10px 0; text-align: center;
               border-bottom: 1px solid #ddd; position: sticky; top: 0; z-index: 100; }

    /* In ấn */
    @media print {
      .no-print  { display: none !important; }
      body        { background: #fff; margin: 0; }
      .page       { margin: 0; box-shadow: none; padding: 10mm 15mm; width: auto; min-height: auto; }
      table.goods input.cell-input  { border: none !important; background: transparent !important; }
      table.goods textarea.cell-input { border: none !important; background: transparent !important; resize: none; }
    }
    @page { size: A4; margin: 0; }
  </style>
</head>
<body>

<!-- ── Toolbar ─────────────────────────────────────────── -->
<div class="toolbar no-print">
  <button class="btn btn-primary me-2" onclick="window.print()">
    <i class="bi bi-printer me-1"></i>In biên bản
  </button>
  <a href="index.php" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Quay lại danh sách
  </a>
  <span class="ms-4 text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Điền vào các ô <span style="background:#fffde7;padding:1px 4px;border-radius:3px">số pallet, ghi chú</span> trước khi in.
  </span>
</div>

<div class="page">

  <!-- ── Logo góc phải ──────────────────────────────────── -->
  <div class="page-logo">
    <img src="<?= BASE_URL ?>/assets/images/logo.png"
         alt="Logo"
         onerror="this.style.display='none'">
  </div>

  <!-- ── Tiêu đề ─────────────────────────────────────────── -->
  <div class="text-center mb-3">
    <div class="doc-title">Biên bản giao hàng</div>
    <div class="doc-meta mt-1">
      Số: <strong><?= e($bbNumber) ?></strong>
      &nbsp;&nbsp;|&nbsp;&nbsp;
      Ngày: <strong><?= e($bbDate) ?></strong>
    </div>
  </div>

  <!-- ── Bên giao / Bên nhận ─────────────────────────────── -->
  <div class="row mb-3">
    <div class="col-6 pe-4">
      <div class="party-label">Bên giao</div>
      <div class="party-info">
        <p><strong><?= e(COMPANY_NAME) ?></strong></p>
        <p>Địa chỉ: <?= e(COMPANY_ADDRESS) ?></p>
        <p>MST: <?= e(COMPANY_TAX) ?>&nbsp;&nbsp;|&nbsp;&nbsp;ĐT: <?= e(COMPANY_PHONE) ?></p>
        <p>NH: <?= e(COMPANY_BANK) ?></p>
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
          <?php if (!empty($header['contact_phone'])): ?>&nbsp;—&nbsp;<?= e($header['contact_phone']) ?><?php endif; ?>
        </p>
        <p>NH: <?= e($header['cust_bank'] ?? '') ?>&nbsp;&nbsp;|&nbsp;&nbsp;Số TK: <?= e($header['cust_account'] ?? '') ?></p>
      </div>
    </div>
  </div>

  <!-- ── Bảng hàng hóa ───────────────────────────────────── -->
  <table class="goods mb-2">
    <thead>
      <tr>
        <th rowspan="2" style="width:4%">STT<br>(No)</th>
        <th rowspan="2" style="width:16%">Loại hàng<br>(Item)</th>
        <th rowspan="2">Mã hàng</th>
        <th rowspan="2" style="width:10%">Số lượng<br>(EA)</th>
        <th rowspan="2" style="width:10%">Số Giá</th>
        <th rowspan="2" style="width:11%">Số pallet/<br>box</th>
        <th rowspan="2" style="width:30%">Ghi chú</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $i => $line): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td class="num"><?= e($line['model_name']) ?></td>
        <td><?= e($line['model_code']) ?></td>
        <td class="num"><?= formatNum((float)$line['qty']) ?></td>
        <td class="num">
          <input type="number" class="cell-input" min="0" step="0.01" placeholder="0">
        </td>
        <td class="num">
          <input type="number" class="cell-input pallet-count" min="0" step="1"
                 placeholder="0" oninput="calcTotals()">
        </td>
        <td>
          <textarea class="cell-input" rows="2" placeholder=""></textarea>
        </td>
      </tr>
      <?php endforeach; ?>
      <!-- Dòng tổng -->
      <tr class="total-row">
        <td colspan="3">TỔNG CỘNG</td>
        <td id="totalQty"><?= formatNum((float)array_sum(array_column($lines, 'qty'))) ?></td>
        <td>—</td>
        <td id="totalPallet">0</td>
        <td></td>
      </tr>
    </tbody>
  </table>

  <?php if (!empty($header['note'])): ?>
  <p style="font-size:12px; margin-bottom:6px"><em>Ghi chú: <?= e($header['note']) ?></em></p>
  <?php endif; ?>

  <!-- ── Chữ ký ──────────────────────────────────────────── -->
  <div class="sign-section">
    <div class="row">
      <div class="col-3 sign-box">
        <div class="sign-title">Người giao</div>
        <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
        <div class="sign-space"></div>
        <div class="sign-name"></div>
      </div>
      <div class="col-3 sign-box">
        <div class="sign-title">Lái xe</div>
        <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
        <div class="sign-space"></div>
        <div class="sign-name"></div>
      </div>
      <div class="col-3 sign-box">
        <div class="sign-title">Bảo vệ</div>
        <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
        <div class="sign-space"></div>
        <div class="sign-name"></div>
      </div>
      <div class="col-3 sign-box">
        <div class="sign-title">Người nhận</div>
        <div class="sign-hint">(Ký, ghi rõ họ tên)</div>
        <div class="sign-space"></div>
        <div class="sign-name"></div>
      </div>
    </div>
  </div>

  <div class="footer-note">Biên bản được lập thành 02 bản, mỗi bên giữ 01 bản.</div>

</div><!-- /.page -->

<script>
function fmt(n) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(n * 100) / 100);
}
function calcTotals() {
    let pallet = 0;
    document.querySelectorAll('.pallet-count').forEach(function(i) { pallet += parseFloat(i.value) || 0; });
    document.getElementById('totalPallet').textContent = fmt(pallet);
}
</script>
</body>
</html>
