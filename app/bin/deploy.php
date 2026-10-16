<?php
// Заливка на хостинг по FTP. Код едет отсюда на сервер, контент — только с сервера сюда.
//
//   php app/bin/deploy.php push          — залить код (ядро, стили, скрипты, картинки оформления) и пересобрать сайт на сервере
//   php app/bin/deploy.php push --dry    — показать, что будет залито, ничего не отправляя
//   php app/bin/deploy.php init <пароль> — первая установка: код + данные + все фото + настройки сервера + вход в админку (только на пустой сервер).
//                                          Фото, которые уже лежат на сервере с тем же размером, повторно не заливаются
//   php app/bin/deploy.php pull          — забрать с сервера данные и фото, которые правил клиент, и пересобрать сайт здесь
//                                          (пропало больше 5 файлов — остановится и попросит --force)
//
// Доступ — в app/deploy.local.php (в git не хранится, пример — app/deploy.example.php).
// Никогда не заливаются: data/ и фото (кроме init), storage/ (пароли, заявки, история), локальные настройки.
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$cmd = $argv[1] ?? '';
$dry = in_array('--dry', $argv, true);
$force = in_array('--force', $argv, true);
if (!in_array($cmd, ['push', 'init', 'pull'], true)) { fwrite(STDERR, "Использование: php app/bin/deploy.php push|init|pull [--dry]\n"); exit(1); }
$dc = APP_DIR . '/deploy.local.php';
if (!is_file($dc)) { fwrite(STDERR, "Нет app/deploy.local.php — скопируйте app/deploy.example.php и заполните доступ.\n"); exit(1); }
$D = require $dc;
foreach (['host', 'user', 'pass', 'root', 'docroot', 'site_url'] as $k) if (!isset($D[$k])) { fwrite(STDERR, "В deploy.local.php не задан ключ $k\n"); exit(1); }

function out(string $s): void { echo $s, "\n"; }
function rel_path(string $base, string $file): string { return str_replace('\\', '/', substr($file, strlen($base) + 1)); }

function local_files(string $dir, callable $keep): array
{
    $out = [];
    if (!is_dir($dir)) return $out;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { $r = rel_path($dir, $f->getPathname()); if ($keep($r)) $out[$r] = $f->getPathname(); }
    ksort($out);
    return $out;
}

// ---------- что заливается как «код» ----------
function code_files(): array
{
    $files = [];
    // ядро без локальных настроек и доступа
    foreach (local_files(APP_DIR, fn($r) => !preg_match('~^(config\.local\.php|deploy\.local\.php|config\.server\.php|config\.server\.example\.php|deploy\.example\.php|dev-router\.php)$~', $r)) as $r => $p) $files["app/$r"] = $p;
    // статика сайта без фото, которые правит клиент (они — контент и живут на сервере)
    $pub = rtrim(cfg('public_dir'), '/\\');
    foreach (local_files($pub, fn($r) => !is_content_img($r)) as $r => $p) $files["@docroot/$r"] = $p;
    return $files;
}
// Фото, которые меняются в админке: товары (img/products/<артикул>/) и загрузки (img/uploads/)
function is_content_img(string $r): bool { return preg_match('~^img/(products|uploads)/~', $r) === 1; }
// Данные сайта; ozon_raw.json — сырая выгрузка для scripts/import-ozon.js, сайту не нужна
function is_content_data(string $r): bool { return preg_match('~^[a-z_]+\.json$~', $r) === 1 && $r !== 'ozon_raw.json'; }
function content_files(): array
{
    $files = [];
    foreach (local_files(cfg('data_dir'), 'is_content_data') as $r => $p) $files["data/$r"] = $p;
    foreach (local_files(rtrim(cfg('public_dir'), '/\\'), 'is_content_img') as $r => $p) $files["@docroot/$r"] = $p;
    return $files;
}

// ---------- FTP ----------
function ftp_open(array $D)
{
    for ($try = 1; $try <= 3; $try++) {
        $c = !empty($D['ssl']) ? @ftp_ssl_connect($D['host'], (int)($D['port'] ?? 21), 20) : @ftp_connect($D['host'], (int)($D['port'] ?? 21), 20);
        if ($c) {
            // Неверный пароль не повторяем: несколько неудачных входов подряд — и хостинг банит IP в файрволе
            if (@ftp_login($c, $D['user'], $D['pass'])) { ftp_pasv($c, true); return $c; }
            fwrite(STDERR, "FTP {$D['host']}: сервер ответил, но вход отклонён — проверьте user/pass в deploy.local.php.\nБольше не пробую: повторные неудачные входы приводят к блокировке IP.\n");
            exit(1);
        }
        out("  подключение не удалось (попытка $try), ждём…");
        sleep(3 * $try); // хостинги отклоняют частые подключения подряд
    }
    fwrite(STDERR, "Не удалось подключиться к FTP {$D['host']} (сервер не отвечает на порту " . (int)($D['port'] ?? 21) . ").\nЕсли сайт при этом открывается у других — вероятно, этот IP заблокирован файрволом хостинга.\n");
    exit(1);
}
function remote_path(array $D, string $key): string
{
    $root = trim($D['root'], '/');
    $key = str_starts_with($key, '@docroot/') ? trim($D['docroot'], '/') . '/' . substr($key, 9) : $key;
    return ($root !== '' ? '/' . $root : '') . '/' . $key;
}
function ftp_mkdirs($c, string $dir): void
{
    static $made = [];
    $parts = explode('/', trim($dir, '/'));
    $cur = '';
    foreach ($parts as $p) {
        $cur .= '/' . $p;
        if (isset($made[$cur])) continue;
        if (!@ftp_chdir($c, $cur)) @ftp_mkdir($c, $cur);
        $made[$cur] = true;
    }
}
function ftp_exists($c, string $path): bool { return ftp_size($c, $path) >= 0 || @ftp_chdir($c, $path); }

// Список файлов на сервере рекурсивно (MLSD, если сервер умеет; иначе обход папок)
function ftp_tree($c, string $dir): array
{
    $out = [];
    $list = @ftp_mlsd($c, $dir);
    if ($list === false) {
        $names = @ftp_nlist($c, $dir) ?: [];
        foreach ($names as $n) {
            $b = basename($n);
            if ($b === '.' || $b === '..') continue;
            $full = rtrim($dir, '/') . '/' . $b;
            if (@ftp_chdir($c, $full)) { foreach (ftp_tree($c, $full) as $k => $v) $out["$b/$k"] = $v; }
            else $out[$b] = ['size' => ftp_size($c, $full)];
        }
        return $out;
    }
    foreach ($list as $e) {
        if (in_array($e['name'], ['.', '..'], true) || in_array($e['type'], ['cdir', 'pdir'], true)) continue;
        if ($e['type'] === 'dir') { foreach (ftp_tree($c, rtrim($dir, '/') . '/' . $e['name']) as $k => $v) $out[$e['name'] . '/' . $k] = $v; }
        else $out[$e['name']] = ['size' => (int)($e['size'] ?? -1)];
    }
    return $out;
}

// ---------- заливка с памятью о том, что уже отправлено ----------
function upload(array $D, array $files, bool $dry): int
{
    $manFile = storage_path('deploy-manifest-' . preg_replace('~[^a-z0-9.-]~i', '_', $D['host'] . '_' . $D['root']) . '.json');
    $man = is_file($manFile) ? read_json($manFile) : [];
    $todo = [];
    foreach ($files as $key => $local) { $h = md5_file($local); if (($man[$key] ?? '') !== $h) $todo[$key] = [$local, $h]; }
    // Фото и видео, которых нет в памяти заливки, но которые уже лежат на сервере с тем же размером (сайт выкладывали
    // раньше), повторно не отправляем — только запоминаем. Код (app/, .htaccess, css, js) сверяется по содержимому.
    $unknown = array_filter($todo, fn($k) => !isset($man[$k]) && preg_match('~^@docroot/(img|video)/~', $k), ARRAY_FILTER_USE_KEY);
    if ($unknown) {
        $c0 = ftp_open($D);
        $remote = [];
        foreach (['img', 'video'] as $d) foreach (ftp_tree($c0, remote_path($D, "@docroot/$d")) as $r => $info) $remote["@docroot/$d/$r"] = $info['size'];
        ftp_close($c0);
        foreach ($unknown as $key => [$local, $h]) if (($remote[$key] ?? -1) === filesize($local)) { $man[$key] = $h; unset($todo[$key]); }
        if (!$dry) write_file_atomic($manFile, json_pretty($man));
    }
    out(count($todo) . ' из ' . count($files) . ' файлов изменились');
    if ($dry) { foreach ($todo as $key => $_) out('  ' . $key); return count($todo); }
    if (!$todo) return 0;
    $c = ftp_open($D);
    $n = 0;
    foreach ($todo as $key => [$local, $h]) {
        $remote = remote_path($D, $key);
        ftp_mkdirs($c, dirname($remote));
        // пишем во временное имя и переименовываем — посетитель не увидит наполовину залитый файл
        $tmp = $remote . '.uploading';
        if (!@ftp_put($c, $tmp, $local, FTP_BINARY) || !(@ftp_rename($c, $tmp, $remote) || (@ftp_delete($c, $remote) && @ftp_rename($c, $tmp, $remote)))) {
            fwrite(STDERR, "Не удалось залить $key\n");
            write_file_atomic($manFile, json_pretty($man));
            exit(1);
        }
        $man[$key] = $h;
        if (++$n % 25 === 0) { out("  … $n"); write_file_atomic($manFile, json_pretty($man)); }
    }
    ftp_close($c);
    write_file_atomic($manFile, json_pretty($man));
    out("Залито файлов: $n");
    return $n;
}

function remote_rebuild(array $D): void
{
    if (empty($D['deploy_key'])) { out('deploy_key не задан — пересоберите сайт на сервере вручную (кнопка в админке или php app/bin/build.php по SSH)'); return; }
    $url = rtrim($D['site_url'], '/') . '/admin/deploy-rebuild';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => '', CURLOPT_HTTPHEADER => ['X-Deploy-Key: ' . $D['deploy_key']], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string)$res, true);
    if ($code === 200 && !empty($j['ok'])) out('Сайт на сервере пересобран: ' . $j['message']);
    else { fwrite(STDERR, "Пересборка на сервере не удалась (HTTP $code): " . substr((string)$res, 0, 300) . "\n"); exit(1); }
}

// =====================================================================
if ($cmd === 'push') {
    out('Заливаю код на ' . $D['host'] . ' …');
    $n = upload($D, code_files(), $dry);
    if (!$dry) remote_rebuild($D);
    exit(0);
}

if ($cmd === 'init') {
    $c = ftp_open($D);
    $dataDir = remote_path($D, 'data');
    if (!$force && ftp_exists($c, $dataDir . '/site.json')) { fwrite(STDERR, "На сервере уже есть данные ($dataDir). init перезапишет правки клиента — остановлено. Используйте push.\n"); exit(1); }
    ftp_close($c);
    $server = APP_DIR . '/config.server.php';
    if (!is_file($server)) { fwrite(STDERR, "Нет app/config.server.php — настройки сервера (пример: app/config.server.example.php).\n"); exit(1); }
    // вход в админку создаётся здесь и заливается вместе с сайтом — SSH не нужен
    $password = (isset($argv[2]) && !str_starts_with($argv[2], '--')) ? $argv[2] : '';
    if (mb_strlen($password) < 10) { fwrite(STDERR, "Укажите пароль админки (от 10 символов): php app/bin/deploy.php init <пароль>\n"); exit(1); }
    $secret = 'vhod-' . bin2hex(random_bytes(5));
    $accFile = tempnam(sys_get_temp_dir(), 'adm');
    file_put_contents($accFile, json_pretty(['hash' => password_hash($password, PASSWORD_DEFAULT), 'secret' => $secret, 'changed' => date('c')]));
    out('Первая установка на ' . $D['host'] . ' …');
    $files = code_files() + content_files();
    $files['app/config.local.php'] = $server;
    $files['storage/admin.json'] = $accFile;
    upload($D, $files, $dry);
    unlink($accFile);
    if (!$dry) {
        remote_rebuild($D);
        out('Адрес входа в админку: ' . rtrim($D['site_url'], '/') . '/admin/' . $secret . '  — сохраните его, без него админка отвечает 404.');
        out('Сменить пароль потом можно в админке → Настройки.');
    }
    exit(0);
}

if ($cmd === 'pull') {
    // забираем поверх исходников — поэтому сначала всё локальное должно быть зафиксировано в git
    $dirty = trim((string)shell_exec('git -C ' . escapeshellarg(ROOT_DIR) . ' status --porcelain -- data public/img/products public/img/uploads'));
    if ($dirty !== '' && !$force) { fwrite(STDERR, "В data/ или фото товаров есть незакоммиченные изменения — сначала закоммитьте их:\n$dirty\n"); exit(1); }
    $c = ftp_open($D);
    $got = 0;
    $isPhoto = fn($r) => is_content_img('img/' . $r);
    $targets = [['data', cfg('data_dir'), 'is_content_data'], ['@docroot/img', rtrim(cfg('public_dir'), '/\\') . '/img', $isPhoto]];
    // Сначала только смотрим. Пустой или странный ответ сервера (неверный путь, сбой листинга)
    // не должен превратиться в удаление всего контента здесь.
    $plan = [];
    foreach ($targets as [$rkey, $ldir, $keep]) {
        $rdir = remote_path($D, $rkey);
        $tree = array_filter(ftp_tree($c, $rdir), fn($r) => $keep($r), ARRAY_FILTER_USE_KEY);
        if (!$tree) { fwrite(STDERR, "На сервере в $rdir ничего не найдено — проверьте root/docroot в deploy.local.php. Ничего не изменено.\n"); exit(1); }
        if ($rkey === 'data' && !isset($tree['site.json'])) { fwrite(STDERR, "На сервере нет $rdir/site.json — похоже, неверный путь. Ничего не изменено.\n"); exit(1); }
        $gone = array_keys(array_diff_key(local_files($ldir, $keep), $tree));
        $plan[] = [$rkey, $rdir, $ldir, $tree, $gone];
    }
    $allGone = array_merge(...array_map(fn($p) => array_map(fn($r) => $p[0] . '/' . $r, $p[4]), $plan));
    if (count($allGone) > 5 && !$force) { fwrite(STDERR, 'С сервера пропало ' . count($allGone) . " файлов — это подозрительно много. Проверьте и повторите с --force:\n  " . implode("\n  ", array_slice($allGone, 0, 30)) . "\n"); exit(1); }
    foreach ($plan as [$rkey, $rdir, $ldir, $tree, $gone]) {
        foreach ($tree as $rel => $info) {
            $local = $ldir . '/' . $rel;
            if (is_file($local) && filesize($local) === $info['size'] && !str_ends_with($rel, '.json')) continue; // фото с тем же размером не качаем
            ensure_dir(dirname($local));
            $tmp = $local . '.part';
            if (!@ftp_get($c, $tmp, "$rdir/$rel", FTP_BINARY)) { @unlink($tmp); fwrite(STDERR, "Не удалось скачать $rel\n"); exit(1); }
            if (is_file($local) && str_replace("\r\n", "\n", (string)file_get_contents($local)) === file_get_contents($tmp)) { unlink($tmp); continue; }
            rename($tmp, $local);
            out('  ' . $rkey . '/' . $rel);
            $got++;
        }
        // клиент удалил услугу или фото
        foreach ($gone as $rel) { unlink($ldir . '/' . $rel); out('  удалено: ' . $rkey . '/' . $rel); $got++; }
    }
    ftp_close($c);
    out("Получено изменений: $got");
    if ($got) { $r = build_site(); out("Сайт пересобран локально: {$r['pages']} страниц. Проверьте git diff и закоммитьте."); }
    exit(0);
}
