<?php
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
$one = fn($sql) => db()->query($sql)->fetchColumn();
$stats = [
  'Titles' => $one('SELECT COUNT(*) FROM books'),
  'Copies on shelf' => (int)$one('SELECT COALESCE(SUM(copies_available),0) FROM books'),
  'Members' => $one("SELECT COUNT(*) FROM users WHERE role='member'"),
  'On loan' => $one('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL'),
  'Overdue' => $one('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL AND due_date < CURDATE()'),
  'Unpaid fines' => money($one('SELECT COALESCE(SUM(fine),0) FROM loans WHERE returned_at IS NOT NULL AND fine_paid=0')),
];
$overdue = db()->query("SELECT l.*, b.title, u.name FROM loans l JOIN books b ON b.id=l.book_id JOIN users u ON u.id=l.user_id
  WHERE l.returned_at IS NULL AND l.due_date < CURDATE() ORDER BY l.due_date LIMIT 10")->fetchAll();
$recent = db()->query("SELECT l.*, b.title, u.name FROM loans l JOIN books b ON b.id=l.book_id JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 8")->fetchAll();
page_head('Dashboard'); ?>
<h1>Dashboard</h1>
<div class="stats"><?php foreach ($stats as $k => $v): ?><div class="stat"><b><?= e($v) ?></b><span><?= e($k) ?></span></div><?php endforeach; ?></div>
<h2>Overdue now</h2>
<table><tr><th>Book</th><th>Member</th><th>Due</th><th>Fine so far</th></tr>
<?php foreach ($overdue as $l): ?><tr class="late"><td><?= e($l['title']) ?></td><td><?= e($l['name']) ?></td><td><?= e($l['due_date']) ?></td><td><?= money(fine_for($l['due_date'])) ?></td></tr>
<?php endforeach; if (!$overdue): ?><tr><td colspan="4">Nothing is overdue.</td></tr><?php endif; ?></table>
<h2>Latest loans</h2>
<table><tr><th>Book</th><th>Member</th><th>Issued</th><th>Due</th></tr>
<?php foreach ($recent as $l): ?><tr><td><?= e($l['title']) ?></td><td><?= e($l['name']) ?></td><td><?= e($l['issued_at']) ?></td><td><?= e($l['due_date']) ?></td></tr>
<?php endforeach; if (!$recent): ?><tr><td colspan="4">No loans yet. <a href="loans.php">Issue the first book</a>.</td></tr><?php endif; ?></table>
<?php page_foot();
