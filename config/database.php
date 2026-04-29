<?php
declare(strict_types=1);

define('DB_HOST',    'localhost');
define('DB_NAME',    'liprolog_cleaning_app');
define('DB_USER',    'liprolog_cleaning_app');  // ← thường cùng tên với DB trên cPanel
define('DB_PASS',    'cleaning_app');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}