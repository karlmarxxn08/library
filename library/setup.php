<?php
// Run once to create the first admin account, then DELETE this file.
require __DIR__ . '/includes/bootstrap.php';
if ((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) { exit('Setup already done. Delete setup.php.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name']); $email = trim($_POST['email']); $pw = $_POST['password'];
    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($pw) >= 8) {
        db()->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'admin')")
            ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
        flash('Admin created. Sign in, then delete setup.php.');
        redirect('login.php');
    }
    flash('Enter a name, a valid email and a password of at least 8 characters.', 'err');
}
page_head('Setup'); ?>
<h1>Create the admin account</h1>
<form method="post" class="form narrow"><?= csrf_field() ?>
<label>Name<input name="name" required></label>
<label>Email<input type="email" name="email" required></label>
<label>Password (8+ characters)<input type="password" name="password" minlength="8" required></label>
<button class="btn">Create admin</button></form>
<?php page_foot();
