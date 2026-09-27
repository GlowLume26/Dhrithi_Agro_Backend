<?php
// Test Gmail SMTP — run via: http://localhost/drithi-agro-backend/test_email.php?to=your@email.com
// DELETE THIS FILE after testing!

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/helpers.php';

$to  = $_GET['to'] ?? '';
$otp = '123456';

echo "<pre>\n";
echo "SMTP_HOST : " . SMTP_HOST . "\n";
echo "SMTP_PORT : " . SMTP_PORT . "\n";
echo "SMTP_USER : " . (SMTP_USER ?: '❌ EMPTY — fill in .env') . "\n";
echo "SMTP_PASS : " . (SMTP_PASS ? str_repeat('*', strlen(SMTP_PASS)) : '❌ EMPTY — fill in .env') . "\n\n";

if (!$to) {
    echo "Usage: http://localhost/drithi-agro-backend/test_email.php?to=your@email.com\n";
    exit;
}

if (!SMTP_USER || !SMTP_PASS) {
    echo "❌ SMTP_USER or SMTP_PASS is empty in .env\n";
    echo "Fill them in and try again.\n";
    exit;
}

echo "Sending test OTP to: $to ...\n";
$result = OtpHelper::sendEmail($to, $otp);
echo $result ? "✅ Email sent successfully! Check your inbox.\n" : "❌ Email failed. Check error log.\n";
echo "</pre>";
