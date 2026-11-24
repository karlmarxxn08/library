<?php
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pdo = db();
    try {
        if (isset($_POST['issue'])) {
            $uid = (int)$_POST['user_id']; $bid = (int)$_POST['book_id'];
            $pdo->beginTransaction();
            $u = $pdo->prepare('SELECT * FROM users WHERE id=?'); $u->execute([$uid]); $u = $u->fetch();
            if (!$u || $u['status'] !== 'active') throw new RuntimeException('This member is not active.');
            $n = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id=? AND returned_at IS NULL'); $n->execute([$uid]);
            if ($n->fetchColumn() >= (int)setting('max_loans', '5')) throw new RuntimeException('Member has reached the loan limit.');
            $d = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id=? AND book_id=? AND returned_at IS NULL'); $d->execute([$uid, $bid]);
            if ($d->fetchColumn()) throw new RuntimeException('Member already has this book.');
            $b = $pdo->prepare('SELECT * FROM books WHERE id=? FOR UPDATE'); $b->execute([$bid]); $b = $b->fetch();
            if (!$b || $b['copies_available'] < 1) throw new RuntimeException('No copies are available.');
            $due = date('Y-m-d', strtotime('+' . (int)setting('loan_days', '14') . ' days'));
            $pdo->prepare('INSERT INTO loans (book_id,user_id,issued_at,due_date) VALUES (?,?,?,?)')->execute([$bid, $uid, $today, $due]);
            $pdo->prepare('UPDATE books SET copies_available = copies_available - 1 WHERE id=?')->execute([$bid]);
            $pdo->prepare("UPDATE reservations SET status='fulfilled' WHERE book_id=? AND user_id=? AND status='waiting'")->execute([$bid, $uid]);
            $pdo->commit();
            flash("Issued \"{$b['title']}\" to {$u['name']}. Due $due.");
        } elseif (isset($_POST['return'])) {
            $pdo->beginTransaction();
            $l = $pdo->prepare('SELECT * FROM loans WHERE id=? AND returned_at IS NULL FOR UPDATE'); $l->execute([(int)$_POST['return']]); $l = $l->fetch();
            if (!$l) throw new RuntimeException('Loan not found or already returned.');
            $fine = fine_for($l['due_date'], $today);
            $pdo->prepare('UPDATE loans SET returned_at=?, fine=?, fine_paid=? WHERE id=?')->execute([$today, $fine, $fine > 0 ? 0 : 1, $l['id']]);
            $pdo->prepare('UPDATE books SET copies_available = copies_available + 1 WHERE id=?')->execute([$l['book_id']]);
            $pdo->commit();
            flash($fine > 0 ? 'Returned late. Fine to collect: ' . money($fine) : 'Book returned.');
        } elseif (isset($_POST['renew'])) {
            $l = $pdo->prepare('SELECT * FROM loans WHERE id=? AND returned_at IS NULL'); $l->execute([(int)$_POST['renew']]); $l = $l->fetch();
            if (!$l || $l['renewals'] >= 2) throw new RuntimeException('This loan cannot be renewed (limit is 2).');
            $pdo->prepare('UPDATE loans SET due_date = DATE_ADD(CURDATE(), INTERVAL ? DAY), renewals = renewals + 1 WHERE id=?')->execute([(int)setting('loan_days', '14'), $l['id']]);
            flash('Loan renewed.');
        } elseif (isset($_POST['paid'])) {
            $pdo->prepare('UPDATE loans SET fine_paid=1 WHERE id=?')->execute([(int)$_POST['paid']]);
            flash('Fine marked as paid.');
        } elseif (isset($_POST['cancel_res'])) {
            $pdo->prepare("UPDATE reservations SET status='cancelled' WHERE id=?")->execute([(int)$_POST['cancel_res']]);
            flash('Reservation cancelled.');
        } elseif (isset($_POST['delete'])) {
            $pdo->prepare('DELETE FROM loans WHERE id=? AND returned_at IS NOT NULL')->execute([(int)$_POST['delete']]);
            flash('Loan record deleted.');
        }
    } catch (RuntimeException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash($ex->getMessage(), 'err'); }
    catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); flash('Something went wrong. Nothing was saved.', 'err'); }
    redirect('admin/loans.php' . (isset($_GET['f']) ? '?f=' . urlencode($_GET['f']) : ''));
}

$f = $_GET['f'] ?? 'active';
$cond = ['active' => 'l.returned_at IS NULL', 'overdue' => 'l.returned_at IS NULL AND l.due_date < CURDATE()',
         'unpaid' => 'l.returned_at IS NOT NULL AND l.fine > 0 AND l.fine_paid = 0', 'returned' => 'l.returned_at IS NOT NULL', 'all' => '1=1'][$f] ?? 'l.returned_at IS NULL';
$loans = db()->query("SELECT l.*, b.title, u.name FROM loans l JOIN books b ON b.id=l.book_id JOIN users u ON u.id=l.user_id WHERE $cond ORDER BY l.due_date LIMIT 300")->fetchAll();
$members = db()->query("SELECT id, name, email FROM users WHERE status='active' ORDER BY name")->fetchAll();
$books = db()->query('SELECT id, title, copies_available FROM books WHERE copies_available > 0 ORDER BY title')->fetchAll();
$res = db()->query("SELECT r.*, b.title, b.copies_available, u.name FROM reservations r JOIN books b ON b.id=r.book_id JOIN users u ON u.id=r.user_id WHERE r.status='waiting' ORDER BY r.created_at")->fetchAll();

page_head('Loans'); ?>
<h1>Loans</h1>
<form method="post" class="form inline"><?= csrf_field() ?>
<select name="user_id" required><option value="">Member</option><?php foreach ($members as $m): ?><option value="<?= $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['email']) ?>)</option><?php endforeach; ?></select>
<select name="book_id" required><option value="">Book available now</option><?php foreach ($books as $b): ?><option value="<?= $b['id'] ?>"><?= e($b['title']) ?> (<?= $b['copies_available'] ?>)</option><?php endforeach; ?></select>
<button class="btn" name="issue" value="1">Issue book</button></form>

<div class="tabs"><?php foreach (['active' => 'On loan', 'overdue' => 'Overdue', 'unpaid' => 'Unpaid fines', 'returned' => 'Returned', 'all' => 'All'] as $k => $label): ?>
<a class="<?= $f === $k ? 'cur' : '' ?>" href="?f=<?= $k ?>"><?= $label ?></a><?php endforeach; ?></div>
<table><tr><th>Book</th><th>Member</th><th>Issued</th><th>Due</th><th>Returned</th><th>Fine</th><th></th></tr>
<?php foreach ($loans as $l):
    $late = !$l['returned_at'] && $l['due_date'] < $today;
    $fine = $l['returned_at'] ? (float)$l['fine'] : fine_for($l['due_date']); ?>
<tr class="<?= $late ? 'late' : '' ?>"><td><?= e($l['title']) ?></td><td><?= e($l['name']) ?></td><td><?= e($l['issued_at']) ?></td><td><?= e($l['due_date']) ?></td>
<td><?= e($l['returned_at'] ?: '-') ?></td><td><?= $fine > 0 ? money($fine) . ($l['returned_at'] ? ($l['fine_paid'] ? ' paid' : ' unpaid') : '') : '-' ?></td>
<td class="actions"><form method="post"><?= csrf_field() ?>
<?php if (!$l['returned_at']): ?><button class="btn small" name="return" value="<?= $l['id'] ?>">Return</button>
<button class="btn small ghost" name="renew" value="<?= $l['id'] ?>">Renew</button>
<?php else: if ($fine > 0 && !$l['fine_paid']): ?><button class="btn small" name="paid" value="<?= $l['id'] ?>">Mark paid</button><?php endif; ?>
<button class="btn small danger" name="delete" value="<?= $l['id'] ?>" onclick="return confirm('Delete this record?')">Delete</button><?php endif; ?></form></td></tr>
<?php endforeach; if (!$loans): ?><tr><td colspan="7">Nothing to show here.</td></tr><?php endif; ?></table>

<h2>Waiting reservations</h2>
<table><tr><th>Book</th><th>Member</th><th>Reserved</th><th>Copy ready?</th><th></th></tr>
<?php foreach ($res as $r): ?><tr><td><?= e($r['title']) ?></td><td><?= e($r['name']) ?></td><td><?= e(substr($r['created_at'], 0, 10)) ?></td><td><?= $r['copies_available'] > 0 ? 'Yes, issue it above' : 'No' ?></td>
<td><form method="post"><?= csrf_field() ?><button class="btn small ghost" name="cancel_res" value="<?= $r['id'] ?>">Cancel</button></form></td></tr>
<?php endforeach; if (!$res): ?><tr><td colspan="5">No reservations are waiting.</td></tr><?php endif; ?></table>
<?php page_foot();
