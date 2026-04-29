<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDB();
$customerId = (int)($_GET['customer_id'] ?? 0);

$customers = $pdo->query("SELECT * FROM customers WHERE is_active=1 ORDER BY name")->fetchAll();

$customer = null;
$models   = [];
if ($customerId) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    $stmt2 = $pdo->prepare("
        SELECT cm.*,
               (SELECT unit_price FROM customer_model_prices
                WHERE customer_model_id = cm.id
                ORDER BY effective_from DESC LIMIT 1) AS latest_price,
               (SELECT effective_from FROM customer_model_prices
                WHERE customer_model_id = cm.id
                ORDER BY effective_from DESC LIMIT 1) AS price_from
        FROM customer_models cm
        WHERE cm.customer_id = ?
        ORDER BY cm.sort_order, cm.model_code
    ");
    $stmt2->execute([$customerId]);
    $models = $stmt2->fetchAll();
}

$pageTitle = 'Mã hàng & Bảng giá';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex align-items-center mb-4 gap-2">
  <a href="../customers/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><i class="bi bi-tags me-2 text-primary"></i>Mã hàng & Bảng giá</h4>
</div>

<!-- Chọn khách hàng -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Chọn khách hàng</label>
        <select name="customer_id" class="form-select" onchange="this.form.submit()">
          <option value="">-- Chọn --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $c['id'] == $customerId ? 'selected' : '' ?>>
              [<?= e($c['code']) ?>] <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($customerId): ?>
      <div class="col-auto">
        <a href="form.php?customer_id=<?= $customerId ?>" class="btn btn-primary">
          <i class="bi bi-plus-circle me-1"></i>Thêm mã hàng
        </a>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if ($customer && $models !== false): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-0 fw-semibold">
    Danh sách mã hàng của <span class="text-primary"><?= e($customer['name']) ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Mã hàng</th><th>Tên hàng</th><th>ĐVT</th>
          <th class="text-end">Giá hiện tại</th><th>Hiệu lực từ</th>
          <th>Trạng thái</th><th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($models as $m): ?>
        <tr>
          <td class="fw-semibold"><?= e($m['model_code']) ?></td>
          <td><?= e($m['model_name']) ?></td>
          <td><?= e($m['unit']) ?></td>
          <td class="text-end fw-semibold text-success">
            <?= $m['latest_price'] !== null ? number_format((float)$m['latest_price'], 0, ',', '.') : '<span class="text-danger small">Chưa có giá</span>' ?>
          </td>
          <td class="text-muted small">
            <?= $m['price_from'] ? date('d/m/Y', strtotime($m['price_from'])) : '—' ?>
          </td>
          <td><?= $m['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
          <td class="text-nowrap">
            <a href="prices.php?model_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-info" title="Lịch sử giá">
              <i class="bi bi-clock-history"></i> Giá
            </a>
            <a href="form.php?id=<?= $m['id'] ?>&customer_id=<?= $customerId ?>" class="btn btn-sm btn-outline-warning">
              <i class="bi bi-pencil"></i>
            </a>
            <button class="btn btn-sm btn-outline-danger"
                    onclick="confirmDelete(<?= $m['id'] ?>, '<?= e(addslashes($m['model_code'])) ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$models): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">
          Chưa có mã hàng. <a href="form.php?customer_id=<?= $customerId ?>">Thêm ngay</a>
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($customerId): ?>
  <div class="alert alert-warning">Không tìm thấy khách hàng.</div>
<?php endif; ?>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="POST" action="delete.php">
      <input type="hidden" name="customer_id" value="<?= $customerId ?>">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h6 class="modal-title">Xác nhận xóa</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">Xóa mã hàng <strong id="deleteName"></strong>?
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
function confirmDelete(id, name) {
  document.getElementById('deleteId').value = id;
  document.getElementById('deleteName').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>