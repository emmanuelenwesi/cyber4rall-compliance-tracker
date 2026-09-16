<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (current_user()) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Honeypot: a real visitor never sees or fills this field (hidden via CSS).
    // A bot filling every input on the form will, so silently drop it.
    if (trim($_POST['website'] ?? '') !== '') {
        header('Location: /signup.php');
        exit;
    }

    $companyName = trim($_POST['company_name'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($companyName === '' || $fullName === '' || $email === '' || $password === '') {
        $error = 'Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $conn = db();

        $check = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->fetch_assoc()) {
            $error = 'An account with that email already exists.';
        }
        $check->close();

        if (!$error) {
            $conn->begin_transaction();
            try {
                $slugBase = slugify($companyName);
                $slug = $slugBase;
                $i = 1;
                while (true) {
                    $s = $conn->prepare('SELECT id FROM companies WHERE slug = ?');
                    $s->bind_param('s', $slug);
                    $s->execute();
                    if (!$s->get_result()->fetch_assoc()) { $s->close(); break; }
                    $s->close();
                    $slug = $slugBase . '-' . (++$i);
                }

                $trialEndsAt = (new DateTime())->modify('+' . TRIAL_DAYS . ' days')->format('Y-m-d H:i:s');
                $ins = $conn->prepare('INSERT INTO companies (name, slug, trial_ends_at) VALUES (?, ?, ?)');
                $ins->bind_param('sss', $companyName, $slug, $trialEndsAt);
                $ins->execute();
                $companyId = $conn->insert_id;
                $ins->close();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $conn->prepare('INSERT INTO users (company_id, full_name, email, password_hash, role) VALUES (?, ?, ?, ?, "admin")');
                $insUser->bind_param('isss', $companyId, $fullName, $email, $hash);
                $insUser->execute();
                $userId = $conn->insert_id;
                $insUser->close();

                $conn->commit();

                $userRow = ['id' => $userId, 'company_id' => $companyId, 'full_name' => $fullName, 'email' => $email, 'role' => 'admin', 'is_platform_admin' => 0];
                login_user($userRow);
                header('Location: /dashboard.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Signup failed: ' . $e->getMessage());
                $error = 'Something went wrong creating your account. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="page-narrow">
    <h1>Create your company account</h1>
    <p class="helper">One account per organization. You'll be the first admin and can invite teammates later.</p>

    <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="panel">
        <?= csrf_field() ?>
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <label for="company_name">Company name</label>
        <input type="text" id="company_name" name="company_name" value="<?= e($_POST['company_name'] ?? '') ?>" required>

        <label for="full_name">Your full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>

        <label for="email">Work email</label>
        <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required>

        <div style="margin-top:20px;">
            <button type="submit">Create account</button>
        </div>
    </form>
    <p class="helper" style="margin-top:14px;">Already have an account? <a href="/login.php">Log in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
