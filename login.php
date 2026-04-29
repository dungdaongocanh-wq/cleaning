<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            redirect(BASE_URL . '/dashboard.php');
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body { background: linear-gradient(135deg, #0d6efd 0%, #0a4cac 100%); min-height: 100vh; }
  .login-card { border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.25); }
</style>
</head>
<body class="d-flex align-items-center justify-content-center">
<div class="card login-card p-4" style="width:380px">
  <div class="text-center mb-4">
    <img src="<?= BASE_URL ?>/assets/images/logo.png" height="60" class="mb-2"
         onerror="this.style.display='none'">
    <h4 class="fw-bold text-primary"><?= APP_NAME ?></h4>
    <p class="text-muted small">Hệ thống quản lý bảng kê</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="form-label fw-semibold">Tên đăng nhập</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person"></i></span>
        <input type="text" name="username" class="form-control"
               value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
      </div>
    </div>
    <div class="mb-4">
      <label class="form-label fw-semibold">Mật khẩu</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock"></i></span>
        <input type="password" name="password" id="pwd" class="form-control" required>
        <button type="button" class="btn btn-outline-secondary"
                onclick="var p=document.getElementById('pwd');p.type=p.type==='password'?'text':'password'">
          <i class="bi bi-eye"></i>
        </button>
      </div>
    </div>
    <button type="submit" class="btn btn-primary w-100 fw-semibold">
      <i class="bi bi-box-arrow-in-right me-1"></i>Đăng nhập
    </button>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>