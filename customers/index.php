<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pageTitle = 'Khách hàng';
$pdo  = getDB();
$rows = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Khách hàng</h4>
  <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Thêm khách hàng</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body">
    <table id="dtCustomers" class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Mã KH</th><th>Tên khách hàng</th><th>MST</th>
          <th>Liên hệ</th><th>Trạng thái</th><th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $i => $c): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><span class="badge bg-primary"><?= e($c['code']) ?></span></td>
          <td class="fw-semibold"><?= e($c['name']) ?></td>
          <td><?= e($c['tax_code']) ?></td>
          <td>
            <?= e($c['contact_name']) ?>
            <?php if($c['contact_phone']): ?>
              <br><small class="text-muted"><?= e($c['contact_phone']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['is_active']): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td class="text-nowrap">
            <a href="../services/index.php?customer_id=<?= $c['id'] ?>"
               class="btn btn-sm btn-outline-info" title="Mã hàng & Giá">
              <i class="bi bi-tags"></i> Mã hàng
            </a>
            <a href="form.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning" title="Sửa">
              <i class="bi bi-pencil"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="confirmDelete(<?= $c['id'] ?>, '<?= e(addslashes($c['name'])) ?>')"
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
          <h6 class="modal-title">Xác nhận xóa</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          Xóa khách hàng <strong id="deleteName"></strong>?
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
  document.getElementById('deleteId').value   = id;
  document.getElementById('deleteName').textContent = name;
  new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>