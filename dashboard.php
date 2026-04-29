<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$pageTitle = 'Dashboard';
$pdo  = getDB();
$user = currentUser();

// ── Thống kê ──
$today = date('Y-m-d');
$thisMonth = date('Y-m');
[$mStart, $mEnd] = monthRange($thisMonth);

// Số phiếu hôm nay
$s1 = $pdo->prepare("SELECT COUNT(*) FROM entry_headers WHERE entry_date = ?");
$s1->execute([$today]);
$countToday = $s1->fetchColumn();

// Tổng tiền tháng này
$s2 = $pdo->prepare("
    SELECT COALESCE(SUM(el.line_total),0)
    FROM entry_lines el
    JOIN entry_headers eh ON eh.id = el.entry_header_id
    WHERE eh.entry_date BETWEEN ? AND ?
");
$s2->execute([$mStart, $mEnd]);
$totalMonth = (float)$s2->fetchColumn();

// Số kh��ch hàng active
$countCust = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_active=1")->fetchColumn();

// Top 5 khách tháng này
$s3 = $pdo->prepare("
    SELECT c.name, c.code, COALESCE(SUM(el.line_total),0) AS total
    FROM customers c
    JOIN entry_headers eh ON eh.customer_id = c.id
    JOIN entry_lines   el ON el.entry_header_id = eh.id
    WHERE eh.entry_date BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY total DESC LIMIT 5
");
$s3->execute([$mStart, $mEnd]);
$topCustomers = $s3->fetchAll();

// 10 phiếu gần nhất
$s4 = $pdo->prepare("
    SELECT eh.*, c.name AS cust_name, c.code AS cust_code,
           u.full_name AS creator,
           COALESCE(SUM(el.line_total),0) AS total_amount
    FROM entry_headers eh
    JOIN customers c ON c.id = eh.customer_id
    JOIN users     u ON u.id = eh.created_by
    LEFT JOIN entry_lines el ON el.entry_header_id = eh.id
    GROUP BY eh.id
    ORDER BY eh.updated_at DESC LIMIT 10
");
$s4->execute();
$recentEntries = $s4->fetchAll();

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<h4 class="mb-4 fw-bold"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard</h4>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
          <i class="bi bi-file-earmark-text text-primary fs-4"></i>
        </div>
        <div>
          <div class="text-muted small">Phiếu hôm nay</div>
          <div class="fs-3 fw-bold"><?= $countToday ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-success bg-opacity-10 p-3">
          <i class="bi bi-currency-exchange text-success fs-4"></i>
        </div>
        <div>
          <div class="text-muted small">Doanh thu tháng <?= date('m/Y') ?></div>
          <div class="fs-5 fw-bold text-success"><?= formatVND($totalMonth) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="rounded-circle bg-info bg-opacity-10 p-3">
          <i class="bi bi-building text-info fs-4"></i>
        </div>
        <div>
          <div class="text-muted small">Khách hàng active</div>
          <div class="fs-3 fw-bold"><?= $countCust ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Top khách hàng -->
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold border-0">
        <i class="bi bi-trophy text-warning me-1"></i> Top khách hàng tháng <?= date('m/Y') ?>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr><th>#</th><th>Khách hàng</th><th class="text-end">Doanh thu</th></tr>
          </thead>
          <tbody>
          <?php foreach ($topCustomers as $i => $row): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><span class="badge bg-secondary me-1"><?= e($row['code']) ?></span><?= e($row['name']) ?></td>
              <td class="text-end fw-semibold text-success"><?= formatVND((float)$row['total']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$topCustomers): ?>
            <tr><td colspan="3" class="text-center text-muted py-3">Chưa có dữ liệu</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Phiếu gần đây -->
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold border-0 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1"></i> Phiếu nhập gần đây</span>
        <a href="<?= BASE_URL ?>/entries/index.php" class="btn btn-sm btn-outline-primary">Xem tất cả</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Ngày</th><th>Khách hàng</th><th class="text-end">Tổng tiền</th><th>Người tạo</th></tr>
          </thead>
          <tbody>
          <?php foreach ($recentEntries as $row): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($row['entry_date'])) ?></td>
              <td><span class="badge bg-secondary me-1"><?= e($row['cust_code']) ?></span><?= e($row['cust_name']) ?></td>
              <td class="text-end fw-semibold"><?= formatVND((float)$row['total_amount']) ?></td>
              <td class="text-muted small"><?= e($row['creator']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentEntries): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">Chưa có phiếu nào</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>