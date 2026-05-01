<?php
date_default_timezone_set('Asia/Kolkata'); // IST — add this line
if (session_status() === PHP_SESSION_NONE) session_start();

// ─── Site Settings ──────────────────────────────────────
define('SITE_NAME',    'AuraAi');
define('SITE_URL',     'https://superdevs.co.in/test');

// ─── Mail Settings (change these) ───────────────────────
define('SMTP_HOST',    '');
define('SMTP_USER',    '');   // ← your Gmail
define('SMTP_PASS',    '');     // ← Gmail App Password
define('SMTP_PORT',    );
define('MAIL_FROM',    '');
define('MAIL_NAME',    '');

// ─── Admin Credentials ───────────────────────────────────
define('ADMIN_EMAIL',  'admin@admin.com');
define('ADMIN_PASS',   'admin123');

// ─── Paths ───────────────────────────────────────────────
define('DATA_DIR',     __DIR__ . '/data/');
define('PHOTOS_DIR',   __DIR__ . '/data/photos/');

foreach ([DATA_DIR, PHOTOS_DIR] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0755, true);
}

// ─── JSON Helpers ────────────────────────────────────────
function rj(string $file): array {
    $path = DATA_DIR . $file;
    if (!file_exists($path)) { file_put_contents($path, '[]'); return []; }
    return json_decode(file_get_contents($path), true) ?? [];
}

function wj(string $file, array $data): void {
    file_put_contents(DATA_DIR . $file, json_encode($data, JSON_PRETTY_PRINT));
}

function find_one(string $file, string $key, mixed $val): ?array {
    foreach (rj($file) as $item) {
        if (($item[$key] ?? null) === $val) return $item;
    }
    return null;
}

function find_many(string $file, string $key, mixed $val): array {
    return array_values(array_filter(rj($file), fn($i) => ($i[$key] ?? null) === $val));
}

function add_item(string $file, array $item): void {
    $data = rj($file); $data[] = $item; wj($file, $data);
}

function update_item(string $file, string $id_key, mixed $id_val, array $updates): void {
    $data = rj($file);
    foreach ($data as &$item) {
        if (($item[$id_key] ?? null) === $id_val) { $item = array_merge($item, $updates); break; }
    }
    wj($file, $data);
}

function delete_item(string $file, string $id_key, mixed $id_val): void {
    wj($file, array_values(array_filter(rj($file), fn($i) => ($i[$id_key] ?? null) !== $id_val)));
}

function gen_id(string $prefix = 'ID'): string {
    return $prefix . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

// ─── Auth Helpers ────────────────────────────────────────
function is_logged(): bool { return !empty($_SESSION['uid']); }
function sess(): array { return $_SESSION ?? []; }

function require_role(string $role): void {
    if (!is_logged() || ($_SESSION['role'] ?? '') !== $role) {
        header('Location: ../index.php'); exit;
    }
}

function json_resp(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
