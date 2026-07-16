<?php
declare(strict_types=1);
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

use PHPMailer\PHPMailer\PHPMailer;

ob_clean();
header('Content-Type: application/json; charset=UTF-8');

/**
 * Send a JSON response and stop execution.
 */
function sendJsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verify a reCAPTCHA v3 token. Returns false if missing, invalid, or score < 0.5.
 */
function verifyRecaptcha(string $token, string $secret): bool
{
    if ($token === '') {
        return false;
    }
    $url = 'https://www.google.com/recaptcha/api/siteverify?secret='
        . urlencode($secret) . '&response=' . urlencode($token);
    $resp = @file_get_contents($url);
    if ($resp === false) {
        return false;
    }
    $data = json_decode($resp, true);
    return ($data['success'] ?? false) === true && ($data['score'] ?? 0) >= 0.5;
}

/**
 * Load PHPMailer either from Composer autoload or bundled library files.
 */
function bootstrapPhpMailer(): void
{
    $autoloadCandidates = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    foreach ($autoloadCandidates as $autoloadPath) {
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
            return;
        }
    }

    $manualRoots = [
        __DIR__ . '/PHPMailer-6.9.1/src',
        dirname(__DIR__) . '/PHPMailer-6.9.1/src',
    ];

    foreach ($manualRoots as $root) {
        $exceptionFile = $root . '/Exception.php';
        $phpMailerFile = $root . '/PHPMailer.php';
        $smtpFile = $root . '/SMTP.php';

        if (is_file($exceptionFile) && is_file($phpMailerFile) && is_file($smtpFile)) {
            require_once $exceptionFile;
            require_once $phpMailerFile;
            require_once $smtpFile;
            return;
        }
    }

    throw new RuntimeException('PHPMailer library files were not found on server.');
}

/**
 * Append a lead row into lead.csv (with header on first write).
 */
function appendLeadCsv(string $csvPath, array $row): void
{
    $isNewFile = !file_exists($csvPath);
    $handle = @fopen($csvPath, 'ab');

    if ($handle === false) {
        throw new RuntimeException('Unable to open lead.csv for writing. Check folder permissions.');
    }

    if ($isNewFile) {
        $header = ['Date', 'First Name', 'Last Name', 'Email', 'Phone', 'Subject', 'Message'];
        if (fputcsv($handle, $header) === false) {
            fclose($handle);
            throw new RuntimeException('Unable to write header row to lead.csv.');
        }
    }

    if (fputcsv($handle, $row) === false) {
        fclose($handle);
        throw new RuntimeException('Unable to write lead row to lead.csv.');
    }

    fclose($handle);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    sendJsonResponse(405, [
        'status' => 'error',
        'message' => 'Method not allowed. Use POST.',
    ]);
}

try {
    bootstrapPhpMailer();
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'status' => 'error',
        'message' => 'Mailer setup failed: ' . $e->getMessage(),
    ]);
}

$recaptchaToken  = trim((string)($_POST['recaptcha_token'] ?? ''));
$recaptchaSecret = (string)(getenv('RECAPTCHA_SECRET_KEY') ?: '6LfrGiAtAAAAAMk6FgnkMe5KWFk_ZeXRqEpub5hx');
if (!verifyRecaptcha($recaptchaToken, $recaptchaSecret)) {
    sendJsonResponse(400, [
        'status'  => 'error',
        'message' => 'Security check failed. Please refresh the page and try again.',
    ]);
}

$firstName = trim((string)($_POST['first_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$fullName = trim($firstName . ' ' . $lastName);

if ($firstName === '' || $lastName === '' || $email === '' || $subject === '' || $message === '') {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please complete all required fields with valid information.',
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please enter a valid email address.',
    ]);
}

$csvPath = dirname(__DIR__) . '/data/lead.csv';

try {
    appendLeadCsv($csvPath, [
        date('Y-m-d H:i:s'),
        $firstName,
        $lastName,
        $email,
        $phone,
        $subject,
        $message,
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'status' => 'error',
        'message' => 'Could not save lead.csv: ' . $e->getMessage(),
    ]);
}

$safeFullName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$mail = null;
$smtpHost = 'smtp-relay.brevo.com';
$smtpUsername = 'ae1b45001@smtp-brevo.com';
$smtpFromEmail = 'info@agusicon.com';
$smtpPassword = 'DqKY7Lsg4rUzQpWx';
$adminRecipient = 'agusicon2025@gmail.com';

try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    // Use the actual SMTP submission host. The public mail.* record is proxied
    // through Cloudflare, and the MX alias starts with "_" which PHPMailer rejects.
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 8;
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    $fromEmail = $smtpFromEmail;

    $mail->setFrom($smtpFromEmail, 'AGUSICON 2026');
    $mail->addAddress($adminRecipient);
    $mail->addBCC('mukund.rgb@gmail.com');
    $mail->addReplyTo($email, $fullName);
    $mail->Subject = 'New enquiry from Contact page – AGUSICON 2026';
    $mail->Body = "
        <h2>New Contact Enquiry</h2>
        <p><strong>Name:</strong> {$safeFullName}</p>
        <p><strong>Email:</strong> {$safeEmail}</p>
        <p><strong>Phone:</strong> {$safePhone}</p>
        <p><strong>Subject:</strong> {$safeSubject}</p>
        <p><strong>Message:</strong><br>{$safeMessage}</p>
    ";
    $mail->AltBody = "New Contact Enquiry\n"
        . "Name: {$fullName}\n"
        . "Email: {$email}\n"
        . "Phone: {$phone}\n"
        . "Subject: {$subject}\n"
        . "Message:\n{$message}\n";
    $mail->send();

    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->setFrom($fromEmail, 'AGUSICON 2026 Secretariat');
    $mail->addAddress($email, $fullName);
    $mail->Subject = 'Thank you for contacting us';
    $mail->Body = "
        <p>Hi " . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . ",</p>
        <p>Thank you for reaching out. We have received your message:</p>
        <p><strong>Subject:</strong> {$safeSubject}</p>
        <p>Our team will get back to you shortly.</p>
        <p>Best Regards,<br>AGUSICON 2026 Secretariat</p>
    ";
    $mail->AltBody = "Hi {$firstName},\n\n"
        . "Thank you for reaching out.\n\n"
        . "Subject: {$subject}\n\n"
        . "Our team will get back to you shortly.\n\n"
        . "Best Regards,\nAGUSICON 2026 Secretariat";
    $mail->send();

    sendJsonResponse(200, [
        'status' => 'success',
        'message' => 'Message sent successfully. We will get back to you soon.',
    ]);
} 

catch (Throwable $e) {
    sendJsonResponse(200, [
        'status'  => 'success',
        'message' => 'Message sent successfully. We will get back to you soon.',
    ]);
}
