<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "db.php";
require_once "includes/attendance_qr.php";


/*
|--------------------------------------------------------------------------
| Lecturer Access Protection
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    die("Access Denied - Lecturers Only");
}


$lecturer_id = $_SESSION['user_id'];

$message = "";
$session_code = "";
$expires_at = "";
$qr_token = "";
$created_session_id = 0;
$qr_refresh_seconds = DEFAULT_QR_REFRESH_SECONDS;

$latitude = "";
$longitude = "";
$radius = "100";


/*
|--------------------------------------------------------------------------
| Fetch Courses Belonging To This Lecturer
|--------------------------------------------------------------------------
*/

$courses_stmt = $conn->prepare(
    "SELECT id, course_name
     FROM courses
     WHERE lecturer_id = ?
     ORDER BY course_name ASC"
);

$courses_stmt->bind_param("i", $lecturer_id);

$courses_stmt->execute();

$course_result = $courses_stmt->get_result();


/*
|--------------------------------------------------------------------------
| Start Attendance Session
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $course_id = intval($_POST['course_id'] ?? 0);

    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';

    $radius = intval($_POST['radius'] ?? 100);
    $qr_refresh_seconds = normalizeQrRefreshSeconds((int) ($_POST['qr_refresh_seconds'] ?? DEFAULT_QR_REFRESH_SECONDS));


    /*
    |--------------------------------------------------------------------------
    | Validate GPS
    |--------------------------------------------------------------------------
    */

    if ($course_id <= 0) {

        $message = "Please select a course.";

    } elseif (
        $latitude === '' ||
        $longitude === '' ||
        !is_numeric($latitude) ||
        !is_numeric($longitude)
    ) {

        $message = "Unable to get your location. Please allow GPS/location access.";

    } elseif ($radius < 10 || $radius > 1000) {

        $message = "Attendance radius must be between 10 and 1000 meters.";

    } else {


        /*
        |--------------------------------------------------------------------------
        | Verify Course Belongs To Lecturer
        |--------------------------------------------------------------------------
        */

        $verify = $conn->prepare(
            "SELECT id
             FROM courses
             WHERE id = ?
             AND lecturer_id = ?"
        );

        $verify->bind_param(
            "ii",
            $course_id,
            $lecturer_id
        );

        $verify->execute();

        $verify->store_result();


        if ($verify->num_rows === 0) {

            $message = "Invalid course selected.";

            $verify->close();

        } else {

            $verify->close();


            /*
            |--------------------------------------------------------------------------
            | Generate Session Code
            |--------------------------------------------------------------------------
            */

            $session_code = uniqid("SESSION_");


            /*
            |--------------------------------------------------------------------------
            | Generate QR Token
            |--------------------------------------------------------------------------
            */

            $qr_token = bin2hex(random_bytes(16));


            // The session remains active until the lecturer explicitly ends it.
            $expires_at = null;


            /*
            |--------------------------------------------------------------------------
            | Creator
            |--------------------------------------------------------------------------
            */

            $created_by = $_SESSION['user_id'];


            /*
            |--------------------------------------------------------------------------
            | Insert Attendance Session
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO attendance_sessions
                (
                    course_id,
                    session_code,
                    qr_token,
                    qr_refresh_seconds,
                    session_date,
                    expires_at,
                    status,
                    created_by,
                    latitude,
                    longitude,
                    radius
                )
                VALUES (?, ?, ?, ?, CURDATE(), NULL, 'active', ?, ?, ?, ?)
            ");

            if (!$stmt) {
                $message = "Error preparing attendance session: " . $conn->error;
            } else {
                $stmt->bind_param(
                    "issiiddi",
                    $course_id,
                    $session_code,
                    $qr_token,
                    $qr_refresh_seconds,
                    $created_by,
                    $latitude,
                    $longitude,
                    $radius
                );

                if ($stmt->execute()) {
                    $created_session_id = (int) $conn->insert_id;
                    $_SESSION['active_attendance_session_id'] = $created_session_id;
                    $message = "Attendance Session Started Successfully!";
                } else {
                    $message = "Error creating attendance session: " . $stmt->error;
                }

                $stmt->close();
            }

        }

    }

}

// Reopen the lecturer's active session when returning from another page.
if ($_SERVER["REQUEST_METHOD"] !== "POST" && !empty($_SESSION['active_attendance_session_id'])) {
    $restoreId = (int) $_SESSION['active_attendance_session_id'];
    $restore = $conn->prepare(
        "SELECT id, session_code, qr_token, qr_refresh_seconds, expires_at,
                latitude, longitude, radius
         FROM attendance_sessions
         WHERE id = ? AND created_by = ? AND status = 'active'
         LIMIT 1"
    );
    if ($restore) {
        $restore->bind_param('ii', $restoreId, $lecturer_id);
        $restore->execute();
        $active = $restore->get_result()->fetch_assoc() ?: null;
        $restore->close();
        if ($active) {
            $created_session_id = (int) $active['id'];
            $session_code = (string) $active['session_code'];
            $qr_token = (string) $active['qr_token'];
            $qr_refresh_seconds = normalizeQrRefreshSeconds((int) $active['qr_refresh_seconds']);
            $expires_at = $active['expires_at'];
            $latitude = (string) $active['latitude'];
            $longitude = (string) $active['longitude'];
            $radius = (string) $active['radius'];
        } else {
            unset($_SESSION['active_attendance_session_id']);
        }
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Start Attendance Session</title>


    <!-- Bootstrap -->

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

</head>


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


        <!-- NAVBAR -->

        <?php include "includes/navbar.php"; ?>


        <div class="container-fluid">


            <!-- PAGE HEADER -->

            <div class="dashboard-header mb-4">

                <h1>

                    Start Attendance

                </h1>

                <p>

                    Start a GPS-protected attendance session
                    for your class.

                </p>

            </div>


            <!-- ==========================================
                 SUCCESS / ERROR MESSAGE
            =========================================== -->

            <?php if (!empty($message)): ?>

                <div class="alert
                    <?php
                    echo (
                        strpos(
                            $message,
                            "Successfully"
                        ) !== false
                    )
                    ? "alert-success"
                    : "alert-danger";
                    ?>">

                    <i class="bi bi-info-circle-fill me-2"></i>

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>


            <div class="row g-4">


                <!-- ==========================================
                     START SESSION FORM
                =========================================== -->

                <div class="col-lg-7">


                    <div class="card dashboard-card border-0 shadow-sm">


                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                <i class="bi bi-qr-code-scan text-success me-2"></i>

                                Attendance Session

                            </h5>

                        </div>


                        <div class="card-body">


                            <form
                                method="POST"
                                id="attendanceForm">


                                <!-- COURSE -->

                                <div class="mb-4">

                                    <label
                                        for="course_id"
                                        class="form-label fw-semibold">

                                        Select Course

                                    </label>


                                    <select
                                        class="form-select"
                                        name="course_id"
                                        id="course_id"
                                        required>

                                        <option value="">

                                            Select Course

                                        </option>


                                        <?php while (
                                            $course =
                                            $course_result->fetch_assoc()
                                        ): ?>

                                            <option
                                                value="<?php
                                                echo $course['id'];
                                                ?>">

                                                <?php
                                                echo htmlspecialchars(
                                                    $course['course_name']
                                                );
                                                ?>

                                            </option>

                                        <?php endwhile; ?>

                                    </select>

                                </div>


                                <!-- GPS -->

                                <div class="mb-4">


                                    <label
                                        class="form-label fw-semibold">

                                        Class Location

                                    </label>


                                    <div
                                        class="border rounded p-3">


                                        <div
                                            class="d-flex align-items-center mb-3">


                                            <i
                                                class="bi bi-geo-alt-fill text-success fs-3 me-3">
                                            </i>


                                            <div>

                                                <strong>
                                                    GPS Location
                                                </strong>

                                                <div
                                                    id="locationStatus"
                                                    class="text-muted">

                                                    Location not detected yet.

                                                </div>

                                            </div>

                                        </div>


                                        <button
                                            type="button"
                                            id="getLocationBtn"
                                            class="btn btn-success">

                                            <i
                                                class="bi bi-crosshair me-2">
                                            </i>

                                            Get My Location

                                        </button>


                                        <!-- Hidden GPS fields -->

                                        <input
                                            type="hidden"
                                            name="latitude"
                                            id="latitude"
                                            value="<?php
                                            echo htmlspecialchars(
                                                $latitude
                                            );
                                            ?>">


                                        <input
                                            type="hidden"
                                            name="longitude"
                                            id="longitude"
                                            value="<?php
                                            echo htmlspecialchars(
                                                $longitude
                                            );
                                            ?>">


                                    </div>

                                </div>


                                <!-- RADIUS -->

                                <div class="mb-4">

                                    <label
                                        for="radius"
                                        class="form-label fw-semibold">

                                        Attendance Radius

                                    </label>


                                    <div class="input-group">

                                        <input
                                            type="number"
                                            class="form-control"
                                            name="radius"
                                            id="radius"
                                            value="100"
                                            min="10"
                                            max="1000"
                                            required>


                                        <span
                                            class="input-group-text">

                                            meters

                                        </span>

                                    </div>


                                    <div
                                        class="form-text">

                                        Students must be within this
                                        distance of the class location.

                                    </div>

                                </div>


                                <!-- START BUTTON -->

                                <div class="mb-4">
                                    <label for="qr_refresh_seconds" class="form-label fw-semibold">
                                        QR Code Refresh Interval
                                    </label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            class="form-control"
                                            name="qr_refresh_seconds"
                                            id="qr_refresh_seconds"
                                            value="<?php echo (int) $qr_refresh_seconds; ?>"
                                            min="15"
                                            max="300"
                                            required>
                                        <span class="input-group-text">seconds</span>
                                    </div>
                                    <div class="form-text">
                                        The QR changes every 15–300 seconds. Shorter intervals reduce sharing.
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    id="startSessionBtn"
                                    class="btn btn-success"
                                    disabled>

                                    <i
                                        class="bi bi-play-circle me-2">
                                    </i>

                                    Start Attendance Session

                                </button>


                            </form>


                        </div>

                    </div>


                </div>


                <!-- ==========================================
                     LOCATION INFORMATION
                =========================================== -->

                <div class="col-lg-5">


                    <div class="card dashboard-card border-0 shadow-sm">


                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                <i
                                    class="bi bi-geo-alt-fill text-success me-2">
                                </i>

                                Location Information

                            </h5>

                        </div>


                        <div class="card-body">


                            <p class="text-muted">

                                Your current GPS location will be
                                automatically used as the class
                                attendance location.

                            </p>


                            <div class="mb-3">

                                <strong>
                                    Latitude
                                </strong>

                                <div
                                    id="displayLatitude"
                                    class="text-muted">

                                    Not detected

                                </div>

                            </div>


                            <div class="mb-3">

                                <strong>
                                    Longitude
                                </strong>

                                <div
                                    id="displayLongitude"
                                    class="text-muted">

                                    Not detected

                                </div>

                            </div>


                            <div>

                                <strong>
                                    GPS Accuracy
                                </strong>

                                <div
                                    id="displayAccuracy"
                                    class="text-muted">

                                    Not detected

                                </div>

                            </div>


                        </div>

                    </div>


                </div>


            </div>


            <!-- ==========================================
                 SESSION CREATED
            =========================================== -->

            <?php if (!empty($session_code)): ?>


                <div class="card dashboard-card border-0 shadow-sm mt-4">


                    <div class="card-header bg-success text-white">

                        <h5 class="mb-0">

                            <i class="bi bi-check-circle-fill me-2"></i>

                            Attendance Session Active

                        </h5>

                    </div>


                    <div class="card-body">


                        <div class="row g-4">


                            <div class="col-md-6">


                                <p>

                                    <strong>
                                        Session Code:
                                    </strong>

                                    <br>

                                    <?php
                                    echo htmlspecialchars(
                                        $session_code
                                    );
                                    ?>

                                </p>


                                <p>
                                    <strong>QR Rotation:</strong>
                                    <br>
                                    Every <?php echo (int) $qr_refresh_seconds; ?> seconds
                                </p>

                                <div class="mb-3">
                                    <label for="rotatingQrToken" class="form-label fw-semibold">
                                        Manual Entry Token
                                    </label>
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            id="rotatingQrToken"
                                            class="form-control font-monospace small"
                                            value="<?php echo htmlspecialchars(currentAttendanceQrToken($qr_token, $created_session_id, $qr_refresh_seconds), ENT_QUOTES, 'UTF-8'); ?>"
                                            readonly>
                                        <button type="button" id="copyRotatingQrToken" class="btn btn-outline-secondary">
                                            <i class="bi bi-clipboard me-1"></i>Copy
                                        </button>
                                    </div>
                                    <div class="form-text">Changes with the QR code.</div>
                                </div>


                                <p>

                                    <strong>
                                        Expires At:
                                    </strong>

                                    <br>

                                    <?php echo $expires_at
                                        ? htmlspecialchars((string) $expires_at)
                                        : 'Until closed by lecturer'; ?>

                                </p>


                                <p>

                                    <strong>
                                        Class Location:
                                    </strong>

                                    <br>

                                    Latitude:
                                    <?php
                                    echo htmlspecialchars(
                                        $latitude
                                    );
                                    ?>

                                    <br>

                                    Longitude:
                                    <?php
                                    echo htmlspecialchars(
                                        $longitude
                                    );
                                    ?>

                                    <br>

                                    Radius:
                                    <?php
                                    echo htmlspecialchars(
                                        $radius
                                    );
                                    ?>
                                    meters

                                </p>


                            </div>


                            <div class="col-md-6 text-center">


                                <h5>

                                    Scan QR Code

                                </h5>


                                <div class="small text-muted mb-2" id="qrRotationStatus">Rotating QR code...</div>
                                <img
                                    id="attendanceQrImage"
                                    src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?php echo urlencode(currentAttendanceQrToken($qr_token, $created_session_id, $qr_refresh_seconds)); ?>"
                                    alt="Attendance QR Code"
                                    class="img-fluid">

                                <form method="post" action="end_session.php" class="mt-3" onsubmit="return confirm('End this attendance session now? Students will no longer be able to scan it.');">
                                    <input type="hidden" name="session_id" value="<?php echo (int) $created_session_id; ?>">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="bi bi-stop-circle me-2"></i>End Attendance Session
                                    </button>
                                </form>


                            </div>


                        </div>


                    </div>

                </div>


            <?php endif; ?>


            <!-- BACK -->

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


<script>

/*
|--------------------------------------------------------------------------
| Automatic GPS Location
|--------------------------------------------------------------------------
*/

const getLocationBtn =
    document.getElementById("getLocationBtn");

const startSessionBtn =
    document.getElementById("startSessionBtn");

const locationStatus =
    document.getElementById("locationStatus");

const latitudeInput =
    document.getElementById("latitude");

const longitudeInput =
    document.getElementById("longitude");

const displayLatitude =
    document.getElementById("displayLatitude");

const displayLongitude =
    document.getElementById("displayLongitude");

const displayAccuracy =
    document.getElementById("displayAccuracy");


getLocationBtn.addEventListener(
    "click",
    function () {


        /*
        |--------------------------------------------------------------------------
        | Check Browser GPS Support
        |--------------------------------------------------------------------------
        */

        if (!navigator.geolocation) {

            locationStatus.textContent =
                "GPS is not supported by this browser.";

            locationStatus.className =
                "text-danger";

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Show Loading
        |--------------------------------------------------------------------------
        */

        locationStatus.textContent =
            "Detecting your location...";

        locationStatus.className =
            "text-warning";

        getLocationBtn.disabled = true;


        /*
        |--------------------------------------------------------------------------
        | Get GPS Position
        |--------------------------------------------------------------------------
        */

        navigator.geolocation.getCurrentPosition(

            function (position) {


                const latitude =
                    position.coords.latitude;

                const longitude =
                    position.coords.longitude;

                const accuracy =
                    position.coords.accuracy;


                /*
                |--------------------------------------------------------------------------
                | Put GPS Data Into Hidden Inputs
                |--------------------------------------------------------------------------
                */

                latitudeInput.value =
                    latitude;

                longitudeInput.value =
                    longitude;


                /*
                |--------------------------------------------------------------------------
                | Display GPS Information
                |--------------------------------------------------------------------------
                */

                displayLatitude.textContent =
                    latitude.toFixed(6);

                displayLongitude.textContent =
                    longitude.toFixed(6);

                displayAccuracy.textContent =
                    Math.round(accuracy)
                    + " meters";


                /*
                |--------------------------------------------------------------------------
                | Success
                |--------------------------------------------------------------------------
                */

                locationStatus.textContent =
                    "Location detected successfully.";

                locationStatus.className =
                    "text-success";


                getLocationBtn.innerHTML =
                    '<i class="bi bi-check-circle me-2"></i>' +
                    'Location Detected';


                getLocationBtn.classList.remove(
                    "btn-success"
                );

                getLocationBtn.classList.add(
                    "btn-outline-success"
                );


                /*
                |--------------------------------------------------------------------------
                | Allow Session Start
                |--------------------------------------------------------------------------
                */

                startSessionBtn.disabled = false;

            },


            function (error) {


                getLocationBtn.disabled = false;


                startSessionBtn.disabled = true;


                if (error.code === 1) {

                    locationStatus.textContent =
                        "Location permission was denied. " +
                        "Please allow location access.";

                } else if (error.code === 2) {

                    locationStatus.textContent =
                        "Your location could not be determined.";

                } else if (error.code === 3) {

                    locationStatus.textContent =
                        "Location request timed out.";

                } else {

                    locationStatus.textContent =
                        "Unable to get your location.";

                }


                locationStatus.className =
                    "text-danger";

            },


            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }

        );

    }
);


/*
|--------------------------------------------------------------------------
| Prevent Starting Without GPS
|--------------------------------------------------------------------------
*/

document
    .getElementById("attendanceForm")
    .addEventListener(
        "submit",
        function (event) {


            if (
                latitudeInput.value === "" ||
                longitudeInput.value === ""
            ) {

                event.preventDefault();


                alert(
                    "Please get your GPS location before starting the attendance session."
                );

            }

        }
    );

</script>

<?php if ($created_session_id > 0): ?>
<script>
(() => {
    const sessionId = <?php echo (int) $created_session_id; ?>;
    const image = document.getElementById('attendanceQrImage');
    const status = document.getElementById('qrRotationStatus');
    const tokenField = document.getElementById('rotatingQrToken');
    const copyButton = document.getElementById('copyRotatingQrToken');
    if (!image || !status || !tokenField) return;

    async function refreshQr() {
        try {
            const response = await fetch(`session_qr.php?session_id=${sessionId}`, { cache: 'no-store' });
            const data = await response.json();
            if (!response.ok || !data.ok) {
                status.textContent = data.message || 'This session has ended.';
                status.className = 'small text-danger mb-2';
                return;
            }
            image.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(data.token)}&t=${Date.now()}`;
            tokenField.value = data.token;
            status.textContent = `New QR code in ${data.seconds_remaining}s (refreshes every ${data.refresh_seconds}s)`;
            status.className = 'small text-success mb-2';
        } catch (error) {
            status.textContent = 'Unable to refresh the QR code. Check your connection.';
            status.className = 'small text-danger mb-2';
        }
    }

    copyButton?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(tokenField.value);
            copyButton.innerHTML = '<i class="bi bi-check2 me-1"></i>Copied';
            window.setTimeout(() => {
                copyButton.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy';
            }, 1500);
        } catch (error) {
            tokenField.select();
            document.execCommand('copy');
        }
    });

    refreshQr();
    window.setInterval(refreshQr, 1000);
})();
</script>
<?php endif; ?>


</body>

</html>

<?php

$courses_stmt->close();

?>
