<?php
session_start();

include "db.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Access Denied - Administrators Only");
}

$attendanceResult = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total FROM attendance WHERE status = 'present'"
);
$totalAttendance = $attendanceResult
    ? (int) mysqli_fetch_assoc($attendanceResult)['total']
    : 0;

$attendanceByDay = array_fill(0, 5, 0);
$dailyAttendance = mysqli_query(
    $conn,
    "SELECT DAYOFWEEK(s.session_date) AS weekday, COUNT(a.id) AS total
     FROM attendance a
     INNER JOIN attendance_sessions s ON s.id = a.session_id
     WHERE a.status = 'present'
       AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
       AND s.session_date < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 5 DAY)
     GROUP BY DAYOFWEEK(s.session_date)"
);

if ($dailyAttendance) {
    while ($day = mysqli_fetch_assoc($dailyAttendance)) {
        $index = (int) $day['weekday'] - 2;
        if ($index >= 0 && $index < 5) {
            $attendanceByDay[$index] = (int) $day['total'];
        }
    }
}

include "includes/header.php";
include "includes/sidebar.php";
?>

<div class="main-content">

<?php include "includes/navbar.php"; ?>

<div class="container-fluid">

    <div class="row g-4">

        <!-- Total Students -->

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card border-0 shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-muted">
                                Students
                            </h6>

                            <h2 class="fw-bold">

                                <?php
                                $students = mysqli_query(
                                    $conn,
                                    "SELECT COUNT(*) AS total
                                     FROM users
                                     WHERE role='student'"
                                );

                                echo mysqli_fetch_assoc($students)['total'];
                                ?>

                            </h2>

                        </div>

                        <div class="icon-circle bg-success">

                            <i class="bi bi-people-fill"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Total Lecturers -->

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card border-0 shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-muted">
                                Lecturers
                            </h6>

                            <h2 class="fw-bold">

                                <?php
                                $lecturers = mysqli_query(
                                    $conn,
                                    "SELECT COUNT(*) AS total
                                     FROM users
                                     WHERE role='lecturer'"
                                );

                                echo mysqli_fetch_assoc($lecturers)['total'];
                                ?>

                            </h2>

                        </div>

                        <div class="icon-circle bg-primary">

                            <i class="bi bi-person-workspace"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Administrators -->

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card border-0 shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-muted">
                                Administrators
                            </h6>

                            <h2 class="fw-bold">

                                <?php
                                $admins = mysqli_query(
                                    $conn,
                                    "SELECT COUNT(*) AS total
                                     FROM users
                                     WHERE role='admin'"
                                );

                                echo mysqli_fetch_assoc($admins)['total'];
                                ?>

                            </h2>

                        </div>

                        <div class="icon-circle bg-warning">

                            <i class="bi bi-shield-lock-fill"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- Attendance -->

        <div class="col-lg-3 col-md-6">

            <div class="card dashboard-card border-0 shadow-sm">

                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <h6 class="text-muted">
                                Attendance
                            </h6>

                            <h2 class="fw-bold">
                                <?php echo $totalAttendance; ?>
                            </h2>

                        </div>

                        <div class="icon-circle bg-danger">

                            <i class="bi bi-calendar-check-fill"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>



<!-- Charts -->

<div class="row mt-4">

    <div class="col-lg-8">

        <div class="card dashboard-card shadow-sm border-0">

            <div class="card-header bg-white">

                <h5>Attendance Overview</h5>

            </div>

            <div class="card-body">

                <canvas id="attendanceChart"></canvas>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="card dashboard-card shadow-sm border-0">

            <div class="card-header bg-white">

                <h5>Quick Actions</h5>

            </div>

            <div class="card-body d-grid gap-3">

                <a href="register.php" class="btn btn-success">
                    Register User
                </a>

                <a href="admin_management.php?view=attendance" class="btn btn-primary">
                    Attendance
                </a>

                <a href="admin_management.php?view=reports" class="btn btn-warning">
                    Reports
                </a>

            </div>

        </div>

    </div>

</div>

<div class="card dashboard-card mt-4">

    <div class="card-header">

        Recent Users

    </div>

    <div class="card-body">

        <table class="table table-hover responsive-card-table">

            <thead>

                <tr>

                    <th>Name</th>

                    <th>Email</th>

                    <th>Role</th>

                </tr>

            </thead>

            <tbody>

            <?php

            $users = mysqli_query(

                $conn,

                "SELECT fullname,email,role
                 FROM users
                 ORDER BY id DESC
                 LIMIT 5"

            );

            while($row=mysqli_fetch_assoc($users)){

            ?>

            <tr>

                <td><?= $row['fullname']; ?></td>

                <td><?= $row['email']; ?></td>

                <td><?= ucfirst($row['role']); ?></td>

            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>


<script>

const ctx = document.getElementById('attendanceChart');

new Chart(ctx,{

    type:'bar',

    data:{

        labels:['Mon','Tue','Wed','Thu','Fri'],

        datasets:[{

            label:'Attendance',

            data:<?php echo json_encode($attendanceByDay); ?>,

            backgroundColor:'#0F6A4A'

        }]

    },

    options:{

        responsive:true,

        maintainAspectRatio:false

    }

});

</script>

<?php include "includes/footer.php"; ?>

