'use strict';

// ══════════════════════════════════════════════════════════════
//  ĐỊNH DẠNG SỐ KIỂU VIỆT NAM
//  Dấu .  = ngăn cách hàng nghìn  →  1.234.567
//  Dấu ,  = thập phân             →  1.234,50
// ══════════════════════════════════════════════════════════════

/**
 * Số nguyên → chuỗi VN:  3690 → "3.690"
 */
function formatVN(n) {
    n = Math.round(parseFloat(n) || 0);
    return n.toLocaleString('vi-VN');
}

/**
 * Số thực → chuỗi VN có thập phân: 3690.5 → "3.690,50"
 */
function formatVNDec(n, decimals) {
    decimals = decimals !== undefined ? decimals : 2;
    return (parseFloat(n) || 0).toLocaleString('vi-VN', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

/**
 * Parse chuỗi số VN → float thuần
 * "3.690"       → 3690
 * "1.234.567"   → 1234567
 * "1.234,50"    → 1234.5
 */
function parseVN(str) {
    if (!str) return 0;
    str = String(str).trim();
    str = str.replace(/\./g, '');   // bỏ dấu . ngăn nghìn
    str = str.replace(',', '.');    // đổi , thập phân → .
    return parseFloat(str) || 0;
}

/**
 * Format input đang gõ → kiểu VN
 * Chỉ giữ chữ số + tối đa 1 dấu phẩy (thập phân)
 */
function formatNumInput(inp) {
    let raw      = inp.value.replace(/[^\d,]/g, '');
    let parts    = raw.split(',');
    let intPart  = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    inp.value    = parts.length > 1
        ? intPart + ',' + parts[1].substring(0, 2)
        : intPart;
}

/**
 * Gắn sự kiện format VN cho tất cả .num-input trong context
 * Gọi lại khi thêm row động
 */
function initNumInputs(context) {
    context.querySelectorAll('.num-input').forEach(function (inp) {
        if (inp.dataset.numInited) return;
        inp.dataset.numInited = '1';

        inp.addEventListener('input', function () {
            formatNumInput(this);
        });
        inp.addEventListener('blur', function () {
            formatNumInput(this);
        });

        // Format giá trị ban đầu nếu có
        if (inp.value) formatNumInput(inp);
    });
}

// ══════════════════════════════════════════════════════════════
//  KHỞI TẠO KHI DOM READY
// ══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {

    // ── DataTables ──────────────────────────────────────────
    document.querySelectorAll('[id^="dt"]').forEach(function (el) {
        if (typeof $ !== 'undefined' && $.fn && $.fn.DataTable) {
            $(el).DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/vi.json'
                },
                pageLength: 25,
                order: [],
                columnDefs: [{ orderable: false, targets: -1 }]
            });
        }
    });

    // ── Input số VN ────────────────────────────────────────
    initNumInputs(document);

    // ── Auto uppercase ─────────────────────────────────────
    document.querySelectorAll('input.text-uppercase').forEach(function (inp) {
        inp.addEventListener('input', function () {
            let pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });

    // ── Tooltip Bootstrap ──────────────────────────────────
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // ── Flash message tự đóng sau 4s ──────────────────────
    setTimeout(function () {
        document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
            let bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        });
    }, 4000);

    // ── Confirm submit ─────────────────────────────────────
    document.querySelectorAll('.confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let msg = form.dataset.confirm || 'Bạn có chắc chắn?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

});