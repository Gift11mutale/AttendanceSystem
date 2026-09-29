<?php

session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied - Students Only");
}

$student_id = (int) $_SESSION['user_id'];
$student_name = $_SESSION['fullname'] ?? 'Student';
$student_email = '';

$emailStmt = $conn->prepare("
    SELECT email, fullname
    FROM users
    WHERE id = ?
    LIMIT 1
");

$emailStmt->bind_param("i", $student_id);
$emailStmt->execute();
$emailRow = $emailStmt->get_result()->fetch_assoc();
$emailStmt->close();

if ($emailRow) {
    $student_email = $emailRow['email'] ?? '';
    if (!empty($emailRow['fullname'])) {
        $student_name = $emailRow['fullname'];
    }
}

$total_courses = 0;
$total_sessions = 0;
$total_present = 0;
$total_expected = 0;

$courseStmt = $conn->prepare("
    SELECT
        c.id,
        c.course_name,
        c.course_code,
        e.enrolled_at
    FROM enrollments e
    INNER JOIN courses c
        ON e.course_id = c.id
    WHERE e.student_id = ?
    ORDER BY c.course_name ASC
");

$courseStmt->bind_param("i", $student_id);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();

$courses = [];
$students = [];

while ($course = $courseResult->fetch_assoc()) {

    $course_id = (int) $course['id'];
    $enrolled_at = $course['enrolled_at'];

    $sessionStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM attendance_sessions
        WHERE course_id = ?
        AND session_date >= DATE(?)
        AND session_date <= CURDATE()
    ");

    $sessionStmt->bind_param("is", $course_id, $enrolled_at);
    $sessionStmt->execute();
    $sessionData = $sessionStmt->get_result()->fetch_assoc();
    $course_sessions = (int) ($sessionData['total'] ?? 0);
    $sessionStmt->close();

    $presentStmt = $conn->prepare("
        SELECT COUNT(DISTINCT a.session_id) AS total
        FROM attendance a
        INNER JOIN attendance_sessions s
            ON a.session_id = s.id
        WHERE a.student_id = ?
        AND s.course_id = ?
        AND a.status = 'present'
        AND s.session_date >= DATE(?)
        AND s.session_date <= CURDATE()
    ");

    $presentStmt->bind_param("iis", $student_id, $course_id, $enrolled_at);
    $presentStmt->execute();
    $presentData = $presentStmt->get_result()->fetch_assoc();
    $course_present = (int) ($presentData['total'] ?? 0);
    $presentStmt->close();

    $course_absent = max(0, $course_sessions - $course_present);

    if ($course_sessions > 0) {
        $percentage = ($course_present / $course_sessions) * 100;
    } else {
        $percentage = 0;
    }

    if ($percentage >= 75) {
        $performance = "Good";
        $performance_class = "success";
        $student_status = "Good Attendance";
        $student_status_class = "success";
        $recommendation =
            "Keep up the good work! Continue attending classes regularly.";
    } elseif ($percentage >= 50) {
        $performance = "Needs Improvement";
        $performance_class = "warning";
        $student_status = "Needs Improvement";
        $student_status_class = "warning";
        $recommendation =
            "Your attendance needs improvement. Attend classes more regularly and use the recommended learning resources.";
    } else {
        $performance = "Low";
        $performance_class = "danger";
        $student_status = "Low Attendance";
        $student_status_class = "danger";
        $recommendation =
            "Your attendance is very low. Improve your class attendance and use the recommended learning resources to catch up.";
    }

    $resources = [];

    $resourceStmt = $conn->prepare("
        SELECT title, description, url, resource_type
        FROM study_resources
        WHERE course_id = ?
        ORDER BY title ASC
    ");

    if ($resourceStmt) {
        $resourceStmt->bind_param("i", $course_id);
        $resourceStmt->execute();
        $resourceResult = $resourceStmt->get_result();

        while ($resource = $resourceResult->fetch_assoc()) {
            $resources[] = $resource;
        }

        $resourceStmt->close();
    }

    $courses[] = [
        'id' => $course_id,
        'name' => $course['course_name'],
        'code' => $course['course_code'],
        'sessions' => $course_sessions,
        'present' => $course_present,
        'absent' => $course_absent,
        'percentage' => $percentage,
        'performance' => $performance,
        'performance_class' => $performance_class
    ];

    $students[] = [
        'fullname' => $student_name,
        'email' => $student_email,
        'course_name' => $course['course_name'],
        'course_code' => $course['course_code'],
        'attended' => $course_present,
        'sessions' => $course_sessions,
        'percentage' => $percentage,
        'status' => $student_status,
        'status_class' => $student_status_class,
        'recommendation' => $recommendation,
        'resources' => $resources
    ];

    $total_courses++;
    $total_sessions += $course_sessions;
    $total_expected += $course_sessions;
    $total_present += $course_present;
}

$courseStmt->close();

$total_absent = max(0, $total_expected - $total_present);

if ($total_expected > 0) {
    $overall_percentage = ($total_present / $total_expected) * 100;
} else {
    $overall_percentage = 0;
}

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

include "includes/header.php";
include "includes/sidebar.php";
?>

<style>

    .stat-card {
        border: none;
        border-radius: 15px;
        background: white;
        height: 100%;
    }

    .stat-card h2 {
        font-weight: bold;
        margin-bottom: 0;
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

    .student-performance-table {
        font-size: 0.82rem;
    }

    .student-performance-table th,
    .student-performance-table td {
        padding: 0.65rem 0.75rem;
        vertical-align: middle;
    }

    .student-performance-table .progress {
        min-width: 110px;
    }

    .student-recommendations {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 12px;
        margin-top: 18px;
    }

    .student-recommendation-card {
        border: 1px solid #f0df9a;
        border-radius: 10px;
        background: #fffdf5;
        padding: 12px;
        font-size: 0.8rem;
    }

    .student-recommendation-card h6 {
        font-size: 0.85rem;
        margin-bottom: 6px;
    }

    .student-recommendation-card p {
        margin-bottom: 8px;
    }

    .resource-link {
        margin: 3px;
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

        .section-card .card-body {
            padding: 16px;
        }

        .chart-container {
            height: 240px;
        }

    }

</style>

<div class="main-content">

    <?php include "includes/navbar.php"; ?>

    <div class="container-fluid">

        <div class="page-title mb-4">

            <h2>

                <i class="bi bi-graph-up-arrow"></i>

                Individual Student Performance

            </h2>

            <p class="text-muted mb-0">

                Track your attendance, performance, and recommended
                learning resources for each enrolled course.

            </p>

        </div>


        <div class="row g-4 mb-4">

            <div class="col-lg-3 col-md-6">

                <div class="card stat-card shadow-sm">

                    <div class="card-body">

                        <div class="d-flex align-items-center">

                            <i
                                class="bi bi-book-fill text-primary"
                                style="font-size: 40px;"
                            ></i>

                            <div class="ms-3">

                                <h6 class="text-muted mb-1">
                                    Enrolled Courses
                                </h6>

                                <h2>
                                    <?php echo $total_courses; ?>
                                </h2>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-lg-3 col-md-6">

                <div class="card stat-card shadow-sm">

                    <div class="card-body">

                        <div class="d-flex align-items-center">

                            <i
                                class="bi bi-calendar-check-fill text-info"
                                style="font-size: 40px;"
                            ></i>

                            <div class="ms-3">

                                <h6 class="text-muted mb-1">
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


            <div class="col-lg-3 col-md-6">

                <div class="card stat-card shadow-sm">

                    <div class="card-body">

                        <div class="d-flex align-items-center">

                            <i
                                class="bi bi-check-circle-fill text-success"
                                style="font-size: 40px;"
                            ></i>

                            <div class="ms-3">

                                <h6 class="text-muted mb-1">
                                    Present
                                </h6>

                                <h2 class="text-success">
                                    <?php echo $total_present; ?>
                                </h2>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-lg-3 col-md-6">

                <div class="card stat-card shadow-sm">

                    <div class="card-body">

                        <div class="d-flex align-items-center">

                            <i
                                class="bi bi-percent text-warning"
                                style="font-size: 40px;"
                            ></i>

                            <div class="ms-3">

                                <h6 class="text-muted mb-1">
                                    Overall Attendance
                                </h6>

                                <h2>
                                    <?php echo number_format($overall_percentage, 1); ?>%
                                </h2>

                                <span class="badge bg-<?php echo $overall_class; ?>">
                                    <?php echo htmlspecialchars($overall_status); ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


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
                                <?php echo number_format($overall_percentage, 1); ?>%
                            </strong>

                        </h5>

                        <span class="badge bg-<?php echo $overall_class; ?> fs-6">
                            <?php echo htmlspecialchars($overall_status); ?>
                        </span>

                    </div>

                    <div class="col-md-6">

                        <div class="progress" style="height: 30px;">

                            <div
                                class="progress-bar bg-<?php echo $overall_class; ?>"
                                role="progressbar"
                                style="width: <?php echo min(100, $overall_percentage); ?>%;"
                            >
                                <?php echo number_format($overall_percentage, 1); ?>%
                            </div>

                        </div>

                        <div class="d-flex justify-content-between mt-2">

                            <small>
                                Present:
                                <strong><?php echo $total_present; ?></strong>
                            </small>

                            <small>
                                Absent:
                                <strong><?php echo $total_absent; ?></strong>
                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="card section-card">

            <div class="card-body">

                <h4>

                    <i class="bi bi-book-fill"></i>

                    Course Performance

                </h4>

                <hr>

                <?php if (count($courses) > 0): ?>

                    <div class="table-responsive mb-4">

                        <table class="responsive-card-table">

                            <thead>

                                <tr>
                                    <th>#</th>
                                    <th>Course</th>
                                    <th>Sessions</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Attendance %</th>
                                    <th>Performance</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($courses as $index => $course): ?>

                                <tr>

                                    <td><?php echo $index + 1; ?></td>

                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($course['name']); ?>
                                        </strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($course['code']); ?>
                                        </small>
                                    </td>

                                    <td><?php echo $course['sessions']; ?></td>

                                    <td>
                                        <strong class="text-success">
                                            <?php echo $course['present']; ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <strong class="text-danger">
                                            <?php echo $course['absent']; ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php echo number_format($course['percentage'], 1); ?>%
                                        </strong>
                                        <div class="progress mt-2" style="height: 8px;">
                                            <div
                                                class="progress-bar bg-<?php echo $course['performance_class']; ?>"
                                                style="width: <?php echo min(100, $course['percentage']); ?>%;"
                                            ></div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge bg-<?php echo $course['performance_class']; ?>">
                                            <?php echo htmlspecialchars($course['performance']); ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="chart-container">
                        <canvas id="courseChart"></canvas>
                    </div>

                <?php else: ?>

                    <div class="empty-message">

                        <i class="bi bi-book" style="font-size: 50px;"></i>

                        <h5 class="mt-3">
                            No Course Data Available
                        </h5>

                        <p>
                            Enroll in a course to generate your performance analytics.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <div class="card section-card">

            <div class="card-body">

                <h4>

                    <i class="bi bi-people-fill"></i>

                    Individual Student Performance

                </h4>

                <p class="text-muted">

                    Courses below 75% attendance receive learning
                    recommendations.

                </p>

                <hr>

                <?php if (count($students) > 0): ?>

                    <div class="table-responsive">

                        <table class="responsive-card-table student-performance-table">

                            <thead>

                                <tr>
                                    <th>Course</th>
                                    <th>Attended</th>
                                    <th>Total Sessions</th>
                                    <th>Attendance %</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($students as $student): ?>

                                <tr class="<?php
                                    if ($student['status_class'] === 'success') {
                                        echo 'student-good';
                                    } elseif ($student['status_class'] === 'warning') {
                                        echo 'student-warning';
                                    } else {
                                        echo 'student-danger';
                                    }
                                ?>">

                                    <td>
                                        <?php echo htmlspecialchars($student['course_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($student['course_code']); ?>
                                        </small>
                                    </td>

                                    <td>
                                        <strong><?php echo $student['attended']; ?></strong>
                                    </td>

                                    <td>
                                        <?php echo $student['sessions']; ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?php echo number_format($student['percentage'], 1); ?>%
                                        </strong>
                                        <div class="progress mt-2" style="height: 8px;">
                                            <div
                                                class="progress-bar bg-<?php echo $student['status_class']; ?>"
                                                style="width: <?php echo min(100, $student['percentage']); ?>%;"
                                            ></div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="badge bg-<?php echo $student['status_class']; ?>">
                                            <?php echo htmlspecialchars($student['status']); ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="student-recommendations">

                        <?php foreach ($students as $student): ?>

                            <article class="student-recommendation-card">

                                <h6>
                                    <i class="bi bi-lightbulb-fill text-warning"></i>
                                    <?php echo htmlspecialchars($student['course_name']); ?>
                                </h6>

                                <p><?php echo htmlspecialchars($student['recommendation']); ?></p>

                                <?php if ($student['percentage'] < 75 && !empty($student['resources'])): ?>

                                    <?php foreach ($student['resources'] as $resource): ?>

                                        <a
                                            href="<?php echo htmlspecialchars($resource['url']); ?>"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-primary resource-link"
                                        >
                                            <?php echo htmlspecialchars($resource['title']); ?>
                                        </a>

                                    <?php endforeach; ?>

                                <?php elseif ($student['percentage'] < 75): ?>

                                    <a href="https://www.w3schools.com/" target="_blank" class="btn btn-sm btn-outline-primary resource-link">W3Schools</a>
                                    <a href="https://www.coursera.org/" target="_blank" class="btn btn-sm btn-outline-primary resource-link">Coursera</a>
                                    <a href="https://www.khanacademy.org/" target="_blank" class="btn btn-sm btn-outline-primary resource-link">Khan Academy</a>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="empty-message">

                        <i class="bi bi-people" style="font-size: 50px;"></i>

                        <h5 class="mt-3">
                            No Performance Data Available
                        </h5>

                        <p>
                            Your individual performance will appear here after
                            you join a class.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </div>


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
                            <strong>🟢 Good Attendance</strong>
                            <br>
                            75% and above
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="alert alert-warning mb-0">
                            <strong>🟠 Needs Improvement</strong>
                            <br>
                            50% – 74%
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="alert alert-danger mb-0">
                            <strong>🔴 Low Attendance</strong>
                            <br>
                            Below 50%
                        </div>
                    </div>

                </div>

            </div>

        </div>


        <a href="student_dashboard.php" class="btn btn-secondary mb-4">
            <i class="bi bi-arrow-left"></i>
            Back to Student Dashboard
        </a>

    </div>

    <?php include "includes/footer.php"; ?>

</div>

<script>

const courseNames = <?php
echo json_encode(array_column($courses, 'code'));
?>;

const coursePercentages = <?php
echo json_encode(array_map(function ($course) {
    return round($course['percentage'], 1);
}, $courses));
?>;

const chartElement = document.getElementById("courseChart");

if (chartElement) {

    new Chart(chartElement, {
        type: "bar",
        data: {
            labels: courseNames,
            datasets: [{
                label: "Attendance Percentage",
                data: coursePercentages,
                backgroundColor: "#0f7454"
            }]
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
    });

}

</script>

</body>
</html>
