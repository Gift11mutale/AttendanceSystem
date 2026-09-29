<?php

declare(strict_types=1);

/**
 * Return an error message when an OTP resend is too frequent or too numerous.
 * Table and column values are selected only from fixed internal callers.
 */
function otpResendRateLimit(mysqli $conn, string $table, string $column, string $value, string $sessionKey): ?string
{
    $lastSentAt = (int) ($_SESSION[$sessionKey] ?? 0);
    $secondsSinceLast = time() - $lastSentAt;
    if ($lastSentAt > 0 && $secondsSinceLast < 60) {
        return 'Please wait ' . (60 - $secondsSinceLast) . ' seconds before requesting another code.';
    }

    $allowedTables = ['registration_otps', 'password_reset_otps'];
    $allowedColumns = ['email', 'user_id'];
    if (!in_array($table, $allowedTables, true) || !in_array($column, $allowedColumns, true)) {
        return 'Unable to resend the code right now.';
    }

    $sql = "SELECT COUNT(*) AS resend_count FROM {$table} WHERE {$column} = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($column === 'user_id' ? 'i' : 's', $value);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['resend_count'] ?? 0);
    $stmt->close();
    if ($count >= 5) {
        return 'Too many code requests. Please try again later.';
    }

    return null;
}

function markOtpResendSent(string $sessionKey): void
{
    $_SESSION[$sessionKey] = time();
}
