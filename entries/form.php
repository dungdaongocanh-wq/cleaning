<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
$pdo  = getDB();
$id   = (int)($_GET['id'] ?? 0);
$user = currentUser();

$header = null;
$lines  = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM entry_headers WHERE id=?");
    $stmt->execute([$id]);
    $header = $stmt->fetch();
    if (!$header) {
        setFlash('danger', 'Không tìm thấy phiếu.');
        redirect(BASE_URL . '/entries/index.php');
    }

    $stmt2 = $pdo->prepare("
        SELECT el.*, cm.model_code, cm.model_name, cm.unit
        FROM entry_lines el
        JOIN customer_models cm ON cm.id = el.customer_model_id
        WHERE el.entry_header_id = ?
        ORDER BY cm.sort_order, cm.model_code
    ");
    $stmt2->execute([$id]);
    $lines = $stmt2->fetchAll();
}

$customers = $pdo->query(
    "SELECT id, code, name FROM customers WHERE is_active=1 ORDER BY name"
)->fetchAll();

$pageTitle = $id ? 'Sửa phiếu nhập' : 'Tạo phiếu nhập mới';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="d-flex align-items-center mb-4 gap-2">
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i>
  </a>
  <h4 class="fw-bold mb-0"><?= e($pageTitle) ?></h4>
</div>

<form id="entryForm" method="POST" action="save.php">
  <?php if ($id): ?>
    <input type="hidden" name="id" value="<?= $id ?>">
  <?php endif; ?>

  <!-- ── Header phiếu ─────────────────────────────────── -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label fw-semibold">
            Ngày nhập <span class="text-danger">*</span>
          </label>
          <input type="date" name="entry_date" id="entryDate" class="form-control"
                 value="<?= e($header['entry_date'] ?? date('Y-m-d')) ?>" required>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">
            Khách hàng <span class="text-danger">*</span>
          </label>
          <select name="customer_id" id="customerId" class="form-select" required>
            <option value="">-- Chọn khách hàng --</option>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"
                <?= ($header['customer_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                [<?= e($c['code']) ?>] <?= e($c['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Ghi chú</label>
          <input type="text" name="note" class="form-control"
                 value="<?= e($header['note'] ?? '') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- ── Bảng chi tiết ────────────────────────────────── -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Chi tiết mã hàng</span>
      <button type="button" id="loadServicesBtn" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-arrow-repeat me-1"></i>Tải danh sách hàng
      </button>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered align-middle" id="linesTable">
          <thead class="table-light">
            <tr>
              <th style="width:40px">#</th>
              <th>Mã hàng</th>
              <th>Tên hàng</th>
              <th style="width:70px">ĐVT</th>
              <th style="width:140px" class="text-end">Đơn giá (đ)</th>
              <th style="width:120px" class="text-center">Số lượng</th>
              <th style="width:150px" class="text-end">Thành tiền (đ)</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody id="linesBody">
            <?php foreach ($lines as $i => $line): ?>
              <tr class="line-row">
                <td class="text-center row-num"><?= $i + 1 ?></td>
                <td>
                  <span class="fw-semibold"><?= e($line['model_code']) ?></span>
                  <input type="hidden" name="model_id[]"
                         value="<?= $line['customer_model_id'] ?>">
                </td>
                <td><?= e($line['model_name']) ?></td>
                <td class="text-center"><?= e($line['unit']) ?></td>
                <td class="text-end">
                  <input type="hidden" name="unit_price[]"
                         class="unit-price"
                         value="<?= $line['unit_price_snapshot'] ?>">
                  <span class="price-display">
                    <?= formatNum((float)$line['unit_price_snapshot']) ?>
                  </span>
                </td>
                <td>
                  <input type="number" name="qty[]"
                         class="form-control form-control-sm text-center qty-input"
                         value="<?= formatNum((float)$line['qty']) ?>"
                         min="0" step="1">
                </td>
                <td class="text-end fw-semibold line-total"
                    data-raw="<?= $line['line_total'] ?>">
                  <?= formatNum((float)$line['line_total']) ?>
                </td>
                <td>
                  <button type="button"
                          class="btn btn-sm btn-outline-danger remove-line">
                    <i class="bi bi-x"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="table-light">
              <td colspan="6" class="text-end fw-bold">TỔNG CỘNG:</td>
              <td class="text-end fw-bold text-success fs-6" id="grandTotal">
                <?= formatVND((float)array_sum(array_column($lines, 'line_total'))) ?>
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-success btn-lg">
      <i class="bi bi-save me-1"></i>Lưu phiếu
    </button>
    <a href="index.php" class="btn btn-outline-secondary btn-lg">Hủy</a>
  </div>
</form>

<!-- Row template (hidden) -->
<template id="rowTemplate">
  <tr class="line-row">
    <td class="text-center row-num"></td>
    <td>
      <span class="fw-semibold model-code-display"></span>
      <input type="hidden" name="model_id[]" class="model-id">
    </td>
    <td class="model-name-display"></td>
    <td class="text-center unit-display"></td>
    <td class="text-end">
      <input type="hidden" name="unit_price[]" class="unit-price">
      <span class="price-display"></span>
    </td>
    <td>
      <input type="number" name="qty[]"
             class="form-control form-control-sm text-center qty-input"
             value="0" min="0" step="1">
    </td>
    <td class="text-end fw-semibold line-total" data-raw="0">0</td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-danger remove-line">
        <i class="bi bi-x"></i>
      </button>
    </td>
  </tr>
</template>

<script>
const BASE_URL = '<?= BASE_URL ?>';

document.getElementById('loadServicesBtn').addEventListener('click', loadServices);

// ── Load danh sách dịch vụ theo KH + ngày ────────────────
async function loadServices() {
    const customerId = document.getElementById('customerId').value;
    const entryDate  = document.getElementById('entryDate').value;
    if (!customerId) {
        alert('Vui lòng chọn khách hàng trước.');
        return;
    }

    const btn = document.getElementById('loadServicesBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang tải...';

    try {
        const res  = await fetch(
            `${BASE_URL}/entries/get_services.php?customer_id=${customerId}&date=${entryDate}`
        );
        const data = await res.json();
        const tbody = document.getElementById('linesBody');

        // Lấy các model_id đã có trong bảng
        const existingIds = new Set(
            [...tbody.querySelectorAll('input[name="model_id[]"]')].map(i => i.value)
        );

        data.forEach(function(item) {
            // Bỏ qua nếu đã tồn tại
            if (existingIds.has(String(item.id))) return;

            const tpl = document.getElementById('rowTemplate').content.cloneNode(true);
            const tr  = tpl.querySelector('tr');

            tr.querySelector('.model-code-display').textContent = item.model_code;
            tr.querySelector('.model-name-display').textContent = item.model_name || '';
            tr.querySelector('.unit-display').textContent       = item.unit;
            tr.querySelector('.model-id').value                 = item.id;
            tr.querySelector('.unit-price').value               = item.unit_price;
            tr.querySelector('.price-display').textContent      = formatVN(item.unit_price);

            tbody.appendChild(tpl);
        });

        // Reindex lại số thứ tự tất cả dòng
        reindex();

        bindLineEvents();
        updateTotal();
    } catch(err) {
        alert('Lỗi khi tải danh sách hàng: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Tải danh sách hàng';
    }
}

// ── Gắn sự kiện cho các dòng ─────────────────────────────
function bindLineEvents() {
    document.querySelectorAll('.qty-input').forEach(function(inp) {
        inp.removeEventListener('input', onQtyInput);
        inp.addEventListener('input', onQtyInput);
    });
    document.querySelectorAll('.remove-line').forEach(function(btn) {
        btn.removeEventListener('click', onRemoveLine);
        btn.addEventListener('click', onRemoveLine);
    });
}

function onQtyInput(e) {
    const tr    = e.target.closest('tr');
    const price = parseFloat(tr.querySelector('.unit-price').value) || 0;
    const qty   = parseFloat(e.target.value) || 0;
    const total = price * qty;
    const tdTotal = tr.querySelector('.line-total');
    tdTotal.textContent  = formatVN(total);
    tdTotal.dataset.raw  = total;
    updateTotal();
}

function onRemoveLine(e) {
    e.target.closest('tr').remove();
    reindex();
    updateTotal();
}

function reindex() {
    document.querySelectorAll('#linesBody tr').forEach(function(tr, i) {
        tr.querySelector('.row-num').textContent = i + 1;
    });
}

// ── Cập nhật tổng cộng ───────────────────────────────────
function updateTotal() {
    let total = 0;
    document.querySelectorAll('.line-total').forEach(function(td) {
        total += parseFloat(td.dataset.raw) || 0;
    });
    document.getElementById('grandTotal').textContent = formatVN(total) + ' đ';
}

// ── Init các dòng đã có sẵn khi sửa phiếu ───────────────
bindLineEvents();
updateTotal();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>