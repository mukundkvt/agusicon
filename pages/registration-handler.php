<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

date_default_timezone_set('Asia/Kolkata');

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
 * Append a row into the event registration CSV with a header on first write.
 */
function appendRegistrationCsv(string $csvPath, array $row): void
{
    $handle = @fopen($csvPath, 'ab+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open registrationleads.csv for writing. Check folder permissions.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock registrationleads.csv for writing.');
    }

    $stats = fstat($handle);
    $isNewFile = ($stats['size'] ?? 0) === 0;

    if ($isNewFile) {
        $header = [
            'Submitted At',
            'Event Name',
            'Event Dates',
            'Event Location',
            'First Name',
            'Last Name',
            'Email',
            'Mobile',
            'Qualification',
            'Years of Experience',
            'Institution / Hospital',
            'City',
            'Area of Interest',
            'Referral Source',
            'Comments',
            'Terms Accepted',
        ];

        if (fputcsv($handle, $header) === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('Unable to write header row to registrationleads.csv.');
        }
    }

    if (fputcsv($handle, $row) === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException('Unable to write lead row to registrationleads.csv.');
    }

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * Append a row into the payment CSV with a header on first write.
 */
function appendPaymentCsv(string $csvPath, array $row): void
{
    $handle = @fopen($csvPath, 'ab+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open registration-payments.csv for writing. Check folder permissions.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock registration-payments.csv for writing.');
    }

    $stats = fstat($handle);
    $isNewFile = ($stats['size'] ?? 0) === 0;

    if ($isNewFile) {
        $header = [
            'Submitted At',
            'Registration ID',
            'Event Name',
            'Event Dates',
            'Event Location',
            'First Name',
            'Last Name',
            'Email',
            'Mobile',
            'Qualification',
            'Years of Experience',
            'Institution / Hospital',
            'City',
            'Area of Interest',
            'Referral Source',
            'Comments',
            'Payment Category',
            'Spouse Included',
            'HTO‑DFO Workshop Interest',
            'Base Amount',
            'Spouse Amount',
            'Total Amount',
            'Amount Paid',
            'Reference Number',
            'UPI Account',
            'Proof File Path',
            'Proof Original Name',
        ];

        if (fputcsv($handle, $header) === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('Unable to write header row to registration-payments.csv.');
        }
    }

    if (fputcsv($handle, $row) === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException('Unable to write lead row to registration-payments.csv.');
    }

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * Append a row into the combined registration CSV with a header on first write.
 * This CSV contains both registration and payment data in one file.
 */
function appendCombinedCsv(string $csvPath, array $row): void
{
    $handle = @fopen($csvPath, 'ab+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open registrations-combined.csv for writing. Check folder permissions.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock registrations-combined.csv for writing.');
    }

    $stats = fstat($handle);
    $isNewFile = ($stats['size'] ?? 0) === 0;

    if ($isNewFile) {
        $header = [
            'Submitted At',
            'Registration ID',
            'Event Name',
            'Event Dates',
            'Event Location',
            'First Name',
            'Last Name',
            'Email',
            'Mobile',
            'Qualification',
            'Years of Experience',
            'Institution / Hospital',
            'City',
            'Area of Interest',
            'Referral Source',
            'Comments',
            'Terms Accepted',
            'Payment Category',
            'Spouse Included',
            'HTO‑DFO Workshop Interest',
            'Base Amount',
            'Spouse Amount',
            'Total Amount',
            'Amount Paid',
            'Reference Number',
            'UPI Account',
            'Proof File Path',
            'Proof Original Name',
            'Payment Status',
        ];

        if (fputcsv($handle, $header) === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('Unable to write header row to registrations-combined.csv.');
        }
    }

    if (fputcsv($handle, $row) === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException('Unable to write lead row to registrations-combined.csv.');
    }

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * Pull SMTP settings from environment variables with current site defaults as fallback.
 */
function getSmtpSettings(): array
{
    $username = trim((string)(getenv('ORTHO_SMTP_USERNAME') ?: 'a9f5c7001@smtp-brevo.com'));
    $notificationEmail = trim((string)(getenv('ORTHO_REGISTRATION_NOTIFY_EMAIL') ?: 'agusicon2025@gmail.com'));
    $fromEmail = trim((string)(getenv('ORTHO_SMTP_FROM_EMAIL') ?: 'agusicon2025@gmail.com'));
    $encryption = strtolower(trim((string)(getenv('ORTHO_SMTP_ENCRYPTION') ?: 'tls')));

    $smtpSecure = PHPMailer::ENCRYPTION_STARTTLS;
    if ($encryption === 'ssl' || $encryption === 'smtps') {
        $smtpSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    return [
        'host' => trim((string)(getenv('ORTHO_SMTP_HOST') ?: 'smtp-relay.brevo.com')),
        'port' => (int)(getenv('ORTHO_SMTP_PORT') ?: 587),
        'username' => $username,
        'password' => (string)(getenv('ORTHO_SMTP_PASSWORD') ?: 'bskv5TjLOERRhnP'),
        'from_email' => $fromEmail !== '' ? $fromEmail : 'agusicon2025@gmail.com',
        'from_name' => trim((string)(getenv('ORTHO_SMTP_FROM_NAME') ?: 'Ortho Edge Website')),
        'notification_email' => $notificationEmail,
        'notification_name' => trim((string)(getenv('ORTHO_REGISTRATION_NOTIFY_NAME') ?: 'Ortho Edge Registrations')),
        'ack_name' => trim((string)(getenv('ORTHO_SMTP_ACK_NAME') ?: 'Ortho Edge Team')),
        'secure' => $smtpSecure,
    ];
}

/**
 * Build a configured SMTP mailer.
 */
function createMailer(array $smtpSettings): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = $smtpSettings['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtpSettings['username'];
    $mail->Password = $smtpSettings['password'];
    $mail->SMTPSecure = $smtpSettings['secure'];
    $mail->Port = $smtpSettings['port'];
    $mail->Timeout = 20;
    $mail->isHTML(true);

    return $mail;
}

/**
 * Escape a string for HTML email output.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Create a registration ID that can link registration and payment steps.
 */
function getNextRegistrationSerial(string $sequencePath): int
{
    $handle = @fopen($sequencePath, 'c+');

    if ($handle === false) {
        throw new RuntimeException('Unable to open the registration sequence file for writing.');
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        throw new RuntimeException('Unable to lock the registration sequence file.');
    }

    rewind($handle);
    $rawValue = trim((string)stream_get_contents($handle));
    $currentSerial = ctype_digit($rawValue) ? (int)$rawValue : 0;
    $nextSerial = $currentSerial + 1;

    rewind($handle);
    ftruncate($handle, 0);

    if (fwrite($handle, (string)$nextSerial) === false) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw new RuntimeException('Unable to update the registration sequence file.');
    }

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $nextSerial;
}

function generateRegistrationId(string $sequencePath): string
{
    $serialNumber = getNextRegistrationSerial($sequencePath);

    return 'OE-Vns2026-' . str_pad((string)$serialNumber, 5, '0', STR_PAD_LEFT);
}

/**
 * Format a rupee amount for email/CSV display.
 */
function formatRupees(int $amount): string
{
    return 'Rs. ' . number_format($amount, 0, '.', ',');
}

/**
 * Return the current India time.
 */
function nowInIndia(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
}

/**
 * Format a date for readable email display in IST.
 */
function formatIndiaDisplayTime(DateTimeInterface $dateTime): string
{
    return $dateTime->format('d-m-Y h:i:s A') . ' IST';
}

/**
 * Render labeled rows for HTML emails.
 */
function renderEmailTable(array $rows): string
{
    $html = '<table cellpadding="8" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">';

    foreach ($rows as $label => $value) {
        $safeLabel = e((string)$label);
        $safeValue = nl2br(e((string)$value));
        $html .= '<tr>'
            . '<td style="width:34%;padding:10px 12px;border-bottom:1px solid #e5e7eb;background:#f8fafc;font-weight:700;color:#1e3a8a;vertical-align:top;">' . $safeLabel . '</td>'
            . '<td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#111827;vertical-align:top;">' . $safeValue . '</td>'
            . '</tr>';
    }

    return $html . '</table>';
}

/**
 * Render labeled rows for plain-text emails.
 */
function renderPlainList(array $rows): string
{
    $lines = [];

    foreach ($rows as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }

    return implode("\n", $lines);
}

/**
 * Persist the uploaded payment proof and return saved file details.
 */
function savePaymentProof(array $file, string $registrationId): array
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode !== UPLOAD_ERR_OK) {
        $messageMap = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded payment screenshot exceeds the server limit.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded payment screenshot exceeds the form limit.',
            UPLOAD_ERR_PARTIAL => 'The payment screenshot upload was incomplete.',
            UPLOAD_ERR_NO_FILE => 'Please upload the payment screenshot.',
        ];

        throw new RuntimeException($messageMap[$errorCode] ?? 'The payment screenshot could not be uploaded.');
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('The uploaded payment screenshot is invalid.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('The uploaded payment screenshot is empty.');
    }

    if ($size > 5 * 1024 * 1024) {
        throw new RuntimeException('Please upload a payment screenshot smaller than 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string)$finfo->file($tmpPath);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException('Please upload the payment proof as a JPG, PNG, or WEBP image.');
    }

    $uploadDir = dirname(__DIR__) . '/data/uploads/payment-proofs';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to create the payment proof upload folder.');
    }

    $safeRegistrationId = preg_replace('/[^A-Za-z0-9_-]/', '', $registrationId) ?: 'OE';
    $fileExtension = $allowedMimeTypes[$mimeType];
    $targetFilename = sprintf(
        '%s-%s-%s.%s',
        $safeRegistrationId,
        date('YmdHis'),
        strtoupper(bin2hex(random_bytes(2))),
        $fileExtension
    );
    $targetPath = $uploadDir . '/' . $targetFilename;

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        throw new RuntimeException('Unable to save the uploaded payment screenshot.');
    }

    return [
        'path' => $targetPath,
        'relative_path' => 'uploads/payment-proofs/' . $targetFilename,
        'original_name' => basename((string)($file['name'] ?? $targetFilename)),
    ];
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
$recaptchaSecret = (string)(getenv('RECAPTCHA_SECRET_KEY') ?: '6LfnIuQsAAAAAHffd2cxwiNMqa3xPcKzST0yHLtD');
if (!verifyRecaptcha($recaptchaToken, $recaptchaSecret)) {
    sendJsonResponse(400, [
        'status'  => 'error',
        'message' => 'Security check failed. Please refresh the page and try again.',
    ]);
}

$submissionStage = trim((string)($_POST['submission_stage'] ?? 'registration'));
$eventName = trim((string)($_POST['event_name'] ?? 'Knee Masterclass 2026 - Varanasi'));
$eventDates = trim((string)($_POST['event_dates'] ?? '25-26 July 2026'));
$eventLocation = trim((string)($_POST['event_location'] ?? 'Hotel Surya, Kaiser Palace, Varanasi, Uttar Pradesh'));
$firstName = trim((string)($_POST['first_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$mobile = trim((string)($_POST['mobile'] ?? ''));
$qualification = trim((string)($_POST['qualification'] ?? ''));
$experience = trim((string)($_POST['experience'] ?? ''));
$institution = trim((string)($_POST['institution'] ?? ''));
$city = trim((string)($_POST['city'] ?? ''));
$areaOfInterest = trim((string)($_POST['area_of_interest'] ?? ''));
$referralSource = trim((string)($_POST['referral_source'] ?? ''));
$comments = trim((string)($_POST['comments'] ?? ''));
$termsAccepted = trim((string)($_POST['terms_accepted'] ?? ''));
$fullName = trim($firstName . ' ' . $lastName);

if (
    $firstName === '' ||
    $lastName === '' ||
    $email === '' ||
    $mobile === '' ||
    $qualification === '' ||
    $institution === '' ||
    $city === ''
) {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please complete all required registration fields.',
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please enter a valid email address.',
    ]);
}

$safeEventName = e($eventName);
$safeEventDates = e($eventDates);
$safeEventLocation = e($eventLocation);
$safeFullName = e($fullName);
$safeEmail = e($email);
$safeMobile = e($mobile);
$safeQualification = e($qualification);
$safeExperience = e($experience !== '' ? $experience : 'Not provided');
$safeInstitution = e($institution);
$safeCity = e($city);
$safeAreaOfInterest = e($areaOfInterest !== '' ? $areaOfInterest : 'Not provided');
$safeReferralSource = e($referralSource !== '' ? $referralSource : 'Not provided');
$safeComments = nl2br(e($comments !== '' ? $comments : 'No additional comments provided.'));
$smtpSettings = getSmtpSettings();
$mail = null;

if ($submissionStage === 'registration') {
    if ($termsAccepted === '') {
        sendJsonResponse(400, [
            'status' => 'error',
            'message' => 'Please accept the terms and conditions to continue.',
        ]);
    }

    $registrationSequencePath = dirname(__DIR__) . '/data/registration-sequence-vns2026.txt';
    try {
        $registrationId = generateRegistrationId($registrationSequencePath);
    } catch (Throwable $e) {
        sendJsonResponse(500, [
            'status' => 'error',
            'message' => 'Could not generate registration ID. Please try again or contact support.',
        ]);
    }
    $registrationTimestamp = nowInIndia();
    $registrationSubmittedAt = $registrationTimestamp->format('Y-m-d H:i:s');
    $registrationSubmittedAtDisplay = formatIndiaDisplayTime($registrationTimestamp);
    $csvPath = dirname(__DIR__) . '/data/registrationleads.csv';
    $combinedCsvPath = dirname(__DIR__) . '/data/registrations-combined.csv';

    try {
        appendRegistrationCsv($csvPath, [
            $registrationSubmittedAt,
            $eventName,
            $eventDates,
            $eventLocation,
            $firstName,
            $lastName,
            $email,
            $mobile,
            $qualification,
            $experience,
            $institution,
            $city,
            $areaOfInterest,
            $referralSource,
            $comments,
            'Yes',
        ]);
    } catch (Throwable $e) {
        sendJsonResponse(500, [
            'status' => 'error',
            'message' => 'Could not save registrationleads.csv: ' . $e->getMessage(),
        ]);
    }

    // Also write to combined CSV with empty payment columns
    try {
        appendCombinedCsv($combinedCsvPath, [
            $registrationSubmittedAt,
            $registrationId,
            $eventName,
            $eventDates,
            $eventLocation,
            $firstName,
            $lastName,
            $email,
            $mobile,
            $qualification,
            $experience,
            $institution,
            $city,
            $areaOfInterest,
            $referralSource,
            $comments,
            'Yes', // Terms Accepted
            '', // Payment Category (empty for registration-only)
            '', // Spouse Included
            '', // HTO‑DFO Workshop Interest (not captured in registration stage)
            '', // Base Amount
            '', // Spouse Amount
            '', // Total Amount
            '', // Amount Paid
            '', // Reference Number
            '', // UPI Account
            '', // Proof File Path
            '', // Proof Original Name
            'Registration Only', // Payment Status
        ]);
    } catch (Throwable $e) {
        // Don't fail the whole registration if combined CSV fails
        // Just log it silently for now
        error_log('Failed to write to combined CSV: ' . $e->getMessage());
    }

    try {
        $mail = createMailer($smtpSettings);
        $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
        $mail->addAddress($smtpSettings['notification_email'], $smtpSettings['notification_name']);
        $mail->addReplyTo($email, $fullName);
        $mail->Subject = 'Partial Registration Received [' . $registrationId . '] - ' . $fullName;
        $mail->Body = "
            <div style='font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.6;'>
                <h2 style='margin:0 0 14px;color:#1e3a8a;'>Step 1 of 2: Registration Details Received</h2>
                <p style='margin:0 0 18px;'>A delegate has completed the first step of the registration flow. Payment details are still pending.</p>

                <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Submission Status</h3>
                " . renderEmailTable([
                    'Registration ID' => $registrationId,
                    'Submission Stage' => 'Step 1 of 2 - Registration saved',
                    'Payment Status' => 'Awaiting payment step',
                    'Submitted At' => $registrationSubmittedAtDisplay,
                ]) . "

                <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Event Details</h3>
                " . renderEmailTable([
                    'Event Name' => $eventName,
                    'Event Dates' => $eventDates,
                    'Event Location' => $eventLocation,
                ]) . "

                <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Delegate Details</h3>
                " . renderEmailTable([
                    'Full Name' => $fullName,
                    'Email Address' => $email,
                    'Mobile Number' => $mobile,
                    'Qualification' => $qualification,
                    'Years of Experience' => $experience !== '' ? $experience : 'Not provided',
                    'Institution / Hospital' => $institution,
                    'City' => $city,
                    'Area of Interest' => $areaOfInterest !== '' ? $areaOfInterest : 'Not provided',
                    'Referral Source' => $referralSource !== '' ? $referralSource : 'Not provided',
                    'Comments / Requirements' => $comments !== '' ? $comments : 'No additional comments provided.',
                    'Terms Accepted' => 'Yes',
                ]) . "
            </div>
        ";
        $mail->AltBody = "STEP 1 OF 2: REGISTRATION DETAILS RECEIVED\n\n"
            . "A delegate has completed the first step of the registration flow. Payment details are still pending.\n\n"
            . "[Submission Status]\n"
            . renderPlainList([
                'Registration ID' => $registrationId,
                'Submission Stage' => 'Step 1 of 2 - Registration saved',
                'Payment Status' => 'Awaiting payment step',
                'Submitted At' => $registrationSubmittedAtDisplay,
            ]) . "\n\n"
            . "[Event Details]\n"
            . renderPlainList([
                'Event Name' => $eventName,
                'Event Dates' => $eventDates,
                'Event Location' => $eventLocation,
            ]) . "\n\n"
            . "[Delegate Details]\n"
            . renderPlainList([
                'Full Name' => $fullName,
                'Email Address' => $email,
                'Mobile Number' => $mobile,
                'Qualification' => $qualification,
                'Years of Experience' => $experience !== '' ? $experience : 'Not provided',
                'Institution / Hospital' => $institution,
                'City' => $city,
                'Area of Interest' => $areaOfInterest !== '' ? $areaOfInterest : 'Not provided',
                'Referral Source' => $referralSource !== '' ? $referralSource : 'Not provided',
                'Comments / Requirements' => $comments !== '' ? $comments : 'No additional comments provided.',
                'Terms Accepted' => 'Yes',
            ]);
        $mail->send();

        $mail->clearAddresses();
        $mail->clearReplyTos();
        $mail->setFrom($smtpSettings['from_email'], $smtpSettings['ack_name']);
        $mail->addAddress($email, $fullName);
        $mail->addBCC('mukund.rgb@gmail.com');
        $mail->Subject = 'Registration received for ' . $eventName;
        $mail->Body = "
            <p>Dear {$safeFullName},</p>
            <p>registration confirmation subject to realisation of payment- we would revert shortly</p>
            <p><strong>Registration ID:</strong> " . e($registrationId) . "</p>
            <p><strong>Dates:</strong> {$safeEventDates}<br><strong>Location:</strong> {$safeEventLocation}</p>
            <p>We have saved your delegate details. Please continue with the payment step on the website to complete the process.</p>
            <p><strong>Registration details received:</strong></p>
            <p>Email: {$safeEmail}<br>Mobile: {$safeMobile}<br>Institution / Hospital: {$safeInstitution}</p>
            <p>Regards,<br>Ortho Edge Team</p>
        ";
        $mail->AltBody = "Dear {$fullName},\n\n"
            . "Thank you for registering for {$eventName}.\n"
            . "Registration ID: {$registrationId}\n"
            . "Dates: {$eventDates}\n"
            . "Location: {$eventLocation}\n\n"
            . "We have saved your delegate details. Please continue with the payment step on the website to complete the process.\n\n"
            . "Regards,\nOrtho Edge Team";
        $mail->send();

        sendJsonResponse(200, [
            'status' => 'success',
            'message' => 'Registration details saved successfully. Please continue with payment.',
            'registration_id' => $registrationId,
        ]);
    } catch (Throwable $e) {
        $reason = $e->getMessage();
        if ($mail instanceof PHPMailer && $mail->ErrorInfo !== '') {
            $reason = $mail->ErrorInfo;
        }

        sendJsonResponse(500, [
            'status' => 'error',
            'message' => 'Registration saved, but email could not be sent: ' . $reason,
        ]);
    }
}

if ($submissionStage !== 'payment') {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Unknown submission stage.',
    ]);
}

$registrationId = trim((string)($_POST['registration_id'] ?? ''));
$paymentCategory = trim((string)($_POST['payment_category'] ?? ''));
$includeSpouse = trim((string)($_POST['include_spouse'] ?? '')) === 'yes';
$attendHtoDfoWorkshop = trim((string)($_POST['attend_hto_dfo_workshop'] ?? '')) === 'yes';
$amountPaidInput = trim((string)($_POST['payment_amount'] ?? ''));
$paymentReferenceNumber = trim((string)($_POST['payment_reference_number'] ?? ''));
$upiAccount = trim((string)($_POST['upi_account'] ?? ''));

if ($registrationId === '') {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'The registration reference is missing. Please complete step 1 again.',
    ]);
}

if (!in_array($paymentCategory, ['pg_student', 'regular_delegate'], true)) {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please select a valid registration fee category.',
    ]);
}

if ($paymentReferenceNumber === '' || $upiAccount === '') {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please complete all required payment details.',
    ]);
}

if (!isset($_FILES['payment_screenshot'])) {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => 'Please upload the payment screenshot.',
    ]);
}

$baseAmount = $paymentCategory === 'regular_delegate' ? 5500 : 2500;
$spouseAmount = $includeSpouse ? 4500 : 0;
$totalAmount = $baseAmount + $spouseAmount;
$paymentTimestamp = nowInIndia();
$paymentSubmittedAt = $paymentTimestamp->format('Y-m-d H:i:s');
$paymentSubmittedAtDisplay = formatIndiaDisplayTime($paymentTimestamp);
$amountPaidDisplay = $amountPaidInput !== '' ? $amountPaidInput : formatRupees($totalAmount);
$paymentCategoryLabel = $paymentCategory === 'regular_delegate' ? 'All Other Delegates' : 'PG Student';
$spouseIncludedLabel = $includeSpouse ? 'Yes' : 'No';
$htoDfoWorkshopLabel = $attendHtoDfoWorkshop ? 'Yes' : 'No';
$safeRegistrationId = e($registrationId);
$safePaymentCategory = e($paymentCategoryLabel);
$safeAmountPaid = e($amountPaidDisplay);
$safeReferenceNumber = e($paymentReferenceNumber);
$safeUpiAccount = e($upiAccount);
$paymentCsvPath = dirname(__DIR__) . '/data/registration-payments.csv';
$combinedCsvPath = dirname(__DIR__) . '/data/registrations-combined.csv';

try {
    $proof = savePaymentProof($_FILES['payment_screenshot'], $registrationId);
} catch (Throwable $e) {
    sendJsonResponse(400, [
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}

try {
    appendPaymentCsv($paymentCsvPath, [
        $paymentSubmittedAt,
        $registrationId,
        $eventName,
        $eventDates,
        $eventLocation,
        $firstName,
        $lastName,
        $email,
        $mobile,
        $qualification,
        $experience,
        $institution,
        $city,
        $areaOfInterest,
        $referralSource,
        $comments,
        $paymentCategoryLabel,
        $spouseIncludedLabel,
        $htoDfoWorkshopLabel,
        formatRupees($baseAmount),
        formatRupees($spouseAmount),
        formatRupees($totalAmount),
        $amountPaidDisplay,
        $paymentReferenceNumber,
        $upiAccount,
        $proof['relative_path'],
        $proof['original_name'],
    ]);
} catch (Throwable $e) {
    sendJsonResponse(500, [
        'status' => 'error',
        'message' => 'Could not save registration-payments.csv: ' . $e->getMessage(),
    ]);
}

// Also write to combined CSV with payment data
try {
    appendCombinedCsv($combinedCsvPath, [
        $paymentSubmittedAt,
        $registrationId,
        $eventName,
        $eventDates,
        $eventLocation,
        $firstName,
        $lastName,
        $email,
        $mobile,
        $qualification,
        $experience,
        $institution,
        $city,
        $areaOfInterest,
        $referralSource,
        $comments,
        'Yes', // Terms Accepted (from registration stage)
        $paymentCategoryLabel,
        $spouseIncludedLabel,
        $htoDfoWorkshopLabel,
        formatRupees($baseAmount),
        formatRupees($spouseAmount),
        formatRupees($totalAmount),
        $amountPaidDisplay,
        $paymentReferenceNumber,
        $upiAccount,
        $proof['relative_path'],
        $proof['original_name'],
        'Payment Completed', // Payment Status
    ]);
} catch (Throwable $e) {
    // Don't fail the whole payment if combined CSV fails
    error_log('Failed to write payment to combined CSV: ' . $e->getMessage());
}

try {
    $mail = createMailer($smtpSettings);
    $mail->setFrom($smtpSettings['from_email'], $smtpSettings['from_name']);
    $mail->addAddress($smtpSettings['notification_email'], $smtpSettings['notification_name']);
    $mail->addReplyTo($email, $fullName);
    $mail->addAttachment($proof['path'], $proof['original_name']);
    $mail->Subject = 'Complete Payment Received [' . $registrationId . '] - ' . $fullName;
    $mail->Body = "
        <div style='font-family:Arial,Helvetica,sans-serif;color:#111827;line-height:1.6;'>
            <h2 style='margin:0 0 14px;color:#1e3a8a;'>Step 2 of 2: Payment Details Received</h2>
            <p style='margin:0 0 18px;'>This is the continuation of an already saved registration. The payment proof is attached to this email.</p>

            <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Submission Status</h3>
            " . renderEmailTable([
                'Registration ID' => $registrationId,
                'Submission Stage' => 'Step 2 of 2 - Payment details submitted',
                'Payment Status' => 'Proof received - verification pending',
                'Submitted At' => $paymentSubmittedAtDisplay,
            ]) . "

            <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Event Details</h3>
            " . renderEmailTable([
                'Event Name' => $eventName,
                'Event Dates' => $eventDates,
                'Event Location' => $eventLocation,
            ]) . "

            <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Delegate Details</h3>
            " . renderEmailTable([
                'Full Name' => $fullName,
                'Email Address' => $email,
                'Mobile Number' => $mobile,
                'Qualification' => $qualification,
                'Years of Experience' => $experience !== '' ? $experience : 'Not provided',
                'Institution / Hospital' => $institution,
                'City' => $city,
                'Area of Interest' => $areaOfInterest !== '' ? $areaOfInterest : 'Not provided',
                'Referral Source' => $referralSource !== '' ? $referralSource : 'Not provided',
                'Comments / Requirements' => $comments !== '' ? $comments : 'No additional comments provided.',
            ]) . "

            <h3 style='margin:18px 0 8px;color:#1e3a8a;'>Payment Details</h3>
            " . renderEmailTable([
                'Payment Category' => $paymentCategoryLabel,
                'Spouse Included' => $spouseIncludedLabel,
                'HTO‑DFO Workshop Interest' => $htoDfoWorkshopLabel,
                'Base Amount' => formatRupees($baseAmount),
                'Spouse Amount' => formatRupees($spouseAmount),
                'Total Payable' => formatRupees($totalAmount),
                'Amount Paid' => $amountPaidDisplay,
                'Reference Number' => $paymentReferenceNumber,
                'UPI Account Used' => $upiAccount,
                'Proof Attachment' => $proof['original_name'],
                'Saved Proof Path' => $proof['relative_path'],
            ]) . "
        </div>
    ";
    $mail->AltBody = "STEP 2 OF 2: PAYMENT DETAILS RECEIVED\n\n"
        . "This is the continuation of an already saved registration. The payment proof is attached to this email.\n\n"
        . "[Submission Status]\n"
        . renderPlainList([
            'Registration ID' => $registrationId,
            'Submission Stage' => 'Step 2 of 2 - Payment details submitted',
            'Payment Status' => 'Proof received - verification pending',
            'Submitted At' => $paymentSubmittedAtDisplay,
        ]) . "\n\n"
        . "[Event Details]\n"
        . renderPlainList([
            'Event Name' => $eventName,
            'Event Dates' => $eventDates,
            'Event Location' => $eventLocation,
        ]) . "\n\n"
        . "[Delegate Details]\n"
        . renderPlainList([
            'Full Name' => $fullName,
            'Email Address' => $email,
            'Mobile Number' => $mobile,
            'Qualification' => $qualification,
            'Years of Experience' => $experience !== '' ? $experience : 'Not provided',
            'Institution / Hospital' => $institution,
            'City' => $city,
            'Area of Interest' => $areaOfInterest !== '' ? $areaOfInterest : 'Not provided',
            'Referral Source' => $referralSource !== '' ? $referralSource : 'Not provided',
            'Comments / Requirements' => $comments !== '' ? $comments : 'No additional comments provided.',
        ]) . "\n\n"
        . "[Payment Details]\n"
        . renderPlainList([
            'Payment Category' => $paymentCategoryLabel,
            'Spouse Included' => $spouseIncludedLabel,
            'HTO‑DFO Workshop Interest' => $htoDfoWorkshopLabel,
            'Base Amount' => formatRupees($baseAmount),
            'Spouse Amount' => formatRupees($spouseAmount),
            'Total Payable' => formatRupees($totalAmount),
            'Amount Paid' => $amountPaidDisplay,
            'Reference Number' => $paymentReferenceNumber,
            'UPI Account Used' => $upiAccount,
            'Proof Attachment' => $proof['original_name'],
            'Saved Proof Path' => $proof['relative_path'],
        ]);
    $mail->send();

    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->clearAttachments();
    $mail->setFrom($smtpSettings['from_email'], $smtpSettings['ack_name']);
    $mail->addAddress($email, $fullName);
    $mail->addBCC('mukund.rgb@gmail.com');
    $mail->Subject = 'Payment details received for ' . $eventName;
    $mail->Body = "
        <p>Dear {$safeFullName},</p>
        <p>Thank you for completing the payment step for <strong>{$safeEventName}</strong>.</p>
        <p><strong>Registration ID:</strong> {$safeRegistrationId}</p>
        <p><strong>Amount Paid:</strong> {$safeAmountPaid}<br>
        <strong>Reference Number:</strong> {$safeReferenceNumber}<br>
        <strong>UPI Account:</strong> {$safeUpiAccount}</p>
        <p>We have received your payment proof and our team will verify it shortly.</p>
        <p>Regards,<br>Ortho Edge Team</p>
    ";
    $mail->AltBody = "Dear {$fullName},\n\n"
        . "Thank you for completing the payment step for {$eventName}.\n"
        . "Registration ID: {$registrationId}\n"
        . "Amount Paid: {$amountPaidDisplay}\n"
        . "Reference Number: {$paymentReferenceNumber}\n"
        . "UPI Account: {$upiAccount}\n\n"
        . "We have received your payment proof and our team will verify it shortly.\n\n"
        . "Regards,\nOrtho Edge Team";
    $mail->send();

    sendJsonResponse(200, [
        'status' => 'success',
        'message' => 'Payment details submitted successfully. We have received your proof and will verify it shortly.',
    ]);
} catch (Throwable $e) {
    $reason = $e->getMessage();
    if ($mail instanceof PHPMailer && $mail->ErrorInfo !== '') {
        $reason = $mail->ErrorInfo;
    }

    sendJsonResponse(200, [
        'status' => 'success',
        'message' => 'Payment details were saved successfully, but the email confirmation could not be sent: ' . $reason,
    ]);
}
