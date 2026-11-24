<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([trim($_POST['email'])]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'], $u['password'])) {
        if ($u['status'] !== 'active') { flash('This account is suspended. Ask the library desk.', 'err'); redirect('login.php'); }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
        redirect(in_array($u['role'], ['admin', 'librarian']) ? 'admin/index.php' : 'index.php');
    }
    flash('Email or password is incorrect.', 'err');
}
page_head('Sign in'); ?>
<h1>Sign in</h1>
<form method="post" class="form narrow"><?= csrf_field() ?>
<label>Email<input type="email" name="email" required autofocus></label>
<label>Password<input type="password" name="password" required></label>
<button class="btn">Sign in</button>
<p class="muted">No account? <a href="<?= url('register.php') ?>">Join the library</a></p></form>
<?php page_foot();
