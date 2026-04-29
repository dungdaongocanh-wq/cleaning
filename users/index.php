<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo  = getDB();
$rows = $pdo->query("SELECT * FROM users ORDER BY role, full_name")->fetchAll();

$pageTitle = 'Quản lý người dùng';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Người dùng</h4>
  <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Thêm người dùng</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table id="dtUsers" class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Username</th><th>Họ tên</th>
          <th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $i => $u): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td class="fw-semibold"><?= e($u['username']) ?></td>
          <td><?= e($u['full_name']) ?></td>
          <td>
            <?php if ($u['role'] === 'admin'): ?>
              <span class="badge bg-warning text-dark"><i class="bi bi-shield-check me-1"></i>Admin</span>
            <?php else: ?>
              <span class="badge bg-info"><i class="bi bi-person me-1"></i>User</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $u['is_active']
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>' ?>
          </td>
          <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td class="text-nowrap">
            <a href="form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if ($u['id'] !== currentUser()['id']): ?>
            <button class="btn btn-sm btn-outline-danger"
                    onclick="confirmDelete(<?= $u['id'] ?>, '<?= e(addslashes($u['full_name'])) ?>')">
              <i class="bi bi-trash"></i>
            </button>
            <?php endif; ?>
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
          <h6 class="modal-title">Xóa người dùng</h6>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">Xóa người dùng <strong id="deleteName"></strong>?
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