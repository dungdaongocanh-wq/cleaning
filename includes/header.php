<?php
declare(strict_types=1);
if (!isset($pageTitle)) $pageTitle = APP_NAME;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<style>
  body { background-color: #f4f6f9; }
  .sidebar { min-height: 100vh; background: #1e2a3a; }
  .sidebar .nav-link { color: #adb5bd; border-radius: 6px; }
  .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,.1); color: #fff; }
  .sidebar .nav-link i { width: 20px; }
  .main-content { padding: 24px; }
</style>
</head>
<body>
<div class="d-flex">
