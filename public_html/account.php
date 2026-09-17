<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/totp.php';

$user = require_login();
$conn = db();
$error = null;
$success = null;

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($current, $userRow['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->bind_param('si', $hash, $user['id']);
            $upd->execute();
            $upd->close();
            $success = 'Password updated.';
        }
    } elseif ($action === 'disable_mfa') {
        $current = $_POST['current_password'] ?? '';
        if (!password_verify($current, $userRow['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } else {
            $upd = $conn->prepare('UPDATE users SET mfa_enabled = 0, mfa_secret = NULL, mfa_recovery_codes = NULL WHERE id = ?');
            $upd->bind_param('i', $user['id']);
            $upd->execute();
            $upd->close();
            $success = 'Two-factor authentication has been turned off.';
            $userRow['mfa_enabled'] = 0;
        }
    }
}

$pageTitle = 'Account Settings';
require __DIR__ . '/includes/header.php';
?>
<h1>Account Settings</h1>

<?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="success-box"><?= e($success) ?></div><?php endif; ?>

<div class="panel">
    <h2>Change password</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required>
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" minlength="8" required>
        <div style="margin-top:16px;"><button type="submit">Update password</button></div>
    </form>
</div>

<div class="panel" style="margin-top:16px;">
    <h2>Two-factor authentication</h2>
    <?php if (!empty($userRow['mfa_enabled'])): ?>
        <p class="success-box" style="display:inline-block;">Enabled — your account requires a code from your authenticator app to sign in.</p>
        <form method="post" onsubmit="return confirm('Turn off two-factor authentication? This makes your account easier to break into if your password leaks.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="disable_mfa">
            <label for="mfa_current_password">Confirm your password to disable</label>
            <input type="password" id="mfa_current_password" name="current_password" required style="max-width:280px;">
            <div style="margin-top:16px;"><button type="submit" class="btn-secondary">Disable two-factor authentication</button></div>
        </form>
    <?php else: ?>
        <p class="helper">Add an extra layer of security — after your password, you'll also need a code from an app like Google Authenticator or Authy.</p>
        <a class="btn" href="/mfa_setup.php">Set up two-factor authentication</a>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
