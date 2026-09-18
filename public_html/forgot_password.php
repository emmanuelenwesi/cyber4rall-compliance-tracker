<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mail.php';
require_once __DIR__ . '/includes/rate_limit.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$submitted = false;
$RESET_MAX_PER_EMAIL = 3;
$RESET_WINDOW_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Honeypot, same pattern as signup.
    if (trim($_POST['website'] ?? '') === '') {
        $email = trim(strtolower($_POST['email'] ?? ''));

        if ($email !== '' && !is_rate_limited('password_reset', $email, $RESET_MAX_PER_EMAIL, $RESET_WINDOW_MINUTES)) {
            $conn = db();
            $stmt = $conn->prepare('SELECT id, full_name FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $userRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($userRow) {
                record_rate_limit_event('password_reset', $email);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

                $upd = $conn->prepare('UPDATE users SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?');
                $upd->bind_param('ssi', $tokenHash, $expires, $userRow['id']);
                $upd->execute();
                $upd->close();

                $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
                $body = "Hi " . $userRow['full_name'] . ",\n\n"
                      . "Someone requested a password reset for your " . SITE_NAME . " account.\n\n"
                      . "Reset your password here (valid for 1 hour):\n" . $resetLink . "\n\n"
                      . "If you didn't request this, you can safely ignore this email. Your password hasn't been changed.\n\n"
                      . SITE_NAME;
                send_email($email, 'Reset your password', $body);
            }
        }
        // Always show the same message whether or not the email exists,
        // or whether it was rate-limited, otherwise any of these become
        // a way to check who has an account.
        $submitted = true;
    } else {
        $submitted = true; // silently drop bot submissions but look normal
    }
}

$pageTitle = 'Forgot password';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <h1>Reset your password</h1>

    <?php if ($submitted): ?>
        <div class="success-box">If an account exists for that email, we've sent a link to reset your password. It's valid for 1 hour.</div>
        <p class="helper"><a href="/login.php">Back to login</a></p>
    <?php else: ?>
        <p class="helper">Enter the email you signed up with and we'll send you a reset link.</p>
        <form method="post" class="panel">
            <?= csrf_field() ?>
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label for="website">Leave this field empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus>
            <div style="margin-top:20px;">
                <button type="submit">Send reset link</button>
            </div>
        </form>
        <p class="helper" style="margin-top:14px;"><a href="/login.php">Back to login</a></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
