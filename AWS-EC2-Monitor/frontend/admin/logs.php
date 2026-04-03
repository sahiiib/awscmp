<?php
require '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Logs - AWS EC2 Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1>System Logs</h1>
        <a href="../dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>
        
        <pre style="background:#f8f9fa; padding:15px; max-height:600px; overflow:auto; border:1px solid #ddd;">
<?php
$logfile = '/data/logs/collector.log';
if (file_exists($logfile)) {
    echo htmlspecialchars(file_get_contents($logfile));
} else {
    echo "No logs yet. Run a manual refresh from the dashboard.";
}
?>
        </pre>
    </div>
</body>
</html>
