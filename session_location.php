<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Student login is required.']);
    exit;
}

require_once 'db.php';
require_once 'includes/geofence.php';
require_once 'includes/attendance_qr.php';
$token = trim((string) ($_POST['qr_token'] ?? ''));
if ($token === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'QR token is required.']);
    exit;
}

$session = findSessionByAttendanceToken($conn, $token);

if (!$session || (!empty($session['expires_at']) && strtotime((string) $session['expires_at']) < time())) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'This attendance session is invalid or expired.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'latitude' => is_numeric($session['latitude'] ?? null) ? (float) $session['latitude'] : null,
    'longitude' => is_numeric($session['longitude'] ?? null) ? (float) $session['longitude'] : null,
    'radius' => (int) ($session['radius'] ?? 0),
]);
