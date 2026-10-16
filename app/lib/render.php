<?php
// Общие части страниц: данные на время сборки, форматирование, иконки, оболочка страницы, шапка, подвал, формы, карточка клейма.
declare(strict_types=1);

final class S
{
    public static string $base = '';    // сайт в корне домена; нужно для путей cookie админки
    public static array $c = [];        // company.json — факты о компании
    public static array $cats = [];     // категории каталога
    public static array $all = [];      // все товары из products.json
    public static array $products = []; // товары с фото
    public static array $stamps = [];   // клейма (и наборы) — то, что в каталоге
    public static array $press = [];    // press.json
    public static ?array $pressProduct = null;
    public static array $laser = [];
    public static array $matrix = [];
    public static array $site = [];     // site.json — тексты страниц
    public static string $updated = '';
    public static int $minPrice = 0;
    public static string $credit = '';
    public static array $svc = [];      // услуги: гравировка и матрицы
}

function load_site_data(): void
{
    $d = cfg('data_dir');
    S::$c = read_json("$d/company.json");
    $pr = read_json("$d/products.json");
    S::$cats = $pr['categories'];
    S::$all = $pr['products'];
    S::$updated = $pr['updated'];
    S::$press = read_json("$d/press.json");
    S::$laser = read_json("$d/laser.json");
    S::$matrix = read_json("$d/matrix.json");
    S::$site = read_json("$d/site.json");

    $visible = array_values(array_filter(S::$all, fn($p) => empty($p['hidden'])));
    S::$products = array_values(array_filter($visible, fn($p) => count($p['images'] ?? []) > 0));
    S::$stamps = array_values(array_filter(S::$products, fn($p) => $p['category'] !== 'equipment' || preg_match('/набор/iu', $p['title'])));
    S::$pressProduct = null;
    foreach (S::$all as $p) if ($p['sku'] === S::$press['ozonSku']) { S::$pressProduct = $p; break; }
    S::$minPrice = S::$stamps ? min(array_map(fn($p) => $p['price'], S::$stamps)) : 0;
    S::$credit = 'https://sitomika.ru/?utm_source=' . S::$c['creditSlug'] . '&amp;utm_medium=footer&amp;utm_campaign=client-sites';
    S::$svc = services();
}

// ---------- форматирование ----------
// Склонение после числа: plural(63, ['позиция','позиции','позиций']) -> '63 позиции'
function plural(int $n, array $f): string
{
    $m10 = $n % 10; $m100 = $n % 100;
    return $n . ' ' . ($m10 === 1 && $m100 !== 11 ? $f[0] : ($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14) ? $f[1] : $f[2]));
}
function pos_n(int $n): string { return plural($n, ['позиция', 'позиции', 'позиций']); }
function esc($s): string { return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], (string)($s ?? '')); }
function rub($n): string { return number_format((float)$n, 0, '', ' ') . ' ₽'; }
function img(array $p, int $n = 1, bool $small = false): string { return '/img/products/' . $p['sku'] . '/' . $n . ($small ? '-s' : '') . '.webp'; }
function cat_name(string $id): string { foreach (S::$cats as $c) if ($c['id'] === $id) return $c['name']; return ''; }
function cat_count(string $id): int { return count(array_filter(S::$stamps, fn($p) => $p['category'] === $id)); }
function ucfirst_u(string $s): string { return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1); }
function map_join(array $list, callable $fn, string $sep = ''): string { return implode($sep, array_map($fn, $list, array_keys($list))); }
// JSON для <script>: «<» и «>» как < и >, чтобы текст из данных не мог закрыть тег
function jsenc($v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_HEX_TAG); }
// Версия css/js в ссылках — по содержимому файла
function ver(string $rel): string
{
    static $cache = [];
    return $cache[$rel] ??= substr(md5_file(rtrim(cfg('public_dir'), '/\\') . '/' . $rel), 0, 8);
}

// ---------- иконки ----------
const I = [
    'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
    'max' => '<img class="brand-ico" src="/img/site/max.png" alt="" width="20" height="20">', // оригинальный значок с max.ru
    'vk' => '<img class="brand-ico" src="/img/site/vk.svg" alt="" width="20" height="20">', // vk.com/brand, #0077FF
    'star' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
    'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>',
    'bolt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 3 5 14h6l-1 7 8-11h-6z"/></svg>',
    'ruler' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 17 14-14 4 4L7 21z"/><path d="m7 13 2 2M10 10l2 2M13 7l2 2"/></svg>',
    'doc' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>',
    'box' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/></svg>',
    'repeat' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'upload' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v4h16v-4"/></svg>',
    'ozon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/></svg>',
    'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
    'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
    'pin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.3-7-12a7 7 0 0 1 14 0c0 5.7-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
];
const LOGO_MARK = '<svg class="logo-mark" viewBox="128 180 323 290" aria-hidden="true"><path d="M414.594 244.75H310.281L346.337 180.008H450.65L414.594 244.75Z" fill="#2146A3"/><path d="M164.056 244.75H265.875L215.872 337.838H289.345L321.773 277.518C327.48 267.88 330.73 256.655 330.73 244.674C330.73 208.958 301.779 180.008 266.064 180.008H128L164.056 244.75Z" fill="url(#lg)"/><path d="M253.289 405.072L289.345 469.777L343.694 373.552L362.818 337.836H289.345L253.289 405.072Z" fill="#E53724"/><defs><linearGradient id="lg" x1="282.929" y1="328.63" x2="175.803" y2="189.2" gradientUnits="userSpaceOnUse"><stop stop-color="#E53724"/><stop offset="1" stop-color="#961900"/></linearGradient></defs></svg>';

// ---------- общий каркас ----------
const NAV = [
    ['/catalog/', 'Клейма'],
    ['/pressy/', 'Прессы'],
    ['/matricy/', 'Матрицы'],
    ['/gravirovka/', 'Гравировка'],
    ['/#constructor', 'Конструктор'],
    ['/#delivery', 'Доставка'],
    ['/#contacts', 'Контакты'],
];

function layout(array $o): string
{
    $C = S::$c;
    $o += ['jsonld' => [], 'ogImage' => '/img/site/og.jpg', 'bodyClass' => ''];
    $url = $C['siteUrl'] . $o['canonical'];
    $t = esc($o['title']); $d = esc($o['description']);
    return '<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $t . '</title>
<meta name="description" content="' . $d . '">
<link rel="canonical" href="' . $url . '">
<meta property="og:type" content="website">
<meta property="og:site_name" content="' . $C['brand'] . '">
<meta property="og:title" content="' . $t . '">
<meta property="og:description" content="' . $d . '">
<meta property="og:url" content="' . $url . '">
<meta property="og:image" content="' . $C['siteUrl'] . $o['ogImage'] . '">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#14161b">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/img/site/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=' . ver('css/style.css') . '">
' . map_join($o['jsonld'], fn($j) => '<script type="application/ld+json">' . jsenc($j) . '</script>', "\n") . '
</head>
<body class="' . $o['bodyClass'] . '" data-phone="' . $C['phone'] . '" data-phone-raw="' . $C['phoneRaw'] . '" data-max="' . $C['max'] . '">
<a class="skip" href="#main">К содержимому</a>
' . site_header() . '
<main id="main">
' . $o['body'] . '
</main>
' . site_footer() . '
' . order_modal() . '
<div class="lb" id="lb" role="dialog" aria-modal="true" aria-label="Просмотр фото" hidden>
  <button class="lb__btn lb__x" type="button" data-lb-close aria-label="Закрыть">' . I['close'] . '</button>
  <button class="lb__btn lb__prev" type="button" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
  <img class="lb__img" alt="">
  <button class="lb__btn lb__next" type="button" aria-label="Следующее фото"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
  <span class="lb__count" aria-live="polite"></span>
</div>
<div class="cookie" id="cookie" hidden>
  <p>Сайт использует файлы cookie, чтобы корректно работать и показывать встроенную карту. Подробнее — в <a href="/politika/">политике конфиденциальности</a>.</p>
  <button class="btn btn--sm" type="button" id="cookie-ok">Понятно</button>
</div>
<script src="/js/main.js?v=' . ver('js/main.js') . '" defer></script>
</body>
</html>';
}

function site_header(): string
{
    $C = S::$c;
    return '<header class="hdr" id="hdr">
  <div class="wrap hdr__in">
    <a class="logo" href="/" aria-label="' . $C['brand'] . ' — на главную">' . LOGO_MARK . '<span class="logo__txt"><b>' . $C['brand'] . '</b><small>клейма · прессы</small></span></a>
    <nav class="nav" id="nav" aria-label="Основное меню">
      ' . map_join(NAV, fn($n) => '<a href="' . $n[0] . '">' . $n[1] . '</a>') . '
    </nav>
    <div class="hdr__act">
      <a class="hdr__phone" href="tel:' . $C['phoneRaw'] . '">' . $C['phone'] . '</a>
      <a class="icon-btn icon-btn--brand" href="' . $C['max'] . '" target="_blank" rel="noopener" aria-label="Написать в MAX">' . I['max'] . '</a>
      <button class="btn btn--sm hdr__cta" type="button" data-order>Заказать</button>
      <button class="icon-btn burger" type="button" id="burger" aria-label="Меню" aria-expanded="false" aria-controls="nav">' . I['menu'] . '</button>
    </div>
  </div>
</header>';
}

function site_footer(): string
{
    $C = S::$c;
    return '<footer class="ftr">
  <div class="wrap ftr__grid">
    <div class="ftr__brand">
      <a class="logo logo--light" href="/">' . LOGO_MARK . '<span class="logo__txt"><b>' . $C['brand'] . '</b><small>клейма · прессы</small></span></a>
      <p>Ударные клейма, гидравлические прессы, матрицы и пуансоны, лазерная маркировка. Производство в Перми, отправка по всей России.</p>
      <span class="ozon-badge">' . I['star'] . '<b>' . $C['ozonStats']['rating'] . '</b> · ' . $C['ozonStats']['reviews'] . ' отзывов покупателей</span>
    </div>
    <div>
      <h3>Разделы</h3>
      <ul>' . map_join(NAV, fn($n) => '<li><a href="' . $n[0] . '">' . $n[1] . '</a></li>') . '</ul>
    </div>
    <div>
      <h3>Каталог</h3>
      <ul>' . map_join(array_values(array_filter(S::$cats, fn($c) => $c['id'] !== 'equipment')), fn($c) => '<li><a href="/catalog/#' . $c['id'] . '">' . $c['name'] . '</a></li>') . '<li><a href="/pressy/">Гидравлические прессы</a></li><li><a href="/matricy/">Матрицы и пуансоны</a></li><li><a href="/gravirovka/">Лазерная гравировка</a></li></ul>
    </div>
    <div>
      <h3>Контакты</h3>
      <ul class="ftr__contacts">
        <li><a href="tel:' . $C['phoneRaw'] . '">' . I['phone'] . $C['phone'] . '</a></li>
        <li><a href="mailto:' . $C['email'] . '">' . I['mail'] . $C['email'] . '</a></li>
        <li><span>' . I['pin'] . $C['address'] . '</span></li>
      </ul>
      <div class="ftr__soc">
        <a class="icon-btn icon-btn--brand" href="' . $C['max'] . '" target="_blank" rel="noopener" aria-label="MAX">' . I['max'] . '</a>
        <a class="icon-btn icon-btn--brand" href="' . $C['vk'] . '" target="_blank" rel="noopener" aria-label="ВКонтакте">' . I['vk'] . '</a>
      </div>
    </div>
  </div>
  <div class="wrap ftr__bottom">
    <span>© ' . date('Y') . ' ' . $C['legalName'] . ' · ИНН ' . $C['inn'] . ' · ОГРН ' . $C['ogrn'] . '</span>
    <span class="ftr__links">
      <a href="/politika/">Политика конфиденциальности</a>
      <a href="/soglasie/">Согласие на обработку данных</a>
      <a class="footer-credit" href="' . S::$credit . '" target="_blank" rel="noopener">Разработано в sitomika.ru</a>
    </span>
  </div>
</footer>';
}

function lead_form(array $o): string
{
    $o += ['title' => '', 'compact' => false, 'withFile' => true, 'withConfig' => false];
    return '<form class="form' . ($o['compact'] ? ' form--compact' : '') . '" action="/send.php" method="post" enctype="multipart/form-data" data-lead novalidate>
  ' . ($o['title'] ? '<h3 class="form__title">' . $o['title'] . '</h3>' : '') . '
  <input type="hidden" name="source" value="' . $o['id'] . '">
  <input type="hidden" name="product" value="">
  ' . ($o['withConfig'] ? '<input type="hidden" name="config" value="">' : '') . '
  <div class="hp" aria-hidden="true"><label>Не заполняйте это поле <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <div class="form__row">
    <label class="fld"><span>Имя</span><input type="text" name="name" autocomplete="name" placeholder="Как к вам обращаться"></label>
    <label class="fld"><span>Телефон *</span><input type="tel" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__" required></label>
  </div>
  <label class="fld"><span>Что нужно сделать</span><textarea name="message" rows="3" placeholder="Текст клейма, размер, количество — или задача для пресса"></textarea></label>
  ' . ($o['withFile'] ? '<label class="file"><input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.dxf,.dwg,.cdr,.ai,.svg,.webp"><span class="file__ico">' . I['upload'] . '</span><span class="file__txt">Прикрепить эскиз или чертёж <small>JPG, PNG, PDF, DXF, CDR — до 10 МБ</small></span></label>' : '') . '
  <label class="consent"><input type="checkbox" name="consent" value="1" checked required><span>Даю <a href="/soglasie/" target="_blank">согласие на обработку персональных данных</a> в соответствии с <a href="/politika/" target="_blank">политикой</a></span></label>
  <button class="btn btn--block" type="submit">Отправить заявку ' . I['arrow'] . '</button>
  <p class="form__msg" role="status" aria-live="polite"></p>
</form>';
}

function order_modal(): string
{
    return '<div class="modal" id="order" role="dialog" aria-modal="true" aria-labelledby="order-title" hidden>
  <div class="modal__bg" data-close></div>
  <div class="modal__box">
    <button class="icon-btn modal__x" type="button" data-close aria-label="Закрыть">' . I['close'] . '</button>
    <h2 id="order-title">Заявка</h2>
    <p class="modal__sub" id="order-sub">Перезвоним в рабочее время, уточним детали и сроки.</p>
    ' . lead_form(['id' => 'modal', 'compact' => true]) . '
    <p class="modal__alt">Быстрее в мессенджере: <a href="' . S::$c['max'] . '" target="_blank" rel="noopener">написать в MAX</a></p>
  </div>
</div>';
}

function card(array $p, bool $eager = false): string
{
    $disc = !empty($p['oldPrice']) ? (int)round((1 - $p['price'] / $p['oldPrice']) * 100) : 0;
    return '<article class="card" data-cat="' . $p['category'] . '" data-price="' . $p['price'] . '" data-idx="' . $p['order'] . '" data-q="' . esc(mb_strtolower($p['title'] . ' ' . $p['short'])) . '">
  <a class="card__img" href="/catalog/' . $p['slug'] . '/" tabindex="-1" aria-hidden="true">
    <img src="' . img($p, 1, true) . '" alt="" width="300" height="400" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async">
    ' . ($disc ? '<span class="tag tag--sale">−' . $disc . '%</span>' : '') . '
  </a>
  <div class="card__body">
    <span class="card__cat">' . cat_name($p['category']) . '</span>
    <h3 class="card__title"><a href="/catalog/' . $p['slug'] . '/">' . esc($p['short']) . '</a></h3>
    <div class="card__meta">' . (!empty($p['rating']) ? '<span class="rating">' . I['star'] . $p['rating'] . '<small>' . esc($p['reviews'] ?? '') . '</small></span>' : '<span class="rating rating--none">Новинка</span>') . '</div>
    <div class="card__foot">
      <div class="price"><b>' . rub($p['price']) . '</b>' . (!empty($p['oldPrice']) ? '<s>' . rub($p['oldPrice']) . '</s>' : '') . '</div>
      <button class="btn btn--sm" type="button" data-order data-product="' . esc($p['title']) . ' (арт. ' . $p['sku'] . ')" aria-label="Заказать «' . esc($p['short']) . '»">Заказать</button>
    </div>
  </div>
</article>';
}

// ---------- JSON-LD ----------
function org_ld(): array
{
    $C = S::$c;
    return [
        '@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $C['brand'], 'legalName' => $C['legalName'], 'url' => $C['siteUrl'],
        'logo' => $C['siteUrl'] . '/img/site/logo-mark.svg', 'image' => $C['siteUrl'] . '/img/site/og.jpg', 'telephone' => $C['phoneRaw'], 'email' => $C['email'],
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $C['street'], 'addressLocality' => $C['city'], 'addressCountry' => 'RU'],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $C['geo']['lat'], 'longitude' => $C['geo']['lon']],
        'taxID' => $C['inn'], 'sameAs' => [$C['vk'], $C['ozonSeller']],
    ];
}
function faq_ld(array $faq): array
{
    return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)];
}
function crumbs_ld(array $items): array
{
    return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => map_join_arr($items, fn($it, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => S::$c['siteUrl'] . $it[1]])];
}
function map_join_arr(array $list, callable $fn): array { return array_map($fn, $list, array_keys($list)); }
function provider_ld(): array
{
    $C = S::$c;
    return ['@type' => 'LocalBusiness', 'name' => $C['brand'], 'telephone' => $C['phoneRaw'], 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $C['street'], 'addressLocality' => $C['city'], 'addressCountry' => 'RU']];
}
