<?php

declare(strict_types=1);

class EmailService
{
    /** @var string OTP / transactional From (must match a sender allowed by your provider) */
    private string $fromEmail;
    private string $fromName;

    /** Selected mail driver (brevo|smtp) */
    private string $mailDriver;

    /** Generic SMTP settings (used by MAIL_DRIVER=smtp) */
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $smtpEncryption;

    /** Brevo SMTP fallback when MAIL_DRIVER=brevo */
    private string $brevoSmtpHost;
    private int $brevoSmtpPort;
    private string $brevoSmtpLogin;
    private string $brevoSmtpPassword;

    /** @var string|null Human-readable reason when sendOTP returns false */
    private ?string $lastSendError = null;

    public function __construct()
    {
        $this->loadDotEnv();

        $this->fromEmail = $this->envFirst('MAIL_FROM_EMAIL', 'BREVO_FROM_EMAIL');
        $this->fromName = $this->envFirst('MAIL_FROM_NAME', 'BREVO_FROM_NAME') ?: 'Book King';

        $this->mailDriver = strtolower($this->envFirst('MAIL_DRIVER') ?: 'brevo');

        $this->smtpHost = $this->envFirst('MAIL_HOST', 'SMTP_HOST');
        $this->smtpPort = (int) (($p = $this->envFirst('MAIL_PORT', 'SMTP_PORT')) !== '' ? $p : '587');
        $this->smtpUsername = $this->envFirst('MAIL_USERNAME', 'SMTP_USERNAME');
        $this->smtpPassword = preg_replace('/\s+/', '', $this->envFirst('MAIL_PASSWORD', 'SMTP_PASSWORD')) ?: '';
        $this->smtpEncryption = strtolower($this->envFirst('MAIL_ENCRYPTION', 'SMTP_ENCRYPTION') ?: 'tls');

        if ($this->fromEmail === '') {
            // Gmail SMTP rejects untrusted From addresses; default to authenticated account.
            $this->fromEmail = $this->mailDriver === 'smtp' && $this->smtpUsername !== ''
                ? $this->smtpUsername
                : 'noreply@bookking.online';
        }

        $this->brevoSmtpLogin = $this->envFirst('BREVO_SMTP_LOGIN');
        $this->brevoSmtpPassword = $this->envFirst('BREVO_SMTP_KEY', 'BREVO_SMTP_PASSWORD');

        $host = $this->envFirst('BREVO_SMTP_HOST');
        $this->brevoSmtpHost = $host !== '' ? $host : 'smtp-relay.brevo.com';
        $this->brevoSmtpPort = (int) (($p = $this->envFirst('BREVO_SMTP_PORT')) !== '' ? $p : '587');
    }

    /** Railway / CLI: prefer real env; getenv() alone is sometimes empty while $_SERVER holds the value. */
    private function envGet(string $name): string
    {
        $v = getenv($name);
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && trim($_SERVER[$name]) !== '') {
            return trim($_SERVER[$name]);
        }
        if (isset($_ENV[$name]) && is_string($_ENV[$name]) && trim($_ENV[$name]) !== '') {
            return trim($_ENV[$name]);
        }

        return '';
    }

    /** First non-empty value among several env keys (same resolution as envGet). */
    private function envFirst(string ...$names): string
    {
        foreach ($names as $n) {
            $v = $this->envGet($n);
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /** True if the runtime already defines this env name (Railway injection counts), even when empty. */
    private function envNameIsDefined(string $name): bool
    {
        if (getenv($name) !== false) {
            return true;
        }
        return array_key_exists($name, $_SERVER) || array_key_exists($name, $_ENV);
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

            if ($key === '' || $this->envNameIsDefined($key)) {
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

        if ($this->mailDriver === 'smtp') {
            return $this->sendOtpViaSmtp((string) $recipientEmail, (string) $otp);
        }

        return $this->sendOtpViaBrevo((string) $recipientEmail, (string) $otp);
    }

    private function sendOtpViaSmtp(string $recipientEmail, string $otp): bool
    {
        $this->lastSendError = null;

        if ($this->smtpHost === '' || $this->smtpUsername === '' || $this->smtpPassword === '') {
            $this->lastSendError = 'Mail not configured: set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD for MAIL_DRIVER=smtp';
            return false;
        }

        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_readable($autoload)) {
            $this->lastSendError = 'Composer vendor/autoload.php missing (needed for PHPMailer / SMTP).';
            return false;
        }

        try {
            require_once $autoload;

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = $this->smtpEncryption === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort > 0 ? $this->smtpPort : 587;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 15;
            $mail->Timelimit = 20;
            $mail->SMTPKeepAlive = false;

            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($recipientEmail);
            $mail->Subject = 'Your OTP Code';
            $mail->isHTML(true);
            $mail->Body = '<p>Your verification code is: <b>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</b></p>';
            $mail->AltBody = 'Your verification code is: ' . $otp;

            $mail->send();
            return true;
        } catch (PHPMailer\PHPMailer\Exception $e) {
            $this->lastSendError = 'SMTP: ' . $e->getMessage();
            error_log($this->lastSendError);
            return false;
        } catch (Throwable $e) {
            $this->lastSendError = 'SMTP: ' . $e->getMessage();
            error_log($this->lastSendError);
            return false;
        }
    }

    /**
     * Try HTTPS transactional API first (port 443), then SMTP. Hosts like Railway often block outbound :587.
     *
     * @see https://developers.brevo.com/reference/send-transac-email
     */
    private function sendOtpViaBrevo(string $recipientEmail, string $otp): bool
    {
        $this->lastSendError = null;
        $errors = [];

        $apiKeyCandidates = array_values(array_unique(array_filter([
            $this->envFirst('BREVO_API_KEY', 'BREVO_TRANSACTIONAL_API_KEY', 'BREVO_V3_API_KEY'),
            $this->envFirst('BREVO_SMTP_KEY', 'BREVO_SMTP_PASSWORD'),
        ])));
        foreach ($apiKeyCandidates as $apiKey) {
            if ($this->sendOtpViaBrevoTransactionalApi($recipientEmail, $otp, $apiKey)) {
                return true;
            }
            $errors[] = (string) ($this->lastSendError ?? 'Brevo API error');
        }

        if ($this->brevoSmtpLogin !== '' && $this->brevoSmtpPassword !== '') {
            if ($this->sendOtpViaBrevoSmtp($recipientEmail, $otp)) {
                return true;
            }
            $errors[] = (string) ($this->lastSendError ?? 'Brevo SMTP error');
        }

        if ($errors !== []) {
            $this->lastSendError = implode(' | ', array_values(array_unique($errors)));
            return false;
        }

        $this->lastSendError = 'Mail not configured: set BREVO_API_KEY (recommended) or BREVO_SMTP_LOGIN + BREVO_SMTP_KEY';
        return false;
    }

    private function sendOtpViaBrevoTransactionalApi(string $recipientEmail, string $otp, string $apiKey): bool
    {
        $this->lastSendError = null;
        $payload = [
            'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to' => [['email' => $recipientEmail]],
            'subject' => 'Your OTP Code',
            'htmlContent' => '<p>Your verification code is: <b>' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</b></p>',
            'textContent' => 'Your verification code is: ' . $otp,
        ];
        $body = json_encode($payload);
        if ($body === false) {
            $this->lastSendError = 'Brevo API: could not encode request';
            return false;
        }

        $endpoint = $this->envFirst('BREVO_API_URL') ?: 'https://api.brevo.com/v3/smtp/email';
        $headerLines = [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            if ($ch === false) {
                $this->lastSendError = 'Brevo API: curl_init failed';
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_CONNECTTIMEOUT => 12,
            ]);
            $response = curl_exec($ch);
            $curlErr = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($response === false && $curlErr !== '') {
                $this->lastSendError = 'Brevo API network: ' . $curlErr;
                error_log($this->lastSendError);
                return false;
            }

            if ($code >= 200 && $code < 300) {
                return true;
            }

            $snippet = ($response !== false && $response !== '') ? substr((string) $response, 0, 1500) : '';
            $this->lastSendError = 'Brevo API HTTP ' . $code . ($snippet !== '' ? (': ' . $snippet) : '');
            error_log($this->lastSendError);
            return false;
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines) . "\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($endpoint, false, $ctx);
        $status = 0;
        $rh = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        if (!empty($rh[0]) && preg_match('#\s(\d{3})\s#', $rh[0], $m)) {
            $status = (int) $m[1];
        }
        if ($status >= 200 && $status < 300) {
            return true;
        }
        $this->lastSendError = 'Brevo API HTTP ' . $status . ($response !== false && $response !== '' ? (': ' . substr((string) $response, 0, 280)) : '');
        if ($status === 0 && $response === false) {
            $this->lastSendError = 'Brevo API: request failed (check allow_url_fopen / TLS)';
        }
        error_log($this->lastSendError);
        return false;
    }

    private function sendOtpViaBrevoSmtp(string $recipientEmail, string $otp): bool
    {
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
            $mail->Timeout = 15;
            $mail->Timelimit = 20;
            $mail->SMTPKeepAlive = false;

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

