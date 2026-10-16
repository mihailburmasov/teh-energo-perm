<?php
// Чтение и запись полей форм в data/*.json, проверка значений, сохранение с пересборкой сайта.
declare(strict_types=1);

final class Docs
{
    public static array $cache = [];
}

function doc_get(string $rel): array
{
    if (!array_key_exists($rel, Docs::$cache)) Docs::$cache[$rel] = read_json(data_path($rel));
    return Docs::$cache[$rel];
}

function split_bind(string $bind): array
{
    [$file, $path] = explode('#', $bind, 2) + [1 => ''];
    return [$file, $path === '' ? [] : explode('.', $path)];
}

function arr_get(array $a, array $path)
{
    foreach ($path as $k) { if (!is_array($a) || !array_key_exists($k, $a)) return null; $a = $a[$k]; }
    return $a;
}
function arr_set(array &$a, array $path, $value): void
{
    if (!$path) { $a = $value; return; }
    $ref = &$a;
    foreach ($path as $i => $k) {
        if ($i === count($path) - 1) { $ref[$k] = $value; return; }
        if (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = [];
        $ref = &$ref[$k];
    }
}

function bind_get(string $bind)
{
    [$file, $path] = split_bind($bind);
    return arr_get(doc_get($file), $path);
}

// Путь к картинке сайта (/img/...) должен вести на существующий файл
function public_file_exists(string $url): bool
{
    return (bool)preg_match('~^/img/[a-z0-9/_.-]+\.(jpg|jpeg|png|webp)$~i', $url) && !str_contains($url, '..') && is_file(rtrim(cfg('public_dir'), '/\\') . $url);
}

// ---------- проверка значений по типу поля ----------
function clean_text($v, array $fd): string
{
    if (is_array($v)) throw new InvalidArgumentException('ожидался текст');
    $s = trim(str_replace(["\r\n", "\r"], "\n", (string)$v));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    if ($fd['type'] !== 'textarea') $s = preg_replace('/\s*\n\s*/u', ' ', $s);
    if (mb_strlen($s) > 10000) throw new InvalidArgumentException('слишком длинный текст');
    if (!empty($fd['required']) && $s === '') throw new InvalidArgumentException('поле обязательно');
    if (!empty($fd['pattern']) && $s !== '' && !preg_match($fd['pattern'], $s)) throw new InvalidArgumentException($fd['patternError'] ?? 'неверный формат');
    return $s;
}

function clean_value(array $fd, $v)
{
    $label = $fd['label'] ?? ($fd['key'] ?? '');
    if ($v === null && in_array($fd['type'], ['list', 'items', 'prodphotos', 'images'], true)) $v = []; // поля нет в данных — пустой список
    try {
        switch ($fd['type']) {
            case 'text': case 'textarea': return clean_text($v, $fd);
            case 'url':
                $s = clean_text($v, ['type' => 'text'] + $fd);
                if ($s !== '' && !preg_match('~^https?://[^\s"<>]+$~i', $s)) throw new InvalidArgumentException('ссылка должна начинаться с https://');
                return $s;
            case 'email':
                $s = clean_text($v, ['type' => 'text'] + $fd);
                if ($s !== '' && !filter_var($s, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('неверный адрес почты');
                return $s;
            case 'number':
                if ($v === '' || $v === null) { if (!empty($fd['required'])) throw new InvalidArgumentException('поле обязательно'); return null; }
                if (!is_numeric($v)) throw new InvalidArgumentException('нужно число');
                $n = (int)round((float)$v);
                if (isset($fd['min']) && $n < $fd['min']) throw new InvalidArgumentException('не меньше ' . $fd['min']);
                return $n;
            case 'float':
                if ($v === '' || $v === null || !is_numeric($v)) throw new InvalidArgumentException('нужно число, например 58.04804');
                return (float)$v;
            case 'bool': return (bool)$v;
            case 'list':
                if (!is_array($v)) throw new InvalidArgumentException('ожидался список');
                return array_values(array_filter(array_map(fn($x) => clean_text($x, ['type' => !empty($fd['multiline']) ? 'textarea' : 'text']), $v), fn($x) => $x !== ''));
            case 'items':
                if (!is_array($v)) throw new InvalidArgumentException('ожидался список');
                $out = [];
                foreach (array_values($v) as $row) {
                    if (!is_array($row)) continue;
                    // Порядок ключей — как в исходной записи; поля, которых нет в форме, сохраняются как были
                    $subs = array_column($fd['item'], null, 'key');
                    $item = [];
                    foreach (array_unique(array_merge(array_keys($row), array_keys($subs))) as $key) {
                        if (!isset($subs[$key])) {
                            if (is_string($key) && preg_match('~^[A-Za-z_]{1,40}$~', $key)) $item[$key] = keep_value($row[$key]);
                            continue;
                        }
                        $sub = $subs[$key];
                        $val = clean_value($sub, $row[$key] ?? null);
                        if ($sub['type'] === 'bool') { if ($val) $item[$key] = true; continue; }
                        if (($val === '' || $val === []) && (!empty($sub['omitEmpty']) || !array_key_exists($key, $row))) continue; // пустое необязательное поле не записываем
                        $item[$key] = $val;
                    }
                    // строки, где все поля пустые, отбрасываем
                    $filled = array_filter($item, fn($x) => $x !== '' && $x !== [] && $x !== null && $x !== false);
                    if ($filled) $out[] = $item;
                }
                if (!empty($fd['fixed']) && isset($fd['count']) && count($out) !== $fd['count']) throw new InvalidArgumentException('нельзя добавлять и удалять строки');
                return $out;
            case 'catsel':
                $v = (string)$v;
                if (cat_name($v) === '') throw new InvalidArgumentException('неизвестная категория');
                return $v;
            case 'image':
                $v = clean_text($v, ['type' => 'text'] + $fd);
                if ($v !== '' && !public_file_exists($v)) throw new InvalidArgumentException('картинка не найдена: ' . $v);
                return $v;
            case 'images':
                if (!is_array($v)) throw new InvalidArgumentException('ожидался список картинок');
                $out = [];
                foreach ($v as $u) { $u = (string)$u; if (!public_file_exists($u)) throw new InvalidArgumentException('картинка не найдена: ' . $u); $out[] = $u; }
                return array_values(array_unique($out));
            case 'prodphotos':
                if (!is_array($v)) throw new InvalidArgumentException('ожидался список фото');
                $sku = (string)($fd['sku'] ?? '');
                $out = [];
                foreach ($v as $n) {
                    if (!is_numeric($n) || (int)$n < 1) throw new InvalidArgumentException('неверный номер фото');
                    if (!is_file(product_img_dir($sku) . '/' . (int)$n . '.webp')) throw new InvalidArgumentException('фото ' . (int)$n . ' не найдено');
                    $out[] = (int)$n;
                }
                return array_values(array_unique($out));
        }
    } catch (InvalidArgumentException $e) {
        throw new InvalidArgumentException("«{$label}»: " . $e->getMessage());
    }
    throw new InvalidArgumentException("«{$label}»: неизвестный тип поля");
}

// Значение поля, которого нет в форме, сохраняется как есть (строки — с очисткой управляющих символов)
function keep_value($v)
{
    if (is_string($v)) return clean_text($v, ['type' => 'textarea']);
    if (is_array($v)) return array_map('keep_value', $v);
    return $v;
}

// Телефон «+7 (908) 277-95-46» -> ссылка +79082779546
function tel_from_display(string $s): string
{
    $d = preg_replace('~\D~', '', $s);
    if (strlen($d) === 11 && ($d[0] === '8' || $d[0] === '7')) return '+7' . substr($d, 1);
    if (strlen($d) === 10) return '+7' . $d;
    return '+' . $d;
}

// Значения полей раздела для формы
function section_values(array $fields, string $svc = ''): array
{
    $out = [];
    foreach ($fields as $fd) {
        if (empty($fd['bind'])) continue;
        $out[$fd['bind']] = bind_get(str_replace('{svc}', $svc, $fd['bind']));
    }
    return $out;
}

// Применить присланные значения к документам. Возвращает [файл => новое содержимое] только для изменённых файлов.
function apply_values(array $fields, array $values, string $svc = ''): array
{
    load_site_data(); // для проверки категорий
    $docs = [];
    foreach (schema_binds($fields) as $bind => $fd) {
        if (!array_key_exists($bind, $values) || !empty($fd['readonly'])) continue;
        $val = clean_value($fd, $values[$bind]);
        $real = str_replace('{svc}', $svc, $bind);
        [$file, $path] = split_bind($real);
        $docs[$file] ??= doc_get($file);
        if ($real === 'company.json#phone') {
            $tel = tel_from_display($val);
            if (strlen($tel) !== 12) throw new InvalidArgumentException('«Телефон»: нужен российский номер из 11 цифр');
            $docs[$file]['phoneRaw'] = $tel; // ссылка tel: — из того же номера
        }
        if ($fd['type'] === 'bool' && !$val) { unset_path($docs[$file], $path); continue; }
        // пустое значение там, где и раньше ничего не было, не записываем (null остаётся null, ключ не появляется)
        if (is_blank($val) && is_blank(arr_get($docs[$file], $path))) continue;
        arr_set($docs[$file], $path, $val);
    }
    return array_filter($docs, fn($d, $file) => $d !== doc_get($file), ARRAY_FILTER_USE_BOTH);
}

function is_blank($v): bool { return $v === null || $v === '' || $v === []; }

function unset_path(array &$a, array $path): void
{
    $last = array_pop($path);
    $ref = &$a;
    foreach ($path as $k) { if (!isset($ref[$k]) || !is_array($ref[$k])) return; $ref = &$ref[$k]; }
    unset($ref[$last]);
}

// Записать изменённые файлы и пересобрать сайт. Если сборка упала — вернуть файлы как были.
// $files: [относительный путь => данные или null (удалить файл)]
function commit_changes(array $files, string $who = 'admin'): array
{
    if (!$files) return ['changed' => 0, 'build' => null];
    return with_lock(function () use ($files, $who) {
        $before = [];
        foreach ($files as $rel => $data) {
            $f = data_path($rel);
            $before[$rel] = is_file($f) ? file_get_contents($f) : null;
        }
        try {
            foreach ($files as $rel => $data) {
                if ($data === null) { if ($before[$rel] !== null) { backup_revision($rel, $before[$rel], $who); unlink(data_path($rel)); } }
                else write_data_json($rel, $data, $who);
            }
            $build = build_site();
        } catch (Throwable $e) {
            foreach ($before as $rel => $raw) {
                if ($raw === null) @unlink(data_path($rel));
                else write_file_atomic(data_path($rel), $raw);
            }
            try { build_site(); } catch (Throwable $e2) { /* сайт остаётся в последнем собранном виде */ }
            throw new RuntimeException('Изменения не сохранены: ' . $e->getMessage());
        } finally {
            Docs::$cache = [];
        }
        return ['changed' => count($files), 'build' => $build];
    });
}
