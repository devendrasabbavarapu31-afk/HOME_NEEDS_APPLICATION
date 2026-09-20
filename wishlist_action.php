<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_logged']) || !isset($_SESSION['customer_id'])) {
    echo json_encode(['status' => 'auth_required', 'message' => 'Login required to modify your wishlist.']);
    exit;
}

$customerId = (int)$_SESSION['customer_id'];
$productId  = (int)($_POST['product_id'] ?? 0);

if ($productId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product identifier.']);
    exit;
}

$checkStmt = $conn->prepare("SELECT id FROM customer_wishlist WHERE customer_id = ? AND public_product_id = ?");
$checkStmt->execute([$customerId, $productId]);
$existing = $checkStmt->fetch();

if ($existing) {
    $delStmt = $conn->prepare("DELETE FROM customer_wishlist WHERE id = ?");
    $delStmt->execute([$existing['id']]);
    $action = 'removed';
} else {
    $addStmt = $conn->prepare("INSERT INTO customer_wishlist (customer_id, public_product_id) VALUES (?, ?)");
    $addStmt->execute([$customerId, $productId]);
    $action = 'added';
}

$cntStmt = $conn->prepare("SELECT COUNT(*) FROM customer_wishlist WHERE customer_id = ?");
$cntStmt->execute([$customerId]);
$totalCount = (int)$cntStmt->fetchColumn();

echo json_encode([
    'status' => 'success',
    'action' => $action,
    'total_wishlist' => $totalCount
]);