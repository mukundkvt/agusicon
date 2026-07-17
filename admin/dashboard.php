<?php
session_start();
if (empty($_SESSION['agusicon_admin'])) {
    header('Location: index.php');
    exit;
}

/* ── CSV paths ── */
$CSV_DIR   = dirname(__DIR__) . '/data';
$CSV_COMBINED  = $CSV_DIR . '/registrations-combined.csv';
$CSV_LEADS     = $CSV_DIR . '/registrationleads.csv';
$CSV_PAYMENTS  = $CSV_DIR . '/registration-payments.csv';
$CSV_CONTACT   = $CSV_DIR . '/lead.csv';
$CSV_STALL     = $CSV_DIR . '/stall-enquiries.csv';
$CSV_TRAINING  = $CSV_DIR . '/training-enrollments.csv';

/* ── Parse a CSV file → array of assoc rows ── */
function parseCsv(string $path): array {
    if (!file_exists($path)) return [];
    $rows    = [];
    $headers = [];
    if (($fh = fopen($path, 'r')) === false) return [];
    while (($row = fgetcsv($fh)) !== false) {
        if (empty($headers)) { $headers = $row; continue; }
        $rows[] = array_combine($headers, array_slice(array_pad($row, count($headers), ''), 0, count($headers)));
    }
    fclose($fh);
    return $rows;
}

$combined = parseCsv($CSV_COMBINED);
$leads    = parseCsv($CSV_LEADS);
$payments = parseCsv($CSV_PAYMENTS);
$contacts = parseCsv($CSV_CONTACT);
$stalls   = parseCsv($CSV_STALL);
$training = parseCsv($CSV_TRAINING);

/* ── Quick stats ── */
$paidRows  = array_filter($combined, function($r) { return !empty($r['Reference Number']); });
$totalPaid = count($paidRows);

/* ── Active tab ── */
$tab = in_array($_GET['tab'] ?? '', ['leads','payments','combined','contact','stall','training']) ? $_GET['tab'] : 'combined';

/* ── Column definitions per tab ── */
$colDefs = [
    'combined' => [
        'Submitted At','Registration ID','First Name','Last Name','Email','Mobile',
        'Qualification','Years of Experience','Institution / Hospital','City',
        'Area of Interest','Referral Source','Payment Category','Spouse Included',
        'HTO‑DFO Workshop Interest','Total Amount','Amount Paid',
        'Reference Number','UPI Account','Payment Status','Comments',
    ],
    'leads' => [
        'Submitted At','First Name','Last Name','Email','Mobile',
        'Qualification','Years of Experience','Institution / Hospital','City',
        'Area of Interest','Referral Source','Comments',
    ],
    'payments' => [
        'Submitted At','Registration ID','Payment Category','Spouse Included',
        'HTO‑DFO Workshop Interest','Base Amount','Spouse Amount','Total Amount',
        'Amount Paid','Reference Number','UPI Account','Proof File Path','Payment Status',
    ],
    'contact' => [
        'Date','First Name','Last Name','Email','Phone','Subject','Message',
    ],
    'stall' => [
        'Submitted At','Event','Company','Contact Person','Mobile','Email','Stall Interest','Message',
    ],
    'training' => [
        'Submitted At','Name','Mobile','Email','Module','Amount','Payment Reference','Proof File Path',
    ],
];

if ($tab === 'leads')         { $activeRows = $leads; }
elseif ($tab === 'payments')  { $activeRows = $payments; }
elseif ($tab === 'contact')   { $activeRows = $contacts; }
elseif ($tab === 'stall')     { $activeRows = $stalls; }
elseif ($tab === 'training')  { $activeRows = $training; }
else                          { $activeRows = $combined; }

$activeCols = $colDefs[$tab];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard — AGUSICON 2026</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <!-- DataTables + Buttons -->
  <link rel="stylesheet" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.0.2/css/buttons.dataTables.min.css" />

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #1e3a8a;
      --accent:  #2563eb;
      --bg:      #f1f5f9;
      --white:   #ffffff;
      --border:  #e2e8f0;
      --text:    #1e293b;
      --muted:   #64748b;
      --green:   #059669;
      --orange:  #d97706;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* ── Top bar ── */
    .topbar {
      background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%);
      padding: 0 32px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 16px rgba(0,0,0,0.18);
    }
    .topbar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #fff;
      font-weight: 800;
      font-size: 1.05rem;
    }
    .topbar-brand-icon {
      width: 36px; height: 36px;
      background: rgba(255,255,255,0.15);
      border-radius: 9px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .topbar-right span { color: rgba(255,255,255,0.70); font-size: 0.82rem; }
    .btn-logout {
      background: rgba(255,255,255,0.12);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.28);
      border-radius: 8px;
      padding: 8px 18px;
      font-family: 'Inter', sans-serif;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: background .2s;
    }
    .btn-logout:hover { background: rgba(255,255,255,0.22); }

    /* ── Main layout ── */
    .main { padding: 28px 32px 56px; max-width: 1680px; margin: 0 auto; }

    /* ── Stats row ── */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }
    .stat-card {
      background: var(--white);
      border-radius: 14px;
      padding: 22px 24px 20px;
      border: 1px solid var(--border);
      box-shadow: 0 1px 6px rgba(0,0,0,0.05);
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .stat-icon {
      width: 46px; height: 46px;
      border-radius: 12px;
      background: rgba(30,58,138,0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      font-size: 1.25rem;
    }
    .stat-card.green  .stat-icon { background: rgba(5,150,105,0.10); }
    .stat-card.orange .stat-icon { background: rgba(217,119,6,0.10); }
    .stat-card.indigo .stat-icon { background: rgba(79,70,229,0.10); }
    .stat-body {}
    .stat-label {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.07em;
      margin-bottom: 4px;
    }
    .stat-value {
      font-size: 1.9rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1;
    }
    .stat-card.green  .stat-value { color: var(--green); }
    .stat-card.orange .stat-value { color: var(--orange); }
    .stat-card.indigo .stat-value { color: #4f46e5; }

    /* ── Tab bar ── */
    .tab-bar {
      display: flex;
      gap: 0;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 5px;
      margin-bottom: 20px;
      width: fit-content;
      box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    }
    .tab-btn {
      padding: 9px 22px;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 0.84rem;
      font-weight: 600;
      color: var(--muted);
      background: none;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: background .15s, color .15s;
      white-space: nowrap;
    }
    .tab-btn:hover { background: var(--bg); color: var(--text); }
    .tab-btn.active { background: var(--primary); color: #fff; }

    /* ── Table card ── */
    .table-card {
      background: var(--white);
      border-radius: 16px;
      border: 1px solid var(--border);
      box-shadow: 0 2px 16px rgba(0,0,0,0.07);
      overflow: hidden;
    }

    /* Card header — title + export buttons */
    .table-card-header {
      padding: 20px 24px 18px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      background: #fafbfd;
    }
    .table-card-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 2px;
    }
    .table-card-sub {
      font-size: 0.78rem;
      color: var(--muted);
    }
    .export-btn-group {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .export-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 8px;
      font-family: 'Inter', sans-serif;
      font-size: 0.80rem;
      font-weight: 600;
      cursor: pointer;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--text);
      text-decoration: none;
      transition: all .15s;
      line-height: 1;
    }
    .export-btn:hover { background: var(--bg); }
    .export-btn.csv   { border-color: #a7f3d0; color: #065f46; background: #f0fdf4; }
    .export-btn.csv:hover { background: #dcfce7; }
    .export-btn.excel { border-color: #86efac; color: #15803d; background: #f0fdf4; }
    .export-btn.excel:hover { background: #dcfce7; }
    .export-btn.pdf   { border-color: #fca5a5; color: #b91c1c; background: #fff1f2; }
    .export-btn.pdf:hover   { background: #fee2e2; }
    .export-btn.print { border-color: #c7d2fe; color: #3730a3; background: #eef2ff; }
    .export-btn.print:hover { background: #e0e7ff; }
    .export-btn.cols  { border-color: var(--border); color: var(--muted); }
    .export-btn.cols:hover { color: var(--text); }
    .export-btn svg { flex-shrink: 0; }

    /* Toolbar row — length selector + search */
    .table-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 14px 24px;
      border-bottom: 1px solid var(--border);
      background: var(--white);
      flex-wrap: wrap;
    }
    .toolbar-left { display: flex; align-items: center; gap: 8px; }
    .toolbar-left label {
      font-size: 0.82rem;
      color: var(--muted);
      font-weight: 500;
      white-space: nowrap;
    }
    .toolbar-right { display: flex; align-items: center; gap: 8px; }
    .toolbar-right label {
      font-size: 0.82rem;
      color: var(--muted);
      font-weight: 500;
    }

    .tbl-input {
      border: 1.5px solid var(--border);
      border-radius: 8px;
      padding: 8px 12px;
      font-family: 'Inter', sans-serif;
      font-size: 0.84rem;
      color: var(--text);
      outline: none;
      background: var(--white);
      transition: border-color .2s, box-shadow .2s;
      height: 38px;
    }
    .tbl-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
    }
    select.tbl-input {
      appearance: none;
      -webkit-appearance: none;
      padding-right: 32px;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 10px center;
      cursor: pointer;
    }
    .search-wrap {
      position: relative;
    }
    .search-wrap svg {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      pointer-events: none;
    }
    .search-wrap input.tbl-input {
      padding-left: 34px;
      width: 240px;
    }

    /* Table wrap */
    .table-wrap { overflow-x: auto; }

    /* ── DataTables core overrides ── */
    div.dt-container { padding: 0; }
    div.dt-container .dt-layout-row { margin: 0; }

    /* DT layout rows — no controls, only the table row renders */
    div.dt-container .dt-layout-row { margin: 0; }

    table.dataTable {
      border-collapse: collapse !important;
      width: 100% !important;
      font-size: 0.845rem;
      margin: 0 !important;
    }
    table.dataTable thead th {
      background: #f4f7fb;
      color: #334155;
      font-weight: 700;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      padding: 12px 16px !important;
      border-bottom: 2px solid #e2e8f0 !important;
      border-top: none !important;
      white-space: nowrap;
      user-select: none;
    }
    table.dataTable thead th.dt-ordering-asc::after  { content: ' ↑'; opacity: .6; font-size: .65rem; }
    table.dataTable thead th.dt-ordering-desc::after { content: ' ↓'; opacity: .6; font-size: .65rem; }
    table.dataTable tbody td {
      padding: 11px 16px !important;
      border-bottom: 1px solid #f1f5f9 !important;
      vertical-align: middle;
      color: var(--text);
      font-size: 0.855rem;
    }
    table.dataTable tbody tr:hover td { background: #f8fafc; }
    table.dataTable tbody tr:last-child td { border-bottom: none !important; }
    table.dataTable tbody tr:nth-child(even) td { background: #fafbfd; }
    table.dataTable tbody tr:nth-child(even):hover td { background: #f3f6fb; }

    /* ── Table footer ── */
    .table-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 24px;
      border-top: 1px solid var(--border);
      background: #fafbfd;
      gap: 12px;
      flex-wrap: wrap;
    }
    .tbl-info-text {
      font-size: 0.80rem;
      color: var(--muted);
      font-weight: 500;
    }
    .tbl-pagination {
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .pg-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 34px;
      height: 34px;
      padding: 0 10px;
      border-radius: 8px;
      border: 1.5px solid var(--border);
      background: var(--white);
      font-family: 'Inter', sans-serif;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text);
      cursor: pointer;
      transition: all .15s;
      text-decoration: none;
    }
    .pg-btn:hover:not(.pg-current):not(:disabled) {
      border-color: var(--accent);
      color: var(--accent);
      background: #eff6ff;
    }
    .pg-btn.pg-current {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
    }
    .pg-btn:disabled, .pg-btn.pg-disabled {
      opacity: 0.38;
      cursor: default;
      pointer-events: none;
    }

    /* ── Status badges ── */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.70rem;
      font-weight: 700;
      white-space: nowrap;
      letter-spacing: 0.02em;
    }
    .badge-paid      { background:#dcfce7; color:#15803d; }
    .badge-confirmed { background:#bbf7d0; color:#065f46; font-weight:800; }
    .badge-regonly   { background:#e0e7ff; color:#3730a3; }
    .badge-pending   { background:#fef9c3; color:#854d0e; }
    .badge-yes       { background:#dcfce7; color:#15803d; }
    .badge-no        { background:#f1f5f9; color:#64748b; }

    /* Approve button */
    .btn-approve {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 6px 14px; border-radius: 7px;
      background: #1e3a8a; color: #fff;
      border: none; font-family: 'Inter', sans-serif;
      font-size: 0.76rem; font-weight: 700; cursor: pointer;
      transition: background .15s, opacity .15s;
      white-space: nowrap;
    }
    .btn-approve:hover { background: #1d4ed8; }
    .btn-approve:disabled { opacity: .45; cursor: default; }
    .btn-approve.loading { opacity: .6; pointer-events: none; }
    .approved-chip {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: 0.74rem; font-weight: 700;
      color: #15803d; background: #dcfce7;
      padding: 4px 10px; border-radius: 20px;
    }
    .no-pay-label { font-size: 0.74rem; color: #9ca3af; }

    @media (max-width: 900px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 640px) {
      .main { padding: 16px 14px 48px; }
      .topbar { padding: 0 16px; }
      .topbar-right span { display: none; }
      .stats-row { grid-template-columns: repeat(2, 1fr); gap: 12px; }
      .tab-bar { width: 100%; overflow-x: auto; }
      .table-toolbar { padding: 12px 16px; }
      .table-footer  { padding: 12px 16px; }
      .search-wrap input.tbl-input { width: 180px; }
      .export-btn-group { gap: 6px; }
      .export-btn span { display: none; }
    }

    /* ── Training bulk-action bar ── */
    .bulk-bar {
      display: none;
      align-items: center;
      gap: 12px;
      padding: 12px 20px;
      background: #eff6ff;
      border: 1.5px solid #bfdbfe;
      border-radius: 10px;
      margin-bottom: 14px;
      flex-wrap: wrap;
    }
    .bulk-bar.visible { display: flex; }
    .bulk-count {
      font-size: 0.85rem;
      font-weight: 600;
      color: #1e40af;
    }
    .btn-send-confirm {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 20px; border-radius: 8px;
      background: #059669; color: #fff;
      border: none; font-family: 'Inter', sans-serif;
      font-size: 0.83rem; font-weight: 700; cursor: pointer;
      transition: background .15s;
    }
    .btn-send-confirm:hover { background: #047857; }
    .btn-send-confirm:disabled { opacity: .5; cursor: default; }
    .btn-clear-sel {
      background: none; border: 1.5px solid #94a3b8;
      border-radius: 7px; padding: 7px 14px;
      font-family: 'Inter', sans-serif; font-size: 0.80rem;
      font-weight: 600; color: var(--muted); cursor: pointer;
      transition: all .15s;
    }
    .btn-clear-sel:hover { border-color: #64748b; color: var(--text); }
    table.dataTable .cb-col { width: 36px; text-align: center !important; padding: 0 8px !important; }
    table.dataTable tbody td.cb-col input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--accent); }
    table.dataTable thead th.cb-col input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; accent-color: #fff; }

    /* ── Toast notifications ── */
    #toast-container {
      position: fixed;
      bottom: 28px;
      right: 28px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    }
    .toast {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 280px;
      max-width: 400px;
      padding: 14px 18px;
      border-radius: 10px;
      font-size: 0.875rem;
      font-weight: 500;
      color: #fff;
      box-shadow: 0 8px 30px rgba(0,0,0,0.18);
      pointer-events: all;
      animation: toastIn .3s ease;
    }
    .toast.toast-success { background: #16a34a; }
    .toast.toast-error   { background: #dc2626; }
    .toast.toast-out     { animation: toastOut .35s ease forwards; }
    @keyframes toastIn  { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    @keyframes toastOut { from { opacity:1; transform:translateY(0); } to { opacity:0; transform:translateY(16px); } }
  </style>
</head>
<body>

<!-- Top bar -->
<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-brand-icon">
      <svg width="20" height="20" fill="none" stroke="#fff" stroke-width="2.2" viewBox="0 0 24 24">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
      </svg>
    </div>
    AGUSICON 2026 &mdash; Admin Dashboard
  </div>
  <div class="topbar-right">
    <span>Logged in as <strong>agusicon</strong></span>
    <a href="manual-confirmation.php" class="btn-logout">&#9993; Manual Confirmation</a>
    <a href="logout.php" class="btn-logout">Sign Out</a>
  </div>
</header>

<main class="main">

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon">📋</div>
      <div class="stat-body">
        <div class="stat-label">Total Registrations</div>
        <div class="stat-value"><?= count($leads) ?></div>
      </div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon">✅</div>
      <div class="stat-body">
        <div class="stat-label">Payments Received</div>
        <div class="stat-value"><?= $totalPaid ?></div>
      </div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon">⏳</div>
      <div class="stat-body">
        <div class="stat-label">Pending Payment</div>
        <div class="stat-value"><?= count($leads) - $totalPaid ?></div>
      </div>
    </div>
    <div class="stat-card indigo">
      <div class="stat-icon">✉️</div>
      <div class="stat-body">
        <div class="stat-label">Contact Enquiries</div>
        <div class="stat-value"><?= count($contacts) ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🏪</div>
      <div class="stat-body">
        <div class="stat-label">Stall Enquiries</div>
        <div class="stat-value"><?= count($stalls) ?></div>
      </div>
    </div>
    <div class="stat-card indigo">
      <div class="stat-icon">🔬</div>
      <div class="stat-body">
        <div class="stat-label">Training Enrollments</div>
        <div class="stat-value"><?= count($training) ?></div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <a href="?tab=combined" class="tab-btn <?= $tab === 'combined' ? 'active' : '' ?>">
      All Data (Combined)
    </a>
    <a href="?tab=leads" class="tab-btn <?= $tab === 'leads' ? 'active' : '' ?>">
      Registrations
    </a>
    <a href="?tab=payments" class="tab-btn <?= $tab === 'payments' ? 'active' : '' ?>">
      Payments
    </a>
    <a href="?tab=contact" class="tab-btn <?= $tab === 'contact' ? 'active' : '' ?>">
      Contact Enquiries
    </a>
    <a href="?tab=stall" class="tab-btn <?= $tab === 'stall' ? 'active' : '' ?>">
      Stall / Sponsorship
    </a>
    <a href="?tab=training" class="tab-btn <?= $tab === 'training' ? 'active' : '' ?>">
      Hands-on Training
    </a>
  </div>

  <?php if ($tab === 'training'): ?>
  <!-- Bulk action bar (training tab only) -->
  <div class="bulk-bar" id="bulkBar">
    <span class="bulk-count" id="bulkCount">0 selected</span>
    <button class="btn-send-confirm" id="btnSendConfirm" disabled>
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Send Payment Confirmation
    </button>
    <button class="btn-clear-sel" id="btnClearSel">Clear selection</button>
  </div>
  <?php endif; ?>

  <!-- Table card -->
  <div class="table-card">

    <!-- Card header: title + export buttons -->
    <div class="table-card-header">
      <div>
        <div class="table-card-title">
          <?php if ($tab === 'combined'): ?>All Registrations &amp; Payments
          <?php elseif ($tab === 'leads'): ?>Registration Leads (Step 1)
          <?php elseif ($tab === 'payments'): ?>Payment Records (Step 2)
          <?php elseif ($tab === 'stall'): ?>Stall &amp; Sponsorship Enquiries
          <?php elseif ($tab === 'training'): ?>Hands-on Training Enrollments
          <?php else: ?>Contact Form Enquiries
          <?php endif; ?>
        </div>
        <div class="table-card-sub">
          <?= $tab === 'contact' ? 'Contact form submissions' : 'AGUSICON 2026 — Bhadohi' ?>
          &nbsp;·&nbsp; <?= count($activeRows) ?> record<?= count($activeRows) !== 1 ? 's' : '' ?>
        </div>
      </div>
      <div class="export-btn-group" id="exportBtnGroup">
        <button class="export-btn csv" id="btnCsv">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <span>CSV</span>
        </button>
        <button class="export-btn excel" id="btnExcel">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <span>Excel</span>
        </button>
        <button class="export-btn pdf" id="btnPdf">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <span>PDF</span>
        </button>
        <button class="export-btn print" id="btnPrint">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          <span>Print</span>
        </button>
        <button class="export-btn cols" id="btnCols">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          <span>Columns</span>
        </button>
      </div>
    </div>

    <!-- Toolbar: length selector + search -->
    <div class="table-toolbar">
      <div class="toolbar-left">
        <label for="tblLength">Show</label>
        <select id="tblLength" class="tbl-input" style="width:80px">
          <option value="10">10</option>
          <option value="25" selected>25</option>
          <option value="50">50</option>
          <option value="100">100</option>
          <option value="-1">All</option>
        </select>
        <label>rows</label>
      </div>
      <div class="toolbar-right">
        <div class="search-wrap">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="tblSearch" type="search" class="tbl-input" placeholder="Search registrations…" />
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap">
      <table id="regTable" class="dataTable">
        <thead>
          <tr>
            <?php if ($tab === 'training'): ?>
              <th class="cb-col no-export"><input type="checkbox" id="chkAll" title="Select all"></th>
            <?php endif; ?>
            <?php foreach ($activeCols as $col): ?>
              <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
            <?php if ($tab === 'combined'): ?>
              <th class="no-export" style="min-width:120px">Actions</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($activeRows as $idx => $row): ?>
            <tr <?php if ($tab === 'training') echo 'class="tr-training"'; ?>>
              <?php if ($tab === 'training'):
                $rowJson = htmlspecialchars(json_encode([
                    'name'         => $row['Name']              ?? '',
                    'email'        => $row['Email']             ?? '',
                    'mobile'       => $row['Mobile']            ?? '',
                    'module'       => $row['Module']            ?? '',
                    'amount'       => $row['Amount']            ?? '',
                    'payment_ref'  => $row['Payment Reference'] ?? '',
                    'submitted_at' => $row['Submitted At']      ?? '',
                ]), ENT_QUOTES); ?>
              <td class="cb-col no-export"><input type="checkbox" class="row-cb" data-row='<?= $rowJson ?>'></td>
              <?php endif; ?>
              <?php foreach ($activeCols as $col): ?>
                <?php
                  $val = $row[$col] ?? '';
                  // Render badges for certain columns
                  if ($col === 'Payment Status') {
                      if ($val === 'Payment Confirmed') echo '<td><span class="badge badge-confirmed">&#10003; Confirmed</span></td>';
                      elseif ($val === 'Payment Received') echo '<td><span class="badge badge-paid">Paid</span></td>';
                      elseif ($val === 'Registration Only') echo '<td><span class="badge badge-regonly">Reg Only</span></td>';
                      else echo '<td><span class="badge badge-pending">' . htmlspecialchars($val ?: 'Pending') . '</span></td>';
                  } elseif (in_array($col, ['Spouse Included','HTO‑DFO Workshop Interest','Terms Accepted'])) {
                      $yn = strtolower($val);
                      if ($yn === 'yes') echo '<td><span class="badge badge-yes">Yes</span></td>';
                      else echo '<td><span class="badge badge-no">' . htmlspecialchars($val ?: 'No') . '</span></td>';
                  } elseif ($col === 'Subject') {
                      $colors = [
                          'Event Registration Enquiry' => ['#dbeafe','#1e40af'],
                          'Sponsorship & Exhibition'   => ['#fef3c7','#92400e'],
                          'Faculty / Speaker Enquiry'  => ['#ede9fe','#5b21b6'],
                          'Abstract Submission'        => ['#d1fae5','#065f46'],
                          'General Enquiry'            => ['#f1f5f9','#475569'],
                      ];
                      [$bg, $fg] = $colors[$val] ?? ['#f1f5f9','#475569'];
                      echo '<td><span class="badge" style="background:' . $bg . ';color:' . $fg . '">' . htmlspecialchars($val) . '</span></td>';
                  } elseif ($col === 'Message') {
                      echo '<td style="max-width:320px;white-space:normal;font-size:.82rem;color:#374151">' . htmlspecialchars($val) . '</td>';
                  } elseif ($col === 'Proof File Path' && $val) {
                      $fname = basename($val);
                      echo '<td><a href="../data/' . htmlspecialchars($val) . '" target="_blank" style="color:#2563eb;font-size:.78rem">' . htmlspecialchars($fname) . '</a></td>';
                  } elseif (in_array($col, ['Total Amount','Amount Paid','Base Amount','Spouse Amount']) && $val !== '') {
                      $numeric = (float) str_replace(['Rs. ', ',', ' '], '', $val);
                      echo '<td>' . htmlspecialchars($numeric > 0 ? '₹ ' . number_format($numeric) : $val) . '</td>';
                  } else {
                      echo '<td>' . htmlspecialchars($val) . '</td>';
                  }
                ?>
              <?php endforeach; ?>
              <?php if ($tab === 'combined'):
                $ref    = $row['Reference Number'] ?? '';
                $status = $row['Payment Status']   ?? '';
                $rid    = htmlspecialchars($row['Registration ID'] ?? '');
                $confirmed = in_array($status, ['Payment Confirmed', 'Payment Received']);
              ?>
              <td class="no-export">
                <?php if ($confirmed): ?>
                  <span class="approved-chip">&#10003; Approved</span>
                <?php elseif ($ref !== ''): ?>
                  <button class="btn-approve" data-reg="<?= $rid ?>">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    Approve
                  </button>
                <?php else: ?>
                  <span class="no-pay-label">No payment yet</span>
                <?php endif; ?>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div><!-- .table-wrap -->

    <!-- Footer: info + pagination -->
    <div class="table-footer">
      <div class="tbl-info-text" id="tblInfo"></div>
      <div class="tbl-pagination" id="tblPaging"></div>
    </div>

  </div><!-- .table-card -->

</main>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.2/js/buttons.colVis.min.js"></script>

<script>
$(function () {
  var table = $('#regTable').DataTable({
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100, -1],
    order: [[0, 'desc']],
    scrollX: true,
    dom: 'rt',
    columnDefs: [
      { targets: '.no-export', orderable: false, searchable: false }
    ],
    buttons: [
      { extend: 'csvHtml5',   title: 'AGUSICON_Registrations',
        exportOptions: { columns: ':visible:not(.no-export)' } },
      { extend: 'excelHtml5', title: 'AGUSICON_Registrations',
        exportOptions: { columns: ':visible:not(.no-export)' } },
      { extend: 'pdfHtml5',   title: 'AGUSICON 2026 Registrations — Bhadohi',
        orientation: 'landscape', pageSize: 'A3',
        exportOptions: { columns: ':visible:not(.no-export)' } },
      { extend: 'print',      exportOptions: { columns: ':visible:not(.no-export)' } },
      { extend: 'colvis',     postfixButtons: ['colvisRestore'] }
    ]
  });

  /* ── Approve payment ─────────────────────────────────────── */
  $('#regTable').on('click', '.btn-approve', function () {
    var btn   = $(this);
    var regId = btn.data('reg');
    if (!confirm('Approve payment for ' + regId + ' and send confirmation email to the delegate?')) return;

    btn.addClass('loading').text('Sending…');

    $.post('approve-payment.php', { reg_id: regId }, function (res) {
      if (res.ok) {
        /* Replace button with approved chip */
        btn.closest('td').html('<span class="approved-chip">&#10003; Approved</span>');
        /* Update the Payment Status badge in this row */
        var row = btn.closest('tr');
        row.find('.badge-regonly, .badge-pending, .badge-paid').each(function () {
          $(this).removeClass('badge-regonly badge-pending badge-paid')
                 .addClass('badge-confirmed')
                 .html('&#10003; Confirmed');
        });
        showToast('&#10003; Approved — confirmation email sent to delegate.', 'success');
      } else {
        btn.removeClass('loading').text('Approve');
        showToast('&#9888; ' + (res.error || 'Approval failed. Try again.'), 'error');
      }
    }, 'json').fail(function () {
      btn.removeClass('loading').text('Approve');
      showToast('&#9888; Network error. Please try again.', 'error');
    });
  });

  /* ── Training tab: checkbox select + bulk confirm ───────── */
  (function () {
    var bulkBar  = $('#bulkBar');
    var bulkCnt  = $('#bulkCount');
    var btnSend  = $('#btnSendConfirm');
    var btnClear = $('#btnClearSel');

    function getSelected() {
      var rows = [];
      $('.row-cb:checked').each(function () {
        rows.push($(this).data('row'));
      });
      return rows;
    }

    function updateBar() {
      var n = $('.row-cb:checked').length;
      if (n > 0) {
        bulkBar.addClass('visible');
        bulkCnt.text(n + ' selected');
        btnSend.prop('disabled', false);
      } else {
        bulkBar.removeClass('visible');
        btnSend.prop('disabled', true);
      }
      // Sync select-all state
      var total = $('.row-cb').length;
      $('#chkAll').prop('indeterminate', n > 0 && n < total);
      $('#chkAll').prop('checked', n === total && total > 0);
    }

    // Select all / deselect all
    $(document).on('change', '#chkAll', function () {
      $('.row-cb').prop('checked', this.checked);
      updateBar();
    });

    // Individual checkboxes
    $(document).on('change', '.row-cb', updateBar);

    // Clear selection
    btnClear.on('click', function () {
      $('.row-cb, #chkAll').prop('checked', false).prop('indeterminate', false);
      updateBar();
    });

    // Send confirmation
    btnSend.on('click', function () {
      var rows = getSelected();
      if (!rows.length) return;
      var names = rows.map(function(r){ return r.name; }).join(', ');
      if (!confirm('Send Payment Confirmation email (with PDF) to:\n' + names + '\n\nProceed?')) return;

      btnSend.prop('disabled', true).text('Sending…');

      $.post('send-training-approval.php',
        { rows: JSON.stringify(rows) },
        function (res) {
          btnSend.prop('disabled', false).html('<svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Payment Confirmation');
          if (res.ok) {
            showToast('&#10003; ' + res.message, 'success');
            $('.row-cb, #chkAll').prop('checked', false);
            updateBar();
          } else {
            var msg = res.errors && res.errors.length ? res.errors[0] : (res.error || 'Failed. Please try again.');
            showToast('&#9888; ' + msg, 'error');
          }
        }, 'json'
      ).fail(function () {
        btnSend.prop('disabled', false).text('Send Payment Confirmation');
        showToast('&#9888; Network error. Please try again.', 'error');
      });
    });
  })();

  /* ── Wire our custom toolbar controls ── */
  $('#tblLength').on('change', function () {
    table.page.len(+this.value).draw();
  });
  $('#tblSearch').on('input', function () {
    table.search(this.value).draw();
  });

  /* ── Wire our export buttons to DT Buttons ── */
  $('#btnCsv'  ).on('click', function () { table.button(0).trigger(); });
  $('#btnExcel').on('click', function () { table.button(1).trigger(); });
  $('#btnPdf'  ).on('click', function () { table.button(2).trigger(); });
  $('#btnPrint').on('click', function () { table.button(3).trigger(); });
  $('#btnCols' ).on('click', function () { table.button(4).trigger(); });

  /* ── Render custom info + pagination after every draw ── */
  function renderFooter() {
    var info = table.page.info();
    var total = info.recordsDisplay;
    var start = total === 0 ? 0 : info.start + 1;
    var end   = info.end;
    var pages = info.pages;
    var page  = info.page; // 0-indexed

    /* Info text */
    if (info.recordsDisplay < info.recordsTotal) {
      $('#tblInfo').text('Showing ' + start + '–' + end + ' of ' + total + ' filtered records (total ' + info.recordsTotal + ')');
    } else {
      $('#tblInfo').text('Showing ' + start + '–' + end + ' of ' + total + ' record' + (total !== 1 ? 's' : ''));
    }

    /* Pagination */
    var html = '';
    var prev = page === 0;
    var next = page >= pages - 1;
    html += '<button class="pg-btn' + (prev ? ' pg-disabled' : '') + '" data-p="' + (page - 1) + '" ' + (prev ? 'disabled' : '') + '>‹</button>';

    /* Show max 7 page buttons with ellipsis */
    var range = [];
    if (pages <= 7) {
      for (var i = 0; i < pages; i++) range.push(i);
    } else {
      range = [0];
      if (page > 2) range.push('…');
      for (var i = Math.max(1, page - 1); i <= Math.min(pages - 2, page + 1); i++) range.push(i);
      if (page < pages - 3) range.push('…');
      range.push(pages - 1);
    }
    range.forEach(function(p) {
      if (p === '…') { html += '<span style="padding:0 4px;color:var(--muted);line-height:34px">…</span>'; return; }
      html += '<button class="pg-btn' + (p === page ? ' pg-current' : '') + '" data-p="' + p + '">' + (p + 1) + '</button>';
    });

    html += '<button class="pg-btn' + (next ? ' pg-disabled' : '') + '" data-p="' + (page + 1) + '" ' + (next ? 'disabled' : '') + '>›</button>';
    $('#tblPaging').html(html);
  }

  table.on('draw', renderFooter);
  renderFooter();

  /* Pagination click */
  $('#tblPaging').on('click', '.pg-btn:not(.pg-disabled)', function () {
    table.page(+$(this).data('p')).draw('page');
  });
});

/* ── Toast notifications ── */
function showToast(message, type) {
  type = type || 'success';
  var $c = $('#toast-container');
  if (!$c.length) { $c = $('<div id="toast-container"></div>').appendTo('body'); }
  var $t = $('<div class="toast toast-' + type + '"></div>').html(message);
  $c.append($t);
  setTimeout(function () {
    $t.addClass('toast-out');
    setTimeout(function () { $t.remove(); }, 370);
  }, 4000);
}
</script>
</body>
</html>
