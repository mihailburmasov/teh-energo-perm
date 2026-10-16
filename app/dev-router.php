<?php
// Локальный сервер для разработки: php -S 127.0.0.1:8090 app/dev-router.php
// Отдаёт собранный сайт из out_dir, адреса /admin/... — админке, /send.php — приёму заявок.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$rel = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (preg_match('~^/admin(/|$)~', $rel)) { require __DIR__ . '/admin/index.php'; return true; }
if ($rel === '/send.php') { require __DIR__ . '/lead.php'; return true; }

$out = realpath(cfg('out_dir'));
$file = realpath($out . $rel);
if ($file && is_dir($file)) $file = realpath($file . '/index.html');
if (!$file || !str_starts_with($file, $out) || !is_file($file) || str_ends_with($file, '.php')) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    readfile($out . '/404.html');
    return true;
}
$types = ['html' => 'text/html; charset=utf-8', 'css' => 'text/css', 'js' => 'text/javascript', 'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'xml' => 'application/xml', 'txt' => 'text/plain; charset=utf-8', 'mp4' => 'video/mp4'];
header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Cache-Control: no-cache');
readfile($file);
return true;
