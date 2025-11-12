<?php
// check_status.php
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$ref = $_GET['ref'] ?? '';
$stmt = $pdo->prepare("SELECT status FROM payments WHERE reference=?");
$stmt->execute([$ref]);
$row = $stmt->fetch();

echo json_encode(['status' => $row['status'] ?? 'PENDING']);
?>