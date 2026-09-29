<?php
session_start();
require_once 'db.php';
require_once 'includes/otp_rate_limit.php';
require_once 'includes/registration_verification.php';
require_once 'includes/password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$type = (string) ($_GET['type'] ?? $_POST['type'] ?? '');
$redirect = $type === 'registration' ? 'verify_registration.php' : 'verify_otp.php';

function resendRedirect(string $location, string $status, string $message = ''): never
{
    $query = http_build_query(['resend' => $status] + ($message !== '' ? ['message' => $message] : []));
    header('Location: ' . $location . '?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    resendRedirect($redirect, 'error', 'Your session expired. Please try again.');
}

if ($type === 'registration') {
    $requestId = (int) ($_SESSION['registration_request_id'] ?? 0);
    $request = $requestId ? getRegistrationRequest($conn, $requestId) : null;
    if (!$request || $request['used_at'] !== null) {
        resendRedirect('register.php', 'error', 'Please start registration again.');
    }

    $rateError = otpResendRateLimit($conn, 'registration_otps', 'email', (string) $request['email'], 'registration_otp_last_sent');
    if ($rateError !== null) {
        resendRedirect($redirect, 'error', $rateError);
    }

    try {
        $pending = createRegistrationOtp($conn, (string) $request['fullname'], (string) $request['email'], (string) $request['password_hash'], (string) $request['role']);
        $_SESSION['registration_request_id'] = $pending['id'];
        require_once 'includes/mailer.php';
        $safeName = htmlspecialchars((string) $request['fullname'], ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6"><h2>Your new verification code</h2><p>Hello ' . $safeName . ',</p><p>Your new Smart Attendance System registration code is:</p><p style="font-size:30px;font-weight:bold;letter-spacing:8px">' . $pending['otp'] . '</p><p>This code expires in 10 minutes.</p></div>';
        sendMail((string) $request['email'], (string) $request['fullname'], 'Your new registration verification code', $html, "Your new registration verification code is {$pending['otp']}. It expires in 10 minutes.");
        markOtpResendSent('registration_otp_last_sent');
        resendRedirect($redirect, 'success', 'A new verification code has been sent.');
    } catch (Throwable $exception) {
        error_log('Registration OTP resend failed: ' . $exception->getMessage());
        resendRedirect($redirect, 'error', 'We could not send a new code. Please try again later.');
    }
}

if ($type === 'password_reset') {
    $requestId = (int) ($_SESSION['password_reset_request_id'] ?? 0);
    $request = $requestId ? getActiveResetRequest($conn, $requestId) : null;
    if (!$request || $request['used_at'] !== null) {
        resendRedirect('forgot_password.php', 'error', 'Please request a new reset code.');
    }

    $userStmt = $conn->prepare('SELECT fullname, email FROM users WHERE id = ? LIMIT 1');
    $userStmt->bind_param('i', $request['user_id']);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: null;
    $userStmt->close();
    if (!$user) {
        resendRedirect('forgot_password.php', 'error', 'Please request a new reset code.');
    }

    $userId = (int) $request['user_id'];
    $rateError = otpResendRateLimit($conn, 'password_reset_otps', 'user_id', (string) $userId, 'password_reset_otp_last_sent');
    if ($rateError !== null) {
        resendRedirect($redirect, 'error', $rateError);
    }

    try {
        $otp = createPasswordResetOtp($conn, $userId);
        $_SESSION['password_reset_request_id'] = $conn->insert_id;
        require_once 'includes/mailer.php';
        $safeName = htmlspecialchars((string) $user['fullname'], ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;line-height:1.6"><h2>Your new password reset code</h2><p>Hello ' . $safeName . ',</p><p>Your new Smart Attendance System password reset code is:</p><p style="font-size:30px;font-weight:bold;letter-spacing:8px">' . $otp . '</p><p>This code expires in 10 minutes.</p></div>';
        sendMail((string) $user['email'], (string) $user['fullname'], 'Your new password reset code', $html, "Your new password reset code is {$otp}. It expires in 10 minutes.");
        markOtpResendSent('password_reset_otp_last_sent');
        resendRedirect($redirect, 'success', 'A new verification code has been sent.');
    } catch (Throwable $exception) {
        error_log('Password reset OTP resend failed: ' . $exception->getMessage());
        resendRedirect($redirect, 'error', 'We could not send a new code. Please try again later.');
    }
}

resendRedirect('login.php', 'error', 'Invalid OTP request.');
