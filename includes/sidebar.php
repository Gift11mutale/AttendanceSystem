<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';

?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar">

    <!-- SIDEBAR HEADER -->
    <div class="sidebar-header text-center">

        <button
            type="button"
            class="sidebar-close"
            id="sidebarClose"
            aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>

        <img
            src="assets/images/kmu logo.png"
            alt="KMU Logo"
            class="sidebar-logo">

        <h5 class="mt-2 mb-0">
            Smart Attendance
        </h5>

        <small>
            Learning Insights System
        </small>

    </div>


    <hr>


    <!-- NAVIGATION -->
    <ul class="nav flex-column">


        <!-- ========================= -->
        <!-- ADMIN -->
        <!-- ========================= -->

        <?php if ($role === 'admin'): ?>

            <li class="nav-item">

                <a href="admin_dashboard.php"
                   class="nav-link">

                    <i class="bi bi-speedometer2"></i>
                    Dashboard

                </a>

            </li>


            <li class="nav-item">

                <a href="admin_management.php?view=students"
                   class="nav-link">

                    <i class="bi bi-people-fill"></i>
                    Students

                </a>

            </li>


            <li class="nav-item">

                <a href="admin_management.php?view=lecturers"
                   class="nav-link">

                    <i class="bi bi-person-workspace"></i>
                    Lecturers

                </a>

            </li>


            <li class="nav-item">

                <a href="admin_management.php?view=attendance"
                   class="nav-link">

                    <i class="bi bi-calendar-check-fill"></i>
                    Attendance

                </a>

            </li>


            <li class="nav-item">

                <a href="admin_management.php?view=reports"
                   class="nav-link">

                    <i class="bi bi-bar-chart-fill"></i>
                    Reports

                </a>

            </li>

            <li class="nav-item">

                <a href="passing_predictions.php"
                   class="nav-link">

                    <i class="bi bi-graph-up-arrow"></i>
                    Passing Predictions

                </a>

            </li>


            <li class="nav-item">

                <a href="admin_management.php?view=courses"
                   class="nav-link">

                    <i class="bi bi-journal-bookmark-fill"></i>
                    Courses

                </a>

            </li>


        <!-- ========================= -->
        <!-- LECTURER -->
        <!-- ========================= -->

        <?php elseif ($role === 'lecturer'): ?>

            <li class="nav-item">

                <a href="lecturer_dashboard.php"
                   class="nav-link">

                    <i class="bi bi-speedometer2"></i>
                    Dashboard

                </a>

            </li>


            <li class="nav-item">

                <a href="create_course.php"
                   class="nav-link">

                    <i class="bi bi-journal-plus"></i>
                    Create Course

                </a>

            </li>


            <li class="nav-item">

                <a href="view_courses.php"
                   class="nav-link">

                    <i class="bi bi-book-fill"></i>
                    My Courses

                </a>

            </li>


            <li class="nav-item">

                <a href="registered_students.php"
                   class="nav-link">

                    <i class="bi bi-people-fill"></i>
                    Registered Students

                </a>

            </li>


            <li class="nav-item">

                <a href="start_session.php"
                   class="nav-link">

                    <i class="bi bi-qr-code"></i>
                    Start Attendance

                </a>

            </li>


            <li class="nav-item">

                <a href="lecturer_attendance.php"
                   class="nav-link">

                    <i class="bi bi-calendar-check-fill"></i>
                    Attendance

                </a>

            </li>

            <li class="nav-item">

                <a href="passing_predictions.php"
                   class="nav-link">

                    <i class="bi bi-graph-up-arrow"></i>
                    Passing Predictions

                </a>

            </li>


        <!-- ========================= -->
        <!-- STUDENT -->
        <!-- ========================= -->

        <?php elseif ($role === 'student'): ?>

            <li class="nav-item">

                <a href="student_dashboard.php"
                   class="nav-link">

                    <i class="bi bi-speedometer2"></i>
                    Dashboard

                </a>

            </li>


            <li class="nav-item">

                <a href="join_class.php"
                   class="nav-link">

                    <i class="bi bi-book-fill"></i>
                    Join Class

                </a>

            </li>


            <li class="nav-item">

                <a href="scan_attendance.php"
                   class="nav-link">

                    <i class="bi bi-qr-code-scan"></i>
                    Mark Attendance

                </a>

            </li>


            <li class="nav-item">

                <a href="view_attendance.php"
                   class="nav-link">

                    <i class="bi bi-calendar-check-fill"></i>
                    My Attendance

                </a>

            </li>


            <li class="nav-item">

                <a href="student_performance.php"
                   class="nav-link">

                    <i class="bi bi-graph-up-arrow"></i>
                    My Performance

                </a>

            </li>

            <li class="nav-item">

                <a href="passing_predictions.php"
                   class="nav-link">

                    <i class="bi bi-graph-up-arrow"></i>
                    Passing Predictions

                </a>

            </li>

        <?php endif; ?>

        <li class="nav-item mt-3">

            <a href="notifications.php"
               class="nav-link">

                <i class="bi bi-bell-fill"></i>
                Notifications

            </a>

        </li>


        <!-- LOGOUT -->

        <li class="nav-item mt-3">

            <a href="logout.php"
               class="nav-link text-danger">

                <i class="bi bi-box-arrow-right"></i>
                Logout

            </a>

        </li>

    </ul>

</div>

<script>
(function () {
    var currentPath = window.location.pathname;
    var currentSearch = window.location.search;

    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        var linkUrl = new URL(link.href, window.location.href);
        var isCurrentPage = linkUrl.pathname === currentPath
            && linkUrl.search === currentSearch;

        link.classList.toggle('active', isCurrentPage);
    });
})();
</script>
