<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

$flag = strtolower(trim((string) (getenv('USER_ONLY_MODE') ?: '0')));
$userOnlyMode = in_array($flag, ['1', 'true', 'yes', 'on'], true);

if (!$userOnlyMode) {
    return;
}

$uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uriPath = is_string($uriPath) ? $uriPath : '/';

if (preg_match('#^/admin(?:/|$)#', $uriPath) === 1) {
    header('Location: /index.php?user_only=1', true, 302);
    exit;
}
