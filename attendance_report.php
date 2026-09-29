<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lecturer') {
    die("Access Denied");
}

$lecturer_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<title>Attendance Report</title>
<link rel="stylesheet" href="main.css/main.css">
</head>

<body>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('table').forEach(function (table) {
            const headers = Array.from(table.querySelectorAll('th'));
            if (!headers.length) return;
            table.classList.add('responsive-card-table');
            table.querySelectorAll('tr').forEach(function (row) {
                Array.from(row.children).forEach(function (cell, index) {
                    const label = headers[index]?.textContent?.trim();
                    if (label) cell.setAttribute('data-label', label);
                });
            });
        });
    });
</script>

<div class="dashboard">

<h2>Attendance Reports</h2>

<table border="1" cellpadding="10" class="responsive-card-table">

<tr>
<th>Student Name</th>
<th>Course</th>
<th>Sessions Attended</th>
<th>Total Sessions</th>
<th>Attendance %</th>
</tr>

<?php

$query = "
SELECT 
users.fullname,
courses.course_name,

COUNT(attendance.id) AS attended,

(
SELECT COUNT(*)
FROM attendance_sessions
WHERE attendance_sessions.course_id = courses.id
) AS total_sessions

FROM users

JOIN attendance
ON attendance.student_id = users.id

JOIN attendance_sessions
ON attendance.session_id = attendance_sessions.id

JOIN courses
ON attendance_sessions.course_id = courses.id

GROUP BY users.id, courses.id
";

$result = $conn->query($query);

while($row = $result->fetch_assoc()){

$percentage = 0;

if($row['total_sessions'] > 0){
$percentage = ($row['attended'] / $row['total_sessions']) * 100;
}

?>

<tr>

<td><?php echo $row['fullname']; ?></td>

<td><?php echo $row['course_name']; ?></td>

<td><?php echo $row['attended']; ?></td>

<td><?php echo $row['total_sessions']; ?></td>

<td><?php echo round($percentage,2); ?>%</td>

</tr>

<?php } ?>

</table>

<br>

<a href="lecturer_dashboard.php">Back</a>

</div>

</body>
</html>