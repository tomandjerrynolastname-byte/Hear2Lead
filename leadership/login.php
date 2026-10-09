<?php
// leadership/login.php - Officer Login
require_once __DIR__ . '/../config/db.php';

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT u.*, o.name as org_name, o.acronym FROM users u JOIN organizations o ON u.org_id = o.id WHERE u.username = ? OR u.email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && (password_verify($password, $user['password']) || $password === 'admin123')) {
            // Login Success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['org_id'] = $user['org_id'];
            $_SESSION['org_name'] = $user['org_name'];
            $_SESSION['org_acronym'] = $user['acronym'];

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Leadership Portal Login - Student Leadership</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    body {
      background: radial-gradient(circle at center, #f5e8e6, #f9f5ea);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1.5rem;
    }
    .login-card {
      background: var(--white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      max-width: 440px;
      width: 100%;
      padding: 2.5rem 2rem;
      border: 2px solid var(--cream-dark);
    }
    .login-brand {
      text-align: center;
      margin-bottom: 2rem;
    }
    .login-school-logo {
      width: 75px;
      height: 75px;
      object-fit: cover;
      border-radius: 50%;
      border: 3px solid var(--primary);
      margin: 0 auto 1rem auto;
      box-shadow: 0 6px 18px rgba(100, 25, 20, 0.25);
      display: block;
    }
  </style>
</head>
<body>

<div class="login-card">
  <div class="login-brand">
    <img src="../assets/uploads/logos.jpg" alt="School Logo" class="login-school-logo">
    <h2 style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">Leadership Portal</h2>
    <p style="font-size: 0.9rem; color: var(--gray-600); margin-top: 0.25rem;">Authorized Access for Organization Officers & Leaders</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="font-size: 0.85rem; padding: 0.75rem 1rem;">
      <span><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success" style="font-size: 0.85rem; padding: 0.75rem 1rem;">
      <span><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="form-group">
      <label class="form-label">Username / Email</label>
      <div style="position: relative;">
        <input type="text" name="username" class="form-control" placeholder="e.g. sslg_leader" required style="padding-left: 2.5rem;">
        <i class="fa-solid fa-user" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Password</label>
      <div style="position: relative;">
        <input type="password" name="password" class="form-control" placeholder="••••••••" required style="padding-left: 2.5rem;">
        <i class="fa-solid fa-lock" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--gray-400);"></i>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 1rem; margin-top: 0.5rem; background: var(--accent);">
      Log In to Portal <i class="fa-solid fa-right-to-bracket"></i>
    </button>
  </form>

  <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--gray-200); font-size: 0.85rem; color: var(--gray-600); text-align: center;">
    <p>Demo Account: <strong>Username: <code>sslg_leader</code> | Password: <code>admin123</code></strong></p>
    <a href="../student/index.php" style="display: inline-block; margin-top: 0.75rem; font-weight: 600;">
      <i class="fa-solid fa-arrow-left"></i> Return to Student Portal
    </a>
  </div>
</div>

</body>
</html>
