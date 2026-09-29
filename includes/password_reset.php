<?php

declare(strict_types=1);

function ensurePasswordResetTable(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS password_reset_otps (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        verified_at DATETIME NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY idx_password_reset_user (user_id), KEY idx_password_reset_expires (expires_at),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return $conn->query($sql) === true;
}

function createPasswordResetOtp(mysqli $conn, int $userId): string
{
    $conn->query("UPDATE password_reset_otps SET used_at = NOW() WHERE user_id = {$userId} AND used_at IS NULL");
    $otp = (string) random_int(100000, 999999);
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $stmt = $conn->prepare('INSERT INTO password_reset_otps (user_id, otp_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $hash, $expiresAt);
    $stmt->execute();
    $stmt->close();
    return $otp;
}

function getActiveResetRequest(mysqli $conn, int $requestId): ?array
{
    $stmt = $conn->prepare('SELECT id, user_id, otp_hash, expires_at, attempts, verified_at, used_at FROM password_reset_otps WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}
