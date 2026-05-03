<?php
require_once dirname(__DIR__) . '/helpers/session_bootstrap.php';
bk_session_start();
require_once './email_service.php';

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $pdo = require '../database/db_connection.php';
    } catch (Throwable $e) {
        error_log('forgot-password DB bootstrap: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Service is temporarily unavailable. Please try again later.']);
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    
    if (!empty($email)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $otp = rand(100000, 999999);
                $emailService = new EmailService();
                
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_email'] = $email;

                // Handle DBs where otp.id is required but not auto-increment.
                $manualOtpId = null;
                $otpIdColumn = $pdo->query("SHOW COLUMNS FROM otp LIKE 'id'")->fetch(PDO::FETCH_ASSOC);
                $otpIdNeedsManualValue = $otpIdColumn
                    && stripos((string) ($otpIdColumn['Extra'] ?? ''), 'auto_increment') === false
                    && (($otpIdColumn['Null'] ?? '') === 'NO')
                    && ($otpIdColumn['Default'] === null);

                if ($otpIdNeedsManualValue) {
                    $manualOtpId = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM otp")->fetchColumn();
                }

                if ($otpIdNeedsManualValue) {
                    $insertOtpStmt = $pdo->prepare("INSERT INTO otp (id, user_id, otp, status) VALUES (?, ?, ?, 0)");
                    $insertOtpStmt->execute([$manualOtpId, $user['user_id'], $otp]);
                } else {
                    $insertOtpStmt = $pdo->prepare("INSERT INTO otp (user_id, otp, status) VALUES (?, ?, 0)");
                    $insertOtpStmt->execute([$user['user_id'], $otp]);
                }

                if ($emailService->sendOTP($email, $otp)) {
                    echo json_encode(['success' => true]);
                    exit;
                }

                // Dev fallback: let user continue with OTP even when email fails.
                echo json_encode([
                    'success' => true,
                    'fallback' => true,
                    'message' => 'Email failed. Use this OTP: ' . $otp
                ]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Email not found']);
                exit;
            }
        } catch (Throwable $e) {
            error_log('forgot-password: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $SITE_ICON_BASE = '../'; require dirname(__DIR__) . '/includes/site_head_icons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/forgot-password.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Left Section -->
        <div class="left-section">
            <img src="../images/logo.png" alt="Book King Logo">
            <p class="info-text">We'll send a code to your email to reset your password</p>
        </div>

        <!-- Right Section -->
        <div class="right-section">
            <h1 class="title">Forgot Password</h1>
            <p class="subtitle">Enter your email to receive a password reset code</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <form id="forgotPasswordForm" method="POST">
                <div class="input-container">
                    <input type="email" name="email" class="input-field" placeholder="Enter your email" required>
                </div>

                <button type="submit" class="submit-btn">
                    <span class="submit-btn-text">SEND RESET CODE</span>
                </button>

                <a href="../index.php" class="back-to-login">Back to Login</a>
            </form>
        </div>
    </div>

    <div id="successPopup" class="popup" style="display: none;">
        <div class="popup-content">
            <p>Email found! Redirecting to OTP verification...</p>
        </div>
    </div>

    <script>
    document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        try {
            const formData = new FormData(this);
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 20000);
            const response = await fetch('forgot-password.php', {
                method: 'POST',
                body: formData,
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            
            const raw = await response.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (_) {
                console.error('Non-JSON response', response.status, raw.slice(0, 500));
                alert('Server returned an unexpected response (' + response.status + '). Please try again.');
                return;
            }
            
            if (data.success) {
                if (data.fallback && data.message) {
                    alert(data.message);
                }
                const popup = document.getElementById('successPopup');
                popup.style.display = 'block';
                
                setTimeout(() => {
                    window.location.href = 'otp2.php';
                }, 3000);
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            if (error.name === 'AbortError') {
                alert('Request timed out. Please try again.');
            } else {
                alert('An error occurred. Please try again.');
            }
        }
    });
    </script>
</body>
</html>