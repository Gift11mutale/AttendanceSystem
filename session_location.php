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
$token = trim((string) ($_POST['qr_token'] ?? ''));
if ($token === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'QR token is required.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, latitude, longitude, radius, expires_at FROM attendance_sessions WHERE qr_token = ? AND status = 'active' LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$session || (!empty($session['expires_at']) && strtotime((string) $session['expires_at']) < time())) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'This attendance session is invalid or expired.']);
    exit;
}

if (!is_numeric($session['latitude']) || !is_numeric($session['longitude']) || (int) $session['radius'] <= 0 || !validGpsCoordinates((float) $session['latitude'], (float) $session['longitude'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'This session does not have a valid GPS location.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'latitude' => (float) $session['latitude'],
    'longitude' => (float) $session['longitude'],
    'radius' => (int) $session['radius'],
]);
