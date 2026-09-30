<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    die("Access Denied - Students Only");
}

$student_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $course_code = trim($_POST['course_code'] ?? '');

    if ($course_code === '') {

        $message = "Please enter a course code.";
        $message_type = "danger";

    } else {

        // Find course
        $stmt = $conn->prepare("
            SELECT id, course_name, course_code
            FROM courses
            WHERE course_code = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $course_code);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {

            $message = "Course not found. Please check the course code.";
            $message_type = "danger";

        } else {

            $course = $result->fetch_assoc();

            $course_id = $course['id'];

            // Check if already enrolled
            $check = $conn->prepare("
                SELECT id
                FROM enrollments
                WHERE student_id = ?
                AND course_id = ?
                LIMIT 1
            ");

            $check->bind_param(
                "ii",
                $student_id,
                $course_id
            );

            $check->execute();

            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0) {

                $message =
                    "You are already enrolled in " .
                    htmlspecialchars($course['course_name']) .
                    ".";

                $message_type = "warning";

            } else {

                // Enroll student
                $insert = $conn->prepare("
                    INSERT INTO enrollments
                    (student_id, course_id)
                    VALUES (?, ?)
                ");

                $insert->bind_param(
                    "ii",
                    $student_id,
                    $course_id
                );

                if ($insert->execute()) {

                    $message =
                        "Successfully joined " .
                        htmlspecialchars($course['course_name']) .
                        "!";

                    $message_type = "success";

                } else {

                    $message =
                        "Error joining the course.";

                    $message_type = "danger";
                }

                $insert->close();
            }

            $check->close();
        }

        $stmt->close();
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

    <title>Join Class</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>

        body {
            background: #f4f7f6;
            font-family: Arial, sans-serif;
        }

        .page-container {
            width: 94%;
            max-width: 700px;
            margin: 40px auto;
        }

        .main-card {
            background: white;
            border-radius: 18px;
            border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .header {
            background: #0f7454;
            color: white;
            border-radius: 18px 18px 0 0;
            padding: 25px;
        }

        .header h2 {
            margin: 0;
            font-weight: bold;
        }

        .form-body {
            padding: 30px;
        }

        .btn-kmu {
            background: #0f7454;
            color: white;
            border: none;
        }

        .btn-kmu:hover {
            background: #09583f;
            color: white;
        }

        @media (max-width: 576px) {

            .page-container {
                width: 92%;
                margin: 20px auto;
            }

            .form-body {
                padding: 20px;
            }

            .header {
                padding: 20px;
            }

            .header h2 {
                font-size: 22px;
            }
        }

    </style>


<link rel="stylesheet" href="assets/css/custom-popups.css">
<script src="assets/js/custom-popups.js" defer></script></head>

<body>

<div class="page-container">

    <div class="card main-card">

        <div class="header">

            <h2>
                <i class="bi bi-book-fill"></i>
                Join Class
            </h2>

            <p class="mb-0 mt-2">
                Enter the course code provided by your lecturer.
            </p>

        </div>

        <div class="form-body">

            <?php if ($message !== ""): ?>

                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="mb-4">

                    <label class="form-label fw-bold">
                        Course Code
                    </label>

                    <input
                        type="text"
                        name="course_code"
                        class="form-control form-control-lg"
                        placeholder="Example: CSC101"
                        required
                    >

                    <div class="form-text">
                        Enter the exact course code given by your lecturer.
                    </div>

                </div>


                <button
                    type="submit"
                    class="btn btn-kmu btn-lg w-100"
                >

                    <i class="bi bi-plus-circle"></i>

                    Join Class

                </button>

            </form>


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

</body>

</html>