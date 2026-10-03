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

    if ($qr_token === '') {

        $message = "No QR code was detected.";
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

            /*
            |--------------------------------------------------------------------------
            | CHECK EXPIRY (Radius limitation removed)
            |--------------------------------------------------------------------------
            */

            if (
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

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' ||
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'))
) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $message_type === 'success',
        'type' => $message_type ?: 'danger',
        'message' => $message,
    ]);
    exit;
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

<?php if ($message !== ""): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.showCustomPopup === 'function') {
        window.showCustomPopup(
            <?php echo json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>,
            <?php echo json_encode($message_type ?: 'danger'); ?>,
            <?php echo json_encode($message_type === 'success' ? 'Attendance Recorded' : ($message_type === 'warning' ? 'Already Scanned' : 'Attendance Error')); ?>,
            <?php echo json_encode($message_type === 'success' ? ['buttonText' => 'Continue', 'secondaryButton' => ['text' => '<i class="bi bi-arrow-left"></i> Dashboard', 'href' => 'student_dashboard.php']] : (object)[]); ?>
        );
    }
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
                    data-popup-ignore="true"
                    hidden
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

            <div id="scannerControls" class="text-center mt-2" style="display:none;">
                <button type="button" class="btn btn-sm btn-outline-success" id="resumeScannerBtn">
                    <i class="bi bi-camera"></i> Scan Another QR Code
                </button>
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

                    <div id="locationPreview" class="alert alert-info small">Ready to scan QR code or enter token.</div>

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
(() => {
    const preview = document.getElementById('locationPreview');
    const scannerMessage = document.getElementById('scannerMessage');
    const manualForm = document.getElementById('alternateAttendanceForm');
    const manualToken = manualForm ? manualForm.querySelector('input[name="qr_token"]') : null;
    const resumeBtn = document.getElementById('resumeScannerBtn');
    const scannerControls = document.getElementById('scannerControls');

    let currentPosition = null;
    let submitting = false;
    let lastScannedToken = '';
    let lastScanTimestamp = 0;
    let scanner = null;

    function updateHiddenInputs(pos) {
        if (!pos || !pos.coords) return;
        const latInput = document.getElementById('alternateLatitude');
        const lngInput = document.getElementById('alternateLongitude');
        if (latInput) latInput.value = pos.coords.latitude;
        if (lngInput) lngInput.value = pos.coords.longitude;
    }

    function locate() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('GPS is not supported by this browser.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve(pos),
                (geoError) => reject(geoError),
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                }
            );
        });
    }

    async function checkAndSubmit(token) {
        if (submitting) return;

        token = (token || '').trim();
        if (!token) {
            if (preview) {
                preview.textContent = 'Please scan or enter the QR token first.';
                preview.className = 'alert alert-warning small';
            }
            if (scannerMessage) {
                scannerMessage.innerHTML = "<strong class='text-warning'>Please scan or enter the QR token first.</strong>";
            }
            return;
        }

        submitting = true;

        try {
            if (scannerMessage) {
                scannerMessage.innerHTML = "<strong class='text-primary'>Recording attendance...</strong>";
            }
            if (preview) {
                preview.textContent = 'Submitting attendance...';
                preview.className = 'alert alert-info small';
            }

            const bodyParams = { qr_token: token };
            if (currentPosition && currentPosition.coords) {
                bodyParams.latitude = currentPosition.coords.latitude;
                bodyParams.longitude = currentPosition.coords.longitude;
            }

            const attendanceResponse = await fetch('scan_attendance.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams(bodyParams)
            });

            const result = await attendanceResponse.json();
            if (!result || typeof result !== 'object') {
                throw new Error('Attendance could not be processed. Please try again.');
            }

            const isSuccess = Boolean(result.ok) || result.type === 'success';
            const isWarning = result.type === 'warning';
            const title = isSuccess
                ? 'Attendance Recorded'
                : (isWarning ? 'Already Scanned' : 'Attendance Error');
            const alertClass = isSuccess ? 'alert-success' : (isWarning ? 'alert-warning' : 'alert-danger');
            const textClass = isSuccess ? 'text-success' : (isWarning ? 'text-warning' : 'text-danger');

            if (preview) {
                preview.textContent = result.message || title;
                preview.className = `alert ${alertClass} small`;
            }
            if (scannerMessage) {
                scannerMessage.innerHTML = `<strong class="${textClass}">${title}: ${result.message || ''}</strong>`;
            }

            if (typeof window.showCustomPopup === 'function') {
                window.showCustomPopup(
                    result.message || title,
                    result.type || (isSuccess ? 'success' : 'danger'),
                    title,
                    isSuccess ? {
                        buttonText: 'Continue',
                        secondaryButton: {
                            text: '<i class="bi bi-arrow-left"></i> Dashboard',
                            href: 'student_dashboard.php'
                        }
                    } : {}
                );
            }

            if (isSuccess) {
                try {
                    if (scanner && typeof scanner.pause === 'function') {
                        scanner.pause(true);
                    }
                    if (scannerControls) {
                        scannerControls.style.display = 'block';
                    }
                } catch (e) {
                    console.warn('Could not pause scanner:', e);
                }
            }

            submitting = false;
        } catch (error) {
            submitting = false;
            const errMsg = error.message || 'Unable to submit attendance.';
            if (scannerMessage) {
                scannerMessage.innerHTML = `<strong class='text-danger'>${errMsg}</strong>`;
            }
            if (preview) {
                preview.textContent = errMsg;
                preview.className = 'alert alert-danger small';
            }
            if (typeof window.showCustomPopup === 'function') {
                window.showCustomPopup(errMsg, 'danger', 'Attendance Error');
            }
        }
    }

    function submitQRToken(token) {
        checkAndSubmit(token);
    }

    function onScanSuccess(decodedText, decodedResult) {
        const token = (decodedText || '').trim();
        if (!token) return;

        const now = Date.now();
        if (token === lastScannedToken && (now - lastScanTimestamp) < 3000) {
            return;
        }
        lastScannedToken = token;
        lastScanTimestamp = now;

        if (scannerMessage) {
            scannerMessage.innerHTML = "<strong class='text-success'>QR code detected. Submitting attendance...</strong>";
        }

        submitQRToken(token);
    }

    function onScanFailure(error) {
        // Normal frame-by-frame scan failures are ignored.
    }

    // Expose for compatibility and manual calls
    window.__gpsSubmitQRToken = checkAndSubmit;
    window.submitQRToken = submitQRToken;

    // Handle manual submission form
    if (manualForm) {
        manualForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (manualToken) {
                checkAndSubmit(manualToken.value);
            }
        });
    }

    // Resume button
    if (resumeBtn) {
        resumeBtn.addEventListener('click', () => {
            try {
                if (scanner && typeof scanner.resume === 'function') {
                    scanner.resume();
                }
            } catch (e) {
                console.warn('Could not resume scanner:', e);
            }
            if (scannerControls) {
                scannerControls.style.display = 'none';
            }
            lastScannedToken = '';
            submitting = false;
            if (scannerMessage) {
                scannerMessage.innerHTML = 'Point your camera at the lecturer\'s QR code.';
            }
            if (preview) {
                preview.textContent = 'Ready to scan QR code or enter token.';
                preview.className = 'alert alert-info small';
            }
        });
    }

    // Optional background location warmup
    if (navigator.geolocation) {
        locate().then((position) => {
            currentPosition = position;
            updateHiddenInputs(position);
        }).catch(() => {
            // Geolocation is optional
        });
    }

    // Initialize Html5QrcodeScanner
    try {
        const qrBoxSize = Math.min(250, Math.max(180, window.innerWidth - 96));
        scanner = new Html5QrcodeScanner(
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
    } catch (err) {
        console.error('Failed to initialize QR scanner:', err);
        if (scannerMessage) {
            scannerMessage.innerHTML = "<strong class='text-danger'>Could not initialize camera scanner. Use manual token entry below.</strong>";
        }
    }
})();
</script>

</body>

</html>
