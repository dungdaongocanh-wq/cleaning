</div><!-- /.main-content -->
</div><!-- /.d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
// Khởi tạo DataTable cho bảng có class .datatable hoặc id bắt đầu bằng dt
$(function () {
  $('[id^=dt], .datatable').each(function () {
    if (!$.fn.DataTable.isDataTable(this)) {
      $(this).DataTable({
        language: {
          url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/vi.json'
        },
        pageLength: 25,
        order: []
      });
    }
  });
});
</script>
</body>
</html>
