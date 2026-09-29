<?php

session_start();
include "db.php";

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

        $stmt = $conn->prepare("
            SELECT
                id,
                course_id,
                session_code,
                qr_token,
                expires_at,
                status
            FROM attendance_sessions
            WHERE qr_token = ?
              AND status = 'active'
            LIMIT 1
        ");

        $result = false;

        if (!$stmt) {

            $message =
                "Database error while checking the attendance session: "
                . $conn->error;

            $message_type = "danger";

        } else {

            $stmt->bind_param("s", $qr_token);

            if (!$stmt->execute()) {

                $message =
                    "Database error while checking the attendance session: "
                    . $stmt->error;

                $message_type = "danger";

            } else {

                $result = $stmt->get_result();

            }
        }

        if ($result === false) {

            // Database error message already prepared above.

        } elseif ($result->num_rows === 0) {

            $message = "Invalid or closed attendance session.";
            $message_type = "danger";

        } else {

            $session = $result->fetch_assoc();

            $session_id = $session['id'];
            $course_id = $session['course_id'];


            /*
            |--------------------------------------------------------------------------
            | CHECK EXPIRY
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


        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

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

</head>


<body>


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


                <form method="POST">

                    <input
                        type="text"
                        name="qr_token"
                        class="form-control mb-3"
                        placeholder="Enter QR token"
                        required
                    >


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

    const form = document.createElement("form");

    form.method = "POST";

    form.action = "scan_attendance.php";


    const input = document.createElement("input");

    input.type = "hidden";

    input.name = "qr_token";

    input.value = token;


    form.appendChild(input);

    document.body.appendChild(form);

    form.submit();

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