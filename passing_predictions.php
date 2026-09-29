<?php
session_start();
include "db.php";

$role = $_SESSION['role'] ?? '';

if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'lecturer', 'student'], true)) {
    die('Access Denied');
}

$sql = "
    SELECT
        u.fullname,
        u.email,
        c.course_name,
        c.course_code,
        e.enrolled_at,
        (
            SELECT COUNT(*)
            FROM attendance_sessions s
            WHERE s.course_id = c.id
              AND s.session_date >= DATE(e.enrolled_at)
              AND s.session_date <= CURDATE()
        ) AS sessions,
        (
            SELECT COUNT(DISTINCT a.session_id)
            FROM attendance a
            INNER JOIN attendance_sessions s ON s.id = a.session_id
            WHERE a.student_id = u.id
              AND s.course_id = c.id
              AND a.status = 'present'
              AND s.session_date >= DATE(e.enrolled_at)
              AND s.session_date <= CURDATE()
        ) AS attended
    FROM enrollments e
    INNER JOIN users u ON u.id = e.student_id
    INNER JOIN courses c ON c.id = e.course_id
";

if ($role === 'lecturer') {
    $sql .= " WHERE c.lecturer_id = ?";
} elseif ($role === 'student') {
    $sql .= " WHERE e.student_id = ?";
}

$sql .= " ORDER BY c.course_name ASC, u.fullname ASC";

$predictions = [];

if ($role === 'lecturer' || $role === 'student') {
    $stmt = $conn->prepare($sql);
    $scopedUserId = (int) $_SESSION['user_id'];
    $stmt->bind_param('i', $scopedUserId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sessions = (int) $row['sessions'];
        $attended = (int) $row['attended'];
        $rate = $sessions > 0 ? ($attended / $sessions) * 100 : null;

        if ($rate === null) {
            $prediction = 'Awaiting attendance data';
            $class = 'secondary';
            $likelihood = null;
        } elseif ($rate >= 75) {
            $prediction = 'Likely to pass';
            $class = 'success';
            $likelihood = min(100, round($rate + 15));
        } elseif ($rate >= 60) {
            $prediction = 'Possible with improvement';
            $class = 'warning';
            $likelihood = round($rate + 5);
        } else {
            $prediction = 'At risk of not passing';
            $class = 'danger';
            $likelihood = max(0, round($rate - 15));
        }

        $row['rate'] = $rate;
        $row['prediction'] = $prediction;
        $row['class'] = $class;
        $row['likelihood'] = $likelihood;
        $predictions[] = $row;
    }
}

if (isset($stmt)) {
    $stmt->close();
}

include "includes/header.php";
include "includes/sidebar.php";
?>

<div class="main-content">
    <?php include "includes/navbar.php"; ?>
    <div class="container-fluid">
        <div class="dashboard-header mb-4">
            <h1><i class="bi bi-graph-up-arrow me-2"></i>Course Passing Predictions</h1>
            <p><?php echo $role === 'student' ? 'Your attendance-based passing predictions for each enrolled course.' : 'Attendance-based predictions for each student and enrolled course.'; ?></p>
        </div>

        <div class="alert alert-info prediction-note">
            <i class="bi bi-info-circle-fill me-2"></i>
            Predictions use attendance only and are not final academic results.
        </div>

        <div class="card dashboard-card border-0 shadow-sm">
            <div class="card-body p-4">
                <?php if (!empty($predictions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle responsive-card-table prediction-table">
                            <thead><tr>
                                <th>Student</th><th>Course</th><th>Attended</th><th>Sessions</th><th>Attendance</th><th>Pass Likelihood</th><th>Prediction</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($predictions as $prediction): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($prediction['fullname']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($prediction['email']); ?></small></td>
                                        <td><?php echo htmlspecialchars($prediction['course_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($prediction['course_code']); ?></small></td>
                                        <td><?php echo (int) $prediction['attended']; ?></td>
                                        <td><?php echo (int) $prediction['sessions']; ?></td>
                                        <td><?php echo $prediction['rate'] === null ? '—' : number_format($prediction['rate'], 1) . '%'; ?></td>
                                        <td><?php echo $prediction['likelihood'] === null ? '—' : (int) $prediction['likelihood'] . '%'; ?></td>
                                        <td><span class="badge bg-<?php echo $prediction['class']; ?><?php echo $prediction['class'] === 'warning' ? ' text-dark' : ''; ?>"><?php echo htmlspecialchars($prediction['prediction']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted"><i class="bi bi-people fs-1 d-block mb-2"></i>No enrolled students are available for predictions yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
</div>
