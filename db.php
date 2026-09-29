<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "smart_attendance_system";  // your database name

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>