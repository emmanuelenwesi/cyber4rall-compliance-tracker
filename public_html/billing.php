<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/paystack.php';

$user = require_login();
$companyId = $user['company_id'];
$conn = db();
$error = null;
$showCancelConfirm = isset($_GET['confirm_cancel']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $company = get_company($companyId);

    if ($action === 'subscribe') {
        $callback = SITE_URL . '/paystack_callback.php';
        $url = paystack_init_subscription($companyId, $user['email'], $callback);
        if ($url) {
            header('Location: ' . $url);
            exit;
        }
        $error = 'Could not start checkout right now. Please try again in a moment.';

    } elseif ($action === 'cancel_confirm') {
        // Real friction: require the account password, not just a JS prompt.
        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $hash = $stmt->get_result()->fetch_assoc()['password_hash'] ?? '';
        $stmt->close();

        if (!password_verify($_POST['current_password'] ?? '', $hash)) {
            $error = 'Incorrect password — subscription was not cancelled.';
            $showCancelConfirm = true;
        } else {
            // Stop future billing immediately, but keep access until the
            // period already paid for actually ends — not right now.
            if ($company['paystack_subscription_code'] && $company['paystack_email_token']) {
                paystack_disable_subscription($company['paystack_subscription_code'], $company['paystack_email_token']);
            }
            $cancelsAt = $company['current_period_end'] ?: date('Y-m-d H:i:s');
            $stmt = $conn->prepare('UPDATE companies SET cancels_at = ? WHERE id = ?');
            $stmt->bind_param('si', $cancelsAt, $companyId);
            $stmt->execute();
            $stmt->close();

            header('Location: /billing.php');
            exit;
        }
    }
}

$company = get_company($companyId);
$active = has_active_access($company);
$status = effective_status($company);

$pageTitle = 'Billing';
require __DIR__ . '/includes/header.php';
?>
<h1>Billing</h1>

<?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>

<div class="panel">
    <?php if ($status === 'trial'): ?>
        <?php $daysLeft = (new DateTime())->diff(new DateTime($company['trial_ends_at']))->days; ?>
        <h2>You're on a free trial</h2>
        <p><?= $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?> left. Subscribe any time to keep access after your trial ends.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <button type="submit">Subscribe — ₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month</button>
        </form>

    <?php elseif ($status === 'trial_expired'): ?>
        <h2>Your trial has ended</h2>
        <p>Subscribe to continue using the compliance tracker.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <button type="submit">Subscribe — ₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month</button>
        </form>

    <?php elseif ($status === 'active'): ?>
        <h2>Active subscription</h2>
        <p>₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month — next billing date <?= e(date('j F Y', strtotime($company['current_period_end']))) ?>.</p>

        <?php if (!$showCancelConfirm): ?>
            <a class="btn btn-secondary" href="/billing.php?confirm_cancel=1">Cancel subscription</a>
        <?php else: ?>
            <div class="panel" style="border-color:var(--risk-high);background:rgba(224,120,90,.08);">
                <h3 style="color:var(--risk-high);">Confirm cancellation</h3>
                <p class="helper">You'll keep full access until <?= e(date('j F Y', strtotime($company['current_period_end']))) ?> — no further charges after that. Enter your password to confirm.</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_confirm">
                    <label for="current_password">Password</label>
                    <input type="password" id="current_password" name="current_password" required autofocus style="max-width:280px;">
                    <div style="margin-top:16px;">
                        <button type="submit" class="btn-secondary">Yes, cancel subscription</button>
                        <a class="btn" href="/billing.php" style="margin-left:8px;">Never mind</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php elseif ($status === 'cancelling'): ?>
        <h2>Subscription cancelled</h2>
        <p>Won't renew. You'll keep access until <strong><?= e(date('j F Y', strtotime($company['cancels_at']))) ?></strong>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <button type="submit">Resubscribe</button>
        </form>

    <?php elseif ($status === 'past_due'): ?>
        <h2>Payment issue</h2>
        <p>Your last payment didn't go through. Subscribe again to restore access.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <button type="submit">Retry — ₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month</button>
        </form>

    <?php else: ?>
        <h2>No active subscription</h2>
        <p>Subscribe to get full access to the compliance tracker.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <button type="submit">Subscribe — ₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month</button>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
