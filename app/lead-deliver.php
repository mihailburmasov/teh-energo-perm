<?php
// Отправка заявки на почту (с вложением) и в Telegram. Настройки — в админке (storage/settings.json).
declare(strict_types=1);

function lead_deliver(string $text, string $subject, ?array $file = null): array
{
    $set = settings_get();
    $res = [];
    $c = read_json(cfg('data_dir') . '/company.json');
    $emails = $set['leadEmails'] ?: (!empty($c['email']) ? [$c['email']] : []);
    if ($emails && function_exists('mail')) {
        $host = (string)parse_url($c['siteUrl'], PHP_URL_HOST);
        $from = $set['mailFrom'] ?: 'no-reply@' . $host;
        $headers = "From: =?UTF-8?B?" . base64_encode($c['brand']) . "?= <$from>\r\nMIME-Version: 1.0\r\n";
        if ($file && is_file($file['path'])) {
            $b = md5(uniqid('', true));
            $headers .= "Content-Type: multipart/mixed; boundary=\"$b\"";
            $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
                . "--$b\r\nContent-Type: application/octet-stream; name=\"{$file['name']}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$file['name']}\"\r\n\r\n"
                . chunk_split(base64_encode((string)file_get_contents($file['path']))) . "--$b--";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
            $body = chunk_split(base64_encode($text));
        }
        $res['mail'] = @mail(implode(', ', $emails), '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }
    if ($set['tgToken'] && $set['tgChats']) {
        $tgText = $text . ($file ? "\n\nВложение: {$file['name']} — в письме и в админке (раздел «Заявки»)" : '');
        foreach ($set['tgChats'] as $chat) $res['tg'][$chat] = tg_send($set['tgToken'], $chat, $tgText);
    }
    return $res;
}

function tg_send(string $token, string $chat, string $text): bool
{
    $ch = curl_init("https://api.telegram.org/bot$token/sendMessage");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => ['chat_id' => $chat, 'text' => $text, 'disable_web_page_preview' => 'true'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5]);
    $out = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $out !== false && $code === 200;
}
