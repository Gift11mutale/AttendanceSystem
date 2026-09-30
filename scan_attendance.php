<?php

session_start();
header('Permissions-Policy: geolocation=(self)');
include "db.php";
require_once "includes/geofence.php";
require_once "includes/attendance_qr.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied - Students Only");
}

$student_id = $_SESSION['user_id'];

$message = "";
$message_type = "";


/*
|--------------------------------------------------------------------------
| PROCESS QR TOKEN
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $qr_token = trim($_POST['qr_token'] ?? '');
    $student_latitude = $_POST['latitude'] ?? '';
    $student_longitude = $_POST['longitude'] ?? '';

    if ($qr_token === '') {

        $message = "No QR code was detected.";
        $message_type = "danger";

    } elseif (
        $student_latitude === '' ||
        $student_longitude === '' ||
        !is_numeric($student_latitude) ||
        !is_numeric($student_longitude)
    ) {
        $message = "Your location is required. Please allow location access.";
        $message_type = "danger";
    } else {
        $student_latitude = (float) $student_latitude;
        $student_longitude = (float) $student_longitude;
        if (!validGpsCoordinates($student_latitude, $student_longitude)) {
            $message = "The GPS coordinates are invalid. Please try again.";
            $message_type = "danger";
        } else {

        /*
        |--------------------------------------------------------------------------
        | FIND ACTIVE SESSION
        |--------------------------------------------------------------------------
        */

        $session = findSessionByAttendanceToken($conn, $qr_token);

        if ($session === null) {

            $message = "Invalid or closed attendance session.";
            $message_type = "danger";

        } else {

            $session_id = $session['id'];
            $course_id = $session['course_id'];
            $class_latitude = (float) $session['latitude'];
            $class_longitude = (float) $session['longitude'];
            $radius = (int) $session['radius'];
            $distance = gpsDistanceMeters(
                $class_latitude,
                $class_longitude,
                $student_latitude,
                $student_longitude
            );


            /*
            |--------------------------------------------------------------------------
            | CHECK EXPIRY
            |--------------------------------------------------------------------------
            */

            if ($distance > $radius) {
                $message = "Attendance rejected. You are approximately "
                    . round($distance)
                    . " meters away. You must be within "
                    . $radius . " meters.";
                $message_type = "danger";
            } elseif (
                !empty($session['expires_at']) &&
                strtotime($session['expires_at']) < time()
            ) {

                $message = "This attendance session has expired.";
                $message_type = "danger";

            } else {


                /*
                |--------------------------------------------------------------------------
                | CHECK ENROLLMENT
                |--------------------------------------------------------------------------
                */

                $checkEnroll = $conn->prepare("
                    SELECT id
                    FROM enrollments
                    WHERE student_id = ?
                    AND course_id = ?
                    LIMIT 1
                ");

                $checkEnroll->bind_param(
                    "ii",
                    $student_id,
                    $course_id
                );

                $checkEnroll->execute();

                $enrollResult = $checkEnroll->get_result();


                if ($enrollResult->num_rows === 0) {

                    $message =
                        "You are not enrolled in this course.";

                    $message_type = "danger";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | CHECK DUPLICATE ATTENDANCE
                    |--------------------------------------------------------------------------
                    */

                    $checkAttendance = $conn->prepare("
                        SELECT id
                        FROM attendance
                        WHERE session_id = ?
                        AND student_id = ?
                        LIMIT 1
                    ");

                    $checkAttendance->bind_param(
                        "ii",
                        $session_id,
                        $student_id
                    );

                    $checkAttendance->execute();

                    $attendanceResult =
                        $checkAttendance->get_result();


                    if ($attendanceResult->num_rows > 0) {

                        $message =
                            "You have already marked attendance for this session.";

                        $message_type = "warning";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | INSERT ATTENDANCE
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
                                "Attendance Marked Successfully!";

                            $message_type = "success";

                        } else {

                            $message =
                                "Error recording attendance.";

                            $message_type = "danger";
                        }


                        $insert->close();
                    }


                    $checkAttendance->close();
                }


                $checkEnroll->close();
            }
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

    <title>Mark Attendance</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- QR SCANNER -->

    <script
        src="https://unpkg.com/html5-qrcode"
    ></script>


    <style>

        body {
            background: #f4f7f6;
            font-family: Arial, sans-serif;
        }

        .page-container {
            width: 94%;
            max-width: 700px;
            margin: 30px auto;
        }

        .scanner-card {
            background: white;
            border: none;
            border-radius: 18px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .header {
            background: #0f7454;
            color: white;
            padding: 25px;
        }

        .header h2 {
            font-weight: bold;
            margin: 0;
        }

        .body {
            padding: 25px;
        }

        #reader {
            width: 100%;
            max-width: 500px;
            margin: 20px auto;
            border-radius: 12px;
            overflow: hidden;
        }

        .btn-kmu {
            background: #0f7454;
            color: white;
        }

        .btn-kmu:hover {
            background: #09583f;
            color: white;
        }

        .manual-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
        }

        @media (max-width: 576px) {

            .page-container {
                width: 94%;
                margin: 15px auto;
            }

            .header {
                padding: 20px;
            }

            .body {
                padding: 18px;
            }

            .header h2 {
                font-size: 22px;
            }

        }

    </style>


<link rel="stylesheet" href="assets/css/custom-popups.css">
<script src="assets/js/custom-popups.js" defer></script></head>

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


<div class="page-container">

    <div class="card scanner-card">


        <div class="header">

            <h2>

                <i class="bi bi-qr-code-scan"></i>

                Mark Attendance

            </h2>

            <p class="mb-0 mt-2">

                Scan the QR code displayed by your lecturer.

            </p>

        </div>


        <div class="body">


            <?php if ($message !== ""): ?>

                <div
                    class="alert alert-<?php echo $message_type; ?> text-center"
                >

                    <?php echo $message; ?>

                </div>

            <?php endif; ?>


            <!-- CAMERA SCANNER -->

            <h5 class="text-center">

                <i class="bi bi-camera-fill"></i>

                Scan QR Code

            </h5>


            <div id="reader"></div>


            <div
                id="scannerMessage"
                class="text-center text-muted"
            >

                Allow camera access when your browser asks.

            </div>


            <hr class="my-4">


            <!-- MANUAL TOKEN -->

            <div class="manual-box">

                <h6>

                    <i class="bi bi-key-fill"></i>

                    Enter QR Token Manually

                </h6>

                <p class="text-muted small">

                    Use this option if the camera is unavailable.

                </p>


                <form method="POST" id="alternateAttendanceForm">

                    <div id="locationPreview" class="alert alert-info small">Detecting your location...</div>

                    <input
                        type="text"
                        name="qr_token"
                        class="form-control mb-3"
                        placeholder="Enter QR token"
                        required
                    >

                    <input type="hidden" name="latitude" id="alternateLatitude">
                    <input type="hidden" name="longitude" id="alternateLongitude">
                    <button
                        type="submit"
                        class="btn btn-kmu w-100"
                    >

                        Submit Attendance

                    </button>

                </form>

            </div>


            <div class="text-center mt-4">

                <a
                    href="student_dashboard.php"
                    class="btn btn-outline-secondary"
                >

                    <i class="bi bi-arrow-left"></i>

                    Back to Dashboard

                </a>

            </div>


        </div>

    </div>

</div>


<script>

function submitQRToken(token) {

    if (typeof window.__gpsSubmitQRToken === "function") {
        window.__gpsSubmitQRToken(token);
        return;
    }

    document.getElementById("scannerMessage").innerHTML =
        "<strong class='text-primary'>Preparing GPS verification...</strong>";

    window.setTimeout(function () {
        submitQRToken(token);
    }, 100);

}


function onScanSuccess(decodedText, decodedResult) {

    document.getElementById("scannerMessage").innerHTML =
        "<strong class='text-success'>QR code detected. Recording attendance...</strong>";


    submitQRToken(decodedText);

}


function onScanFailure(error) {

    // Keep scanning.

}


const qrBoxSize = Math.min(250, Math.max(180, window.innerWidth - 96));

const scanner = new Html5QrcodeScanner(

    "reader",

    {

        fps: 10,

        qrbox: {
            width: qrBoxSize,
            height: qrBoxSize
        },

        rememberLastUsedCamera: true

    },

    false

);


scanner.render(
    onScanSuccess,
    onScanFailure
);

</script>


</body>

</html>
<script>
(() => {
    const preview = document.getElementById('locationPreview');
    const scannerMessage = document.getElementById('scannerMessage');
    const manualForm = document.getElementById('alternateAttendanceForm');
    const manualToken = manualForm.querySelector('input[name="qr_token"]');
    let currentPosition = null;
    let submitting = false;

    function renderDistanceIndicator(distance, radius) {
        let indicator = document.getElementById('distanceIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'distanceIndicator';
            indicator.className = 'mt-2';
            preview.insertAdjacentElement('afterend', indicator);
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
            <div class="small text-muted mt-1">Distance is checked against the class radius.</div>`;
    }

    function distanceMeters(lat1, lon1, lat2, lon2) {
        const radians = Math.PI / 180;
        const a = Math.sin((lat2 - lat1) * radians / 2) ** 2
            + Math.cos(lat1 * radians) * Math.cos(lat2 * radians)
            * Math.sin((lon2 - lon1) * radians / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function locate() {
        return new Promise((resolve, reject) => {
            if (!window.isSecureContext) {
                reject(new Error('Location requires HTTPS. Open https://scanattend.site.je in your browser.'));
                return;
            }
            if (!navigator.geolocation) {
                reject(new Error('GPS is not supported by this browser.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            });
        });
    }

    async function ensureLocation() {
        if (!currentPosition) {
            preview.textContent = 'Detecting your location...';
            currentPosition = await locate();
        }
        preview.textContent = 'Location ready. Distance will be checked against the class radius.';
        return currentPosition;
    }

    async function checkAndSubmit(token) {
        if (submitting) return;
        token = token.trim();
        if (!token) {
            preview.textContent = 'Please scan or enter the QR token first.';
            return;
        }
        try {
            scannerMessage.innerHTML = "<strong class='text-primary'>Checking GPS and class distance...</strong>";
            const position = await ensureLocation();
            preview.textContent = 'Checking distance from the class...';
            const response = await fetch('session_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ qr_token: token })
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.message || 'Invalid or expired attendance session.');
            const distance = distanceMeters(
                Number(data.latitude), Number(data.longitude),
                position.coords.latitude, position.coords.longitude
            );
            preview.textContent = `Distance from class: ${Math.round(distance)}m | Allowed: ${data.radius}m`;
            preview.className = `alert small ${distance <= Number(data.radius) ? 'alert-success' : 'alert-danger'}`;
            renderDistanceIndicator(distance, Number(data.radius));
            if (distance > Number(data.radius)) {
                preview.textContent += ' — attendance cannot be submitted outside the allowed radius.';
                return;
            }
            submitting = true;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'scan_attendance.php';
            [['qr_token', token], ['latitude', position.coords.latitude], ['longitude', position.coords.longitude]].forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });
            document.body.appendChild(form);
            scannerMessage.innerHTML = "<strong class='text-success'>Location verified. Recording attendance...</strong>";
            form.submit();
        } catch (error) {
            scannerMessage.innerHTML = "<strong class='text-danger'>Attendance could not be submitted.</strong>";
            preview.textContent = error.message || 'Unable to verify your location.';
            preview.className = 'alert alert-danger small';
        }
    }

    navigator.geolocation && locate().then((position) => {
        currentPosition = position;
        preview.textContent = 'Location ready. Distance will be checked against the class radius.';
        preview.className = 'alert alert-info small';
    }).catch((error) => {
        preview.textContent = error.message || 'Please allow location access.';
        preview.className = 'alert alert-warning small';
    });

    manualForm.addEventListener('submit', (event) => {
        event.preventDefault();
        checkAndSubmit(manualToken.value);
    });

    window.__gpsSubmitQRToken = checkAndSubmit;
})();
</script>
