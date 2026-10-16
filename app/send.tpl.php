<?php
// Приём заявок с сайта ТЕХ-ЭНЕРГО: письмо на почту.
// Нужен хостинг с PHP 7.4+ и работающей функцией mail() (Beget, Timeweb, reg.ru и т. п.).

const MAIL_TO   = '{{EMAIL}}';               // подставляется при сборке из data/company.json
const MAIL_FROM = 'no-reply@{{DOMAIN}}';   // адрес на домене сайта — так письма реже уходят в спам
const MAX_FILE  = 10 * 1024 * 1024;
const ALLOWED   = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dxf', 'dwg', 'cdr', 'ai', 'svg'];

header('Content-Type: application/json; charset=utf-8');
function out($ok, $error = '') { echo json_encode(['ok' => $ok, 'error' => $error], JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); out(false, 'method'); }

$f = fn($k, $max = 2000) => mb_substr(trim(strip_tags($_POST[$k] ?? '')), 0, $max);
// honeypot: поле скрыто от людей, боты его заполняют
if ($f('website') !== '') out(true);
if (($_POST['consent'] ?? '') !== '1') { http_response_code(400); out(false, 'Нужно согласие на обработку персональных данных'); }
$phone = $f('phone', 40);
if (strlen(preg_replace('/\D/', '', $phone)) < 11) { http_response_code(400); out(false, 'Укажите телефон'); }

$data = [
  'Имя'       => $f('name', 120),
  'Телефон'   => $phone,
  'Товар'     => $f('product', 300),
  'Схема'     => $f('config', 500),
  'Сообщение' => $f('message', 3000),
  'Форма'     => $f('source', 40),
];
$lines = [];
foreach ($data as $k => $v) if ($v !== '') $lines[] = "$k: $v";
$text = "Заявка с сайта {{DOMAIN}}\n\n" . implode("\n", $lines) . "\n\nВремя: " . date('d.m.Y H:i');

// вложение
$file = null;
if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
  $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
  if ($_FILES['file']['size'] <= MAX_FILE && in_array($ext, ALLOWED, true)) {
    $file = ['name' => preg_replace('/[^\w.\-]+/u', '_', $_FILES['file']['name']), 'path' => $_FILES['file']['tmp_name']];
  }
}

// письмо
$subject = '=?UTF-8?B?' . base64_encode('Заявка с сайта: ' . ($data['Товар'] ?: 'консультация')) . '?=';
$headers = "From: ТЕХ-ЭНЕРГО <" . MAIL_FROM . ">\r\nMIME-Version: 1.0\r\n";
if ($file) {
  $b = md5(uniqid('', true));
  $headers .= "Content-Type: multipart/mixed; boundary=\"$b\"\r\n";
  $body = "--$b\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b\r\nContent-Type: application/octet-stream; name=\"{$file['name']}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$file['name']}\"\r\n\r\n"
        . chunk_split(base64_encode(file_get_contents($file['path']))) . "--$b--";
} else {
  $headers .= "Content-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n";
  $body = chunk_split(base64_encode($text));
}
$mailed = @mail(MAIL_TO, $subject, $body, $headers);

if (!$mailed) { http_response_code(500); out(false, 'Не удалось отправить'); }
out(true);
