<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$db = get_db();
$message = '';

// Handle form submission - Add new AWS account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $account_name = trim($_POST['account_name']);
    $owner = trim($_POST['owner']);
    $access_key_id = trim($_POST['access_key_id']);
    $secret_access_key = trim($_POST['secret_access_key']);

    try {
        $stmt = $db->prepare("INSERT INTO aws_accounts (account_name, owner, access_key_id, secret_access_key) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([$account_name, $owner, $access_key_id, $secret_access_key]);
        $message = "<div class='alert alert-success'>✅ AWS Account '$account_name' added successfully!</div>";
        
        // Trigger immediate collection for the new account
        shell_exec('/opt/venv/bin/python /usr/local/bin/collector.py >> /data/logs/collector.log 2>&1 &');
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Handle delete account
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $db->prepare("DELETE FROM aws_accounts WHERE id = ?")->execute([$id]);
        $message = "<div class='alert alert-success'>✅ Account deleted.</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>Error deleting account.</div>";
    }
}

// Fetch all accounts
$stmt = $db->query("SELECT * FROM aws_accounts ORDER BY account_name");
$accounts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage AWS Accounts - AWS EC2 Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1 class="mb-4">Manage AWS Accounts</h1>
        
        <a href="../dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>
        
        <?php echo $message; ?>

        <!-- Add New Account Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5>Add New AWS Account</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Account Name (e.g. Production, Dev, etc.)</label>
                            <input type="text" name="account_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Owner / Organization</label>
                            <input type="text" name="owner" class="form-control">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">AWS Access Key ID</label>
                            <input type="text" name="access_key_id" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">AWS Secret Access Key</label>
                            <input type="password" name="secret_access_key" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="add_account" class="btn btn-success mt-4">Add AWS Account</button>
                </form>
            </div>
        </div>

        <!-- Existing Accounts -->
        <h4>Existing AWS Accounts</h4>
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Account Name</th>
                    <th>Owner</th>
                    <th>Access Key ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $acc): ?>
                <tr>
                    <td><?= $acc['id'] ?></td>
                    <td><?= htmlspecialchars($acc['account_name']) ?></td>
                    <td><?= htmlspecialchars($acc['owner'] ?? '—') ?></td>
                    <td><?= substr(htmlspecialchars($acc['access_key_id']), 0, 8) ?>••••••••</td>
                    <td>
                        <a href="?delete=<?= $acc['id'] ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this account and all its EC2 data?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="5" class="text-center">No AWS accounts added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
