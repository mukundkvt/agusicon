<?php
session_start();

/* ── Credentials (change before deploying to production) ── */
define('ADMIN_USER', 'agusicon');
define('ADMIN_PASS_HASH', password_hash('Agusicon@2026', PASSWORD_DEFAULT));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user  = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['agusicon_admin'] = true;
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}

if (!empty($_SESSION['agusicon_admin'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — AGUSICON 2026</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #0a3d5c 0%, #115B86 55%, #30AEC3 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-card {
      background: #fff;
      border-radius: 20px;
      padding: 48px 44px 44px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    }
    .login-logo {
      text-align: center;
      margin-bottom: 32px;
    }
    .login-logo-mark {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #115B86, #30AEC3);
      border-radius: 14px;
      margin-bottom: 14px;
    }
    .login-logo-mark svg { color: #fff; }
    .login-title {
      font-size: 1.35rem;
      font-weight: 700;
      color: #1e3a8a;
      text-align: center;
      margin-bottom: 4px;
    }
    .login-sub {
      font-size: 0.82rem;
      color: #6b7280;
      text-align: center;
      margin-bottom: 32px;
    }
    label {
      display: block;
      font-size: 0.82rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 6px;
    }
    .field { margin-bottom: 18px; }
    input[type="text"], input[type="password"] {
      width: 100%;
      padding: 11px 14px;
      border: 1.5px solid #e5e7eb;
      border-radius: 9px;
      font-family: 'Inter', sans-serif;
      font-size: 0.92rem;
      color: #111827;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      min-height: 46px;
    }
    input:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
    }
    .btn-login {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #115B86, #30AEC3);
      color: #fff;
      border: none;
      border-radius: 9px;
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      margin-top: 8px;
      transition: opacity .2s;
    }
    .btn-login:hover { opacity: .88; }
    .error-msg {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 0.85rem;
      margin-bottom: 20px;
    }
    .back-link {
      text-align: center;
      margin-top: 20px;
      font-size: 0.8rem;
      color: #9ca3af;
    }
    .back-link a { color: #2563eb; text-decoration: none; }
  </style>
</head>
<body>
  <div class="login-card">
    <div class="login-logo">
      <div class="login-logo-mark">
        <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
          <path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>
        </svg>
      </div>
      <div class="login-title">AGUSICON 2026 Admin</div>
      <div class="login-sub">Registration data &amp; export portal</div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="error-msg">&#9888; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required />
      </div>
      <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="back-link"><a href="../pages/events/agusicon-2026-bhadohi.html">&larr; Back to event page</a></div>
  </div>
</body>
</html>
