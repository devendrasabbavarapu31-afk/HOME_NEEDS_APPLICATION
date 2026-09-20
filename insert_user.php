<?php
require 'db.php';

$username = '1001';
$plainPassword = '1001@';
$role = 'counter';

$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("
        INSERT INTO logins (username, password, role) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            password = VALUES(password), 
            role = VALUES(role)
    ");
    $stmt->execute([$username, $hashedPassword, $role]);

    echo "✅ Counter user <strong>{$username}</strong> created/updated successfully with password <strong>{$plainPassword}</strong>.";
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage();
}
?>