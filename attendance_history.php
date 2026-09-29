<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    die("Access Denied");
}

$student_id = $_SESSION['user_id'];

$sql = "
SELECT
c.course_name,
c.course_code,
s.session_date,
a.created_at
FROM attendance a

JOIN attendance_sessions s
ON a.session_id = s.id

JOIN courses c
ON s.course_id = c.id

WHERE a.student_id = ?

ORDER BY s.session_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i",$student_id);
$stmt->execute();

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>

<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">

<title>Attendance History</title>

<link rel="stylesheet" href="main.css/main.css">

<style>

table{

width:100%;
border-collapse:collapse;
margin-top:20px;
background:white;

}

table th,table td{

padding:12px;
border:1px solid #ddd;
text-align:center;

}

table th{

background:#009688;
color:white;

}

</style>

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

<h2>My Attendance History</h2>

<p>
Welcome,
<strong><?php echo $_SESSION['fullname']; ?></strong>
</p>

<table class="responsive-card-table">

<tr>

<th>Course</th>
<th>Course Code</th>
<th>Session Date</th>
<th>Recorded On</th>
<th>Status</th>

</tr>

<?php

if($result->num_rows>0){

while($row=$result->fetch_assoc()){

?>

<tr>

<td><?php echo $row['course_name']; ?></td>

<td><?php echo $row['course_code']; ?></td>

<td><?php echo $row['session_date']; ?></td>

<td><?php echo $row['created_at']; ?></td>

<td style="color:green;font-weight:bold;">
Present
</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="5">
No attendance records found.
</td>

</tr>

<?php

}

?>

</table>

<br>

<a href="student_dashboard.php">
Back to Dashboard
</a>

</div>

</body>

</html>