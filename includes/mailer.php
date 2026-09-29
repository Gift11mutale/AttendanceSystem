<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('PHPMailer is not installed. Run composer install.');
}
require_once $autoload;

// PHP does not automatically load .env files when running under Apache/Nginx.
// Load simple KEY=VALUE entries when the host has not already exported them.
function loadMailEnvironment(): void
{
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($value !== '' && (($value[0] ?? '') === '"' || ($value[0] ?? '') === "'")) {
            $value = trim($value, "\"'");
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
        }
    }
}

/**
 * Send an email through Gmail SMTP using a Google App Password.
 */
function sendMail(string $recipient, string $recipientName, string $subject, string $htmlBody, string $plainBody): void
{
    loadMailEnvironment();
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('SMTP_USERNAME') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
    $mail->SMTPSecure = (getenv('SMTP_ENCRYPTION') ?: 'tls') === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) (getenv('SMTP_PORT') ?: 587);
    $mail->CharSet = 'UTF-8';

    $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $mail->Username;
    $fromName = getenv('MAIL_FROM_NAME') ?: 'Smart Attendance System';
    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($recipient, $recipientName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $htmlBody;
    $mail->AltBody = $plainBody;
    $mail->send();
}
