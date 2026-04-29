/* ── Cleaning App — Global JS ──────────────────────────── */
'use strict';

document.addEventListener('DOMContentLoaded', function () {

    // ── DataTables init (tất cả bảng có id dt*) ─────────────
    document.querySelectorAll('[id^="dt"]').forEach(function (el) {
        if ($.fn.DataTable) {
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

    // ── Số tiền: tự động format khi nhập vào .num-input ─────
    document.querySelectorAll('.num-input').forEach(function (inp) {
        inp.addEventListener('input', function () {
            let raw = this.value.replace(/[^\d]/g, '');
            if (raw) this.value = parseInt(raw, 10).toLocaleString('vi-VN');
        });
        inp.addEventListener('blur', function () {
            let raw = this.value.replace(/[^\d]/g, '');
            if (raw) this.value = parseInt(raw, 10).toLocaleString('vi-VN');
        });
    });

    // ── Auto-uppercase cho .text-uppercase ───────────────────
    document.querySelectorAll('input.text-uppercase').forEach(function (inp) {
        inp.addEventListener('input', function () {
            let pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });

    // ── Tooltip Bootstrap init ────────────────────────────────
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // ── Flash message auto-hide sau 4 giây ───────────────────
    setTimeout(function () {
        document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
            let bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        });
    }, 4000);

    // ── Confirm trước khi submit form có class confirm-form ──
    document.querySelectorAll('.confirm-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            let msg = form.dataset.confirm || 'Bạn có chắc chắn?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

});

// ── Global helper: format số VN ─────────────────────────────
function formatVN(n) {
    return Math.round(parseFloat(n) || 0).toLocaleString('vi-VN');
}