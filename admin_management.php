<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die('Access Denied - Administrators Only');
}

$view = $_GET['view'] ?? 'students';
$views = [
    'students' => ['Students', 'bi-people-fill'],
    'lecturers' => ['Lecturers', 'bi-person-workspace'],
    'courses' => ['Courses', 'bi-journal-bookmark-fill'],
    'attendance' => ['Attendance Sessions', 'bi-calendar-check-fill'],
    'reports' => ['Attendance Reports', 'bi-bar-chart-fill'],
];

if (!isset($views[$view])) {
    $view = 'students';
}

[$title, $icon] = $views[$view];

$queries = [
    'students' => "SELECT u.fullname AS Name, u.email AS Email, COUNT(DISTINCT e.course_id) AS Courses, COUNT(DISTINCT a.id) AS Attendance FROM users u LEFT JOIN enrollments e ON e.student_id = u.id LEFT JOIN attendance a ON a.student_id = u.id AND a.status = 'present' WHERE u.role = 'student' GROUP BY u.id, u.fullname, u.email ORDER BY u.fullname",
    'lecturers' => "SELECT u.fullname AS Name, u.email AS Email, COUNT(DISTINCT c.id) AS Courses, COUNT(DISTINCT s.id) AS Sessions FROM users u LEFT JOIN courses c ON c.lecturer_id = u.id LEFT JOIN attendance_sessions s ON s.course_id = c.id WHERE u.role = 'lecturer' GROUP BY u.id, u.fullname, u.email ORDER BY u.fullname",
    'courses' => "SELECT c.course_name AS Course, c.course_code AS Code, u.fullname AS Lecturer, COUNT(DISTINCT e.student_id) AS Students, COUNT(DISTINCT s.id) AS Sessions FROM courses c LEFT JOIN users u ON u.id = c.lecturer_id LEFT JOIN enrollments e ON e.course_id = c.id LEFT JOIN attendance_sessions s ON s.course_id = c.id GROUP BY c.id, c.course_name, c.course_code, u.fullname ORDER BY c.course_name",
    'attendance' => "SELECT s.session_date AS Date, c.course_name AS Course, s.session_code AS 'Session Code', s.status AS Status, COUNT(a.id) AS Present FROM attendance_sessions s INNER JOIN courses c ON c.id = s.course_id LEFT JOIN attendance a ON a.session_id = s.id AND a.status = 'present' GROUP BY s.id, s.session_date, c.course_name, s.session_code, s.status ORDER BY s.session_date DESC, s.id DESC",
    'reports' => "SELECT c.course_name AS Course, c.course_code AS Code, COUNT(DISTINCT e.student_id) AS Enrolled, COUNT(DISTINCT s.id) AS Sessions, COUNT(DISTINCT a.id) AS Present, GREATEST((COUNT(DISTINCT e.student_id) * COUNT(DISTINCT s.id)) - COUNT(DISTINCT a.id), 0) AS Absent FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id LEFT JOIN attendance_sessions s ON s.course_id = c.id LEFT JOIN attendance a ON a.session_id = s.id AND a.status = 'present' GROUP BY c.id, c.course_name, c.course_code ORDER BY c.course_name",
];

$result = $conn->query($queries[$view]);
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

include "includes/header.php";
include "includes/sidebar.php";
?>

<div class="main-content">
    <?php include "includes/navbar.php"; ?>
    <div class="container-fluid">
        <div class="dashboard-header mb-4">
            <h1><i class="bi <?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($title); ?></h1>
            <p>Review system-wide <?php echo strtolower(htmlspecialchars($title)); ?>.</p>
        </div>

        <div class="card dashboard-card border-0 shadow-sm">
            <div class="card-body p-4">
                <?php if ($view === 'reports' && !empty($rows)): ?>
                    <div class="admin-report-grid">
                        <?php foreach ($rows as $row): ?>
                            <article class="admin-report-card">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($row['Course']); ?></h5>
                                        <span class="badge bg-success"><?php echo htmlspecialchars($row['Code']); ?></span>
                                    </div>
                                    <i class="bi bi-bar-chart-fill text-success fs-4"></i>
                                </div>
                                <div class="admin-report-stats">
                                    <span><small>Enrolled</small><strong><?php echo (int) $row['Enrolled']; ?></strong></span>
                                    <span><small>Sessions</small><strong><?php echo (int) $row['Sessions']; ?></strong></span>
                                    <span><small>Present</small><strong class="text-success"><?php echo (int) $row['Present']; ?></strong></span>
                                    <span><small>Absent</small><strong class="text-danger"><?php echo (int) $row['Absent']; ?></strong></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($rows)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle responsive-card-table admin-data-table">
                            <thead><tr>
                                <?php foreach (array_keys($rows[0]) as $heading): ?>
                                    <th><?php echo htmlspecialchars($heading); ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?><tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?php echo htmlspecialchars((string) $value); ?></td>
                                    <?php endforeach; ?>
                                </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No <?php echo strtolower(htmlspecialchars($title)); ?> found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
</div>
