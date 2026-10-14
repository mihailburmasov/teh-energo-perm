// Сборка статического сайта в dist/: node scripts/build.js
// Источники правды: data/company.json (факты о компании), data/products.json (каталог), data/press.json (прессы).
const fs = require('fs'), path = require('path');
const ROOT = path.join(__dirname, '..');
// Демо-режим для GitHub Pages: DEMO_BASE=/teh-energo-perm node scripts/build.js
// Сайт кладётся в .demo/, абсолютные пути получают префикс, страницы закрыты от индексации, формы нет.
const BASE = (process.env.DEMO_BASE || '').replace(/\/$/, '');
const DIST = path.join(ROOT, BASE ? '.demo' : 'dist');
const C = require('../data/company.json');
const { categories: CATS, products: ALL, updated } = require('../data/products.json');
const PRESS = require('../data/press.json');
const LASER = require('../data/laser.json');
const MATRIX = require('../data/matrix.json');

const PRODUCTS = ALL.filter(p => p.images.length);
const STAMPS = PRODUCTS.filter(p => p.category !== 'equipment' || /набор/i.test(p.title));
const PRESS_PRODUCT = ALL.find(p => p.sku === PRESS.ozonSku);
const minPrice = Math.min(...STAMPS.map(p => p.price));
const UPDATED_RU = updated.split('-').reverse().join('.');
const CREDIT = `https://sitomika.ru/?utm_source=${C.creditSlug}&amp;utm_medium=footer&amp;utm_campaign=client-sites`;

// Склонение после числа: plural(63, ['позиция','позиции','позиций']) -> '63 позиции'
const plural = (n, [one, few, many]) => { const m10 = n % 10, m100 = n % 100; return `${n} ${m10 === 1 && m100 !== 11 ? one : m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14) ? few : many}`; };
const POS = n => plural(n, ['позиция', 'позиции', 'позиций']);
const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const rub = n => n.toLocaleString('ru-RU').replace(/ /g, ' ') + ' ₽';
const img = (p, n = 1, small = false) => `/img/products/${p.sku}/${n}${small ? '-s' : ''}.webp`;
const catName = id => (CATS.find(c => c.id === id) || {}).name || '';
const count = id => STAMPS.filter(p => p.category === id).length;

// ---------- иконки ----------
const I = {
  arrow: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
  phone: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg>',
  tg: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 4 3 11l6 2 2 6 3-4 5 4z"/><path d="m9 13 12-9"/></svg>',
  max: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 0 1 0 18c-1.6 0-3.1-.4-4.4-1.2L3 21l1.2-4.4A9 9 0 0 1 12 3z"/><path d="M8 15V9l4 4 4-4v6"/></svg>',
  vk: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h3.2c.4 3 1.8 5 3.3 5.4V7h3v3c1.4-.2 2.7-1.6 3.4-3H19c-.5 2-2 3.6-3.2 4.3C17 12 18.8 13.6 19.5 16h-3.3c-.6-1.5-1.9-2.8-3.7-3v3h-.4C7 16 3.4 12.5 3 7z"/></svg>',
  star: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>',
  check: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4 10-10"/></svg>',
  bolt: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 3 5 14h6l-1 7 8-11h-6z"/></svg>',
  ruler: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 17 14-14 4 4L7 21z"/><path d="m7 13 2 2M10 10l2 2M13 7l2 2"/></svg>',
  doc: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg>',
  box: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/></svg>',
  repeat: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/></svg>',
  clock: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  upload: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 16v4h16v-4"/></svg>',
  ozon: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3.5"/></svg>',
  menu: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
  close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>',
  search: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
  pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7-6.3-7-12a7 7 0 0 1 14 0c0 5.7-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
  mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
};
const LOGO_MARK = `<svg class="logo-mark" viewBox="128 180 323 290" aria-hidden="true"><path d="M414.594 244.75H310.281L346.337 180.008H450.65L414.594 244.75Z" fill="#2146A3"/><path d="M164.056 244.75H265.875L215.872 337.838H289.345L321.773 277.518C327.48 267.88 330.73 256.655 330.73 244.674C330.73 208.958 301.779 180.008 266.064 180.008H128L164.056 244.75Z" fill="url(#lg)"/><path d="M253.289 405.072L289.345 469.777L343.694 373.552L362.818 337.836H289.345L253.289 405.072Z" fill="#E53724"/><defs><linearGradient id="lg" x1="282.929" y1="328.63" x2="175.803" y2="189.2" gradientUnits="userSpaceOnUse"><stop stop-color="#E53724"/><stop offset="1" stop-color="#961900"/></linearGradient></defs></svg>`;

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

function layout({ title, description, canonical, body, jsonld = [], ogImage = '/img/site/og.jpg', bodyClass = '' }) {
  const url = C.siteUrl + canonical;
  return `<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">${BASE ? '\n<meta name="robots" content="noindex, nofollow">' : ''}
<title>${esc(title)}</title>
<meta name="description" content="${esc(description)}">
<link rel="canonical" href="${url}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="${C.brand}">
<meta property="og:title" content="${esc(title)}">
<meta property="og:description" content="${esc(description)}">
<meta property="og:url" content="${url}">
<meta property="og:image" content="${C.siteUrl}${ogImage}">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#14161b">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/img/site/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/style.css?v=${BUILD_ID}">
${jsonld.map(j => `<script type="application/ld+json">${JSON.stringify(j)}</script>`).join('\n')}
</head>
<body class="${bodyClass}" data-phone="${C.phone}" data-phone-raw="${C.phoneRaw}" data-tg="${C.telegram}">
<a class="skip" href="#main">К содержимому</a>
${header()}
<main id="main">
${body}
</main>
${footer()}
${orderModal()}
<div class="lb" id="lb" role="dialog" aria-modal="true" aria-label="Просмотр фото" hidden>
  <button class="lb__btn lb__x" type="button" data-lb-close aria-label="Закрыть">${I.close}</button>
  <button class="lb__btn lb__prev" type="button" aria-label="Предыдущее фото"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
  <img class="lb__img" alt="">
  <button class="lb__btn lb__next" type="button" aria-label="Следующее фото"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
  <span class="lb__count" aria-live="polite"></span>
</div>
<div class="cookie" id="cookie" hidden>
  <p>Сайт использует файлы cookie, чтобы корректно работать и показывать встроенную карту. Подробнее — в <a href="/politika/">политике конфиденциальности</a>.</p>
  <button class="btn btn--sm" type="button" id="cookie-ok">Понятно</button>
</div>
<script src="/js/main.js?v=${BUILD_ID}" defer></script>
</body>
</html>`;
}

function header() {
  return `<header class="hdr" id="hdr">
  <div class="wrap hdr__in">
    <a class="logo" href="/" aria-label="${C.brand} — на главную">${LOGO_MARK}<span class="logo__txt"><b>${C.brand}</b><small>клейма · прессы</small></span></a>
    <nav class="nav" id="nav" aria-label="Основное меню">
      ${NAV.map(([h, t]) => `<a href="${h}">${t}</a>`).join('')}
    </nav>
    <div class="hdr__act">
      <a class="hdr__phone" href="tel:${C.phoneRaw}">${C.phone}</a>
      <a class="icon-btn" href="${C.max}" target="_blank" rel="noopener" aria-label="Написать в MAX">${I.max}</a>
      <a class="icon-btn hdr__tg" href="${C.telegram}" target="_blank" rel="noopener" aria-label="Написать в Telegram">${I.tg}</a>
      <button class="btn btn--sm hdr__cta" type="button" data-order>Заказать</button>
      <button class="icon-btn burger" type="button" id="burger" aria-label="Меню" aria-expanded="false" aria-controls="nav">${I.menu}</button>
    </div>
  </div>
</header>`;
}

function footer() {
  return `<footer class="ftr">
  <div class="wrap ftr__grid">
    <div class="ftr__brand">
      <a class="logo logo--light" href="/">${LOGO_MARK}<span class="logo__txt"><b>${C.brand}</b><small>клейма · прессы</small></span></a>
      <p>Ударные клейма, гидравлические прессы, матрицы и пуансоны, лазерная маркировка. Производство в Перми, отправка по всей России.</p>
      <span class="ozon-badge">${I.star}<b>${C.ozonStats.rating}</b> · ${C.ozonStats.reviews} отзывов покупателей</span>
    </div>
    <div>
      <h3>Разделы</h3>
      <ul>${NAV.map(([h, t]) => `<li><a href="${h}">${t}</a></li>`).join('')}</ul>
    </div>
    <div>
      <h3>Каталог</h3>
      <ul>${CATS.filter(c => c.id !== 'equipment').map(c => `<li><a href="/catalog/#${c.id}">${c.name}</a></li>`).join('')}<li><a href="/pressy/">Гидравлические прессы</a></li><li><a href="/matricy/">Матрицы и пуансоны</a></li><li><a href="/gravirovka/">Лазерная гравировка</a></li></ul>
    </div>
    <div>
      <h3>Контакты</h3>
      <ul class="ftr__contacts">
        <li><a href="tel:${C.phoneRaw}">${I.phone}${C.phone}</a></li>
        <li><a href="mailto:${C.email}">${I.mail}${C.email}</a></li>
        <li><span>${I.pin}${C.address}</span></li>
      </ul>
      <div class="ftr__soc">
        <a class="icon-btn" href="${C.max}" target="_blank" rel="noopener" aria-label="MAX">${I.max}</a>
        <a class="icon-btn" href="${C.telegram}" target="_blank" rel="noopener" aria-label="Telegram">${I.tg}</a>
        <a class="icon-btn" href="${C.vk}" target="_blank" rel="noopener" aria-label="ВКонтакте">${I.vk}</a>
      </div>
    </div>
  </div>
  <div class="wrap ftr__bottom">
    <span>© ${new Date().getFullYear()} ${C.legalName} · ИНН ${C.inn} · ОГРН ${C.ogrn}</span>
    <span class="ftr__links">
      <a href="/politika/">Политика конфиденциальности</a>
      <a href="/soglasie/">Согласие на обработку данных</a>
      <a class="footer-credit" href="${CREDIT}" target="_blank" rel="noopener">Разработано в sitomika.ru</a>
    </span>
  </div>
</footer>`;
}

function leadForm({ id, title = '', compact = false, withFile = true, withConfig = false }) {
  return `<form class="form${compact ? ' form--compact' : ''}" action="/send.php" method="post" enctype="multipart/form-data" data-lead novalidate>
  ${title ? `<h3 class="form__title">${title}</h3>` : ''}
  <input type="hidden" name="source" value="${id}">
  <input type="hidden" name="product" value="">
  ${withConfig ? '<input type="hidden" name="config" value="">' : ''}
  <div class="hp" aria-hidden="true"><label>Не заполняйте это поле <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
  <div class="form__row">
    <label class="fld"><span>Имя</span><input type="text" name="name" autocomplete="name" placeholder="Как к вам обращаться"></label>
    <label class="fld"><span>Телефон *</span><input type="tel" name="phone" autocomplete="tel" placeholder="+7 (___) ___-__-__" required></label>
  </div>
  <label class="fld"><span>Что нужно сделать</span><textarea name="message" rows="3" placeholder="Текст клейма, размер, количество — или задача для пресса"></textarea></label>
  ${withFile ? `<label class="file"><input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.dxf,.dwg,.cdr,.ai,.svg,.webp"><span class="file__ico">${I.upload}</span><span class="file__txt">Прикрепить эскиз или чертёж <small>JPG, PNG, PDF, DXF, CDR — до 10 МБ</small></span></label>` : ''}
  <label class="consent"><input type="checkbox" name="consent" value="1" checked required><span>Даю <a href="/soglasie/" target="_blank">согласие на обработку персональных данных</a> в соответствии с <a href="/politika/" target="_blank">политикой</a></span></label>
  <button class="btn btn--block" type="submit">Отправить заявку ${I.arrow}</button>
  <p class="form__msg" role="status" aria-live="polite"></p>
</form>`;
}

function orderModal() {
  return `<div class="modal" id="order" role="dialog" aria-modal="true" aria-labelledby="order-title" hidden>
  <div class="modal__bg" data-close></div>
  <div class="modal__box">
    <button class="icon-btn modal__x" type="button" data-close aria-label="Закрыть">${I.close}</button>
    <h2 id="order-title">Заявка</h2>
    <p class="modal__sub" id="order-sub">Перезвоним в рабочее время, уточним детали и сроки.</p>
    ${leadForm({ id: 'modal', compact: true })}
    <p class="modal__alt">Быстрее в мессенджере: <a href="${C.max}" target="_blank" rel="noopener">MAX</a> · <a href="${C.telegram}" target="_blank" rel="noopener">Telegram</a></p>
  </div>
</div>`;
}

function card(p, { eager = false } = {}) {
  const disc = p.oldPrice ? Math.round((1 - p.price / p.oldPrice) * 100) : 0;
  return `<article class="card" data-cat="${p.category}" data-price="${p.price}" data-order="${p.order}" data-q="${esc((p.title + ' ' + p.short).toLowerCase())}">
  <a class="card__img" href="/catalog/${p.slug}/" tabindex="-1" aria-hidden="true">
    <img src="${img(p, 1, true)}" alt="" width="300" height="400" loading="${eager ? 'eager' : 'lazy'}" decoding="async">
    ${disc ? `<span class="tag tag--sale">−${disc}%</span>` : ''}
  </a>
  <div class="card__body">
    <span class="card__cat">${catName(p.category)}</span>
    <h3 class="card__title"><a href="/catalog/${p.slug}/">${esc(p.short)}</a></h3>
    <div class="card__meta">${p.rating ? `<span class="rating">${I.star}${p.rating}<small>${esc(p.reviews || '')}</small></span>` : '<span class="rating rating--none">Новинка</span>'}</div>
    <div class="card__foot">
      <div class="price"><b>${rub(p.price)}</b>${p.oldPrice ? `<s>${rub(p.oldPrice)}</s>` : ''}</div>
      <button class="btn btn--sm" type="button" data-order data-product="${esc(p.title)} (арт. ${p.sku})" aria-label="Заказать «${esc(p.short)}»">Заказать</button>
    </div>
  </div>
</article>`;
}

// ---------- главная ----------
function home() {
  const popular = [...STAMPS].sort((a, b) => (parseInt(b.reviews) || 0) - (parseInt(a.reviews) || 0) || a.order - b.order).slice(0, 8);
  const heroStamps = ['2548412438', '4840849029', '1732570493'].map(s => PRODUCTS.find(p => String(p.sku) === s)).filter(Boolean);
  const faq = FAQ;
  const body = `
<section class="hero">
  <div class="hero__glow" aria-hidden="true"></div>
  <div class="wrap hero__in">
    <div class="hero__txt reveal">
      <p class="eyebrow"><span class="dot"></span>Собственное производство · ${C.city}</p>
      <h1>Ударные клейма <span class="hl">и&nbsp;гидравлические прессы</span></h1>
      <p class="lead">Маркировка металла под ключ: ${plural(STAMPS.length, ['готовое клеймо', 'готовых клейма', 'готовых клейм'])} — ОТК, EAC, марки стали, знаки — в наличии на складе. Изготовим клеймо по вашему эскизу от 1 дня и соберём пресс под вашу задачу.</p>
      <div class="hero__cta">
        <a class="btn btn--lg" href="/catalog/">Каталог клейм ${I.arrow}</a>
        <a class="btn btn--lg btn--ghost" href="/pressy/">Прессы с ЧПУ</a>
      </div>
      <ul class="hero__facts">
        <li><b>от 1 дня</b><span>изготовление клейма</span></li>
        <li><b>от 1 мм</b><span>высота шрифта</span></li>
        <li><b>${C.ozonStats.rating}<i>★</i></b><span>${C.ozonStats.reviews} отзывов покупателей</span></li>
        <li><b>${C.ozonStats.orders}</b><span>заказов от покупателей</span></li>
      </ul>
    </div>
    <div class="hero__vis reveal" aria-hidden="true">
      ${heroStamps.map((p, i) => `<img class="hero__stamp hero__stamp--${i + 1}" src="${img(p, 1)}" alt="" width="500" height="667" ${i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'}>`).join('')}
      <div class="sparks"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    </div>
  </div>
  <div class="ticker" aria-hidden="true"><div class="ticker__track">${[...Array(2)].map(() => STAMPS.slice(0, 24).map(p => `<span>${esc(p.short)}</span>`).join('')).join('')}</div></div>
</section>

<section class="sec" id="directions">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Что мы делаем</p><h2>Два направления — одно производство</h2></div>
    <div class="dirs">
      <a class="dir dir--stamps reveal" href="/catalog/">
        <div class="dir__txt">
          <span class="dir__num">01</span>
          <h3>Ударные клейма</h3>
          <p>Клейма ОТК, знаки соответствия, марки стали, символы. Сталь 9ХС и У8А с закалкой рабочей части. Готовые — на складе, индивидуальные — по эскизу.</p>
          <span class="dir__more">${POS(STAMPS.length)} · от ${rub(minPrice)} ${I.arrow}</span>
        </div>
        <div class="dir__imgs">${STAMPS.slice(3, 6).map(p => `<img src="${img(p, 1, true)}" alt="" width="300" height="400" loading="lazy">`).join('')}</div>
      </a>
      <a class="dir dir--press reveal" href="/pressy/">
        <div class="dir__txt">
          <span class="dir__num">02</span>
          <h3>Гидравлические прессы</h3>
          <p>Электрогидравлический пресс ${PRESS.model}: ${PRESS.force}, ЧПУ, пульт ДУ и педаль. Запрессовка, маркировка, гибка, штамповка и испытания материалов.</p>
          <span class="dir__more">Характеристики и заказ ${I.arrow}</span>
        </div>
        <div class="dir__imgs dir__imgs--one"><img src="${img(PRESS_PRODUCT, 2, true)}" alt="" width="300" height="400" loading="lazy"></div>
      </a>
    </div>
  </div>
</section>

<section class="sec sec--tint" id="catalog">
  <div class="wrap">
    <div class="sec__head sec__head--row reveal">
      <div><p class="eyebrow">Каталог</p><h2>Клейма в наличии</h2><p class="sec__sub">Оставьте заказ на сайте — перезвоним, подтвердим наличие и сроки доставки.</p></div>
      <a class="btn btn--ghost-dark" href="/catalog/">Весь каталог · ${STAMPS.length} ${I.arrow}</a>
    </div>
    <div class="cats reveal">
      ${CATS.filter(c => count(c.id)).map(c => `<a class="cat" href="/catalog/#${c.id}"><b>${c.short}</b><span>${count(c.id)}</span></a>`).join('')}
    </div>
    <div class="grid">${popular.map(p => card(p)).join('')}</div>
  </div>
</section>

<section class="sec sec--dark" id="constructor">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow eyebrow--light">Конструктор</p><h2>Соберите своё клеймо — и сразу увидите оттиск</h2><p class="sec__sub">Введите текст, выберите рамку и размер. Мы получим эту схему вместе с заявкой и подготовим макет бесплатно.</p></div>
    <div class="cons reveal">
      <div class="cons__ctrl">
        <label class="fld fld--dark"><span>Текст или номер</span><input type="text" id="c-text" maxlength="14" value="ОТК" autocomplete="off"></label>
        <fieldset class="seg"><legend>Рамка</legend>
          ${[['none', 'Без рамки'], ['circle', 'Круг'], ['triangle', 'Треугольник'], ['square', 'Квадрат'], ['pentagon', 'Пятиугольник']].map(([v, t], i) => `<label><input type="radio" name="c-frame" value="${v}"${i === 2 ? ' checked' : ''}><span>${t}</span></label>`).join('')}
        </fieldset>
        <div class="cons__two">
          <label class="fld fld--dark"><span>Высота символов: <output id="c-h-out">3</output> мм</span><input type="range" id="c-h" min="1" max="12" step="1" value="3"></label>
          <label class="fld fld--dark"><span>Диаметр заготовки</span><select id="c-d"><option value="10">⌀ 10 мм</option><option value="12">⌀ 12 мм</option><option value="16">⌀ 16 мм</option><option value="20" selected>⌀ 20 мм</option><option value="25">⌀ 25 мм</option></select></label>
        </div>
        <div class="cons__two">
          <fieldset class="seg seg--sm"><legend>Сталь</legend>
            <label><input type="radio" name="c-steel" value="9ХС" checked><span>9ХС</span></label>
            <label><input type="radio" name="c-steel" value="У8А"><span>У8А</span></label>
          </fieldset>
          <label class="fld fld--dark"><span>Количество, шт.</span><input type="number" id="c-qty" min="1" max="999" value="1"></label>
        </div>
        <p class="cons__note">${I.doc}Ориентир по цене: готовые клейма в каталоге — от ${rub(minPrice)}. Стоимость индивидуального клейма назовём после согласования макета.</p>
        <button class="btn btn--lg btn--block" type="button" id="c-send">Отправить схему в заявку ${I.arrow}</button>
      </div>
      <div class="cons__view">
        <div class="cons__tabs" role="tablist">
          <button type="button" role="tab" aria-selected="true" data-view="imprint">Оттиск на металле</button>
          <button type="button" role="tab" aria-selected="false" data-view="die">Рабочая часть клейма</button>
        </div>
        <div class="cons__stage" id="c-stage" data-view="imprint">
          <svg id="c-svg" viewBox="0 0 400 400" role="img" aria-label="Предпросмотр клейма"></svg>
        </div>
        <p class="cons__cap" id="c-cap"></p>
      </div>
    </div>
  </div>
</section>

<section class="sec" id="press">
  <div class="wrap press-teaser">
    <div class="press-teaser__img reveal">
      <img src="${img(PRESS_PRODUCT, 1)}" alt="Электрогидравлический пресс ${PRESS.model}" width="750" height="1000" loading="lazy">
    </div>
    <div class="press-teaser__txt reveal">
      <p class="eyebrow">Изготовление прессов</p>
      <h2>Пресс ${PRESS.model}: ${PRESS.force} с&nbsp;ЧПУ</h2>
      <p class="lead lead--dark">${PRESS.lead}</p>
      <dl class="specs specs--mini">
        ${PRESS.specs.slice(0, 6).map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}
      </dl>
      <div class="hero__cta">
        <a class="btn btn--lg" href="/pressy/">Подробнее о прессе ${I.arrow}</a>
        <button class="btn btn--lg btn--ghost-dark" type="button" data-order data-product="Пресс ${PRESS.model} / пресс под задачу">Обсудить задачу</button>
      </div>
    </div>
  </div>
</section>

<section class="sec sec--tint" id="why">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Почему ${C.brand}</p><h2>Делаем быстро, точно и официально</h2></div>
    <div class="bento">
      <div class="bx bx--big reveal"><span class="bx__ico">${I.clock}</span><h3>Срок — от 1 дня</h3><p>Быстро запускаем и оперативно выполняем заказы, строго в оговорённый срок. Популярные клейма и клише — на складе, без ожидания.</p><span class="bx__big">24 ч</span></div>
      <div class="bx reveal"><span class="bx__ico">${I.ruler}</span><h3>Шрифт от 1 мм</h3><p>Делаем даже сверхтонкую и мелкую маркировку.</p></div>
      <div class="bx reveal"><span class="bx__ico">${I.doc}</span><h3>Физлица и юрлица</h3><p>Договор, чек, гарантия — оформляем официально.</p></div>
      <div class="bx bx--accent reveal"><span class="bx__ico">${I.repeat}</span><h3>${C.returnRate} клиентов возвращаются</h3><p>Нас рекомендуют и заказывают снова — главный показатель качества.</p></div>
      <div class="bx reveal"><span class="bx__ico">${I.bolt}</span><h3>Закалённая сталь</h3><p>Инструментальные стали 9ХС и У8А, твёрдость рабочей части 50–58 HRC.</p></div>
    </div>
  </div>
</section>

<section class="sec" id="process">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Как заказать индивидуальное клеймо</p><h2>4 шага от эскиза до посылки</h2></div>
    <ol class="steps">
      <li class="reveal"><b>01</b><h3>Эскиз</h3><p>Пришлите текст, логотип или чертёж — или соберите схему в конструкторе.</p></li>
      <li class="reveal"><b>02</b><h3>Макет</h3><p>Подготовим макет, согласуем размеры, сталь и стоимость.</p></li>
      <li class="reveal"><b>03</b><h3>Производство</h3><p>Изготавливаем на оптоволоконном лазере и закаливаем рабочую часть.</p></li>
      <li class="reveal"><b>04</b><h3>Отправка</h3><p>СДЭК, Почта России или самовывоз в Перми.</p></li>
    </ol>
  </div>
</section>

<section class="sec sec--tint" id="laser">
  <div class="wrap laser">
    <div class="laser__txt reveal">
      <p class="eyebrow">Дополнительная услуга</p>
      <h2>Лазерная гравировка и маркировка</h2>
      <p>${LASER.lead}</p>
      <ul class="ticks">${LASER.services.slice(0, 5).map(([t, , p]) => `<li>${I.check}<span><b>${t}</b>${p ? ` — ${p}` : ''}</span></li>`).join('')}</ul>
      <p class="laser__note">${LASER.note}</p>
      <div class="hero__cta">
        <a class="btn" href="/gravirovka/">Подробнее о гравировке ${I.arrow}</a>
        <button class="btn btn--ghost-dark" type="button" data-order data-product="Лазерная гравировка">Рассчитать по макету</button>
      </div>
    </div>
    <div class="laser__img laser__img--duo reveal">
      <img src="${LASER.hero}" alt="Лазерная обработка металла" width="1200" height="789" loading="lazy">
      <img src="/img/site/eac-plates.jpg" alt="Таблички с лазерной маркировкой EAC" width="546" height="410" loading="lazy">
    </div>
  </div>
</section>

<section class="sec" id="matrix">
  <div class="wrap laser laser--rev">
    <div class="laser__txt reveal">
      <p class="eyebrow">Изготовление на заказ</p>
      <h2>Матрицы и пуансоны для тиснения</h2>
      <p>${MATRIX.lead}</p>
      <ul class="ticks">${MATRIX.works.slice(0, 4).map(([t]) => `<li>${I.check}<span><b>${t}</b></span></li>`).join('')}<li>${I.check}<span><b>Тиснение на коже, кожзаме и древесине</b></span></li></ul>
      <p class="laser__note">Цена — ${MATRIX.price}, зависит от размера и сложности рисунка.</p>
      <div class="hero__cta">
        <a class="btn" href="/matricy/">Подробнее о матрицах ${I.arrow}</a>
        <button class="btn btn--ghost-dark" type="button" data-order data-product="Матрица и пуансон">Рассчитать по образцу</button>
      </div>
    </div>
    <div class="laser__img laser__img--duo laser__img--light reveal">
      <img src="/img/matrix/caps.jpg" alt="Матрицы и колпачки с тиснением логотипов" width="1400" height="1050" loading="lazy">
      <img src="/img/matrix/pair-s.jpg" alt="Пара «матрица — пуансон»" width="640" height="480" loading="lazy">
    </div>
  </div>
</section>

<section class="sec" id="reviews">
  <div class="wrap reviews reveal">
    <div class="reviews__score">
      <span class="reviews__num">${C.ozonStats.rating}</span>
      <span class="reviews__stars">${I.star.repeat(5)}</span>
      <p>Средняя оценка покупателей<br>${C.ozonStats.reviews} отзывов · ${C.ozonStats.orders} заказов</p>
    </div>
    <div class="reviews__txt">
      <h2>Нам доверяют покупатели</h2>
      <p>Оценки оставлены реальными покупателями наших клейм на маркетплейсе — мы их не переписываем и не редактируем. ${C.returnRate} клиентов возвращаются за новыми заказами.</p>
      <a class="link-quiet" href="${C.ozonSeller}" target="_blank" rel="noopener">Посмотреть отзывы на Ozon</a>
    </div>
  </div>
</section>

<section class="sec sec--tint" id="delivery">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Оплата и доставка</p><h2>Удобно платить, быстро получать</h2></div>
    <div class="pay">
      <div class="pay__item reveal"><h3>Оплата</h3><p>Банковской картой, переводом на расчётный счёт или наличными при самовывозе. После подтверждения оплаты приступаем к работе.</p></div>
      <div class="pay__item reveal"><h3>Доставка</h3><p>Отправляем СДЭК и Почтой России. Стоимость доставки оплачивает покупатель, сроки зависят от способа и адреса.</p></div>
      <div class="pay__item reveal"><h3>Самовывоз</h3><p>${C.address}. Заранее предупредите о визите по телефону.</p></div>
      <div class="pay__item reveal"><h3>Для юрлиц</h3><p>Работаем по договору: выставляем счёт, предоставляем чек и гарантию. Подходит для закупки на предприятие.</p></div>
    </div>
  </div>
</section>

<section class="sec" id="faq">
  <div class="wrap faq-wrap">
    <div class="sec__head reveal"><p class="eyebrow">Вопросы</p><h2>Частые вопросы</h2></div>
    <div class="faq">${faq.map(([q, a]) => `<details class="reveal"><summary>${q}</summary><p>${a}</p></details>`).join('')}</div>
  </div>
</section>

<section class="sec sec--dark" id="contacts">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Контакты</p>
      <h2>Нет нужного клейма? Сделаем по вашему эскизу</h2>
      <p class="lead">Оставьте телефон — менеджер свяжется в течение часа в рабочее время и предложит решение.</p>
      <ul class="contacts__list">
        <li><a href="tel:${C.phoneRaw}">${I.phone}${C.phone}</a></li>
        <li><a href="mailto:${C.email}">${I.mail}${C.email}</a></li>
        <li><span>${I.pin}${C.address}</span></li>
      </ul>
      <div class="contacts__msg">
        <a class="btn btn--max" href="${C.max}" target="_blank" rel="noopener">${I.max}MAX</a>
        <a class="btn btn--tg" href="${C.telegram}" target="_blank" rel="noopener">${I.tg}Telegram</a>
      </div>
    </div>
    <div class="contacts__form reveal">${leadForm({ id: 'contacts', title: 'Заявка на изготовление', withConfig: true })}</div>
  </div>
  <div class="wrap map reveal"><iframe title="${C.brand} на карте" src="https://yandex.ru/map-widget/v1/?ll=${C.geo.lon}%2C${C.geo.lat}&amp;z=16&amp;pt=${C.geo.lon}%2C${C.geo.lat}%2Cpm2rdm" loading="lazy" allowfullscreen></iframe></div>
</section>`;

  return layout({
    title: `${C.brand} — ударные клейма и гидравлические прессы | ${C.city}`,
    description: `Ударные клейма по металлу: ОТК, EAC, марки стали, знаки — ${POS(STAMPS.length)} в наличии, от ${rub(minPrice)}. Изготовление клейм по эскизу от 1 дня. Гидравлические прессы с ЧПУ. Доставка по России.`,
    canonical: '/', body, bodyClass: 'home',
    jsonld: [orgLd(), faqLd(faq)],
  });
}

const FAQ = [
  ['Какие сроки изготовления?', 'Минимальный срок — от 1 дня, всё зависит от сложности и объёма. Популярные клейма есть в наличии и отправляются сразу.'],
  ['Можно ли сделать клеймо по моему эскизу или логотипу?', 'Да. Пришлите эскиз, логотип или чертёж через форму — или соберите схему в конструкторе на сайте. Подготовим макет и согласуем его до начала работ.'],
  ['Из какой стали делаете клейма?', 'Используем инструментальные стали 9ХС и У8А с закалкой рабочей части. Твёрдость рабочей части — 50–58 HRC.'],
  ['Какой минимальный размер шрифта?', 'Делаем клейма со шрифтом от 1 мм — подходит для мелкой и сверхтонкой маркировки.'],
  ['Как оплатить заказ?', 'Банковской картой, переводом на расчётный счёт или наличными при самовывозе. Работаем с физическими и юридическими лицами: договор, чек, гарантия.'],
  ['Как доставляете?', 'СДЭК и Почтой России, стоимость доставки оплачивает покупатель.'],
  ['Делаете лазерную гравировку?', 'Да, это наша дополнительная услуга: шильдики, таблички, бирки, клише для тиснения, логотипы и номера на деталях, гравировка по фото. Работаем на оптоволоконном лазере по металлу, пластику и коже. Пришлите макет — назовём цену и срок.'],
  ['Делаете матрицы и пуансоны для тиснения?', 'Да. Изготавливаем матрицы и пуансоны для тиснения логотипов и надписей на колпачках, крышках и пробках, а также на коже, кожзаме и древесине — по файлу логотипа или образцу изделия, в том числе серией одинаковых комплектов. Цена — от 2 000 ₽, зависит от размера и сложности. Пришлите логотип и размеры — рассчитаем.'],
  ['Можно ли изготовить пресс под нашу задачу?', `Да. Базовая модель — ${PRESS.model} (${PRESS.force}, ЧПУ). Программу ЧПУ можно скорректировать под ваши алгоритмы, а при снятии боковых панелей пресс встраивается в линию. Опишите задачу в заявке — предложим решение.`],
];

// ---------- каталог ----------
function catalogPage() {
  const body = `
<section class="page-hero">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Каталог клейм</span></nav>
    <h1>Ударные клейма по металлу</h1>
    <p class="lead lead--dark">${POS(STAMPS.length)} в наличии. Нужно своё клеймо? <a href="/#constructor">Соберите его в конструкторе</a>.</p>
  </div>
</section>
<section class="sec sec--catalog">
  <div class="wrap">
    <div class="filters" id="filters">
      <div class="chips" role="group" aria-label="Категории">
        <button type="button" class="chip is-on" data-cat="all">Все <span>${STAMPS.length}</span></button>
        ${CATS.filter(c => count(c.id)).map(c => `<button type="button" class="chip" data-cat="${c.id}">${c.short} <span>${count(c.id)}</span></button>`).join('')}
      </div>
      <div class="filters__right">
        <label class="search">${I.search}<input type="search" id="q" placeholder="Поиск: ОТК, 12Х18Н10Т, якорь…" aria-label="Поиск по каталогу"></label>
        <select id="sort" aria-label="Сортировка"><option value="order">По популярности</option><option value="asc">Сначала дешевле</option><option value="desc">Сначала дороже</option></select>
      </div>
    </div>
    <p class="cat-desc" id="cat-desc"></p>
    <div class="grid" id="grid">${STAMPS.map((p, i) => card(p, { eager: i < 4 })).join('')}</div>
    <p class="empty" id="empty" hidden>Ничего не нашлось. <button type="button" class="link" data-order>Оставьте заявку</button> — сделаем клеймо по вашему эскизу.</p>
    <aside class="cta-band">
      <div><h2>Не нашли нужное клеймо?</h2><p>Изготовим по эскизу от 1 дня: любой текст, номер, логотип, шрифт от 1 мм.</p></div>
      <div class="cta-band__btns"><a class="btn btn--lg" href="/#constructor">Конструктор клейма</a><button class="btn btn--lg btn--ghost" type="button" data-order>Оставить заявку</button></div>
    </aside>
  </div>
</section>
<script>window.CATS=${JSON.stringify(Object.fromEntries(CATS.map(c => [c.id, c.desc])))}</script>`;
  return layout({
    title: `Каталог ударных клейм по металлу — ОТК, EAC, марки стали | ${C.brand}`,
    description: `Каталог ударных клейм: ОТК, EAC, РСТ, марки стали (12Х18Н10Т, AISI 316, 09Г2С), символы и знаки. ${POS(STAMPS.length)} в наличии от ${rub(minPrice)}. Заказ напрямую у производителя.`,
    canonical: '/catalog/', body, bodyClass: 'catalog',
    jsonld: [crumbsLd([['Главная', '/'], ['Каталог клейм', '/catalog/']]), {
      '@context': 'https://schema.org', '@type': 'ItemList', name: 'Каталог ударных клейм',
      itemListElement: STAMPS.map((p, i) => ({ '@type': 'ListItem', position: i + 1, url: `${C.siteUrl}/catalog/${p.slug}/`, name: p.title })),
    }],
  });
}

const CAT_TEXT = {
  otk: 'Клеймо ОТК ставят на детали и изделия, прошедшие контроль качества. Номер в клейме закрепляют за конкретным контролёром — так по оттиску видно, кто принял изделие.',
  quality: 'Знаки соответствия и происхождения наносят на продукцию, прошедшую сертификацию или декларирование, а также для маркировки страны производства.',
  steel: 'Клейма с маркой стали нужны для маркировки заготовок, проката и готовых деталей: по оттиску сразу видно материал изделия при сборке, ремонте и приёмке.',
  symbols: 'Фигурные клейма и литеры используют для фирменной маркировки изделий, кузнечных и слесарных работ, обозначения деталей и узлов.',
  equipment: 'Набор клейм удобен для участков, где одновременно работают несколько контролёров.',
};

function productPage(p) {
  const related = STAMPS.filter(x => x.category === p.category && x.sku !== p.sku).slice(0, 4);
  const specRows = [
    ['Категория', catName(p.category)],
    p.specs.steel && ['Сталь', p.specs.steel],
    p.specs.hrc && ['Твёрдость рабочей части', p.specs.hrc + ' HRC'],
    p.specs.font && ['Высота символов', p.specs.font],
    p.specs.base && ['Диаметр заготовки', p.specs.base],
    ['Способ нанесения', 'Ударный — молотком или под прессом'],
    ['Артикул', p.sku],
  ].filter(Boolean);
  const body = `
<section class="sec sec--product">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><a href="/catalog/">Каталог</a><span>/</span><a href="/catalog/#${p.category}">${catName(p.category)}</a><span>/</span><span>${esc(p.short)}</span></nav>
    <div class="prod">
      <div class="gallery" data-gallery>
        <button type="button" class="gallery__main zoom" data-lb-open="prod" aria-label="Увеличить фото"><img src="${img(p, 1)}" alt="${esc(p.title)}" width="750" height="1000" fetchpriority="high"></button>
        <div hidden>${p.images.map(n => `<a href="${img(p, n)}" data-lb="prod" aria-label="Фото ${n}"></a>`).join('')}</div>
        ${p.images.length > 1 ? `<div class="gallery__thumbs">${p.images.map((n, i) => `<button type="button" class="${i ? '' : 'is-on'}" data-src="${img(p, n)}" aria-label="Фото ${n}"><img src="${img(p, n, true)}" alt="" width="75" height="100" loading="lazy"></button>`).join('')}</div>` : ''}
      </div>
      <div class="prod__info">
        <span class="card__cat">${catName(p.category)}</span>
        <h1>${esc(p.title)}</h1>
        ${p.rating ? `<span class="rating rating--lg">${I.star}${p.rating}<small>${esc(p.reviews || '')} покупателей</small></span>` : ''}
        <div class="prod__buy">
          <div class="price price--lg"><b>${rub(p.price)}</b>${p.oldPrice ? `<s>${rub(p.oldPrice)}</s>` : ''}</div>
          <div class="prod__btns">
            <button class="btn btn--lg" type="button" data-order data-product="${esc(p.title)} (арт. ${p.sku})">Заказать ${I.arrow}</button>
            <a class="btn btn--lg btn--ghost-dark" href="${C.max}" target="_blank" rel="noopener">${I.max}Спросить в MAX</a>
          </div>
          <p class="prod__hint">Для юрлиц — по счёту с договором. Нужен другой текст или размер? <a href="/#constructor">Соберите своё клеймо</a>.</p>
        </div>
        <dl class="specs">${specRows.map(([k, v]) => `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>`).join('')}</dl>
        <div class="prod__txt">
          <h2>Описание</h2>
          <p>${CAT_TEXT[p.category] || ''}</p>
          <p>Клеймо изготовлено из инструментальной стали с закалкой рабочей части, поэтому даёт чёткий оттиск на стали, цветных металлах, пластике и коже и не сминается при многократных ударах.</p>
        </div>
      </div>
    </div>
    ${related.length ? `<div class="related"><h2>Похожие клейма</h2><div class="grid">${related.map(x => card(x)).join('')}</div></div>` : ''}
  </div>
</section>`;
  return layout({
    title: `${p.title} — купить за ${rub(p.price)} | ${C.brand}`,
    description: `${p.title}. Цена ${rub(p.price)}, в наличии. ${CAT_TEXT[p.category] ? CAT_TEXT[p.category].split('.')[0] + '.' : ''} Заказ напрямую у производителя с доставкой по России.`,
    canonical: `/catalog/${p.slug}/`, body, ogImage: img(p, 1), bodyClass: 'product',
    jsonld: [crumbsLd([['Главная', '/'], ['Каталог', '/catalog/'], [catName(p.category), `/catalog/#${p.category}`], [p.short, `/catalog/${p.slug}/`]]), {
      '@context': 'https://schema.org', '@type': 'Product', name: p.title, sku: String(p.sku),
      image: p.images.map(n => C.siteUrl + img(p, n)), brand: { '@type': 'Brand', name: C.brand }, category: catName(p.category),
      offers: { '@type': 'Offer', price: p.price, priceCurrency: 'RUB', availability: 'https://schema.org/InStock', url: `${C.siteUrl}/catalog/${p.slug}/` },
    }],
  });
}

// ---------- прессы ----------
function pressPage() {
  const P = PRESS_PRODUCT;
  const body = `
<section class="press-hero">
  <div class="wrap press-hero__in">
    <div class="reveal">
      <nav class="crumbs crumbs--light" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Прессы</span></nav>
      <p class="eyebrow eyebrow--light">Изготовление прессов · ${PRESS.availability.toLowerCase()}</p>
      <h1>Электрогидравлический пресс ${PRESS.model}</h1>
      <p class="lead">${PRESS.lead}</p>
      <ul class="press-kpi">${PRESS.kpi.map(([b, s]) => `<li><b>${b}</b><span>${s}</span></li>`).join('')}</ul>
      <div class="hero__cta">
        <button class="btn btn--lg" type="button" data-order data-product="Пресс ${PRESS.model}">Запросить КП ${I.arrow}</button>
        <a class="btn btn--lg btn--ghost" href="tel:${C.phoneRaw}">${I.phone}${C.phone}</a>
      </div>
    </div>
    <button type="button" class="press-hero__img zoom reveal" data-lb-open="press" data-lb-start="0" aria-label="Увеличить фото пресса"><img src="${img(P, 1)}" alt="Пресс ${PRESS.model}" width="750" height="1000" fetchpriority="high"></button>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Применение</p><h2>Один пресс — пять задач</h2></div>
    <div class="uses">${PRESS.uses.map(([t, d], i) => `<div class="use reveal"><b>0${i + 1}</b><h3>${t}</h3><p>${d}</p></div>`).join('')}</div>
  </div>
</section>

<section class="sec sec--tint">
  <div class="wrap press-ctrl">
    <div class="reveal">
      <p class="eyebrow">Управление</p>
      <h2>Три способа управления и ЧПУ с записью испытаний</h2>
      <ol class="ctrl-list">${PRESS.control.map(([t, d]) => `<li><h3>${t}</h3><p>${d}</p></li>`).join('')}</ol>
      <h3 class="mt">Режимы системы ЧПУ</h3>
      <ul class="ticks">${PRESS.modes.map(m => `<li>${I.check}${m}</li>`).join('')}</ul>
      <h3 class="mt">Дополнительные опции</h3>
      <ul class="ticks">${PRESS.options.map(m => `<li>${I.check}${m}</li>`).join('')}</ul>
      <p class="note">${PRESS.custom}</p>
    </div>
    <button type="button" class="press-ctrl__img zoom reveal" data-lb-open="press" data-lb-start="3" aria-label="Увеличить фото панели ЧПУ"><img src="${img(P, 4)}" alt="Панель ЧПУ пресса" width="750" height="1000" loading="lazy"></button>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Характеристики</p><h2>Технические данные ${PRESS.model}</h2></div>
    <dl class="specs specs--big reveal">${PRESS.specs.map(([k, v]) => `<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}</dl>
    <div class="press-gal reveal">${P.images.map(n => `<a href="${img(P, n)}" data-lb="press" aria-label="Увеличить фото ${n}"><img src="${img(P, n, true)}" alt="Пресс ${PRESS.model}, фото ${n}" width="300" height="400" loading="lazy"></a>`).join('')}</div>
  </div>
</section>

<section class="sec sec--dark">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Пресс под вашу задачу</p>
      <h2>Опишите задачу — подготовим предложение</h2>
      <p class="lead">Усилие, рабочее поле, алгоритм работы ЧПУ, встраивание в линию — обсудим, что нужно вашему производству.</p>
      <ul class="contacts__list">
        <li><a href="tel:${C.phoneRaw}">${I.phone}${C.phone}</a></li>
        <li><a href="mailto:${C.email}">${I.mail}${C.email}</a></li>
      </ul>
    </div>
    <div class="contacts__form reveal">${leadForm({ id: 'press', title: 'Запрос по прессу' })}</div>
  </div>
</section>`;
  return layout({
    title: `Гидравлический пресс ${PRESS.model} с ЧПУ, 50 тонн — изготовление | ${C.brand}`,
    description: `Электрогидравлический пресс ${PRESS.model}: усилие 50 т, ход штока 200 мм, рабочее поле 600×600 мм, ЧПУ, пульт ДУ. Запрессовка, маркировка, гибка, штамповка, испытания. Изготовление под задачу.`,
    canonical: '/pressy/', body, ogImage: img(P, 1), bodyClass: 'press',
    jsonld: [crumbsLd([['Главная', '/'], ['Прессы', '/pressy/']]), {
      '@context': 'https://schema.org', '@type': 'Product', name: `Электрогидравлический пресс ${PRESS.model}`, sku: String(P.sku),
      image: P.images.map(n => C.siteUrl + img(P, n)), brand: { '@type': 'Brand', name: C.brand }, // цену не указываем: зависит от комплектации
    }],
  });
}

// ---------- лазерная гравировка (дополнительная услуга) ----------
function laserPage() {
  const examples = STAMPS.filter(p => p.rating).slice(0, 4);
  const body = `
<section class="page-hero page-hero--split">
  <div class="wrap page-hero__in">
    <div>
      <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Лазерная гравировка</span></nav>
      <p class="eyebrow eyebrow--light">Дополнительная услуга</p>
      <h1>Лазерная гравировка и маркировка</h1>
      <p class="lead">${LASER.lead}</p>
      <ul class="press-kpi press-kpi--3">${LASER.facts.map(([b, s]) => `<li><b>${b}</b><span>${s}</span></li>`).join('')}</ul>
      <div class="hero__cta">
        <button class="btn btn--lg" type="button" data-order data-product="Лазерная гравировка">Рассчитать по макету ${I.arrow}</button>
        <a class="btn btn--lg btn--ghost" href="${C.max}" target="_blank" rel="noopener">${I.max}Отправить эскиз в MAX</a>
      </div>
    </div>
    <div class="page-hero__img">
      <img src="${LASER.hero}" alt="Лазерная обработка металла" width="1200" height="789" fetchpriority="high"><!-- сток со старого сайта, временно -->
      <img class="page-hero__img2" src="/img/stock/laser-leather.jpg" alt="Лазерная гравировка по коже" width="1200" height="675" loading="lazy">
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Что гравируем</p><h2>Маркировка под вашу задачу</h2><p class="sec__sub">${LASER.note}</p></div>
    <div class="uses uses--3">${LASER.services.map(([t, d, p, src, more], i) => `<div class="use use--img reveal">${src ? `<a class="use__img${src.includes('/products/') ? ' use__img--bottom' : ''}" href="${src.replace(/-s.(jpg|webp)$/, '.$1')}" data-lb="laser" aria-label="${t}"><img src="${src}" alt="${t}" loading="lazy"></a>` : ''}<b>0${i + 1}</b><h3>${t}</h3><p>${d}</p>${p ? `<span class="use__price">${p}</span>` : ''}${more ? `<a class="use__more" href="${more}">Подробнее ${I.arrow}</a>` : ''}</div>`).join('')}</div>
  </div>
</section>

<section class="sec sec--tint">
  <div class="wrap laser">
    <div class="laser__txt reveal">
      <p class="eyebrow">Как работаем</p>
      <h2>От макета до готового изделия</h2>
      <ol class="ctrl-list">
        <li><h3>Макет</h3><p>Пришлите эскиз, логотип, фото или текст — подготовим и согласуем макет.</p></li>
        <li><h3>Расчёт</h3><p>Назовём цену и срок с учётом материала, размера и тиража.</p></li>
        <li><h3>Гравировка</h3><p>Наносим на оптоволоконном лазере — от 1 дня.</p></li>
        <li><h3>Получение</h3><p>СДЭК, Почта России или самовывоз в Перми.</p></li>
      </ol>
    </div>
    <div class="laser__img reveal"><img src="${LASER.process}" alt="Лазерная головка гравировального станка" width="1200" height="784" loading="lazy"></div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head sec__head--row reveal">
      <div><p class="eyebrow">Основное производство</p><h2>Ударные клейма — на том же лазере</h2><p class="sec__sub">Клейма ОТК, знаки соответствия, марки стали и символы — в наличии и по эскизу.</p></div>
      <a class="btn btn--ghost-dark" href="/catalog/">Каталог клейм ${I.arrow}</a>
    </div>
    <div class="grid">${examples.map(p => card(p)).join('')}</div>
  </div>
</section>

<section class="sec sec--dark">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Заявка на гравировку</p>
      <h2>Пришлите макет — рассчитаем стоимость</h2>
      <p class="lead">Подойдёт эскиз, фото, логотип в любом формате или просто описание задачи.</p>
      <ul class="contacts__list">
        <li><a href="tel:${C.phoneRaw}">${I.phone}${C.phone}</a></li>
        <li><a href="mailto:${C.email}">${I.mail}${C.email}</a></li>
      </ul>
      <div class="contacts__msg">
        <a class="btn btn--max" href="${C.max}" target="_blank" rel="noopener">${I.max}MAX</a>
        <a class="btn btn--tg" href="${C.telegram}" target="_blank" rel="noopener">${I.tg}Telegram</a>
      </div>
    </div>
    <div class="contacts__form reveal">${leadForm({ id: 'laser', title: 'Расчёт гравировки' })}</div>
  </div>
</section>`;
  return layout({
    title: `Лазерная гравировка в Перми — шильдики, таблички, клише | ${C.brand}`,
    description: 'Лазерная гравировка и маркировка на оптоволоконном лазере: шильдики и таблички от 1 000 ₽, бирки, клише для тиснения кожи, логотипы и номера на деталях. Металл, пластик, кожа. От 1 дня, доставка по России.',
    canonical: '/gravirovka/', body, bodyClass: 'laser-page',
    jsonld: [crumbsLd([['Главная', '/'], ['Лазерная гравировка', '/gravirovka/']]), {
      '@context': 'https://schema.org', '@type': 'Service', name: 'Лазерная гравировка и маркировка', serviceType: 'Лазерная гравировка', areaServed: 'RU',
      provider: { '@type': 'LocalBusiness', name: C.brand, telephone: C.phoneRaw, address: { '@type': 'PostalAddress', streetAddress: C.street, addressLocality: C.city, addressCountry: 'RU' } },
    }],
  });
}

// ---------- матрицы и пуансоны ----------
function matrixPage() {
  const full = src => src.replace(/-s\.(jpg|webp)$/, '.$1');
  const body = `
<section class="page-hero page-hero--split">
  <div class="wrap page-hero__in">
    <div>
      <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Матрицы и пуансоны</span></nav>
      <p class="eyebrow eyebrow--light">Изготовление на заказ</p>
      <h1>Изготовление матриц и пуансонов</h1>
      <p class="lead">${MATRIX.lead}</p>
      <ul class="press-kpi press-kpi--fit">${MATRIX.facts.map(([b, s]) => `<li><b>${b}</b><span>${s}</span></li>`).join('')}</ul>
      <div class="hero__cta">
        <button class="btn btn--lg" type="button" data-order data-product="Матрица и пуансон">Рассчитать по образцу ${I.arrow}</button>
        <a class="btn btn--lg btn--ghost" href="${C.max}" target="_blank" rel="noopener">${I.max}Отправить логотип в MAX</a>
      </div>
    </div>
    <div class="page-hero__img">
      <img src="${MATRIX.hero}" alt="Матрицы и колпачки с тиснением логотипов" width="1400" height="1050" fetchpriority="high">
      <img class="page-hero__img2" src="${MATRIX.hero2}" alt="Пара «матрица — пуансон»" width="640" height="480" loading="lazy">
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Что делаем</p><h2>Инструмент для тиснения под ваш логотип</h2><p class="sec__sub">${MATRIX.note}</p></div>
    <div class="uses uses--3">${MATRIX.works.map(([t, d, src], i) => `<div class="use use--img reveal"><a class="use__img" href="${full(src)}" data-lb="matrix" aria-label="${t}"><img src="${src}" alt="${t}" loading="lazy"></a><b>0${i + 1}</b><h3>${t}</h3><p>${d}</p></div>`).join('')}</div>
  </div>
</section>

<section class="sec sec--tint">
  <div class="wrap laser">
    <div class="laser__txt reveal">
      <p class="eyebrow">Как работаем</p>
      <h2>От логотипа до готового комплекта</h2>
      <ol class="ctrl-list">
        <li><h3>Макет</h3><p>Пришлите логотип, эскиз или образец изделия и размеры посадочного места.</p></li>
        <li><h3>Согласование</h3><p>Подготовим макет рисунка, назовём цену и срок.</p></li>
        <li><h3>Изготовление</h3><p>Готовим заготовку и наносим рисунок на оптоволоконном лазере.</p></li>
        <li><h3>Получение</h3><p>СДЭК, Почта России или самовывоз в Перми.</p></li>
      </ol>
    </div>
    <div class="laser__img reveal"><a href="/img/matrix/series.jpg" data-lb="matrix-p" aria-label="Увеличить фото"><img src="/img/matrix/series-s.jpg" alt="Серия одинаковых матриц и колпачок с тиснением" width="480" height="640" loading="lazy"></a></div>
  </div>
</section>

<section class="sec sec--dark">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Заявка на матрицу</p>
      <h2>Пришлите логотип или образец — рассчитаем</h2>
      <p class="lead">Подойдёт файл логотипа, фото колпачка или крышки, чертёж посадочного места или просто описание задачи.</p>
      <ul class="contacts__list">
        <li><a href="tel:${C.phoneRaw}">${I.phone}${C.phone}</a></li>
        <li><a href="mailto:${C.email}">${I.mail}${C.email}</a></li>
      </ul>
      <div class="contacts__msg">
        <a class="btn btn--max" href="${C.max}" target="_blank" rel="noopener">${I.max}MAX</a>
        <a class="btn btn--tg" href="${C.telegram}" target="_blank" rel="noopener">${I.tg}Telegram</a>
      </div>
    </div>
    <div class="contacts__form reveal">${leadForm({ id: 'matrix', title: 'Расчёт матрицы' })}</div>
  </div>
</section>`;
  return layout({
    title: `Изготовление матриц и пуансонов для тиснения в Перми | ${C.brand}`,
    description: 'Матрицы и пуансоны для тиснения логотипов от 2 000 ₽: колпачки, крышки и пробки, кожа, кожзам, древесина. Рисунок на оптоволоконном лазере: надписи по кругу, гербы, орнаменты. Серийные комплекты, расчёт по логотипу или образцу.',
    canonical: '/matricy/', body, ogImage: MATRIX.hero, bodyClass: 'laser-page',
    jsonld: [crumbsLd([['Главная', '/'], ['Матрицы и пуансоны', '/matricy/']]), {
      '@context': 'https://schema.org', '@type': 'Service', name: 'Изготовление матриц и пуансонов', serviceType: 'Изготовление матриц для тиснения', areaServed: 'RU', offers: { '@type': 'Offer', priceCurrency: 'RUB', priceSpecification: { '@type': 'PriceSpecification', minPrice: 2000, priceCurrency: 'RUB' } },
      provider: { '@type': 'LocalBusiness', name: C.brand, telephone: C.phoneRaw, address: { '@type': 'PostalAddress', streetAddress: C.street, addressLocality: C.city, addressCountry: 'RU' } },
    }],
  });
}

// ---------- юридические страницы ----------
function legalPage(slug, title, html) {
  return layout({
    title: `${title} | ${C.brand}`, description: `${title} — ${C.legalName}`, canonical: `/${slug}/`, bodyClass: 'legal',
    body: `<section class="sec"><div class="wrap legal-txt"><nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>${title}</span></nav><h1>${title}</h1>${html}</div></section>`,
  });
}
const LEGAL = {
  politika: ['Политика конфиденциальности', `
<p>Редакция от ${UPDATED_RU}. Политика составлена в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных» и определяет порядок обработки персональных данных посетителей сайта ${C.siteUrl}.</p>
<h2>1. Оператор</h2>
<p>${C.legalName}, ИНН ${C.inn}, ОГРН ${C.ogrn}. Адрес: ${C.address}. E-mail для обращений: <a href="mailto:${C.email}">${C.email}</a>.</p>
<h2>2. Какие данные мы обрабатываем</h2>
<p>Только то, что вы сами указываете в формах сайта: имя, номер телефона, текст сообщения и прикреплённый файл (эскиз, чертёж). Мы не просим паспортных данных и реквизитов банковских карт.</p>
<h2>3. Цели обработки</h2>
<ul><li>обработка заявки и обратная связь;</li><li>консультация по товарам и услугам, расчёт стоимости;</li><li>заключение и исполнение договора.</li></ul>
<h2>4. Правовое основание</h2>
<p>Согласие субъекта персональных данных, которое вы даёте, отмечая соответствующий пункт в форме, а также необходимость исполнения договора.</p>
<h2>5. Срок хранения</h2>
<p>Данные хранятся не дольше, чем этого требуют цели обработки, и уничтожаются по достижении целей или при отзыве согласия, если иное не предусмотрено законом.</p>
<h2>6. Передача третьим лицам</h2>
<p>Мы не продаём и не передаём ваши данные третьим лицам, кроме случаев, предусмотренных законом, и служб доставки — в объёме, необходимом для отправки заказа.</p>
<h2>7. Файлы cookie</h2>
<p>Сайт использует технические cookie и локальное хранилище браузера, чтобы запомнить закрытие уведомления о cookie. Встроенная Яндекс Карта может устанавливать собственные cookie по правилам Яндекса, а шрифты загружаются с серверов Google Fonts (при этом Google получает IP-адрес посетителя). Сервисы веб-аналитики на сайте не подключены.</p>
<h2>8. Ваши права</h2>
<p>Вы можете запросить сведения о своих данных, потребовать их уточнения или удаления, а также отозвать согласие, написав на <a href="mailto:${C.email}">${C.email}</a>. Мы ответим в срок, установленный законом.</p>
<h2>9. Защита данных</h2>
<p>Оператор принимает организационные и технические меры для защиты персональных данных от неправомерного доступа, изменения и распространения.</p>`],
  soglasie: ['Согласие на обработку персональных данных', `
<p>Оставляя заявку на сайте ${C.siteUrl}, я в соответствии с Федеральным законом № 152-ФЗ «О персональных данных» свободно, своей волей и в своём интересе даю согласие ${C.legalName} (ИНН ${C.inn}, ОГРН ${C.ogrn}, адрес: ${C.address}, e-mail: ${C.email}) на обработку моих персональных данных.</p>
<h2>Данные</h2><p>Имя, номер телефона, текст сообщения, прикреплённые файлы.</p>
<h2>Цели</h2><ul><li>обработка заявки и обратная связь со мной, в том числе через мессенджеры и e-mail;</li><li>консультации по товарам и услугам;</li><li>заключение и исполнение договора.</li></ul>
<h2>Действия с данными</h2><p>Сбор, запись, систематизация, накопление, хранение, уточнение, использование, передача (в объёме, необходимом для доставки заказа), удаление и уничтожение — с использованием средств автоматизации и без них.</p>
<h2>Срок и отзыв</h2><p>Согласие действует до достижения целей обработки или до его отзыва. Отозвать согласие можно, направив письмо на <a href="mailto:${C.email}">${C.email}</a>.</p>
<p>Подробнее — в <a href="/politika/">политике конфиденциальности</a>.</p>`],
};

// ---------- JSON-LD ----------
function orgLd() {
  return {
    '@context': 'https://schema.org', '@type': 'LocalBusiness', name: C.brand, legalName: C.legalName, url: C.siteUrl,
    logo: C.siteUrl + '/img/site/logo-mark.svg', image: C.siteUrl + '/img/site/og.jpg', telephone: C.phoneRaw, email: C.email,
    address: { '@type': 'PostalAddress', streetAddress: C.street, addressLocality: C.city, addressCountry: 'RU' },
    geo: { '@type': 'GeoCoordinates', latitude: C.geo.lat, longitude: C.geo.lon },
    taxID: C.inn, sameAs: [C.vk, C.ozonSeller],
  };
}
const faqLd = faq => ({ '@context': 'https://schema.org', '@type': 'FAQPage', mainEntity: faq.map(([q, a]) => ({ '@type': 'Question', name: q, acceptedAnswer: { '@type': 'Answer', text: a } })) });
const crumbsLd = items => ({ '@context': 'https://schema.org', '@type': 'BreadcrumbList', itemListElement: items.map(([n, u], i) => ({ '@type': 'ListItem', position: i + 1, name: n, item: C.siteUrl + u })) });

// ---------- запись ----------
const BUILD_ID = Date.now().toString(36);
function write(rel, html) {
  if (BASE && rel.endsWith('.html')) html = html.replace(/((?:href|src|data-src|action)=")\/(?!\/)/g, `$1${BASE}/`);
  const f = path.join(DIST, rel); fs.mkdirSync(path.dirname(f), { recursive: true }); fs.writeFileSync(f, html); }
function copyDir(src, dst) {
  if (!fs.existsSync(src)) return;
  fs.mkdirSync(dst, { recursive: true });
  for (const e of fs.readdirSync(src, { withFileTypes: true })) {
    const s = path.join(src, e.name), d = path.join(dst, e.name);
    if (e.isDirectory()) { fs.mkdirSync(d, { recursive: true }); copyDir(s, d); } else fs.copyFileSync(s, d);
  }
}

fs.rmSync(DIST, { recursive: true, force: true });
fs.mkdirSync(DIST, { recursive: true });
copyDir(path.join(ROOT, 'public'), DIST);
copyDir(path.join(ROOT, 'src/css'), path.join(DIST, 'css'));
copyDir(path.join(ROOT, 'src/js'), path.join(DIST, 'js'));
fs.writeFileSync(path.join(DIST, 'send.php'), fs.readFileSync(path.join(ROOT, 'src/send.php'), 'utf8')
  .replaceAll('{{EMAIL}}', C.email).replaceAll('{{DOMAIN}}', new URL(C.siteUrl).hostname));

write('index.html', home());
write('catalog/index.html', catalogPage());
STAMPS.forEach(p => write(`catalog/${p.slug}/index.html`, productPage(p)));
write('pressy/index.html', pressPage());
write('matricy/index.html', matrixPage());
write('gravirovka/index.html', laserPage());
for (const [slug, [t, h]] of Object.entries(LEGAL)) write(`${slug}/index.html`, legalPage(slug, t, h));
write('404.html', layout({ title: `Страница не найдена | ${C.brand}`, description: 'Страница не найдена', canonical: '/404.html', bodyClass: 'legal',
  body: `<section class="sec"><div class="wrap legal-txt"><h1>Страница не найдена</h1><p>Возможно, клеймо переехало. Загляните в <a href="/catalog/">каталог</a> или на <a href="/">главную</a>.</p></div></section>` }));

const urls = ['/', '/catalog/', '/pressy/', '/matricy/', '/gravirovka/', ...STAMPS.map(p => `/catalog/${p.slug}/`), '/politika/', '/soglasie/'];
write('sitemap.xml', `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls.map(u => `  <url><loc>${C.siteUrl}${u}</loc><lastmod>${updated}</lastmod></url>`).join('\n')}\n</urlset>\n`);
write('robots.txt', BASE ? 'User-agent: *\nDisallow: /\n' : `User-agent: *\nDisallow: /send.php\nSitemap: ${C.siteUrl}/sitemap.xml\n`);
if (BASE) { // на Pages PHP и .htaccess не работают
  fs.rmSync(path.join(DIST, 'send.php'), { force: true });
  fs.rmSync(path.join(DIST, '.htaccess'), { force: true });
  fs.writeFileSync(path.join(DIST, '.nojekyll'), '');
}
console.log(`Готово: ${urls.length} страниц, ${STAMPS.length} клейм → ${path.relative(ROOT, DIST)}/`);
