<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    die("Access Denied - Lecturers Only");
}

$lecturer_id = (int) $_SESSION['user_id'];
$selected_course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

$courses = [];

$courseStmt = $conn->prepare("
    SELECT id, course_name, course_code
    FROM courses
    WHERE lecturer_id = ?
    ORDER BY course_name ASC
");

$courseStmt->bind_param("i", $lecturer_id);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();

while ($course = $courseResult->fetch_assoc()) {
    $courses[] = $course;
}

$courseStmt->close();

$selected_course = null;
$students = [];

if ($selected_course_id > 0) {

    foreach ($courses as $course) {
        if ((int) $course['id'] === $selected_course_id) {
            $selected_course = $course;
            break;
        }
    }

    if ($selected_course) {

        $studentStmt = $conn->prepare("
            SELECT
                u.id AS student_id,
                u.fullname,
                u.email,
                e.enrolled_at
            FROM enrollments e
            INNER JOIN users u
                ON e.student_id = u.id
            WHERE e.course_id = ?
            ORDER BY u.fullname ASC
        ");

        $studentStmt->bind_param("i", $selected_course_id);
        $studentStmt->execute();
        $studentResult = $studentStmt->get_result();

        while ($row = $studentResult->fetch_assoc()) {
            $students[] = $row;
        }

        $studentStmt->close();
    }
}

$total_registered = count($students);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registered Students | Smart Attendance System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=3">

</head>

<body>

<div class="wrapper">

    <?php include "includes/sidebar.php"; ?>

    <div class="main-content">

        <?php include "includes/navbar.php"; ?>

        <div class="container-fluid">

            <div class="dashboard-header mb-4">

                <h1>Registered Students</h1>

                <p>
                    View all students registered in a selected course.
                </p>

            </div>


            <div class="card dashboard-card border-0 shadow-sm mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">

                        <i class="bi bi-funnel-fill text-success me-2"></i>
                        Select Course

                    </h5>

                </div>

                <div class="card-body">

                    <?php if (count($courses) > 0): ?>

                        <form method="GET" action="registered_students.php" class="row g-3 align-items-end">

                            <div class="col-md-8">

                                <label for="course_id" class="form-label">
                                    Course
                                </label>

                                <select
                                    name="course_id"
                                    id="course_id"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        -- Select a course --
                                    </option>

                                    <?php foreach ($courses as $course): ?>

                                        <option
                                            value="<?php echo (int) $course['id']; ?>"
                                            <?php echo $selected_course_id === (int) $course['id'] ? 'selected' : ''; ?>>

                                            <?php
                                            echo htmlspecialchars(
                                                $course['course_name'] . ' (' . $course['course_code'] . ')'
                                            );
                                            ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <div class="col-md-4">

                                <button type="submit" class="btn btn-success w-100">

                                    <i class="bi bi-people-fill me-2"></i>
                                    View Students

                                </button>

                            </div>

                        </form>

                    <?php else: ?>

                        <div class="text-center py-4">

                            <i class="bi bi-journal-x display-4 text-muted"></i>

                            <h5 class="mt-3">No Courses Found</h5>

                            <p class="text-muted">
                                Create a course first to view registered students.
                            </p>

                            <a href="create_course.php" class="btn btn-success">

                                <i class="bi bi-plus-circle me-2"></i>
                                Create Course

                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <?php if ($selected_course_id > 0 && !$selected_course): ?>

                <div class="alert alert-warning">

                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    The selected course was not found or does not belong to your account.

                </div>

            <?php elseif ($selected_course): ?>

                <div class="row g-4 mb-4">

                    <div class="col-lg-4 col-md-6">

                        <div class="card dashboard-card border-0 shadow-sm">

                            <div class="card-body">

                                <div class="d-flex justify-content-between align-items-center">

                                    <div>
                                        <h6 class="text-muted">Registered Students</h6>
                                        <h2 class="fw-bold mb-0">
                                            <?php echo $total_registered; ?>
                                        </h2>
                                    </div>

                                    <div class="icon-circle bg-success">
                                        <i class="bi bi-people-fill"></i>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="col-lg-8 col-md-6">

                        <div class="card dashboard-card border-0 shadow-sm">

                            <div class="card-body">

                                <h6 class="text-muted">Selected Course</h6>

                                <h4 class="fw-bold mb-1">
                                    <?php echo htmlspecialchars($selected_course['course_name']); ?>
                                </h4>

                                <span class="badge bg-success">
                                    <?php echo htmlspecialchars($selected_course['course_code']); ?>
                                </span>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="card dashboard-card border-0 shadow-sm">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-people-fill text-success me-2"></i>
                            Students Registered in
                            <?php echo htmlspecialchars($selected_course['course_name']); ?>

                        </h5>

                    </div>

                    <div class="card-body">

                        <?php if ($total_registered > 0): ?>

                            <div class="table-responsive">

                                <table class="table table-hover align-middle responsive-card-table">

                                    <thead>

                                        <tr>
                                            <th>#</th>
                                            <th>Student Name</th>
                                            <th>Email</th>
                                            <th>Enrolled On</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($students as $index => $student): ?>

                                            <tr>

                                                <td><?php echo $index + 1; ?></td>

                                                <td>
                                                    <strong>
                                                        <?php echo htmlspecialchars($student['fullname']); ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <?php echo htmlspecialchars($student['email']); ?>
                                                </td>

                                                <td>
                                                    <?php
                                                    echo !empty($student['enrolled_at'])
                                                        ? htmlspecialchars(date('d M Y', strtotime($student['enrolled_at'])))
                                                        : 'N/A';
                                                    ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="text-center py-5">

                                <i class="bi bi-person-x display-4 text-muted"></i>

                                <h5 class="mt-3">No Registered Students</h5>

                                <p class="text-muted mb-0">
                                    No students have joined this course yet.
                                </p>

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            <?php elseif (count($courses) > 0): ?>

                <div class="card dashboard-card border-0 shadow-sm">

                    <div class="card-body text-center py-5">

                        <i class="bi bi-people display-4 text-muted"></i>

                        <h5 class="mt-3">Select a Course</h5>

                        <p class="text-muted mb-0">
                            Choose a course above to view the students registered for that class.
                        </p>

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
