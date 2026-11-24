<?php
require_once __DIR__ . '/../config.php';
session_start();

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $p = ''): string { return BASE_URL . '/' . ltrim($p, '/'); }
function redirect(string $p): void { header('Location: ' . url($p)); exit; }
function csrf_field(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_staff(): bool { return in_array(user()['role'] ?? '', ['admin', 'librarian'], true); }
function require_login(): void { if (!user()) redirect('login.php'); }
function require_staff(): void { require_login(); if (!is_staff()) { http_response_code(403); exit('Staff only.'); } }
function require_admin(): void { require_login(); if (user()['role'] !== 'admin') { http_response_code(403); exit('Admins only.'); } }
function setting(string $k, string $d = ''): string {
    static $s;
    if ($s === null) $s = db()->query('SELECT k, v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $s[$k] ?? $d;
}
function fine_for(string $due, ?string $returned = null): float {
    $end = strtotime($returned ?: date('Y-m-d'));
    $days = (int)floor(($end - strtotime($due)) / 86400);
    return $days > 0 ? round($days * (float)setting('fine_per_day', '1'), 2) : 0.0;
}
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'page';
}
function clean_html(string $h): string { return strip_tags($h, '<p><h2><h3><ul><ol><li><strong><em><a><br><blockquote>'); }
function money($n): string { return number_format((float)$n, 2); }

require_once __DIR__ . '/layout.php';
