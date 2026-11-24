<?php
require __DIR__ . '/../includes/bootstrap.php';
require_staff();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        $st = db()->prepare('SELECT COUNT(*) FROM loans WHERE book_id=?'); $st->execute([$id]);
        if ($st->fetchColumn()) flash('This book has loan history and cannot be deleted. Set its copies to 0 instead.', 'err');
        else { db()->prepare('DELETE FROM books WHERE id=?')->execute([$id]); flash('Book deleted.'); }
        redirect('admin/books.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $f = [trim($_POST['isbn']), trim($_POST['title']), trim($_POST['author']), trim($_POST['publisher']),
          $_POST['year'] !== '' ? (int)$_POST['year'] : null, $_POST['category_id'] ? (int)$_POST['category_id'] : null,
          trim($_POST['shelf']), trim($_POST['description'])];
    $copies = max(0, (int)$_POST['copies_total']);
    if ($f[1] === '' || $f[2] === '') { flash('Title and author are required.', 'err'); redirect('admin/books.php?' . ($id ? "edit=$id" : 'new=1')); }
    if ($id) {
        $old = db()->prepare('SELECT copies_total, copies_available FROM books WHERE id=?'); $old->execute([$id]); $o = $old->fetch();
        $avail = max(0, $o['copies_available'] + ($copies - $o['copies_total']));
        db()->prepare('UPDATE books SET isbn=?,title=?,author=?,publisher=?,year=?,category_id=?,shelf=?,description=?,copies_total=?,copies_available=? WHERE id=?')
            ->execute([...$f, $copies, $avail, $id]);
        flash('Book updated.');
    } else {
        db()->prepare('INSERT INTO books (isbn,title,author,publisher,year,category_id,shelf,description,copies_total,copies_available) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([...$f, $copies, $copies]);
        flash('Book added.');
    }
    redirect('admin/books.php');
}

$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$editing = null;
if (isset($_GET['edit'])) { $st = db()->prepare('SELECT * FROM books WHERE id=?'); $st->execute([(int)$_GET['edit']]); $editing = $st->fetch(); }
$showForm = isset($_GET['new']) || $editing;

page_head('Books');
if ($showForm): $b = $editing ?: ['id' => 0, 'isbn' => '', 'title' => '', 'author' => '', 'publisher' => '', 'year' => '', 'category_id' => '', 'shelf' => '', 'description' => '', 'copies_total' => 1]; ?>
<h1><?= $b['id'] ? 'Edit book' : 'Add a book' ?></h1>
<form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $b['id'] ?>">
<div class="grid2">
<label>Title<input name="title" value="<?= e($b['title']) ?>" required></label>
<label>Author<input name="author" value="<?= e($b['author']) ?>" required></label>
<label>ISBN<input name="isbn" value="<?= e($b['isbn']) ?>"></label>
<label>Publisher<input name="publisher" value="<?= e($b['publisher']) ?>"></label>
<label>Year<input type="number" name="year" value="<?= e($b['year']) ?>"></label>
<label>Category<select name="category_id"><option value="">None</option>
<?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $b['category_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
<label>Shelf location<input name="shelf" value="<?= e($b['shelf']) ?>"></label>
<label>Total copies<input type="number" min="0" name="copies_total" value="<?= e($b['copies_total']) ?>" required></label>
</div>
<label>Description<textarea name="description" rows="4"><?= e($b['description']) ?></textarea></label>
<button class="btn">Save book</button> <a href="books.php" class="btn ghost">Cancel</a></form>
<?php else:
    $q = trim($_GET['q'] ?? '');
    $st = db()->prepare("SELECT b.*, c.name AS cat FROM books b LEFT JOIN categories c ON c.id=b.category_id WHERE b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? ORDER BY b.title LIMIT 200");
    $st->execute(["%$q%", "%$q%", "%$q%"]); $rows = $st->fetchAll(); ?>
<div class="bar"><h1>Books</h1><a class="btn" href="?new=1">Add book</a></div>
<form class="search" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Search books"><button class="btn ghost">Search</button></form>
<table><tr><th>Title</th><th>Author</th><th>Category</th><th>Shelf</th><th>Copies</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['title']) ?><br><span class="muted"><?= e($r['isbn']) ?></span></td><td><?= e($r['author']) ?></td><td><?= e($r['cat']) ?></td><td><?= e($r['shelf']) ?></td>
<td><?= $r['copies_available'] ?> / <?= $r['copies_total'] ?></td>
<td class="actions"><a class="btn small ghost" href="?edit=<?= $r['id'] ?>">Edit</a>
<form method="post" onsubmit="return confirm('Delete this book?')"><?= csrf_field() ?><button class="btn small danger" name="delete" value="<?= $r['id'] ?>">Delete</button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6">No books yet. Add the first one.</td></tr><?php endif; ?></table>
<?php endif; page_foot();
