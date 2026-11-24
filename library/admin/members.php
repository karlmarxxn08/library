<?php
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
$admin = user()['role'] === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        if ($id === user()['id']) flash('You cannot delete your own account.', 'err');
        else {
            try { db()->prepare("DELETE FROM users WHERE id=? AND (role='member' OR ?)")->execute([$id, $admin ? 1 : 0]); flash('Account deleted.'); }
            catch (PDOException $ex) { flash('This account has loan history. Suspend it instead.', 'err'); }
        }
        redirect('admin/members.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name']); $email = trim($_POST['email']); $pw = $_POST['password'] ?? '';
    $status = $_POST['status'] === 'suspended' ? 'suspended' : 'active';
    $role = $admin && in_array($_POST['role'], ['admin', 'librarian', 'member']) ? $_POST['role'] : 'member';
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || (!$id && strlen($pw) < 8) || ($pw !== '' && strlen($pw) < 8)) {
        flash('Name, a valid email and a password of 8+ characters are required.', 'err'); redirect('admin/members.php?' . ($id ? "edit=$id" : 'new=1'));
    }
    try {
        if ($id) {
            $cur = db()->prepare('SELECT role FROM users WHERE id=?'); $cur->execute([$id]); $c = $cur->fetch();
            if (!$admin && $c && $c['role'] !== 'member') { flash('Only an admin can edit staff accounts.', 'err'); redirect('admin/members.php'); }
            if (!$admin) $role = $c['role'];
            db()->prepare('UPDATE users SET name=?,email=?,phone=?,address=?,status=?,role=? WHERE id=?')->execute([$name, $email, trim($_POST['phone']), trim($_POST['address']), $status, $role, $id]);
            if ($pw !== '') db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
            flash('Member updated.');
        } else {
            db()->prepare('INSERT INTO users (name,email,password,phone,address,status,role) VALUES (?,?,?,?,?,?,?)')
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), trim($_POST['phone']), trim($_POST['address']), $status, $role]);
            flash('Member added.');
        }
    } catch (PDOException $ex) { flash('That email is already used.', 'err'); }
    redirect('admin/members.php');
}

$editing = null;
if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([(int)$_GET['edit']]); $editing = $st->fetch(); }
page_head('Members');
if (isset($_GET['new']) || $editing): $m = $editing ?: ['id' => 0, 'name' => '', 'email' => '', 'phone' => '', 'address' => '', 'status' => 'active', 'role' => 'member']; ?>
<h1><?= $m['id'] ? 'Edit member' : 'Add a member' ?></h1>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $m['id'] ?>">
<div class="grid2">
<label>Full name<input name="name" value="<?= e($m['name']) ?>" required></label>
<label>Email<input type="email" name="email" value="<?= e($m['email']) ?>" required></label>
<label>Phone<input name="phone" value="<?= e($m['phone']) ?>"></label>
<label>Address<input name="address" value="<?= e($m['address']) ?>"></label>
<label>Status<select name="status"><option value="active">Active</option><option value="suspended" <?= $m['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option></select></label>
<?php if ($admin): ?><label>Role<select name="role"><?php foreach (['member', 'librarian', 'admin'] as $r): ?><option <?= $m['role'] === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></label><?php endif; ?>
<label><?= $m['id'] ? 'New password (leave blank to keep)' : 'Password (8+ characters)' ?><input type="password" name="password" autocomplete="new-password"></label>
</div>
<button class="btn">Save member</button> <a class="btn ghost" href="members.php">Cancel</a></form>
<?php else:
    $q = trim($_GET['q'] ?? '');
    $st = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM loans l WHERE l.user_id=u.id AND l.returned_at IS NULL) AS active FROM users u WHERE name LIKE ? OR email LIKE ? ORDER BY name LIMIT 200");
    $st->execute(["%$q%", "%$q%"]); $rows = $st->fetchAll(); ?>
<div class="bar"><h1>Members</h1><a class="btn" href="?new=1">Add member</a></div>
<form class="search" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Search name or email"><button class="btn ghost">Search</button></form>
<table><tr><th>Name</th><th>Contact</th><th>Role</th><th>Status</th><th>On loan</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?><br><span class="muted"><?= e($r['phone']) ?></span></td><td><?= e($r['role']) ?></td>
<td><?= $r['status'] === 'suspended' ? '<span class="tag">Suspended</span>' : 'Active' ?></td><td><?= $r['active'] ?></td>
<td class="actions"><a class="btn small ghost" href="?edit=<?= $r['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this account?')"><?= csrf_field() ?><button class="btn small danger" name="delete" value="<?= $r['id'] ?>">Delete</button></form></td></tr>
<?php endforeach; ?></table>
<?php endif; page_foot();
