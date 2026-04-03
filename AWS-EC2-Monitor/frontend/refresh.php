<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Trigger collector with lock-aware wrapper
$cmd = '/opt/venv/bin/python /usr/local/bin/collector.py >> /data/logs/collector.log 2>&1 &';
shell_exec($cmd);

echo json_encode([
    "status" => "triggered",
    "message" => "Collection started in background. Please wait 30-90 seconds then refresh the page."
]);
?>