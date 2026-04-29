<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// ── Tham số ──────────────────────────────────────────────────
$fMonth     = $_GET['month']       ?? date('Y-m');
$customerId = (int)($_GET['customer_id'] ?? 0);
$type       = $_GET['type']        ?? 'report'; // report | invoice

if (!$customerId) {
    setFlash('danger', 'Vui lòng chọn khách hàng.');
    redirect(BASE_URL . '/reports/index.php');
}

[$mStart, $mEnd] = monthRange($fMonth);

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    setFlash('danger', 'Không tìm thấy khách hàng.');
    redirect(BASE_URL . '/reports/index.php');
}

// ── Query chi tiết theo ngày (dùng cho cả report & invoice) ──
$sql = "
    SELECT
        eh.entry_date,
        cm.model_code,
        cm.model_name,
        cm.unit,
        el.unit_price_snapshot,
        SUM(el.qty)        AS total_qty,
        SUM(el.line_total) AS total_amount
    FROM entry_lines el
    JOIN entry_headers   eh ON eh.id  = el.entry_header_id
    JOIN customer_models cm ON cm.id  = el.customer_model_id
    WHERE eh.customer_id = ?
      AND eh.entry_date BETWEEN ? AND ?
    GROUP BY eh.entry_date, cm.id, el.unit_price_snapshot
    ORDER BY eh.entry_date, cm.sort_order, cm.model_code
";
$stmt2 = $pdo->prepare($sql);
$stmt2->execute([$customerId, $mStart, $mEnd]);
$rows = $stmt2->fetchAll();

$grandTotal = (float)array_sum(array_column($rows, 'total_amount'));
$invoiceNo  = generateInvoiceNumber($customer['code'], $mEnd);

// ── Spreadsheet init ─────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle($type === 'invoice' ? 'Hóa đơn' : 'Bảng kê');

// ── Màu sắc ──────────────────────────────────────────────────
$COLOR_HEADER_BG  = '17375E'; // xanh navy đậm — header bảng
$COLOR_HEADER_FG  = 'FFFFFF'; // chữ trắng
$COLOR_QTY_BG     = 'FCE4D6'; // cam nhạt — cột số lượng
$COLOR_TOTAL_BG   = 'FFF2CC'; // vàng nhạt — dòng tổng
$COLOR_TITLE      = '17375E'; // tiêu đề
$COLOR_SECTION    = '1F3864'; // BÊN MUA / BÊN BÁN

// ── Độ rộng cột (8 cột: A-H) ─────────────────────────────────
$sheet->getColumnDimension('A')->setWidth(5.5);   // STT
$sheet->getColumnDimension('B')->setWidth(13);    // Ngày tháng
$sheet->getColumnDimension('C')->setWidth(24);    // Model
$sheet->getColumnDimension('D')->setWidth(40);    // Tên hàng
$sheet->getColumnDimension('E')->setWidth(7);     // ĐV
$sheet->getColumnDimension('F')->setWidth(10);    // Số lượng
$sheet->getColumnDimension('G')->setWidth(12);    // Đơn giá
$sheet->getColumnDimension('H')->setWidth(14);    // Thành tiền

$lastCol = 'H';
$numFmt  = '#,##0';

// ════════════════════════════════════════════════════════════
// HELPER: set style nhanh
// ════════════════════════════════════════════════════════════
function S(object $sheet, string $range): \PhpOffice\PhpSpreadsheet\Style\Style
{
    return $sheet->getStyle($range);
}

function setBold(object $sheet, string $range, bool $bold = true): void
{
    $sheet->getStyle($range)->getFont()->setBold($bold);
}

function setBg(object $sheet, string $range, string $rgb): void
{
    $sheet->getStyle($range)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB($rgb);
}

function setAlign(object $sheet, string $range, string $h, string $v = 'center'): void
{
    $sheet->getStyle($range)->getAlignment()
        ->setHorizontal($h)->setVertical($v);
}

function setBorder(object $sheet, string $range, string $style = Border::BORDER_THIN): void
{
    $sheet->getStyle($range)->getBorders()
        ->getAllBorders()->setBorderStyle($style);
}

function setColor(object $sheet, string $range, string $rgb): void
{
    $sheet->getStyle($range)->getFont()->getColor()->setRGB($rgb);
}

function mergeSet(object $sheet, string $range, mixed $value): void
{
    $sheet->mergeCells($range);
    // Lấy ô đầu tiên
    preg_match('/^([A-Z]+)(\d+)/', $range, $m);
    $sheet->setCellValue($m[1] . $m[2], $value);
}

// ════════════════════════════════════════════════════════════
// PHẦN 1: LOGO + TIÊU ĐỀ (rows 1-3)
// ════════════════════════════════════════════════════════════
$r = 1;

// Logo (A1:B3) — floating image
$logoPath = __DIR__ . '/../assets/images/logo.png';
if (file_exists($logoPath)) {
    $drawing = new Drawing();
    $drawing->setPath($logoPath);
    $drawing->setHeight(72);
    $drawing->setCoordinates('A1');
    $drawing->setOffsetX(4);
    $drawing->setOffsetY(4);
    $drawing->setWorksheet($sheet);
}
// Placeholder nếu không có logo
$sheet->mergeCells("A1:B3");
$sheet->getRowDimension(1)->setRowHeight(24);
$sheet->getRowDimension(2)->setRowHeight(24);
$sheet->getRowDimension(3)->setRowHeight(24);

// Tên ngắn công ty dưới logo (chỉ hiện khi không có logo)
if (!file_exists($logoPath)) {
    $sheet->setCellValue('A1', COMPANY_SHORT);
    S($sheet, 'A1')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1A7431');
    setAlign($sheet, 'A1', Alignment::HORIZONTAL_CENTER);
}

// Tiêu đề chính
$sheet->mergeCells("C1:{$lastCol}1");
$sheet->mergeCells("C2:{$lastCol}2");
$sheet->mergeCells("C3:{$lastCol}3");

$custCodeUpper = strtoupper($customer['code']);
$titleText     = "BẢNG KÊ CHI TIẾT XUẤT HÀNG $custCodeUpper";

$sheet->setCellValue('C1', '');
$sheet->setCellValue('C2', $titleText);

// Ngày xuất hóa đơn = ngày cuối tháng
$dateEnd = DateTime::createFromFormat('Y-m-d', $mEnd);
$ngayText = 'Số ' . $invoiceNo . '   Ngày ' .
            $dateEnd->format('d') . ' tháng ' .
            $dateEnd->format('m') . ' năm ' .
            $dateEnd->format('Y');
$sheet->setCellValue('C3', $ngayText);

// Style tiêu đề
S($sheet, 'C2')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB($COLOR_TITLE);
setAlign($sheet, 'C2', Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);

S($sheet, 'C3')->getFont()->setItalic(true)->setSize(11)->getColor()->setRGB('444444');
setAlign($sheet, 'C3', Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);

$r = 4;
// Row kẻ đường phân cách
$sheet->getRowDimension($r)->setRowHeight(4);
setBg($sheet, "A{$r}:{$lastCol}{$r}", $COLOR_HEADER_BG);
$r++;

// ════════════════════════════════════════════════════════════
// PHẦN 2: BÊN MUA
// ════════════════════════════════════════════════════════════
$sheet->getRowDimension($r)->setRowHeight(18);
$sheet->mergeCells("A{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", 'BÊN MUA: ' . strtoupper($customer['name']));
S($sheet, "A{$r}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB($COLOR_SECTION);
$r++;

$buyerRows = [
    ['Địa chỉ: ', $customer['address']       ?? ''],
    ['Tel: ',     $customer['contact_phone']  ?? ''],
    ['Mã số thuế: ', $customer['tax_code']    ?? ''],
    ['Tại Ngân hàng: ', $customer['bank_name']    ?? ''],
    ['Số tài khoản: ',  $customer['bank_account'] ?? ''],
];
foreach ($buyerRows as [$label, $val]) {
    $sheet->getRowDimension($r)->setRowHeight(16);
    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
    $sheet->setCellValue("A{$r}", $label . $val);
    S($sheet, "A{$r}")->getFont()->setSize(10);
    S($sheet, "A{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $r++;
}

$r++; // blank

// ════════════════════════════════════════════════════════════
// PHẦN 3: BÊN BÁN
// ════════════════════════════════════════════════════════════
$sheet->getRowDimension($r)->setRowHeight(18);
$sheet->mergeCells("A{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", 'BÊN BÁN: ' . strtoupper(COMPANY_NAME));
S($sheet, "A{$r}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB($COLOR_SECTION);
$r++;

$sellerRows = [
    ['Địa chỉ: ',    COMPANY_ADDRESS],
    ['Tel: ',        COMPANY_PHONE],
    ['Mã số thuế: ', COMPANY_TAX],
    ['Ngân hàng: ',  COMPANY_BANK],
    ['Số tài khoản: ', COMPANY_ACCOUNT],
];
foreach ($sellerRows as [$label, $val]) {
    $sheet->getRowDimension($r)->setRowHeight(16);
    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
    $sheet->setCellValue("A{$r}", $label . $val);
    S($sheet, "A{$r}")->getFont()->setSize(10);
    S($sheet, "A{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $r++;
}

$r++; // blank

// ════════════════════════════════════════════════════════════
// PHẦN 4: SUBTITLE DATE RANGE
// ════════════════════════════════════════════════════════════
$dStart = DateTime::createFromFormat('Y-m-d', $mStart);
$dEnd   = DateTime::createFromFormat('Y-m-d', $mEnd);
$rangeText = sprintf(
    'Bảng kê chi tiết giao hàng các loại từ ngày %s - %s với đơn giá như sau:',
    $dStart->format('d.m.Y'),
    $dEnd->format('d.m.Y')
);
$sheet->mergeCells("A{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", $rangeText);
S($sheet, "A{$r}")->getFont()->setItalic(true)->setBold(true)->setSize(10);
setAlign($sheet, "A{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
$sheet->getRowDimension($r)->setRowHeight(18);
$r++;

// ════════════════════════════════════════════════════════════
// PHẦN 5: BẢNG DỮ LIỆU
// ════════════════════════════════════════════════════════════
$tableHeaderRow = $r;

// Header bảng
$headers = ['STT', 'Ngày tháng', 'Model', 'Tên hàng', 'ĐV', 'Số lượng', 'Đơn giá', 'Thành tiền'];
$cols    = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

foreach ($headers as $i => $hdr) {
    $sheet->setCellValue($cols[$i] . $r, $hdr);
}

$headerRange = "A{$r}:{$lastCol}{$r}";
setBg($sheet,    $headerRange, $COLOR_HEADER_BG);
setColor($sheet, $headerRange, $COLOR_HEADER_FG);
setBold($sheet,  $headerRange);
setBorder($sheet,$headerRange, Border::BORDER_THIN);
S($sheet, $headerRange)->getFont()->setSize(10);
S($sheet, $headerRange)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$sheet->getRowDimension($r)->setRowHeight(22);

// AutoFilter
$sheet->setAutoFilter("A{$r}:{$lastCol}{$r}");

$r++;
$dataStart = $r;

// ── Data rows ─────────────────────────────────────────────
foreach ($rows as $i => $row) {
    $bgRow = ($i % 2 === 0) ? 'FFFFFF' : 'F2F7FF';

    $sheet->setCellValue("A{$r}", $i + 1);
    $sheet->setCellValue("B{$r}", date('d/m/Y', strtotime($row['entry_date'])));
    $sheet->setCellValue("C{$r}", $row['model_code']);
    $sheet->setCellValue("D{$r}", $row['model_name'] ?: $row['model_code']);
    $sheet->setCellValue("E{$r}", $row['unit']);
    $sheet->setCellValue("F{$r}", (float)$row['total_qty']);
    $sheet->setCellValue("G{$r}", (float)$row['unit_price_snapshot']);
    $sheet->setCellValue("H{$r}", (float)$row['total_amount']);

    // Background toàn dòng
    setBg($sheet, "A{$r}:{$lastCol}{$r}", $bgRow);

    // Cột Số lượng — màu cam nhạt đặc trưng
    setBg($sheet, "F{$r}", $COLOR_QTY_BG);

    // Border
    setBorder($sheet, "A{$r}:{$lastCol}{$r}", Border::BORDER_THIN);

    // Alignment
    setAlign($sheet, "A{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
    setAlign($sheet, "B{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
    setAlign($sheet, "C{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
    setAlign($sheet, "D{$r}", Alignment::HORIZONTAL_LEFT,   Alignment::VERTICAL_CENTER);
    setAlign($sheet, "E{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
    setAlign($sheet, "F{$r}", Alignment::HORIZONTAL_RIGHT,  Alignment::VERTICAL_CENTER);
    setAlign($sheet, "G{$r}", Alignment::HORIZONTAL_RIGHT,  Alignment::VERTICAL_CENTER);
    setAlign($sheet, "H{$r}", Alignment::HORIZONTAL_RIGHT,  Alignment::VERTICAL_CENTER);

    // Format số
    $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode($numFmt);
    $sheet->getStyle("G{$r}")->getNumberFormat()->setFormatCode($numFmt);
    $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode($numFmt);

    // Font size
    S($sheet, "A{$r}:{$lastCol}{$r}")->getFont()->setSize(10);

    $sheet->getRowDimension($r)->setRowHeight(20);
    $r++;
}

// ── Dòng TỔNG CỘNG ───────────────────────────────────────
$sheet->mergeCells("A{$r}:E{$r}");
$sheet->setCellValue("A{$r}", 'TỔNG CỘNG');
$sheet->setCellValue("F{$r}", (float)array_sum(array_column($rows, 'total_qty')));
$sheet->setCellValue("G{$r}", '');
$sheet->setCellValue("H{$r}", $grandTotal);

setBg($sheet,    "A{$r}:{$lastCol}{$r}", $COLOR_TOTAL_BG);
setBold($sheet,  "A{$r}:{$lastCol}{$r}");
setBorder($sheet,"A{$r}:{$lastCol}{$r}", Border::BORDER_MEDIUM);
setAlign($sheet, "A{$r}", Alignment::HORIZONTAL_RIGHT,  Alignment::VERTICAL_CENTER);
setAlign($sheet, "F{$r}", Alignment::HORIZONTAL_RIGHT,  Alignment::VERTICAL_CENTER);
setAlign($sheet, "H{$r}", Alignment::HORIZONTAL_RIGHT,  Alignment::VERTICAL_CENTER);
$sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode($numFmt);
$sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode($numFmt);
S($sheet, "A{$r}:{$lastCol}{$r}")->getFont()->setSize(11);
$sheet->getRowDimension($r)->setRowHeight(22);
$r += 2;

// ── Số tiền bằng chữ ─────────────────────────────────────
$sheet->mergeCells("A{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", 'Số tiền bằng chữ: ' . numberToWords($grandTotal) . ' đồng chẵn./');
S($sheet, "A{$r}")->getFont()->setItalic(true)->setSize(10);
$sheet->getRowDimension($r)->setRowHeight(18);
$r += 2;

// ── Chữ ký ───────────────────────────────────────────────
$sheet->mergeCells("A{$r}:C{$r}");
$sheet->mergeCells("F{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", 'ĐẠI DIỆN BÊN MUA');
$sheet->setCellValue("F{$r}", 'ĐẠI DIỆN BÊN BÁN');
setBold($sheet, "A{$r}");
setBold($sheet, "F{$r}");
setAlign($sheet, "A{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
setAlign($sheet, "F{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
$r++;

$sheet->mergeCells("A{$r}:C{$r}");
$sheet->mergeCells("F{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", '(Ký, ghi rõ họ tên)');
$sheet->setCellValue("F{$r}", '(Ký, ghi rõ họ tên)');
S($sheet, "A{$r}")->getFont()->setItalic(true)->getColor()->setRGB('888888');
S($sheet, "F{$r}")->getFont()->setItalic(true)->getColor()->setRGB('888888');
setAlign($sheet, "A{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
setAlign($sheet, "F{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
$r += 5;

$sheet->mergeCells("A{$r}:C{$r}");
$sheet->mergeCells("F{$r}:{$lastCol}{$r}");
$sheet->setCellValue("A{$r}", $customer['contact_name'] ?? '');
$sheet->setCellValue("F{$r}", 'Giám đốc');
setBold($sheet, "A{$r}");
setBold($sheet, "F{$r}");
setAlign($sheet, "A{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);
setAlign($sheet, "F{$r}", Alignment::HORIZONTAL_CENTER, Alignment::VERTICAL_CENTER);

// ── Print settings ────────────────────────────────────────
$sheet->getPageSetup()
    ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
    ->setPaperSize(PageSetup::PAPERSIZE_A4)
    ->setFitToPage(true)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getPageMargins()
    ->setTop(0.5)->setRight(0.5)->setBottom(0.5)->setLeft(0.5);

// Print area & repeat header
$sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$r}");
$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($tableHeaderRow, $tableHeaderRow);

// ── Xuất file ─────────────────────────────────────────────
$filename = 'BangKe_' . $custCodeUpper . '_' . str_replace('-', '', $fMonth) . '.xlsx';

$spreadsheet->getProperties()
    ->setCreator(COMPANY_NAME)
    ->setTitle($filename);

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
exit;

// ════════════════════════════════════════════════════════════
// Helper: Số thành chữ tiếng Việt
// ════════════════════════════════════════════════════════════

 