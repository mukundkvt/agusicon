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

$name   = trim((string)($_POST['name']        ?? ''));
$mobile = trim((string)($_POST['mobile']      ?? ''));
$email  = trim((string)($_POST['email']       ?? ''));
$module = trim((string)($_POST['module']      ?? ''));
$amount = trim((string)($_POST['amount']      ?? ''));
$payRef = trim((string)($_POST['payment_ref'] ?? ''));

if ($name === '' || $mobile === '' || $email === '' || $module === '' || $payRef === '') {
    sendJson(400, ['status' => 'error', 'message' => 'Please fill in all required fields.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson(400, ['status' => 'error', 'message' => 'Please enter a valid email address.']);
}

// Proof upload
if (!isset($_FILES['proof']) || $_FILES['proof']['error'] === UPLOAD_ERR_NO_FILE || empty($_FILES['proof']['name'])) {
    sendJson(400, ['status' => 'error', 'message' => 'Please upload your payment screenshot.']);
}
if ($_FILES['proof']['error'] !== UPLOAD_ERR_OK) {
    sendJson(400, ['status' => 'error', 'message' => 'File upload error. Please try again.']);
}
if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
    sendJson(400, ['status' => 'error', 'message' => 'Payment screenshot must be under 5 MB.']);
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $_FILES['proof']['tmp_name']);
finfo_close($finfo);
$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extMap[$mime])) {
    sendJson(400, ['status' => 'error', 'message' => 'Payment proof must be JPEG, PNG, or WEBP.']);
}
$ext       = $extMap[$mime];
$uploadDir = __DIR__ . '/uploads/training-proofs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
$filename = 'training-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
move_uploaded_file($_FILES['proof']['tmp_name'], $uploadDir . $filename);
$proofPath = 'uploads/training-proofs/' . $filename;

// Save to CSV
$csvPath = __DIR__ . '/training-enrollments.csv';
$handle  = @fopen($csvPath, 'ab+');
if ($handle !== false && flock($handle, LOCK_EX)) {
    $stats = fstat($handle);
    if (($stats['size'] ?? 0) === 0) {
        fputcsv($handle, ['Submitted At', 'Name', 'Mobile', 'Email', 'Module', 'Amount', 'Payment Reference', 'Proof File Path']);
    }
    fputcsv($handle, [date('Y-m-d H:i:s'), $name, $mobile, $email, $module, $amount, $payRef, $proofPath]);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

// Email notification
function loadPhpMailer(): bool {
    $autoloadPaths = [__DIR__ . '/vendor/autoload.php', dirname(__DIR__) . '/vendor/autoload.php'];
    foreach ($autoloadPaths as $path) {
        if (is_file($path)) { require_once $path; return true; }
    }
    $srcRoots = [__DIR__ . '/PHPMailer-6.9.1/src', dirname(__DIR__) . '/PHPMailer-6.9.1/src'];
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
        $mail->Subject = 'Hands-on Training Enrollment: ' . $name . ' – AGUSICON 2026';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;color:#111;line-height:1.6'>
            <h2 style='color:#115B86'>New Hands-on Training Enrollment – AGUSICON 2026</h2>
            <table cellpadding='8' cellspacing='0' border='0' style='width:100%;border-collapse:collapse'>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86;width:35%'>Name</td><td>" . htmlspecialchars($name) . "</td></tr>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Mobile</td><td>" . htmlspecialchars($mobile) . "</td></tr>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Email</td><td>" . htmlspecialchars($email) . "</td></tr>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Module</td><td>" . htmlspecialchars($module) . "</td></tr>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Amount</td><td>&#8377;" . htmlspecialchars($amount) . "</td></tr>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Payment Reference</td><td>" . htmlspecialchars($payRef) . "</td></tr>
              <tr><td style='background:#f0f7fa;font-weight:700;color:#115B86'>Proof File</td><td>" . htmlspecialchars($proofPath) . "</td></tr>
            </table>
            </div>";
        $mail->AltBody = "Hands-on Training Enrollment\nName: {$name}\nMobile: {$mobile}\nEmail: {$email}\nModule: {$module}\nAmount: {$amount}\nRef: {$payRef}";
        $mail->send();

        // ── ACK email to registrant ──────────────────────────
        $moduleLabel = (stripos($module, 'pcnl') !== false || stripos($module, 'puncture') !== false)
            ? 'Module 1 – Initial Puncture for PCNL with C-Arm Control'
            : (stripos($module, 'turp') !== false ? 'Module 2 – TURP (Transurethral Resection of Prostate)' : $module);

        $ack = new \PHPMailer\PHPMailer\PHPMailer(true);
        $ack->isSMTP();
        $ack->Host       = 'smtp-relay.brevo.com';
        $ack->SMTPAuth   = true;
        $ack->Username   = 'ae1b45001@smtp-brevo.com';
        $ack->Password   = 'DqKY7Lsg4rUzQpWx';
        $ack->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $ack->Port       = 587;
        $ack->Timeout    = 8;
        $ack->CharSet    = 'UTF-8';
        $ack->isHTML(true);
        $ack->setFrom('info@agusicon.com', 'AGUSICON 2026');
        $ack->addAddress($email, $name);
        $ack->addBCC('mukund.rgb@gmail.com');
        $ack->Subject = 'Enrollment Received — AGUSICON 2026 Hands-on Training';
        $safeName      = htmlspecialchars($name);
        $safeModule    = htmlspecialchars($moduleLabel);
        $safeAmount    = htmlspecialchars($amount);
        $safeRef       = htmlspecialchars($payRef);
        $ack->Body = "
<div style='font-family:Arial,sans-serif;background:#f4f7fb;padding:32px 0'>
<div style='max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.10)'>
  <div style='background:linear-gradient(135deg,#0b3d5e 0%,#115B86 100%);padding:28px 36px 22px;text-align:center'>
    <div style='font-size:22px;font-weight:800;color:#fff'>AGUSICON 2026</div>
    <div style='font-size:11px;color:#b4d2ee;margin-top:4px;letter-spacing:.04em'>NATIONAL UROLOGY CONFERENCE</div>
    <div style='width:48px;height:3px;background:#b48c1e;margin:12px auto 0;border-radius:2px'></div>
  </div>
  <div style='padding:30px 36px'>
    <div style='font-size:17px;font-weight:700;color:#0b3d5e;margin-bottom:8px'>Enrollment Received</div>
    <p style='font-size:14px;color:#374151;line-height:1.7;margin:0 0 20px'>
      Dear <strong>{$safeName}</strong>,<br>
      Thank you for enrolling in the <strong>Hands-on Training</strong> programme at AGUSICON 2026. We have received your enrollment and payment details.
    </p>
    <div style='background:#f0f7ff;border:1.5px solid #bdd7f0;border-radius:10px;padding:18px 20px;margin-bottom:20px'>
      <table style='width:100%;border-collapse:collapse;font-size:13.5px'>
        <tr><td style='padding:7px 0;color:#6b7280;font-weight:700;width:42%'>MODULE</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>{$safeModule}</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>AMOUNT</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>Rs. {$safeAmount}</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>PAYMENT REF.</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>{$safeRef}</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>EVENT DATE</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>25&ndash;26 July 2026</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>VENUE</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>Blessing Garden, Bhadohi, Uttar Pradesh</td></tr>
      </table>
    </div>
    <div style='background:#fefce8;border:1.5px solid #fde047;border-radius:10px;padding:14px 18px;margin-bottom:20px'>
      <div style='font-size:12.5px;color:#854d0e;line-height:1.6'>
        <strong>Next Step:</strong> Our team will verify your payment and send a formal <strong>Payment Confirmation email</strong> with a PDF receipt within 24 hours.
        If you have any questions, please reply to this email.
      </div>
    </div>
    <p style='font-size:13px;color:#374151;line-height:1.7;margin:0'>We look forward to an enriching hands-on experience with you at AGUSICON 2026!</p>
  </div>
  <div style='background:#0b3d5e;padding:18px 36px;text-align:center'>
    <div style='font-size:12px;color:#b4d2ee'>AGUSICON 2026 &bull; agusicon.com &bull; agusicon2025@gmail.com</div>
    <div style='font-size:11px;color:#6b97b8;margin-top:5px'>Blessing Garden, Bhadohi, Uttar Pradesh</div>
  </div>
</div></div>";
        $ack->AltBody = "Dear {$name},\nThank you for enrolling in AGUSICON 2026 Hands-on Training.\nModule: {$moduleLabel}\nAmount: Rs. {$amount}\nRef: {$payRef}\n\nWe will verify your payment and send confirmation within 24 hours.";
        $ack->send();
    } catch (\Throwable $e) {
        // Email failure is silent — CSV was already saved
    }
}

sendJson(200, ['status' => 'success', 'message' => 'Enrollment submitted! We will confirm your seat within 24 hours.']);
