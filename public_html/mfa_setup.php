<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/totp.php';

$user = require_login();
start_secure_session();
$conn = db();
$error = null;
$recoveryCodesToShow = null;

// Generate (or reuse) a pending secret for this setup session, not saved
// to the user's account until they prove they can generate a valid code.
if (empty($_SESSION['mfa_pending_secret'])) {
    $_SESSION['mfa_pending_secret'] = totp_generate_secret();
}
$secret = $_SESSION['mfa_pending_secret'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = trim($_POST['code'] ?? '');

    if (totp_verify($secret, $code)) {
        $recovery = totp_generate_recovery_codes();
        $recoveryJson = json_encode($recovery['hashed']);

        $stmt = $conn->prepare('UPDATE users SET mfa_secret = ?, mfa_enabled = 1, mfa_recovery_codes = ? WHERE id = ?');
        $stmt->bind_param('ssi', $secret, $recoveryJson, $user['id']);
        $stmt->execute();
        $stmt->close();

        unset($_SESSION['mfa_pending_secret']);
        $recoveryCodesToShow = $recovery['plain'];
    } else {
        $error = 'That code didn\'t match. Make sure your authenticator app is showing the current code and try again.';
    }
}

$provisioningUri = totp_provisioning_uri($secret, $user['email']);

$pageTitle = 'Set up two-factor authentication';
require __DIR__ . '/includes/header.php';
?>
<div style="max-width:520px;">
<h1>Set up two-factor authentication</h1>

<?php if ($recoveryCodesToShow): ?>
    <div class="panel">
        <div class="success-box">Two-factor authentication is now enabled.</div>
        <h2>Save your recovery codes</h2>
        <p class="helper">If you lose access to your authenticator app, each of these codes can be used once to sign in instead. Save them somewhere safe. They won't be shown again.</p>
        <div class="mono panel" style="background:var(--bg);line-height:2;">
            <?php foreach ($recoveryCodesToShow as $rc): ?>
                <?= e($rc) ?><br>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:16px;"><a class="btn" href="/account.php">Done</a></div>
    </div>
<?php else: ?>
    <div class="panel">
        <p class="helper">1. Scan this QR code with Google Authenticator, Authy, or any TOTP app.</p>
        <div id="qrcode" style="background:#fff;padding:16px;display:inline-block;border-radius:4px;"></div>
        <p class="helper" style="margin-top:14px;">Can't scan? Enter this key manually:</p>
        <div class="mono panel" style="background:var(--bg);word-break:break-all;"><?= e($secret) ?></div>
    </div>

    <div class="panel" style="margin-top:16px;">
        <p class="helper">2. Enter the 6-digit code your app is showing now to confirm setup.</p>
        <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="code">6-digit code</label>
            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autofocus required autocomplete="one-time-code">
            <div style="margin-top:16px;">
                <button type="submit">Confirm and enable</button>
                <a class="btn btn-secondary" href="/account.php">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Rendered entirely client-side; the secret never leaves your own
        // server except to your own browser here, unlike third-party QR APIs.
        new QRCode(document.getElementById("qrcode"), {
            text: <?= json_encode($provisioningUri) ?>,
            width: 200,
            height: 200
        });
    </script>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
