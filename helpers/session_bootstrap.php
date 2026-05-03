<?php

declare(strict_types=1);

function bk_is_local_host(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return true;
    }
    $hostOnly = preg_replace('/:\\d+$/', '', $host);

    if (in_array($hostOnly, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
        return true;
    }

    // Typical home LAN dev over HTTP — avoid Secure cookies the browser would reject.
    if (preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[0-1])\.)/', $hostOnly) === 1) {
        return true;
    }

    return false;
}

/** Normalize PK / name columns from different dumps or PDO settings */
function bk_normalize_user_row(?array $user): ?array
{
    if ($user === null) {
        return null;
    }

    $uid = $user['user_id'] ?? $user['id'] ?? $user['USER_ID'] ?? null;
    if ($uid !== null && $uid !== '') {
        $user['user_id'] = $uid;
    }

    return $user;
}

/** Never send Secure cookies over plain HTTP on localhost — browsers drop them and login breaks. */
function bk_session_should_use_secure_cookie(): bool
{
    if (bk_is_local_host()) {
        return false;
    }

    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function bk_request_scheme(): string
{
    if (!bk_is_local_host()) {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    return $fwd === 'https' ? 'https' : 'http';
}

/** Absolute URL for this host (path may include query string). */
function bk_absolute_url(string $pathOrPathQuery): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = bk_request_scheme() . '://' . $host;
    $pathOrPathQuery = ltrim($pathOrPathQuery, '/');

    return $base . '/' . $pathOrPathQuery;
}

/**
 * Writable app-local session dir (CLI server / macOS often has a broken or non-writable php.ini path).
 */
function bk_configure_session_storage(): void
{
    static $configured = false;
    if ($configured) {
        return;
    }
    $configured = true;

    $dir = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir);

        return;
    }

    error_log('BOOK-KING-LIMS: cannot use storage/sessions; check permissions: ' . $dir);
}

/**
 * Flush buffers, persist session, redirect. Saving before Location guarantees data on disk before the next request.
 */
function bk_finish_redirect(string $absoluteUrl, int $status = 302): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $absoluteUrl, true, $status);
    exit;
}

/** Stable cookie scope for /user/* and site root so OTP survives redirects. */
function bk_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');

    bk_configure_session_storage();

    $params = [
        'lifetime' => 0,
        'path' => '/',
        'secure' => bk_session_should_use_secure_cookie(),
        'httponly' => true,
    ];
    // Omit SameSite on local/LAN HTTP — strict defaults broke cookie storage for some browsers.
    if (!bk_is_local_host()) {
        $params['samesite'] = 'Lax';
    }
    session_set_cookie_params($params);

    session_start();
}
