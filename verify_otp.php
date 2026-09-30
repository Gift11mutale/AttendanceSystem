<?php
session_start();
require_once 'db.php';
require_once 'includes/password_reset.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$requestId = (int) ($_SESSION['password_reset_request_id'] ?? 0);
$request = $requestId ? getActiveResetRequest($conn, $requestId) : null;
if (!$request || $request['used_at'] !== null) {
    header('Location: forgot_password.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? ''));
    $expired = strtotime((string) $request['expires_at']) < time();
    if ($expired || (int) $request['attempts'] >= 5) {
        $error = 'This code has expired or reached its maximum attempts. Request a new code.';
    } elseif (strlen($otp) !== 6 || !password_verify($otp, (string) $request['otp_hash'])) {
        $stmt = $conn->prepare('UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = ?');
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->close();
        $error = 'That code is not valid. Please check the email and try again.';
        $request['attempts']++;
    } else {
        $stmt = $conn->prepare('UPDATE password_reset_otps SET verified_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['password_reset_verified'] = true;
        header('Location: reset_password.php');
        exit;
    }
}
$resendMessage = (string) ($_GET['message'] ?? '');
$resendCooldown = max(0, 60 - (time() - (int) ($_SESSION['password_reset_otp_last_sent'] ?? 0)));
?>
<!doctype html>
<html lang="en"><head>
<link rel="icon" type="image/png" href="assets/images/favicon.png"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify Code | Smart Attendance</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/custom-popups.css">
<script src="assets/js/custom-popups.js" defer></script></head>
<body><div class="container-fluid login-page"><div class="row min-vh-100"><div class="col-lg-6 left-panel d-none d-lg-flex"><div class="branding"><img src="assets/images/kmu%20logo.png" class="logo" alt="KMU Logo"><h1>Smart Attendance &amp; Learning Insights System</h1><h4>Kapasa Makasa University</h4><p class="tagline">Secure • Intelligent • Reliable</p></div></div><div class="col-lg-6 d-flex align-items-center justify-content-center"><main class="login-card shadow-lg"><div class="text-center"><img src="assets/images/kmu%20logo.png" class="mobile-logo mb-3" alt="KMU Logo"><h2>Verify Your Code</h2><p class="text-muted">Enter the 6-digit code sent to <strong><?php echo htmlspecialchars((string) ($_SESSION['password_reset_email'] ?? 'your email')); ?></strong>.</p></div><?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?><?php if ($resendMessage): ?><div class="alert alert-<?php echo ($_GET['resend'] ?? '') === 'success' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($resendMessage); ?></div><?php endif; ?><form method="post"><div class="mb-4"><label class="form-label fw-semibold" for="otp">Verification Code</label><input class="form-control text-center" style="letter-spacing:8px;font-size:1.4rem" id="otp" name="otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus></div><button class="btn btn-success w-100 login-btn" type="submit">Verify Code</button></form><div class="text-center mt-4"><form method="post" action="resend_otp.php?type=password_reset" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"><button class="btn btn-link register-link p-0" type="submit">Resend code</button></form></div></main></div></div></div></body></html>

<script>
(() => {
    const cooldown = <?php echo (int) $resendCooldown; ?>;
    const button = document.querySelector('form[action^="resend_otp.php"] button[type="submit"]');
    if (!button || cooldown <= 0) return;
    const originalText = button.textContent.trim();
    let remaining = cooldown;
    button.disabled = true;
    const tick = () => {
        button.textContent = `Resend code (${remaining}s)`;
        if (remaining <= 0) {
            button.disabled = false;
            button.textContent = originalText;
            clearInterval(timer);
        }
        remaining -= 1;
    };
    tick();
    const timer = setInterval(tick, 1000);
})();
</script>
<script>
(() => {
    const input = document.querySelector('input[name="otp"]');
    if (!input) return;
    const form = input.closest('form');
    let submitted = false;
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0, 6);
        if (input.value.length === 6 && !submitted) {
            submitted = true;
            input.readOnly = true;
            form.submit();
        }
    });
})();
</script>
<script>
(() => {
    const hidden = document.querySelector('input[name="otp"]');
    if (!hidden) return;
    hidden.type = 'hidden';
    hidden.required = false;

    const wrapper = document.createElement('div');
    wrapper.className = 'd-flex justify-content-center gap-2';
    wrapper.setAttribute('aria-label', 'Six-digit verification code');
    const boxes = [];
    let submitted = false;

    for (let index = 0; index < 6; index += 1) {
        const box = document.createElement('input');
        box.type = 'text';
        box.inputMode = 'numeric';
        box.maxLength = 1;
        box.className = 'form-control text-center otp-digit';
        box.style.cssText = 'width:48px;height:56px;font-size:1.5rem;font-weight:600;';
        box.setAttribute('aria-label', `Verification digit ${index + 1}`);
        wrapper.appendChild(box);
        boxes.push(box);

        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(-1);
            if (box.value && index < 5) boxes[index + 1].focus();
            hidden.value = boxes.map((digit) => digit.value).join('');
            if (hidden.value.length === 6 && !submitted) {
                submitted = true;
                boxes.forEach((digit) => { digit.readOnly = true; });
                hidden.form.submit();
            }
        });

        box.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !box.value && index > 0) {
                boxes[index - 1].focus();
            }
        });

        box.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
            pasted.split('').forEach((digit, offset) => {
                if (boxes[index + offset]) boxes[index + offset].value = digit;
            });
            hidden.value = boxes.map((digit) => digit.value).join('');
            const next = Math.min(index + pasted.length, 5);
            boxes[next].focus();
            if (hidden.value.length === 6 && !submitted) {
                submitted = true;
                boxes.forEach((digit) => { digit.readOnly = true; });
                hidden.form.submit();
            }
        });
    }

    hidden.parentNode.insertBefore(wrapper, hidden);
    boxes[0].focus();
})();
</script>
