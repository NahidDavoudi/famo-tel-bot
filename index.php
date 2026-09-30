<?php

require_once __DIR__ . '/admin/functions.php';

startSession();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// AJAX handler
if ($action === 'ajax') {
    header('Content-Type: application/json');
    try {
        requireLogin();
        $bot = initBot();
        $type = $_GET['type'] ?? '';

        switch ($type) {
            case 'bot-info': jsonResponse(getBotInfo($bot)); break;
            case 'webhook-info': jsonResponse(getWebhookInfo($bot)); break;
            case 'bot-status': jsonResponse(getBotStatus()); break;
            case 'logs': jsonResponse(getLogs((int)($_GET['limit'] ?? 50))); break;
            case 'runtime': jsonResponse(getRuntimeSummary()); break;
            case 'toggle-bot': jsonResponse(toggleBot($bot)); break;
            case 'set-webhook': jsonResponse(setWebhookAction($bot)); break;
            case 'delete-webhook': jsonResponse(deleteWebhookAction($bot)); break;
            default: jsonResponse(['error' => 'Unknown type'], false);
        }
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST handlers
if ($method === 'POST') {
    if ($action === 'login') {
        handleLogin();
    } elseif ($action === 'verify') {
        handleVerify();
    } elseif ($action === 'logout') {
        session_destroy();
        header('Location: /');
        exit;
    }
    // Fall through to render
}

// Logout
if ($action === 'logout') {
    session_destroy();
    header('Location: /');
    exit;
}

// Determine which view to show
if (!isLoggedIn()) {
    if (!empty($_SESSION['2fa_pending'])) {
        renderVerifyPage();
    } else {
        renderLoginPage();
    }
} else {
    renderDashboard();
}

// ========== HANDLER FUNCTIONS ==========

function handleLogin(): void
{
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username !== ($_ENV['ADMIN_NAME'] ?? '') || !password_verify($password, $_ENV['ADMIN_PASS'] ?? '')) {
        $_SESSION['login_error'] = 'نام کاربری یا رمز عبور اشتباه است';
        header('Location: /');
        exit;
    }

    // Generate 2FA code
    $code = generateCode();
    $_SESSION['2fa_code'] = $code;
    $_SESSION['2fa_pending'] = true;
    $_SESSION['2fa_attempts'] = 0;
    unset($_SESSION['login_error']);

    // Send code via Telegram bot
    try {
        $bot = initBot();
        $chatId = (int)($_ENV['ADMIN_CHAT_ID'] ?? 0);
        if ($chatId) {
            sendTelegram($bot, $chatId, "🔐 <b>کد تأیید دو مرحله‌ای</b>\n\nکد شما: <code>$code</code>\n\nاین کد تا 5 دقیقه معتبر است.");
            $_SESSION['2fa_sent'] = true;
            addLog('auth', 'کد ۲FA برای ادمین ارسال شد');
        } else {
            $_SESSION['2fa_error'] = 'شناسه چت ادمین تنظیم نشده است';
            unset($_SESSION['2fa_pending'], $_SESSION['2fa_code']);
            header('Location: /');
            exit;
        }
    } catch (\Throwable $e) {
        $_SESSION['2fa_error'] = 'خطا در ارسال کد: ' . $e->getMessage();
        unset($_SESSION['2fa_pending'], $_SESSION['2fa_code']);
        addLog('auth', 'خطا در ارسال کد ۲FA: ' . $e->getMessage());
        header('Location: /');
        exit;
    }

    header('Location: /?action=verify');
    exit;
}

function handleVerify(): void
{
    $code = trim($_POST['code'] ?? '');

    if (empty($_SESSION['2fa_code'])) {
        unset($_SESSION['2fa_pending']);
        header('Location: /');
        exit;
    }

    $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

    if ($_SESSION['2fa_attempts'] > 5) {
        unset($_SESSION['2fa_code'], $_SESSION['2fa_pending']);
        $_SESSION['login_error'] = 'تعداد تلاش‌های ناموفق بیش از حد مجاز';
        addLog('auth', 'تلاش‌های ناموفق ۲FA بیش از حد');
        header('Location: /');
        exit;
    }

    if ($code === $_SESSION['2fa_code']) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['logged_in_at'] = date('Y-m-d H:i:s');
        unset($_SESSION['2fa_code'], $_SESSION['2fa_pending'], $_SESSION['2fa_attempts'], $_SESSION['login_error']);
        addLog('auth', 'ادمین با موفقیت وارد شد');
        header('Location: /');
        exit;
    }

    $_SESSION['verify_error'] = 'کد وارد شده اشتباه است';
    header('Location: /?action=verify');
    exit;
}

function toggleBot(Api $bot): array
{
    $status = getBotStatus();
    $newStatus = !($status['active'] ?? false);
    setBotStatus($newStatus);

    // If activating, ensure webhook is set
    if ($newStatus) {
        $webhookUrl = $_ENV['BOT_WEBHOOK_URL'] ?? '';
        if ($webhookUrl) {
            setWebhook($bot, $webhookUrl);
        } else {
            deleteWebhook($bot);
        }
    } else {
        deleteWebhook($bot, true);
    }

    addLog('bot', $newStatus ? 'ربات فعال شد' : 'ربات غیرفعال شد');
    return ['ok' => true, 'active' => $newStatus];
}

function setWebhookAction(Api $bot): array
{
    $url = $_ENV['BOT_WEBHOOK_URL'] ?? '';
    if (!$url) {
        return ['ok' => false, 'error' => 'BOT_WEBHOOK_URL not set'];
    }
    $ok = setWebhook($bot, $url);
    addLog('webhook', $ok ? 'وب‌هوک تنظیم شد' : 'خطا در تنظیم وب‌هوک');
    return ['ok' => $ok];
}

function deleteWebhookAction(Api $bot): array
{
    $dropPending = ($_GET['drop_pending'] ?? '0') === '1';
    $ok = deleteWebhook($bot, $dropPending);
    addLog('webhook', $ok ? 'وب‌هوک حذف شد' : 'خطا در حذف وب‌هوک');
    return ['ok' => $ok];
}

function jsonResponse($data, bool $ok = true): void
{
    echo json_encode(['ok' => $ok, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ========== RENDER FUNCTIONS ==========

function renderLoginPage(): void
{
    $error = $_SESSION['login_error'] ?? '';
    unset($_SESSION['login_error']);
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ورود ادمین | NadBot</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            html { font-size: 14px; }
            body {
                font-family: 'Vazirmatn', 'Inter', sans-serif;
                background: #000;
                color: #ededed;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-container {
                width: 100%;
                max-width: 380px;
                padding: 24px;
            }
            .login-card {
                background: #0a0a0a;
                border: 1px solid #262626;
                border-radius: 12px;
                padding: 32px;
            }
            .login-header { text-align: center; margin-bottom: 28px; }
            .login-header h1 {
                font-size: 20px;
                font-weight: 700;
                color: #fff;
                margin-bottom: 6px;
            }
            .login-header p {
                font-size: 13px;
                color: #737373;
            }
            .form-group { margin-bottom: 16px; }
            .form-group label {
                display: block;
                font-size: 12px;
                font-weight: 600;
                color: #a1a1a1;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .form-group input {
                width: 100%;
                height: 40px;
                background: #0a0a0a;
                border: 1px solid #262626;
                border-radius: 6px;
                color: #fff;
                padding: 0 12px;
                font-family: inherit;
                font-size: 14px;
                outline: none;
                transition: border-color 150ms ease, box-shadow 150ms ease;
            }
            .form-group input:focus {
                border-color: #fff;
                box-shadow: 0 0 0 1px #fff;
            }
            .form-group input::placeholder { color: #525252; }
            .btn-primary {
                width: 100%;
                height: 40px;
                background: #fff;
                color: #000;
                border: 1px solid #fff;
                border-radius: 6px;
                font-family: inherit;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: background 150ms ease, transform 150ms ease;
            }
            .btn-primary:hover { background: #e5e5e5; transform: translateY(-1px); }
            .error-msg {
                background: #1a0a0a;
                border: 1px solid #3a1a1a;
                border-radius: 6px;
                padding: 10px 14px;
                font-size: 13px;
                color: #ff453a;
                margin-bottom: 16px;
                text-align: center;
            }
            .footer-text {
                text-align: center;
                margin-top: 20px;
                font-size: 12px;
                color: #525252;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h1>NadBot</h1>
                    <p>پنل مدیریت ربات تلگرام</p>
                </div>
                <?php if ($error): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="/?action=login">
                    <div class="form-group">
                        <label>نام کاربری</label>
                        <input type="text" name="username" placeholder="نام کاربری ادمین" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-primary">ورود</button>
                </form>
                <div class="footer-text">NadBot v1.0</div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function renderVerifyPage(): void
{
    $error = $_SESSION['verify_error'] ?? '';
    $sent = $_SESSION['2fa_sent'] ?? false;
    unset($_SESSION['verify_error'], $_SESSION['2fa_sent']);
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تأیید دو مرحله‌ای | NadBot</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            html { font-size: 14px; }
            body {
                font-family: 'Vazirmatn', 'Inter', sans-serif;
                background: #000;
                color: #ededed;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .verify-container {
                width: 100%;
                max-width: 380px;
                padding: 24px;
            }
            .verify-card {
                background: #0a0a0a;
                border: 1px solid #262626;
                border-radius: 12px;
                padding: 32px;
            }
            .verify-header { text-align: center; margin-bottom: 28px; }
            .verify-header h1 {
                font-size: 20px;
                font-weight: 700;
                color: #fff;
                margin-bottom: 6px;
            }
            .verify-header p {
                font-size: 13px;
                color: #737373;
                line-height: 1.6;
            }
            .verify-header .bot-msg {
                display: inline-block;
                margin-top: 12px;
                padding: 8px 16px;
                background: #111;
                border: 1px solid #262626;
                border-radius: 8px;
                font-size: 12px;
                color: #a1a1a1;
            }
            .form-group { margin-bottom: 16px; }
            .form-group label {
                display: block;
                font-size: 12px;
                font-weight: 600;
                color: #a1a1a1;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .code-input {
                width: 100%;
                height: 56px;
                background: #0a0a0a;
                border: 1px solid #262626;
                border-radius: 6px;
                color: #fff;
                padding: 0 12px;
                font-family: 'Inter', monospace;
                font-size: 28px;
                font-weight: 600;
                text-align: center;
                letter-spacing: 12px;
                outline: none;
                transition: border-color 150ms ease, box-shadow 150ms ease;
            }
            .code-input:focus {
                border-color: #fff;
                box-shadow: 0 0 0 1px #fff;
            }
            .btn-primary {
                width: 100%;
                height: 40px;
                background: #fff;
                color: #000;
                border: 1px solid #fff;
                border-radius: 6px;
                font-family: inherit;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: background 150ms ease, transform 150ms ease;
            }
            .btn-primary:hover { background: #e5e5e5; transform: translateY(-1px); }
            .btn-secondary {
                width: 100%;
                height: 40px;
                background: transparent;
                color: #a1a1a1;
                border: 1px solid #262626;
                border-radius: 6px;
                font-family: inherit;
                font-size: 13px;
                cursor: pointer;
                margin-top: 8px;
                transition: background 150ms ease, color 150ms ease;
            }
            .btn-secondary:hover { background: #111; color: #fff; }
            .error-msg {
                background: #1a0a0a;
                border: 1px solid #3a1a1a;
                border-radius: 6px;
                padding: 10px 14px;
                font-size: 13px;
                color: #ff453a;
                margin-bottom: 16px;
                text-align: center;
            }
            .success-msg {
                background: #0a1a0a;
                border: 1px solid #1a3a1a;
                border-radius: 6px;
                padding: 10px 14px;
                font-size: 13px;
                color: #00c853;
                margin-bottom: 16px;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="verify-container">
            <div class="verify-card">
                <div class="verify-header">
                    <h1>تأیید دو مرحله‌ای</h1>
                    <?php if ($sent): ?>
                        <p>یک کد تأیید به ربات تلگرام شما ارسال شد</p>
                        <div class="bot-msg">🤖 @<?= htmlspecialchars(explode(':', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '')[0]) ?></div>
                    <?php else: ?>
                        <p>کد تأیید برای شما ارسال می‌شود</p>
                    <?php endif; ?>
                </div>
                <?php if ($error): ?>
                    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="POST" action="/?action=verify" autocomplete="off">
                    <div class="form-group">
                        <label>کد تأیید ۶ رقمی</label>
                        <input class="code-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autocomplete="one-time-code">
                    </div>
                    <button type="submit" class="btn-primary">تأیید</button>
                </form>
                <form method="POST" action="/?action=logout">
                    <button type="submit" class="btn-secondary">بازگشت به صفحه ورود</button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function renderDashboard(): void
{
    $loginTime = $_SESSION['logged_in_at'] ?? '';
    $tokenConfigured = !empty($_ENV['TELEGRAM_BOT_TOKEN']);
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>داشبورد | NadBot</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            html { font-size: 14px; }
            body {
                font-family: 'Vazirmatn', 'Inter', sans-serif;
                background: #000;
                color: #ededed;
                min-height: 100vh;
            }
            .app-layout { display: flex; min-height: 100vh; }
            .sidebar {
                width: 240px;
                background: #000;
                border-left: 1px solid #262626;
                display: flex;
                flex-direction: column;
                position: fixed;
                top: 0;
                right: 0;
                height: 100vh;
                z-index: 10;
            }
            .sidebar-brand {
                padding: 20px 20px 16px;
                border-bottom: 1px solid #262626;
            }
            .sidebar-brand h1 {
                font-size: 18px;
                font-weight: 700;
                color: #fff;
            }
            .sidebar-brand span {
                font-size: 11px;
                color: #525252;
                display: block;
                margin-top: 2px;
            }
            .sidebar-nav { padding: 12px 8px; flex: 1; }
            .sidebar-nav a {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                border-radius: 6px;
                color: #737373;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
                transition: background 150ms ease, color 150ms ease;
                cursor: pointer;
            }
            .sidebar-nav a:hover, .sidebar-nav a.active {
                background: #171717;
                color: #fff;
            }
            .sidebar-nav a svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.5; }
            .sidebar-footer {
                padding: 12px 8px;
                border-top: 1px solid #262626;
            }
            .sidebar-footer a {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 12px;
                border-radius: 6px;
                color: #ff453a;
                text-decoration: none;
                font-size: 13px;
                transition: background 150ms ease;
            }
            .sidebar-footer a:hover { background: #1a0a0a; }

            .main-content {
                flex: 1;
                margin-right: 240px;
                padding: 32px;
                max-width: 1200px;
            }
            .page-header {
                margin-bottom: 28px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .page-header h1 {
                font-size: 24px;
                font-weight: 600;
                color: #fff;
            }
            .page-header .subtitle {
                font-size: 13px;
                color: #737373;
                margin-top: 2px;
            }
            .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
            .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
            .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
            .card {
                background: #0a0a0a;
                border: 1px solid #262626;
                border-radius: 12px;
                padding: 20px;
            }
            .card-title {
                font-size: 12px;
                font-weight: 600;
                color: #737373;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }
            .card-value {
                font-size: 28px;
                font-weight: 700;
                color: #fff;
            }
            .card-label {
                font-size: 12px;
                color: #525252;
                margin-top: 2px;
            }
            .table-wrap { overflow-x: auto; }
            table { width: 100%; border-collapse: collapse; }
            table th {
                text-align: right;
                font-size: 12px;
                font-weight: 600;
                color: #737373;
                padding: 10px 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-bottom: 1px solid #262626;
            }
            table td {
                padding: 10px 12px;
                font-size: 13px;
                color: #ededed;
                border-bottom: 1px solid #1a1a1a;
            }
            table tr:hover td { background: #111; }
            .badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 500;
            }
            .badge-success { color: #00c853; background: #0a1a0a; border: 1px solid #1a3a1a; }
            .badge-danger { color: #ff453a; background: #1a0a0a; border: 1px solid #3a1a1a; }
            .badge-warning { color: #f5a623; background: #1a1400; border: 1px solid #3a2a00; }
            .badge-info { color: #0070f3; background: #0a0a1a; border: 1px solid #1a1a3a; }
            .badge-neutral { color: #a1a1a1; background: #111; border: 1px solid #262626; }
            .status-dot {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 50%;
                margin-left: 6px;
            }
            .status-dot.active { background: #00c853; }
            .status-dot.inactive { background: #ff453a; }
            .status-dot.pending { background: #f5a623; }

            .toggle-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                border-radius: 6px;
                font-family: inherit;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                transition: background 150ms ease, transform 150ms ease;
                border: 1px solid #262626;
            }
            .toggle-btn.active { background: #0a1a0a; color: #00c853; border-color: #1a3a1a; }
            .toggle-btn.inactive { background: #1a0a0a; color: #ff453a; border-color: #3a1a1a; }
            .toggle-btn:hover { transform: translateY(-1px); }
            .toggle-btn.loading { opacity: 0.5; pointer-events: none; }

            .info-table { width: 100%; }
            .info-table tr td:first-child {
                color: #737373;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                width: 140px;
                padding: 8px 12px 8px 0;
                vertical-align: top;
            }
            .info-table tr td:last-child {
                color: #ededed;
                font-size: 13px;
                padding: 8px 0;
            }
            .log-time { color: #525252; font-size: 12px; font-family: 'Inter', monospace; direction: ltr; }
            .log-msg { color: #ededed; }
            .empty-state { text-align: center; padding: 40px 20px; color: #525252; }
            .skeleton { background: #111; border-radius: 4px; animation: pulse 1.5s ease-in-out infinite; }
            @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
            .webhook-url { font-family: 'Inter', monospace; font-size: 12px; color: #a1a1a1; word-break: break-all; direction: ltr; display: inline-block; }

            @media (max-width: 768px) {
                .sidebar { display: none; }
                .main-content { margin-right: 0; padding: 16px; }
                .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            }
            .mobile-nav {
                display: none;
                position: fixed;
                bottom: 0; left: 0; right: 0;
                background: #000;
                border-top: 1px solid #262626;
                padding: 8px;
                z-index: 20;
            }
            .mobile-nav a {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 2px;
                padding: 6px 12px;
                color: #737373;
                text-decoration: none;
                font-size: 10px;
            }
            .mobile-nav a.active { color: #fff; }
            .mobile-nav a svg { width: 20px; height: 20px; }
            @media (max-width: 768px) {
                .mobile-nav { display: flex; justify-content: space-around; }
                .main-content { padding-bottom: 72px; }
            }
        </style>
    </head>
    <body>
        <div class="app-layout">
            <aside class="sidebar">
                <div class="sidebar-brand">
                    <h1>NadBot</h1>
                    <span>پنل مدیریت ربات</span>
                </div>
                <nav class="sidebar-nav">
                    <a class="active" onclick="switchTab('overview')" data-tab="overview">
                        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        خلاصه
                    </a>
                    <a onclick="switchTab('bot-info')" data-tab="bot-info">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        اطلاعات ربات
                    </a>
                    <a onclick="switchTab('logs')" data-tab="logs">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        لاگ‌ها
                    </a>
                    <a onclick="switchTab('runtime')" data-tab="runtime">
                        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        اجرای ربات
                    </a>
                </nav>
                <div class="sidebar-footer">
                    <a href="/?action=logout">
                        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        خروج
                    </a>
                </div>
            </aside>

            <main class="main-content" id="main-content">
                <div id="tab-content"></div>
            </main>
        </div>

        <nav class="mobile-nav">
            <a class="active" onclick="switchTab('overview')" data-tab="overview">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                خلاصه
            </a>
            <a onclick="switchTab('bot-info')" data-tab="bot-info">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                ربات
            </a>
            <a onclick="switchTab('logs')" data-tab="logs">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                لاگ‌ها
            </a>
            <a onclick="switchTab('runtime')" data-tab="runtime">
                <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                اجرای ربات
            </a>
        </nav>

        <script>
            var tokenConfigured = <?= $tokenConfigured ? 'true' : 'false' ?>;
            var loginTime = <?= json_encode($loginTime) ?>;

            let currentTab = 'overview';

            function switchTab(tab) {
                currentTab = tab;
                document.querySelectorAll('[data-tab]').forEach(function(el) {
                    if (el.dataset.tab === tab) {
                        el.classList.add('active');
                    } else {
                        el.classList.remove('active');
                    }
                });
                loadTab(tab);
            }

            function loadTab(tab) {
                var container = document.getElementById('tab-content');
                container.innerHTML = '<div style="text-align:center;padding:40px;color:#525252;">در حال بارگذاری...</div>';

                switch (tab) {
                    case 'overview': loadOverview(container); break;
                    case 'bot-info': loadBotInfo(container); break;
                    case 'logs': loadLogs(container); break;
                    case 'runtime': loadRuntime(container); break;
                }
            }

            async function api(type, params) {
                params = params || {};
                var qs = new URLSearchParams({ action: 'ajax', type: type });
                Object.keys(params).forEach(function(k) { qs.set(k, params[k]); });
                var res = await fetch('/?' + qs.toString());
                return res.json();
            }

            function loadOverview(container) {
                Promise.all([
                    api('bot-status'),
                    api('bot-info'),
                    api('webhook-info'),
                ]).then(function(results) {
                    var statusRes = results[0], infoRes = results[1], webhookRes = results[2];
                    var status = statusRes.data || {};
                    var info = infoRes.data || {};
                    var webhook = webhookRes.data || {};
                    var isActive = status.active || false;
                    var isOnline = info && info.id ? true : false;
                    var pendingCount = webhook.pending_update_count || '—';

                    container.innerHTML =
                        '<div class="page-header">' +
                            '<div>' +
                                '<h1>خلاصه</h1>' +
                                '<div class="subtitle">نمای کلی از وضعیت ربات</div>' +
                            '</div>' +
                            '<button class="toggle-btn ' + (isActive ? 'active' : 'inactive') + '" onclick="toggleBot(this)">' +
                                '<span class="status-dot ' + (isActive ? 'active' : 'inactive') + '"></span> ' +
                                (isActive ? 'فعال' : 'غیرفعال') +
                            '</button>' +
                        '</div>' +
                        '<div class="grid-4" style="margin-bottom:16px;">' +
                            '<div class="card">' +
                                '<div class="card-title">وضعیت ربات</div>' +
                                '<div class="card-value" style="font-size:20px;">' +
                                    '<span class="status-dot ' + (isActive ? 'active' : 'inactive') + '"></span> ' +
                                    (isActive ? 'فعال' : 'غیرفعال') +
                                '</div>' +
                                '<div class="card-label">آخرین تغییر: ' + (status.updated_at || '—') + '</div>' +
                            '</div>' +
                            '<div class="card">' +
                                '<div class="card-title">اتصال به تلگرام</div>' +
                                '<div class="card-value" style="font-size:20px;">' +
                                    '<span class="status-dot ' + (isOnline ? 'active' : 'inactive') + '"></span> ' +
                                    (isOnline ? 'متصل' : 'قطع') +
                                '</div>' +
                                '<div class="card-label">API Telegram</div>' +
                            '</div>' +
                            '<div class="card">' +
                                '<div class="card-title">نام ربات</div>' +
                                '<div class="card-value" style="font-size:18px;">' + (info.first_name || '—') + '</div>' +
                                '<div class="card-label">@' + (info.username || '—') + '</div>' +
                            '</div>' +
                            '<div class="card">' +
                                '<div class="card-title">آپدیت‌های در انتظار</div>' +
                                '<div class="card-value">' + pendingCount + '</div>' +
                                '<div class="card-label">در صف وب‌هوک</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="card">' +
                            '<div class="card-title" style="margin-bottom:12px;">آخرین لاگ‌ها</div>' +
                            '<div id="overview-logs">در حال بارگذاری...</div>' +
                        '</div>';
                    loadLogsInto(document.getElementById('overview-logs'), 5);
                }).catch(function(err) {
                    container.innerHTML = '<div class="empty-state">خطا در بارگذاری: ' + err.message + '</div>';
                });
            }

            function toggleBot(btn) {
                btn.classList.add('loading');
                api('toggle-bot').then(function(res) {
                    if (res.ok) {
                        var active = res.data.active;
                        btn.className = 'toggle-btn ' + (active ? 'active' : 'inactive');
                        btn.innerHTML = '<span class="status-dot ' + (active ? 'active' : 'inactive') + '"></span> ' + (active ? 'فعال' : 'غیرفعال');
                    }
                }).finally(function() {
                    btn.classList.remove('loading');
                });
            }

            function loadBotInfo(container) {
                Promise.all([
                    api('bot-info'),
                    api('webhook-info'),
                ]).then(function(results) {
                    var infoRes = results[0], webhookRes = results[1];
                    var info = infoRes.data || {};
                    var webhook = webhookRes.data || {};
                    var whUrl = webhook.url || '—';
                    var whPending = webhook.pending_update_count || '—';
                    var whError = webhook.last_error_message || '—';
                    var whDate = webhook.last_error_date
                        ? new Date(webhook.last_error_date * 1000).toLocaleString('fa-IR')
                        : '—';

                    container.innerHTML =
                        '<div class="page-header">' +
                            '<div>' +
                                '<h1>اطلاعات ربات</h1>' +
                                '<div class="subtitle">مشخصات و وضعیت اتصال ربات تلگرام</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="grid-2" style="margin-bottom:16px;">' +
                            '<div class="card">' +
                                '<div class="card-title">مشخصات ربات</div>' +
                                '<table class="info-table">' +
                                    '<tr><td>نام</td><td>' + (info.first_name || '—') + '</td></tr>' +
                                    '<tr><td>نام کاربری</td><td>@' + (info.username || '—') + '</td></tr>' +
                                    '<tr><td>آیدی</td><td style="font-family:Inter,monospace;direction:ltr;">' + (info.id || '—') + '</td></tr>' +
                                    '<tr><td>توکن</td><td>' + (tokenConfigured ? '<span class="badge badge-success">تنظیم شده</span>' : '<span class="badge badge-danger">تنظیم نشده</span>') + '</td></tr>' +
                                '</table>' +
                            '</div>' +
                            '<div class="card">' +
                                '<div class="card-title">وب‌هوک</div>' +
                                '<table class="info-table">' +
                                    '<tr><td>وضعیت</td>' +
                                        '<td>' + (whUrl !== '—' && whUrl !== ''
                                            ? '<span class="badge badge-success">تنظیم شده</span>'
                                            : '<span class="badge badge-danger">تنظیم نشده</span>') +
                                        '</td>' +
                                    '</tr>' +
                                    '<tr><td>آدرس</td><td class="webhook-url">' + whUrl + '</td></tr>' +
                                    '<tr><td>آپدیت‌های در انتظار</td><td>' + whPending + '</td></tr>' +
                                    '<tr><td>آخرین خطا</td><td style="color:#ff453a;font-size:12px;">' + whError + '</td></tr>' +
                                    '<tr><td>زمان آخرین خطا</td><td style="font-size:12px;color:#737373;">' + whDate + '</td></tr>' +
                                '</table>' +
                            '</div>' +
                        '</div>' +
                        '<div class="card">' +
                            '<div class="card-title" style="margin-bottom:12px;">تنظیمات وب‌هوک</div>' +
                            '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
                                '<button class="toggle-btn" onclick="setWebhookAction()" style="background:#0a0a1a;color:#0070f3;border-color:#1a1a3a;">تنظیم وب‌هوک</button>' +
                                '<button class="toggle-btn inactive" onclick="deleteWebhookAction()">حذف وب‌هوک</button>' +
                            '</div>' +
                        '</div>';
                }).catch(function(err) {
                    container.innerHTML = '<div class="empty-state">خطا: ' + err.message + '</div>';
                });
            }

            function setWebhookAction() {
                api('set-webhook').then(function(res) {
                    if (res.ok) { loadTab(currentTab); }
                });
            }

            function deleteWebhookAction() {
                if (!confirm('آیا از حذف وب‌هوک اطمینان دارید؟')) return;
                api('delete-webhook', { drop_pending: '0' }).then(function(res) {
                    if (res.ok) { loadTab(currentTab); }
                });
            }

            function loadLogs(container) {
                container.innerHTML =
                    '<div class="page-header">' +
                        '<div>' +
                            '<h1>لاگ‌ها</h1>' +
                            '<div class="subtitle">۵۰ رویداد آخر</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="card" id="logs-container">' +
                        '<div style="text-align:center;padding:20px;color:#525252;">در حال بارگذاری...</div>' +
                    '</div>';
                loadLogsInto(document.getElementById('logs-container'), 50);
            }

            function loadRuntime(container) {
                api('runtime').then(function(res) {
                    var s = res.data || {};
                    var c = s.counters || {};
                    container.innerHTML =
                        '<div class="page-header">' +
                            '<div><h1>اجرای ربات</h1>' +
                            '<div class="subtitle">وضعیت هستهٔ جدید (Module 1)</div></div>' +
                        '</div>' +
                        '<div class="grid-4" style="margin-bottom:16px;">' +
                            '<div class="card"><div class="card-title">آخرین وب‌هوک</div><div class="card-value" style="font-size:16px;">' + (s.last_webhook_at || '—') + '</div></div>' +
                            '<div class="card"><div class="card-title">آخرین API موفق</div><div class="card-value" style="font-size:16px;">' + (s.last_api_ok_at || '—') + '</div></div>' +
                            '<div class="card"><div class="card-title">آخرین API ناموفق</div><div class="card-value" style="font-size:16px;">' + (s.last_api_error_at || '—') + '</div></div>' +
                            '<div class="card"><div class="card-title">آخرین drain</div><div class="card-value" style="font-size:16px;">' + (s.last_drain_at || '—') + '</div></div>' +
                        '</div>' +
                        '<div class="grid-4" style="margin-bottom:16px;">' +
                            '<div class="card"><div class="card-title">ارسال‌شده</div><div class="card-value">' + (c.outbox_sent || 0) + '</div></div>' +
                            '<div class="card"><div class="card-title">ناموفق</div><div class="card-value">' + (c.outbox_failed || 0) + '</div></div>' +
                            '<div class="card"><div class="card-title">مسدود</div><div class="card-value">' + (c.outbox_blocked || 0) + '</div></div>' +
                            '<div class="card"><div class="card-title">خطاها</div><div class="card-value">' + (c.errors || 0) + '</div></div>' +
                        '</div>' +
                        '<div class="card"><div class="card-title" style="margin-bottom:12px;">خطاهای اخیر</div>' +
                            ((s.recent_errors && s.recent_errors.length)
                                ? '<div class="table-wrap"><table><tr><th>زمان</th><th>پیام</th></tr>' +
                                  s.recent_errors.map(function(e) { return '<tr><td class="log-time">' + e.time + '</td><td>' + e.message + '</td></tr>'; }).join('') +
                                  '</table></div>'
                                : '<div class="empty-state">خطایی ثبت نشده است</div>') +
                        '</div>';
                }).catch(function(err) {
                    container.innerHTML = '<div class="empty-state">خطا: ' + err.message + '</div>';
                });
            }

            function loadLogsInto(el, limit) {
                api('logs', { limit: limit }).then(function(res) {
                    var logs = res.data || [];
                    if (!logs.length) {
                        el.innerHTML = '<div class="empty-state">هیچ لاگی ثبت نشده است</div>';
                        return;
                    }
                    var html = '<div class="table-wrap"><table>';
                    html += '<tr><th>زمان</th><th>نوع</th><th>پیام</th></tr>';
                    var badges = {
                        auth: '<span class="badge badge-info">ورود</span>',
                        bot: '<span class="badge badge-success">ربات</span>',
                        webhook: '<span class="badge badge-warning">وب‌هوک</span>',
                        error: '<span class="badge badge-danger">خطا</span>'
                    };
                    for (var i = logs.length - 1; i >= 0; i--) {
                        var log = logs[i];
                        var typeBadge = badges[log.type] || '<span class="badge badge-neutral">' + log.type + '</span>';
                        html += '<tr>' +
                            '<td class="log-time">' + log.time + '</td>' +
                            '<td>' + typeBadge + '</td>' +
                            '<td class="log-msg">' + log.message + '</td>' +
                        '</tr>';
                    }
                    html += '</table></div>';
                    el.innerHTML = html;
                }).catch(function(err) {
                    el.innerHTML = '<div class="empty-state">خطا: ' + err.message + '</div>';
                });
            }

            document.addEventListener('DOMContentLoaded', function() { loadTab('overview'); });
        </script>
    </body>
    </html><?php
}