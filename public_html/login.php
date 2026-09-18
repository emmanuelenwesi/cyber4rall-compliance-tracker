<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/rate_limit.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;
if (isset($_GET['mfa_locked'])) {
    $error = 'Too many incorrect codes. Please log in again.';
}
$LOGIN_MAX_PER_EMAIL = 5;
$LOGIN_MAX_PER_IP = 20;
$LOGIN_WINDOW_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $ip = client_ip();

    if (is_rate_limited('login_email', $email, $LOGIN_MAX_PER_EMAIL, $LOGIN_WINDOW_MINUTES)) {
        $error = "Too many failed attempts for this account. Please wait {$LOGIN_WINDOW_MINUTES} minutes, or use \"Forgot password?\" below.";
    } elseif (is_rate_limited('login_ip', $ip, $LOGIN_MAX_PER_IP, $LOGIN_WINDOW_MINUTES)) {
        $error = "Too many login attempts from your network. Please wait {$LOGIN_WINDOW_MINUTES} minutes and try again.";
    } else {
        $conn = db();
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($userRow && password_verify($password, $userRow['password_hash'])) {
            if (!empty($userRow['mfa_enabled'])) {
                begin_mfa_challenge((int)$userRow['id']);
                header('Location: /mfa_verify.php');
                exit;
            }
            login_user($userRow);
            header('Location: /dashboard.php');
            exit;
        }

        // Only failures count toward lockout — successful logins never do.
        record_rate_limit_event('login_email', $email);
        record_rate_limit_event('login_ip', $ip);
        $error = 'Incorrect email or password.';
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <h1>Log in</h1>

    <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="panel">
        <?= csrf_field() ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <p class="helper" style="margin:6px 0 0;"><a href="/forgot_password.php">Forgot password?</a></p>

        <div style="margin-top:20px;">
            <button type="submit">Log in</button>
        </div>
    </form>
    <p class="helper" style="margin-top:14px;">No account yet? <a href="/signup.php">Create one</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
