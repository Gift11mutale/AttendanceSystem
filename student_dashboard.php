<?php
session_start();

include "db.php";
include "includes/header.php";
include "includes/sidebar.php";

$student_id = (int) ($_SESSION['user_id'] ?? 0);
$dashboardStats = [
    'courses' => 0,
    'attendance' => 0,
    'sessions' => 0,
];

$statsStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT e.course_id) AS courses,
        COUNT(DISTINCT CASE WHEN a.status = 'present' THEN a.id END) AS attendance,
        COUNT(DISTINCT s.id) AS sessions
    FROM enrollments e
    LEFT JOIN attendance_sessions s ON s.course_id = e.course_id
    LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = e.student_id
    WHERE e.student_id = ?
");

if ($statsStmt) {
    $statsStmt->bind_param("i", $student_id);
    $statsStmt->execute();
    $dashboardStats = $statsStmt->get_result()->fetch_assoc() ?: $dashboardStats;
    $statsStmt->close();
}

$attendanceRate = $dashboardStats['sessions'] > 0
    ? round(((int) $dashboardStats['attendance'] / (int) $dashboardStats['sessions']) * 100)
    : 0;

$attendanceByDay = array_fill(0, 5, 0);
$chartStmt = $conn->prepare("
    SELECT DAYOFWEEK(s.session_date) AS weekday, COUNT(a.id) AS total
    FROM attendance a
    INNER JOIN attendance_sessions s ON s.id = a.session_id
    WHERE a.student_id = ?
      AND a.status = 'present'
      AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
      AND s.session_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 5 DAY)
    GROUP BY DAYOFWEEK(s.session_date)
");

if ($chartStmt) {
    $chartStmt->bind_param("i", $student_id);
    $chartStmt->execute();
    $chartResult = $chartStmt->get_result();
    while ($day = $chartResult->fetch_assoc()) {
        $index = (int) $day['weekday'] - 2;
        if ($index >= 0 && $index < 5) {
            $attendanceByDay[$index] = (int) $day['total'];
        }
    }
    $chartStmt->close();
}
?>

<div class="main-content">

    <?php include "includes/navbar.php"; ?>

    <div class="container-fluid">

        <!-- Welcome Header -->

        <div class="dashboard-header mb-4">

            <h1>
                Student Dashboard
            </h1>

            <p>
                Welcome back,
                <strong><?php echo $_SESSION['fullname']; ?></strong>
            </p>

        </div>


        <!-- Dashboard Cards -->

        <div class="row g-4">


            <!-- My Courses -->

            <div class="col-lg-4 col-md-6">

                <div class="card dashboard-card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <h6 class="text-muted">
                                    My Courses
                                </h6>

                                <h2 class="fw-bold">
                                    <?php echo (int) $dashboardStats['courses']; ?>
                                </h2>

                            </div>

                            <div class="icon-circle bg-success">

                                <i class="bi bi-book-fill"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Attendance -->

            <div class="col-lg-4 col-md-6">

                <div class="card dashboard-card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <h6 class="text-muted">
                                    Attendance
                                </h6>

                                <h2 class="fw-bold">
                                    <?php echo (int) $dashboardStats['attendance']; ?>
                                </h2>

                            </div>

                            <div class="icon-circle bg-primary">

                                <i class="bi bi-calendar-check-fill"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Attendance Rate -->

            <div class="col-lg-4 col-md-6">

                <div class="card dashboard-card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center">

                            <div>

                                <h6 class="text-muted">
                                    Attendance Rate
                                </h6>

                                <h2 class="fw-bold">
                                    <?php echo $attendanceRate; ?>%
                                </h2>

                            </div>

                            <div class="icon-circle bg-warning">

                                <i class="bi bi-graph-up-arrow"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Student Actions -->

        <div class="row g-4 mt-2">


            <!-- Attendance Overview -->

            <div class="col-lg-8">

                <div class="card dashboard-card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5>
                            Attendance Overview
                        </h5>

                    </div>

                    <div class="card-body">

                        <canvas id="attendanceChart"></canvas>

                    </div>

                </div>

            </div>


            <!-- Quick Actions -->

            <div class="col-lg-4">

                <div class="card dashboard-card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5>
                            Quick Actions
                        </h5>

                    </div>

                    <div class="card-body d-grid gap-3">


                        <a href="join_class.php"
                           class="btn btn-success">

                            <i class="bi bi-book"></i>
                            Join Class

                        </a>


                        <a href="scan_attendance.php"
                           class="btn btn-primary">

                            <i class="bi bi-qr-code-scan"></i>
                            Mark Attendance

                        </a>


                        <a href="view_attendance.php"
                           class="btn btn-warning">

                            <i class="bi bi-calendar-check"></i>
                            View Attendance

                        </a>


                        <a href="student_performance.php"
                           class="btn btn-secondary">

                            <i class="bi bi-bar-chart-fill"></i>
                            My Performance

                        </a>


                    </div>

                </div>

            </div>

        </div>


        <!-- Student Information -->

        <div class="card dashboard-card mt-4">

            <div class="card-header">

                Student Information

            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6">

                        <p>
                            <strong>Name:</strong>
                            <?php echo $_SESSION['fullname']; ?>
                        </p>

                    </div>

                    <div class="col-md-6">

                        <p>
                            <strong>Role:</strong>
                            Student
                        </p>

                    </div>

                </div>

            </div>

        </div>


    </div>
    <?php include "includes/footer.php"; ?>
</div>


<script>

const ctx = document.getElementById('attendanceChart');

new Chart(ctx, {

    type: 'bar',

    data: {

        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],

        datasets: [{

            label: 'Attendance',

            data: <?php echo json_encode($attendanceByDay); ?>,

            backgroundColor: '#0f7454'

        }]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false

    }

});

</script>


