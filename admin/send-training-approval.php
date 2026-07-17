<?php
declare(strict_types=1);
session_start();
if (empty($_SESSION['agusicon_admin'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated.']);
    exit;
}

error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=UTF-8');

/* ── helpers ─────────────────────────────────────── */
function sendJson(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function loadPhpMailer(): bool {
    $roots = [dirname(__DIR__) . '/PHPMailer-6.9.1/src', __DIR__ . '/PHPMailer-6.9.1/src'];
    foreach ($roots as $r) {
        if (is_file("$r/PHPMailer.php")) {
            require_once "$r/Exception.php";
            require_once "$r/PHPMailer.php";
            require_once "$r/SMTP.php";
            return true;
        }
    }
    return false;
}

/* ── input ───────────────────────────────────────── */
$rows = $_POST['rows'] ?? '';
if (!$rows) sendJson(['ok' => false, 'error' => 'No rows selected.']);
$selected = json_decode($rows, true);
if (!is_array($selected) || empty($selected)) sendJson(['ok' => false, 'error' => 'Invalid selection.']);

/* ── FPDF ────────────────────────────────────────── */
require_once dirname(__DIR__) . '/fpdf/fpdf.php';

/* ── PHPMailer ───────────────────────────────────── */
if (!loadPhpMailer()) sendJson(['ok' => false, 'error' => 'Mailer not available.']);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/* ─────────────────────────────────────────────────
   PDF GENERATOR
   ──────────────────────────────────────────────── */
function generateConfirmationPdf(array $r): string {
    // Colours (RGB)
    $navy   = [11,  61,  94];   // #0b3d5e
    $blue   = [17,  91, 134];   // #115B86
    $gold   = [180, 130,  30];  // amber accent
    $green  = [5,  120,  80];
    $white  = [255, 255, 255];
    $light  = [245, 248, 252];
    $mid    = [100, 116, 139];

    class PDF extends \FPDF {
        function Header() {}   // suppress default
        function Footer() {}
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $pw = $pdf->GetPageWidth();   // 210
    $ph = $pdf->GetPageHeight();  // 297

    /* ── 1. Navy header band ────────────────────── */
    $pdf->SetFillColor(...$navy);
    $pdf->Rect(0, 0, $pw, 52, 'F');

    // Thin gold accent strip at bottom of header
    $pdf->SetFillColor(...$gold);
    $pdf->Rect(0, 50, $pw, 2, 'F');

    // Conference title
    $pdf->SetFont('Helvetica', 'B', 22);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY(0, 10);
    $pdf->Cell($pw, 10, 'AGUSICON 2026', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(180, 210, 235);
    $pdf->SetXY(0, 22);
    $pdf->Cell($pw, 6, 'National Urology Conference  |  25-26 July 2026', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY(0, 30);
    $pdf->Cell($pw, 5, 'Blessing Garden, Bhadohi, Uttar Pradesh', 0, 1, 'C');

    /* ── 2. PAYMENT CONFIRMATION headline ──────── */
    $pdf->SetFillColor(...$light);
    $pdf->Rect(0, 52, $pw, 36, 'F');

    $pdf->SetFont('Helvetica', 'B', 17);
    $pdf->SetTextColor(...$blue);
    $pdf->SetXY(0, 62);
    $pdf->Cell($pw, 10, 'PAYMENT CONFIRMATION', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$mid);
    $pdf->SetXY(0, 74);
    $pdf->Cell($pw, 5, 'Hands-on Training Enrollment  |  AGUSICON 2026', 0, 1, 'C');

    /* ── 3. Details card (white box with border) ─ */
    $cardX = 24; $cardY = 96; $cardW = $pw - 48; $cardH = 118;
    $pdf->SetFillColor(...$white);
    $pdf->SetDrawColor(...$blue);
    $pdf->SetLineWidth(0.5);
    $pdf->RoundedRect($cardX, $cardY, $cardW, $cardH, 4, 'DF');

    // Card header strip
    $pdf->SetFillColor(...$blue);
    $pdf->RoundedRect($cardX, $cardY, $cardW, 11, 4, 'F');
    // Cover bottom-rounded corners of strip
    $pdf->Rect($cardX, $cardY + 7, $cardW, 4, 'F');

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY($cardX, $cardY + 1.5);
    $pdf->Cell($cardW, 8, 'ENROLLMENT DETAILS', 0, 0, 'C');

    // Details rows
    $labels = ['Name', 'Email', 'Mobile', 'Module', 'Amount', 'Payment Reference', 'Date'];
    $values = [
        $r['name'],
        $r['email'],
        $r['mobile'],
        $r['module'],
        (strpos($r['amount'], 'Rs') !== false ? $r['amount'] : 'Rs. ' . number_format((float)$r['amount'])),
        $r['payment_ref'],
        date('d F Y', strtotime($r['submitted_at'])),
    ];

    $rowH  = 13;
    $startY = $cardY + 14;
    $labelX = $cardX + 8;
    $valX   = $cardX + 55;
    $valW   = $cardW - 63;

    foreach ($labels as $i => $label) {
        $y = $startY + ($i * $rowH);
        // Alternate row tint
        if ($i % 2 === 0) {
            $pdf->SetFillColor(248, 251, 255);
            $pdf->Rect($cardX + 0.5, $y, $cardW - 1, $rowH, 'F');
        }
        // Label
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor(...$mid);
        $pdf->SetXY($labelX, $y + 2);
        $pdf->Cell(45, 8, strtoupper($label), 0, 0, 'L');
        // Value
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$navy);
        $pdf->SetXY($valX, $y + 2);
        $pdf->Cell($valW, 8, $values[$i], 0, 0, 'L');
        // Thin separator
        if ($i < count($labels) - 1) {
            $pdf->SetDrawColor(225, 232, 242);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($cardX + 4, $y + $rowH, $cardX + $cardW - 4, $y + $rowH);
        }
    }

    /* ── 4. Green confirmed stamp ───────────────── */
    $stampY = $cardY + $cardH + 14;
    $pdf->SetFillColor(236, 253, 245);
    $pdf->SetDrawColor(...$green);
    $pdf->SetLineWidth(1.2);
    $pdf->RoundedRect($cardX, $stampY, $cardW, 28, 5, 'DF');

    $pdf->SetFillColor(...$green);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(...$green);

    // Checkmark circle
    $cx = $cardX + 16; $cy = $stampY + 14;
    $pdf->SetFillColor(...$green);
    $pdf->Circle($cx, $cy, 6, 'F');
    $pdf->SetFont('ZapfDingbats', '', 10);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY($cx - 4, $cy - 5);
    $pdf->Cell(8, 10, chr(52), 0, 0, 'C');  // ✔ in ZapfDingbats

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(...$green);
    $pdf->SetXY($cardX + 28, $stampY + 5);
    $pdf->Cell($cardW - 36, 8, 'PAYMENT RECEIVED', 0, 0, 'L');

    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(30, 100, 65);
    $pdf->SetXY($cardX + 28, $stampY + 15);
    $pdf->Cell($cardW - 36, 7, 'Your payment has been confirmed. Your seat is reserved.', 0, 0, 'L');

    /* ── 5. Footer ──────────────────────────────── */
    $footY = $ph - 28;
    $pdf->SetFillColor(...$navy);
    $pdf->Rect(0, $footY, $pw, 28, 'F');

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(180, 210, 235);
    $pdf->SetXY(0, $footY + 6);
    $pdf->Cell($pw, 5, 'AGUSICON 2026  |  Blessing Garden, Bhadohi, Uttar Pradesh', 0, 1, 'C');
    $pdf->SetXY(0, $footY + 13);
    $pdf->Cell($pw, 5, 'agusicon.com  |  agusicon2025@gmail.com', 0, 1, 'C');
    $pdf->SetFont('Helvetica', '', 7);
    $pdf->SetTextColor(130, 170, 205);
    $pdf->SetXY(0, $footY + 20);
    $pdf->Cell($pw, 5, 'This is a system-generated document. For queries, contact the secretariat.', 0, 1, 'C');

    return $pdf->Output('S');  // return as string
}

/* Helper: RoundedRect for FPDF */
// FPDF doesn't have RoundedRect natively — add it as a standalone function
function fpdfRoundedRect(\FPDF $pdf, float $x, float $y, float $w, float $h, float $r, string $style = ''): void {
    $k = $pdf->k ?? 2.8346456692913; // pt per mm
    // Use FPDF's Rect for simplicity — approximate with regular rect
    $pdf->Rect($x, $y, $w, $h, $style);
}

/* Monkey-patch RoundedRect onto FPDF at runtime */
// FPDF doesn't support rounded corners natively, so we extend it
class AgusiconPDF extends \FPDF {
    function Header() {}
    function Footer() {}
    function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = ''): void {
        $k  = $this->k;
        $hp = $this->h;
        if ($style === 'F') $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        else $op = 'S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x+$r)*$k, ($hp-$y)*$k));
        $xc = $x+$w-$r; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-$y)*$k));
        $this->_Arc($xc+$r*$MyArc, $yc-$r, $xc+$r, $yc-$r*$MyArc, $xc+$r, $yc);
        $xc = $x+$w-$r; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k, ($hp-$yc)*$k));
        $this->_Arc($xc+$r, $yc+$r*$MyArc, $xc+$r*$MyArc, $yc+$r, $xc, $yc+$r);
        $xc = $x+$r; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k, ($hp-($y+$h))*$k));
        $this->_Arc($xc-$r*$MyArc, $yc+$r, $xc-$r, $yc+$r*$MyArc, $xc-$r, $yc);
        $xc = $x+$r; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $x*$k, ($hp-$yc)*$k));
        $this->_Arc($xc-$r, $yc-$r*$MyArc, $xc-$r*$MyArc, $yc-$r, $xc, $yc-$r);
        $this->_out($op);
    }
    function Circle(float $x, float $y, float $r, string $style = 'D'): void {
        $this->Ellipse($x, $y, $r, $r, $style);
    }
    function Ellipse(float $x, float $y, float $rx, float $ry, string $style = 'D'): void {
        if ($style === 'F') $op = 'f';
        elseif ($style === 'FD' || $style === 'DF') $op = 'B';
        else $op = 'S';
        $lx = 4/3*(M_SQRT2 - 1)*$rx;
        $ly = 4/3*(M_SQRT2 - 1)*$ry;
        $k  = $this->k; $h = $this->h;
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x+$rx)*$k, ($h-$y)*$k,
            ($x+$rx)*$k, ($h-($y-$ly))*$k, ($x+$lx)*$k, ($h-($y-$ry))*$k, $x*$k, ($h-($y-$ry))*$k,
            ($x-$lx)*$k, ($h-($y-$ry))*$k, ($x-$rx)*$k, ($h-($y-$ly))*$k, ($x-$rx)*$k, ($h-$y)*$k,
            ($x-$rx)*$k, ($h-($y+$ly))*$k, ($x-$lx)*$k, ($h-($y+$ry))*$k, $x*$k, ($h-($y+$ry))*$k,
            ($x+$lx)*$k, ($h-($y+$ry))*$k, ($x+$rx)*$k, ($h-($y+$ly))*$k, ($x+$rx)*$k, ($h-$y)*$k,
            $op
        ));
    }
    protected function _Arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
}

/* ── Build PDF ───────────────────────────────────── */
function buildPdf(array $r): string {
    $navy  = [11,  61,  94];
    $blue  = [17,  91, 134];
    $green = [5,  120,  80];
    $white = [255, 255, 255];
    $light = [245, 248, 252];
    $mid   = [100, 116, 139];

    $pdf = new AgusiconPDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(false);
    $pw = $pdf->GetPageWidth();
    $ph = $pdf->GetPageHeight();

    /* ── Header band ── */
    $pdf->SetFillColor(...$navy);
    $pdf->Rect(0, 0, $pw, 54, 'F');
    $pdf->SetFillColor(180, 130, 30);
    $pdf->Rect(0, 52, $pw, 2.5, 'F');

    $pdf->SetFont('Helvetica', 'B', 23);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY(0, 9);
    $pdf->Cell($pw, 11, 'AGUSICON 2026', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(175, 210, 238);
    $pdf->SetXY(0, 22);
    $pdf->Cell($pw, 6, 'National Urology Conference  |  25\xe2\x80\x9326 July 2026', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetXY(0, 30);
    $pdf->Cell($pw, 5, 'Blessing Garden, Bhadohi, Uttar Pradesh', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(140, 185, 220);
    $pdf->SetXY(0, 38);
    $pdf->Cell($pw, 5, 'Organised by: Agra Urology Society & AGUS', 0, 1, 'C');

    /* ── Sub-header ── */
    $pdf->SetFillColor(...$light);
    $pdf->Rect(0, 54.5, $pw, 34, 'F');

    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetTextColor(...$blue);
    $pdf->SetXY(0, 62);
    $pdf->Cell($pw, 9, 'PAYMENT CONFIRMATION', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(...$mid);
    $pdf->SetXY(0, 73);
    $pdf->Cell($pw, 5, 'Hands-on Training Enrollment  \xe2\x80\x94  AGUSICON 2026', 0, 1, 'C');

    /* ── Details card ── */
    $cx = 22; $cy = 96; $cw = $pw - 44; $ch = 126;
    $pdf->SetFillColor(...$white);
    $pdf->SetDrawColor(200, 218, 235);
    $pdf->SetLineWidth(0.4);
    $pdf->RoundedRect($cx, $cy, $cw, $ch, 4, 'DF');

    // Card header strip
    $pdf->SetFillColor(...$blue);
    $pdf->RoundedRect($cx, $cy, $cw, 12, 4, 'F');
    $pdf->Rect($cx, $cy + 8, $cw, 4, 'F');

    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY($cx, $cy + 2);
    $pdf->Cell($cw, 8, 'ENROLLMENT DETAILS', 0, 0, 'C');

    // Module badge inside card header
    $modLabel = (stripos($r['module'], 'pcnl') !== false || stripos($r['module'], 'puncture') !== false) ? 'MODULE 1' : 'MODULE 2';
    $pdf->SetFont('Helvetica', 'B', 7);
    $pdf->SetTextColor(255, 220, 100);
    $pdf->SetXY($cx + $cw - 28, $cy + 3);
    $pdf->Cell(24, 6, $modLabel, 0, 0, 'C');

    $rows2 = [
        ['NAME',             $r['name']],
        ['EMAIL',            $r['email']],
        ['MOBILE',           $r['mobile']],
        ['MODULE',           wordwrap($r['module'], 48, "\n", true)],
        ['AMOUNT',           'Rs. ' . number_format((float) preg_replace('/[^0-9.]/', '', $r['amount']))],
        ['PAYMENT REFERENCE',$r['payment_ref']],
        ['SUBMISSION DATE',  date('d F Y', strtotime($r['submitted_at']))],
        ['APPROVAL DATE',    date('d F Y')],
    ];

    $rowH   = 14.5;
    $startY = $cy + 14;
    $lx     = $cx + 6;
    $vx     = $cx + 54;
    $vw     = $cw - 60;

    foreach ($rows2 as $i => [$label, $value]) {
        $y = $startY + ($i * $rowH);
        if ($i % 2 === 1) {
            $pdf->SetFillColor(247, 251, 255);
            $pdf->Rect($cx + 0.4, $y, $cw - 0.8, $rowH, 'F');
        }
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(...$mid);
        $pdf->SetXY($lx, $y + 3.5);
        $pdf->Cell(46, 6, $label, 0, 0, 'L');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(...$navy);
        $pdf->SetXY($vx, $y + 3);
        $pdf->MultiCell($vw, 5.5, $value, 0, 'L');

        if ($i < count($rows2) - 1) {
            $pdf->SetDrawColor(225, 235, 245);
            $pdf->SetLineWidth(0.15);
            $pdf->Line($cx + 4, $y + $rowH, $cx + $cw - 4, $y + $rowH);
        }
    }

    /* ── Confirmed stamp ── */
    $sY = $cy + $ch + 12;
    $pdf->SetFillColor(236, 253, 245);
    $pdf->SetDrawColor(16, 185, 129);
    $pdf->SetLineWidth(1);
    $pdf->RoundedRect($cx, $sY, $cw, 30, 5, 'DF');

    $pdf->SetFillColor(...$green);
    $pdf->Circle($cx + 18, $sY + 15, 7, 'F');
    $pdf->SetFont('Helvetica', 'B', 14);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY($cx + 12, $sY + 9);
    $pdf->Cell(12, 12, chr(10003), 0, 0, 'C');  // ✓

    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetTextColor(...$green);
    $pdf->SetXY($cx + 31, $sY + 6);
    $pdf->Cell($cw - 38, 8, 'PAYMENT RECEIVED & CONFIRMED', 0, 0, 'L');

    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->SetTextColor(21, 128, 61);
    $pdf->SetXY($cx + 31, $sY + 17);
    $pdf->Cell($cw - 38, 7, 'Your seat in the Hands-on Training module is confirmed.', 0, 0, 'L');

    /* ── Footer ── */
    $fy = $ph - 26;
    $pdf->SetFillColor(...$navy);
    $pdf->Rect(0, $fy, $pw, 26, 'F');

    $pdf->SetFont('Helvetica', 'B', 8);
    $pdf->SetTextColor(...$white);
    $pdf->SetXY(0, $fy + 5);
    $pdf->Cell($pw, 5, 'AGUSICON 2026  |  Blessing Garden, Bhadohi, Uttar Pradesh', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(175, 210, 238);
    $pdf->SetXY(0, $fy + 12);
    $pdf->Cell($pw, 5, 'agusicon.com  |  agusicon2025@gmail.com', 0, 1, 'C');

    $pdf->SetFont('Helvetica', '', 6.5);
    $pdf->SetTextColor(130, 170, 205);
    $pdf->SetXY(0, $fy + 19);
    $pdf->Cell($pw, 5, 'System-generated document. For queries contact the secretariat.', 0, 1, 'C');

    return $pdf->Output('S');
}

/* ── Send emails ─────────────────────────────────── */
$sent   = 0;
$errors = [];

foreach ($selected as $r) {
    $name   = trim($r['name']    ?? '');
    $email  = trim($r['email']   ?? '');
    $module = trim($r['module']  ?? '');
    $amount = trim($r['amount']  ?? '');
    $ref    = trim($r['payment_ref'] ?? '');
    $subAt  = trim($r['submitted_at'] ?? '');
    $mobile = trim($r['mobile']  ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email for {$name}";
        continue;
    }

    try {
        $pdfBytes = buildPdf([
            'name' => $name, 'email' => $email, 'mobile' => $mobile,
            'module' => $module, 'amount' => $amount,
            'payment_ref' => $ref, 'submitted_at' => $subAt,
        ]);

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp-relay.brevo.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ae1b45001@smtp-brevo.com';
        $mail->Password   = 'DqKY7Lsg4rUzQpWx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10;
        $mail->CharSet    = 'UTF-8';
        $mail->isHTML(true);

        $mail->setFrom('info@agusicon.com', 'AGUSICON 2026');
        $mail->addAddress($email, $name);
        $mail->addBCC('mukund.rgb@gmail.com');
        $mail->Subject = 'Payment Confirmed — AGUSICON 2026 Hands-on Training';

        $safeName   = htmlspecialchars($name);
        $safeModule = htmlspecialchars($module);
        $safeAmount = htmlspecialchars($amount);
        $safeRef    = htmlspecialchars($ref);
        $mail->Body = "
<div style='font-family:Arial,sans-serif;background:#f4f7fb;padding:32px 0;min-height:100vh'>
<div style='max-width:560px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.10)'>

  <!-- Header -->
  <div style='background:linear-gradient(135deg,#0b3d5e 0%,#115B86 100%);padding:32px 36px 24px;text-align:center'>
    <div style='font-size:22px;font-weight:800;color:#fff;letter-spacing:.02em'>AGUSICON 2026</div>
    <div style='font-size:11px;color:#b4d2ee;margin-top:4px;letter-spacing:.04em'>NATIONAL UROLOGY CONFERENCE</div>
    <div style='width:48px;height:3px;background:#b48c1e;margin:14px auto 0;border-radius:2px'></div>
  </div>

  <!-- Body -->
  <div style='padding:32px 36px'>
    <div style='font-size:18px;font-weight:700;color:#0b3d5e;margin-bottom:6px'>Payment Confirmed! ✓</div>
    <p style='font-size:14px;color:#374151;line-height:1.7;margin:0 0 22px'>
      Dear <strong>{$safeName}</strong>,<br>
      We are delighted to confirm that your payment has been received and your seat in the <strong>Hands-on Training</strong> module is confirmed for AGUSICON 2026.
    </p>

    <!-- Details box -->
    <div style='background:#f0f7ff;border:1.5px solid #bdd7f0;border-radius:10px;padding:20px 22px;margin-bottom:22px'>
      <table style='width:100%;border-collapse:collapse;font-size:13.5px'>
        <tr><td style='padding:7px 0;color:#6b7280;font-weight:700;width:42%'>MODULE</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>{$safeModule}</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>AMOUNT PAID</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>{$safeAmount}</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>PAYMENT REF.</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>{$safeRef}</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>EVENT DATE</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>25&ndash;26 July 2026</td></tr>
        <tr style='border-top:1px solid #dce9f5'><td style='padding:7px 0;color:#6b7280;font-weight:700'>VENUE</td>
            <td style='padding:7px 0;color:#0b3d5e;font-weight:600'>Blessing Garden, Bhadohi, UP</td></tr>
      </table>
    </div>

    <!-- Confirmed badge -->
    <div style='background:#ecfdf5;border:1.5px solid #6ee7b7;border-radius:10px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center'>
      <div style='width:32px;height:32px;background:#059669;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:14px;flex-shrink:0'>
        <span style='color:#fff;font-size:16px;font-weight:800'>&#10003;</span>
      </div>
      <div>
        <div style='font-size:13px;font-weight:800;color:#059669'>SEAT CONFIRMED</div>
        <div style='font-size:12px;color:#065f46;margin-top:2px'>Please find your payment confirmation PDF attached to this email.</div>
      </div>
    </div>

    <p style='font-size:13px;color:#374151;line-height:1.7;margin:0 0 10px'>
      Please carry a copy of the attached PDF (printed or on your phone) when you arrive at the training venue.
      Hands-on training sessions will be conducted on <strong>25 July 2026</strong> as per the schedule.
    </p>
    <p style='font-size:13px;color:#374151;line-height:1.7;margin:0'>
      We look forward to seeing you at AGUSICON 2026. For any queries, reply to this email or contact the secretariat.
    </p>
  </div>

  <!-- Footer -->
  <div style='background:#0b3d5e;padding:20px 36px;text-align:center'>
    <div style='font-size:12px;color:#b4d2ee'>AGUSICON 2026 &bull; agusicon.com &bull; agusicon2025@gmail.com</div>
    <div style='font-size:11px;color:#6b97b8;margin-top:6px'>Blessing Garden, Bhadohi, Uttar Pradesh</div>
  </div>
</div>
</div>";

        $mail->AltBody = "Dear {$name},\n\nYour payment for AGUSICON 2026 Hands-on Training has been confirmed.\n\nModule: {$module}\nAmount: {$amount}\nRef: {$ref}\n\nPlease keep the attached PDF as your confirmation. See you at AGUSICON 2026!";

        // Attach PDF
        $safeFn = preg_replace('/[^a-z0-9]/i', '_', $name);
        $mail->addStringAttachment($pdfBytes, "AGUSICON2026_TrainingConfirmation_{$safeFn}.pdf", PHPMailer::ENCODING_BASE64, 'application/pdf');

        $mail->send();
        $sent++;

        // ── Record in tracking log ──────────────────────
        $sentKey = md5($email . $subAt);
        $sentKeys[$sentKey] = date('Y-m-d H:i:s');
    } catch (\Throwable $e) {
        $errors[] = "Failed for {$name}: " . $e->getMessage();
    }
}

// Persist tracking log
$trackPath = dirname(__DIR__) . '/data/training-sent.json';
$existing  = [];
if (is_file($trackPath)) {
    $existing = json_decode(file_get_contents($trackPath), true) ?: [];
}
$merged = array_merge($existing, $sentKeys ?? []);
$fh = fopen($trackPath, 'w');
if ($fh && flock($fh, LOCK_EX)) {
    fwrite($fh, json_encode($merged, JSON_PRETTY_PRINT));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

sendJson([
    'ok'       => $sent > 0,
    'sent'     => $sent,
    'errors'   => $errors,
    'sentKeys' => array_keys($sentKeys ?? []),
    'message'  => $sent === 1 ? '1 confirmation email sent.' : "{$sent} confirmation emails sent.",
]);
