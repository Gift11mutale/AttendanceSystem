<?php

declare(strict_types=1);

function ensureRegistrationOtpTable(mysqli $conn): bool
{
    $sql = "CREATE TABLE IF NOT EXISTS registration_otps (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        fullname VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(30) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_registration_email (email),
        KEY idx_registration_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return $conn->query($sql) === true;
}

function createRegistrationOtp(mysqli $conn, string $fullname, string $email, string $passwordHash, string $role): array
{
    $stmt = $conn->prepare('UPDATE registration_otps SET used_at = NOW() WHERE email = ? AND used_at IS NULL');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();

    $otp = (string) random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $stmt = $conn->prepare('INSERT INTO registration_otps (fullname, email, password_hash, role, otp_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ssssss', $fullname, $email, $passwordHash, $role, $otpHash, $expiresAt);
    $stmt->execute();
    $requestId = $conn->insert_id;
    $stmt->close();
    return ['id' => $requestId, 'otp' => $otp];
}

function getRegistrationRequest(mysqli $conn, int $requestId): ?array
{
    $stmt = $conn->prepare('SELECT id, fullname, email, password_hash, role, otp_hash, expires_at, attempts, used_at FROM registration_otps WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function getLatestRegistrationRequestForEmail(mysqli $conn, string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT id, fullname, email, password_hash, role, otp_hash, expires_at, attempts, used_at FROM registration_otps WHERE email = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}
