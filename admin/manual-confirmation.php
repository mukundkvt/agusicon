<?php
session_start();
if (empty($_SESSION['agusicon_admin'])) { header('Location: index.php'); exit; }

// ── Read history log ────────────────────────────────────────
$logPath = dirname(__DIR__) . '/data/manual-confirmations-log.csv';
$history = [];
if (file_exists($logPath) && ($fh = fopen($logPath, 'r')) !== false) {
    $headers = [];
    while (($row = fgetcsv($fh)) !== false) {
        if (!$headers) { $headers = $row; continue; }
        if (count(array_filter($row)) === 0) continue; // skip blank lines
        $history[] = array_combine($headers, array_pad($row, count($headers), ''));
    }
    fclose($fh);
    $history = array_reverse($history);
}
$historyCount = count($history);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manual Confirmations — AGUSICON 2026 Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary: #1e3a8a; --accent: #2563eb;
      --bg: #f1f5f9;      --white: #ffffff;
      --border: #e2e8f0;  --text: #1e293b;
      --muted: #64748b;   --green: #059669;
      --red: #dc2626;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

    /* ── Top bar ── */
    .topbar {
      background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%);
      padding: 0 32px; height: 64px;
      display: flex; align-items: center; justify-content: space-between;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 16px rgba(0,0,0,0.18);
    }
    .topbar-brand { display: flex; align-items: center; gap: 12px; color: #fff; font-weight: 800; font-size: 1.05rem; text-decoration: none; }
    .topbar-brand-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.15); border-radius: 9px; display: flex; align-items: center; justify-content: center; }
    .topbar-right { display: flex; align-items: center; gap: 16px; }
    .topbar-right span { color: rgba(255,255,255,0.70); font-size: 0.82rem; }
    .btn-ghost {
      background: rgba(255,255,255,0.12); color: #fff;
      border: 1px solid rgba(255,255,255,0.28); border-radius: 8px;
      padding: 8px 18px; font-family: 'Inter', sans-serif;
      font-size: 0.82rem; font-weight: 600; cursor: pointer;
      text-decoration: none; transition: background .2s;
    }
    .btn-ghost:hover { background: rgba(255,255,255,0.22); }

    /* ── Layout ── */
    .main { padding: 32px; max-width: 1100px; margin: 0 auto; }
    .page-header { margin-bottom: 24px; }
    .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--accent); font-size: 0.82rem; font-weight: 600;
      text-decoration: none; margin-bottom: 14px;
    }
    .back-link:hover { color: var(--primary); }
    .page-title { font-size: 1.5rem; font-weight: 800; color: var(--primary); margin-bottom: 6px; }
    .page-sub { font-size: 0.88rem; color: var(--muted); }

    /* ── Tab bar ── */
    .tab-bar {
      display: flex; gap: 4px;
      background: var(--white); border: 1px solid var(--border);
      border-radius: 12px; padding: 5px; width: fit-content;
      margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    }
    .tab-btn {
      padding: 9px 22px; border-radius: 8px;
      font-family: 'Inter', sans-serif; font-size: 0.84rem; font-weight: 600;
      color: var(--muted); background: none; border: none; cursor: pointer;
      transition: background .15s, color .15s; display: flex; align-items: center; gap: 7px;
    }
    .tab-btn.active { background: var(--primary); color: #fff; }
    .tab-btn:not(.active):hover { background: #f1f5f9; color: var(--text); }
    .tab-badge {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 20px; height: 20px; padding: 0 6px;
      border-radius: 10px; font-size: 0.7rem; font-weight: 700;
      background: rgba(255,255,255,0.22); color: #fff;
    }
    .tab-btn:not(.active) .tab-badge { background: #e2e8f0; color: var(--muted); }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── Card ── */
    .card {
      background: var(--white); border-radius: 16px;
      border: 1px solid var(--border);
      box-shadow: 0 2px 12px rgba(0,0,0,0.05); overflow: hidden;
    }
    .card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 22px 28px 18px; border-bottom: 1px solid var(--border);
    }
    .card-title { font-size: 1rem; font-weight: 700; color: var(--primary); }
    .card-sub { font-size: 0.8rem; color: var(--muted); margin-top: 2px; }

    /* ── Send form table ── */
    .recipients-table { width: 100%; border-collapse: collapse; }
    .recipients-table thead th {
      background: #f8fafc; font-size: 0.72rem; font-weight: 700;
      color: var(--muted); text-transform: uppercase; letter-spacing: 0.07em;
      padding: 11px 14px; text-align: left; border-bottom: 1px solid var(--border);
    }
    .recipients-table tbody td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .recipients-table tbody tr:last-child td { border-bottom: none; }
    .recipients-table tbody tr:hover td { background: #fafbfd; }
    .row-num { font-size: 0.78rem; font-weight: 700; color: var(--muted); min-width: 28px; text-align: center; }

    input.cell-input {
      width: 100%; padding: 9px 12px;
      border: 1.5px solid var(--border); border-radius: 8px;
      font-family: 'Inter', sans-serif; font-size: 0.88rem; color: var(--text);
      outline: none; transition: border-color .18s, box-shadow .18s; background: #fff;
    }
    input.cell-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
    input.cell-input.error { border-color: var(--red); }

    .btn-remove {
      display: flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 8px;
      background: #fef2f2; border: 1px solid #fecaca;
      color: var(--red); cursor: pointer; font-size: 1rem;
      transition: background .18s; flex-shrink: 0;
    }
    .btn-remove:hover { background: #fee2e2; }
    .btn-remove:disabled { opacity: 0.35; cursor: default; }

    /* ── Status chips ── */
    .status-chip {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.78rem; font-weight: 600; padding: 4px 10px;
      border-radius: 20px; white-space: nowrap;
    }
    .status-chip.pending  { background: #f1f5f9; color: var(--muted); }
    .status-chip.sending  { background: #eff6ff; color: var(--accent); }
    .status-chip.sent,
    .status-chip.Sent     { background: #f0fdf4; color: var(--green); }
    .status-chip.failed,
    .status-chip.Failed   { background: #fef2f2; color: var(--red); }
    .status-chip .dot     { width: 7px; height: 7px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
    .spin { animation: spin .7s linear infinite; display: inline-block; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Card footer ── */
    .card-footer {
      padding: 20px 28px; border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      background: #fafbfd;
    }
    .btn-add {
      display: inline-flex; align-items: center; gap: 7px;
      background: #eff6ff; color: var(--accent);
      border: 1.5px solid #bfdbfe; border-radius: 9px;
      padding: 9px 20px; font-family: 'Inter', sans-serif;
      font-size: 0.85rem; font-weight: 700; cursor: pointer;
      transition: background .18s;
    }
    .btn-add:hover { background: #dbeafe; }
    .btn-send {
      display: inline-flex; align-items: center; gap: 8px;
      background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff;
      border: none; border-radius: 10px;
      padding: 12px 28px; font-family: 'Inter', sans-serif;
      font-size: 0.92rem; font-weight: 700; cursor: pointer;
      transition: opacity .18s; box-shadow: 0 2px 10px rgba(37,99,235,0.28);
    }
    .btn-send:hover { opacity: .88; }
    .btn-send:disabled { opacity: .45; cursor: default; }

    /* ── Summary banner ── */
    .summary {
      margin-top: 20px; padding: 16px 20px; border-radius: 12px;
      font-size: 0.88rem; font-weight: 600; display: none;
    }
    .summary.success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
    .summary.partial { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .summary.error   { background: #fef2f2; border: 1px solid #fecaca; color: var(--red); }

    /* ── History table ── */
    .history-table { width: 100%; border-collapse: collapse; }
    .history-table thead th {
      background: #f8fafc; font-size: 0.72rem; font-weight: 700;
      color: var(--muted); text-transform: uppercase; letter-spacing: 0.07em;
      padding: 11px 16px; text-align: left; border-bottom: 1px solid var(--border);
    }
    .history-table tbody td {
      padding: 12px 16px; border-bottom: 1px solid #f1f5f9;
      font-size: 0.86rem; vertical-align: middle;
    }
    .history-table tbody tr:last-child td { border-bottom: none; }
    .history-table tbody tr:hover td { background: #fafbfd; }

    .cell-date  { color: var(--muted); font-size: 0.8rem; white-space: nowrap; }
    .cell-name  { font-weight: 600; color: var(--text); }
    .cell-email { color: var(--accent); }
    .cell-amount { font-weight: 600; color: var(--text); }
    .cell-regid { font-family: monospace; font-size: 0.82rem; color: var(--muted); }
    .cell-error { font-size: 0.78rem; color: var(--red); max-width: 220px; }

    .history-search-bar {
      padding: 16px 20px; border-bottom: 1px solid var(--border);
      background: #fafbfd; display: flex; align-items: center; gap: 10px;
    }
    .search-input {
      flex: 1; padding: 9px 14px 9px 36px;
      border: 1.5px solid var(--border); border-radius: 9px;
      font-family: 'Inter', sans-serif; font-size: 0.88rem; color: var(--text);
      outline: none; background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%2394a3b8' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center;
      transition: border-color .18s, box-shadow .18s; max-width: 340px;
    }
    .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
    .filter-select {
      padding: 9px 32px 9px 12px; border: 1.5px solid var(--border); border-radius: 9px;
      font-family: 'Inter', sans-serif; font-size: 0.85rem; color: var(--text);
      background: #fff; outline: none; cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%2364748b' stroke-width='2.5' viewBox='0 0 24 24'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
    }
    .history-count { font-size: 0.8rem; color: var(--muted); margin-left: auto; white-space: nowrap; }

    .empty-history {
      padding: 60px 20px; text-align: center; color: var(--muted); font-size: 0.9rem;
    }
    .empty-history svg { display: block; margin: 0 auto 14px; opacity: .3; }

    /* ── Pagination ── */
    .pagination-bar {
      padding: 14px 20px; border-top: 1px solid var(--border);
      background: #fafbfd; display: flex; align-items: center; justify-content: space-between;
      gap: 12px; flex-wrap: wrap;
    }
    .page-info { font-size: 0.8rem; color: var(--muted); white-space: nowrap; }
    .page-btns { display: flex; align-items: center; gap: 4px; }
    .page-btn {
      min-width: 34px; height: 34px; padding: 0 10px;
      border: 1.5px solid var(--border); border-radius: 8px;
      font-family: 'Inter', sans-serif; font-size: 0.82rem; font-weight: 600;
      color: var(--text); background: #fff; cursor: pointer;
      transition: background .15s, border-color .15s, color .15s;
      display: inline-flex; align-items: center; justify-content: center;
    }
    .page-btn:hover:not(.active):not(:disabled) { background: #eff6ff; border-color: var(--accent); color: var(--accent); }
    .page-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; cursor: default; }
    .page-btn:disabled { opacity: .38; cursor: default; }
    .page-ellipsis { font-size: 0.82rem; color: var(--muted); padding: 0 4px; user-select: none; }
    .perpage-wrap { display: flex; align-items: center; gap: 7px; }
    .perpage-label { font-size: 0.8rem; color: var(--muted); white-space: nowrap; }
    .perpage-select-sm {
      padding: 6px 28px 6px 10px; border: 1.5px solid var(--border); border-radius: 8px;
      font-family: 'Inter', sans-serif; font-size: 0.82rem; color: var(--text);
      background: #fff; outline: none; cursor: pointer; appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='none' stroke='%2364748b' stroke-width='2.5' viewBox='0 0 24 24'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 8px center;
    }
  </style>
</head>
<body>

<nav class="topbar">
  <a href="dashboard.php" class="topbar-brand">
    <div class="topbar-brand-icon">
      <svg width="20" height="20" fill="none" stroke="#fff" stroke-width="2.2" viewBox="0 0 24 24">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
      </svg>
    </div>
    AGUSICON 2026 Admin
  </a>
  <div class="topbar-right">
    <span>Manual Confirmations</span>
    <a href="logout.php" class="btn-ghost">Log Out</a>
  </div>
</nav>

<main class="main">
  <div class="page-header">
    <a href="dashboard.php" class="back-link">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M5 12l7-7M5 12l7 7"/></svg>
      Back to Dashboard
    </a>
    <div class="page-title">Manual Payment Confirmations</div>
    <div class="page-sub">Send payment confirmation emails with PDF to delegates who paid outside the website.</div>
  </div>

  <!-- Tab bar -->
  <div class="tab-bar">
    <button class="tab-btn active" data-tab="send">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      Send Confirmations
    </button>
    <button class="tab-btn" data-tab="history">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
      Sent History
      <span class="tab-badge"><?= $historyCount ?></span>
    </button>
  </div>

  <!-- ── Tab: Send ── -->
  <div class="tab-panel active" id="tab-send">
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Recipients</div>
          <div class="card-sub">Each recipient receives an email with a signed PDF confirmation letter attached.</div>
        </div>
      </div>

      <table class="recipients-table" id="recipientsTable">
        <thead>
          <tr>
            <th style="width:40px">#</th>
            <th>Full Name <span style="color:#dc2626">*</span></th>
            <th>Email Address <span style="color:#dc2626">*</span></th>
            <th style="width:170px">Amount</th>
            <th style="width:190px">Registration ID <span style="color:#94a3b8;font-weight:400">(optional)</span></th>
            <th style="width:130px">Status</th>
            <th style="width:44px"></th>
          </tr>
        </thead>
        <tbody id="recipientsBody"></tbody>
      </table>

      <div class="card-footer">
        <button class="btn-add" id="btnAddRow">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Recipient
        </button>
        <button class="btn-send" id="btnSendAll">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Send Confirmation Emails
        </button>
      </div>
    </div>
    <div class="summary" id="summary"></div>
  </div>

  <!-- ── Tab: History ── -->
  <div class="tab-panel" id="tab-history">
    <div class="card">
      <div class="history-search-bar">
        <input type="text" class="search-input" id="historySearch" placeholder="Search by name, email or registration ID…" />
        <select class="filter-select" id="historyFilter">
          <option value="">All Statuses</option>
          <option value="Sent">Sent</option>
          <option value="Failed">Failed</option>
        </select>
        <div class="perpage-wrap">
          <span class="perpage-label">Rows:</span>
          <select class="perpage-select-sm" id="perPageSelect">
            <option value="10" selected>10</option>
            <option value="20">20</option>
            <option value="40">40</option>
            <option value="50">50</option>
          </select>
        </div>
        <span class="history-count" id="historyCount"><?= $historyCount ?> record<?= $historyCount !== 1 ? 's' : '' ?></span>
      </div>

      <?php if ($historyCount === 0): ?>
        <div class="empty-history">
          <svg width="42" height="42" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/>
          </svg>
          No manual confirmation emails sent yet.
        </div>
      <?php else: ?>
        <div style="overflow-x:auto">
          <table class="history-table" id="historyTable">
            <thead>
              <tr>
                <th>Sent At</th>
                <th>Name</th>
                <th>Email</th>
                <th>Amount</th>
                <th>Registration ID</th>
                <th>Status</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $row): ?>
                <?php
                  $status  = htmlspecialchars($row['Status'] ?? '', ENT_QUOTES, 'UTF-8');
                  $sentAt  = htmlspecialchars($row['Sent At'] ?? '', ENT_QUOTES, 'UTF-8');
                  $rName   = htmlspecialchars($row['Name']   ?? '', ENT_QUOTES, 'UTF-8');
                  $rEmail  = htmlspecialchars($row['Email']  ?? '', ENT_QUOTES, 'UTF-8');
                  $rAmt    = htmlspecialchars($row['Amount'] ?? '', ENT_QUOTES, 'UTF-8');
                  $rReg    = htmlspecialchars($row['Registration ID'] ?? '', ENT_QUOTES, 'UTF-8');
                  $rErr    = htmlspecialchars($row['Error']  ?? '', ENT_QUOTES, 'UTF-8');
                  $chipIcon = $status === 'Sent'
                    ? '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>'
                    : '<svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                ?>
                <tr class="history-row" data-name="<?= strtolower($rName) ?>" data-email="<?= strtolower($rEmail) ?>" data-regid="<?= strtolower($rReg) ?>" data-status="<?= $status ?>">
                  <td class="cell-date"><?= $sentAt ?></td>
                  <td class="cell-name"><?= $rName ?></td>
                  <td class="cell-email"><?= $rEmail ?></td>
                  <td class="cell-amount"><?= $rAmt !== '' ? $rAmt : '—' ?></td>
                  <td class="cell-regid"><?= $rReg !== '' ? $rReg : '—' ?></td>
                  <td><span class="status-chip <?= $status ?>"><?= $chipIcon ?><?= $status ?></span></td>
                  <td class="cell-error"><?= $rErr !== '' ? $rErr : '' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="pagination-bar" id="paginationBar">
          <span class="page-info" id="pageInfo"></span>
          <div class="page-btns" id="pageBtns"></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
(function () {
  // ── Tab switching ──────────────────────────────────────────
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
  });

  // ── History search / filter / pagination ──────────────────
  const searchInput  = document.getElementById('historySearch');
  const filterSelect = document.getElementById('historyFilter');
  const perPageSel   = document.getElementById('perPageSelect');
  const countEl      = document.getElementById('historyCount');
  const pageInfo     = document.getElementById('pageInfo');
  const pageBtns     = document.getElementById('pageBtns');

  let currentPage = 1;
  let perPage     = 10;

  function getFilteredRows() {
    const q      = (searchInput?.value ?? '').toLowerCase().trim();
    const status = filterSelect?.value ?? '';
    return Array.from(document.querySelectorAll('.history-row')).filter(tr => {
      const matchQ = !q || tr.dataset.name.includes(q) || tr.dataset.email.includes(q) || tr.dataset.regid.includes(q);
      const matchS = !status || tr.dataset.status === status;
      return matchQ && matchS;
    });
  }

  function getPageRange(cur, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const s = new Set([1, total]);
    for (let i = Math.max(2, cur - 2); i <= Math.min(total - 1, cur + 2); i++) s.add(i);
    return Array.from(s).sort((a, b) => a - b);
  }

  function renderPage() {
    const filtered = getFilteredRows();
    const total    = filtered.length;
    const pages    = Math.max(1, Math.ceil(total / perPage));
    if (currentPage > pages) currentPage = pages;

    const start = (currentPage - 1) * perPage;
    const end   = start + perPage;

    document.querySelectorAll('.history-row').forEach(tr => tr.style.display = 'none');
    filtered.slice(start, end).forEach(tr => tr.style.display = '');

    if (countEl) countEl.textContent = total + ' record' + (total !== 1 ? 's' : '');
    if (pageInfo) pageInfo.textContent = total === 0 ? 'No records' : `Showing ${start + 1}–${Math.min(end, total)} of ${total}`;

    if (!pageBtns) return;
    pageBtns.innerHTML = '';
    if (pages <= 1) return;

    const mkBtn = (label, page, isActive, isDisabled) => {
      const b = document.createElement('button');
      b.className = 'page-btn' + (isActive ? ' active' : '');
      b.innerHTML = label;
      b.disabled  = isDisabled;
      b.addEventListener('click', () => { if (!isDisabled && !isActive) { currentPage = page; renderPage(); } });
      return b;
    };

    pageBtns.appendChild(mkBtn('&#8592;', currentPage - 1, false, currentPage === 1));

    const range = getPageRange(currentPage, pages);
    let prev = 0;
    range.forEach(p => {
      if (p - prev > 1) {
        const el = document.createElement('span');
        el.className = 'page-ellipsis'; el.textContent = '…';
        pageBtns.appendChild(el);
      }
      pageBtns.appendChild(mkBtn(p, p, p === currentPage, false));
      prev = p;
    });

    pageBtns.appendChild(mkBtn('&#8594;', currentPage + 1, false, currentPage === pages));
  }

  searchInput  && searchInput.addEventListener('input',  () => { currentPage = 1; renderPage(); });
  filterSelect && filterSelect.addEventListener('change', () => { currentPage = 1; renderPage(); });
  perPageSel   && perPageSel.addEventListener('change',  () => { perPage = +perPageSel.value; currentPage = 1; renderPage(); });

  renderPage();

  // ── Send tab logic ─────────────────────────────────────────
  const tbody   = document.getElementById('recipientsBody');
  const btnAdd  = document.getElementById('btnAddRow');
  const btnSend = document.getElementById('btnSendAll');
  const summary = document.getElementById('summary');
  let rowCount = 0;

  function addRow(prefill) {
    rowCount++;
    const idx = rowCount;
    const tr  = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
      <td><div class="row-num">${idx}</div></td>
      <td><input class="cell-input f-name"   type="text"  placeholder="Dr. Full Name"         value="${prefill?.name   ?? ''}" /></td>
      <td><input class="cell-input f-email"  type="email" placeholder="delegate@email.com"    value="${prefill?.email  ?? ''}" /></td>
      <td><input class="cell-input f-amount" type="text"  placeholder="Rs. 5,500"             value="${prefill?.amount ?? 'Rs. 5,500'}" /></td>
      <td><input class="cell-input f-regid"  type="text"  placeholder="AGS-Bha2026-XXXXX"     value="${prefill?.regId  ?? ''}" /></td>
      <td><span class="status-chip pending"><span class="dot"></span>Pending</span></td>
      <td><button class="btn-remove" title="Remove">&#10005;</button></td>`;
    tbody.appendChild(tr);
    tr.querySelector('.btn-remove').addEventListener('click', () => {
      if (tbody.rows.length > 1) tr.remove(); else clearRow(tr);
      reindex();
    });
  }

  function clearRow(tr) {
    tr.querySelectorAll('.cell-input').forEach(i => i.value = '');
    setStatus(tr, 'pending');
  }

  function reindex() {
    Array.from(tbody.rows).forEach((tr, i) => {
      const n = tr.querySelector('.row-num');
      if (n) n.textContent = i + 1;
    });
  }

  function setStatus(tr, state, msg) {
    const icons = {
      sent:  `<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>`,
      failed:`<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    };
    const map = {
      pending: `<span class="dot"></span>Pending`,
      sending: `<span class="spin">&#8635;</span>Sending…`,
      sent:    icons.sent  + 'Sent',
      failed:  icons.failed + (msg ? msg.slice(0, 30) + (msg.length > 30 ? '…' : '') : 'Failed'),
    };
    const chip = tr.querySelector('.status-chip');
    chip.className = `status-chip ${state}`;
    chip.innerHTML = map[state] ?? map.pending;
    if (state === 'failed' && msg) chip.title = msg;
  }

  function getRows() {
    return Array.from(tbody.rows).map(tr => ({
      tr,
      name:   tr.querySelector('.f-name').value.trim(),
      email:  tr.querySelector('.f-email').value.trim(),
      amount: tr.querySelector('.f-amount').value.trim(),
      regId:  tr.querySelector('.f-regid').value.trim(),
    }));
  }

  btnAdd && btnAdd.addEventListener('click', () => addRow());

  btnSend && btnSend.addEventListener('click', async () => {
    summary.style.display = 'none';
    const rows = getRows();

    let hasError = false;
    rows.forEach(r => {
      const nameOk  = r.name  !== '';
      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(r.email);
      r.tr.querySelector('.f-name').classList.toggle('error',  !nameOk);
      r.tr.querySelector('.f-email').classList.toggle('error', !emailOk);
      if (!nameOk || !emailOk) hasError = true;
    });
    if (hasError) { showSummary('error', 'Please fill in Name and a valid Email for all rows.'); return; }

    btnSend.disabled = true;
    btnAdd.disabled  = true;
    tbody.querySelectorAll('.btn-remove, .cell-input').forEach(el => el.disabled = true);

    let sent = 0, failed = 0;
    for (const r of rows) {
      setStatus(r.tr, 'sending');
      try {
        const res  = await fetch('send-manual-confirmation.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
          body: JSON.stringify({ name: r.name, email: r.email, amount: r.amount, reg_id: r.regId }),
        });
        const json = await res.json().catch(() => ({ ok: false, error: 'Invalid server response' }));
        if (json.ok) { setStatus(r.tr, 'sent'); sent++; }
        else         { setStatus(r.tr, 'failed', json.error ?? 'Unknown error'); failed++; }
      } catch (err) {
        setStatus(r.tr, 'failed', err.message ?? 'Network error');
        failed++;
      }
    }

    btnSend.disabled = false;
    btnAdd.disabled  = false;
    tbody.querySelectorAll('.btn-remove, .cell-input').forEach(el => el.disabled = false);

    if (failed === 0)    showSummary('success', `&#10003; All ${sent} confirmation email${sent !== 1 ? 's' : ''} sent. Refresh to see them in <strong>Sent History</strong>.`);
    else if (sent === 0) showSummary('error',   `All ${failed} email${failed !== 1 ? 's' : ''} failed. Check SMTP settings.`);
    else                 showSummary('partial',  `${sent} sent, ${failed} failed. Check the failed rows above.`);
  });

  function showSummary(type, msg) {
    summary.className = `summary ${type}`;
    summary.innerHTML = msg;
    summary.style.display = 'block';
    summary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  addRow(); // start with one empty row
})();
</script>
</body>
</html>
