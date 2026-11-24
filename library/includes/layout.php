<?php
function page_head(string $title): void {
    $u = user();
    $lib = setting('library_name', 'Library');
    $pages = db()->query("SELECT slug, title FROM pages WHERE type='page' AND published=1 ORDER BY title")->fetchAll();
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> | <?= e($lib) ?></title>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>"></head><body>
<header class="top"><a class="brand" href="<?= url('index.php') ?>"><?= e($lib) ?></a>
<nav>
  <a href="<?= url('index.php') ?>">Catalog</a>
  <?php foreach ($pages as $p): ?><a href="<?= url('page.php?slug=' . urlencode($p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach; ?>
  <?php if ($u): ?>
    <a href="<?= url('my-loans.php') ?>">My loans</a>
    <a href="<?= url('logout.php') ?>">Sign out (<?= e($u['name']) ?>)</a>
  <?php else: ?>
    <a href="<?= url('login.php') ?>">Sign in</a><a href="<?= url('register.php') ?>">Join</a>
  <?php endif; ?>
</nav></header>
<?php if (is_staff()): ?>
<div class="staff"><a href="<?= url('admin/index.php') ?>">Dashboard</a><a href="<?= url('admin/books.php') ?>">Books</a>
<a href="<?= url('admin/categories.php') ?>">Categories</a><a href="<?= url('admin/members.php') ?>">Members</a>
<a href="<?= url('admin/loans.php') ?>">Loans</a><a href="<?= url('admin/cms.php') ?>">Pages &amp; news</a>
<?php if (user()['role'] === 'admin'): ?><a href="<?= url('admin/settings.php') ?>">Settings</a><?php endif; ?></div>
<?php endif; ?>
<main>
<?php foreach ($_SESSION['flash'] ?? [] as [$t, $m]): ?><div class="flash <?= $t === 'err' ? 'err' : 'ok' ?>"><?= e($m) ?></div><?php endforeach; unset($_SESSION['flash']); ?>
<?php }

function page_foot(): void { ?>
</main><footer>&copy; <?= date('Y') ?> <?= e(setting('library_name', 'Library')) ?></footer></body></html>
<?php }
