<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/bkav.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json; charset=utf-8');

// ── Đọc input ────────────────────────────────────────────────
$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$month      = trim($input['month']       ?? '');
$customerId = (int)($input['customer_id'] ?? 0);

if (!$month || !$customerId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu tháng hoặc khách hàng.']);
    exit;
}

// ── Lấy dữ liệu ─────────────────────────────────────────────
$pdo = getDB();
[$mStart, $mEnd] = monthRange($month);

$stmtC = $pdo->prepare("SELECT * FROM customers WHERE id=?");
$stmtC->execute([$customerId]);
$customer = $stmtC->fetch();
if (!$customer) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng.']);
    exit;
}

// Tổng hợp theo model
$sql = "
    SELECT cm.model_name, cm.unit,
           el.unit_price_snapshot,
           SUM(el.qty)        AS total_qty,
           SUM(el.line_total) AS total_amount
    FROM entry_lines el
    JOIN entry_headers   eh ON eh.id = el.entry_header_id
    JOIN customer_models cm ON cm.id = el.customer_model_id
    WHERE eh.customer_id = ? AND eh.entry_date BETWEEN ? AND ?
    GROUP BY cm.id, el.unit_price_snapshot
    ORDER BY cm.sort_order, cm.model_code
";
$stmtR = $pdo->prepare($sql);
$stmtR->execute([$customerId, $mStart, $mEnd]);
$rows = $stmtR->fetchAll();

if (!$rows) {
    echo json_encode(['success' => false, 'message' => 'Không có dữ liệu trong tháng này.']);
    exit;
}

$grandTotal = (float)array_sum(array_column($rows, 'total_amount'));
$invoiceNo  = generateInvoiceNumber($customer['code'], $mEnd);

// ── Build cấu trúc hóa đơn BKAV ─────────────────────────────
$itemList = [];
foreach ($rows as $row) {
    $itemList[] = [
        'ItemName'                    => $row['model_name'],
        'UnitName'                    => $row['unit'],
        'UnitPrice'                   => (float)$row['unit_price_snapshot'],
        'Quantity'                    => (float)$row['total_qty'],
        'ItemTotalAmountWithoutTax'   => (float)$row['total_amount'],
        'TaxPercentage'               => VAT_RATE * 100,
        'TaxAmount'                   => 0,
        'ItemTotalAmount'             => (float)$row['total_amount'],
        'IsIncreaseItem'              => true,
    ];
}

$vatAmount = round($grandTotal * VAT_RATE);

$invoiceData = [
    'CmdType' => 100,
    'Invoice' => [
        'InvoiceType'                   => BKAV_INVOICE_TYPE,
        'InvoiceSerial'                 => BKAV_INVOICE_SERIAL,
        'InvoiceIssuedDate'             => date('Y-m-d', strtotime($mEnd)) . 'T00:00:00',
        'CurrencyUnit'                  => 'VND',
        'ExchangeRate'                  => 1,
        'InvoiceNote'                   => $invoiceNo,
        'PaymentMethodName'             => BKAV_PAYMENT_METHOD,
        // Bên mua
        'BuyerName'                     => $customer['contact_name'] ?? '',
        'BuyerTaxCode'                  => $customer['tax_code']     ?? '',
        'BuyerUnitName'                 => $customer['name'],
        'BuyerAddress'                  => $customer['address']      ?? '',
        'BuyerBankAccount'              => $customer['bank_account'] ?? '',
        'BuyerBankName'                 => $customer['bank_name']    ?? '',
        // Bên bán
        'SellerBankAccount'             => COMPANY_ACCOUNT,
        'SellerBankName'                => COMPANY_BANK,
        // Danh sách hàng
        'InvoiceGroupItemList'          => $itemList,
        // Thuế
        'InvoiceTaxBreakdowns'          => [[
            'TaxPercentage' => VAT_RATE * 100,
            'TaxableAmount' => $grandTotal,
            'TaxAmount'     => $vatAmount,
        ]],
        // Tổng tiền
        'InvoiceTotalAmount'            => $grandTotal + $vatAmount,
        'InvoiceTotalAmountWithoutTax'  => $grandTotal,
        'InvoiceTotalTaxAmount'         => $vatAmount,
        'InvoiceTotalAmountInWords'     => numberToWordsVN($grandTotal + $vatAmount),
    ],
];

// ── Mã hóa & gửi lên BKAV ───────────────────────────────────
try {
    $jsonStr   = json_encode($invoiceData, JSON_UNESCAPED_UNICODE);
    $encrypted = bkavEncrypt($jsonStr);
    $soapResp  = bkavSoapCall($encrypted);

    if (!$soapResp) {
        echo json_encode(['success' => false, 'message' => 'Không nhận được phản hồi từ BKAV.']);
        exit;
    }

    // Parse SOAP XML → lấy kết quả
    $xml = @simplexml_load_string($soapResp);
    if (!$xml) {
        echo json_encode(['success' => false, 'message' => 'Phản hồi SOAP không hợp lệ.', 'raw' => substr($soapResp, 0, 1000)]);
        exit;
    }
    $xml->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
    $nodes = $xml->xpath('//*[local-name()="PublicPostInvoiceDataResult"]');

    if (empty($nodes)) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy kết quả trong phản hồi SOAP.', 'raw' => substr($soapResp, 0, 1000)]);
        exit;
    }

    $resultEncrypted = (string)$nodes[0];

    // Thử parse plain JSON trước (BKAV đôi khi trả plain JSON khi lỗi)
    $result = json_decode($resultEncrypted, true);

    // Nếu không phải plain JSON thì decrypt rồi parse
    if ($result === null) {
        $resultJson = bkavDecrypt($resultEncrypted);
        $result = json_decode($resultJson, true);
        if ($result === null) {
            echo json_encode([
                'success' => false,
                'message' => 'Không parse được JSON từ BKAV.',
                'raw'     => substr($resultJson, 0, 1000),
            ]);
            exit;
        }
    }

    $success   = isset($result['Status']) && $result['Status'] === 0;
    $errMsg    = $result['Message'] ?? ($result['Desc'] ?? 'Lỗi không xác định từ BKAV');
    $returnedInvoiceNo = $result['InvoiceNo'] ?? $result['InvoiceNumber'] ?? $invoiceNo;

    echo json_encode([
        'success'   => $success,
        'message'   => $success ? 'Xuất hóa đơn thành công!' : $errMsg,
        'invoiceNo' => $returnedInvoiceNo,
        'data'      => $result,
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
}

// ════════════════════════════════════════════════════════════
// Helper functions
// ════════════════════════════════════════════════════════════

function bkavEncrypt(string $data): string
{
    $key = base64_decode(BKAV_AES_KEY);
    $iv  = base64_decode(BKAV_AES_IV);
    $gz  = gzencode($data, 6);
    $enc = openssl_encrypt($gz, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode((string)$enc);
}

function bkavDecrypt(string $data): string
{
    $key = base64_decode(BKAV_AES_KEY);
    $iv  = base64_decode(BKAV_AES_IV);
    $raw = base64_decode($data);
    $dec = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($dec === false) {
        throw new \RuntimeException('bkavDecrypt: openssl_decrypt thất bại — kiểm tra BKAV_AES_KEY / BKAV_AES_IV. Raw (100 ký tự đầu): ' . substr($data, 0, 100));
    }
    $ungz = @gzdecode($dec);
    return $ungz !== false ? $ungz : $dec;
}

function bkavSoapCall(string $encryptedData): string
{
    $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <PublicPostInvoiceData xmlns="http://tempuri.org/">
      <PartnerGUID>' . BKAV_PARTNER_GUID . '</PartnerGUID>
      <CommandData>' . htmlspecialchars($encryptedData, ENT_XML1) . '</CommandData>
    </PublicPostInvoiceData>
  </soap:Body>
</soap:Envelope>';

    $ch = curl_init(BKAV_WS_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://tempuri.org/PublicPostInvoiceData"',
            'Content-Length: ' . strlen($xml),
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp     = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new \RuntimeException('cURL error: ' . $err);
    }
    if ($httpCode !== 200) {
        throw new \RuntimeException('BKAV trả về HTTP ' . $httpCode . '. Response: ' . substr((string)$resp, 0, 1000));
    }
    return (string)$resp;
}

/**
 * Số tiền bằng chữ tiếng Việt (đơn giản)
 */
function numberToWordsVN(float $amount): string
{
    $amount = (int)round($amount);
    if ($amount === 0) return 'Không đồng';

    $ones  = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $teens = ['mười', 'mười một', 'mười hai', 'mười ba', 'mười bốn', 'mười lăm',
              'mười sáu', 'mười bảy', 'mười tám', 'mười chín'];

    $readGroup = function (int $n) use ($ones, $teens): string {
        if ($n === 0) return '';
        $h = intdiv($n, 100);
        $t = intdiv($n % 100, 10);
        $u = $n % 10;
        $s = '';
        if ($h) $s .= $ones[$h] . ' trăm';
        if ($t === 1) {
            $s .= ($s ? ' ' : '') . $teens[$u];
        } elseif ($t > 1) {
            $s .= ($s ? ' ' : '') . $ones[$t] . ' mươi';
            if ($u === 1) $s .= ' mốt';
            elseif ($u === 5) $s .= ' lăm';
            elseif ($u)  $s .= ' ' . $ones[$u];
        } elseif ($h && $u) {
            $s .= ' lẻ ' . $ones[$u];
        } elseif ($u) {
            $s .= ($s ? ' ' : '') . $ones[$u];
        }
        return trim($s);
    };

    $billions  = intdiv($amount, 1_000_000_000);
    $millions  = intdiv($amount % 1_000_000_000, 1_000_000);
    $thousands = intdiv($amount % 1_000_000, 1_000);
    $remainder = $amount % 1_000;

    $parts = [];
    if ($billions)  $parts[] = $readGroup($billions)  . ' tỷ';
    if ($millions)  $parts[] = $readGroup($millions)  . ' triệu';
    if ($thousands) $parts[] = $readGroup($thousands) . ' nghìn';
    if ($remainder) $parts[] = $readGroup($remainder);

    return ucfirst(implode(' ', $parts)) . ' đồng';
}
