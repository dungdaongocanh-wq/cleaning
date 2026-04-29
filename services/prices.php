<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo     = getDB();
$modelId = (int)($_GET['model_id'] ?? 0);

// ── Lấy thông tin model ──────────────────────────────────
$stmt = $pdo->prepare("
    SELECT cm.*, c.name AS cust_name, c.id AS cust_id
    FROM customer_models cm
    JOIN customers c ON c.id = cm.customer_id
    WHERE cm.id = ?
");
$stmt->execute([$modelId]);
$model = $stmt->fetch();
if (!$model) {
    setFlash('danger', 'Không tìm thấy mã hàng.');
    redirect(BASE_URL . '/services/index.php');
}

$errors = [];

// ════════════════════════════════════════════════════════
// XỬ LÝ POST
// ════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    // ── THÊM GIÁ MỚI ────────────────────────────────────
    if ($action === 'add') {
        $price    = parseInputPrice($_POST['unit_price'] ?? '0');
        $fromDate = trim($_POST['effective_from'] ?? '');
        $note     = trim($_POST['note'] ?? '');

        if ($price <= 0) $errors[] = 'Đơn giá phải lớn hơn 0.';
        if (!$fromDate)  $errors[] = 'Vui lòng chọn ngày hiệu lực.';

        if (!$errors) {
            $stmt2 = $pdo->prepare("
                INSERT INTO customer_model_prices
                    (customer_model_id, effective_from, unit_price, note, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt2->execute([$modelId, $fromDate, $price, $note, currentUser()['id']]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'customer_model_prices', $newId, null,
                ['price' => $price, 'from' => $fromDate, 'note' => $note],
                "Thêm giá {$model['model_code']}: " . formatNum($price) . " từ $fromDate"
            );
            setFlash('success', 'Thêm giá mới thành công!');
            redirect(BASE_URL . '/services/prices.php?model_id=' . $modelId);
        }
    }

    // ── CẬP NHẬT GIÁ ────────────────────────────────────
    if ($action === 'edit') {
        $priceId  = (int)($_POST['price_id'] ?? 0);
        $price    = parseInputPrice($_POST['unit_price'] ?? '0');
        $fromDate = trim($_POST['effective_from'] ?? '');
        $note     = trim($_POST['note'] ?? '');

        if ($price <= 0) $errors[] = 'Đơn giá phải lớn hơn 0.';
        if (!$fromDate)  $errors[] = 'Vui lòng chọn ngày hiệu lực.';

        if (!$errors && $priceId) {
            $stmtOld = $pdo->prepare("SELECT * FROM customer_model_prices WHERE id=?");
            $stmtOld->execute([$priceId]);
            $old = $stmtOld->fetch();

            $pdo->prepare("
                UPDATE customer_model_prices
                SET effective_from=?, unit_price=?, note=?
                WHERE id=? AND customer_model_id=?
            ")->execute([$fromDate, $price, $note, $priceId, $modelId]);

            auditLog('update', 'customer_model_prices', $priceId, $old,
                ['price' => $price, 'from' => $fromDate, 'note' => $note],
                "Sửa giá {$model['model_code']}"
            );
            setFlash('success', 'Cập nhật giá thành công!');
            redirect(BASE_URL . '/services/prices.php?model_id=' . $modelId);
        }
    }

    // ── XÓA GIÁ ─────────────────────────────────────────
    if ($action === 'delete') {
        $priceId = (int)($_POST['price_id'] ?? 0);
        if ($priceId) {
            $stmtOld = $pdo->prepare(
                "SELECT * FROM customer_model_prices WHERE id=?"
            );
            $stmtOld->execute([$priceId]);
            $old = $stmtOld->fetch();

            $pdo->prepare("
                DELETE FROM customer_model_prices
                WHERE id=? AND customer_model_id=?
            ")->execute([$priceId, $modelId]);

            auditLog('delete', 'customer_model_prices', $priceId, $old, null,
                "Xóa giá {$model['model_code']}: {$old['unit_price']} từ {$old['effective_from']}"
            );
            setFlash('success', 'Đã xóa giá.');
            redirect(BASE_URL . '/services/prices.php?model_id=' . $modelId);
        }
    }
}

// ── Lấy danh sách giá ────────────────────────────────────
$stmtP = $pdo->prepare("
    SELECT cmp.*, u.full_name AS creator
    FROM customer_model_prices cmp
    LEFT JOIN users u ON u.id = cmp.created_by
    WHERE cmp.customer_model_id = ?
    ORDER BY cmp.effective_from DESC
");
$stmtP->execute([$modelId]);
$priceList = $stmtP->fetchAll();

$pageTitle = 'Lịch sử giá — ' . $model['model_code'];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="d-flex align-items-center mb-4 gap-2">
  <a href="index.php?customer_id=<?= $model['cust_id'] ?>"
     class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h4 class="fw-bold mb-0">
    <i class="bi bi-clock-history me-2 text-primary"></i>
    Lịch sử giá: <span class="text-primary"><?= e($model['model_code']) ?></span>
    <small class="text-muted fs-6 ms-1">— <?= e($model['cust_name']) ?></small>
  </h4>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $er): ?>
        <li><?= e($er) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- ══ FORM THÊM GIÁ MỚI ══════════════════════════════ -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-primary text-white fw-semibold border-0">
        <i class="bi bi-plus-circle me-1"></i>Thêm giá mới
      </div>
      <div class="card-body">
        <form method="POST" id="addForm">
          <input type="hidden" name="action" value="add">

          <div class="mb-3">
            <label class="form-label fw-semibold">
              Hiệu lực từ ngày <span class="text-danger">*</span>
            </label>
            <input type="date" name="effective_from" class="form-control"
                   value="<?= date('Y-m-d') ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">
              Đơn giá <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <input type="text" name="unit_price"
                     class="form-control text-end num-input"
                     placeholder="3.690" required>
              <span class="input-group-text">đ</span>
            </div>
            <div class="form-text">Nhập số, VD: 3.690 hoặc 5.140</div>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Ghi chú</label>
            <input type="text" name="note" class="form-control"
                   placeholder="VD: Điều chỉnh tháng 5">
          </div>

          <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="bi bi-save me-1"></i>Lưu giá mới
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ══ DANH SÁCH GIÁ ═══════════════════════════════════ -->
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-0 fw-semibold">
        Lịch sử đơn giá
        <span class="badge bg-secondary ms-1"><?= count($priceList) ?></span>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:90px">#</th>
              <th>Hiệu lực từ</th>
              <th class="text-end">Đơn giá</th>
              <th>Ghi chú</th>
              <th>Người tạo</th>
              <th style="width:100px" class="text-center">Thao tác</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($priceList as $i => $p): ?>
            <tr <?= $i === 0 ? 'class="table-success"' : '' ?>>
              <td>
                <?php if ($i === 0): ?>
                  <span class="badge bg-success">Hiện tại</span>
                <?php else: ?>
                  <?= $i + 1 ?>
                <?php endif; ?>
              </td>
              <td class="fw-semibold">
                <?= date('d/m/Y', strtotime($p['effective_from'])) ?>
              </td>
              <td class="text-end fw-bold text-success">
                <?= formatNum((float)$p['unit_price']) ?> đ
              </td>
              <td class="text-muted small"><?= e($p['note']) ?></td>
              <td class="small"><?= e($p['creator'] ?? '—') ?></td>
              <td class="text-center text-nowrap">
                <button type="button"
                        class="btn btn-sm btn-outline-warning"
                        title="Sửa"
                        onclick="openEditModal(
                          <?= $p['id'] ?>,
                          '<?= $p['effective_from'] ?>',
                          '<?= formatNum((float)$p['unit_price']) ?>',
                          '<?= e(addslashes($p['note'] ?? '')) ?>'
                        )">
                  <i class="bi bi-pencil"></i>
                </button>
                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        title="Xóa"
                        onclick="openDeleteModal(
                          <?= $p['id'] ?>,
                          '<?= date('d/m/Y', strtotime($p['effective_from'])) ?>',
                          '<?= formatNum((float)$p['unit_price']) ?>'
                        )">
                  <i class="bi bi-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (!$priceList): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">
                <i class="bi bi-inbox fs-4 d-block mb-1"></i>
                Chưa có giá nào. Thêm giá đầu tiên ở bên trái.
              </td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL SỬA GIÁ ════════════════════════════════════════ -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" id="editForm">
      <input type="hidden" name="action"   value="edit">
      <input type="hidden" name="price_id" id="editPriceId">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h6 class="modal-title fw-bold">
            <i class="bi bi-pencil me-1"></i>Sửa đơn giá
          </h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Hiệu lực từ ngày <span class="text-danger">*</span>
            </label>
            <input type="date" name="effective_from" id="editFromDate"
                   class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">
              Đơn giá <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <input type="text" name="unit_price" id="editUnitPrice"
                     class="form-control text-end num-input" required>
              <span class="input-group-text">đ</span>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label fw-semibold">Ghi chú</label>
            <input type="text" name="note" id="editNote" class="form-control">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary"
                  data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-warning fw-semibold">
            <i class="bi bi-save me-1"></i>Lưu thay đổi
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL XÓA GIÁ ════════════════════════════════════════ -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action"   value="delete">
      <input type="hidden" name="price_id" id="deletePriceId">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h6 class="modal-title">
            <i class="bi bi-trash me-1"></i>Xác nhận xóa
          </h6>
          <button type="button" class="btn-close btn-close-white"
                  data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center py-3">
          <i class="bi bi-exclamation-triangle text-danger fs-2 d-block mb-2"></i>
          Xóa giá ngày <strong id="deleteFromDate"></strong><br>
          Đơn giá: <strong id="deletePrice" class="text-success"></strong> đ<br>
          <small class="text-muted">Hành động này không thể hoàn tác!</small>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm"
                  data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-danger btn-sm fw-semibold">
            <i class="bi bi-trash me-1"></i>Xóa
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, fromDate, price, note) {
    document.getElementById('editPriceId').value   = id;
    document.getElementById('editFromDate').value  = fromDate;  // YYYY-MM-DD
    document.getElementById('editUnitPrice').value = price;     // đã format VN
    document.getElementById('editNote').value      = note;
    // Reinit num-input cho modal
    initNumInputs(document.getElementById('editModal'));
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openDeleteModal(id, fromDate, price) {
    document.getElementById('deletePriceId').value        = id;
    document.getElementById('deleteFromDate').textContent = fromDate;
    document.getElementById('deletePrice').textContent    = price;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>