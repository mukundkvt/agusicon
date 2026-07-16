<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['agusicon_admin'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$regId = trim($_POST['reg_id'] ?? '');
if (!$regId) {
    echo json_encode(['ok' => false, 'error' => 'Missing registration ID']);
    exit;
}

// ── Paths ───────────────────────────────────────────────────
$DATA_DIR    = dirname(__DIR__) . '/data';
$combinedCsv = $DATA_DIR . '/registrations-combined.csv';
$paymentsCsv = $DATA_DIR . '/registration-payments.csv';

// ── CSV helpers ─────────────────────────────────────────────
function csvRead(string $path): array {
    if (!file_exists($path)) return ['headers' => [], 'rows' => []];
    $headers = [];
    $rows    = [];
    $fh = fopen($path, 'r');
    if (!$fh) return ['headers' => [], 'rows' => []];
    while (($row = fgetcsv($fh)) !== false) {
        if (!$headers) { $headers = $row; continue; }
        $rows[] = array_combine($headers, array_pad($row, count($headers), ''));
    }
    fclose($fh);
    return compact('headers', 'rows');
}

function csvWrite(string $path, array $headers, array $rows): void {
    $fh = fopen($path, 'w');
    if (!$fh) throw new RuntimeException("Cannot write $path");
    fputcsv($fh, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $h) $line[] = $row[$h] ?? '';
        fputcsv($fh, $line);
    }
    fclose($fh);
}

// ── Find registration ────────────────────────────────────────
$data    = csvRead($combinedCsv);
$headers = $data['headers'];
$rows    = $data['rows'];

$reg = null;
foreach ($rows as $i => $row) {
    if (($row['Registration ID'] ?? '') === $regId && !empty($row['Reference Number'])) {
        $reg = $row;
        break;
    }
}

if (!$reg) {
    echo json_encode(['ok' => false, 'error' => 'No payment record found for ' . $regId]);
    exit;
}

$approvedAt = date('Y-m-d H:i:s');

// ── Update combined CSV ──────────────────────────────────────
foreach ($rows as $i => $row) {
    if (($row['Registration ID'] ?? '') === $regId && !empty($row['Reference Number'])) {
        $rows[$i]['Payment Status'] = 'Payment Confirmed';
    }
}
csvWrite($combinedCsv, $headers, $rows);

// ── Update payments CSV ──────────────────────────────────────
$pd = csvRead($paymentsCsv);
if ($pd['headers']) {
    foreach ($pd['rows'] as $i => $row) {
        if (($row['Registration ID'] ?? '') === $regId) {
            $pd['rows'][$i]['Payment Status'] = 'Payment Confirmed';
        }
    }
    csvWrite($paymentsCsv, $pd['headers'], $pd['rows']);
}

// ── PDF generation (raw PDF 1.4, Type1 fonts, no deps) ──────
function pdfEsc(string $s): string {
    $s = (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}
function pt(float $x, float $y, string $t, string $f, float $sz, array $c): string {
    $e = pdfEsc($t);
    return "{$c[0]} {$c[1]} {$c[2]} rg\nBT\n/$f $sz Tf\n1 0 0 1 $x $y Tm\n($e) Tj\nET\n";
}
function fillR(float $x, float $y, float $w, float $h, float $r, float $g, float $b): string {
    return "$r $g $b rg\n$x $y $w $h re\nf\n";
}
function bordR(float $x, float $y, float $w, float $h, float $r, float $g, float $b, float $lw = 0.5): string {
    return "$r $g $b RG\n$lw w\n$x $y $w $h re\nS\n";
}
function hln(float $x1, float $x2, float $y, array $c = [0.878, 0.894, 0.929], float $lw = 0.5): string {
    return "{$c[0]} {$c[1]} {$c[2]} RG\n$lw w\n$x1 $y m\n$x2 $y l\nS\n";
}

function buildPdfStream(array $reg, string $approvedAt): string {
    $s   = '';
    $ML  = 50.0;
    $MR  = 545.28;
    $CW  = 495.28;
    $PW  = 595.28;

    $blue   = [0.118, 0.227, 0.541];
    $lblue  = [0.294, 0.506, 0.929];
    $white  = [1.0, 1.0, 1.0];
    $black  = [0.12, 0.12, 0.12];
    $muted  = [0.42, 0.42, 0.42];
    $bgBlue = [0.937, 0.945, 0.980];
    $bgGrn  = [0.925, 0.980, 0.945];
    $dGrn   = [0.054, 0.545, 0.310];

    // ── Header bar ─────────────────────────────────────────
    $s .= fillR(0, 796, $PW, 46, 0.067, 0.357, 0.525);
    $s .= pt($ML, 826, 'AGUSICON 2026', 'F2', 19, $white);
    $s .= pt($ML, 810, 'National Conference of AGUSI', 'F1', 9, [0.76, 0.92, 0.96]);
    $s .= pt(370, 827, date('d M Y', strtotime($approvedAt)), 'F2', 9, $white);
    $s .= pt(370, 812, 'Payment Confirmation Letter', 'F1', 8.5, [0.76, 0.82, 0.96]);
    $s .= "0.294 0.506 0.929 RG\n1.5 w\n0 795 m\n595.28 795 l\nS\n";

    // ── Title ────────────────────────────────────────────────
    $s .= pt(122, 756, 'PAYMENT CONFIRMATION', 'F2', 18, $blue);
    $s .= "0.294 0.506 0.929 RG\n2 w\n206 749 m\n389 749 l\nS\n";

    // ── Reg ID box ───────────────────────────────────────────
    $s .= fillR($ML, 715, $CW, 28, 0.937, 0.945, 0.980);
    $s .= bordR($ML, 715, $CW, 28, 0.765, 0.792, 0.914);
    $regIdStr = 'Registration ID: ' . ($reg['Registration ID'] ?? '');
    $s .= pt(170, 724, $regIdStr, 'F2', 12, $blue);

    // ── Event ────────────────────────────────────────────────
    $s .= pt(118, 697, $reg['Event Name'] ?? 'AGUSICON 2026 – Bhadohi', 'F2', 12, $black);
    $evSub = ($reg['Event Dates'] ?? '25-26 July 2026') . '   |   ' . ($reg['Event Location'] ?? 'Blessing Garden, Bhadohi, UP');
    $s .= pt(152, 681, $evSub, 'F1', 10, $muted);
    $s .= hln($ML, $MR, 667);

    // ── Delegate details ─────────────────────────────────────
    $s .= pt($ML, 651, 'DELEGATE DETAILS', 'F2', 8, $lblue);
    $s .= hln($ML, $MR, 645, [0.878, 0.894, 0.929], 0.3);

    $dData = [
        ['Full Name',        trim(($reg['First Name'] ?? '') . ' ' . ($reg['Last Name'] ?? ''))],
        ['Email',            $reg['Email']   ?? ''],
        ['Mobile',           $reg['Mobile']  ?? ''],
        ['Qualification',    $reg['Qualification'] ?? ''],
        ['Experience',       $reg['Years of Experience'] ?? ''],
        ['Institution',      $reg['Institution / Hospital'] ?? ''],
        ['City',             $reg['City']    ?? ''],
        ['Area of Interest', $reg['Area of Interest'] ?? ''],
    ];

    $dy   = 629.0;
    $rowH = 19.0;
    $bgAlt = [0.975, 0.979, 0.991];
    foreach ($dData as $idx => [$lbl, $val]) {
        if ($idx % 2 === 0) $s .= fillR($ML, $dy - 5, $CW, $rowH, ...$bgAlt);
        $s .= pt($ML + 8, $dy, $lbl . ':', 'F2', 8.5, $muted);
        $s .= pt($ML + 155, $dy, $val,      'F1', 8.5, $black);
        $dy -= $rowH;
    }

    // ── Payment details ──────────────────────────────────────
    $divY = $dy - 7;
    $s   .= hln($ML, $MR, $divY);
    $py   = $divY - 16;
    $s   .= pt($ML, $py, 'PAYMENT DETAILS', 'F2', 8, $lblue);
    $s   .= hln($ML, $MR, $py - 6, [0.878, 0.894, 0.929], 0.3);

    $pData = [
        ['Payment Category', $reg['Payment Category'] ?? ''],
        ['Spouse Add-on',    (($reg['Spouse Included'] ?? '') === 'Yes') ? 'Yes' : 'No'],
        ['Total Amount',     $reg['Total Amount'] ?? '—'],
        ['Amount Paid',      $reg['Amount Paid']  ?? '—'],
        ['Reference No.',    $reg['Reference Number'] ?? ''],
        ['UPI Account',      $reg['UPI Account'] ?? ''],
    ];

    $py -= 20;
    foreach ($pData as $idx => [$lbl, $val]) {
        if ($idx % 2 === 0) $s .= fillR($ML, $py - 5, $CW, $rowH, ...$bgAlt);
        $s .= pt($ML + 8,  $py, $lbl . ':', 'F2', 8.5, $muted);
        $s .= pt($ML + 155,$py, $val,        'F1', 8.5, $black);
        $py -= $rowH;
    }

    // ── Confirmed banner ─────────────────────────────────────
    $bY = $py - 22;
    $s .= fillR($ML, $bY, $CW, 58, 0.925, 0.980, 0.945);
    $s .= bordR($ML, $bY, $CW, 58, 0.2, 0.72, 0.45, 0.8);
    $s .= pt(148, $bY + 38, 'PAYMENT RECEIVED & CONFIRMED', 'F2', 13, $dGrn);
    $s .= pt(175, $bY + 20, 'Approved on: ' . date('d M Y, g:i A', strtotime($approvedAt)), 'F1', 9, [0.05, 0.38, 0.18]);

    // ── Footer ────────────────────────────────────────────────
    $s .= hln($ML, $MR, 56);
    $s .= pt($ML, 43, 'Association of Genitourinary Surgeons of India (AGUSI)   |   agusicon2025@gmail.com   |   AGUSICON 2026 Secretariat, Bhadohi, UP', 'F1', 7.5, $muted);
    $s .= pt($ML, 30, 'This is a system-generated confirmation letter. Please retain this document for your records.', 'F1', 7, [0.60, 0.60, 0.60]);

    return $s;
}

function buildPdf(array $reg, string $approvedAt): string {
    $stream    = buildPdfStream($reg, $approvedAt);
    $streamLen = strlen($stream);

    $pdf = "%PDF-1.4\n";
    $off = [];

    $off[1] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

    $off[2] = strlen($pdf);
    $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

    $off[3] = strlen($pdf);
    $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89]"
         .  " /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";

    $off[4] = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length $streamLen >>\nstream\n" . $stream . "endstream\nendobj\n";

    $off[5] = strlen($pdf);
    $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

    $off[6] = strlen($pdf);
    $pdf .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

    $xref = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    for ($i = 1; $i <= 6; $i++) {
        $pdf .= str_pad((string)$off[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";

    return $pdf;
}

// ── Load PHPMailer ───────────────────────────────────────────
function loadMailer(): void {
    $roots = [
        dirname(dirname(__DIR__)) . '/PHPMailer-6.9.1/src',
        dirname(__DIR__) . '/PHPMailer-6.9.1/src',
    ];
    foreach ($roots as $root) {
        if (is_file("$root/PHPMailer.php")) {
            require_once "$root/Exception.php";
            require_once "$root/PHPMailer.php";
            require_once "$root/SMTP.php";
            return;
        }
    }
    throw new RuntimeException('PHPMailer not found');
}

// ── Send confirmation email ──────────────────────────────────
function sendConfirmEmail(array $reg, string $pdfBytes, string $approvedAt): void {
    loadMailer();

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';
    $mail->Port       = 587;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username   = 'ae1b45001@smtp-brevo.com';
    $mail->Password   = 'DqKY7Lsg4rUzQpWx';

    $from = 'info@agusicon.com';
    $mail->setFrom($from, 'AGUSICON 2026 Secretariat');

    $name  = trim(($reg['First Name'] ?? '') . ' ' . ($reg['Last Name'] ?? ''));
    $regId = $reg['Registration ID'] ?? '';
    $mail->addAddress($reg['Email'] ?? '', $name);
    $mail->addBCC('mukund.rgb@gmail.com');

    // PDF attachment
    $mail->addStringAttachment($pdfBytes, "AGUSICON-Confirmation-{$regId}.pdf", 'base64', 'application/pdf');

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = "Payment Confirmed - {$regId} | AGUSICON 2026, Bhadohi";

    $total = $reg['Total Amount']     ?? '';
    $ref   = $reg['Reference Number'] ?? '';
    $dates = $reg['Event Dates']      ?? '25–26 July 2026';
    $venue = $reg['Event Location']   ?? 'Blessing Garden, Bhadohi, Uttar Pradesh';
    $cat   = $reg['Payment Category'] ?? '';

    $mail->Body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,sans-serif">
<div style="max-width:620px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,0.09)">

  <div style="background:linear-gradient(135deg,#115B86,#30AEC3);padding:30px 36px 26px">
    <div style="color:rgba(255,255,255,0.65);font-size:11px;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:6px">Association of Genitourinary Surgeons of India (AGUSI)</div>
    <div style="color:#fff;font-size:22px;font-weight:800;line-height:1.25">Payment Confirmation</div>
    <div style="color:rgba(255,255,255,0.70);font-size:13px;margin-top:5px">AGUSICON 2026 — Bhadohi, Uttar Pradesh</div>
  </div>

  <div style="background:#e0f7fa;border-bottom:1px solid #80deea;padding:13px 36px;display:flex;justify-content:space-between;align-items:center">
    <span style="font-size:12px;color:#6b7280">Registration ID</span>
    <span style="font-size:15px;font-weight:800;color:#115B86">{$regId}</span>
  </div>

  <div style="padding:28px 36px">
    <p style="color:#1e293b;font-size:15px;margin:0 0 18px">Dear <strong>{$name}</strong>,</p>
    <p style="color:#374151;font-size:14px;line-height:1.7;margin:0 0 22px">
      We are pleased to confirm that your payment for <strong>AGUSICON 2026, Bhadohi</strong>
      has been received and verified by our team. Your registration is now <strong>complete and confirmed</strong>.
    </p>

    <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:10px;padding:20px 24px;margin:0 0 20px">
      <div style="color:#15803d;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:12px">&#10003;&nbsp; Payment Received</div>
      <table style="width:100%;border-collapse:collapse;font-size:13.5px">
        <tr><td style="color:#6b7280;padding:5px 0;width:42%">Payment Category</td><td style="font-weight:600;color:#1e293b">{$cat}</td></tr>
        <tr><td style="color:#6b7280;padding:5px 0">Amount Paid</td><td style="font-weight:600;color:#1e293b">{$total}</td></tr>
        <tr><td style="color:#6b7280;padding:5px 0">Reference No.</td><td style="font-weight:600;color:#1e293b;font-family:monospace">{$ref}</td></tr>
        <tr><td style="color:#6b7280;padding:5px 0">Confirmed On</td><td style="font-weight:600;color:#1e293b">{$approvedAt}</td></tr>
      </table>
    </div>

    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 24px;margin:0 0 22px">
      <div style="color:#374151;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px">Event Details</div>
      <table style="width:100%;border-collapse:collapse;font-size:13.5px">
        <tr><td style="color:#6b7280;padding:4px 0;width:42%">Event</td><td style="color:#1e293b">AGUSICON 2026 – National Conference of AGUSI</td></tr>
        <tr><td style="color:#6b7280;padding:4px 0">Dates</td><td style="color:#1e293b">{$dates}</td></tr>
        <tr><td style="color:#6b7280;padding:4px 0">Venue</td><td style="color:#1e293b">{$venue}</td></tr>
      </table>
    </div>

    <p style="color:#6b7280;font-size:13px;line-height:1.65;margin:0 0 8px">
      Your <strong>Payment Confirmation Letter</strong> is attached as a PDF — please save it for your records and present it at registration on the day of the event.
    </p>
    <p style="color:#6b7280;font-size:13px;line-height:1.65;margin:0 0 28px">
      We look forward to seeing you in Bhadohi. For any queries please write to <a href="mailto:agusicon2025@gmail.com" style="color:#115B86">agusicon2025@gmail.com</a>.
    </p>

    <div style="border-top:1px solid #e5e7eb;padding-top:18px;color:#9ca3af;font-size:12px;line-height:1.7">
      <strong style="color:#374151;display:block;margin-bottom:2px">AGUSICON 2026 – Association of Genitourinary Surgeons of India</strong>
      Blessing Garden, Bhadohi, Uttar Pradesh<br>
      agusicon2025@gmail.com
    </div>
  </div>
</div>
</body></html>
HTML;

    $mail->AltBody = "Dear $name,\n\nYour payment for AGUSICON 2026, Bhadohi is confirmed.\n\nRegistration ID : $regId\nAmount Paid    : $total\nReference No.  : $ref\nConfirmed On   : $approvedAt\n\nPlease find the PDF confirmation letter attached.\n\nAGUSICON 2026 Secretariat\nagusicon2025@gmail.com";

    $mail->send();
}

// ── Execute ──────────────────────────────────────────────────
try {
    $pdfBytes = buildPdf($reg, $approvedAt);
    sendConfirmEmail($reg, $pdfBytes, $approvedAt);
    echo json_encode(['ok' => true, 'approved_at' => $approvedAt]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'CSV updated but email/PDF failed: ' . $e->getMessage()]);
}
