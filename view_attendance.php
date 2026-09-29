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
    die("Access Denied");
}

$student_id = (int) $_SESSION['user_id'];

$student_name = $_SESSION['fullname'] ?? 'Student';


/*
|--------------------------------------------------------------------------
| Attendance Recommendation
|--------------------------------------------------------------------------
*/

function getAttendanceRecommendation($percentage, $course_name)
{
    if ($percentage >= 90) {

        return [
            'status' => 'Excellent Attendance',
            'badge_class' => 'bg-success',
            'icon' => 'bi-check-circle-fill',
            'recommendation' =>
                "Excellent attendance! Your attendance in {$course_name} is " .
                number_format($percentage, 1) .
                "%. Keep maintaining your consistent attendance."
        ];

    } elseif ($percentage >= 75) {

        return [
            'status' => 'Good Attendance',
            'badge_class' => 'bg-warning text-dark',
            'icon' => 'bi-hand-thumbs-up-fill',
            'recommendation' =>
                "Your attendance in {$course_name} is " .
                number_format($percentage, 1) .
                "%. Your attendance is good, but try to attend every upcoming session consistently."
        ];

    } elseif ($percentage >= 60) {

        return [
            'status' => 'Needs Improvement',
            'badge_class' => 'bg-warning text-dark',
            'icon' => 'bi-exclamation-triangle-fill',
            'recommendation' =>
                "Your attendance in {$course_name} is " .
                number_format($percentage, 1) .
                "%. Your attendance needs improvement. Try to avoid missing upcoming sessions and use the study resources below to strengthen your understanding."
        ];

    } else {

        return [
            'status' => 'Low Attendance',
            'badge_class' => 'bg-danger',
            'icon' => 'bi-x-circle-fill',
            'recommendation' =>
                "Your attendance in {$course_name} is " .
                number_format($percentage, 1) .
                "%. Your attendance is low. Make attending this course a priority and use the study resources below to catch up on missed topics."
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Get Student's Enrolled Courses
|--------------------------------------------------------------------------
*/

$courses = [];

$sql = "
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
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    $stmt->bind_param("i", $student_id);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Overall Attendance
|--------------------------------------------------------------------------
*/

$overall_total = 0;
$overall_present = 0;


/*
|--------------------------------------------------------------------------
| Calculate Attendance For Each Course
|--------------------------------------------------------------------------
*/

foreach ($courses as &$course) {

    $course_id = (int) $course['id'];
    $enrolled_at = $course['enrolled_at'];


    /*
    |--------------------------------------------------------------------------
    | Total Sessions
    |--------------------------------------------------------------------------
    |
    | Only count sessions:
    | - belonging to this course
    | - occurring on or after student's enrollment date
    | - that have already happened
    |
    */

    $session_sql = "
        SELECT COUNT(*) AS total_sessions
        FROM attendance_sessions
        WHERE course_id = ?
        AND session_date >= DATE(?)
        AND session_date <= CURDATE()
    ";

    $session_stmt = $conn->prepare($session_sql);

    $total_sessions = 0;

    if ($session_stmt) {

        $session_stmt->bind_param(
            "is",
            $course_id,
            $enrolled_at
        );

        $session_stmt->execute();

        $session_result = $session_stmt->get_result();

        $session_data = $session_result->fetch_assoc();

        $total_sessions = (int) (
            $session_data['total_sessions'] ?? 0
        );

        $session_stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Present Sessions
    |--------------------------------------------------------------------------
    */

    $present_sql = "
        SELECT COUNT(DISTINCT a.session_id) AS present_sessions
        FROM attendance a
        INNER JOIN attendance_sessions s
            ON a.session_id = s.id
        WHERE a.student_id = ?
        AND s.course_id = ?
        AND a.status = 'present'
        AND s.session_date >= DATE(?)
        AND s.session_date <= CURDATE()
    ";

    $present_stmt = $conn->prepare($present_sql);

    $present_sessions = 0;

    if ($present_stmt) {

        $present_stmt->bind_param(
            "iis",
            $student_id,
            $course_id,
            $enrolled_at
        );

        $present_stmt->execute();

        $present_result = $present_stmt->get_result();

        $present_data = $present_result->fetch_assoc();

        $present_sessions = (int) (
            $present_data['present_sessions'] ?? 0
        );

        $present_stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Absent Sessions
    |--------------------------------------------------------------------------
    */

    $absent_sessions = max(
        0,
        $total_sessions - $present_sessions
    );


    /*
    |--------------------------------------------------------------------------
    | Attendance Percentage
    |--------------------------------------------------------------------------
    */

    if ($total_sessions > 0) {

        $percentage =
            ($present_sessions / $total_sessions) * 100;

    } else {

        $percentage = 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Recommendation
    |--------------------------------------------------------------------------
    */

    $recommendation = getAttendanceRecommendation(
        $percentage,
        $course['course_name']
    );


    /*
    |--------------------------------------------------------------------------
    | Study Resources
    |--------------------------------------------------------------------------
    |
    | Automatically uses the current course_id.
    |
    */

    $resources = [];

    $resource_sql = "
        SELECT
            id,
            title,
            description,
            url,
            resource_type
        FROM study_resources
        WHERE course_id = ?
        ORDER BY title ASC
    ";

    $resource_stmt = $conn->prepare($resource_sql);

    if ($resource_stmt) {

        $resource_stmt->bind_param(
            "i",
            $course_id
        );

        $resource_stmt->execute();

        $resource_result = $resource_stmt->get_result();

        while ($resource = $resource_result->fetch_assoc()) {

            $resources[] = $resource;
        }

        $resource_stmt->close();
    }


    /*
    |--------------------------------------------------------------------------
    | Store Course Calculations
    |--------------------------------------------------------------------------
    */

    $course['total_sessions'] = $total_sessions;
    $course['present_sessions'] = $present_sessions;
    $course['absent_sessions'] = $absent_sessions;
    $course['percentage'] = $percentage;
    $course['recommendation'] = $recommendation;
    $course['resources'] = $resources;


    /*
    |--------------------------------------------------------------------------
    | Overall Totals
    |--------------------------------------------------------------------------
    */

    $overall_total += $total_sessions;
    $overall_present += $present_sessions;
}

unset($course);


/*
|--------------------------------------------------------------------------
| Overall Percentage
|--------------------------------------------------------------------------
*/

if ($overall_total > 0) {

    $overall_percentage =
        ($overall_present / $overall_total) * 100;

} else {

    $overall_percentage = 0;
}

$overall_absent =
    max(0, $overall_total - $overall_present);

?>

<!DOCTYPE html>

<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>My Attendance</title>


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


    <style>

        .attendance-page-header {
            margin-bottom: 25px;
        }


        .overall-card {
            border: 0;
            border-radius: 15px;
            overflow: hidden;
        }


        .course-card {
            border: 0;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.2s ease,
                        box-shadow 0.2s ease;
        }


        .course-card:hover {
            transform: translateY(-3px);

            box-shadow:
                0 10px 25px rgba(0,0,0,0.08)
                !important;
        }


        .course-header {
            background: #ffffff;
            border-bottom: 1px solid #eeeeee;
            padding: 20px;
        }


        .course-code {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }


        .attendance-stat {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            height: 100%;
        }


        .attendance-stat .number {
            font-size: 1.5rem;
            font-weight: 700;
        }


        .attendance-stat .label {
            font-size: 0.8rem;
            color: #6c757d;
        }


        .percentage-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            height: 100%;
        }


        .percentage-number {
            font-size: 1.5rem;
            font-weight: 700;
        }


        .recommendation-box {
            border-radius: 12px;
            padding: 20px;
            background: #f8f9fa;
        }


        .recommendation-title {
            font-weight: 700;
            margin-bottom: 10px;
        }


        .recommendation-text {
            margin-bottom: 0;
            line-height: 1.6;
        }


        .resources-area {
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid #dee2e6;
        }


        .resources-heading {
            font-weight: 700;
            margin-bottom: 6px;
        }


        .resources-intro {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 15px;
        }


        .resource-item {
            background: #ffffff;
            border: 1px solid #eeeeee;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }


        .resource-item:last-child {
            margin-bottom: 0;
        }


        .resource-title {
            font-weight: 600;
            margin-bottom: 5px;
        }


        .resource-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 8px;
        }


        .resource-type {
            font-size: 0.72rem;
        }


        .empty-resources {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0;
        }


        .course-section-title {
            margin-bottom: 4px;
        }


        .course-section-description {
            color: #6c757d;
            margin-bottom: 20px;
        }

    </style>

</head>


<body>


<div class="wrapper">


    <!-- SIDEBAR -->

    <?php include "includes/sidebar.php"; ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">


        <!-- NAVBAR -->

        <?php include "includes/navbar.php"; ?>


        <div class="container-fluid">


            <!-- =====================================================
                 PAGE HEADER
            ====================================================== -->

            <div class="dashboard-header attendance-page-header">

                <h1>
                    My Attendance
                </h1>

                <p>
                    Welcome back,
                    <strong>
                        <?php
                        echo htmlspecialchars($student_name);
                        ?>
                    </strong>
                </p>

            </div>


            <!-- =====================================================
                 OVERALL ATTENDANCE
            ====================================================== -->

            <div class="card overall-card shadow-sm mb-4">

                <div class="card-body p-4">

                    <div class="mb-4">

                        <h5 class="mb-1">

                            <i class="bi bi-bar-chart-fill me-2"></i>

                            Overall Attendance

                        </h5>

                        <p class="text-muted mb-0">

                            Your attendance across all enrolled courses.

                        </p>

                    </div>


                    <div class="row g-3">


                        <!-- TOTAL -->

                        <div class="col-6 col-md-3">

                            <div class="attendance-stat">

                                <div class="number">

                                    <?php
                                    echo $overall_total;
                                    ?>

                                </div>

                                <div class="label">

                                    Total Sessions

                                </div>

                            </div>

                        </div>


                        <!-- PRESENT -->

                        <div class="col-6 col-md-3">

                            <div class="attendance-stat">

                                <div class="number text-success">

                                    <?php
                                    echo $overall_present;
                                    ?>

                                </div>

                                <div class="label">

                                    Present

                                </div>

                            </div>

                        </div>


                        <!-- ABSENT -->

                        <div class="col-6 col-md-3">

                            <div class="attendance-stat">

                                <div class="number text-danger">

                                    <?php
                                    echo $overall_absent;
                                    ?>

                                </div>

                                <div class="label">

                                    Absent

                                </div>

                            </div>

                        </div>


                        <!-- PERCENTAGE -->

                        <div class="col-6 col-md-3">

                            <div class="percentage-box">

                                <div class="percentage-number">

                                    <?php
                                    echo number_format(
                                        $overall_percentage,
                                        1
                                    );
                                    ?>%

                                </div>

                                <div class="label text-muted">

                                    Overall Attendance

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 COURSE ATTENDANCE
            ====================================================== -->

            <div class="mb-4">

                <h4 class="course-section-title">

                    Course Attendance

                </h4>

                <p class="course-section-description">

                    View your attendance, percentage, recommendations
                    and course-specific study resources.

                </p>

            </div>


            <?php if (empty($courses)): ?>

                <div class="alert alert-info">

                    <i class="bi bi-info-circle me-2"></i>

                    You are not currently enrolled in any courses.

                </div>

            <?php endif; ?>


            <div class="row g-4">


                <?php foreach ($courses as $course): ?>


                    <!-- =================================================
                         COURSE CARD
                    ================================================== -->

                    <div class="col-12">

                        <div class="card course-card shadow-sm">


                            <!-- COURSE HEADER -->

                            <div class="course-header">

                                <div class="d-flex
                                            justify-content-between
                                            align-items-start
                                            gap-3">

                                    <div>

                                        <h4 class="mb-1">

                                            <?php
                                            echo htmlspecialchars(
                                                $course['course_name']
                                            );
                                            ?>

                                        </h4>

                                        <div class="course-code">

                                            <?php
                                            echo htmlspecialchars(
                                                $course['course_code']
                                            );
                                            ?>

                                        </div>

                                    </div>


                                    <span
                                        class="badge
                                        <?php
                                        echo $course['recommendation']
                                            ['badge_class'];
                                        ?>">

                                        <i
                                            class="bi
                                            <?php
                                            echo $course['recommendation']
                                                ['icon'];
                                            ?>
                                            me-1">
                                        </i>

                                        <?php
                                        echo htmlspecialchars(
                                            $course['recommendation']
                                                ['status']
                                        );
                                        ?>

                                    </span>

                                </div>

                            </div>


                            <!-- COURSE BODY -->

                            <div class="card-body p-4">


                                <!-- =================================================
                                     ATTENDANCE STATISTICS
                                ================================================== -->

                                <div class="row g-3 mb-4">


                                    <!-- TOTAL -->

                                    <div class="col-6 col-md-3">

                                        <div class="attendance-stat">

                                            <div class="number">

                                                <?php
                                                echo $course[
                                                    'total_sessions'
                                                ];
                                                ?>

                                            </div>

                                            <div class="label">

                                                Total Sessions

                                            </div>

                                        </div>

                                    </div>


                                    <!-- PRESENT -->

                                    <div class="col-6 col-md-3">

                                        <div class="attendance-stat">

                                            <div class="number text-success">

                                                <?php
                                                echo $course[
                                                    'present_sessions'
                                                ];
                                                ?>

                                            </div>

                                            <div class="label">

                                                Present

                                            </div>

                                        </div>

                                    </div>


                                    <!-- ABSENT -->

                                    <div class="col-6 col-md-3">

                                        <div class="attendance-stat">

                                            <div class="number text-danger">

                                                <?php
                                                echo $course[
                                                    'absent_sessions'
                                                ];
                                                ?>

                                            </div>

                                            <div class="label">

                                                Absent

                                            </div>

                                        </div>

                                    </div>


                                    <!-- PERCENTAGE -->

                                    <div class="col-6 col-md-3">

                                        <div class="percentage-box">

                                            <div
                                                class="percentage-number">

                                                <?php
                                                echo number_format(
                                                    $course[
                                                        'percentage'
                                                    ],
                                                    1
                                                );
                                                ?>%

                                            </div>

                                            <div class="label text-muted">

                                                Attendance Rate

                                            </div>

                                        </div>

                                    </div>

                                </div>


                                <!-- =================================================
                                     PROGRESS BAR
                                ================================================== -->

                                <div class="mb-4">

                                    <div
                                        class="d-flex
                                               justify-content-between
                                               mb-2">

                                        <small class="fw-semibold">

                                            Attendance Progress

                                        </small>

                                        <small>

                                            <?php
                                            echo number_format(
                                                $course[
                                                    'percentage'
                                                ],
                                                1
                                            );
                                            ?>%

                                        </small>

                                    </div>


                                    <div
                                        class="progress"
                                        style="height: 10px;">

                                        <div
                                            class="progress-bar"
                                            role="progressbar"
                                            style="width: <?= min(100, max(0, $course['percentage'])) ?>%;"
                                            aria-valuenow="<?= $course['percentage'] ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100">

                                        </div>

                                    </div>

                                </div>


                                <!-- =================================================
                                     RECOMMENDATION + RESOURCES
                                ================================================== -->

                                <div class="recommendation-box">


                                    <!-- RECOMMENDATION -->

                                    <div class="recommendation-title">

                                        <i
                                            class="bi
                                                   bi-lightbulb-fill
                                                   me-2">
                                        </i>

                                        Recommendation

                                    </div>


                                    <p class="recommendation-text">

                                        <?php
                                        echo htmlspecialchars(
                                            $course[
                                                'recommendation'
                                            ]['recommendation']
                                        );
                                        ?>

                                    </p>


                                    <!-- =================================================
                                         STUDY RESOURCES INSIDE RECOMMENDATION
                                    ================================================== -->

                                    <div class="resources-area">


                                        <div class="resources-heading">

                                            <i
                                                class="bi
                                                       bi-book-half
                                                       me-2">
                                            </i>

                                            Study Resources

                                        </div>


                                        <p class="resources-intro">

                                            Recommended learning resources
                                            to help you improve your
                                            understanding of

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $course[
                                                        'course_name'
                                                    ]
                                                );
                                                ?>
                                            </strong>.

                                        </p>


                                        <?php if (empty(
                                            $course['resources']
                                        )): ?>


                                            <p class="empty-resources">

                                                <i
                                                    class="bi
                                                           bi-info-circle
                                                           me-2">
                                                </i>

                                                No study resources have
                                                been added for this course
                                                yet.

                                            </p>


                                        <?php else: ?>


                                            <?php foreach (
                                                $course['resources']
                                                as $resource
                                            ): ?>


                                                <div
                                                    class="resource-item">

                                                    <div
                                                        class="row
                                                               align-items-center
                                                               g-3">


                                                        <!-- RESOURCE INFORMATION -->

                                                        <div
                                                            class="col-md-9">


                                                            <div
                                                                class="resource-title">

                                                                <i
                                                                    class="bi
                                                                           bi-link-45deg
                                                                           me-2">
                                                                </i>

                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $resource[
                                                                        'title'
                                                                    ]
                                                                );
                                                                ?>

                                                            </div>


                                                            <?php if (
                                                                !empty(
                                                                    $resource[
                                                                        'description'
                                                                    ]
                                                                )
                                                            ): ?>

                                                                <div
                                                                    class="resource-description">

                                                                    <?php
                                                                    echo htmlspecialchars(
                                                                        $resource[
                                                                            'description'
                                                                        ]
                                                                    );
                                                                    ?>

                                                                </div>

                                                            <?php endif; ?>


                                                            <span
                                                                class="badge
                                                                       bg-secondary
                                                                       resource-type">

                                                                <?php
                                                                echo htmlspecialchars(
                                                                    $resource[
                                                                        'resource_type'
                                                                    ]
                                                                );
                                                                ?>

                                                            </span>

                                                        </div>


                                                        <!-- OPEN RESOURCE -->

                                                        <div
                                                            class="col-md-3
                                                                   text-md-end">

                                                            <a
                                                                href="<?php
                                                                echo htmlspecialchars(
                                                                    $resource[
                                                                        'url'
                                                                    ]
                                                                );
                                                                ?>"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="btn
                                                                       btn-sm
                                                                       btn-primary">

                                                                <i
                                                                    class="bi
                                                                           bi-box-arrow-up-right
                                                                           me-1">
                                                                </i>

                                                                Open Resource

                                                            </a>

                                                        </div>


                                                    </div>

                                                </div>


                                            <?php endforeach; ?>


                                        <?php endif; ?>

                                    </div>

                                </div>


                                <!-- =================================================
                                     ATTENDANCE DETAILS
                                ================================================== -->

                                <div class="mt-4">

                                    <a
                                        href="attendance_details.php?course_id=<?php
                                            echo $course['id'];
                                        ?>"
                                        class="btn btn-outline-primary">

                                        <i
                                            class="bi
                                                   bi-list-check
                                                   me-2">
                                        </i>

                                        View Attendance Details

                                    </a>

                                </div>


                            </div>

                        </div>

                    </div>


                <?php endforeach; ?>


            </div>


        </div>

    </div>

</div>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>