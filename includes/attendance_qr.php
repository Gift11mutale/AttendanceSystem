<?php

declare(strict_types=1);

const DEFAULT_QR_REFRESH_SECONDS = 15;
const MIN_QR_REFRESH_SECONDS = 15;
const MAX_QR_REFRESH_SECONDS = 300;

function normalizeQrRefreshSeconds(int $seconds): int
{
    return max(MIN_QR_REFRESH_SECONDS, min(MAX_QR_REFRESH_SECONDS, $seconds));
}

function rotatingAttendanceToken(string $secret, int $sessionId, int $timestamp, int $refreshSeconds): string
{
    $refreshSeconds = normalizeQrRefreshSeconds($refreshSeconds);
    $slot = intdiv($timestamp, $refreshSeconds);
    return hash_hmac('sha256', $sessionId . ':' . $slot, $secret);
}

function currentAttendanceQrToken(string $secret, int $sessionId, int $refreshSeconds): string
{
    return rotatingAttendanceToken($secret, $sessionId, time(), $refreshSeconds);
}

/**
 * Resolve a scanned rotating token to an active session.
 * The current and immediately previous time slots are accepted to allow for
 * camera/network delay at a rotation boundary.
 */
function findSessionByAttendanceToken(mysqli $conn, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT id, course_id, session_code, qr_token, qr_refresh_seconds,
                expires_at, status, latitude, longitude, radius, created_by
         FROM attendance_sessions
         WHERE status = 'active'
           AND (expires_at IS NULL OR expires_at >= NOW())"
    );
    if (!$stmt || !$stmt->execute()) {
        if ($stmt) {
            $stmt->close();
        }
        return null;
    }

    $result = $stmt->get_result();
    $now = time();
    $matched = null;
    while ($row = $result->fetch_assoc()) {
        $refreshSeconds = normalizeQrRefreshSeconds((int) ($row['qr_refresh_seconds'] ?? DEFAULT_QR_REFRESH_SECONDS));
        $secret = (string) $row['qr_token'];
        $sessionId = (int) $row['id'];
        for ($offset = 0; $offset <= 1; $offset++) {
            $candidate = rotatingAttendanceToken($secret, $sessionId, $now - ($offset * $refreshSeconds), $refreshSeconds);
            if (hash_equals($candidate, $token)) {
                $matched = $row;
                break 2;
            }
        }
    }
    $stmt->close();
    return $matched;
}
