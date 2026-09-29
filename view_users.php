<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

$result = $conn->query("SELECT id, fullname, email, role FROM users");
?>

<!DOCTYPE html>
<html>
<head>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
    <title>Manage Users</title>
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

<div class="admin-layout">

    <div class="sidebar">
        <h3>Admin Panel</h3>
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="view_users.php">Manage Users</a>
        <a href="view_all_courses.php">Manage Courses</a>
        <a href="logout.php">Logout</a>
    </div>

    <div class="content">

        <h2>Manage Users</h2>

        <table class="responsive-card-table">
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Role</th>
            </tr>

            <?php while($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['fullname']; ?></td>
                <td><?php echo $row['email']; ?></td>
                <td><?php echo ucfirst($row['role']); ?></td>
            </tr>
            <?php } ?>
        </table>

    </div>

</div>

</body>
</html>