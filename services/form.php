<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo        = getDB();
$id         = (int)($_GET['id']          ?? 0);
$customerId = (int)($_GET['customer_id'] ?? 0);
$model      = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM customer_models WHERE id=?");
    $stmt->execute([$id]);
    $model      = $stmt->fetch();
    $customerId = $model['customer_id'] ?? $customerId;
}

$customer = null;
if ($customerId) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
}

$pageTitle = $id ? 'Sửa mã hàng' : 'Thêm mã hàng';
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int)$_POST['customer_id'];
    $data = [
        'customer_id' => $customerId,
        'model_code'  => strtoupper(trim($_POST['model_code'] ?? '')),
        'model_name'  => trim($_POST['model_name'] ?? ''),
        'unit'        => trim($_POST['unit'] ?? 'cái'),
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];

    // Parse đơn giá kiểu VN: "3.690" → 3690, "3,690" → 3690, "3690" → 3690
    $rawInitPrice  = trim($_POST['init_price'] ?? '');
    $rawInitPrice  = str_replace('.', '', $rawInitPrice); // bỏ dấu chấm ngăn nghìn
    $rawInitPrice  = str_replace(',', '.', $rawInitPrice); // đổi phẩy thập phân → chấm
    $initPrice     = $rawInitPrice;
    $initPriceDate = trim($_POST['init_price_date'] ?? date('Y-m-d'));

    if (!$data['model_code']) $errors[] = 'Mã hàng không được để trống.';
    if (!$id && $initPrice && !is_numeric($initPrice)) $errors[] = 'Đơn giá không hợp lệ.';

    if (!$errors) {
        if ($id) {
            $before = $model;
            $stmt = $pdo->prepare("
                UPDATE customer_models
                SET model_code=?,model_name=?,unit=?,sort_order=?,is_active=?
                WHERE id=?
            ");
            $stmt->execute([$data['model_code'],$data['model_name'],$data['unit'],$data['sort_order'],$data['is_active'],$id]);
            auditLog('update','customer_models',$id,$before,$data);
            setFlash('success','Cập nhật mã hàng thành công!');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO customer_models (customer_id,model_code,model_name,unit,sort_order,is_active)
                VALUES (?,?,?,?,?,?)
            ");
            $stmt->execute([$customerId,$data['model_code'],$data['model_name'],$data['unit'],$data['sort_order'],$data['is_active']]);
            $newModelId = (int)$pdo->lastInsertId();
            auditLog('create','customer_models',$newModelId,null,$data);

            // Thêm giá khởi tạo nếu có
            if ($initPrice && (float)$initPrice > 0) {
                $pstmt = $pdo->prepare("
                    INSERT INTO customer_model_prices (customer_model_id,effective_from,unit_price,created_by)
                    VALUES (?,?,?,?)
                ");
                $pstmt->execute([$newModelId, $initPriceDate, (float)$initPrice, currentUser()['id']]);
            }
            setFlash('success','Thêm mã hàng thành công!');
        }
        redirect(BASE_URL . '/services/index.php?customer_id=' . $customerId);
    }
    $model = array_merge($model ?? [], $data);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex align-items-center mb-4 gap-2">
  <a href="index.php?customer_id=<?= $customerId ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= e($pageTitle) ?></h4>
  <?php if ($customer): ?>
    <span class="badge bg-primary fs-6"><?= e($customer['name']) ?></span>
  <?php endif; ?>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width:520px">
  <div class="card-body p-4">
    <form method="POST">
      <input type="hidden" name="customer_id" value="<?= $customerId ?>">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label fw-semibold">Mã hàng <span class="text-danger">*</span></label>
          <input type="text" name="model_code" class="form-control text-uppercase"
                 value="<?= e($model['model_code'] ?? '') ?>" placeholder="VD: XU01" required>
        </div>
        <div class="col-md-7">
          <label class="form-label fw-semibold">Tên hàng</label>
          <input type="text" name="model_name" class="form-control" value="<?= e($model['model_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Đơn vị tính</label>
          <input type="text" name="unit" class="form-control" value="<?= e($model['unit'] ?? 'cái') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold">Thứ tự sắp xếp</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)($model['sort_order'] ?? 0) ?>" min="0">
        </div>
        <?php if (!$id): ?>
        <div class="col-12"><hr class="my-1"><p class="fw-semibold text-primary mb-1">Giá khởi tạo</p></div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Đơn giá</label>
          <div class="input-group">
            <input type="text" name="init_price" class="form-control text-end num-input"
                   placeholder="3.690">
            <span class="input-group-text">đ</span>
          </div>
          <div class="form-text">Nhập số, VD: 3690 hoặc 3.690</div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Hiệu lực từ ngày</label>
          <input type="date" name="init_price_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <?php endif; ?>
        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   <?= (!isset($model['is_active']) || $model['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active</label>
          </div>
        </div>
      </div>
      <hr>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu</button>
        <a href="index.php?customer_id=<?= $customerId ?>" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
