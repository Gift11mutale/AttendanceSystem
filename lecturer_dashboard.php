<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Lecturer Access Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {

    die("Access Denied");

}

include "db.php";

$lecturer_id = (int) $_SESSION['user_id'];
$dashboardStats = [
    'courses' => 0,
    'sessions' => 0,
    'students' => 0,
];

$statsStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT c.id) AS courses,
        COUNT(DISTINCT s.id) AS sessions,
        COUNT(DISTINCT e.student_id) AS students
    FROM courses c
    LEFT JOIN attendance_sessions s ON s.course_id = c.id
    LEFT JOIN enrollments e ON e.course_id = c.id
    WHERE c.lecturer_id = ?
");

if ($statsStmt) {
    $statsStmt->bind_param("i", $lecturer_id);
    $statsStmt->execute();
    $dashboardStats = $statsStmt->get_result()->fetch_assoc() ?: $dashboardStats;
    $statsStmt->close();
}

$attendanceByDay = array_fill(0, 5, 0);
$chartStmt = $conn->prepare("
    SELECT DAYOFWEEK(s.session_date) AS weekday, COUNT(a.id) AS total
    FROM attendance a
    INNER JOIN attendance_sessions s ON s.id = a.session_id
    INNER JOIN courses c ON c.id = s.course_id
    WHERE a.status = 'present'
      AND c.lecturer_id = ?
      AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
      AND s.session_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 5 DAY)
    GROUP BY DAYOFWEEK(s.session_date)
");

if ($chartStmt) {
    $chartStmt->bind_param("i", $lecturer_id);
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

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Lecturer Dashboard</title>


    <!-- Bootstrap CSS -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <!-- Dashboard CSS -->

    <link
    rel="stylesheet"
    href="assets/dashboard.css?v=3">

</head>


<body>


<!-- =====================================================
     MAIN WRAPPER
===================================================== -->

<div class="wrapper">


    <!-- =================================================
         SIDEBAR
    ================================================== -->

    <?php include "includes/sidebar.php"; ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================== -->

    <div class="main-content">


        <!-- =================================================
             NAVBAR
        ================================================== -->

        <?php include "includes/navbar.php"; ?>


        <!-- =================================================
             LECTURER DASHBOARD
        ================================================== -->

        <div class="container-fluid">


            <!-- Welcome Header -->

            <div class="dashboard-header mb-4">

                <h1>

                    Lecturer Dashboard

                </h1>


                <p>

                    Welcome back,

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $_SESSION['fullname'] ?? 'Lecturer'
                        );
                        ?>

                    </strong>

                </p>

            </div>


            <!-- =================================================
                 DASHBOARD CARDS
            ================================================== -->

            <div class="row g-4">


                <!-- MY COURSES -->

                <div class="col-lg-4 col-md-6">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">


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


                <!-- ATTENDANCE SESSIONS -->

                <div class="col-lg-4 col-md-6">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">


                                <div>

                                    <h6 class="text-muted">

                                        Attendance Sessions

                                    </h6>


                                    <h2 class="fw-bold">

                                        <?php echo (int) $dashboardStats['sessions']; ?>

                                    </h2>

                                </div>


                                <div class="icon-circle bg-primary">

                                    <i class="bi bi-calendar-check-fill"></i>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- STUDENTS -->

                <div class="col-lg-4 col-md-6">

                    <div class="card dashboard-card border-0 shadow-sm">

                        <div class="card-body">

                            <div
                                class="d-flex justify-content-between align-items-center">


                                <div>

                                    <h6 class="text-muted">

                                        Students

                                    </h6>


                                    <h2 class="fw-bold">

                                        <?php echo (int) $dashboardStats['students']; ?>

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


            <!-- =================================================
                 QUICK ACTIONS + ATTENDANCE
            ================================================== -->

            <div class="row g-4 mt-2">


                <!-- QUICK ACTIONS -->

                <div class="col-lg-4">

                    <div class="card dashboard-card shadow-sm border-0">


                        <div class="card-header bg-white">

                            <h5>

                                Quick Actions

                            </h5>

                        </div>


                        <div class="card-body d-grid gap-3">


                            <!-- CREATE COURSE -->

                            <a
                                href="create_course.php"
                                class="btn btn-success">

                                <i class="bi bi-journal-plus me-2"></i>

                                Create Course

                            </a>


                            <!-- VIEW COURSES -->

                            <a
                                href="view_courses.php"
                                class="btn btn-primary">

                                <i class="bi bi-book me-2"></i>

                                My Courses

                            </a>


                            <a
                                href="registered_students.php"
                                class="btn btn-outline-success">

                                <i class="bi bi-people me-2"></i>

                                Registered Students

                            </a>


                            <!-- START ATTENDANCE -->

                            <a
                                href="start_session.php"
                                class="btn btn-warning">

                                <i class="bi bi-qr-code me-2"></i>

                                Start Attendance

                            </a>


                            <!-- VIEW ATTENDANCE -->

<a
    href="lecturer_attendance.php"
    class="btn btn-info">

    <i class="bi bi-calendar-check me-2"></i>

    Attendance

</a>


                            <!-- ATTENDANCE PERCENTAGE -->

                            <a
                                href="attendance_percentage.php"
                                class="btn btn-secondary">

                                <i class="bi bi-bar-chart-fill me-2"></i>

                                Attendance Percentage

                            </a>


                        </div>

                    </div>

                </div>


                <!-- ATTENDANCE OVERVIEW -->

                <div class="col-lg-8">

                    <div class="card dashboard-card shadow-sm border-0">


                        <div class="card-header bg-white">

                            <h5>

                                Attendance Overview

                            </h5>

                        </div>


                        <div class="card-body">

                            <div
                                style="height: 300px;">

                                <canvas
                                    id="attendanceChart">
                                </canvas>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 LECTURER INFORMATION
            ================================================== -->

            <div class="card dashboard-card mt-4">


                <div class="card-header">

                    Lecturer Information

                </div>


                <div class="card-body">

                    <div class="row">


                        <!-- NAME -->

                        <div class="col-md-6">

                            <p>

                                <strong>

                                    Name:

                                </strong>


                                <?php

                                echo htmlspecialchars(
                                    $_SESSION['fullname'] ?? 'Lecturer'
                                );

                                ?>

                            </p>

                        </div>


                        <!-- ROLE -->

                        <div class="col-md-6">

                            <p>

                                <strong>

                                    Role:

                                </strong>


                                Lecturer

                            </p>

                        </div>

                    </div>

                </div>

            </div>


        </div>

    </div>

</div>


<!-- =====================================================
     BOOTSTRAP JAVASCRIPT
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


<!-- =====================================================
     CHART.JS
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js">
</script>


<script>

const chartElement =
    document.getElementById('attendanceChart');


if (chartElement) {

    new Chart(chartElement, {

        type: 'bar',

        data: {

            labels: [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday'
            ],

            datasets: [{

                label: 'Attendance',

                data: <?php echo json_encode($attendanceByDay); ?>

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true

                }

            }

        }

    });

}

</script>


</body>

</html>
