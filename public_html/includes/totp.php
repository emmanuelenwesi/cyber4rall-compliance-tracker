<?php
/**
 * Minimal TOTP (RFC 6238) implementation, compatible with Google
 * Authenticator, Authy, 1Password, etc. No external library, because
 * shared hosting here has no Composer and we don't want the MFA secret
 * ever leaving our own server (e.g. to a third-party QR image API).
 * The QR code itself is rendered client-side in the user's browser;
 * see mfa_setup.php.
 */

function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

function totp_base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $output = '';
    foreach (str_split($bits, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $output .= $alphabet[bindec($chunk)];
    }
    return $output;
}

function totp_base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
    $bits = '';
    foreach (str_split($secret) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) < 8) continue;
        $bytes .= chr(bindec($byte));
    }
    return $bytes;
}

function totp_code_at(string $secret, int $timeSlice): string
{
    $key = totp_base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice); // 8-byte big-endian counter
    $hash = hash_hmac('sha1', $time, $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part = substr($hash, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Verify a 6-digit code, allowing +/- 1 time step (30s each) for clock drift.
 */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) return false;

    $timeSlice = (int)floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code_at($secret, $timeSlice + $i), $code)) {
            return true;
        }
    }
    return false;
}

function totp_provisioning_uri(string $secret, string $email, string $issuer = 'Cyber4rall Compliance'): string
{
    $label = rawurlencode($issuer . ':' . $email);
    $params = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/{$label}?{$params}";
}

/**
 * Generate N one-time recovery codes (plaintext to show once) and their
 * hashed form (to store). Each code is used at most once.
 */
function totp_generate_recovery_codes(int $count = 8): array
{
    $plain = [];
    $hashed = [];
    for ($i = 0; $i < $count; $i++) {
        $code = strtoupper(bin2hex(random_bytes(4))); // e.g. "A1B2C3D4"
        $plain[] = $code;
        $hashed[] = password_hash($code, PASSWORD_DEFAULT);
    }
    return ['plain' => $plain, 'hashed' => $hashed];
}

function totp_consume_recovery_code(array &$hashedCodes, string $inputCode): bool
{
    $inputCode = strtoupper(trim($inputCode));
    foreach ($hashedCodes as $i => $hash) {
        if (password_verify($inputCode, $hash)) {
            unset($hashedCodes[$i]);
            $hashedCodes = array_values($hashedCodes);
            return true;
        }
    }
    return false;
}
