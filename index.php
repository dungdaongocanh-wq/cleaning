<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
header('Location: ' . BASE_URL . '/dashboard.php');
exit;