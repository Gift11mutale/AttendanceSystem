<?php

session_start();

include "db.php";
require_once "includes/registration_verification.php";
require_once "includes/otp_rate_limit.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = trim((string) ($_POST['fullname'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $role = (string) ($_POST['role'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');

    // Check empty fields

    if (
        empty($fullname) ||
        empty($email) ||
        empty($role) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $error = "Please fill in all fields.";

    }

    // Password confirmation

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    }

    elseif (!in_array($role, ['student', 'lecturer', 'admin'], true)) {

        $error = "Please select a valid account type.";

    }

    elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters long.";

    }

    elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    }

    else {

        // Check duplicate email

        $check = $conn->prepare("SELECT id FROM users WHERE email=?");

        $check->bind_param("s",$email);

        $check->execute();

        $result = $check->get_result();

        if($result->num_rows > 0){

            $error = "This email is already registered.";

        }

        else{

            if (!ensureRegistrationOtpTable($conn)) {
                $error = "Unable to start email verification. Please try again.";
            } else {
                try {
                    $pending = createRegistrationOtp($conn, $fullname, $email, password_hash($password, PASSWORD_DEFAULT), $role);
                    require_once 'includes/mailer.php';
                    $safeName = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
                    $html = '<div style="font-family:Arial,sans-serif;line-height:1.6"><h2>Verify your account</h2><p>Hello ' . $safeName . ',</p><p>Use this verification code to complete your Smart Attendance System registration:</p><p style="font-size:30px;font-weight:bold;letter-spacing:8px">' . $pending['otp'] . '</p><p>This code expires in 10 minutes.</p></div>';
                    $plain = "Your Smart Attendance System registration code is {$pending['otp']}. It expires in 10 minutes.";
                    sendMail($email, $fullname, 'Verify your Smart Attendance System account', $html, $plain);
                    markOtpResendSent('registration_otp_last_sent');
                    $_SESSION['registration_request_id'] = $pending['id'];
                    $_SESSION['registration_email'] = $email;
                    header('Location: verify_registration.php');
                    exit;
                } catch (Throwable $exception) {
                    error_log('Registration verification email failed: ' . $exception->getMessage());
                    $error = "We could not send the verification code. Check your email details and try again.";
                }
            }

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Create Account</title>

<link rel="icon"
href="assets/images/kmu%20logo.png">

<!-- Bootstrap -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
rel="stylesheet">

<!-- Bootstrap Icons -->

<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<!-- Google Font -->

<link
href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">

<link
rel="stylesheet"
href="assets/css/style.css">

</head>

<body>

<div class="container-fluid login-page">

<div class="row min-vh-100">

<!-- LEFT PANEL -->

<div class="col-lg-6 left-panel d-none d-lg-flex">

<div class="branding">

<img
src="assets/images/kmu%20logo.png"
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

Join the secure attendance platform.

</p>

<div class="features">

<div class="feature">

<i class="bi bi-person-plus-fill"></i>

<span>

Student Registration

</span>

</div>

<div class="feature">

<i class="bi bi-person-workspace"></i>

<span>

Lecturer Registration

</span>

</div>

<div class="feature">

<i class="bi bi-shield-check"></i>

<span>

Administrator Accounts

</span>

</div>

</div>

</div>

</div>

<!-- RIGHT PANEL -->

<div
class="col-lg-6 d-flex align-items-center justify-content-center">

<div class="login-card shadow-lg">

<div class="text-center">

<img
src="assets/images/kmu%20logo.png"
class="mobile-logo mb-3"
alt="KMU Logo">

<h2>Create Account</h2>

<p class="text-muted">

Register to access the system.

</p>

</div>

<?php if(!empty($error)){ ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-circle-fill"></i>

<?php echo $error; ?>

</div>

<?php } ?>

<?php if(!empty($message)){ ?>

<div class="alert alert-success">

<i class="bi bi-check-circle-fill"></i>

<?php echo $message; ?>

</div>

<?php } ?>

<form method="POST" id="registerForm">

<!-- Full Name -->

<div class="mb-3">

<label class="form-label fw-semibold">

Full Name

</label>

<div class="input-group">

<span class="input-group-text">

<i class="bi bi-person-fill"></i>

</span>

<input
type="text"
name="fullname"
class="form-control"
placeholder="Enter your full name"
required>

</div>

</div>

<!-- Email -->

<div class="mb-3">

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
placeholder="Enter your email"
required>

</div>

</div>

<!-- Role -->

<div class="mb-3">

<label class="form-label fw-semibold">

Account Type

</label>

<div class="input-group">

<span class="input-group-text">

<i class="bi bi-person-badge-fill"></i>

</span>

<select
name="role"
class="form-select"
required>

<option value="">Select Role</option>

<option value="student">

Student

</option>

<option value="lecturer">

Lecturer

</option>

<option value="admin">

Administrator

</option>

</select>

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
placeholder="Create Password"
required>

<button
class="btn btn-outline-secondary password-toggle"
type="button"
id="togglePassword">

<i class="bi bi-eye-fill"></i>

</button>

</div>

<div class="progress mt-2" style="height:8px;">

<div
id="passwordStrength"
class="progress-bar"
style="width:0%;">

</div>

</div>

<small
id="strengthText"
class="text-muted">

Password Strength

</small>

</div>

<!-- Confirm Password -->

<div class="mb-3">

    <label class="form-label fw-semibold">
        Confirm Password
    </label>

    <div class="input-group">

        <span class="input-group-text">
            <i class="bi bi-shield-lock-fill"></i>
        </span>

        <input
            type="password"
            id="confirmPassword"
            name="confirm_password"
            class="form-control"
            placeholder="Confirm Password"
            required>

       <button
class="btn btn-outline-secondary password-toggle"
type="button"
id="toggleConfirmPassword">

            <i class="bi bi-eye-fill"></i>

        </button>

    </div>

    <div id="passwordMatch" class="small mt-2"></div>

</div>

<!-- Terms -->

<div class="form-check mb-4">

<input
class="form-check-input"
type="checkbox"
required>

<label class="form-check-label">

I agree to the Terms & Conditions

</label>

</div>

<!-- Button -->

<button
type="submit"
id="registerButton"
class="btn btn-success login-btn w-100">

<span id="buttonText">

<i class="bi bi-person-plus-fill"></i>

Create Account

</span>

<span
id="loadingSpinner"
class="spinner-border spinner-border-sm d-none">

</span>

</button>

</form>

<div class="text-center mt-4">

<p>

Already have an account?

<a
href="login.php"
class="register-link">

Login Here

</a>

</p>

</div>

<hr>

<div class="footer text-center">

<small>

© 2026

Smart Attendance & Learning Insights System

<br><br>

Developed by

<strong>

Gift Mutale

</strong>

&amp;

<strong>

Gideon Takawila

</strong>

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

<!--<script src="assets/js/script.js"></script>-->

<script>

function setupPasswordToggle(inputId, buttonId){

    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);

    if(!input || !button) return;

    button.innerHTML = '<i class="bi bi-eye-fill"></i>';

    button.addEventListener("click", function(){

        if(input.type === "password"){

            input.type = "text";
            button.innerHTML = '<i class="bi bi-eye-slash-fill"></i>';

        }else{

            input.type = "password";
            button.innerHTML = '<i class="bi bi-eye-fill"></i>';

        }

    });

}

setupPasswordToggle("password","togglePassword");
setupPasswordToggle("confirmPassword","toggleConfirmPassword");
// Password Strength

password.addEventListener("keyup",function(){

let strength=0;

const value=password.value;

if(value.length>=8) strength++;

if(/[A-Z]/.test(value)) strength++;

if(/[0-9]/.test(value)) strength++;

if(/[^A-Za-z0-9]/.test(value)) strength++;

const bar=document.getElementById("passwordStrength");

const text=document.getElementById("strengthText");

switch(strength){

case 1:

bar.style.width="25%";

bar.className="progress-bar bg-danger";

text.innerHTML="Weak Password";

break;

case 2:

bar.style.width="50%";

bar.className="progress-bar bg-warning";

text.innerHTML="Fair Password";

break;

case 3:

bar.style.width="75%";

bar.className="progress-bar bg-info";

text.innerHTML="Good Password";

break;

case 4:

bar.style.width="100%";

bar.className="progress-bar bg-success";

text.innerHTML="Strong Password";

break;

default:

bar.style.width="0%";

text.innerHTML="Password Strength";

}

});

// Show / Hide Confirm Password



// Loading Animation

document.getElementById("registerForm").addEventListener("submit",function(){

document.getElementById("loadingSpinner").classList.remove("d-none");

document.getElementById("buttonText").innerHTML="Creating Account...";

document.getElementById("registerButton").disabled=true;

});

</script>

</body>

</html>
