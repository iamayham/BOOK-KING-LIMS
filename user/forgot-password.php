<?php
require_once dirname(__DIR__) . '/helpers/session_bootstrap.php';
bk_session_start();

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
            // SMTP on production can take longer, especially with multi-transport fallback attempts.
            const timeoutId = setTimeout(() => controller.abort(), 60000);
            const response = await fetch('handlers/forgot-password-post.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
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