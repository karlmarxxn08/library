<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
$uid = user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['cancel'])) {
        db()->prepare("UPDATE reservations SET status='cancelled' WHERE id=? AND user_id=? AND status='waiting'")->execute([(int)$_POST['cancel'], $uid]);
        flash('Reservation cancelled.');
    }
    if (isset($_POST['renew'])) {
        $st = db()->prepare('SELECT * FROM loans WHERE id=? AND user_id=? AND returned_at IS NULL');
        $st->execute([(int)$_POST['renew'], $uid]); $l = $st->fetch();
        if (!$l) flash('Loan not found.', 'err');
        elseif ($l['renewals'] >= 2) flash('This book has reached the renewal limit.', 'err');
        elseif ($l['due_date'] < date('Y-m-d')) flash('Overdue books must be returned to the desk.', 'err');
        else {
            db()->prepare('UPDATE loans SET due_date = DATE_ADD(due_date, INTERVAL ? DAY), renewals = renewals + 1 WHERE id=?')
                ->execute([(int)setting('loan_days', '14'), $l['id']]);
            flash('Renewed.');
        }
    }
    redirect('my-loans.php');
}
$st = db()->prepare('SELECT l.*, b.title, b.author FROM loans l JOIN books b ON b.id=l.book_id WHERE l.user_id=? ORDER BY l.returned_at IS NOT NULL, l.due_date');
$st->execute([$uid]); $loans = $st->fetchAll();
$st = db()->prepare("SELECT r.*, b.title, b.copies_available FROM reservations r JOIN books b ON b.id=r.book_id WHERE r.user_id=? AND r.status='waiting'");
$st->execute([$uid]); $res = $st->fetchAll();
page_head('My loans'); ?>
<h1>My loans</h1>
<table><tr><th>Book</th><th>Issued</th><th>Due</th><th>Status</th><th>Fine</th><th></th></tr>
<?php foreach ($loans as $l):
    $late = !$l['returned_at'] && $l['due_date'] < date('Y-m-d');
    $fine = $l['returned_at'] ? $l['fine'] : fine_for($l['due_date']); ?>
<tr class="<?= $late ? 'late' : '' ?>"><td><?= e($l['title']) ?><br><span class="muted"><?= e($l['author']) ?></span></td>
<td><?= e($l['issued_at']) ?></td><td><?= e($l['due_date']) ?></td>
<td><?= $l['returned_at'] ? 'Returned ' . e($l['returned_at']) : ($late ? 'Overdue' : 'On loan') ?></td>
<td><?= $fine > 0 ? money($fine) . ($l['fine_paid'] ? ' (paid)' : '') : '-' ?></td>
<td><?php if (!$l['returned_at'] && !$late): ?><form method="post"><?= csrf_field() ?><button name="renew" value="<?= $l['id'] ?>" class="btn small">Renew</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$loans): ?><tr><td colspan="6">You have not borrowed anything yet. <a href="<?= url('index.php') ?>">Browse the catalog</a>.</td></tr><?php endif; ?></table>
<?php if ($res): ?><h2>My reservations</h2><table><?php foreach ($res as $r): ?>
<tr><td><?= e($r['title']) ?></td><td><?= $r['copies_available'] > 0 ? 'Ready: ask at the desk' : 'Waiting for a copy' ?></td>
<td><form method="post"><?= csrf_field() ?><button name="cancel" value="<?= $r['id'] ?>" class="btn small ghost">Cancel</button></form></td></tr><?php endforeach; ?></table><?php endif; ?>
<?php page_foot();
