<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$pdo = getDB();

$fMonth    = $_GET['month']       ?? date('Y-m');
$fCustomer = (int)($_GET['customer_id'] ?? 0);
$viewType  = $_GET['view']        ?? 'summary'; // summary | detail

[$mStart, $mEnd] = monthRange($fMonth);
$customers = $pdo->query("SELECT id,code,name FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();

$reportData  = [];
$headerInfo  = null;

if ($fCustomer) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$fCustomer]);
    $headerInfo = $stmt->fetch();

    if ($viewType === 'detail') {
        // Chi tiết theo ngày
        $sql = "
            SELECT eh.entry_date,
                   cm.model_code, cm.model_name, cm.unit,
                   el.qty, el.unit_price_snapshot, el.line_total,
                   u.full_name AS creator
            FROM entry_lines el
            JOIN entry_headers eh ON eh.id = el.entry_header_id
            JOIN customer_models cm ON cm.id = el.customer_model_id
            JOIN users u ON u.id = eh.created_by
            WHERE eh.customer_id = ? AND eh.entry_date BETWEEN ? AND ?
            ORDER BY eh.entry_date, cm.sort_order, cm.model_code
        ";
        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute([$fCustomer, $mStart, $mEnd]);
        $reportData = $stmt2->fetchAll();
    } else {
        // Tổng hợp theo model
        $sql = "
            SELECT cm.model_code, cm.model_name, cm.unit,
                   el.unit_price_snapshot,
                   SUM(el.qty) AS total_qty,
                   SUM(el.line_total) AS total_amount
            FROM entry_lines el
            JOIN entry_headers eh ON eh.id = el.entry_header_id
            JOIN customer_models cm ON cm.id = el.customer_model_id
            WHERE eh.customer_id = ? AND eh.entry_date BETWEEN ? AND ?
            GROUP BY cm.id, el.unit_price_snapshot
            ORDER BY cm.sort_order, cm.model_code
        ";
        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute([$fCustomer, $mStart, $mEnd]);
        $reportData = $stmt2->fetchAll();
    }
}

$grandTotal = array_sum(array_column($reportData, 'total_amount'));
$invoiceNo  = $headerInfo ? generateInvoiceNumber($headerInfo['code'], $mEnd) : '';

$pageTitle = 'Báo cáo & Xuất hóa đơn';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<h4 class="fw-bold mb-4"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Báo cáo & Xuất hóa đơn</h4>

<!-- Bộ lọc -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label fw-semibold">Tháng</label>
        <input type="month" name="month" class="form-control" value="<?= e($fMonth) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Khách hàng</label>
        <select name="customer_id" class="form-select" required>
          <option value="">-- Chọn khách hàng --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id']==$fCustomer?'selected':'' ?>>
              [<?= e($c['code']) ?>] <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Loại xem</label>
        <select name="view" class="form-select">
          <option value="summary" <?= $viewType==='summary'?'selected':'' ?>>Tổng hợp theo model</option>
          <option value="detail"  <?= $viewType==='detail' ?'selected':'' ?>>Chi tiết theo ngày</option>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Xem báo cáo</button>
      </div>
    </form>
  </div>
</div>

<?php if ($headerInfo && $reportData): ?>
<!-- Info hóa đơn -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted fw-semibold" style="width:140px">Khách hàng:</td><td class="fw-bold"><?= e($headerInfo['name']) ?></td></tr>
          <tr><td class="text-muted fw-semibold">MST:</td><td><?= e($headerInfo['tax_code'] ?? '—') ?></td></tr>
          <tr><td class="text-muted fw-semibold">Địa chỉ:</td><td><?= e($headerInfo['address'] ?? '—') ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <table class="table table-sm table-borderless mb-0">
          <tr><td class="text-muted fw-semibold" style="width:140px">Kỳ:</td><td class="fw-bold">Tháng <?= date('m/Y', strtotime($mStart)) ?></td></tr>
          <tr><td class="text-muted fw-semibold">Số HĐ:</td><td class="fw-bold text-primary"><?= e($invoiceNo) ?></td></tr>
          <tr><td class="text-muted fw-semibold">VAT:</td><td>0%</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Bảng dữ liệu -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-0">
    <?php if ($viewType === 'summary'): ?>
    <table class="table table-bordered align-middle mb-0">
      <thead class="table-primary">
        <tr>
          <th>#</th><th>Mã hàng</th><th>Tên hàng</th><th>ĐVT</th>
          <th class="text-end">Đơn giá</th>
          <th class="text-center">Tổng SL</th>
          <th class="text-end">Thành tiền</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($reportData as $i => $row): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td class="fw-semibold"><?= e($row['model_code']) ?></td>
          <td><?= e($row['model_name']) ?></td>
          <td><?= e($row['unit']) ?></td>
          <td class="text-end"><?= number_format((float)$row['unit_price_snapshot'],0,',','.') ?></td>
          <td class="text-center fw-semibold"><?= number_format((float)$row['total_qty'],0,',','.') ?></td>
          <td class="text-end fw-bold text-success"><?= number_format((float)$row['total_amount'],0,',','.') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <td colspan="6" class="text-end fw-bold">TỔNG CỘNG:</td>
          <td class="text-end fw-bold fs-6 text-success"><?= number_format($grandTotal,0,',','.') ?> đ</td>
        </tr>
        <tr>
          <td colspan="6" class="text-end fw-semibold">Thuế VAT (0%):</td>
          <td class="text-end">0 đ</td>
        </tr>
        <tr class="table-success">
          <td colspan="6" class="text-end fw-bold">TỔNG THANH TOÁN:</td>
          <td class="text-end fw-bold fs-5 text-success"><?= number_format($grandTotal,0,',','.') ?> đ</td>
        </tr>
      </tfoot>
    </table>

    <?php else: ?>
    <!-- Chi tiết theo ngày -->
    <table class="table table-bordered align-middle mb-0" style="font-size:.9rem">
      <thead class="table-primary">
        <tr>
          <th>Ngày</th><th>Mã hàng</th><th>Tên hàng</th><th>ĐVT</th>
          <th class="text-end">Đơn giá</th>
          <th class="text-center">SL</th>
          <th class="text-end">Thành tiền</th>
          <th>NV nhập</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($reportData as $row): ?>
        <tr>
          <td><?= date('d/m/Y', strtotime($row['entry_date'])) ?></td>
          <td class="fw-semibold"><?= e($row['model_code']) ?></td>
          <td><?= e($row['model_name']) ?></td>
          <td><?= e($row['unit']) ?></td>
          <td class="text-end"><?= number_format((float)$row['unit_price_snapshot'],0,',','.') ?></td>
          <td class="text-center"><?= number_format((float)$row['qty'],0,',','.') ?></td>
          <td class="text-end fw-semibold"><?= number_format((float)$row['line_total'],0,',','.') ?></td>
          <td class="small text-muted"><?= e($row['creator']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light">
        <tr>
          <td colspan="7" class="text-end fw-bold">TỔNG CỘNG:</td>
          <td class="fw-bold text-success"><?= number_format($grandTotal,0,',','.') ?> đ</td>
        </tr>
      </tfoot>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Nút xuất -->
<div class="d-flex gap-2">
  <a href="export_excel.php?month=<?= urlencode($fMonth) ?>&customer_id=<?= $fCustomer ?>&type=report"
     class="btn btn-success btn-lg">
    <i class="bi bi-file-earmark-excel me-1"></i>Xuất Báo cáo Excel
  </a>
  <a href
  <a href="export_excel.php?month=<?= urlencode($fMonth) ?>&customer_id=<?= $fCustomer ?>&type=report"
     class="btn btn-success btn-lg">
    <i class="bi bi-file-earmark-excel me-1"></i>Xuất Báo cáo Excel
  </a>
  <a href="export_excel.php?month=<?= urlencode($fMonth) ?>&customer_id=<?= $fCustomer ?>&type=invoice"
     class="btn btn-primary btn-lg">
    <i class="bi bi-receipt me-1"></i>Xuất Hóa đơn VAT
  </a>
</div>

<?php elseif ($fCustomer && !$reportData): ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Không có dữ liệu trong tháng <?= date('m/Y', strtotime($mStart)) ?>.
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>