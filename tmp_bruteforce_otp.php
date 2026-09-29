<?php
$conn = new mysqli('localhost','root','','smart_attendance_system');
$sql = "SELECT otp_hash FROM registration_otps WHERE id = 7 LIMIT 1";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) { echo 'NO_ROW'; exit; }
$row = $result->fetch_assoc();
$hash = $row['otp_hash'];
for ($i = 0; $i <= 999999; $i++) {
    $otp = (string) str_pad((string) $i, 6, '0', STR_PAD_LEFT);
    if (password_verify($otp, $hash)) {
        echo 'OTP=' . $otp;
        exit;
    }
}
echo 'NOT_FOUND';
