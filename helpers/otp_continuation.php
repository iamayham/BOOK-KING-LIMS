<?php

declare(strict_types=1);

/**
 * Signed, time-limited ticket so otp.php can open after signup/login even when
 * the session cookie is not carried on redirect (localhost vs 127.0.0.1, new session, etc.).
 */

function otp_continuation_load_dotenv(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $envPath = dirname(__DIR__) . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        $pos = strpos($trimmed, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($trimmed, 0, $pos));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        $value = trim(substr($trimmed, $pos + 1));
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function otp_continuation_secret(): string
{
    otp_continuation_load_dotenv();
    $s = getenv('APP_SECRET');
    if (is_string($s) && $s !== '') {
        return $s;
    }

    return 'book-king-dev-otp-continuation-v1';
}

function otp_continuation_issue(int $userId): string
{
    if ($userId <= 0) {
        return '';
    }
    $exp = time() + 7200;
    $payload = $userId . ':' . $exp;
    $sig = hash_hmac('sha256', $payload, otp_continuation_secret());
    $raw = $payload . ':' . $sig;

    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function otp_continuation_verify(?string $ticket): int
{
    if ($ticket === null || $ticket === '') {
        return 0;
    }
    $ticket = strtr($ticket, '-_', '+/');
    $pad = strlen($ticket) % 4;
    if ($pad > 0) {
        $ticket .= str_repeat('=', 4 - $pad);
    }
    $raw = base64_decode($ticket, true);
    if ($raw === false) {
        return 0;
    }
    $parts = explode(':', $raw);
    if (count($parts) !== 3) {
        return 0;
    }
    [$uid, $exp, $sig] = $parts;
    $uid = (int) $uid;
    $exp = (int) $exp;
    if ($uid <= 0 || $exp < time()) {
        return 0;
    }
    $payload = $uid . ':' . $exp;
    $expected = hash_hmac('sha256', $payload, otp_continuation_secret());
    if (!hash_equals($expected, $sig)) {
        return 0;
    }

    return $uid;
}

/** Latest unverified OTP code for this user (DB), or null */
function otp_latest_pending_code(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT otp, status FROM otp WHERE user_id = :uid ORDER BY created_at DESC LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && (int) $row['status'] === 0) {
        return (string) $row['otp'];
    }

    return null;
}
