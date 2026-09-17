<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$conn = db();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = null;
$success = false;

function find_valid_reset_user(mysqli $conn, string $token): ?array
{
    if (!$token) return null;
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare('SELECT * FROM users WHERE reset_token_hash = ? AND reset_token_expires >= NOW()');
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$userRow = find_valid_reset_user($conn, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRow) {
    verify_csrf();
    $newPassword = $_POST['new_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $upd = $conn->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_token_expires = NULL WHERE id = ?');
        $upd->bind_param('si', $hash, $userRow['id']);
        $upd->execute();
        $upd->close();
        $success = true;
    }
}

$pageTitle = 'Reset password';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <h1>Reset your password</h1>

    <?php if ($success): ?>
        <div class="success-box">Your password has been updated.</div>
        <a class="btn" href="/login.php">Log in</a>

    <?php elseif (!$userRow): ?>
        <div class="error-box">This link is invalid or has expired.</div>
        <p class="helper"><a href="/forgot_password.php">Request a new link</a></p>

    <?php else: ?>
        <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="panel">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required autofocus>
            <div style="margin-top:20px;">
                <button type="submit">Update password</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
