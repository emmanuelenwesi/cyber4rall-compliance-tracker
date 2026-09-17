<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/totp.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

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
    $error = 'Incorrect email or password.';
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
