<?php
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// BKAV eHoaDon — Cấu hình tích hợp
// !! Điền thông tin thực tế do BKAV cấp vào đây !!
// !! KHÔNG commit file này khi đã có thông tin thật !!
// ══════════════════════════════════════════════════════════════

// URL Webservice
// Sandbox : 'https://wsdemo.ehoadon.vn/WSPublicEhoadon.asmx'
// Production: 'https://ws.ehoadon.vn/WSPublicEhoadon.asmx'
define('BKAV_WS_URL', 'https://wsdemo.ehoadon.vn/WSPublicEhoadon.asmx');

// PartnerGUID — do BKAV cấp (dạng UUID)
define('BKAV_PARTNER_GUID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

// PartnerToken dạng "AESKey_Base64:AESIV_Base64"
// Tách từ token BKAV cấp, VD: "54dSxxx...8F4=:+bRSxxx...wA=="
define('BKAV_AES_KEY', 'BASE64_ENCODED_32_BYTE_KEY_HERE');
define('BKAV_AES_IV',  'BASE64_ENCODED_16_BYTE_IV_HERE');

// Ký hiệu mẫu số / ký hiệu hóa đơn — xem trong phần mềm BKAV
// VD: 'C23TAA' hoặc '1/001'
define('BKAV_INVOICE_SERIAL', '1/001');

// Loại hóa đơn (1 = hóa đơn GTGT)
define('BKAV_INVOICE_TYPE', 1);

// Hình thức thanh toán
define('BKAV_PAYMENT_METHOD', 'Chuyển khoản');
