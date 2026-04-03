<?php
require 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

$id = (int)$_GET['id'];
$db = get_db();

$stmt = $db->prepare("
    SELECT e.*, a.account_name 
    FROM ec2_instances e 
    JOIN aws_accounts a ON e.aws_account_id = a.id 
    WHERE e.id = ?
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['error' => 'Instance not found']);
    exit;
}

// Decode JSON fields
$row['tags'] = json_decode($row['tags'], true);
$row['security_groups'] = json_decode($row['security_groups'], true);

echo json_encode($row);
?>