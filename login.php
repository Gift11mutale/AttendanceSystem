<?php
session_start();
include "db.php";

$error = "";
$success = isset($_GET['reset']) && $_GET['reset'] === 'success' ? "Your password has been reset. You can now sign in." : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == "admin") {
                header("Location: admin_dashboard.php");
            }
            elseif ($user['role'] == "lecturer") {
                header("Location: lecturer_dashboard.php");
            }
            else {
                header("Location: student_dashboard.php");
            }

            exit();

        } else {
            $error = "Incorrect password.";
        }

    } else {
        $error = "Account not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Smart Attendance & Learning Insights System</title>

<link rel="icon" href="assets/images/kmu%20logo.png">

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">

<!-- Custom CSS -->
<link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

<div class="container-fluid login-page">

<div class="row min-vh-100">

<!-- LEFT PANEL -->

<div class="col-lg-6 left-panel d-none d-lg-flex">

<div class="branding">

<img src="assets/images/kmu%20logo.png"
class="logo"
alt="KMU Logo">

<h1>
Smart Attendance &
Learning Insights System
</h1>

<h4>
Kapasa Makasa University
</h4>

<p class="tagline">
Secure • Intelligent • Reliable
</p>

<div class="features">

<div class="feature">

<i class="bi bi-qr-code"></i>

<span>
QR Code Attendance
</span>

</div>

<div class="feature">

<i class="bi bi-geo-alt-fill"></i>

<span>
GPS Verification
</span>

</div>

<div class="feature">

<i class="bi bi-bar-chart-fill"></i>

<span>
Attendance Analytics
</span>

</div>

<div class="feature">

<i class="bi bi-person-check-fill"></i>

<span>
Role Based Access
</span>

</div>

</div>

</div>

</div>

<!-- RIGHT PANEL -->

<div class="col-lg-6 d-flex align-items-center justify-content-center">

<div class="login-card shadow-lg">

<div class="text-center">

<img src="assets/images/kmu%20logo.png"
class="mobile-logo mb-3"
alt="KMU Logo">

<h2>
Welcome Back
</h2>

<p class="text-muted">
Sign in to continue
</p>

</div>

<?php if(!empty($error)): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-circle-fill"></i>

<?php echo $error; ?>

</div>

<?php endif; ?>

<?php if(!empty($success)): ?>

<div class="alert alert-success">

<?php echo htmlspecialchars($success); ?>

</div>

<?php endif; ?>

<form method="POST" id="loginForm">
        <!-- Email -->

    <div class="mb-4">

        <label class="form-label fw-semibold">
            Email Address
        </label>

        <div class="input-group">

            <span class="input-group-text">
                <i class="bi bi-envelope-fill"></i>
            </span>

            <input
                type="email"
                name="email"
                class="form-control"
                placeholder="Enter your university email"
                required>

        </div>

    </div>

    <!-- Password -->

    <div class="mb-3">

        <label class="form-label fw-semibold">
            Password
        </label>

        <div class="input-group">

            <span class="input-group-text">
                <i class="bi bi-lock-fill"></i>
            </span>

            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Enter your password"
                required>

            <button
                class="btn btn-outline-secondary"
                type="button"
                id="togglePassword">

                <i class="bi bi-eye-fill"></i>

            </button>

        </div>

    </div>

    <!-- Remember Me -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div class="form-check">

            <input
                class="form-check-input"
                type="checkbox"
                id="remember">

            <label
                class="form-check-label"
                for="remember">

                Remember Me

            </label>

        </div>

        <a href="forgot_password.php"
           class="forgot-link">

            Forgot Password?

        </a>

    </div>

    <!-- Login Button -->

    <button
        class="btn btn-success w-100 login-btn"
        type="submit"
        id="loginButton">

        <span id="buttonText">

            <i class="bi bi-box-arrow-in-right"></i>

            Login

        </span>

        <span
            id="loadingSpinner"
            class="spinner-border spinner-border-sm d-none">

        </span>

    </button>

</form>

<div class="text-center mt-4">

    <p>

        Don't have an account?

        <a href="register.php"
           class="register-link">

            Register

        </a>

    </p>

</div>

<hr>

<div class="footer text-center">

<small>

© 2026 Smart Attendance & Learning Insights System

<br><br>

Developed by

<strong>Gift Mutale</strong>

&amp;

<strong>Gideon Takawila</strong>

<br>

Bachelor of ICT with Education

<br>

Kapasa Makasa University

</small>

</div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/script.js"></script>

</body>

</html>
