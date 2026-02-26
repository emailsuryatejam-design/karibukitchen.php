<?php
require_once 'config.php';

// If already logged in, redirect to app
if (isLoggedIn()) {
    header('Location: app.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM pilot_users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['pilot_user_id'] = $user['id'];
            $_SESSION['pilot_user_name'] = $user['name'];
            $_SESSION['pilot_user_role'] = $user['role'];
            $_SESSION['pilot_username'] = $user['username'];
            header('Location: app.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantry Planner - Pilot Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        .login-card h2 {
            color: #1a1a2e;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .login-card .subtitle {
            color: #6c757d;
            margin-bottom: 30px;
        }
        .form-control:focus {
            border-color: #0f3460;
            box-shadow: 0 0 0 0.2rem rgba(15,52,96,0.25);
        }
        .btn-primary {
            background: #0f3460;
            border-color: #0f3460;
            padding: 12px;
            font-weight: 600;
            font-size: 1.05rem;
        }
        .btn-primary:hover {
            background: #1a1a2e;
            border-color: #1a1a2e;
        }
        .badge-pilot {
            background: #e94560;
            color: #fff;
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 12px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <h2>Pantry Planner <span class="badge-pilot">PILOT</span></h2>
            <p class="subtitle">Kitchen & Store Management</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <input type="text" name="username" class="form-control form-control-lg" placeholder="Enter username" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <input type="password" name="password" class="form-control form-control-lg" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
        </form>
    </div>
    <div class="text-center mt-4">
        <small style="color:rgba(255,255,255,0.5);">Powered by <strong style="color:rgba(255,255,255,0.7);">VyomaAI Studios</strong></small>
    </div>
</body>
</html>
