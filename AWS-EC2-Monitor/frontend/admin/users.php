<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$db = get_db();
$message = '';

// ======================
// 1. Add New User
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'user';

    if (strlen($password) < 6) {
        $message = "<div class='alert alert-danger'>Password must be at least 6 characters long.</div>";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            $message = "<div class='alert alert-success'>✅ User <strong>$username</strong> created successfully!</div>";
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger'>❌ Username already exists.</div>";
        }
    }
}

// ======================
// 2. Edit User (Username + Role)
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $new_username = trim($_POST['new_username']);
    $new_role = $_POST['new_role'];

    try {
        $stmt = $db->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        $stmt->execute([$new_username, $new_role, $user_id]);
        $message = "<div class='alert alert-success'>✅ User updated successfully!</div>";
    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>❌ Error updating user (username may already exist).</div>";
    }
}

// ======================
// 3. Change Password (for any user - admin only)
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];

    if (strlen($new_password) < 6) {
        $message = "<div class='alert alert-danger'>New password must be at least 6 characters.</div>";
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $user_id]);
        $message = "<div class='alert alert-success'>✅ Password changed successfully!</div>";
    }
}

// ======================
// 4. Delete User
// ======================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        $message = "<div class='alert alert-success'>✅ User deleted.</div>";
    } else {
        $message = "<div class='alert alert-danger'>You cannot delete your own account!</div>";
    }
}

// Fetch all users
$stmt = $db->query("SELECT id, username, role, created_at FROM users ORDER BY username");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - AWS EC2 Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">

    <h1 class="mb-4">Manage Users & Roles</h1>
    <a href="../dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>

    <?php if ($message) echo $message; ?>

    <!-- Add New User -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5><i class="bi bi-person-plus"></i> Add New User</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-success mt-3">Create User</button>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <h4>Existing Users</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <span class="badge <?= $u['role']=='admin' ? 'bg-danger' : 'bg-secondary' ?>">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td><?= $u['created_at'] ?></td>
                    <td>
                        <!-- Edit Button -->
                        <button class="btn btn-sm btn-warning me-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#editModal"
                                onclick="fillEditModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>', '<?= $u['role'] ?>')">
                            <i class="bi bi-pencil"></i> Edit
                        </button>

                        <!-- Change Password Button -->
                        <button class="btn btn-sm btn-info me-1" 
                                data-bs-toggle="modal" 
                                data-bs-target="#passwordModal"
                                onclick="fillPasswordModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                            <i class="bi bi-key"></i> Password
                        </button>

                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="?delete=<?= $u['id'] ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Delete this user?')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        <?php else: ?>
                            <span class="text-muted small">Current User</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="new_username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="new_role" id="edit_role" class="form-select">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Password for <span id="password_username"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="password_user_id">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="change_password" class="btn btn-success">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function fillEditModal(id, username, role) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
}

function fillPasswordModal(id, username) {
    document.getElementById('password_user_id').value = id;
    document.getElementById('password_username').textContent = username;
}
</script>
</body>
</html>
