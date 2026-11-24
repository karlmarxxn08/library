<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve'])) {
    csrf_check(); require_login();
    $bid = (int)$_POST['book_id']; $uid = user()['id'];
    $st = db()->prepare("SELECT COUNT(*) FROM reservations WHERE book_id=? AND user_id=? AND status='waiting'");
    $st->execute([$bid, $uid]);
    if ($st->fetchColumn()) flash('You already reserved this book.', 'err');
    else {
        db()->prepare('INSERT INTO reservations (book_id,user_id) VALUES (?,?)')->execute([$bid, $uid]);
        flash('Reserved. The desk will hold it for you when it is returned.');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

$q = trim($_GET['q'] ?? ''); $cat = (int)($_GET['cat'] ?? 0);
$pg = max(1, (int)($_GET['p'] ?? 1)); $per = 12;
$where = ['1=1']; $args = [];
if ($q !== '') { $where[] = '(b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
if ($cat) { $where[] = 'b.category_id = ?'; $args[] = $cat; }
$w = implode(' AND ', $where);
$cnt = db()->prepare("SELECT COUNT(*) FROM books b WHERE $w"); $cnt->execute($args);
$total = (int)$cnt->fetchColumn(); $pages = max(1, (int)ceil($total / $per)); $pg = min($pg, $pages);
$st = db()->prepare("SELECT b.*, c.name AS cat FROM books b LEFT JOIN categories c ON c.id=b.category_id WHERE $w ORDER BY b.title LIMIT $per OFFSET " . ($pg - 1) * $per);
$st->execute($args); $books = $st->fetchAll();
$cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
$news = db()->query("SELECT * FROM pages WHERE type='announcement' AND published=1 ORDER BY created_at DESC LIMIT 3")->fetchAll();

page_head('Catalog'); ?>
<?php foreach ($news as $n): ?><aside class="notice"><strong><?= e($n['title']) ?></strong><?= clean_html($n['body']) ?></aside><?php endforeach; ?>
<h1>Find a book</h1>
<form class="search" method="get">
  <input name="q" value="<?= e($q) ?>" placeholder="Title, author or ISBN">
  <select name="cat"><option value="0">All categories</option>
    <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
  <button class="btn">Search</button>
</form>
<p class="muted"><?= $total ?> title<?= $total == 1 ? '' : 's' ?> found</p>
<?php if (!$books): ?><p>No books match. Try a shorter search or another category.</p><?php endif; ?>
<ul class="shelf">
<?php foreach ($books as $b): $in = $b['copies_available'] > 0; ?>
  <li class="<?= $in ? 'in' : 'out' ?>">
    <div><h3><?= e($b['title']) ?></h3>
      <p><?= e($b['author']) ?><?= $b['year'] ? ', ' . e($b['year']) : '' ?><?= $b['cat'] ? ' · ' . e($b['cat']) : '' ?><?= $b['shelf'] ? ' · Shelf ' . e($b['shelf']) : '' ?></p>
      <?php if ($b['description']): ?><p class="muted"><?= e(mb_strimwidth($b['description'], 0, 160, '...')) ?></p><?php endif; ?></div>
    <div class="status"><b><?= $in ? $b['copies_available'] . ' of ' . $b['copies_total'] . ' available' : 'All copies out' ?></b>
      <?php if (!$in && user()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="book_id" value="<?= $b['id'] ?>"><button name="reserve" class="btn small">Reserve</button></form>
      <?php elseif (!$in): ?><a href="<?= url('login.php') ?>">Sign in to reserve</a><?php endif; ?></div>
  </li>
<?php endforeach; ?>
</ul>
<?php if ($pages > 1): ?><div class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?>
  <a class="<?= $i == $pg ? 'cur' : '' ?>" href="?<?= e(http_build_query(['q' => $q, 'cat' => $cat, 'p' => $i])) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
<?php page_foot();
