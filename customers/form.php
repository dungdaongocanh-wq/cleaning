<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$customer = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
}

$pageTitle = $id ? 'Sửa khách hàng' : 'Thêm khách hàng';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'code'          => strtoupper(trim($_POST['code']          ?? '')),
        'name'          => trim($_POST['name']          ?? ''),
        'tax_code'      => trim($_POST['tax_code']      ?? ''),
        'address'       => trim($_POST['address']       ?? ''),
        'contact_name'  => trim($_POST['contact_name']  ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'bank_name'     => trim($_POST['bank_name']     ?? ''),
        'bank_account'  => trim($_POST['bank_account']  ?? ''),
        'is_active'     => isset($_POST['is_active']) ? 1 : 0,
    ];

    if (!$data['code']) $errors[] = 'Mã khách hàng không được để trống.';
    if (!$data['name']) $errors[] = 'Tên khách hàng không được để trống.';

    if (!$errors) {
        if ($id) {
            $before = $customer;
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET code=?,name=?,tax_code=?,address=?,contact_name=?,
                    contact_phone=?,bank_name=?,bank_account=?,is_active=?
                WHERE id=?
            ");
            $stmt->execute([...$data, $id]);
            auditLog('update', 'customers', $id, $before, $data);
            setFlash('success', 'Cập nhật khách hàng thành công!');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO customers 
                    (code,name,tax_code,address,contact_name,contact_phone,bank_name,bank_account,is_active)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute(array_values($data));
            $newId = (int)$pdo->lastInsertId();
            auditLog('create', 'customers', $newId, null, $data);
            setFlash('success', 'Thêm khách hàng thành công! Vui lòng thiết lập mã hàng và giá.');
            redirect(BASE_URL . '/services/index.php?customer_id=' . $newId);
        }
        redirect(BASE_URL . '/customers/index.php');
    }
    $customer = $data;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex align-items-center mb-4 gap-2">
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= e($pageTitle) ?></h4>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width:680px">
  <div class="card-body p-4">
    <form method="POST">
      <div class="row g-3">

        <!-- Thông tin cơ bản -->
        <div class="col-12"><h6 class="fw-bold text-primary border-bottom pb-1 mb-0">Thông tin cơ bản</h6></div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Mã khách hàng <span class="text-danger">*</span></label>
          <input type="text" name="code" class="form-control text-uppercase"
                 value="<?= e($customer['code'] ?? '') ?>"
                 placeholder="VD: SAMJU" maxlength="20" required>
          <div class="form-text">Dùng cho số HĐ: ECO-<em>SAMJU</em>/DDMMYY</div>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Tên khách hàng <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control"
                 value="<?= e($customer['name'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label fw-semibold">Địa chỉ</label>
          <textarea name="address" class="form-control" rows="2"><?= e($customer['address'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Mã số thuế</label>
          <input type="text" name="tax_code" class="form-control"
                 value="<?= e($customer['tax_code'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">SĐT liên hệ</label>
          <input type="text" name="contact_phone" class="form-control"
                 value="<?= e($customer['contact_phone'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Người liên hệ</label>
          <input type="text" name="contact_name" class="form-control"
                 value="<?= e($customer['contact_name'] ?? '') ?>">
        </div>

        <!-- Thông tin ngân hàng -->
        <div class="col-12 mt-2">
          <h6 class="fw-bold text-primary border-bottom pb-1 mb-0">
            <i class="bi bi-bank me-1"></i>Thông tin ngân hàng
          </h6>
        </div>
        <div class="col-md-8">
          <label class="form-label fw-semibold">Tên ngân hàng</label>
          <input type="text" name="bank_name" class="form-control"
                 value="<?= e($customer['bank_name'] ?? '') ?>"
                 placeholder="VD: Ngân hàng TMCP Ngoại thương Việt Nam">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Số tài khoản</label>
          <input type="text" name="bank_account" class="form-control"
                 value="<?= e($customer['bank_account'] ?? '') ?>"
                 placeholder="VD: 0123456789">
        </div>

        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   <?= (!isset($customer['is_active']) || $customer['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active</label>
          </div>
        </div>
      </div>
      <hr>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i><?= $id ? 'Cập nhật' : 'Lưu & Thiết lập mã hàng' ?>
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>