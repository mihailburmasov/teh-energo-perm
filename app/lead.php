<?php
// Приём заявок с форм сайта (адрес /send.php). Проверка, запись в журнал (storage/leads) вместе с вложением,
// затем письмо на почту и сообщение в Telegram. Заявка видна в админке, даже если почта не сработала.
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin/ops.php';
require_once __DIR__ . '/lead-deliver.php';

const LEAD_MAX_FILE = 10 * 1024 * 1024;
const LEAD_FILE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dxf', 'dwg', 'cdr', 'ai', 'svg'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function lead_reply(int $code, bool $ok, string $error = ''): void
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'error' => $error], JSON_FLAGS);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') lead_reply(405, false, 'method');
if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) lead_reply(400, false, 'Файл слишком большой — до 10 МБ');

$field = fn(string $k, int $max) => mb_substr(trim(strip_tags(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)($_POST[$k] ?? '')))), 0, $max);

// бот заполнил скрытое поле — делаем вид, что всё хорошо
if ($field('website', 200) !== '') lead_reply(200, true);
if (($_POST['consent'] ?? '') !== '1') lead_reply(400, false, 'Нужно согласие на обработку персональных данных');
$phone = $field('phone', 40);
$digits = preg_replace('/\D/', '', $phone);
if (strlen($digits) < 11 || strlen($digits) > 13) lead_reply(400, false, 'Укажите телефон');

$lead = [
    'date' => date('Y-m-d H:i:s'),
    'name' => $field('name', 120),
    'phone' => $phone,
    'product' => $field('product', 300),
    'config' => $field('config', 500),
    'message' => $field('message', 3000),
    'source' => $field('source', 40),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
];

// Вложение сохраняем рядом с журналом: оно видно в админке, даже если письмо не дошло
$file = null;
if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $ext = strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
    if ($_FILES['file']['size'] > LEAD_MAX_FILE) lead_reply(400, false, 'Файл больше 10 МБ');
    if (!in_array($ext, LEAD_FILE_EXT, true)) lead_reply(400, false, 'Такой тип файла не принимаем: нужен JPG, PNG, PDF, DXF, DWG, CDR, AI или SVG');
    $name = preg_replace('/[^\w.\-]+/u', '_', (string)$_FILES['file']['name']);
    $name = mb_substr($name, -100);
    $stored = bin2hex(random_bytes(6)) . '_' . $name;
    ensure_dir(storage_path('leads/files'));
    if (move_uploaded_file($_FILES['file']['tmp_name'], storage_path('leads/files/' . $stored))) {
        $file = ['name' => $name, 'path' => storage_path('leads/files/' . $stored)];
        $lead['file'] = $stored;
    }
}

// Заявка всегда пишется в журнал. Лимит (30 в час с одного адреса — офис за одним IP тоже пройдёт)
// только придерживает уведомления, чтобы бот не завалил почту.
try {
    $notify = with_lock(function () use (&$lead) {
        $f = storage_path('lead-rate.json');
        $all = is_file($f) ? read_json($f) : [];
        foreach ($all as $ip => $list) { $all[$ip] = array_values(array_filter($list, fn($t) => $t > time() - 3600)); if (!$all[$ip]) unset($all[$ip]); }
        $notify = count($all[$lead['ip']] ?? []) < 30;
        $all[$lead['ip']][] = time();
        write_file_atomic($f, json_pretty($all));
        if (!$notify) $lead['muted'] = true;
        ensure_dir(storage_path('leads'));
        if (file_put_contents(storage_path('leads/' . date('Y-m') . '.jsonl'), json_encode($lead, JSON_FLAGS) . "\n", FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('journal');
        return $notify;
    });
} catch (Throwable $e) {
    error_log('lead: ' . $e->getMessage());
    lead_reply(500, false, 'Не удалось отправить');
}
if (!$notify) lead_reply(200, true);

$host = (string)parse_url(read_json(cfg('data_dir') . '/company.json')['siteUrl'], PHP_URL_HOST);
$labels = ['name' => 'Имя', 'phone' => 'Телефон', 'product' => 'Товар', 'config' => 'Схема', 'message' => 'Сообщение', 'source' => 'Форма'];
$lines = [];
foreach ($labels as $k => $t) if ($lead[$k] !== '') $lines[] = "$t: {$lead[$k]}";
$text = "Заявка с сайта $host\n\n" . implode("\n", $lines) . "\n\nВремя: " . date('d.m.Y H:i');
$subject = 'Заявка с сайта: ' . ($lead['product'] ?: 'консультация');

// Посетителю отвечаем сразу (заявка уже в журнале), письмо и Telegram уходят после ответа
$body = json_encode(['ok' => true, 'error' => '']);
ignore_user_abort(true);
http_response_code(200);
header('Content-Length: ' . strlen($body));
header('Connection: close');
echo $body;
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
else { while (ob_get_level() > 0) ob_end_flush(); flush(); }
lead_deliver($text, $subject, $file);
