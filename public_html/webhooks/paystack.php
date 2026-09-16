<?php
/**
 * Paystack webhook endpoint.
 * Configure this URL in Paystack Dashboard > Settings > API Keys & Webhooks:
 *   https://compliance.cyber4rall.com/webhooks/paystack.php
 * No login/CSRF here — Paystack calls this server-to-server. Trust is
 * established purely via the signature check below.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? null;

if (!paystack_verify_webhook_signature($rawBody, $signature)) {
    http_response_code(401);
    exit;
}

$event = json_decode($rawBody, true);
if (!is_array($event) || empty($event['event'])) {
    http_response_code(400);
    exit;
}

$conn = db();

function find_company_by_customer_code(mysqli $conn, ?string $customerCode): ?array
{
    if (!$customerCode) return null;
    $stmt = $conn->prepare('SELECT * FROM companies WHERE paystack_customer_code = ?');
    $stmt->bind_param('s', $customerCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function find_company_by_subscription_code(mysqli $conn, ?string $subCode): ?array
{
    if (!$subCode) return null;
    $stmt = $conn->prepare('SELECT * FROM companies WHERE paystack_subscription_code = ?');
    $stmt->bind_param('s', $subCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

$type = $event['event'];
$data = $event['data'] ?? [];

switch ($type) {

    case 'charge.success': {
        $customerCode = $data['customer']['customer_code'] ?? null;
        $companyId = (int)($data['metadata']['company_id'] ?? 0);
        $company = $companyId ? get_company($companyId) : find_company_by_customer_code($conn, $customerCode);

        if ($company) {
            $periodEnd = (new DateTime())->modify('+30 days')->format('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE companies SET subscription_status = 'active', paystack_customer_code = ?, current_period_end = ? WHERE id = ?");
            $stmt->bind_param('ssi', $customerCode, $periodEnd, $company['id']);
            $stmt->execute();
            $stmt->close();

            $reference = $data['reference'] ?? uniqid('evt_');
            $amountKobo = (int)($data['amount'] ?? 0);
            $ins = $conn->prepare('INSERT IGNORE INTO payments (company_id, reference, amount_kobo, status, paid_at) VALUES (?, ?, ?, "success", NOW())');
            $ins->bind_param('isi', $company['id'], $reference, $amountKobo);
            $ins->execute();
            $ins->close();
        }
        break;
    }

    case 'subscription.create': {
        $customerCode = $data['customer']['customer_code'] ?? null;
        $company = find_company_by_customer_code($conn, $customerCode);
        if ($company) {
            $subCode = $data['subscription_code'] ?? null;
            $emailToken = $data['email_token'] ?? null;
            $stmt = $conn->prepare('UPDATE companies SET paystack_subscription_code = ?, paystack_email_token = ? WHERE id = ?');
            $stmt->bind_param('ssi', $subCode, $emailToken, $company['id']);
            $stmt->execute();
            $stmt->close();
        }
        break;
    }

    case 'invoice.payment_failed': {
        $customerCode = $data['customer']['customer_code'] ?? ($data['subscription']['customer']['customer_code'] ?? null);
        $company = find_company_by_customer_code($conn, $customerCode);
        if ($company) {
            $stmt = $conn->prepare("UPDATE companies SET subscription_status = 'past_due' WHERE id = ?");
            $stmt->bind_param('i', $company['id']);
            $stmt->execute();
            $stmt->close();
        }
        break;
    }

    case 'subscription.disable':
    case 'subscription.not_renew': {
        $subCode = $data['subscription_code'] ?? null;
        $company = find_company_by_subscription_code($conn, $subCode);
        if ($company) {
            $stmt = $conn->prepare("UPDATE companies SET subscription_status = 'cancelled' WHERE id = ?");
            $stmt->bind_param('i', $company['id']);
            $stmt->execute();
            $stmt->close();
        }
        break;
    }
}

http_response_code(200);
echo 'ok';
