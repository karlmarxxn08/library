<?php
require_once __DIR__ . '/db.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

$error = '';
$pageant_title = '';
$full_name = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageant_title = trim($_POST['pageant_title'] ?? '');
    $full_name     = trim($_POST['full_name'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $password      = $_POST['password'] ?? '';

    if ($pageant_title === '' || $full_name === '' || $username === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            // Check if username is already taken
            $chk = $pdo->prepare("SELECT admin_id FROM admins WHERE username = ? LIMIT 1");
            $chk->execute([$username]);

            if ($chk->fetch()) {
                $error = 'Username is already taken. Please choose another.';
            } else {
                $pdo->beginTransaction();

                // 1. Create Organizer Account
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmtAdmin = $pdo->prepare("INSERT INTO admins (full_name, username, password) VALUES (?, ?, ?)");
                $stmtAdmin->execute([$full_name, $username, $hashed]);
                $admin_id = (int)$pdo->lastInsertId();

                // 2. Create Initial Isolated Pageant Workspace
                $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $pageant_title), '-')) . '-' . rand(1000, 9999);
                $stmtEvent = $pdo->prepare("
                    INSERT INTO pageants (admin_id, pageant_title, tagline, slug, primary_color)
                    VALUES (?, ?, 'Official Tabulation & Scoring System', ?, '#4f8ef7')
                ");
                $stmtEvent->execute([$admin_id, $pageant_title, $slug]);
                $pageant_id = (int)$pdo->lastInsertId();

                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['admin_id']      = $admin_id;
                $_SESSION['admin_user']    = $full_name;
                $_SESSION['pageant_id']    = $pageant_id;
                $_SESSION['pageant_title'] = $pageant_title;

                header('Location: admin/dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Create Pageant Account</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet"/>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: #f4f6fb; color: #0f1c3f; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
  .card { width: 100%; max-width: 440px; background: #fff; border: 1px solid #e4e8f0; border-radius: 16px; padding: 2.25rem; box-shadow: 0 10px 30px rgba(15, 28, 63, 0.05); }
  .eyebrow { font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: #4f8ef7; margin-bottom: 0.4rem; }
  h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin-bottom: 0.35rem; }
  .subtitle { font-size: 13.5px; color: #6b7a99; margin-bottom: 1.5rem; }
  .alert-error { background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; font-size: 13px; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; }
  .field { margin-bottom: 1.1rem; }
  label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 0.4rem; color: #1e293b; }
  input { width: 100%; height: 42px; padding: 0 0.9rem; border: 1px solid #e4e8f0; border-radius: 8px; font-family: inherit; font-size: 14px; color: #0f1c3f; }
  input:focus { outline: none; border-color: #4f8ef7; }
  .btn { width: 100%; height: 44px; margin-top: 0.5rem; background: #4f8ef7; color: #fff; border: none; border-radius: 8px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #3b7be3; }
  .footer-link { text-align: center; margin-top: 1.25rem; font-size: 13px; color: #6b7a99; }
  .footer-link a { color: #4f8ef7; text-decoration: none; font-weight: 500; }
</style>
</head>
<body>

<div class="card">
  <div class="eyebrow">Organizer Registration</div>
  <h1>Create Your Pageant</h1>
  <p class="subtitle">Set up your own isolated pageant workspace and CMS.</p>

  <?php if ($error !== ''): ?>
    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="field">
      <label for="pageant_title">Pageant / Event Name</label>
      <input type="text" id="pageant_title" name="pageant_title" placeholder="e.g. Miss University 2026" value="<?= htmlspecialchars($pageant_title) ?>" required>
    </div>

    <div class="field">
      <label for="full_name">Organizer Full Name</label>
      <input type="text" id="full_name" name="full_name" placeholder="Your full name" value="<?= htmlspecialchars($full_name) ?>" required>
    </div>

    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" placeholder="Choose a username" value="<?= htmlspecialchars($username) ?>" required>
    </div>

    <div class="field">
      <label for="password">Password (min. 8 characters)</label>
      <input type="password" id="password" name="password" minlength="8" required>
    </div>

    <button type="submit" class="btn">Create Workspace</button>
  </form>

  <div class="footer-link">
    Already have an account? <a href="admin/login.php">Sign in</a>
  </div>
</div>

</body>
</html>