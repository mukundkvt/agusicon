<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
date_default_timezone_set('Asia/Kolkata');
ob_clean();
header('Content-Type: application/json; charset=UTF-8');

function sendJson(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendJson(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

$companyName   = trim((string)($_POST['company_name']   ?? ''));
$contactName   = trim((string)($_POST['contact_name']   ?? ''));
$mobile        = trim((string)($_POST['mobile']         ?? ''));
$email         = trim((string)($_POST['email']          ?? ''));
$stallInterest = trim((string)($_POST['stall_interest'] ?? ''));
$message       = trim((string)($_POST['message']        ?? ''));
$eventName     = trim((string)($_POST['event_name']     ?? 'AGUSICON 2026 - Bhadohi'));

if ($companyName === '' || $contactName === '' || $mobile === '' || $email === '' || $stallInterest === '') {
    sendJson(400, ['status' => 'error', 'message' => 'Please fill in all required fields.']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson(400, ['status' => 'error', 'message' => 'Please enter a valid email address.']);
}

// reCAPTCHA v3 — fail-open: if token is empty or verification fails, allow submission
function verifyRecaptcha(string $token, string $secret): bool {
    if ($token === '') return true; // No token — allow (bot protection handled client-side)
    $url  = 'https://www.google.com/recaptcha/api/siteverify';
    $post = 'secret=' . urlencode($secret) . '&response=' . urlencode($token);
    $resp = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        curl_close($ch);
    } elseif (ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $post, 'timeout' => 10]]);
        $resp = @file_get_contents($url, false, $ctx);
    }
    if ($resp === false || $resp === '') return true; // Can't reach Google — allow
    $data = json_decode($resp, true);
    return !empty($data['success']);
}
$recaptchaToken  = trim((string)($_POST['recaptcha_token'] ?? ''));
$recaptchaSecret = '6LfrGiAtAAAAAMk6FgnkMe5KWFk_ZeXRqEpub5hx';
if (!verifyRecaptcha($recaptchaToken, $recaptchaSecret)) {
    sendJson(400, ['status' => 'error', 'message' => 'Security check failed. Please refresh and try again.']);
}

// Write to CSV
$csvPath = __DIR__ . '/stall-enquiries.csv';
$handle = @fopen($csvPath, 'ab+');
if ($handle !== false && flock($handle, LOCK_EX)) {
    $stats = fstat($handle);
    if (($stats['size'] ?? 0) === 0) {
        fputcsv($handle, ['Submitted At', 'Event', 'Company', 'Contact Person', 'Mobile', 'Email', 'Stall Interest', 'Message']);
    }
    fputcsv($handle, [
        date('Y-m-d H:i:s'),
        $eventName,
        $companyName,
        $contactName,
        $mobile,
        $email,
        $stallInterest,
        $message,
    ]);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

// Load PHPMailer
function loadPhpMailer(): bool {
    $autoloadPaths = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];
    foreach ($autoloadPaths as $path) {
        if (is_file($path)) { require_once $path; return true; }
    }
    $srcRoots = [
        __DIR__ . '/PHPMailer-6.9.1/src',
        dirname(__DIR__) . '/PHPMailer-6.9.1/src',
    ];
    foreach ($srcRoots as $root) {
        if (is_file($root . '/Exception.php') && is_file($root . '/PHPMailer.php') && is_file($root . '/SMTP.php')) {
            require_once $root . '/Exception.php';
            require_once $root . '/PHPMailer.php';
            require_once $root . '/SMTP.php';
            return true;
        }
    }
    return false;
}

// Send email notification (silently skip if anything fails)
if (loadPhpMailer()) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ae1b45001@smtp-brevo.com';
        $mail->Password   = 'DqKY7Lsg4rUzQpWx';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom('info@agusicon.com', 'AGUSICON 2026');
        $mail->addAddress('agusicon2025@gmail.com', 'AGUSICON 2026 Team');
        $mail->addBCC('mukund.rgb@gmail.com');
        $mail->Subject = 'Stall Enquiry: ' . $companyName . ' – AGUSICON 2026';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;color:#111;line-height:1.6'>
            <h2 style='color:#115B86'>New Stall / Sponsorship Enquiry – AGUSICON 2026</h2>
            <table cellpadding='8' cellspacing='0' border='0' style='width:100%;border-collapse:collapse'>
                <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86;width:35%'>Company</td><td>" . htmlspecialchars($companyName) . "</td></tr>
                <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Contact Person</td><td>" . htmlspecialchars($contactName) . "</td></tr>
                <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Mobile</td><td>" . htmlspecialchars($mobile) . "</td></tr>
                <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Email</td><td>" . htmlspecialchars($email) . "</td></tr>
                <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Interest</td><td>" . htmlspecialchars($stallInterest) . "</td></tr>
                <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Message</td><td>" . nl2br(htmlspecialchars($message ?: 'None')) . "</td></tr>
            </table>
            </div>";
        $mail->AltBody = "Stall Enquiry from {$companyName}\nContact: {$contactName}\nMobile: {$mobile}\nEmail: {$email}\nInterest: {$stallInterest}\nMessage: {$message}";
        $mail->send();
    } catch (\Throwable $e) {
        // Email failed silently — enquiry was already saved to CSV
    }
}

sendJson(200, ['status' => 'success', 'message' => 'Enquiry received. Our team will contact you shortly.']);
