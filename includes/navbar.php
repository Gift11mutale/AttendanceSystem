<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fullname = $_SESSION['fullname'] ?? 'User';

?>


<!-- TOP NAVBAR -->

<nav class="navbar bg-white shadow-sm rounded-3 mb-4">

    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between">


        <!-- LEFT -->

        <div class="d-flex align-items-center gap-2 navbar-title">

            <button
                type="button"
                class="btn btn-light sidebar-toggle"
                id="sidebarToggle"
                aria-label="Open menu">

                <i class="bi bi-list fs-4"></i>

            </button>

            <span class="navbar-brand-label">Smart Attendance</span>

        </div>


        <!-- RIGHT -->

        <div class="d-flex flex-nowrap align-items-center navbar-actions">


            <!-- SEARCH -->

            <div class="me-3 navbar-search">

                <input
                    type="search"
                    class="form-control"
                    placeholder="Search...">

            </div>


            <!-- NOTIFICATION -->

            <a
                href="notifications.php"
                class="btn btn-light position-relative me-3 navbar-icon-button d-inline-flex align-items-center justify-content-center"
                aria-label="Notifications">

                <i class="bi bi-bell-fill" aria-hidden="true"></i>

            </a>


            <!-- USER -->

            <div class="dropdown">

                <button
                    type="button"
                    class="btn btn-light dropdown-toggle navbar-profile-button d-inline-flex align-items-center"
                    data-bs-toggle="dropdown">

                    <i class="bi bi-person-circle me-2"></i>

                    <span class="navbar-user-name">
                        <?php echo htmlspecialchars($fullname); ?>
                    </span>

                </button>


                <ul class="dropdown-menu dropdown-menu-end">

                    <li>

                        <a
                            class="dropdown-item"
                            href="#">

                            <i class="bi bi-person me-2"></i>
                            Profile

                        </a>

                    </li>


                    <li>

                        <a
                            class="dropdown-item"
                            href="#">

                            <i class="bi bi-gear me-2"></i>
                            Settings

                        </a>

                    </li>


                    <li>

                        <hr class="dropdown-divider">

                    </li>


                    <li>

                        <a
                            class="dropdown-item text-danger"
                            href="logout.php">

                            <i class="bi bi-box-arrow-right me-2"></i>
                            Logout

                        </a>

                    </li>

                </ul>

            </div>

        </div>

    </div>

</nav>

<script>
(function () {
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var openBtn = document.getElementById('sidebarToggle');
    var closeBtn = document.getElementById('sidebarClose');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('show');
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
        document.body.classList.remove('sidebar-open');
    }

    if (openBtn) openBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 992) closeSidebar();
    });
})();
</script>
