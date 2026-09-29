<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    die("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_id = $_SESSION['user_id'];
    $course_id = $_POST['course_id'];

    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $course_id);

    if ($stmt->execute()) {
        echo "Joined successfully!";
    } else {
        echo "Error joining course.";
    }

    $stmt->close();
}

$courses = $conn->query("SELECT * FROM courses");
?>

<h2>Join Course</h2>

<form method="POST">
<select name="course_id">
<?php
while ($row = $courses->fetch_assoc()) {
    echo "<option value='".$row['id']."'>".$row['course_name']."</option>";
}
?>
</select>
<br><br>
<button type="submit">Join</button>
</form>

<br>
<a href="student_dashboard.php">Back</a>
