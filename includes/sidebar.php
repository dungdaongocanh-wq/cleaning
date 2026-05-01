<?php
$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
$user = currentUser();
?>
<!-- Sidebar -->
<nav class="sidebar d-flex flex-column p-3" style="width:220px;min-width:220px">
  <a href="<?= BASE_URL ?>/dashboard.php" class="text-white text-decoration-none mb-4 d-block">
    <strong><i class="bi bi-stars me-1"></i><?= APP_NAME ?></strong>
  </a>

  <ul class="nav flex-column gap-1">
    <li class="nav-item">
      <a href="<?= BASE_URL ?>/dashboard.php"
         class="nav-link <?= $currentFile === 'dashboard.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a href="<?= BASE_URL ?>/entries/index.php"
         class="nav-link <?= $currentDir === 'entries' ? 'active' : '' ?>">
        <i class="bi bi-table"></i> Phiếu nhập
      </a>
    </li>
    <li class="nav-item">
      <a href="<?= BASE_URL ?>/customers/index.php"
         class="nav-link <?= $currentDir === 'customers' ? 'active' : '' ?>">
        <i class="bi bi-building"></i> Khách hàng
      </a>
    </li>
    <li class="nav-item">
      <a href="<?= BASE_URL ?>/services/index.php"
         class="nav-link <?= $currentDir === 'services' ? 'active' : '' ?>">
        <i class="bi bi-tags"></i> Mã hàng & Giá
      </a>
    </li>
    <?php if (isAdmin()): ?>
    <li class="nav-item">
      <a href="<?= BASE_URL ?>/reports/index.php"
         class="nav-link <?= $currentDir === 'reports' ? 'active' : '' ?>">
        <i class="bi bi-bar-chart"></i> Báo cáo
      </a>
    </li>
    <li class="nav-item">
      <a href="<?= BASE_URL ?>/users/index.php"
         class="nav-link <?= $currentDir === 'users' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Người dùng
      </a>
    </li>
    <?php endif; ?>
  </ul>

  <div class="mt-auto pt-3 border-top border-secondary">
    <div class="text-white-50 small mb-2">
      <i class="bi bi-person-circle me-1"></i><?= e($user['full_name']) ?>
      <span class="badge bg-secondary ms-1"><?= e($user['role']) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="nav-link text-danger">
      <i class="bi bi-box-arrow-left"></i> Đăng xuất
    </a>
  </div>
</nav>

<!-- Main Content -->
<div class="main-content flex-grow-1">
