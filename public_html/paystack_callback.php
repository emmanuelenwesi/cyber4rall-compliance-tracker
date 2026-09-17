<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/paystack.php';

$user = require_login();
$companyId = $user['company_id'];

$reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';
$message = 'We could not confirm your payment. If you were charged, contact support — no need to pay twice.';
$success = false;

if ($reference) {
    $data = paystack_verify_transaction($reference);
    if ($data && (int)($data['metadata']['company_id'] ?? 0) === $companyId) {
        $conn = db();

        // Log the payment (idempotent — reference is unique)
        $amountKobo = (int)($data['amount'] ?? 0);
        $status = $data['status'] ?? 'unknown';
        $paidAt = !empty($data['paid_at']) ? date('Y-m-d H:i:s', strtotime($data['paid_at'])) : null;
        $ins = $conn->prepare('INSERT IGNORE INTO payments (company_id, reference, amount_kobo, status, paid_at) VALUES (?, ?, ?, ?, ?)');
        $ins->bind_param('isiss', $companyId, $reference, $amountKobo, $status, $paidAt);
        $ins->execute();
        $ins->close();

        // Activate access immediately for a fast UX; the webhook keeps this
        // in sync going forward (renewals, failures, cancellations).
        $customerCode = $data['customer']['customer_code'] ?? null;
        $periodEnd = (new DateTime())->modify('+30 days')->format('Y-m-d H:i:s');

        $upd = $conn->prepare("UPDATE companies SET subscription_status = 'active', paystack_customer_code = ?, current_period_end = ?, cancels_at = NULL WHERE id = ?");
        $upd->bind_param('ssi', $customerCode, $periodEnd, $companyId);
        $upd->execute();
        $upd->close();

        $success = true;
        $message = 'Payment confirmed — your subscription is active.';
    }
}

$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
?>
<div class="panel" style="max-width:520px;margin:40px auto;">
    <?php if ($success): ?>
        <div class="success-box"><?= e($message) ?></div>
        <a class="btn" href="/dashboard.php">Go to dashboard</a>
    <?php else: ?>
        <div class="error-box"><?= e($message) ?></div>
        <a class="btn btn-secondary" href="/billing.php">Back to billing</a>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
