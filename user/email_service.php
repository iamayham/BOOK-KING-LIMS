<?php

class EmailService
{
    /** @var string OTP / transactional From (must match a sender allowed by your provider) */
    private string $fromEmail;
    private string $fromName;

    /** Brevo SMTP */
    private string $brevoSmtpHost;
    private int $brevoSmtpPort;
    private string $brevoSmtpLogin;
    private string $brevoSmtpPassword;

    /** @var string|null Human-readable reason when sendOTP returns false */
    private ?string $lastSendError = null;

    public function __construct()
    {
        $this->loadDotEnv();

        $this->fromEmail = trim((string) (getenv('MAIL_FROM_EMAIL')
            ?: getenv('BREVO_FROM_EMAIL')
            ?: ''));

        $this->fromName = trim((string) (getenv('MAIL_FROM_NAME')
            ?: getenv('BREVO_FROM_NAME')
            ?: 'Book King'));

        if ($this->fromEmail === '') {
            $this->fromEmail = 'noreply@bookking.online';
        }

        $this->brevoSmtpLogin = trim((string) (getenv('BREVO_SMTP_LOGIN') ?: ''));
        $this->brevoSmtpPassword = trim((string) (getenv('BREVO_SMTP_KEY') ?: getenv('BREVO_SMTP_PASSWORD') ?: ''));

        $this->brevoSmtpHost = trim((string) (getenv('BREVO_SMTP_HOST') ?: 'smtp-relay.brevo.com'));
        $this->brevoSmtpPort = (int) (getenv('BREVO_SMTP_PORT') ?: 587);
    }

    private function loadDotEnv(): void
    {
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
            if (str_starts_with($trimmed, "\xEF\xBB\xBF")) {
                $trimmed = trim(substr($trimmed, 3));
            }
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separatorPos = strpos($trimmed, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $separatorPos));
            $value = trim(substr($trimmed, $separatorPos + 1));

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $value = trim($value);

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public function getLastSendError(): ?string
    {
        return $this->lastSendError;
    }

    public function sendOTP($recipientEmail, $otp): bool
    {
        $this->lastSendError = null;
        return $this->sendOtpViaBrevo((string) $recipientEmail, (string) $otp);
    }

    private function sendOtpViaBrevo(string $recipientEmail, string $otp): bool
    {
        if ($this->brevoSmtpLogin === '' || $this->brevoSmtpPassword === '') {
            $this->lastSendError = 'Brevo SMTP: BREVO_SMTP_LOGIN / BREVO_SMTP_KEY missing in .env';
            return false;
        }

        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            $this->lastSendError = 'Composer vendor/autoload.php missing (needed for PHPMailer / Brevo SMTP).';
            return false;
        }

        try {
            require_once $autoload;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->brevoSmtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->brevoSmtpLogin;
            $mail->Password = $this->brevoSmtpPassword;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->brevoSmtpPort > 0 ? $this->brevoSmtpPort : 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($recipientEmail);
            $mail->Subject = 'Your OTP Code';
            $mail->isHTML(true);
            $mail->Body = '<p>Your verification code is: <b>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</b></p>';
            $mail->AltBody = 'Your verification code is: ' . $otp;

            $mail->send();
            return true;
        } catch (PHPMailer\PHPMailer\Exception $e) {
            $this->lastSendError = 'Brevo SMTP: ' . $e->getMessage();
            error_log('Brevo SMTP: ' . $e->getMessage());
            return false;
        } catch (Throwable $e) {
            $this->lastSendError = 'Brevo SMTP: ' . $e->getMessage();
            error_log($this->lastSendError);
            return false;
        }
    }

}
