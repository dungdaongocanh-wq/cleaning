<?php
declare(strict_types=1);

$errors  = [];
$success = [];

if (PHP_VERSION_ID < 80200) {
    $errors[] = 'Cần PHP >= 8.2, hiện tại: ' . PHP_VERSION;
}
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    $errors[] = 'Chưa chạy <code>composer install</code>.';
}

// ── SQL nhúng thẳng ──────────────────────────────────��────────
$SETUP_SQL = "
CREATE DATABASE IF NOT EXISTS `{DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `{DB_NAME}`;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(20)  NOT NULL UNIQUE,
    name          VARCHAR(100) NOT NULL,
    tax_code      VARCHAR(20),
    address       TEXT,
    contact_name  VARCHAR(100),
    contact_phone VARCHAR(20),
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_models (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT         NOT NULL,
    model_code   VARCHAR(50) NOT NULL,
    model_name   VARCHAR(100),
    unit         VARCHAR(20) NOT NULL DEFAULT 'cai',
    is_active    TINYINT(1)  NOT NULL DEFAULT 1,
    sort_order   INT         NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cust_model (customer_id, model_code),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_model_prices (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    customer_model_id INT           NOT NULL,
    effective_from    DATE          NOT NULL,
    unit_price        DECIMAL(15,2) NOT NULL,
    note              VARCHAR(255),
    created_by        INT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_model_id) REFERENCES customer_models(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)        REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entry_headers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT  NOT NULL,
    entry_date  DATE NOT NULL,
    note        TEXT,
    created_by  INT  NOT NULL,
    updated_by  INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by)  REFERENCES users(id),
    FOREIGN KEY (updated_by)  REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS entry_lines (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    entry_header_id     INT           NOT NULL,
    customer_model_id   INT           NOT NULL,
    qty                 DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit_price_snapshot DECIMAL(15,2) NOT NULL,
    line_total          DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (entry_header_id)   REFERENCES entry_headers(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_model_id) REFERENCES customer_models(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT,
    action      ENUM('create','update','delete') NOT NULL,
    table_name  VARCHAR(50)  NOT NULL,
    record_id   INT,
    before_data JSON,
    after_data  JSON,
    ip_address  VARCHAR(45),
    description VARCHAR(255),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO users (username, password_hash, full_name, role) VALUES
('admin',
 '\$2y\$10\$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77bqiW',
 'Administrator', 'admin');
";

// ── Xử lý POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    $host  = trim($_POST['db_host'] ?? 'localhost');
    $user  = trim($_POST['db_user'] ?? 'root');
    $pass  = $_POST['db_pass'] ?? '';
    $name  = trim($_POST['db_name'] ?? 'cleaning_app');

    try {
        // Kết nối không chỉ định DB trước
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Thay {DB_NAME} bằng tên thực
        $sql = str_replace('{DB_NAME}', $name, $SETUP_SQL);

        // Tách & chạy từng statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stm) {
            if ($stm !== '') $pdo->exec($stm);
        }

        $success[] = '✅ Database <strong>' . htmlspecialchars($name) . '</strong> tạo thành công!';
        $success[] = '✅ Tài khoản mặc định: <strong>admin</strong> / <strong>Admin@123</strong>';
        $success[] = '⚠️ <strong>Xóa file setup.php sau khi hoàn thành!</strong>';

        // Ghi lại config/database.php
        $cfg = <<<PHP
<?php
declare(strict_types=1);

define('DB_HOST',    '$host');
define('DB_NAME',    '$name');
define('DB_USER',    '$user');
define('DB_PASS',    '$pass');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return \$pdo;
}
PHP;
        file_put_contents(__DIR__ . '/config/database.php', $cfg);
        $success[] = '✅ File <code>config/database.php</code> đã được cập nhật.';

    } catch (PDOException $e) {
        $errors[] = 'Lỗi kết nối / tạo DB: ' . $e->getMessage();
    }
}

// ── Checklist ─────────────────────────────────────────────────
$checks = [
    ['PHP >= 8.2',              PHP_VERSION_ID >= 80200],
    ['ext-pdo',                 extension_loaded('pdo')],
    ['ext-pdo_mysql',           extension_loaded('pdo_mysql')],
    ['ext-mbstring',            extension_loaded('mbstring')],
    ['ext-zip',                 extension_loaded('zip')],
    ['ext-xml',                 extension_loaded('xml')],
    ['ext-gd',                  extension_loaded('gd')],
    ['vendor/ (composer)',      file_exists(__DIR__.'/vendor/autoload.php')],
    ['config/ writable',        is_writable(__DIR__.'/config')],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup — Cleaning App</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:580px">
  <div class="card shadow border-0">
    <div class="card-header bg-primary text-white text-center py-3">
      <h4 class="mb-0">🚀 Cleaning App — Cài đặt lần đầu</h4>
    </div>
    <div class="card-body p-4">

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <ul class="mb-0"><?php foreach ($success as $s): ?><li><?= $s ?></li><?php endforeach; ?></ul>
        </div>
        <a href="login.php" class="btn btn-success w-100 mt-2 fw-semibold">
          Đến trang đăng nhập →
        </a>

      <?php else: ?>

        <!-- Checklist -->
        <h6 class="fw-semibold mb-2">Kiểm tra môi trường</h6>
        <ul class="list-group list-group-flush mb-4">
          <?php foreach ($checks as [$label, $ok]): ?>
            <li class="list-group-item d-flex justify-content-between py-2">
              <span><?= htmlspecialchars($label) ?></span>
              <?php if ($ok): ?>
                <span class="badge bg-success">✓ OK</span>
              <?php else: ?>
                <span class="badge bg-danger">✗ Thiếu</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>

        <!-- Form DB -->
        <h6 class="fw-semibold mb-3">Cấu hình Database</h6>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-semibold">DB Host</label>
            <input type="text" name="db_host" class="form-control"
                   value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">DB Username</label>
            <input type="text" name="db_user" class="form-control"
                   value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">DB Password</label>
            <input type="password" name="db_pass" class="form-control"
                   placeholder="(để trống nếu không có password)">
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Tên Database</label>
            <input type="text" name="db_name" class="form-control"
                   value="<?= htmlspecialchars($_POST['db_name'] ?? 'cleaning_app') ?>" required>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-semibold">
            ⚡ Tạo Database &amp; Hoàn tất cài đặt
          </button>
        </form>

      <?php endif; ?>

    </div>
  </div>
  <p class="text-center text-muted small mt-3">
    Sau khi cài đặt xong hãy <strong>xóa file setup.php</strong>
  </p>
</div>
</body>
</html>