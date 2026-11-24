<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $vals = ['library_name' => trim($_POST['library_name']) ?: 'Library', 'loan_days' => max(1, (int)$_POST['loan_days']),
             'fine_per_day' => max(0, (float)$_POST['fine_per_day']), 'max_loans' => max(1, (int)$_POST['max_loans'])];
    foreach ($vals as $k => $v) db()->prepare('REPLACE INTO settings (k, v) VALUES (?, ?)')->execute([$k, (string)$v]);
    flash('Settings saved.'); redirect('admin/settings.php');
}
page_head('Settings'); ?>
<h1>Settings</h1>
<form method="post" class="form narrow"><?= csrf_field() ?>
<label>Library name<input name="library_name" value="<?= e(setting('library_name')) ?>" required></label>
<label>Loan period (days)<input type="number" min="1" name="loan_days" value="<?= e(setting('loan_days', '14')) ?>"></label>
<label>Fine per overdue day<input type="number" step="0.01" min="0" name="fine_per_day" value="<?= e(setting('fine_per_day', '1')) ?>"></label>
<label>Maximum books per member<input type="number" min="1" name="max_loans" value="<?= e(setting('max_loans', '5')) ?>"></label>
<button class="btn">Save settings</button></form>
<?php page_foot();
