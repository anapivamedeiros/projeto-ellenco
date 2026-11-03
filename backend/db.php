<?php
require_once __DIR__ . '/config.php';
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO('pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';sslmode=' . DB_SSLMODE, DB_USER, DB_PASS, $opts);
    }
    return $pdo;
}
function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
        session_start();
    }
}
function app_path(string $path): string {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = preg_replace('~/backend$~', '', $dir);
    if ($base === '.' || $base === '/') $base = '';
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function valid_csrf(?string $token): bool {
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
function require_login(): array {
    start_session();
    if (empty($_SESSION['user'])) { header('Location: ' . app_path('login.html')); exit; }
    return $_SESSION['user'];
}function is_admin(array $user): bool {
    return ($user['tipo_acesso'] ?? '') === 'admin';
}
function require_admin(array $user): void {
    if (!is_admin($user)) {
        http_response_code(403);
        exit('Acesso permitido somente para administradoras.');
    }
}
function sector_label(string $sector): string {
    return ['engenharia'=>'Engenharia','administrativo'=>'Administrativo','obras'=>'Obras'][$sector] ?? 'Setor';
}