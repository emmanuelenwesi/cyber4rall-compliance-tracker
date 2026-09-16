<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/paystack.php';

$user = require_login();
$companyId = $user['company_id'];
$conn = db();
$error = null;

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
    } elseif ($action === 'cancel') {
        if ($company['paystack_subscription_code'] && $company['paystack_email_token']) {
            paystack_disable_subscription($company['paystack_subscription_code'], $company['paystack_email_token']);
        }
        $stmt = $conn->prepare("UPDATE companies SET subscription_status = 'cancelled' WHERE id = ?");
        $stmt->bind_param('i', $companyId);
        $stmt->execute();
        $stmt->close();
        header('Location: /billing.php');
        exit;
    }
}

$company = get_company($companyId);
$active = has_active_access($company);

$pageTitle = 'Billing';
require __DIR__ . '/includes/header.php';
?>
<h1>Billing</h1>

<?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>

<div class="panel">
    <?php if ($company['subscription_status'] === 'trial'): ?>
        <?php if ($active): ?>
            <?php $daysLeft = (new DateTime())->diff(new DateTime($company['trial_ends_at']))->days; ?>
            <h2>You're on a free trial</h2>
            <p><?= $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?> left. Subscribe any time to keep access after your trial ends.</p>
        <?php else: ?>
            <h2>Your trial has ended</h2>
            <p>Subscribe to continue using the compliance tracker.</p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="subscribe">
            <button type="submit">Subscribe — ₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month</button>
        </form>

    <?php elseif ($company['subscription_status'] === 'active' && $active): ?>
        <h2>Active subscription</h2>
        <p>₦<?= number_format(SUBSCRIPTION_PRICE_NAIRA) ?>/month — next billing date <?= e(date('j F Y', strtotime($company['current_period_end']))) ?>.</p>
        <form method="post" onsubmit="return confirm('Cancel your subscription? You will lose access when the current period ends.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn-secondary">Cancel subscription</button>
        </form>

    <?php elseif ($company['subscription_status'] === 'past_due'): ?>
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
