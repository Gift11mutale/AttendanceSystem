<?php

declare(strict_types=1);

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

function smtpReadResponse($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 3 && $line[3] === ' ') {
            break;
        }
    }

    if ($response === '') {
        throw new RuntimeException('No response received from SMTP server.');
    }

    return trim($response);
}

function smtpExpect($socket, array $expectedCodes): string
{
    $response = smtpReadResponse($socket);
    foreach ($expectedCodes as $expectedCode) {
        if (str_starts_with($response, (string) $expectedCode)) {
            return $response;
        }
    }

    throw new RuntimeException('SMTP server rejected the command: ' . $response);
}

function smtpWriteLine($socket, string $command): void
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Failed to write SMTP command: ' . $command);
    }
}

function sendMailViaDirectSmtp(string $recipient, string $recipientName, string $subject, string $htmlBody, string $plainBody): void
{
    $host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $port = (int) (getenv('SMTP_PORT') ?: 587);
    $username = getenv('SMTP_USERNAME') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
    $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: $username;
    $fromName = getenv('MAIL_FROM_NAME') ?: 'Smart Attendance System';

    if ($username === '' || $password === '') {
        throw new RuntimeException('SMTP username and password are not configured.');
    }

    $transport = ($encryption === 'ssl') ? 'ssl://' : 'tcp://';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);

    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    if ($socket === false) {
        throw new RuntimeException('Could not connect to SMTP host: ' . $errstr . ' (' . $errno . ')');
    }

    smtpExpect($socket, [220]);

    if ($encryption === 'tls') {
        smtpWriteLine($socket, 'EHLO ' . $host);
        smtpExpect($socket, [250]);
        smtpWriteLine($socket, 'STARTTLS');
        smtpExpect($socket, [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Failed to enable STARTTLS for Gmail SMTP.');
        }
    }

    smtpWriteLine($socket, 'EHLO ' . $host);
    smtpExpect($socket, [250]);

    smtpWriteLine($socket, 'AUTH LOGIN');
    smtpExpect($socket, [334]);
    smtpWriteLine($socket, base64_encode($username));
    smtpExpect($socket, [334]);
    smtpWriteLine($socket, base64_encode($password));
    smtpExpect($socket, [235]);

    $fromHeader = sprintf('From: %s <%s>', $fromName, $fromAddress);
    $toHeader = sprintf('To: %s <%s>', $recipientName, $recipient);
    $subjectHeader = 'Subject: ' . $subject;
    $mimeHeader = 'MIME-Version: 1.0';
    $contentTypeHeader = 'Content-Type: text/html; charset=UTF-8';
    $boundary = '----=' . md5((string) microtime(true));
    $messageBody = "<html><body>{$htmlBody}</body></html>";

    $headers = [
        $fromHeader,
        $toHeader,
        $subjectHeader,
        $mimeHeader,
        $contentTypeHeader,
        'Content-Transfer-Encoding: quoted-printable',
        '',
    ];

    smtpWriteLine($socket, 'MAIL FROM:<' . $fromAddress . '>');
    smtpExpect($socket, [250]);
    smtpWriteLine($socket, 'RCPT TO:<' . $recipient . '>');
    smtpExpect($socket, [250, 251]);
    smtpWriteLine($socket, 'DATA');
    smtpExpect($socket, [354]);

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $messageBody . "\r\n." . "\r\n";
    fwrite($socket, $data);
    smtpExpect($socket, [250]);
    smtpWriteLine($socket, 'QUIT');
    fclose($socket);
}

/**
 * Send an email through Gmail SMTP using a Google App Password.
 */
function sendMail(string $recipient, string $recipientName, string $subject, string $htmlBody, string $plainBody): void
{
    loadMailEnvironment();

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USERNAME') ?: '';
            $mail->Password = getenv('SMTP_PASSWORD') ?: '';
            $mail->SMTPSecure = (getenv('SMTP_ENCRYPTION') ?: 'tls') === 'ssl'
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
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
            return;
        }
    }

    sendMailViaDirectSmtp($recipient, $recipientName, $subject, $htmlBody, $plainBody);
}
