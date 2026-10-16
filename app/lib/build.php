<?php
// Сборка сайта: data/*.json -> готовые HTML-страницы в корне сайта.
// Все страницы сначала собираются в памяти; если по дороге ошибка — на диск ничего не пишется, сайт остаётся прежним.
declare(strict_types=1);

// Собирает все страницы. Возвращает [относительный путь файла => содержимое].
function render_site(): array
{
    load_site_data();
    validate_data();
    $C = S::$c;
    $f = [];
    $f['index.html'] = page_home();
    $f['catalog/index.html'] = page_catalog();
    foreach (S::$stamps as $p) $f['catalog/' . $p['slug'] . '/index.html'] = page_product($p);
    $f['pressy/index.html'] = page_press();
    $f['matricy/index.html'] = page_matrix();
    $f['gravirovka/index.html'] = page_laser();
    $svcPages = [];
    foreach (S::$svc as $g) foreach ($g['items'] as $x) if ($x['own']) { $svcPages[] = $x['href']; $f[substr($x['href'], 1) . 'index.html'] = page_service($g, $x); }
    foreach (legal_pages() as $slug => [$t, $h]) $f[$slug . '/index.html'] = page_legal($slug, $t, $h);
    $f['404.html'] = page_404();

    $urls = array_merge(['/', '/catalog/', '/pressy/', '/matricy/', '/gravirovka/'], $svcPages, array_map(fn($p) => '/catalog/' . $p['slug'] . '/', S::$stamps), ['/politika/', '/soglasie/']);
    $f['sitemap.xml'] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode("\n", array_map(fn($u) => '  <url><loc>' . $C['siteUrl'] . $u . '</loc><lastmod>' . S::$updated . '</lastmod></url>', $urls)) . "\n</urlset>\n";
    $f['robots.txt'] = "User-agent: *\nDisallow: /send.php\nSitemap: " . $C['siteUrl'] . "/sitemap.xml\n";
    $f['send.php'] = str_replace(['{{EMAIL}}', '{{DOMAIN}}'], [$C['email'], parse_url($C['siteUrl'], PHP_URL_HOST)], (string)file_get_contents(APP_DIR . '/send.tpl.php'));

    // Редиректы со старого сайта не должны вести на несуществующую страницу (иначе вес теряется на 404)
    $ht = (string)@file_get_contents(rtrim(cfg('public_dir'), '/\\') . '/.htaccess');
    preg_match_all('~https://%\{HTTP_HOST\}(/[^\s$%]*)\s~', $ht, $m);
    $dead = [];
    foreach ($m[1] as $u) {
        $rel = ltrim($u, '/') . (str_ends_with($u, '/') ? 'index.html' : '');
        if ($rel === '') $rel = 'index.html';
        if (!isset($f[$rel]) && !is_file(rtrim(cfg('public_dir'), '/\\') . '/' . $rel)) $dead[] = $u;
    }
    if ($dead) throw new RuntimeException('Со старых адресов сайта ведут редиректы на страницы, которых больше нет: ' . implode(', ', array_unique($dead)) . '. Верните товар или услугу (или снимите галочку «скрыть»).');
    return $f;
}

// Полная сборка с записью на диск. Возвращает краткий отчёт.
function build_site(): array
{
    return with_lock(function () {
        $t0 = microtime(true);
        $files = render_site();
        $out = rtrim(cfg('out_dir'), '/\\');
        ensure_dir($out);

        $pub = realpath(cfg('public_dir'));
        if ($pub && $pub !== realpath($out)) mirror_static($pub, $out, array_keys($files));

        $changed = 0;
        foreach ($files as $rel => $content) {
            $f = "$out/$rel";
            if (is_file($f) && file_get_contents($f) === $content) continue;
            write_file_atomic($f, $content);
            $changed++;
        }
        // Страницы, которые больше не собираются (скрытый товар), убираем — они производные от данных
        $manifestFile = storage_path('build-manifest.json');
        $prev = is_file($manifestFile) ? read_json($manifestFile) : [];
        $removed = 0;
        foreach (array_diff($prev, array_keys($files)) as $rel) {
            $f = "$out/$rel";
            if (is_file($f)) { unlink($f); $removed++; }
            $dir = dirname($f);
            if ($dir !== $out && is_dir($dir) && count(scandir($dir)) === 2) rmdir($dir);
        }
        write_file_atomic($manifestFile, json_pretty(array_keys($files)));
        return ['pages' => count($files), 'changed' => $changed, 'removed' => $removed, 'ms' => (int)round((microtime(true) - $t0) * 1000)];
    });
}

// Для работы на своём компьютере: статика из public копируется в out, лишнее удаляется.
function mirror_static(string $src, string $dst, array $generated): void
{
    $keep = array_flip($generated);
    $seen = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($src) + 1));
        $seen[$rel] = true;
        $to = "$dst/$rel";
        if (is_file($to) && filesize($to) === $f->getSize() && filemtime($to) >= $f->getMTime()) continue;
        ensure_dir(dirname($to));
        copy($f->getPathname(), $to);
    }
    if (!is_dir($dst)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dst, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($dst) + 1));
        if ($f->isDir()) { if (count(scandir($f->getPathname())) === 2) rmdir($f->getPathname()); continue; }
        if (!isset($seen[$rel]) && !isset($keep[$rel])) unlink($f->getPathname());
    }
}
