<?php require 'config.php'; ?>
<!DOCTYPE html>
<html>
<head><title>Setup</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="p-5">
<h1>🚀 One-time Setup</h1>
<?php
$db = get_db();
$db->exec(file_get_contents('../database/schema.sql'));

$hash = password_hash('admin123', PASSWORD_DEFAULT);
$db->exec("INSERT OR IGNORE INTO users (username, password_hash, role) VALUES ('admin', '$hash', 'admin')");

echo "<div class='alert alert-success'>✅ Database + default admin created!<br><strong>Username:</strong> admin<br><strong>Password:</strong> admin123<br><a href='login.php' class='btn btn-primary mt-3'>Go to Login</a></div>";
?>
</body>
</html>
