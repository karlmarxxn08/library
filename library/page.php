<?php
require __DIR__ . '/includes/bootstrap.php';
$st = db()->prepare("SELECT * FROM pages WHERE slug = ? AND published = 1");
$st->execute([$_GET['slug'] ?? '']);
$p = $st->fetch();
if (!$p) { http_response_code(404); page_head('Not found'); echo '<h1>Page not found</h1>'; page_foot(); exit; }
page_head($p['title']); ?>
<article class="prose"><h1><?= e($p['title']) ?></h1><?= clean_html($p['body']) ?></article>
<?php page_foot();
