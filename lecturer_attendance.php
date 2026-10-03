<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

$lecturer_id = $_SESSION['user_id'];
$selected_course_id = isset($_GET['course_id'])
    ? (int) $_GET['course_id']
    : 0;

$selected_session = isset($_GET['session_id'])
    ? (int) $_GET['session_id']
    : 0;

$sql = "
    SELECT
        s.id AS session_id,
        s.session_date,
        s.session_code,
        s.status,
        s.expires_at,
        s.created_at,
        c.course_name,
        c.course_code,
        COUNT(DISTINCT e.student_id) AS students_registered,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN e.student_id END) AS students_present,
        COUNT(DISTINCT e.student_id) - COUNT(DISTINCT CASE WHEN a.status = 'present' THEN e.student_id END) AS students_absent
    FROM attendance_sessions s
    INNER JOIN courses c
        ON s.course_id = c.id
    LEFT JOIN enrollments e
        ON e.course_id = s.course_id
    LEFT JOIN attendance a
        ON s.id = a.session_id
        AND a.student_id = e.student_id
        AND a.status = 'present'
    WHERE s.created_by = ?
      AND (? = 0 OR s.course_id = ?)
    GROUP BY
        s.id,
        s.session_date,
        s.session_code,
        s.status,
        s.expires_at,
        s.created_at,
        c.course_name,
        c.course_code
    ORDER BY s.session_date DESC, s.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $lecturer_id, $selected_course_id, $selected_course_id);
$stmt->execute();
$sessions = $stmt->get_result();

$session_rows = [];

while ($row = $sessions->fetch_assoc()) {
    $session_rows[] = $row;
}

$total_sessions = count($session_rows);
$total_present = 0;
$active_sessions = 0;

foreach ($session_rows as $row) {
    $total_present += (int) $row['students_present'];

    if ($row['status'] === 'active') {
        $active_sessions++;
    }
}

$session_info = null;
$students = null;

if ($selected_session > 0) {

    $session_sql = "
        SELECT
            s.id AS session_id,
            s.session_date,
            s.session_code,
            s.status,
            s.expires_at,
            s.created_at,
            c.course_name,
            c.course_code
        FROM attendance_sessions s
        INNER JOIN courses c
            ON s.course_id = c.id
        WHERE s.id = ?
          AND s.created_by = ?
        LIMIT 1
    ";

    $session_stmt = $conn->prepare($session_sql);
    $session_stmt->bind_param(
        "ii",
        $selected_session,
        $lecturer_id
    );
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();

    if ($session_result->num_rows === 1) {

        $session_info = $session_result->fetch_assoc();

        $students_sql = "
            SELECT
                u.id AS student_id,
                u.fullname,
                u.email,
                COALESCE(a.status, 'absent') AS status,
                a.scan_time
            FROM enrollments e
            INNER JOIN users u
                ON e.student_id = u.id
            LEFT JOIN attendance a
                ON a.session_id = ?
                AND a.student_id = e.student_id
            WHERE e.course_id = (
                SELECT course_id
                FROM attendance_sessions
                WHERE id = ?
            )
            ORDER BY (a.status IS NULL) DESC, u.fullname ASC
        ";

        $students_stmt = $conn->prepare($students_sql);
        $students_stmt->bind_param("ii", $selected_session, $selected_session);
        $students_stmt->execute();
        $students = $students_stmt->get_result();
    }
}

function attendanceStatusBadge($status)
{
    $status = strtolower((string) $status);

    if ($status === 'active' || $status === 'present') {
        return 'bg-success';
    }

    if ($status === 'closed' || $status === 'absent') {
        return 'bg-danger';
    }

    return 'bg-secondary';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | Smart Attendance System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=3">


<link rel="stylesheet" href="assets/css/custom-popups.css">
<script src="assets/js/custom-popups.js" defer></script></head>

<body>

<div class="wrapper">

    <?php include "includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "includes/navbar.php"; ?>

        <div class="container-fluid">

            <div class="dashboard-header mb-4">

                <h1>Attendance Management</h1>

                <p>
                    View attendance sessions and monitor student attendance.
                </p>

            </div>


            <div class="row g-4 mb-4">

                <div class="col-lg-4 col-md-6">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>
                                    <h6 class="text-muted">Total Sessions</h6>
                                    <h2 class="fw-bold mb-0">
                                        <?php echo $total_sessions; ?>
                                    </h2>
                                </div>

                                <div class="icon-circle bg-primary">
                                    <i class="bi bi-calendar-event-fill"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>
                                    <h6 class="text-muted">Active Sessions</h6>
                                    <h2 class="fw-bold mb-0">
                                        <?php echo $active_sessions; ?>
                                    </h2>
                                </div>

                                <div class="icon-circle bg-success">
                                    <i class="bi bi-broadcast"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-4 col-md-6">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>
                                    <h6 class="text-muted">Students Present</h6>
                                    <h2 class="fw-bold mb-0">
                                        <?php echo $total_present; ?>
                                    </h2>
                                </div>

                                <div class="icon-circle bg-warning">
                                    <i class="bi bi-people-fill"></i>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <?php if ($selected_session > 0): ?>

                <?php if ($session_info): ?>

                    <div class="card dashboard-card border-0 shadow-sm mb-4">

                        <div class="card-header bg-white">

                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">

                                <div>

                                    <h5 class="mb-1">

                                        <i class="bi bi-calendar-check-fill text-success me-2"></i>

                                        <?php echo htmlspecialchars($session_info['course_name']); ?>

                                    </h5>

                                    <div class="text-muted small">

                                        <span class="me-3">
                                            <strong>Code:</strong>
                                            <?php echo htmlspecialchars($session_info['course_code']); ?>
                                        </span>

                                        <span class="me-3">
                                            <strong>Date:</strong>
                                            <?php echo htmlspecialchars($session_info['session_date']); ?>
                                        </span>

                                        <span class="me-3">
                                            <strong>Session:</strong>
                                            <?php echo htmlspecialchars($session_info['session_code'] ?? 'N/A'); ?>
                                        </span>

                                        <span class="badge <?php echo attendanceStatusBadge($session_info['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($session_info['status'])); ?>
                                        </span>

                                    </div>

                                </div>

                                <a
                                    href="lecturer_attendance.php"
                                    class="btn btn-outline-secondary">

                                    <i class="bi bi-arrow-left me-1"></i>
                                    Back to Sessions

                                </a>

                            </div>

                        </div>

                        <div class="card-body">

                            <?php if ($students && $students->num_rows > 0): ?>

                                <div class="table-responsive">

                                    <table class="table table-hover align-middle responsive-card-table">

                                        <thead>

                                            <tr>
                                                <th>#</th>
                                                <th>Student Name</th>
                                                <th>Email</th>
                                                <th>Status</th>
                                                <th>Scan Time</th>
                                            </tr>

                                        </thead>

                                        <tbody>

                                            <?php
                                            $number = 1;
                                            while ($student = $students->fetch_assoc()):
                                            ?>

                                                <tr>

                                                    <td><?php echo $number++; ?></td>

                                                    <td>
                                                        <strong>
                                                            <?php echo htmlspecialchars($student['fullname']); ?>
                                                        </strong>
                                                    </td>

                                                    <td>
                                                        <?php echo htmlspecialchars($student['email']); ?>
                                                    </td>

                                                    <td>
                                                        <span class="badge <?php echo attendanceStatusBadge($student['status']); ?>">
                                                            <?php echo htmlspecialchars(ucfirst($student['status'])); ?>
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <?php echo $student['scan_time'] ? htmlspecialchars($student['scan_time']) : '<span class="text-muted">Not scanned</span>'; ?>
                                                    </td>

                                                </tr>

                                            <?php endwhile; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php else: ?>

                                <div class="text-center py-5">

                                    <i class="bi bi-clipboard-x display-4 text-muted"></i>

                                    <h5 class="mt-3">No students registered</h5>

                                    <p class="text-muted mb-0">
                                        No students are currently registered in this course.
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body text-center py-5">

                            <i class="bi bi-exclamation-triangle display-4 text-warning"></i>

                            <h5 class="mt-3">Session Not Found</h5>

                            <p class="text-muted">
                                This attendance session does not belong to your account
                                or no longer exists.
                            </p>

                            <a
                                href="lecturer_attendance.php"
                                class="btn btn-success">

                                <i class="bi bi-arrow-left me-2"></i>
                                Back to Attendance

                            </a>

                        </div>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="card dashboard-card border-0 shadow-sm">

                    <div class="card-header bg-white">

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

                            <h5 class="mb-0">

                                <i class="bi bi-calendar-check-fill text-success me-2"></i>
                                My Attendance Sessions

                            </h5>

                            <a href="start_session.php" class="btn btn-success">

                                <i class="bi bi-qr-code me-2"></i>
                                Start Attendance

                            </a>

                        </div>

                    </div>

                    <div class="card-body">

                        <?php if ($total_sessions > 0): ?>

                            <div class="table-responsive">

                                <table class="table table-hover align-middle responsive-card-table">

                                    <thead>

                                        <tr>
                                            <th>Course</th>
                                            <th>Code</th>
                                            <th>Date</th>
                                            <th>Session</th>
                                            <th>Registered</th>
                                            <th>Present</th>
                                            <th>Absent</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($session_rows as $row): ?>

                                            <tr>

                                                <td>
                                                    <strong>
                                                        <?php echo htmlspecialchars($row['course_name']); ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo htmlspecialchars($row['course_code']); ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?php echo htmlspecialchars($row['session_date']); ?>
                                                </td>

                                                <td>
                                                    <?php echo htmlspecialchars($row['session_code'] ?? 'N/A'); ?>
                                                </td>

                                                <td>
                                                    <strong>
                                                        <?php echo (int) $row['students_registered']; ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <strong>
                                                        <?php echo (int) $row['students_present']; ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <strong class="text-danger">
                                                        <?php echo (int) $row['students_absent']; ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <span class="badge <?php echo attendanceStatusBadge($row['status']); ?>">
                                                        <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <a
                                                        href="lecturer_attendance.php?session_id=<?php echo (int) $row['session_id']; ?>"
                                                        class="btn btn-sm btn-primary">

                                                        <i class="bi bi-eye me-1"></i>
                                                        View Details

                                                    </a>
                                                    <?php if ($row['status'] === 'active'): ?>
                                                        <form method="post" action="end_session.php" class="d-inline" data-confirm="End this attendance session now?" data-confirm-title="End Attendance Session?" data-confirm-btn="End Session">
                                                            <input type="hidden" name="session_id" value="<?php echo (int) $row['session_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger mt-1">
                                                                <i class="bi bi-stop-circle me-1"></i>End
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="text-center py-5">

                                <i class="bi bi-calendar-x display-4 text-muted"></i>

                                <h5 class="mt-3">No Attendance Sessions Yet</h5>

                                <p class="text-muted">
                                    You have not created any attendance sessions yet.
                                </p>

                                <a href="start_session.php" class="btn btn-success">

                                    <i class="bi bi-qr-code me-2"></i>
                                    Start Attendance Session

                                </a>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>


            <div class="mt-4">

                <a href="lecturer_dashboard.php" class="btn btn-outline-light">

                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Dashboard

                </a>

            </div>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/script.js"></script>

</body>
</html>
