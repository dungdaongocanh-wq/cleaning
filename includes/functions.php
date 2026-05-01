<?php
declare(strict_types=1);

/**
 * Escape HTML output
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format số tiền VNĐ
 */
function formatVND(float $amount): string
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Trả về [ngày_đầu, ngày_cuối] của tháng
 * @param string $month  'Y-m'  vd: '2026-05'
 * @return array{string, string}
 */
function monthRange(string $month): array
{
    $start = $month . '-01';
    $end   = date('Y-m-t', strtotime($start));
    return [$start, $end];
}
