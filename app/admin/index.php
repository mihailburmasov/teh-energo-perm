<?php
// Вход в админку. Все адреса /admin/... приходят сюда (см. public/.htaccess и app/dev-router.php).
// Без пропуска (секретного адреса) на любой адрес отвечаем обычной страницей 404 сайта.
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/content.php';
require_once __DIR__ . '/ops.php';
require_once __DIR__ . '/ui/sprite.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$prefix = '/admin';
if (!str_starts_with($uri, $prefix)) not_found();
$route = trim(substr($uri, strlen($prefix)), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $f = rtrim(cfg('out_dir'), '/\\') . '/404.html';
    echo is_file($f) ? file_get_contents($f) : 'Not found';
    exit;
}
function json_out(array $body, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_FLAGS | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function redirect(string $to): void { header('Location: ' . $to, true, 303); exit; }

// ---------- пересборка после заливки кода (app/bin/deploy.php), только по ключу из настроек сервера ----------
if ($route === 'deploy-rebuild' && $method === 'POST' && strlen((string)cfg('deploy_key')) >= 24) {
    if (!hash_equals((string)cfg('deploy_key'), (string)($_SERVER['HTTP_X_DEPLOY_KEY'] ?? ''))) not_found();
    try { $r = build_site(); json_out(['ok' => true, 'message' => "файлов {$r['pages']}, изменено {$r['changed']}"]); }
    catch (Throwable $e) { json_out(['ok' => false, 'error' => $e->getMessage()], 500); }
}

// ---------- пропуск по секретному адресу ----------
$acc = admin_account();
if ($acc && $route !== '' && hash_equals((string)$acc['secret'], $route)) {
    gate_open();
    redirect($prefix . '/');
}
if (!gate_passed()) not_found();

// ---------- статика админки ----------
if (preg_match('~^asset/(admin\.(?:css|js))$~', $route, $m)) {
    header('Content-Type: ' . (str_ends_with($m[1], '.css') ? 'text/css' : 'text/javascript') . '; charset=utf-8');
    header('Cache-Control: private, max-age=300');
    readfile(__DIR__ . '/ui/' . $m[1]);
    exit;
}

// ---------- вход и выход ----------
if ($route === 'login') {
    $error = null;
    if ($method === 'POST') {
        $error = login_attempt((string)($_POST['password'] ?? ''));
        if ($error === null) redirect($prefix . '/');
    }
    require __DIR__ . '/ui/login.php';
    exit;
}
if ($route === 'logout' && $method === 'POST') { session_end(); redirect($prefix . '/login'); }

$sess = session_current();
if (!$sess) {
    if (str_starts_with($route, 'api/')) json_out(['ok' => false, 'error' => 'Сессия истекла, войдите снова', 'relogin' => true], 401);
    redirect($prefix . '/login');
}

// ---------- вложение заявки ----------
if ($route === 'lead-file' && $method === 'GET') {
    try { $f = lead_file_path((string)($_GET['name'] ?? '')); } catch (Throwable $e) { not_found(); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . preg_replace('~^[0-9a-f]+_~', '', basename($f)) . '"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

// ---------- API ----------
if (str_starts_with($route, 'api/')) {
    if ($method === 'POST' && !hash_equals($sess['csrf'], (string)($_SERVER['HTTP_X_CSRF'] ?? ''))) json_out(['ok' => false, 'error' => 'Устаревшая страница, обновите её'], 403);
    $in = [];
    if ($method === 'POST' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    try {
        json_out(['ok' => true] + api(substr($route, 4), $method, $in));
    } catch (InvalidArgumentException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    } catch (Throwable $e) {
        error_log('admin: ' . $e);
        json_out(['ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ---------- оболочка админки (дальше всё рисует admin.js) ----------
if ($route === '' && $method === 'GET') {
    load_site_data();
    require __DIR__ . '/ui/layout.php';
    exit;
}
not_found();

function sku_arg(array $in): string
{
    $sku = (string)($in['sku'] ?? $_GET['sku'] ?? '');
    if (!preg_match('~^\d{1,15}$~', $sku) || !product_by_sku($sku)) throw new InvalidArgumentException('Товар не найден');
    return $sku;
}

function upload_guard(): void
{
    if (empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) throw new InvalidArgumentException('Файл слишком большой для хостинга — уменьшите фото');
}

function api(string $action, string $method, array $in): array
{
    load_site_data();
    $sections = admin_sections();
    switch ($method . ' ' . $action) {
        case 'GET bootstrap':
            $nav = [];
            foreach ($sections as $id => $s) $nav[] = ['id' => $id, 'title' => $s['title'], 'group' => $s['group']];
            return ['nav' => $nav, 'cats' => array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], S::$cats), 'products' => count(S::$all), 'siteUrl' => S::$c['siteUrl'], 'leads' => count(leads_list(1000)), 'trash' => count(trash_index())];

        case 'GET section':
            $id = (string)($_GET['id'] ?? '');
            if (!isset($sections[$id])) throw new InvalidArgumentException('Раздел не найден');
            $s = $sections[$id];
            return ['title' => $s['title'], 'url' => $s['url'], 'fields' => $s['fields'], 'values' => section_values($s['fields'])];

        case 'POST section':
            $id = (string)($in['id'] ?? '');
            if (!isset($sections[$id])) throw new InvalidArgumentException('Раздел не найден');
            $changes = apply_values($sections[$id]['fields'], (array)($in['values'] ?? []));
            commit_changes($changes);
            return ['message' => $changes ? 'Сохранено, сайт обновлён' : 'Изменений нет'];

        case 'GET products':
            return ['products' => products_all()];

        case 'GET product':
            $sku = sku_arg([]);
            $p = product_by_sku($sku);
            $fields = product_fields($sku);
            return ['sku' => $sku, 'title' => $p['short'], 'url' => '/catalog/' . $p['slug'] . '/', 'visible' => empty($p['hidden']) && count($p['images']) > 0, 'press' => $sku === (string)S::$press['ozonSku'], 'fields' => $fields, 'values' => section_values($fields, (string)product_index($sku))];

        case 'POST product':
            $sku = sku_arg($in);
            $changes = apply_values(product_fields($sku), (array)($in['values'] ?? []), (string)product_index($sku));
            commit_changes($changes);
            return ['message' => $changes ? 'Сохранено, сайт обновлён' : 'Изменений нет'];

        case 'POST product/create':
            return ['sku' => product_create((string)($in['short'] ?? ''), (string)($in['category'] ?? '')), 'message' => 'Товар создан. Он скрыт, пока вы не добавите фото и не снимете галочку «Скрыть с сайта».'];
        case 'POST product/delete':
            product_delete(sku_arg($in));
            return ['message' => 'Товар перемещён в корзину'];
        case 'POST products/order':
            products_reorder(array_map('strval', (array)($in['order'] ?? [])));
            return ['message' => 'Порядок сохранён'];
        case 'POST product/photo':
            upload_guard();
            $sku = sku_arg($_POST);
            return ['n' => product_photo_upload($sku, $_FILES['file'] ?? []), 'message' => 'Фото загружено. Не забудьте сохранить товар'];

        case 'POST image/upload':
            upload_guard();
            return image_upload($_FILES['file'] ?? []) + ['message' => 'Фото загружено. Не забудьте сохранить'];

        case 'GET trash':
            return ['items' => trash_index()];
        case 'POST trash/restore':
            return ['message' => trash_restore((string)($in['id'] ?? ''))];

        case 'GET revisions':
            return ['items' => revisions_list()];
        case 'POST revision/restore':
            revision_restore((string)($in['id'] ?? ''));
            return ['message' => 'Версия восстановлена, сайт обновлён'];

        case 'GET leads':
            return ['items' => leads_list()];

        case 'GET settings':
            $s = settings_get();
            if ($s['tgToken'] !== '') $s['tgToken'] = '••••••';
            return ['settings' => $s, 'secretUrl' => S::$c['siteUrl'] . '/admin/' . admin_account()['secret'], 'companyEmail' => S::$c['email']];
        case 'POST settings':
            settings_save($in);
            return ['message' => 'Настройки сохранены'];
        case 'POST settings/test-lead':
            require_once dirname(__DIR__) . '/lead-deliver.php';
            $r = lead_deliver("Проверка: так будут приходить заявки с сайта.\nВремя: " . date('d.m.Y H:i'), 'Проверка заявок с сайта');
            return ['result' => $r, 'message' => 'Проверка отправлена'];
        case 'POST settings/password':
            if (!password_verify((string)($in['current'] ?? ''), admin_account()['hash'] ?? '')) throw new InvalidArgumentException('Текущий пароль указан неверно');
            admin_set_password((string)($in['password'] ?? ''));
            return ['message' => 'Пароль изменён'];

        case 'POST rebuild':
            $r = build_site();
            return ['message' => "Сайт пересобран: файлов {$r['pages']}, изменено {$r['changed']}"];
    }
    throw new InvalidArgumentException('Неизвестное действие');
}
