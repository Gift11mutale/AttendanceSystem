<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'lecturer') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Lecturer login is required.']);
    exit;
}

require_once 'db.php';
require_once 'includes/attendance_qr.php';

$sessionId = (int) ($_GET['session_id'] ?? 0);
$stmt = $conn->prepare(
    "SELECT id, qr_token, qr_refresh_seconds, expires_at, status
     FROM attendance_sessions
     WHERE id = ? AND created_by = ? LIMIT 1"
);
$stmt->bind_param('ii', $sessionId, $_SESSION['user_id']);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$session || $session['status'] !== 'active' || (!empty($session['expires_at']) && strtotime((string) $session['expires_at']) < time())) {
    http_response_code(410);
    echo json_encode(['ok' => false, 'message' => 'This attendance session has ended or expired.']);
    exit;
}

$refreshSeconds = normalizeQrRefreshSeconds((int) ($session['qr_refresh_seconds'] ?? DEFAULT_QR_REFRESH_SECONDS));
$token = currentAttendanceQrToken((string) $session['qr_token'], $sessionId, $refreshSeconds);
$remaining = $refreshSeconds - (time() % $refreshSeconds);

echo json_encode([
    'ok' => true,
    'token' => $token,
    'refresh_seconds' => $refreshSeconds,
    'seconds_remaining' => $remaining,
    'expires_at' => $session['expires_at'],
]);
