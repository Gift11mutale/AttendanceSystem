<?php
session_start();
require_once 'db.php';
require_once 'includes/password_reset.php';

$requestId = (int) ($_SESSION['password_reset_request_id'] ?? 0);
if (!$requestId || empty($_SESSION['password_reset_verified'])) {
    header('Location: forgot_password.php');
    exit;
}
$request = getActiveResetRequest($conn, $requestId);
if (!$request || $request['verified_at'] === null || $request['used_at'] !== null || strtotime((string) $request['expires_at']) < time()) {
    unset($_SESSION['password_reset_request_id'], $_SESSION['password_reset_verified']);
    header('Location: forgot_password.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($password) < 8) {
        $error = 'Your new password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn->begin_transaction();
        $passwordStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $passwordStmt->bind_param('si', $hash, $request['user_id']);
        $passwordStmt->execute();
        $passwordStmt->close();
        $usedStmt = $conn->prepare('UPDATE password_reset_otps SET used_at = NOW() WHERE id = ?');
        $usedStmt->bind_param('i', $requestId);
        $usedStmt->execute();
        $usedStmt->close();
        $conn->commit();
        unset($_SESSION['password_reset_request_id'], $_SESSION['password_reset_email'], $_SESSION['password_reset_verified']);
        header('Location: login.php?reset=success');
        exit;
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Set New Password | Smart Attendance</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css"></head><body><div class="container-fluid login-page"><div class="row min-vh-100"><div class="col-lg-6 left-panel d-none d-lg-flex"><div class="branding"><img src="assets/images/kmu%20logo.png" class="logo" alt="KMU Logo"><h1>Smart Attendance &amp; Learning Insights System</h1><h4>Kapasa Makasa University</h4><p class="tagline">Secure • Intelligent • Reliable</p></div></div><div class="col-lg-6 d-flex align-items-center justify-content-center"><main class="login-card shadow-lg"><div class="text-center"><img src="assets/images/kmu%20logo.png" class="mobile-logo mb-3" alt="KMU Logo"><h2>Set New Password</h2><p class="text-muted">Choose a new password for your account.</p></div><?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><form method="post"><div class="mb-3"><label class="form-label fw-semibold" for="password">New Password</label><input class="form-control" id="password" name="password" type="password" minlength="8" required autofocus></div><div class="mb-4"><label class="form-label fw-semibold" for="confirm_password">Confirm New Password</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="8" required></div><button class="btn btn-success w-100 login-btn" type="submit">Update Password</button></form></main></div></div></div></body></html>
