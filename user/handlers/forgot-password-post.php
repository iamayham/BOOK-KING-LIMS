<?php

declare(strict_types=1);

/**
 * Forgot-password POST API — JSON-only. Avoid routing POST through the HTML page so CDNs/cache
 * and accidental output can't return a GET document instead of JSON.
 */

ini_set('display_errors', '0');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    header('Allow: POST');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__, 2) . '/helpers/session_bootstrap.php';
bk_session_start();

require_once dirname(__DIR__) . '/email_service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

try {
    $pdo = require dirname(__DIR__, 2) . '/database/db_connection.php';
} catch (Throwable $e) {
    error_log('forgot-password-post DB bootstrap: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Service is temporarily unavailable. Please try again later.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }

    $otp = rand(100000, 999999);
    $emailService = new EmailService();

    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_user_id'] = $user['user_id'];
    $_SESSION['reset_email'] = $email;

    $otpIdColumn = $pdo->query("SHOW COLUMNS FROM otp LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
    $otpIdNeedsManualValue = $otpIdColumn
        && stripos((string) ($otpIdColumn['Extra'] ?? ''), 'auto_increment') === false
        && (($otpIdColumn['Null'] ?? '') === 'NO')
        && ($otpIdColumn['Default'] === null);

    if ($otpIdNeedsManualValue) {
        $manualOtpId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM otp')->fetchColumn();
        $insertOtpStmt = $pdo->prepare('INSERT INTO otp (id, user_id, otp, status) VALUES (?, ?, ?, 0)');
        $insertOtpStmt->execute([$manualOtpId, $user['user_id'], $otp]);
    } else {
        $insertOtpStmt = $pdo->prepare('INSERT INTO otp (user_id, otp, status) VALUES (?, ?, 0)');
        $insertOtpStmt->execute([$user['user_id'], $otp]);
    }

    if ($emailService->sendOTP($email, $otp)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $sendWhy = $emailService->getLastSendError() ?? 'unknown';
    error_log('forgot-password email send failed: ' . $sendWhy);

    echo json_encode([
        'success' => true,
        'fallback' => true,
        'message' => 'Email failed. Use this OTP: ' . $otp . ' — ' . $sendWhy,
    ]);
    exit;
} catch (Throwable $e) {
    error_log('forgot-password-post: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
