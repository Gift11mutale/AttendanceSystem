<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";


/*
|--------------------------------------------------------------------------
| Lecturer Access Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    die("Access Denied");
}


$lecturer_id = $_SESSION['user_id'];


/*
|--------------------------------------------------------------------------
| Get Lecturer Courses
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT * FROM courses WHERE lecturer_id = ? ORDER BY course_name ASC"
);

$stmt->bind_param("i", $lecturer_id);

$stmt->execute();

$result = $stmt->get_result();

?>

<!DOCTYPE html>

<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Courses</title>


    <!-- Bootstrap CSS -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <!-- KMU Dashboard CSS -->

    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=3">


<link rel="stylesheet" href="assets/css/custom-popups.css">
<script src="assets/js/custom-popups.js" defer></script></head>


<body>


<div class="wrapper">


    <!-- ==========================================
         SIDEBAR
    =========================================== -->

    <?php include "includes/sidebar.php"; ?>


    <!-- ==========================================
         MAIN CONTENT
    =========================================== -->

    <div class="main-content">


        <!-- ==========================================
             NAVBAR
        =========================================== -->

        <?php include "includes/navbar.php"; ?>


        <!-- ==========================================
             PAGE CONTENT
        =========================================== -->

        <div class="container-fluid">


            <!-- PAGE HEADER -->

            <div class="dashboard-header mb-4">

                <h1>

                    My Courses

                </h1>


                <p>

                    View and manage the courses you have created.

                </p>

            </div>


            <!-- ==========================================
                 COURSE CARD
            =========================================== -->

            <div class="card dashboard-card border-0 shadow-sm">


                <!-- CARD HEADER -->

                <div class="card-header bg-white">


                    <div class="d-flex justify-content-between align-items-center">


                        <h5 class="mb-0">

                            <i class="bi bi-book-fill text-success me-2"></i>

                            My Courses

                        </h5>


                        <a
                            href="create_course.php"
                            class="btn btn-success">

                            <i class="bi bi-plus-circle me-2"></i>

                            Create Course

                        </a>


                    </div>


                </div>


                <!-- CARD BODY -->

                <div class="card-body">


                    <?php if ($result->num_rows > 0): ?>


                        <div class="table-responsive">


                            <table class="table table-hover align-middle responsive-card-table">


                                <thead>


                                    <tr>

                                        <th>
                                            #
                                        </th>

                                        <th>
                                            Course Name
                                        </th>

                                        <th>
                                            Course Code
                                        </th>

                                        <th>
                                            Actions
                                        </th>

                                    </tr>


                                </thead>


                                <tbody>


                                <?php

                                $number = 1;

                                while ($row = $result->fetch_assoc()):

                                ?>


                                    <tr>


                                        <!-- NUMBER -->

                                        <td>

                                            <?php echo $number++; ?>

                                        </td>


                                        <!-- COURSE NAME -->

                                        <td>

                                            <strong>

                                                <?php
                                                echo htmlspecialchars(
                                                    $row['course_name']
                                                );
                                                ?>

                                            </strong>

                                        </td>


                                        <!-- COURSE CODE -->

                                        <td>

                                            <span class="badge bg-success">

                                                <?php
                                                echo htmlspecialchars(
                                                    $row['course_code']
                                                );
                                                ?>

                                            </span>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td>


                                            <a
                                                href="start_session.php"
                                                class="btn btn-sm btn-warning">

                                                <i class="bi bi-qr-code me-1"></i>

                                                Start Attendance

                                            </a>


                                            <a
                                                href="lecturer_attendance.php?course_id=<?php echo (int) $row['id']; ?>"
                                                class="btn btn-sm btn-primary">

                                                <i class="bi bi-calendar-check me-1"></i>

                                                Attendance

                                            </a>


                                        </td>


                                    </tr>


                                <?php endwhile; ?>


                                </tbody>


                            </table>


                        </div>


                    <?php else: ?>


                        <!-- NO COURSES -->

                        <div class="text-center py-5">


                            <i
                                class="bi bi-journal-x display-4 text-muted">
                            </i>


                            <h5 class="mt-3">

                                No Courses Found

                            </h5>


                            <p class="text-muted">

                                You have not created any courses yet.

                            </p>


                            <a
                                href="create_course.php"
                                class="btn btn-success">

                                <i class="bi bi-plus-circle me-2"></i>

                                Create Your First Course

                            </a>


                        </div>


                    <?php endif; ?>


                </div>

            </div>


            <!-- BACK TO DASHBOARD -->

            <div class="mt-4">


                <a
                    href="lecturer_dashboard.php"
                    class="btn btn-outline-light">

                    <i class="bi bi-arrow-left me-2"></i>

                    Back to Dashboard

                </a>


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


<?php

$stmt->close();

?>
