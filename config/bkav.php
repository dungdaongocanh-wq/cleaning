<?php
declare(strict_types=1);

// ══════════════════════════════════════════════════════════════
// BKAV eHoaDon — Cấu hình tích hợp
// !! KHÔNG public file này — thêm vào .gitignore sau khi dùng !!
// ══════════════════════════════════════════════════════════════

// URL Webservice
// Sandbox   : 'https://wsdemo.ehoadon.vn/WSPublicEhoadon.asmx'
// Production: 'https://ws.ehoadon.vn/WSPublicEhoadon.asmx'
define('BKAV_WS_URL', 'https://ws.ehoadon.vn/WSPublicEhoadon.asmx');

// PartnerGUID — do BKAV cấp
define('BKAV_PARTNER_GUID', '5a11f81a-1953-419d-ae9c-e0768b7f4d37');

// PartnerToken: "AESKey_Base64:AESIV_Base64"
// Token gốc: IjPFS9lhAijf6wbKwJD4leTwiQ+DjLxNVNVRxF9HI6o=:ptOjlZBdVfvVXFoap1UjtQ==
define('BKAV_AES_KEY', 'IjPFS9lhAijf6wbKwJD4leTwiQ+DjLxNVNVRxF9HI6o=');
define('BKAV_AES_IV',  'ptOjlZBdVfvVXFoap1UjtQ==');

// Ký hiệu hóa đơn — Mẫu số: 1-C26TYY, Ký hiệu: C26TYY
define('BKAV_INVOICE_SERIAL', 'C26TYY');

// Loại hóa đơn (1 = Hóa đơn GTGT — khớp với Mẫu số 1-C26TYY)
define('BKAV_INVOICE_TYPE', 1);

// Hình thức thanh toán
define('BKAV_PAYMENT_METHOD', 'Chuyển khoản');
