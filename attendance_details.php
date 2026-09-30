<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

/*
|--------------------------------------------------------------------------
| Student Access Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied - Students Only");
}

$student_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Get Course ID
|--------------------------------------------------------------------------
*/

$course_id = (int) ($_GET['course_id'] ?? 0);

if ($course_id <= 0) {
    die("Invalid course selected.");
}

/*
|--------------------------------------------------------------------------
| Verify Student Is Enrolled In Course
|--------------------------------------------------------------------------
*/

$enroll_stmt = $conn->prepare("
    SELECT
        e.enrolled_at,
        c.id,
        c.course_name,
        c.course_code
    FROM enrollments e
    INNER JOIN courses c
        ON e.course_id = c.id
    WHERE e.student_id = ?
      AND e.course_id = ?
    LIMIT 1
");

if (!$enroll_stmt) {
    die("Database error: " . $conn->error);
}

$enroll_stmt->bind_param("ii", $student_id, $course_id);
$enroll_stmt->execute();

$enroll_result = $enroll_stmt->get_result();
$course = $enroll_result->fetch_assoc();

$enroll_stmt->close();

if (!$course) {
    die("You are not enrolled in this course.");
}

$enrolled_at = $course['enrolled_at'];

/*
|--------------------------------------------------------------------------
| Get Attendance Details
|--------------------------------------------------------------------------
|
| Every session is shown.
| If the student has an attendance record, it is Present.
| If there is no attendance record, it is Absent.
|
*/

$attendance_rows = [];

$attendance_stmt = $conn->prepare("
    SELECT
        s.id AS session_id,
        s.session_code,
        s.session_date,
        s.created_at,
        s.expires_at,
        s.status AS session_status,
        a.status AS attendance_status
    FROM attendance_sessions s
    LEFT JOIN attendance a
        ON a.session_id = s.id
       AND a.student_id = ?
    WHERE s.course_id = ?
      AND s.session_date >= DATE(?)
      AND s.session_date <= CURDATE()
    ORDER BY s.session_date DESC, s.id DESC
");

if (!$attendance_stmt) {
    die("Database error: " . $conn->error);
}

$attendance_stmt->bind_param(
    "iis",
    $student_id,
    $course_id,
    $enrolled_at
);

$attendance_stmt->execute();

$attendance_result = $attendance_stmt->get_result();

while ($row = $attendance_result->fetch_assoc()) {
    $attendance_rows[] = $row;
}

$attendance_stmt->close();

/*
|--------------------------------------------------------------------------
| Calculate Summary
|--------------------------------------------------------------------------
*/

$total_sessions = count($attendance_rows);
$present_sessions = 0;
$absent_sessions = 0;

foreach ($attendance_rows as &$row) {
    $is_present = strtolower((string) $row['attendance_status']) === 'present';

    if ($is_present) {
        $present_sessions++;
        $row['display_status'] = 'Present';
        $row['badge_class'] = 'bg-success';
        $row['icon'] = 'bi-check-circle-fill';
    } else {
        $absent_sessions++;
        $row['display_status'] = 'Absent';
        $row['badge_class'] = 'bg-danger';
        $row['icon'] = 'bi-x-circle-fill';
    }
}

unset($row);

$percentage = $total_sessions > 0
    ? ($present_sessions / $total_sessions) * 100
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Attendance Details - <?php echo htmlspecialchars($course['course_name']); ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=3"
    >

    <style>
        .details-card {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .summary-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            height: 100%;
        }

        .summary-number {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .summary-label {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .attendance-table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            min-width: 760px;
        }

        .status-badge {
            min-width: 95px;
        }

        .session-code {
            font-weight: 600;
            font-family: monospace;
        }

        .empty-state {
            padding: 50px 20px;
            text-align: center;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .summary-box {
                padding: 14px;
            }

            .summary-number {
                font-size: 1.35rem;
            }
        }
    </style>

<link rel="stylesheet" href="assets/css/custom-popups.css">
<script src="assets/js/custom-popups.js" defer></script></head>

<body>

<div class="wrapper">

    <!-- SIDEBAR -->
    <?php include "includes/sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- NAVBAR -->
        <?php include "includes/navbar.php"; ?>

        <div class="container-fluid">

            <!-- PAGE HEADER -->
            <div class="dashboard-header mb-4">

                <h1 class="mb-1">
                    Attendance Details
                </h1>

                <p class="mb-0 text-muted">
                    <?php echo htmlspecialchars($course['course_name']); ?>
                    <span class="mx-1">•</span>
                    <?php echo htmlspecialchars($course['course_code']); ?>
                </p>

            </div>

            <!-- SUMMARY -->
            <div class="row g-3 mb-4">

                <div class="col-6 col-lg-3">
                    <div class="summary-box">
                        <div class="summary-number">
                            <?php echo $total_sessions; ?>
                        </div>
                        <div class="summary-label">
                            Total Sessions
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="summary-box">
                        <div class="summary-number text-success">
                            <?php echo $present_sessions; ?>
                        </div>
                        <div class="summary-label">
                            Present
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="summary-box">
                        <div class="summary-number text-danger">
                            <?php echo $absent_sessions; ?>
                        </div>
                        <div class="summary-label">
                            Absent
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="summary-box">
                        <div class="summary-number">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                        <div class="summary-label">
                            Attendance Rate
                        </div>
                    </div>
                </div>

            </div>

            <!-- ATTENDANCE TABLE -->
            <div class="card details-card shadow-sm">

                <div class="card-header bg-white py-3">

                    <div class="d-flex justify-content-between align-items-center gap-2">

                        <div>
                            <h5 class="mb-1">
                                <i class="bi bi-list-check me-2"></i>
                                Session-by-Session Attendance
                            </h5>

                            <small class="text-muted">
                                Your attendance for each class session
                            </small>
                        </div>

                        <a
                            href="view_attendance.php"
                            class="btn btn-outline-primary btn-sm"
                        >
                            <i class="bi bi-arrow-left me-1"></i>
                            Back
                        </a>

                    </div>

                </div>

                <div class="card-body">

                    <?php if (empty($attendance_rows)): ?>

                        <div class="empty-state">

                            <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>

                            <h5>
                                No Attendance Sessions Yet
                            </h5>

                            <p class="mb-0">
                                No attendance sessions have been recorded
                                for this course since you enrolled.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="attendance-table-wrapper">

                            <table class="table table-hover align-middle attendance-table responsive-card-table">

                                <thead>

                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Session Code</th>
                                        <th>Session Status</th>
                                        <th>Your Attendance</th>
                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($attendance_rows as $index => $row): ?>

                                        <tr>

                                            <td>
                                                <?php echo $index + 1; ?>
                                            </td>

                                            <td>
                                                <?php
                                                echo date(
                                                    'd M Y',
                                                    strtotime($row['session_date'])
                                                );
                                                ?>
                                                <small class="d-block text-muted">
                                                    <?php
                                                    echo !empty($row['created_at'])
                                                        ? date(
                                                            'h:i A',
                                                            strtotime($row['created_at'])
                                                        )
                                                        : 'Time unavailable';
                                                    ?>
                                                </small>
                                            </td>

                                            <td>
                                                <span class="session-code">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $row['session_code']
                                                    );
                                                    ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if ($row['session_status'] === 'active'): ?>

                                                    <span class="badge bg-success">
                                                        Active
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge bg-secondary">
                                                        <?php
                                                        echo htmlspecialchars(
                                                            ucfirst(
                                                                $row['session_status'] ?: 'Closed'
                                                            )
                                                        );
                                                        ?>
                                                    </span>

                                                <?php endif; ?>
                                            </td>

                                            <td>

                                                <span
                                                    class="badge <?php echo $row['badge_class']; ?> status-badge"
                                                >
                                                    <i
                                                        class="bi <?php echo $row['icon']; ?> me-1"
                                                    ></i>

                                                    <?php
                                                    echo $row['display_status'];
                                                    ?>
                                                </span>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- BOTTOM ACTION -->
            <div class="mt-4 mb-4">

                <a
                    href="view_attendance.php"
                    class="btn btn-primary"
                >
                    <i class="bi bi-calendar-check me-2"></i>
                    Back to My Attendance
                </a>

            </div>

        </div>

    </div>

</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>
