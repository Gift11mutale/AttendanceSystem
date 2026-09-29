<?php

session_start();
include "db.php";

/*
|--------------------------------------------------------------------------
| LECTURER ACCESS
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    die("Access Denied - Lecturers Only");
}

$lecturer_id = $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| OVERALL STATISTICS
|--------------------------------------------------------------------------
*/

$total_students = 0;
$total_sessions = 0;
$total_present = 0;
$total_expected = 0;


/*
|--------------------------------------------------------------------------
| GET LECTURER COURSES
|--------------------------------------------------------------------------
*/

$courseStmt = $conn->prepare("
    SELECT id, course_name, course_code
    FROM courses
    WHERE lecturer_id = ?
    ORDER BY course_name ASC
");

$courseStmt->bind_param("i", $lecturer_id);
$courseStmt->execute();

$courseResult = $courseStmt->get_result();

$courses = [];


/*
|--------------------------------------------------------------------------
| COURSE STATISTICS
|--------------------------------------------------------------------------
*/

while ($course = $courseResult->fetch_assoc()) {

    $course_id = $course['id'];

    /*
    |--------------------------------------------------------------------------
    | ENROLLED STUDENTS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM enrollments
        WHERE course_id = ?
    ");

    $stmt->bind_param("i", $course_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $enrolled_students = (int)$data['total'];

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE SESSIONS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM attendance_sessions
        WHERE course_id = ?
    ");

    $stmt->bind_param("i", $course_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $course_sessions = (int)$data['total'];

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | PRESENT ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM attendance a
        INNER JOIN attendance_sessions s
            ON a.session_id = s.id
        WHERE s.course_id = ?
        AND a.status = 'present'
    ");

    $stmt->bind_param("i", $course_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    $course_present = (int)$data['total'];

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | EXPECTED ATTENDANCE
    |--------------------------------------------------------------------------
    */

    $expected = $enrolled_students * $course_sessions;


    /*
    |--------------------------------------------------------------------------
    | ABSENT
    |--------------------------------------------------------------------------
    */

    $course_absent = max(
        0,
        $expected - $course_present
    );


    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE PERCENTAGE
    |--------------------------------------------------------------------------
    */

    if ($expected > 0) {

        $percentage =
            ($course_present / $expected) * 100;

    } else {

        $percentage = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | PERFORMANCE CATEGORY
    |--------------------------------------------------------------------------
    */

    if ($percentage >= 75) {

        $performance = "Good";
        $performance_class = "success";

    } elseif ($percentage >= 50) {

        $performance = "Needs Improvement";
        $performance_class = "warning";

    } else {

        $performance = "Low";
        $performance_class = "danger";
    }


    /*
    |--------------------------------------------------------------------------
    | STORE COURSE DATA
    |--------------------------------------------------------------------------
    */

    $courses[] = [

        'id' => $course_id,

        'name' => $course['course_name'],

        'code' => $course['course_code'],

        'students' => $enrolled_students,

        'sessions' => $course_sessions,

        'expected' => $expected,

        'present' => $course_present,

        'absent' => $course_absent,

        'percentage' => $percentage,

        'performance' => $performance,

        'performance_class' => $performance_class

    ];


    /*
    |--------------------------------------------------------------------------
    | OVERALL TOTALS
    |--------------------------------------------------------------------------
    */

    $total_students += $enrolled_students;

    $total_sessions += $course_sessions;

    $total_expected += $expected;

    $total_present += $course_present;
}

$courseStmt->close();


/*
|--------------------------------------------------------------------------
| OVERALL ATTENDANCE
|--------------------------------------------------------------------------
*/

$total_absent = max(
    0,
    $total_expected - $total_present
);


if ($total_expected > 0) {

    $overall_percentage =
        ($total_present / $total_expected) * 100;

} else {

    $overall_percentage = 0;
}


/*
|--------------------------------------------------------------------------
| OVERALL PERFORMANCE
|--------------------------------------------------------------------------
*/

if ($overall_percentage >= 75) {

    $overall_status = "Good Attendance";
    $overall_class = "success";

} elseif ($overall_percentage >= 50) {

    $overall_status = "Needs Improvement";
    $overall_class = "warning";

} else {

    $overall_status = "Low Attendance";
    $overall_class = "danger";
}


/*
|--------------------------------------------------------------------------
| INDIVIDUAL STUDENT PERFORMANCE
|--------------------------------------------------------------------------
|
| We start from enrollments so that students who have attended
| ZERO sessions are also displayed.
|
|--------------------------------------------------------------------------
*/

$studentStmt = $conn->prepare("

    SELECT

        u.id AS student_id,

        u.fullname,

        u.email,

        c.id AS course_id,

        c.course_name,

        c.course_code,

        (
            SELECT COUNT(*)
            FROM attendance_sessions s
            WHERE s.course_id = c.id
        ) AS total_sessions,

        (
            SELECT COUNT(*)
            FROM attendance a
            INNER JOIN attendance_sessions s2
                ON a.session_id = s2.id
            WHERE a.student_id = u.id
            AND s2.course_id = c.id
            AND a.status = 'present'
        ) AS attended_sessions

    FROM enrollments e

    INNER JOIN users u
        ON e.student_id = u.id

    INNER JOIN courses c
        ON e.course_id = c.id

    WHERE c.lecturer_id = ?

    ORDER BY c.course_name ASC, u.fullname ASC

");

$studentStmt->bind_param("i", $lecturer_id);
$studentStmt->execute();

$studentResult = $studentStmt->get_result();

$students = [];


/*
|--------------------------------------------------------------------------
| PROCESS EACH STUDENT
|--------------------------------------------------------------------------
*/

while ($row = $studentResult->fetch_assoc()) {

    $attended = (int)$row['attended_sessions'];

    $sessions = (int)$row['total_sessions'];


    /*
    |--------------------------------------------------------------------------
    | STUDENT ATTENDANCE PERCENTAGE
    |--------------------------------------------------------------------------
    */

    if ($sessions > 0) {

        $student_percentage =
            ($attended / $sessions) * 100;

    } else {

        $student_percentage = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | STUDENT PERFORMANCE
    |--------------------------------------------------------------------------
    */

    if ($student_percentage >= 75) {

        $student_status = "Good Attendance";

        $student_status_class = "success";

        $recommendation =
            "Keep up the good work! Continue attending classes regularly.";

    } elseif ($student_percentage >= 50) {

        $student_status = "Needs Improvement";

        $student_status_class = "warning";

        $recommendation =
            "Your attendance needs improvement. Attend classes more regularly and use the recommended learning resources.";

    } else {

        $student_status = "Low Attendance";

        $student_status_class = "danger";

        $recommendation =
            "Your attendance is very low. Improve your class attendance and use the recommended learning resources to catch up.";
    }


    /*
    |--------------------------------------------------------------------------
    | STORE STUDENT DATA
    |--------------------------------------------------------------------------
    */

    $students[] = [

        'student_id' => $row['student_id'],

        'fullname' => $row['fullname'],

        'email' => $row['email'],

        'course_name' => $row['course_name'],

        'course_code' => $row['course_code'],

        'attended' => $attended,

        'sessions' => $sessions,

        'percentage' => $student_percentage,

        'status' => $student_status,

        'status_class' => $student_status_class,

        'recommendation' => $recommendation

    ];
}

$studentStmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Attendance Analytics</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- Chart.js -->

    <script
        src="https://cdn.jsdelivr.net/npm/chart.js"
    ></script>


    <style>

        body {
            background: #f5f7fa;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        .dashboard {
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h2 {
            font-weight: bold;
        }

        .stat-card {
            border: none;
            border-radius: 15px;
            background: white;
            padding: 10px;
            height: 100%;
        }

        .stat-card h2 {
            font-weight: bold;
        }

        .analytics-card {
            border: none;
            border-radius: 15px;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            border: none;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .section-card .card-body {
            padding: 25px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f1f3f5;
            padding: 12px;
            text-align: left;
            white-space: nowrap;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #eeeeee;
            vertical-align: middle;
        }

        table tr:hover {
            background: #fafafa;
        }

        .student-good {
            background: #f0fff4;
        }

        .student-warning {
            background: #fffaf0;
        }

        .student-danger {
            background: #fff5f5;
        }

        .recommendation-box {
            background: #fff8e1;
            border-radius: 10px;
            padding: 12px;
            margin-top: 8px;
            min-width: 0;
        }

        .resource-link {
            margin: 3px;
        }

        .progress {
            min-width: 100px;
        }

        .chart-container {
            position: relative;
            height: 350px;
        }

        .empty-message {
            text-align: center;
            padding: 50px;
            color: #777;
        }

        @media (max-width: 768px) {

            .dashboard {
                width: 100%;
                margin: 16px auto;
                padding: 0 12px;
            }

            .section-card .card-body {
                padding: 16px;
            }

            .chart-container {
                height: 240px;
            }

            .page-title h2 {
                font-size: 1.4rem;
            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- ========================================================= -->
    <!-- PAGE HEADER -->
    <!-- ========================================================= -->

    <div class="page-title">

        <h2>

            <i class="bi bi-bar-chart-fill"></i>

            Attendance Analytics

        </h2>

        <p class="text-muted">

            Monitor student attendance and performance.

        </p>

    </div>


    <!-- ========================================================= -->
    <!-- SUMMARY CARDS -->
    <!-- ========================================================= -->

    <div class="row g-4 mb-4">


        <!-- STUDENTS -->

        <div class="col-lg-3 col-md-6">

            <div class="card stat-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <i
                            class="bi bi-people-fill text-primary"
                            style="font-size: 40px;"
                        ></i>

                        <div class="ms-3">

                            <h6 class="text-muted">
                                Enrolled Students
                            </h6>

                            <h2>
                                <?php echo $total_students; ?>
                            </h2>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SESSIONS -->

        <div class="col-lg-3 col-md-6">

            <div class="card stat-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <i
                            class="bi bi-calendar-check-fill text-info"
                            style="font-size: 40px;"
                        ></i>

                        <div class="ms-3">

                            <h6 class="text-muted">
                                Sessions
                            </h6>

                            <h2>
                                <?php echo $total_sessions; ?>
                            </h2>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- PRESENT -->

        <div class="col-lg-3 col-md-6">

            <div class="card stat-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <i
                            class="bi bi-check-circle-fill text-success"
                            style="font-size: 40px;"
                        ></i>

                        <div class="ms-3">

                            <h6 class="text-muted">
                                Present
                            </h6>

                            <h2 class="text-success">

                                <?php
                                echo $total_present;
                                ?>

                            </h2>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- OVERALL -->

        <div class="col-lg-3 col-md-6">

            <div class="card stat-card shadow-sm">

                <div class="card-body">

                    <div class="d-flex align-items-center">

                        <i
                            class="bi bi-percent text-warning"
                            style="font-size: 40px;"
                        ></i>

                        <div class="ms-3">

                            <h6 class="text-muted">
                                Overall Attendance
                            </h6>

                            <h2>

                                <?php
                                echo number_format(
                                    $overall_percentage,
                                    1
                                );
                                ?>%

                            </h2>

                            <span
                                class="badge bg-<?php echo $overall_class; ?>"
                            >

                                <?php
                                echo $overall_status;
                                ?>

                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>


    </div>


    <!-- ========================================================= -->
    <!-- OVERALL PERFORMANCE -->
    <!-- ========================================================= -->

    <div class="card section-card">

        <div class="card-body">

            <h4>

                <i class="bi bi-speedometer2"></i>

                Overall Attendance Performance

            </h4>

            <hr>


            <div class="row align-items-center">


                <div class="col-md-6">

                    <h5>

                        Attendance Rate:

                        <strong>

                            <?php
                            echo number_format(
                                $overall_percentage,
                                1
                            );
                            ?>%

                        </strong>

                    </h5>


                    <span
                        class="badge bg-<?php echo $overall_class; ?> fs-6"
                    >

                        <?php
                        echo $overall_status;
                        ?>

                    </span>

                </div>


                <div class="col-md-6">


                    <div
                        class="progress"
                        style="height: 30px;"
                    >

                        <div
                            class="progress-bar bg-<?php echo $overall_class; ?>"
                            role="progressbar"
                            style="width: <?php echo min(100, $overall_percentage); ?>%;"
                        >

                            <?php
                            echo number_format(
                                $overall_percentage,
                                1
                            );
                            ?>%

                        </div>

                    </div>


                    <div
                        class="d-flex justify-content-between mt-2"
                    >

                        <small>

                            Present:

                            <strong>
                                <?php echo $total_present; ?>
                            </strong>

                        </small>


                        <small>

                            Absent:

                            <strong>
                                <?php echo $total_absent; ?>
                            </strong>

                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================================= -->
    <!-- COURSE PERFORMANCE -->
    <!-- ========================================================= -->

    <div class="card section-card">

        <div class="card-body">

            <h4>

                <i class="bi bi-book-fill"></i>

                Course Performance

            </h4>

            <hr>


            <?php if (count($courses) > 0): ?>


                <div class="course-performance-grid">

                    <?php foreach ($courses as $course): ?>

                        <div class="course-stat-card">

                            <div class="course-card-header">

                                <span class="course-code-badge">
                                    <?php echo htmlspecialchars($course['code']); ?>
                                </span>

                                <span class="badge bg-<?php echo $course['performance_class']; ?>">
                                    <?php echo $course['performance']; ?>
                                </span>

                            </div>

                            <h5 class="course-card-title">
                                <?php echo htmlspecialchars($course['name']); ?>
                            </h5>

                            <div class="course-metrics">

                                <div class="course-metric">

                                    <span>Attended</span>

                                    <strong class="text-success">
                                        <?php echo $course['present']; ?>
                                    </strong>

                                </div>


                                <div class="course-metric">

                                    <span>Total Sessions</span>

                                    <strong>
                                        <?php echo $course['sessions']; ?>
                                    </strong>

                                </div>


                                <div class="course-metric wider">

                                    <span>Attendance</span>

                                    <strong>
                                        <?php echo number_format($course['percentage'], 1); ?>%
                                    </strong>

                                </div>


                                <div class="course-metric wider">

                                    <span>Status</span>

                                    <span class="badge bg-<?php echo $course['performance_class']; ?>">
                                        <?php echo $course['performance']; ?>
                                    </span>

                                </div>

                            </div>

                            <div class="progress mt-3" style="height: 8px;">

                                <div
                                    class="progress-bar bg-<?php echo $course['performance_class']; ?>"
                                    style="width: <?php echo min(100, $course['percentage']); ?>%;"
                                ></div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>


            <?php else: ?>


                <div class="empty-message">

                    <i
                        class="bi bi-bar-chart"
                        style="font-size: 50px;"
                    ></i>

                    <h5 class="mt-3">
                        No Course Data Available
                    </h5>

                    <p>
                        Create a course and start attendance sessions
                        to generate analytics.
                    </p>

                </div>


            <?php endif; ?>


        </div>

    </div>


    <!-- ========================================================= -->
    <!-- CHART -->
    <!-- ========================================================= -->

    <div class="card section-card">

        <div class="card-body">

            <h4>

                <i class="bi bi-bar-chart-line-fill"></i>

                Attendance by Course

            </h4>

            <hr>


            <?php if (count($courses) > 0): ?>


                <div class="chart-container">

                    <canvas id="courseChart"></canvas>

                </div>


            <?php else: ?>


                <div class="empty-message">

                    No chart data available.

                </div>


            <?php endif; ?>


        </div>

    </div>


    <!-- ========================================================= -->
    <!-- INDIVIDUAL STUDENT PERFORMANCE -->
    <!-- ========================================================= -->

    <div class="card section-card">

        <div class="card-body">

            <h4>

                <i class="bi bi-people-fill"></i>

                Individual Student Performance

            </h4>

            <p class="text-muted">

                Students below 75% attendance receive learning
                recommendations.

            </p>

            <hr>


            <?php if (count($students) > 0): ?>


                <div class="table-responsive">

                    <table class="responsive-card-table">

                        <thead>

                            <tr>

                                <th>Student</th>

                                <th>Course</th>

                                <th>Attended</th>

                                <th>Total Sessions</th>

                                <th>Attendance %</th>

                                <th>Status</th>

                                <th>Recommendation</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($students as $student): ?>


                            <tr class="<?php

                                if (
                                    $student['status_class']
                                    === 'success'
                                ) {

                                    echo 'student-good';

                                } elseif (
                                    $student['status_class']
                                    === 'warning'
                                ) {

                                    echo 'student-warning';

                                } else {

                                    echo 'student-danger';
                                }

                            ?>">


                                <!-- STUDENT -->

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $student['fullname']
                                        );
                                        ?>

                                    </strong>

                                    <br>

                                    <small class="text-muted">

                                        <?php
                                        echo htmlspecialchars(
                                            $student['email']
                                        );
                                        ?>

                                    </small>

                                </td>


                                <!-- COURSE -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student['course_name']
                                    );
                                    ?>

                                    <br>

                                    <small class="text-muted">

                                        <?php
                                        echo htmlspecialchars(
                                            $student['course_code']
                                        );
                                        ?>

                                    </small>

                                </td>


                                <!-- ATTENDED -->

                                <td>

                                    <strong>

                                        <?php
                                        echo $student['attended'];
                                        ?>

                                    </strong>

                                </td>


                                <!-- TOTAL SESSIONS -->

                                <td>

                                    <?php
                                    echo $student['sessions'];
                                    ?>

                                </td>


                                <!-- PERCENTAGE -->

                                <td>

                                    <strong>

                                        <?php
                                        echo number_format(
                                            $student['percentage'],
                                            1
                                        );
                                        ?>%

                                    </strong>


                                    <div
                                        class="progress mt-2"
                                        style="height: 8px;"
                                    >

                                        <div
                                            class="progress-bar bg-<?php echo $student['status_class']; ?>"
                                            style="width: <?php echo min(100, $student['percentage']); ?>%;"
                                        ></div>

                                    </div>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="badge bg-<?php echo $student['status_class']; ?>"
                                    >

                                        <?php
                                        echo $student['status'];
                                        ?>

                                    </span>

                                </td>


                                <!-- RECOMMENDATION -->

                                <td>

                                    <div>

                                        <?php
                                        echo htmlspecialchars(
                                            $student['recommendation']
                                        );
                                        ?>

                                    </div>


                                    <?php if (
                                        $student['percentage'] < 75
                                    ): ?>


                                        <div
                                            class="recommendation-box"
                                        >

                                            <strong>

                                                <i
                                                    class="bi bi-lightbulb-fill"
                                                ></i>

                                                Recommended Learning
                                                Resources

                                            </strong>


                                            <br><br>


                                            <a
                                                href="https://www.w3schools.com/"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-primary resource-link"
                                            >

                                                W3Schools

                                            </a>


                                            <a
                                                href="https://www.coursera.org/"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-primary resource-link"
                                            >

                                                Coursera

                                            </a>


                                            <a
                                                href="https://www.khanacademy.org/"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-primary resource-link"
                                            >

                                                Khan Academy

                                            </a>


                                            <a
                                                href="https://www.udemy.com/"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-primary resource-link"
                                            >

                                                Udemy

                                            </a>

                                        </div>


                                    <?php else: ?>


                                        <div class="mt-2 text-success">

                                            <strong>

                                                <i
                                                    class="bi bi-emoji-smile-fill"
                                                ></i>

                                                Keep up the good work!

                                            </strong>

                                        </div>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty-message">

                    <i
                        class="bi bi-people"
                        style="font-size: 50px;"
                    ></i>

                    <h5 class="mt-3">
                        No Student Data Available
                    </h5>

                    <p>
                        Students will appear here after they enroll
                        in your courses.
                    </p>

                </div>


            <?php endif; ?>


        </div>

    </div>


    <!-- ========================================================= -->
    <!-- PERFORMANCE GUIDE -->
    <!-- ========================================================= -->

    <div class="card section-card">

        <div class="card-body">

            <h4>

                <i class="bi bi-lightbulb-fill"></i>

                Performance Guide

            </h4>

            <hr>


            <div class="row g-3">


                <div class="col-md-4">

                    <div class="alert alert-success mb-0">

                        <strong>

                            🟢 Good Attendance

                        </strong>

                        <br>

                        75% and above

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="alert alert-warning mb-0">

                        <strong>

                            🟠 Needs Improvement

                        </strong>

                        <br>

                        50% – 74%

                    </div>

                </div>


                <div class="col-md-4">

                    <div class="alert alert-danger mb-0">

                        <strong>

                            🔴 Low Attendance

                        </strong>

                        <br>

                        Below 50%

                    </div>

                </div>


            </div>

        </div>

    </div>


    <!-- ========================================================= -->
    <!-- BACK TO DASHBOARD -->
    <!-- ========================================================= -->

    <a
        href="lecturer_dashboard.php"
        class="btn btn-secondary mb-5"
    >

        <i class="bi bi-arrow-left"></i>

        Back to Lecturer Dashboard

    </a>


</div>


<!-- ========================================================= -->
<!-- CHART JAVASCRIPT -->
<!-- ========================================================= -->

<script>

const courseNames = <?php

echo json_encode(
    array_column(
        $courses,
        'code'
    )
);

?>;


const coursePercentages = <?php

echo json_encode(
    array_map(
        function ($course) {

            return round(
                $course['percentage'],
                1
            );

        },
        $courses
    )
);

?>;


const chartElement =
    document.getElementById("courseChart");


if (chartElement) {

    new Chart(
        chartElement,
        {

            type: "bar",

            data: {

                labels: courseNames,

                datasets: [

                    {

                        label: "Attendance Percentage",

                        data: coursePercentages

                    }

                ]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                scales: {

                    y: {

                        beginAtZero: true,

                        max: 100,

                        title: {

                            display: true,

                            text: "Attendance %"

                        }

                    },

                    x: {

                        title: {

                            display: true,

                            text: "Course"

                        }

                    }

                },

                plugins: {

                    legend: {

                        display: true

                    }

                }

            }

        }
    );

}

</script>


</body>

</html>