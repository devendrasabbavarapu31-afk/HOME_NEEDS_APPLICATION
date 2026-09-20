<?php
require 'db.php';

// Generate a valid native hash for PHP 8.2
$newHash = password_hash('1001@', PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE logins SET password = ? WHERE username = '1001'");
$stmt->execute([$newHash]);

echo "<div style='font-family:sans-serif; padding:20px; text-align:center;'>";
echo "<h2 style='color:green;'>Password for 1001 has been reset successfully!</h2>";
echo "<p>Now click below to log in:</p>";
echo "<a href='login.php?action=reset' style='padding:10px 20px; background:#000; color:#fff; text-decoration:none; border-radius:8px;'>Go to Login</a>";
echo "</div>";
?>