<?php
session_start();
require_once 'db.php';
require_once 'includes/password_reset.php';

$message = '';
$error = '';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $generic = 'If an account exists for that email, a one-time verification code has been sent.';
        $message = $generic;

        if (filter_var($email, FILTER_VALIDATE_EMAIL) && ensurePasswordResetTable($conn)) {
            $stmt = $conn->prepare('SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            if ($user) {
                try {
                    $otp = createPasswordResetOtp($conn, (int) $user['id']);
                    require_once 'includes/mailer.php';
                    $name = htmlspecialchars((string) $user['fullname'], ENT_QUOTES, 'UTF-8');
                    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6"><h2>Password reset code</h2><p>Hello ' . $name . ',</p><p>Use this verification code to reset your Smart Attendance System password:</p><p style="font-size:30px;font-weight:bold;letter-spacing:8px">' . $otp . '</p><p>This code expires in 10 minutes. If you did not request a reset, you can ignore this email.</p></div>';
                    $plain = "Your Smart Attendance System password reset code is {$otp}. It expires in 10 minutes.";
                    sendMail((string) $user['email'], (string) $user['fullname'], 'Your password reset code', $html, $plain);
                    $_SESSION['password_reset_request_id'] = $conn->insert_id;
                    $_SESSION['password_reset_email'] = $email;
                    header('Location: verify_otp.php');
                    exit;
                } catch (Throwable $exception) {
                    error_log('Password reset email failed: ' . $exception->getMessage());
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Reset Password | Smart Attendance</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head>
<body><div class="container-fluid login-page"><div class="row min-vh-100"><div class="col-lg-6 left-panel d-none d-lg-flex"><div class="branding"><img src="assets/images/kmu%20logo.png" class="logo" alt="KMU Logo"><h1>Smart Attendance &amp; Learning Insights System</h1><h4>Kapasa Makasa University</h4><p class="tagline">Secure • Intelligent • Reliable</p></div></div><div class="col-lg-6 d-flex align-items-center justify-content-center"><main class="login-card shadow-lg"><div class="text-center"><img src="assets/images/kmu%20logo.png" class="mobile-logo mb-3" alt="KMU Logo"><h2>Forgot Password?</h2><p class="text-muted">Enter your email and we’ll send a 6-digit verification code.</p></div><?php if ($message): ?><div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><div class="mb-4"><label class="form-label fw-semibold" for="email">Email Address</label><input class="form-control" id="email" name="email" type="email" placeholder="Enter your registered email" required autofocus></div><button class="btn btn-success w-100 login-btn" type="submit"><i class="bi bi-envelope me-2"></i>Send Verification Code</button></form><div class="text-center mt-4"><a href="login.php" class="register-link">Back to Login</a></div></main></div></div></div></body></html>
