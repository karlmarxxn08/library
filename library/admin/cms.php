<?php
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete'])) { db()->prepare('DELETE FROM pages WHERE id=?')->execute([(int)$_POST['delete']]); flash('Deleted.'); redirect('admin/cms.php'); }
    $id = (int)$_POST['id']; $title = trim($_POST['title']);
    $type = $_POST['type'] === 'announcement' ? 'announcement' : 'page';
    $slug = slugify(trim($_POST['slug']) !== '' ? $_POST['slug'] : $title);
    $pub = isset($_POST['published']) ? 1 : 0;
    if ($title === '') { flash('A title is required.', 'err'); redirect('admin/cms.php?' . ($id ? "edit=$id" : 'new=1')); }
    try {
        if ($id) db()->prepare('UPDATE pages SET title=?, slug=?, body=?, type=?, published=? WHERE id=?')->execute([$title, $slug, $_POST['body'], $type, $pub, $id]);
        else db()->prepare('INSERT INTO pages (title, slug, body, type, published) VALUES (?,?,?,?,?)')->execute([$title, $slug, $_POST['body'], $type, $pub]);
        flash('Saved.');
    } catch (PDOException $ex) { flash('That URL name is already used by another page.', 'err'); redirect('admin/cms.php?' . ($id ? "edit=$id" : 'new=1')); }
    redirect('admin/cms.php');
}
$editing = null;
if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM pages WHERE id=?'); $st->execute([(int)$_GET['edit']]); $editing = $st->fetch(); }
page_head('Pages & news');
if (isset($_GET['new']) || $editing): $p = $editing ?: ['id' => 0, 'title' => '', 'slug' => '', 'body' => '', 'type' => 'page', 'published' => 1]; ?>
<h1><?= $p['id'] ? 'Edit' : 'New' ?> page or announcement</h1>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
<div class="grid2"><label>Title<input name="title" value="<?= e($p['title']) ?>" required></label>
<label>URL name (optional)<input name="slug" value="<?= e($p['slug']) ?>"></label>
<label>Type<select name="type"><option value="page">Page (shown in the menu)</option><option value="announcement" <?= $p['type'] === 'announcement' ? 'selected' : '' ?>>Announcement (shown on the catalog)</option></select></label>
<label class="check"><input type="checkbox" name="published" <?= $p['published'] ? 'checked' : '' ?>> Published</label></div>
<label>Content (HTML allowed: p, h2, h3, ul, ol, li, strong, em, a, br)<textarea name="body" rows="12"><?= e($p['body']) ?></textarea></label>
<button class="btn">Save</button> <a class="btn ghost" href="cms.php">Cancel</a></form>
<?php else: $rows = db()->query('SELECT * FROM pages ORDER BY type, title')->fetchAll(); ?>
<div class="bar"><h1>Pages &amp; news</h1><a class="btn" href="?new=1">New</a></div>
<table><tr><th>Title</th><th>Type</th><th>Status</th><th>Updated</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['title']) ?></td><td><?= e($r['type']) ?></td><td><?= $r['published'] ? 'Published' : '<span class="tag">Draft</span>' ?></td><td><?= e(substr($r['updated_at'], 0, 10)) ?></td>
<td class="actions"><a class="btn small ghost" href="?edit=<?= $r['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this?')"><?= csrf_field() ?><button class="btn small danger" name="delete" value="<?= $r['id'] ?>">Delete</button></form></td></tr>
<?php endforeach; ?></table>
<?php endif; page_foot();
