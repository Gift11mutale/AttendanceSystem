<?php

session_start();
header('Permissions-Policy: geolocation=(self)');
include "db.php";
require_once "includes/geofence.php";
require_once "includes/attendance_qr.php";

// Restrict to students only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    die("Access Denied - Students Only");
}

$student_id = $_SESSION['user_id'];

$message = "";
$message_type = "danger";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $qr_token = trim($_POST['qr_token'] ?? '');
    $student_latitude = $_POST['latitude'] ?? '';
    $student_longitude = $_POST['longitude'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validate QR Token
    |--------------------------------------------------------------------------
    */

    if ($qr_token === '') {

        $message = "Please enter or scan the QR token.";

    } elseif (
        $student_latitude === '' ||
        $student_longitude === '' ||
        !is_numeric($student_latitude) ||
        !is_numeric($student_longitude)
    ) {

        $message = "Your location could not be detected. Please allow location access.";

    } else {

        $student_latitude = (float) $student_latitude;
        $student_longitude = (float) $student_longitude;
        if (!validGpsCoordinates($student_latitude, $student_longitude)) {
            $message = "The GPS coordinates are invalid. Please try again.";
        } else {

        /*
        |--------------------------------------------------------------------------
        | Check Session
        |--------------------------------------------------------------------------
        */

        $session = findSessionByAttendanceToken($conn, $qr_token);

        if ($session !== null) {

            $session_id = $session['id'];
            $course_id = $session['course_id'];

            $class_latitude = (float) $session['latitude'];
            $class_longitude = (float) $session['longitude'];
            $radius = (int) $session['radius'];


            /*
            |--------------------------------------------------------------------------
            | Check Session Expiry
            |--------------------------------------------------------------------------
            */

            if (
                !empty($session['expires_at']) &&
                strtotime($session['expires_at']) < time()
            ) {

                $message = "This attendance session has expired.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | Check Student Enrollment
                |--------------------------------------------------------------------------
                */

                $checkEnroll = $conn->prepare("
                    SELECT *
                    FROM enrollments
                    WHERE student_id = ?
                    AND course_id = ?
                ");

                $checkEnroll->bind_param(
                    "ii",
                    $student_id,
                    $course_id
                );

                $checkEnroll->execute();

                $enrollResult = $checkEnroll->get_result();


                if ($enrollResult->num_rows == 1) {


                    /*
                    |--------------------------------------------------------------------------
                    | Check Duplicate Attendance
                    |--------------------------------------------------------------------------
                    */

                    $checkAttendance = $conn->prepare("
                        SELECT *
                        FROM attendance
                        WHERE session_id = ?
                        AND student_id = ?
                    ");

                    $checkAttendance->bind_param(
                        "ii",
                        $session_id,
                        $student_id
                    );

                    $checkAttendance->execute();

                    $attendanceResult =
                        $checkAttendance->get_result();


                    if ($attendanceResult->num_rows == 0) {


                        /*
                        |--------------------------------------------------------------------------
                        | GPS DISTANCE CALCULATION
                        |--------------------------------------------------------------------------
                        */

                        $distance = gpsDistanceMeters(
                            $class_latitude,
                            $class_longitude,
                            $student_latitude,
                            $student_longitude
                        );


                        /*
                        |--------------------------------------------------------------------------
                        | Check Attendance Radius
                        |--------------------------------------------------------------------------
                        */

                        if ($distance > $radius) {

                            $message =
                                "Attendance rejected. You are approximately "
                                . round($distance)
                                . " meters away from the class location. "
                                . "You must be within "
                                . $radius
                                . " meters.";

                        } else {


                            /*
                            |--------------------------------------------------------------------------
                            | Insert Attendance
                            |--------------------------------------------------------------------------
                            */

                            $insert = $conn->prepare("
                                INSERT INTO attendance
                                (
                                    session_id,
                                    student_id,
                                    status
                                )
                                VALUES (?, ?, 'present')
                            ");

                            $insert->bind_param(
                                "ii",
                                $session_id,
                                $student_id
                            );


                            if ($insert->execute()) {

                                $message =
                                    "Attendance Marked Successfully! "
                                    . "You are approximately "
                                    . round($distance)
                                    . " meters from the class.";

                                $message_type = "success";

                            } else {

                                $message =
                                    "Error recording attendance.";
                            }


                            $insert->close();
                        }


                    } else {

                        $message =
                            "You have already marked attendance for this session.";
                    }


                    $checkAttendance->close();


                } else {

                    $message =
                        "You are not enrolled in this course.";
                }


                $checkEnroll->close();
            }


        } else {

            $message =
                "Invalid or Closed Session.";
        }

        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Scan Attendance</title>


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

    <!-- Dashboard CSS -->

    <link
        rel="stylesheet"
        href="assets/dashboard.css?v=3"
    >

    <!-- QR Scanner -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

</head>

<body>

<?php if ($message_type === "success"): ?>
<div id="attendanceSuccessPopup" class="attendance-success-popup" role="dialog" aria-modal="true" aria-labelledby="attendanceSuccessTitle">
    <div class="attendance-success-card">
        <div class="attendance-success-icon"><i class="bi bi-check-lg"></i></div>
        <h3 id="attendanceSuccessTitle">Attendance Recorded!</h3>
        <p>Your attendance has been successfully recorded.</p>
        <div class="attendance-success-detail"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <button type="button" id="closeAttendanceSuccess" class="btn btn-success px-4">Continue</button>
    </div>
</div>
<style>
.attendance-success-popup { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(8, 35, 26, .58); }
.attendance-success-card { width: min(430px, 100%); padding: 2rem; text-align: center; background: #fff; border-radius: 22px; box-shadow: 0 18px 60px rgba(0,0,0,.25); animation: attendancePopupIn .22s ease-out; }
.attendance-success-icon { width: 68px; height: 68px; margin: 0 auto 1rem; display: grid; place-items: center; border-radius: 50%; color: #fff; background: #198754; font-size: 2rem; }
.attendance-success-card h3 { color: #146c43; margin-bottom: .5rem; }
.attendance-success-card p { margin-bottom: .75rem; color: #495057; }
.attendance-success-detail { margin-bottom: 1.25rem; padding: .75rem; border-radius: 10px; color: #146c43; background: #e9f7ef; font-size: .9rem; }
@keyframes attendancePopupIn { from { opacity: 0; transform: translateY(12px) scale(.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
</style>
<script>
document.getElementById('closeAttendanceSuccess')?.addEventListener('click', () => {
    document.getElementById('attendanceSuccessPopup')?.remove();
});
</script>
<?php endif; ?>


<div class="container-fluid">


    <!-- SIDEBAR -->

    <?php include "includes/sidebar.php"; ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">


        <!-- NAVBAR -->

        <?php include "includes/navbar.php"; ?>


        <div class="container-fluid">


            <!-- HEADER -->

            <div class="dashboard-header mb-4">

                <h1>
                    Scan Attendance
                </h1>

                <p>
                    Scan the attendance QR code and verify your location.
                </p>

            </div>


            <!-- ATTENDANCE CARD -->

            <div class="row justify-content-center">

                <div class="col-lg-7">


                    <div class="card dashboard-card border-0 shadow-sm">


                        <div class="card-header bg-white">

                            <h5 class="mb-0">

                                <i
                                    class="bi bi-qr-code-scan text-success me-2"
                                ></i>

                                Mark Attendance

                            </h5>

                        </div>


                        <div class="card-body">


                            <!-- MESSAGE -->

                            <?php if (!empty($message)): ?>

                                <div
                                    class="alert alert-<?php echo $message_type; ?>"
                                >

                                    <?php if ($message_type === "success"): ?>

                                        <i
                                            class="bi bi-check-circle-fill me-2"
                                        ></i>

                                    <?php else: ?>

                                        <i
                                            class="bi bi-exclamation-triangle-fill me-2"
                                        ></i>

                                    <?php endif; ?>


                                    <?php
                                    echo htmlspecialchars($message);
                                    ?>

                                </div>

                            <?php endif; ?>


                            <!-- FORM -->

                            <form
                                method="POST"
                                id="attendanceForm"
                            >

                                <!-- QR CAMERA SCANNER -->
                                <div class="mb-4">

                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-camera-fill text-success me-1"></i>
                                        Scan Lecturer QR Code
                                    </label>

                                    <div class="border rounded p-3 bg-light">
                                        <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>

                                        <div id="qrScanStatus" class="text-muted text-center mt-3">
                                            Tap the button below to scan the lecturer's QR code.
                                        </div>

                                        <div class="text-center mt-3">
                                            <button
                                                type="button"
                                                id="startScannerBtn"
                                                class="btn btn-primary"
                                            >
                                                <i class="bi bi-camera me-2"></i>
                                                Scan QR Code
                                            </button>

                                            <button
                                                type="button"
                                                id="stopScannerBtn"
                                                class="btn btn-outline-danger ms-2"
                                                style="display:none;"
                                            >
                                                <i class="bi bi-stop-circle me-2"></i>
                                                Stop Camera
                                            </button>
                                        </div>
                                    </div>

                                </div>

                                <!-- QR TOKEN -->

                                <div class="mb-4">

                                    <label
                                        for="qr_token"
                                        class="form-label fw-semibold"
                                    >

                                        QR Token

                                    </label>


                                    <input
                                        type="text"
                                        name="qr_token"
                                        id="qr_token"
                                        class="form-control"
                                        placeholder="Enter or scan QR token"
                                        required
                                    >

                                    <div class="form-text">

                                        Enter the token from the lecturer's
                                        attendance QR code.

                                    </div>

                                </div>


                                <!-- GPS STATUS -->

                                <div class="mb-4">

                                    <label
                                        class="form-label fw-semibold"
                                    >

                                        <i
                                            class="bi bi-geo-alt-fill text-success me-1"
                                        ></i>

                                        Your Location

                                    </label>


                                    <div
                                        class="border rounded p-3"
                                        id="locationBox"
                                    >

                                        <div
                                            class="d-flex align-items-center"
                                        >

                                            <i
                                                class="bi bi-crosshair text-success fs-2 me-3"
                                            ></i>


                                            <div>

                                                <strong id="locationTitle">

                                                    Detecting location...

                                                </strong>


                                                <div
                                                    id="locationStatus"
                                                    class="text-muted"
                                                >

                                                    Please allow location
                                                    access when your browser
                                                    asks.

                                                </div>

                                                <div id="distanceStatus" class="small mt-2 fw-semibold"></div>

                                            </div>

                                        </div>


                                    </div>

                                </div>


                                <!-- HIDDEN GPS FIELDS -->

                                <input
                                    type="hidden"
                                    name="latitude"
                                    id="latitude"
                                >


                                <input
                                    type="hidden"
                                    name="longitude"
                                    id="longitude"
                                >

                                <!-- SUBMIT -->

                                <button
                                    type="submit"
                                    id="submitBtn"
                                    class="btn btn-success w-100"
                                    disabled
                                >

                                    <i
                                        class="bi bi-check-circle me-2"
                                    ></i>

                                    Mark Attendance

                                </button>


                            </form>


                        </div>

                    </div>


                    <!-- INFORMATION -->

                    <div
                        class="card dashboard-card border-0 shadow-sm mt-4"
                    >

                        <div class="card-body">

                            <h5>

                                <i
                                    class="bi bi-info-circle-fill text-primary me-2"
                                ></i>

                                How GPS Attendance Works

                            </h5>


                            <ul class="mb-0">

                                <li>
                                    Your browser automatically detects
                                    your current location.
                                </li>

                                <li>
                                    The system compares your location with
                                    the classroom location.
                                </li>

                                <li>
                                    You must be within the lecturer's
                                    allowed attendance radius.
                                </li>

                                <li>
                                    If you are outside the allowed area,
                                    attendance will be rejected.
                                </li>

                            </ul>

                        </div>

                    </div>


                    <!-- BACK -->

                    <div class="mt-4">

                        <a
                            href="student_dashboard.php"
                            class="btn btn-outline-light"
                        >

                            <i
                                class="bi bi-arrow-left me-2"
                            ></i>

                            Back to Dashboard

                        </a>

                    </div>


                </div>

            </div>


        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| QR CAMERA SCANNER
|--------------------------------------------------------------------------
*/

const qrReaderElement = document.getElementById("qr-reader");
const startScannerBtn = document.getElementById("startScannerBtn");
const stopScannerBtn = document.getElementById("stopScannerBtn");
const qrScanStatus = document.getElementById("qrScanStatus");
const qrTokenInput = document.getElementById("qr_token");

let qrScanner = null;
let scannerRunning = false;

function onQrScanSuccess(decodedText) {

    qrTokenInput.value = decodedText.trim();

    qrScanStatus.textContent =
        "QR code detected successfully. You can now mark attendance.";
    qrScanStatus.className =
        "text-success text-center mt-3 fw-semibold";

    if (qrScanner && scannerRunning) {
        qrScanner.stop().then(function () {
            scannerRunning = false;
            startScannerBtn.style.display = "inline-block";
            stopScannerBtn.style.display = "none";
        }).catch(function () {
            scannerRunning = false;
            startScannerBtn.style.display = "inline-block";
            stopScannerBtn.style.display = "none";
        });
    }
}

function onQrScanError(errorMessage) {
    // Normal frame-by-frame scan failures are ignored.
}

startScannerBtn.addEventListener("click", function () {

    if (typeof Html5Qrcode === "undefined") {
        qrScanStatus.textContent =
            "QR scanner could not be loaded. Check your internet connection.";
        qrScanStatus.className =
            "text-danger text-center mt-3";
        return;
    }

    if (scannerRunning) {
        return;
    }

    qrScanner = new Html5Qrcode("qr-reader");
    scannerRunning = true;

    startScannerBtn.style.display = "none";
    stopScannerBtn.style.display = "inline-block";

    qrScanStatus.textContent =
        "Point your camera at the lecturer's QR code.";
    qrScanStatus.className =
        "text-primary text-center mt-3";

    qrScanner.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 250, height: 250 }
        },
        onQrScanSuccess,
        onQrScanError
    ).catch(function (error) {

        scannerRunning = false;
        startScannerBtn.style.display = "inline-block";
        stopScannerBtn.style.display = "none";

        qrScanStatus.textContent =
            "Unable to access the camera. Please allow camera permission and try again.";
        qrScanStatus.className =
            "text-danger text-center mt-3";

        console.error("QR scanner error:", error);
    });
});

stopScannerBtn.addEventListener("click", function () {

    if (!qrScanner || !scannerRunning) {
        return;
    }

    qrScanner.stop().then(function () {

        scannerRunning = false;
        startScannerBtn.style.display = "inline-block";
        stopScannerBtn.style.display = "none";

        qrScanStatus.textContent =
            "Camera stopped. Tap Scan QR Code to scan again.";
        qrScanStatus.className =
            "text-muted text-center mt-3";

    }).catch(function (error) {
        console.error("Unable to stop QR scanner:", error);
    });
});


/*
|--------------------------------------------------------------------------
| Automatic Student GPS
|--------------------------------------------------------------------------
*/

const form =
    document.getElementById("attendanceForm");

const submitBtn =
    document.getElementById("submitBtn");

const latitudeInput =
    document.getElementById("latitude");

const longitudeInput =
    document.getElementById("longitude");


const locationTitle =
    document.getElementById("locationTitle");

const locationStatus =
    document.getElementById("locationStatus");


function getStudentLocation() {

    if (!window.isSecureContext) {
        locationTitle.textContent = "Secure connection required";
        locationTitle.className = "text-danger";
        locationStatus.textContent = "Open this page using https://scanattend.site.je so your browser can request location.";
        submitBtn.disabled = true;
        return;
    }

    if (!navigator.geolocation) {

        locationTitle.textContent =
            "GPS Not Supported";

        locationTitle.className =
            "text-danger";

        locationStatus.textContent =
            "Your browser does not support GPS location.";

        return;
    }


    locationTitle.textContent =
        "Detecting location...";

    locationTitle.className =
        "";


    locationStatus.textContent =
        "Please allow location access.";


    navigator.geolocation.getCurrentPosition(

        function(position) {

            const latitude =
                position.coords.latitude;

            const longitude =
                position.coords.longitude;


            /*
            |--------------------------------------------------------------------------
            | Store GPS
            |--------------------------------------------------------------------------
            */

            latitudeInput.value =
                latitude;

            longitudeInput.value =
                longitude;


            /*
            |--------------------------------------------------------------------------
            | Display Location
            |--------------------------------------------------------------------------
            */

            locationTitle.textContent =
                "Location Detected Successfully";

            locationTitle.className =
                "text-success";


            locationStatus.textContent =
                "Location detected. Distance will be checked against the class radius.";


            /*
            |--------------------------------------------------------------------------
            | Allow Submission
            |--------------------------------------------------------------------------
            */

            submitBtn.disabled = false;

        },


        function(error) {

            submitBtn.disabled = true;


            locationTitle.textContent =
                "Location Required";

            locationTitle.className =
                "text-danger";


            if (error.code === 1) {

                locationStatus.textContent =
                    "Location permission was denied. "
                    + "Please allow location access.";

            } else if (error.code === 2) {

                locationStatus.textContent =
                    "Your location could not be determined.";

            } else if (error.code === 3) {

                locationStatus.textContent =
                    "Location request timed out.";

            } else {

                locationStatus.textContent =
                    "Unable to detect your location.";
            }

        },


        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }

    );
}


/*
|--------------------------------------------------------------------------
| Get Location When Page Loads
|--------------------------------------------------------------------------
*/

window.addEventListener(
    "load",
    getStudentLocation
);


/*
|--------------------------------------------------------------------------
| Prevent Submission Without GPS
|--------------------------------------------------------------------------
*/

form.addEventListener(
    "submit",
    function(event) {

        if (
            latitudeInput.value === "" ||
            longitudeInput.value === ""
        ) {

            event.preventDefault();

            alert(
                "Please allow location access before marking attendance."
            );

        }

    }
);


/*
|--------------------------------------------------------------------------
| Stop Camera When Leaving Page
|--------------------------------------------------------------------------
*/

window.addEventListener("beforeunload", function () {

    if (qrScanner && scannerRunning) {
        qrScanner.stop().catch(function () {});
    }

});

</script>


</body>

</html>
<script>
(() => {
    const formElement = document.getElementById('attendanceForm');
    const tokenInput = document.getElementById('qr_token');
    const latitudeField = document.getElementById('latitude');
    const longitudeField = document.getElementById('longitude');
    const submitButton = document.getElementById('submitBtn');
    const distanceStatus = document.getElementById('distanceStatus');
    let lastPreviewToken = '';
    let submitting = false;

    function renderDistanceIndicator(distance, radius) {
        let indicator = document.getElementById('distanceIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'distanceIndicator';
            indicator.className = 'mt-3';
            distanceStatus.insertAdjacentElement('afterend', indicator);
        }
        const percentage = Math.min(100, Math.max(0, (distance / radius) * 100));
        const inside = distance <= radius;
        const color = inside ? '#198754' : '#dc3545';
        indicator.innerHTML = `
            <div class="d-flex justify-content-between small mb-1">
                <span>${inside ? 'Inside attendance radius' : 'Outside attendance radius'}</span>
                <strong>${Math.round(distance)}m / ${radius}m</strong>
            </div>
            <div style="height:12px;background:#e9ecef;border-radius:999px;overflow:hidden">
                <div style="height:100%;width:${percentage}%;background:${color};transition:width .35s ease,background .35s ease"></div>
            </div>
            <div class="small text-muted mt-1">GPS accuracy is not used as a requirement.</div>`;
    }

    function distanceMeters(lat1, lon1, lat2, lon2) {
        const radians = Math.PI / 180;
        const a = Math.sin((lat2 - lat1) * radians / 2) ** 2
            + Math.cos(lat1 * radians) * Math.cos(lat2 * radians)
            * Math.sin((lon2 - lon1) * radians / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    async function previewDistance() {
        const token = tokenInput.value.trim();
        if (!token || !latitudeField.value || !longitudeField.value) return false;
        if (token === lastPreviewToken) return true;
        lastPreviewToken = token;
        distanceStatus.textContent = 'Checking distance from the class...';
        distanceStatus.className = 'small mt-2 fw-semibold text-muted';
        const response = await fetch('session_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ qr_token: token })
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            distanceStatus.textContent = data.message || 'Unable to verify the attendance session.';
            distanceStatus.className = 'small mt-2 fw-semibold text-danger';
            return false;
        }
        const distance = distanceMeters(
            Number(data.latitude), Number(data.longitude),
            Number(latitudeField.value), Number(longitudeField.value)
        );
        const withinRadius = distance <= Number(data.radius);
        distanceStatus.textContent = `Distance from class: ${Math.round(distance)}m | Allowed: ${data.radius}m`;
        distanceStatus.className = `small mt-2 fw-semibold ${withinRadius ? 'text-success' : 'text-danger'}`;
        renderDistanceIndicator(distance, Number(data.radius));
        submitButton.disabled = !withinRadius;
        return withinRadius;
    }

    tokenInput.addEventListener('input', () => {
        lastPreviewToken = '';
        previewDistance().catch(() => {
            distanceStatus.textContent = 'Unable to check the class location.';
            distanceStatus.className = 'small mt-2 fw-semibold text-danger';
        });
    });

    formElement.addEventListener('submit', async (event) => {
        if (submitting) return;
        event.preventDefault();
        if (!tokenInput.value.trim()) {
            alert('Please scan or enter the QR token first.');
            return;
        }
        try {
            const allowed = await previewDistance();
            if (!allowed) return;
            submitting = true;
            submitButton.disabled = true;
            HTMLFormElement.prototype.submit.call(formElement);
        } catch (error) {
            distanceStatus.textContent = 'Unable to verify your distance. Please try again.';
            distanceStatus.className = 'small mt-2 fw-semibold text-danger';
        }
    });

    const originalScanSuccess = window.onQrScanSuccess;
    if (typeof originalScanSuccess === 'function') {
        window.onQrScanSuccess = function (decodedText) {
            originalScanSuccess(decodedText);
            tokenInput.dispatchEvent(new Event('input'));
        };
    }
})();
</script>
