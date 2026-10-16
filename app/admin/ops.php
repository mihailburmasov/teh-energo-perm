<?php
// Операции админки: товары каталога и их фото, картинки услуг, корзина, история правок, заявки, настройки.
declare(strict_types=1);

// =====================================================================
// ТОВАРЫ КАТАЛОГА (data/products.json, фото — public/img/products/<артикул>/N.webp и N-s.webp)
// =====================================================================
function product_img_dir(string $sku): string
{
    if (!preg_match('~^\d{1,15}$~', $sku)) throw new InvalidArgumentException('Неверный артикул');
    return rtrim(cfg('public_dir'), '/\\') . '/img/products/' . $sku;
}

function product_index(string $sku): int
{
    foreach (doc_get('products.json')['products'] as $i => $p) if ((string)$p['sku'] === $sku) return $i;
    throw new InvalidArgumentException('Товар не найден');
}
function product_by_sku(string $sku): ?array
{
    foreach (doc_get('products.json')['products'] as $p) if ((string)$p['sku'] === $sku) return $p;
    return null;
}

// Список для экрана «Товары»: в порядке каталога
function products_all(): array
{
    $pr = doc_get('products.json');
    $press = (string)doc_get('press.json')['ozonSku'];
    $cats = array_column($pr['categories'], 'short', 'id');
    return array_map(fn($p) => [
        'sku' => (string)$p['sku'], 'title' => $p['title'], 'short' => $p['short'], 'price' => $p['price'],
        'cat' => $cats[$p['category']] ?? $p['category'], 'hidden' => !empty($p['hidden']), 'photo' => ($p['images'][0] ?? null),
        'press' => (string)$p['sku'] === $press,
    ], $pr['products']);
}

function translit_slug(string $s): string
{
    static $map = ['а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya'];
    $s = strtr(mb_strtolower($s), $map);
    $s = trim(preg_replace('~[^a-z0-9]+~', '-', $s), '-');
    return substr($s, 0, 50) ?: 'kleymo';
}

// Новый товар: свой артикул (у товаров не с Ozon — начиная с 9000000001), создаётся скрытым и без фото
function product_create(string $short, string $category): string
{
    $short = trim($short);
    if (mb_strlen($short) < 2) throw new InvalidArgumentException('Введите название');
    load_site_data();
    if (cat_name($category) === '') throw new InvalidArgumentException('Выберите категорию');
    $pr = doc_get('products.json');
    $sku = 9000000000;
    foreach ($pr['products'] as $p) if ($p['sku'] >= $sku) $sku = $p['sku'];
    $sku++;
    $trash = array_column(trash_index(), 'ref');
    while (in_array((string)$sku, $trash, true) || is_dir(product_img_dir((string)$sku))) $sku++;
    $maxOrder = max(array_map(fn($p) => (int)$p['order'], $pr['products']) ?: [0]);
    $pr['products'][] = [
        'sku' => $sku, 'slug' => translit_slug($short) . '-' . $sku,
        'title' => 'Ударное клеймо по металлу ' . $short, 'short' => $short,
        'category' => $category, 'price' => 3500, 'oldPrice' => null, 'rating' => null, 'reviews' => null,
        'images' => [], 'order' => $maxOrder + 1, 'hidden' => true,
    ];
    commit_changes(['products.json' => $pr]);
    return (string)$sku;
}

function product_delete(string $sku): void
{
    if ($sku === (string)doc_get('press.json')['ozonSku']) throw new InvalidArgumentException('Это товар пресса: его фото показываются на странице «Прессы». Удалить нельзя — можно поменять фото.');
    $pr = doc_get('products.json');
    $i = product_index($sku);
    $p = $pr['products'][$i];
    array_splice($pr['products'], $i, 1);
    // сначала проверяем, что сайт без товара собирается (например, на него не ведёт редирект со старого сайта)
    commit_changes(['products.json' => $pr]);
    $tid = trash_put('product', $sku, $p['short'], ['data' => $p, 'pos' => $i]);
    $dir = product_img_dir($sku);
    if (is_dir($dir)) rename($dir, storage_path("trash/$tid/img"));
}

function products_reorder(array $skus): void
{
    $pr = doc_get('products.json');
    $by = [];
    foreach ($pr['products'] as $p) $by[(string)$p['sku']] = $p;
    $out = [];
    foreach ($skus as $s) if (isset($by[$s])) { $out[] = $by[$s]; unset($by[$s]); }
    foreach ($by as $p) $out[] = $p; // кого не прислали — в конец, ничего не теряем
    // «order» — номер для сортировки «По популярности» на сайте: идёт подряд за порядком списка
    foreach ($out as $i => &$p) $p['order'] = $i;
    unset($p);
    $pr['products'] = $out;
    commit_changes(['products.json' => $pr]);
}

// Фото товара: обрезка до 3:4, крупное 750×1000 и превью 300×400 — как у остальных товаров
function product_photo_upload(string $sku, array $file): int
{
    product_index($sku);
    $src = image_from_upload($file);
    $dir = product_img_dir($sku);
    ensure_dir($dir);
    $n = 1;
    foreach (glob("$dir/*.webp") ?: [] as $f) if (preg_match('~/(\d+)\.webp$~', str_replace('\\', '/', $f), $m)) $n = max($n, (int)$m[1] + 1);
    webp_cover($src, 750, 1000, 82, "$dir/$n.webp");
    webp_cover($src, 300, 400, 80, "$dir/$n-s.webp");
    imagedestroy($src);
    return $n;
}

// =====================================================================
// КАРТИНКИ УСЛУГ И ПЕРВЫХ ЭКРАНОВ: public/img/uploads/<имя>.webp (до 1400 px) и <имя>-s.webp (до 640 px)
// =====================================================================
function image_upload(array $file): array
{
    $src = image_from_upload($file);
    $dir = rtrim(cfg('public_dir'), '/\\') . '/img/uploads';
    ensure_dir($dir);
    do { $id = date('Ymd') . '-' . bin2hex(random_bytes(3)); } while (is_file("$dir/$id.webp"));
    webp_fit($src, 1400, 82, "$dir/$id.webp");
    webp_fit($src, 640, 80, "$dir/$id-s.webp");
    imagedestroy($src);
    return ['full' => "/img/uploads/$id.webp", 'small' => "/img/uploads/$id-s.webp"];
}

function image_from_upload(array $file)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'Файл слишком большой' : 'Файл не загрузился');
    if ($file['size'] > 30 * 1024 * 1024) throw new InvalidArgumentException('Файл больше 30 МБ');
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) throw new InvalidArgumentException('Нужна картинка JPG, PNG или WebP');
    if ($info[0] * $info[1] > 60_000_000) throw new InvalidArgumentException('Слишком большое разрешение');
    @ini_set('memory_limit', '512M');
    $src = match ($types[$info[2]]) { 'jpeg' => @imagecreatefromjpeg($file['tmp_name']), 'png' => @imagecreatefrompng($file['tmp_name']), 'webp' => @imagecreatefromwebp($file['tmp_name']) };
    if (!$src) throw new InvalidArgumentException('Не удалось прочитать картинку');
    if ($types[$info[2]] === 'jpeg' && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }
    return $src;
}

function webp_save($img, int $q, string $dest): void
{
    $tmp = $dest . '.tmp';
    if (!imagewebp($img, $tmp, $q)) throw new RuntimeException('Не удалось сохранить WebP');
    imagedestroy($img);
    if (!@rename($tmp, $dest)) { @unlink($dest); rename($tmp, $dest); }
}

// Вписать в квадрат max×max без увеличения
function webp_fit($src, int $max, int $q, string $dest): array
{
    $w = imagesx($src); $h = imagesy($src);
    $k = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    webp_save($dst, $q, $dest);
    return [$nw, $nh];
}

// Заполнить ровно W×H с обрезкой по центру
function webp_cover($src, int $W, int $H, int $q, string $dest): void
{
    $w = imagesx($src); $h = imagesy($src);
    $k = max($W / $w, $H / $h);
    $cw = (int)round($W / $k); $ch = (int)round($H / $k);
    $dst = imagecreatetruecolor($W, $H);
    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $cw) / 2), (int)(($h - $ch) / 2), $W, $H, $cw, $ch);
    webp_save($dst, $q, $dest);
}

// =====================================================================
// КОРЗИНА: удалённое не стирается, а переезжает сюда. Автоочистки нет.
// =====================================================================
function trash_index(): array
{
    $f = storage_path('trash/index.json');
    return is_file($f) ? read_json($f) : [];
}
function trash_save_index(array $idx): void { write_file_atomic(storage_path('trash/index.json'), json_pretty(array_values($idx))); }

function trash_put(string $type, string $ref, string $title, array $payload): string
{
    $tid = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    ensure_dir(storage_path("trash/$tid"));
    write_file_atomic(storage_path("trash/$tid/item.json"), json_pretty(['type' => $type, 'ref' => $ref, 'title' => $title] + $payload));
    $idx = trash_index();
    array_unshift($idx, ['id' => $tid, 'type' => $type, 'ref' => $ref, 'title' => $title, 'date' => date('c')]);
    trash_save_index($idx);
    return $tid;
}
function trash_forget(string $tid): void { trash_save_index(array_filter(trash_index(), fn($t) => $t['id'] !== $tid)); }

function trash_restore(string $tid): string
{
    if (!preg_match('~^[0-9a-f-]+$~', $tid) || !is_file(storage_path("trash/$tid/item.json"))) throw new InvalidArgumentException('Не найдено в корзине');
    $item = read_json(storage_path("trash/$tid/item.json"));
    $sku = (string)$item['ref'];
    if (product_by_sku($sku)) throw new InvalidArgumentException('Товар с таким артикулом уже есть');
    $dir = product_img_dir($sku);
    if (is_dir(storage_path("trash/$tid/img"))) { if (is_dir($dir)) throw new InvalidArgumentException('Папка фото товара уже занята'); rename(storage_path("trash/$tid/img"), $dir); }
    $pr = doc_get('products.json');
    $p = $item['data'];
    $p['hidden'] = true; // возвращаем скрытым: проверить и включить
    array_splice($pr['products'], min((int)($item['pos'] ?? PHP_INT_MAX), count($pr['products'])), 0, [$p]);
    try { commit_changes(['products.json' => $pr]); }
    catch (Throwable $e) { if (is_dir($dir)) rename($dir, storage_path("trash/$tid/img")); throw $e; }
    trash_forget($tid);
    rrmdir(storage_path("trash/$tid"));
    return 'Товар возвращён скрытым — проверьте его и снимите галочку «Скрыть с сайта».';
}

// =====================================================================
// ИСТОРИЯ ПРАВОК
// =====================================================================
function file_label(string $rel): string
{
    static $names = ['site.json' => 'Вопросы и описания категорий', 'company.json' => 'Компания и контакты', 'products.json' => 'Каталог (товары, цены, категории)', 'press.json' => 'Пресс', 'laser.json' => 'Лазерная гравировка', 'matrix.json' => 'Матрицы и пуансоны'];
    return $names[$rel] ?? $rel;
}

function revisions_list(int $limit = 200): array
{
    $out = [];
    foreach (glob(storage_path('revisions/*'), GLOB_ONLYDIR) ?: [] as $dir) {
        $rel = str_replace('__', '/', basename($dir));
        foreach (glob("$dir/*.json") ?: [] as $f) {
            $name = basename($f, '.json');
            if (!preg_match('~^(\d{4}-\d\d-\d\d)_(\d\d)-(\d\d)-(\d\d)~', $name, $m)) continue;
            $out[] = ['id' => basename($dir) . '/' . $name, 'file' => $rel, 'label' => file_label($rel), 'date' => "{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}"];
        }
    }
    usort($out, fn($a, $b) => strcmp($b['date'], $a['date']));
    return array_slice($out, 0, $limit);
}

// Вернуть версию файла, какой она была ДО указанной правки
function revision_restore(string $id): void
{
    if (!preg_match('~^([a-z0-9_.-]+)/([0-9_a-z-]+)$~i', $id, $m)) throw new InvalidArgumentException('Неверная версия');
    $f = storage_path("revisions/{$m[1]}/{$m[2]}.json");
    if (!is_file($f)) throw new InvalidArgumentException('Версия не найдена');
    commit_changes([str_replace('__', '/', $m[1]) => read_json($f)], 'restore');
}

// =====================================================================
// ЗАЯВКИ (пишет app/lead.php)
// =====================================================================
function leads_list(int $limit = 300): array
{
    $out = [];
    $files = glob(storage_path('leads/*.jsonl')) ?: [];
    rsort($files);
    foreach ($files as $f) {
        $lines = array_reverse(file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
        foreach ($lines as $l) { $j = json_decode($l, true); if ($j) $out[] = $j; if (count($out) >= $limit) break 2; }
    }
    return $out;
}

// Вложение заявки (эскиз, чертёж) — только для вошедшего в админку
function lead_file_path(string $name): string
{
    if (!preg_match('~^[0-9a-f]{8,}_[\w.\-]{1,120}$~u', $name)) throw new InvalidArgumentException('Файл не найден');
    $f = storage_path('leads/files/' . $name);
    if (!is_file($f)) throw new InvalidArgumentException('Файл не найден');
    return $f;
}

// =====================================================================
// НАСТРОЙКИ (storage/settings.json — не публикуется и не попадает в git)
// =====================================================================
function settings_get(): array
{
    $f = storage_path('settings.json');
    $s = is_file($f) ? read_json($f) : [];
    return $s + ['leadEmails' => [], 'tgToken' => '', 'tgChats' => [], 'mailFrom' => ''];
}
function settings_save(array $v): void
{
    $emails = array_values(array_filter(array_map('trim', (array)($v['leadEmails'] ?? []))));
    foreach ($emails as $e) if (!filter_var($e, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException("Неверный адрес почты: $e");
    $chats = array_values(array_filter(array_map('trim', (array)($v['tgChats'] ?? []))));
    foreach ($chats as $c) if (!preg_match('~^-?\d{3,20}$~', $c)) throw new InvalidArgumentException('Chat ID — это число, например 123456789 или -1001234567890');
    $token = trim((string)($v['tgToken'] ?? ''));
    if ($token === '••••••') $token = settings_get()['tgToken'];
    if ($token !== '' && !preg_match('~^\d{5,15}:[A-Za-z0-9_-]{30,}$~', $token)) throw new InvalidArgumentException('Токен бота выглядит неверно');
    $from = trim((string)($v['mailFrom'] ?? ''));
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Неверный адрес отправителя');
    write_file_atomic(storage_path('settings.json'), json_pretty(['leadEmails' => $emails, 'tgToken' => $token, 'tgChats' => $chats, 'mailFrom' => $from]));
}
