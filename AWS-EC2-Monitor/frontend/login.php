<?php require 'config.php';
if ($_POST) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['password'], $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit;
    }
    $error = "Invalid credentials";
}
?>
<!DOCTYPE html>
<html><head><title>Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="d-flex align-items-center justify-content-center vh-100 bg-light">
<div class="card p-4" style="width: 380px;">
    <h2 class="text-center">AWS EC2 Monitor</h2>
    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
    <form method="post">
        <input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
        <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
        <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
</div>
</body></html>
