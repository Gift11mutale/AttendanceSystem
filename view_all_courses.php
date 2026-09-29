<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

$result = $conn->query("SELECT * FROM courses");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Courses</title>
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
<h2 style="color:white;">Manage Courses</h2>

<div class="management-card">

<table border="1" width="100%" cellpadding="8" class="responsive-card-table">
<tr>
    <th>ID</th>
    <th>Course Name</th>
    <th>Course Code</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo $row['course_name']; ?></td>
    <td><?php echo $row['course_code']; ?></td>
</tr>
<?php } ?>

</table>

<br>
<a href="admin_dashboard.php">Back to Dashboard</a>

</div>
</div>

</body>
</html>