<?php
// Shared page header
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Smart Attendance & Learning Insights System</title>

<link rel="icon" type="image/png" href="assets/images/favicon.png">

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
rel="stylesheet">

<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/dashboard.css?v=3">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.responsive-card-table').forEach(function (table) {
            const headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
                return th.textContent.trim();
            });

            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.from(row.children).forEach(function (cell, index) {
                    if (!cell.hasAttribute('data-label')) {
                        cell.setAttribute('data-label', headers[index] || 'Details');
                    }
                });
            });
        });
    });
</script>
</head>

<body>
