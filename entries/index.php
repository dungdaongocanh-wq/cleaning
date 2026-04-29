<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$pdo  = getDB();
$user = currentUser();

// Filters
$fCustomer = (int)($_GET['customer_id'] ?? 0);
$fFrom     = $_GET['date_from'] ?? date('Y-m-01');
$fTo       = $_GET['date_to']   ?? date('Y-m-d');

$where  = ['1=1'];
$params = [];

if (!isAdmin()) {
    // User chỉ thấy phiếu do mình tạo
    $where[]  = 'eh.created_by = ?';
    $params[] = $user['id'];
}
if ($fCustomer) { $where[] = 'eh.customer_id = ?'; $params[] = $fCustomer; }
if ($fFrom)     { $where[] = 'eh.entry_date >= ?'; $params[] = $fFrom; }
if ($fTo)       { $where[] = 'eh.entry_date <= ?'; $params[] = $fTo; }

$sql = "
    SELECT eh.*,
           c.name AS cust_name, c.code AS cust_code,
           u.full_name AS creator,
           COALESCE(SUM(el.line_total),0) AS total_amount,
           COUNT(el.id) AS line_count
    FROM entry_headers eh
    JOIN customers c ON c.id = eh.customer_id
    JOIN users u ON u.id = eh.created_by
    LEFT JOIN entry_lines el ON el.entry_header_id = eh.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY eh.id
    ORDER BY eh.entry_date DESC, eh.updated_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$customers = $pdo->query("SELECT id,code,name FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();

$pageTitle = 'Danh sách phiếu nhập';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-table me-2 text-primary"></i>Danh sách phiếu nhập</h4>
  <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tạo phiếu mới</a>
</div>

<!-- Bộ lọc -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Từ ngày</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($fFrom) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Đến ngày</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($fTo) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Khách hàng</label>
        <select name="customer_id" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id']==$fCustomer?'selected':'' ?>>
              [<?= e($c['code']) ?>] <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Lọc</button>
        <a href="index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table id="dtEntries" class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Ngày nhập</th><th>Khách hàng</th>
          <th class="text-center">Số dòng</th>
          <th class="text-end">Tổng tiền</th>
          <th>Người tạo</th><th>Cập nhật lúc</th><th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($entries as $row): ?>
        <tr>
          <td class="fw-semibold"><?= date('d/m/Y', strtotime($row['entry_date'])) ?></td>
          <td><span class="badge bg-primary me-1"><?= e($row['cust_code']) ?></span><?= e($row['cust_name']) ?></td>
          <td class="text-center"><span class="badge bg-secondary"><?= $row['line_count'] ?></span></td>
          <td class="text-end fw-bold text-success"><?= formatVND((float)$row['total_amount']) ?></td>
          <td class="small text-muted"><?= e($row['creator']) ?></td>
          <td class="small text-muted"><?= date('d/m H:i', strtotime($row['updated_at'])) ?></td>
          <td class="text-nowrap">
            <a href="delivery_note.php?id=<?= $row['id'] ?>"
               class="btn btn-sm btn-outline-info"
               title="In biên bản giao hàng"
               target="_blank">
              <i class="bi bi-printer"></i>
            </a>
            <a href="form.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning" title="Sửa">
              <i class="bi bi-pencil"></i>
            </a>
            <button class="btn btn-sm btn-outline-danger"
                    onclick="confirmDelete(<?= $row['id'] ?>, '<?= date('d/m/Y',strtotime($row['entry_date'])) ?> - <?= e(addslashes($row['cust_name'])) ?>')"
                    title="Xóa">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="POST" action="delete.php">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h6 class="modal-title">Xóa phiếu</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">Xóa phiếu <strong id="deleteName"></strong>?
          <input type="hidden" name="id" id="deleteId">
        </div>
        <div class="modal-footer">
          <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
          <button class="btn btn-sm btn-danger">Xóa</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
function confirmDelete(id,name){
  document.getElementById('deleteId').value=id;
  document.getElementById('deleteName').textContent=name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>