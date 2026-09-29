<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    die('Access Denied');
}

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$notifications = [];

function addNotification(&$notifications, $type, $title, $message, $link)
{
    $notifications[] = compact('type', 'title', 'message', 'link');
}

if ($role === 'student') {
    $activeStmt = $conn->prepare("SELECT c.course_name, s.session_code, s.expires_at FROM attendance_sessions s INNER JOIN enrollments e ON e.course_id = s.course_id INNER JOIN courses c ON c.id = s.course_id WHERE e.student_id = ? AND s.status = 'active' AND (s.expires_at IS NULL OR s.expires_at >= NOW()) ORDER BY s.expires_at ASC");
    $activeStmt->bind_param('i', $userId);
    $activeStmt->execute();
    $activeSessions = $activeStmt->get_result();
    while ($session = $activeSessions->fetch_assoc()) {
        addNotification($notifications, 'primary', 'Attendance session is open', $session['course_name'] . ' is accepting attendance now.', 'scan_attendance.php');
    }
    $activeStmt->close();

    $riskSql = "SELECT c.course_name, COUNT(DISTINCT s.id) AS sessions, COUNT(DISTINCT a.id) AS attended FROM enrollments e INNER JOIN courses c ON c.id = e.course_id LEFT JOIN attendance_sessions s ON s.course_id = c.id AND s.session_date >= DATE(e.enrolled_at) AND s.session_date <= CURDATE() LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = e.student_id AND a.status = 'present' WHERE e.student_id = ? GROUP BY c.id, c.course_name, e.enrolled_at";
    $riskStmt = $conn->prepare($riskSql);
    $riskStmt->bind_param('i', $userId);
    $riskStmt->execute();
    $riskRows = $riskStmt->get_result();
    while ($row = $riskRows->fetch_assoc()) {
        $sessions = (int) $row['sessions'];
        $rate = $sessions ? ((int) $row['attended'] / $sessions) * 100 : null;
        if ($rate !== null && $rate < 60) {
            addNotification($notifications, 'danger', 'Critical passing risk', $row['course_name'] . ' attendance is ' . number_format($rate, 1) . '%. Attend upcoming sessions to improve your passing possibility.', 'student_performance.php');
        }
    }
    $riskStmt->close();
} elseif ($role === 'lecturer') {
    $activeStmt = $conn->prepare("SELECT c.course_name FROM attendance_sessions s INNER JOIN courses c ON c.id = s.course_id WHERE c.lecturer_id = ? AND s.status = 'active' AND (s.expires_at IS NULL OR s.expires_at >= NOW()) ORDER BY s.expires_at ASC");
    $activeStmt->bind_param('i', $userId);
    $activeStmt->execute();
    $activeSessions = $activeStmt->get_result();
    while ($session = $activeSessions->fetch_assoc()) {
        addNotification($notifications, 'primary', 'Attendance session active', 'Your ' . $session['course_name'] . ' attendance session is currently open.', 'lecturer_attendance.php');
    }
    $activeStmt->close();

    $riskStmt = $conn->prepare("SELECT u.fullname, c.course_name, COUNT(DISTINCT s.id) AS sessions, COUNT(DISTINCT a.id) AS attended FROM enrollments e INNER JOIN users u ON u.id = e.student_id INNER JOIN courses c ON c.id = e.course_id LEFT JOIN attendance_sessions s ON s.course_id = c.id AND s.session_date >= DATE(e.enrolled_at) AND s.session_date <= CURDATE() LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id AND a.status = 'present' WHERE c.lecturer_id = ? GROUP BY u.id, u.fullname, c.id, c.course_name, e.enrolled_at");
    $riskStmt->bind_param('i', $userId);
    $riskStmt->execute();
    $riskRows = $riskStmt->get_result();
    while ($row = $riskRows->fetch_assoc()) {
        $sessions = (int) $row['sessions'];
        if ($sessions > 0 && ((int) $row['attended'] / $sessions) * 100 < 60) {
            addNotification($notifications, 'danger', 'Student at critical risk', $row['fullname'] . ' has critical attendance in ' . $row['course_name'] . '.', 'passing_predictions.php');
        }
    }
    $riskStmt->close();
} elseif ($role === 'admin') {
    $activeSessions = $conn->query("SELECT c.course_name FROM attendance_sessions s INNER JOIN courses c ON c.id = s.course_id WHERE s.status = 'active' AND (s.expires_at IS NULL OR s.expires_at >= NOW()) ORDER BY s.expires_at ASC");
    while ($session = $activeSessions->fetch_assoc()) {
        addNotification($notifications, 'primary', 'Attendance session active', $session['course_name'] . ' currently has an open attendance session.', 'admin_management.php?view=attendance');
    }

    $riskRows = $conn->query("SELECT u.fullname, c.course_name, COUNT(DISTINCT s.id) AS sessions, COUNT(DISTINCT a.id) AS attended FROM enrollments e INNER JOIN users u ON u.id = e.student_id INNER JOIN courses c ON c.id = e.course_id LEFT JOIN attendance_sessions s ON s.course_id = c.id AND s.session_date >= DATE(e.enrolled_at) AND s.session_date <= CURDATE() LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = u.id AND a.status = 'present' GROUP BY u.id, u.fullname, c.id, c.course_name, e.enrolled_at");
    while ($row = $riskRows->fetch_assoc()) {
        $sessions = (int) $row['sessions'];
        if ($sessions > 0 && ((int) $row['attended'] / $sessions) * 100 < 60) {
            addNotification($notifications, 'danger', 'Critical student attendance', $row['fullname'] . ' is at risk in ' . $row['course_name'] . '.', 'passing_predictions.php');
        }
    }
}

include "includes/header.php";
include "includes/sidebar.php";
?>

<div class="main-content">
    <?php include "includes/navbar.php"; ?>
    <div class="container-fluid">
        <div class="dashboard-header mb-4">
            <h1><i class="bi bi-bell-fill me-2"></i>Notifications</h1>
            <p>Live attendance updates and critical passing-risk alerts.</p>
        </div>
        <div class="card dashboard-card border-0 shadow-sm"><div class="card-body p-4">
            <?php if (!empty($notifications)): ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <a class="notification-item notification-<?php echo $notification['type']; ?>" href="<?php echo htmlspecialchars($notification['link']); ?>">
                            <i class="bi <?php echo $notification['type'] === 'danger' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill'; ?>"></i>
                            <span><strong><?php echo htmlspecialchars($notification['title']); ?></strong><small><?php echo htmlspecialchars($notification['message']); ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted"><i class="bi bi-bell-slash fs-1 d-block mb-2"></i>You are up to date. There are no critical notifications.</div>
            <?php endif; ?>
        </div></div>
    </div>
    <?php include "includes/footer.php"; ?>
</div>
