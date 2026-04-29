<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();
$pdo = getDB();
$id  = (int)($_GET['id'] ?? 0);
$row = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
}

$pageTitle = $id ? 'Sửa người dùng' : 'Thêm người dùng';
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'username'  => trim($_POST['username']  ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role'      => $_POST['role'] === 'admin' ? 'admin' : 'user',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!$data['username'])  $errors[] = 'Username không được để trống.';
    if (!$data['full_name']) $errors[] = 'Họ tên không được để trống.';
    if (!$id && !$password)  $errors[] = 'Mật khẩu không được để trống khi tạo mới.';
    if ($password && $password !== $password2) $errors[] = 'Xác nhận mật khẩu không khớp.';
    if ($password && strlen($password) < 6)   $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';

    if (!$errors) {
        if ($id) {
            if ($password) {
                $stmt = $pdo->prepare("UPDATE users SET username=?,full_name=?,role=?,is_active=?,password_hash=? WHERE id=?");
                $stmt->execute([$data['username'],$data['full_name'],$data['role'],$data['is_active'],password_hash($password,PASSWORD_BCRYPT),$id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username=?,full_name=?,role=?,is_active=? WHERE id=?");
                $stmt->execute([$data['username'],$data['full_name'],$data['role'],$data['is_active'],$id]);
            }
            auditLog('update','users',$id, $row, $data, 'Cập nh��t user '.$data['username']);
            setFlash('success','Cập nhật người dùng thành công!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username,full_name,role,is_active,password_hash) VALUES (?,?,?,?,?)");
            $stmt->execute([$data['username'],$data['full_name'],$data['role'],$data['is_active'],password_hash($password,PASSWORD_BCRYPT)]);
            $newId = (int)$pdo->lastInsertId();
            auditLog('create','users',$newId,null,$data,'Tạo user '.$data['username']);
            setFlash('success','Thêm người dùng thành công!');
        }
        redirect(BASE_URL . '/users/index.php');
    }
    $row = array_merge($row ?? [], $data);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="d-flex align-items-center mb-4 gap-2">
  <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= e($pageTitle) ?></h4>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width:520px">
  <div class="card-body p-4">
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control"
                 value="<?= e($row['username'] ?? '') ?>" required autocomplete="off">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Họ tên <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control"
                 value="<?= e($row['full_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">
            Mật khẩu <?= $id ? '<span class="text-muted small">(để trống = không đổi)</span>' : '<span class="text-danger">*</span>' ?>
          </label>
          <input type="password" name="password" class="form-control"
                 autocomplete="new-password" <?= !$id ? 'required' : '' ?>>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Xác nhận mật khẩu</label>
          <input type="password" name="password2" class="form-control" autocomplete="new-password">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Vai trò</label>
          <select name="role" class="form-select">
            <option value="user"  <?= ($row['role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>User (Nhập liệu)</option>
            <option value="admin" <?= ($row['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
        <div class="col-md-6 d-flex align-items-end pb-1">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   <?= (!isset($row['is_active']) || $row['is_active']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active</label>
          </div>
        </div>
      </div>
      <hr>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Lưu</button>
        <a href="index.php" class="btn btn-outline-secondary">Hủy</a>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>