<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = get_db();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AWS EC2 Monitor - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        .status-running { color: #198754; font-weight: bold; }
        .status-stopped { color: #dc3545; font-weight: bold; }
        .modal-body pre { background: #f8f9fa; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-cloud"></i> AWS EC2 Monitor</h1>
        <div>
            <a href="admin/accounts.php" class="btn btn-success me-2">Manage AWS Accounts</a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin/users.php" class="btn btn-warning me-2">Manage Users</a>
                <a href="admin/logs.php" class="btn btn-info me-2">View Logs</a>
            <?php endif; ?>
            <button onclick="manualRefresh()" class="btn btn-primary me-2">
                <i class="bi bi-arrow-repeat"></i> Manual Refresh
            </button>
            <a href="logout.php" class="btn btn-outline-secondary">Logout</a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
            <table id="ec2Table" class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Account</th>
                        <th>Region</th>
                        <th>Name</th>
                        <th>Instance ID</th>
                        <th>Status</th>
                        <th>Public IP</th>
                        <th>Private IP</th>
                        <th>Instance Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $stmt = $db->query("
                    SELECT e.*, a.account_name 
                    FROM ec2_instances e 
                    JOIN aws_accounts a ON e.aws_account_id = a.id 
                    ORDER BY a.account_name, e.region, e.name
                ");
                while ($row = $stmt->fetch()) {
                    $statusClass = ($row['state'] === 'running') ? 'status-running' : 'status-stopped';
                    echo "<tr data-id='{$row['id']}'>
                        <td>{$row['account_name']}</td>
                        <td>{$row['region']}</td>
                        <td>" . htmlspecialchars($row['name'] ?: '-') . "</td>
                        <td><code>{$row['instance_id']}</code></td>
                        <td><span class='{$statusClass}'>{$row['state']}</span></td>
                        <td>" . ($row['public_ip'] ?: '-') . "</td>
                        <td><code>" . ($row['private_ip'] ?: '-') . "</code></td>
                        <td>{$row['instance_type']}</td>
                        <td>
                            <button onclick='showDetails({$row['id']})' class='btn btn-sm btn-outline-primary'>
                                <i class='bi bi-info-circle'></i> Details
                            </button>
                        </td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">EC2 Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Filled by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.0.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#ec2Table').DataTable({
        pageLength: 25,
        order: [[0, 'asc'], [1, 'asc']],
        language: { search: "Search (IP, Name, ID...):" }
    });
});

function showDetails(id) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const body = document.getElementById('modalBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div><p class="mt-2">Loading details...</p></div>';
    modal.show();

    fetch('get_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                body.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }

            let html = `
                <h5>${data.name || 'No Name'} <small class="text-muted">(${data.instance_id})</small></h5>
                <table class="table table-sm">
                    <tr><th>Account</th><td>${data.account_name}</td></tr>
                    <tr><th>Region</th><td>${data.region}</td></tr>
                    <tr><th>Status</th><td><span class="${data.state === 'running' ? 'text-success' : 'text-danger'}">${data.state}</span></td></tr>
                    <tr><th>Instance Type</th><td>${data.instance_type}</td></tr>
                    <tr><th>Public IP</th><td>${data.public_ip || '-'}</td></tr>
                    <tr><th>Private IP</th><td>${data.private_ip || '-'}</td></tr>
                    <tr><th>VPC ID</th><td>${data.vpc_id || '-'}</td></tr>
                    <tr><th>Subnet ID</th><td>${data.subnet_id || '-'}</td></tr>
                    <tr><th>Launch Time</th><td>${data.launch_time || '-'}</td></tr>
                    <tr><th>IAM Role</th><td><code>${data.iam_instance_profile || 'None'}</code></td></tr>
                </table>

                <h6 class="mt-4">Security Groups</h6>
                <pre>${JSON.stringify(data.security_groups || [], null, 2)}</pre>

                <h6 class="mt-4">Tags</h6>
                <pre>${JSON.stringify(data.tags || {}, null, 2)}</pre>
            `;
            body.innerHTML = html;
        })
        .catch(err => {
            body.innerHTML = `<div class="alert alert-danger">Failed to load details: ${err.message}</div>`;
        });
}

function manualRefresh() {
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Refreshing...';

    fetch('refresh.php')
        .then(() => {
            setTimeout(() => location.reload(), 1500);   // Small delay so collector can start
        });
}
</script>
</body>
</html>