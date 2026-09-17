<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/totp.php';

$userId = pending_mfa_user_id();
if (!$userId) {
    header('Location: /login.php');
    exit;
}

$conn = db();
$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userRow || empty($userRow['mfa_enabled'])) {
    header('Location: /login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = trim($_POST['code'] ?? '');
    $useRecovery = !empty($_POST['use_recovery']);

    if ($useRecovery) {
        $recoveryCodes = json_decode($userRow['mfa_recovery_codes'] ?? '[]', true) ?: [];
        if (totp_consume_recovery_code($recoveryCodes, $code)) {
            $upd = $conn->prepare('UPDATE users SET mfa_recovery_codes = ? WHERE id = ?');
            $json = json_encode($recoveryCodes);
            $upd->bind_param('si', $json, $userId);
            $upd->execute();
            $upd->close();
            login_user($userRow);
            header('Location: /dashboard.php');
            exit;
        }
        $error = 'That recovery code is invalid or has already been used.';
    } elseif (totp_verify($userRow['mfa_secret'], $code)) {
        login_user($userRow);
        header('Location: /dashboard.php');
        exit;
    } else {
        $error = 'Incorrect code. Codes refresh every 30 seconds — check your app and try the current one.';
    }
}

$pageTitle = 'Verify your identity';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <h1>Enter your authentication code</h1>
    <p class="helper">Open your authenticator app and enter the current 6-digit code for <?= e(SITE_NAME) ?>.</p>

    <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="panel">
        <?= csrf_field() ?>
        <label for="code">6-digit code</label>
        <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required autocomplete="one-time-code">
        <div style="margin-top:20px;">
            <button type="submit">Verify</button>
        </div>
    </form>

    <form method="post" style="margin-top:14px;">
        <?= csrf_field() ?>
        <input type="hidden" name="use_recovery" value="1">
        <label for="recovery_code" class="helper" style="margin-top:0;">Lost your device? Use a recovery code instead:</label>
        <div style="display:flex;gap:8px;">
            <input type="text" id="recovery_code" name="code" placeholder="XXXXXXXX" style="flex:1;">
            <button type="submit" class="btn-secondary">Use code</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
