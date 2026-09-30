<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'lecturer') {
    header('Location: login.php');
    exit;
}

require_once 'db.php';
$sessionId = (int) ($_POST['session_id'] ?? 0);

if ($sessionId > 0) {
    $stmt = $conn->prepare(
        "UPDATE attendance_sessions
         SET status = 'closed'
         WHERE id = ? AND created_by = ? AND status = 'active'"
    );
    $stmt->bind_param('ii', $sessionId, $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();

    if ((int) ($_SESSION['active_attendance_session_id'] ?? 0) === $sessionId) {
        unset($_SESSION['active_attendance_session_id']);
    }
}

header('Location: lecturer_attendance.php?ended=1');
exit;
