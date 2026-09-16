<?php
require_once __DIR__ . '/../config.php';

/**
 * Make an authenticated request to the Paystack API.
 * Returns the decoded JSON response body as an array, or null on hard failure.
 */
function paystack_request(string $method, string $path, array $body = []): ?array
{
    $ch = curl_init("https://api.paystack.co{$path}");
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);

    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Paystack request failed: ' . $curlError);
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Start a subscription checkout for a company. Returns the Paystack
 * authorization_url to redirect the user to, or null on failure.
 */
function paystack_init_subscription(int $companyId, string $email, string $callbackUrl): ?string
{
    $reference = 'c4a_' . $companyId . '_' . time() . '_' . bin2hex(random_bytes(4));

    $result = paystack_request('POST', '/transaction/initialize', [
        'email' => $email,
        'plan' => PAYSTACK_PLAN_CODE,
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => ['company_id' => $companyId],
    ]);

    if ($result && !empty($result['status']) && !empty($result['data']['authorization_url'])) {
        return $result['data']['authorization_url'];
    }
    error_log('Paystack init failed: ' . json_encode($result));
    return null;
}

/**
 * Verify a transaction reference after the user returns from checkout.
 * Returns the transaction data array on a successful, verified charge, else null.
 */
function paystack_verify_transaction(string $reference): ?array
{
    $result = paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
    if ($result && !empty($result['status']) && ($result['data']['status'] ?? '') === 'success') {
        return $result['data'];
    }
    return null;
}

/**
 * Disable (cancel) a company's active subscription at Paystack.
 */
function paystack_disable_subscription(string $subscriptionCode, string $emailToken): bool
{
    $result = paystack_request('POST', '/subscription/disable', [
        'code' => $subscriptionCode,
        'token' => $emailToken,
    ]);
    return (bool)($result['status'] ?? false);
}

/**
 * Verify that an incoming webhook request really came from Paystack.
 * Paystack signs the raw request body with your secret key (HMAC SHA512)
 * and sends it in the x-paystack-signature header.
 */
function paystack_verify_webhook_signature(string $rawBody, ?string $signatureHeader): bool
{
    if (!$signatureHeader) return false;
    $expected = hash_hmac('sha512', $rawBody, PAYSTACK_SECRET_KEY);
    return hash_equals($expected, $signatureHeader);
}
