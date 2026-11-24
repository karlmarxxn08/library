<?php
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (isset($_POST['delete'])) { db()->prepare('DELETE FROM categories WHERE id=?')->execute([(int)$_POST['delete']]); flash('Category deleted. Its books are now uncategorised.'); }
        elseif (trim($_POST['name']) === '') flash('Category name is required.', 'err');
        elseif ((int)$_POST['id']) { db()->prepare('UPDATE categories SET name=?, description=? WHERE id=?')->execute([trim($_POST['name']), trim($_POST['description']), (int)$_POST['id']]); flash('Category updated.'); }
        else { db()->prepare('INSERT INTO categories (name, description) VALUES (?,?)')->execute([trim($_POST['name']), trim($_POST['description'])]); flash('Category added.'); }
    } catch (PDOException $ex) { flash('A category with that name already exists.', 'err'); }
    redirect('admin/categories.php');
}
$edit = null;
if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM categories WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit = $st->fetch(); }
$rows = db()->query('SELECT c.*, (SELECT COUNT(*) FROM books b WHERE b.category_id=c.id) AS n FROM categories c ORDER BY name')->fetchAll();
page_head('Categories'); ?>
<h1>Categories</h1>
<form method="post" class="form inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
<input name="name" placeholder="Name" value="<?= e($edit['name'] ?? '') ?>" required>
<input name="description" placeholder="Description (optional)" value="<?= e($edit['description'] ?? '') ?>">
<button class="btn"><?= $edit ? 'Save category' : 'Add category' ?></button><?php if ($edit): ?> <a class="btn ghost" href="categories.php">Cancel</a><?php endif; ?></form>
<table><tr><th>Name</th><th>Description</th><th>Books</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= e($r['description']) ?></td><td><?= $r['n'] ?></td>
<td class="actions"><a class="btn small ghost" href="?edit=<?= $r['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this category?')"><?= csrf_field() ?><button class="btn small danger" name="delete" value="<?= $r['id'] ?>">Delete</button></form></td></tr>
<?php endforeach; ?></table>
<?php page_foot();
