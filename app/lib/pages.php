<?php
// Страницы сайта. Каждая функция возвращает готовый HTML; запись на диск — в build.php.
declare(strict_types=1);

// Проверки данных перед сборкой: ошибка здесь отменяет сохранение в админке, сайт остаётся прежним
function validate_data(): void
{
    if (!S::$pressProduct) throw new RuntimeException('Не найден товар пресса (артикул ' . S::$press['ozonSku'] . ') в каталоге');
    $slugs = [];
    foreach (S::$all as $p) {
        if (!preg_match('~^[a-z0-9-]+$~', (string)$p['slug'])) throw new RuntimeException('У товара «' . $p['title'] . '» неверный адрес страницы');
        if (isset($slugs[$p['slug']])) throw new RuntimeException('Два товара с одинаковым адресом страницы: ' . $p['slug']);
        $slugs[$p['slug']] = true;
        if (!is_int($p['price']) || $p['price'] <= 0) throw new RuntimeException('У товара «' . $p['title'] . '» не указана цена');
        if (cat_name($p['category']) === '') throw new RuntimeException('У товара «' . $p['title'] . '» неизвестная категория');
    }
    $pages = [];
    foreach (S::$svc as $g) foreach ($g['items'] as $x) {
        if ($x['own'] && isset($pages[$x['href']])) throw new RuntimeException('У двух услуг одинаковый адрес страницы: ' . $x['href']);
        $pages[$x['href']] = true;
        if ($x['src'] === '') throw new RuntimeException('У услуги «' . $x['t'] . '» нет фото');
    }
}

// ---------- главная ----------
function page_home(): string
{
    $C = S::$c; $PRESS = S::$press; $LASER = S::$laser; $MATRIX = S::$matrix; $STAMPS = S::$stamps; $PP = S::$pressProduct;
    $popular = $STAMPS;
    usort($popular, fn($a, $b) => ((int)($b['reviews'] ?? 0) - (int)($a['reviews'] ?? 0)) ?: $a['order'] - $b['order']);
    $popular = array_slice($popular, 0, 8);
    $heroStamps = [];
    foreach (['2548412438', '4840849029', '1732570493'] as $s) foreach (S::$products as $p) if ((string)$p['sku'] === $s) { $heroStamps[] = $p; break; }
    $faq = faq_list();
    $minPrice = S::$minPrice;
    $tick = map_join(array_slice($STAMPS, 0, 24), fn($p) => '<span>' . esc($p['short']) . '</span>');
    $body = '
<section class="hero">
  <div class="hero__glow" aria-hidden="true"></div>
  <div class="wrap hero__in">
    <div class="hero__txt reveal">
      <p class="eyebrow"><span class="dot"></span>Собственное производство · ' . $C['city'] . '</p>
      <h1>Ударные клейма <span class="hl">и&nbsp;гидравлические прессы</span></h1>
      <p class="lead">Маркировка металла под ключ: ' . plural(count($STAMPS), ['готовое клеймо', 'готовых клейма', 'готовых клейм']) . ' — ОТК, EAC, марки стали, знаки — в наличии на складе. Изготовим клеймо по вашему эскизу от 1 дня и соберём пресс под вашу задачу.</p>
      <div class="hero__cta">
        <a class="btn btn--lg" href="/catalog/">Каталог клейм ' . I['arrow'] . '</a>
        <a class="btn btn--lg btn--ghost" href="/pressy/">Прессы с ЧПУ</a>
      </div>
      <ul class="hero__facts">
        <li><b>от 1 дня</b><span>изготовление клейма</span></li>
        <li><b>от 1 мм</b><span>высота шрифта</span></li>
        <li><b>' . $C['ozonStats']['rating'] . '<i>★</i></b><span>' . $C['ozonStats']['reviews'] . ' отзывов покупателей</span></li>
        <li><b>' . $C['ozonStats']['orders'] . '</b><span>заказов от покупателей</span></li>
      </ul>
    </div>
    <div class="hero__vis reveal" aria-hidden="true">
      ' . map_join($heroStamps, fn($p, $i) => '<img class="hero__stamp hero__stamp--' . ($i + 1) . '" src="' . img($p, 1) . '" alt="" width="500" height="667" ' . ($i === 0 ? 'fetchpriority="high"' : 'loading="lazy"') . '>') . '
      <div class="sparks"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    </div>
  </div>
  <div class="ticker" aria-hidden="true"><div class="ticker__track">' . $tick . $tick . '</div></div>
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
          <span class="dir__more">' . pos_n(count($STAMPS)) . ' · от ' . rub($minPrice) . ' ' . I['arrow'] . '</span>
        </div>
        <div class="dir__imgs">' . map_join(array_slice($STAMPS, 3, 3), fn($p) => '<img src="' . img($p, 1, true) . '" alt="" width="300" height="400" loading="lazy">') . '</div>
      </a>
      <a class="dir dir--press reveal" href="/pressy/">
        <div class="dir__txt">
          <span class="dir__num">02</span>
          <h3>Гидравлические прессы</h3>
          <p>Электрогидравлический пресс ' . $PRESS['model'] . ': ' . $PRESS['force'] . ', ЧПУ, пульт ДУ и педаль. Запрессовка, маркировка, гибка, штамповка и испытания материалов.</p>
          <span class="dir__more">Характеристики и заказ ' . I['arrow'] . '</span>
        </div>
        <div class="dir__imgs dir__imgs--one"><img src="' . img($PP, 2, true) . '" alt="" width="300" height="400" loading="lazy"></div>
      </a>
    </div>
  </div>
</section>

<section class="sec sec--tint" id="catalog">
  <div class="wrap">
    <div class="sec__head sec__head--row reveal">
      <div><p class="eyebrow">Каталог</p><h2>Клейма в наличии</h2><p class="sec__sub">Оставьте заказ на сайте — перезвоним, подтвердим наличие и сроки доставки.</p></div>
      <a class="btn btn--ghost-dark" href="/catalog/">Весь каталог · ' . count($STAMPS) . ' ' . I['arrow'] . '</a>
    </div>
    <div class="cats reveal">
      ' . map_join(array_values(array_filter(S::$cats, fn($c) => cat_count($c['id']))), fn($c) => '<a class="cat" href="/catalog/#' . $c['id'] . '"><b>' . $c['short'] . '</b><span>' . cat_count($c['id']) . '</span></a>') . '
    </div>
    <div class="grid">' . map_join($popular, fn($p) => card($p)) . '</div>
  </div>
</section>

<section class="sec sec--dark" id="constructor">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow eyebrow--light">Конструктор</p><h2>Соберите своё клеймо — и сразу увидите оттиск</h2><p class="sec__sub">Введите текст, выберите рамку и размер. Мы получим эту схему вместе с заявкой и подготовим макет бесплатно.</p></div>
    <div class="cons reveal">
      <div class="cons__ctrl">
        <label class="fld fld--dark"><span>Текст или номер</span><input type="text" id="c-text" maxlength="14" value="ОТК" autocomplete="off"></label>
        <fieldset class="seg"><legend>Рамка</legend>
          ' . map_join([['none', 'Без рамки'], ['circle', 'Круг'], ['triangle', 'Треугольник'], ['square', 'Квадрат'], ['pentagon', 'Пятиугольник']], fn($f, $i) => '<label><input type="radio" name="c-frame" value="' . $f[0] . '"' . ($i === 2 ? ' checked' : '') . '><span>' . $f[1] . '</span></label>') . '
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
        <p class="cons__note">' . I['doc'] . 'Ориентир по цене: готовые клейма в каталоге — от ' . rub($minPrice) . '. Стоимость индивидуального клейма назовём после согласования макета.</p>
        <button class="btn btn--lg btn--block" type="button" id="c-send">Отправить схему в заявку ' . I['arrow'] . '</button>
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
      <img src="' . img($PP, 1) . '" alt="Электрогидравлический пресс ' . $PRESS['model'] . '" width="750" height="1000" loading="lazy">
    </div>
    <div class="press-teaser__txt reveal">
      <p class="eyebrow">Изготовление прессов</p>
      <h2>Пресс ' . $PRESS['model'] . ': ' . $PRESS['force'] . ' с&nbsp;ЧПУ</h2>
      <p class="lead lead--dark">' . $PRESS['lead'] . '</p>
      <dl class="specs specs--mini">
        ' . map_join(array_slice($PRESS['specs'], 0, 6), fn($s) => '<div><dt>' . $s['name'] . '</dt><dd>' . $s['value'] . '</dd></div>') . '
      </dl>
      <div class="hero__cta">
        <a class="btn btn--lg" href="/pressy/">Подробнее о прессе ' . I['arrow'] . '</a>
        <button class="btn btn--lg btn--ghost-dark" type="button" data-order data-product="Пресс ' . $PRESS['model'] . ' / пресс под задачу">Обсудить задачу</button>
      </div>
    </div>
  </div>
</section>

<section class="sec sec--tint" id="why">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Почему ' . $C['brand'] . '</p><h2>Делаем быстро, точно и официально</h2></div>
    <div class="bento">
      <div class="bx bx--big reveal"><span class="bx__ico">' . I['clock'] . '</span><h3>Срок — от 1 дня</h3><p>Быстро запускаем и оперативно выполняем заказы, строго в оговорённый срок. Популярные клейма и клише — на складе, без ожидания.</p><span class="bx__big">24 ч</span></div>
      <div class="bx reveal"><span class="bx__ico">' . I['ruler'] . '</span><h3>Шрифт от 1 мм</h3><p>Делаем даже сверхтонкую и мелкую маркировку.</p></div>
      <div class="bx reveal"><span class="bx__ico">' . I['doc'] . '</span><h3>Физлица и юрлица</h3><p>Договор, чек, гарантия — оформляем официально.</p></div>
      <div class="bx bx--accent reveal"><span class="bx__ico">' . I['repeat'] . '</span><h3>' . $C['returnRate'] . ' клиентов возвращаются</h3><p>Нас рекомендуют и заказывают снова — главный показатель качества.</p></div>
      <div class="bx reveal"><span class="bx__ico">' . I['bolt'] . '</span><h3>Закалённая сталь</h3><p>Инструментальные стали 9ХС и У8А, твёрдость рабочей части 50–58 HRC.</p></div>
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
      <p>' . $LASER['lead'] . '</p>
      <ul class="ticks">' . map_join(array_slice($LASER['services'], 0, 5), fn($s) => '<li>' . I['check'] . '<span><b>' . $s['title'] . '</b>' . ($s['price'] ? ' — ' . $s['price'] : '') . '</span></li>') . '</ul>
      <p class="laser__note">' . $LASER['note'] . '</p>
      <div class="hero__cta">
        <a class="btn" href="/gravirovka/">Подробнее о гравировке ' . I['arrow'] . '</a>
        <button class="btn btn--ghost-dark" type="button" data-order data-product="Лазерная гравировка">Рассчитать по макету</button>
      </div>
    </div>
    <div class="laser__img laser__img--duo reveal">
      <img src="' . $LASER['hero'] . '" alt="Металлические бирки с лазерной маркировкой" width="1400" height="1050" loading="lazy">
      <img src="/img/site/eac-plates.jpg" alt="Таблички с лазерной маркировкой EAC" width="546" height="410" loading="lazy">
    </div>
  </div>
</section>

<section class="sec" id="matrix">
  <div class="wrap laser laser--rev">
    <div class="laser__txt reveal">
      <p class="eyebrow">Изготовление на заказ</p>
      <h2>Матрицы и пуансоны для тиснения</h2>
      <p>' . $MATRIX['lead'] . '</p>
      <ul class="ticks">' . map_join(array_slice($MATRIX['works'], 0, 4), fn($w) => '<li>' . I['check'] . '<span><b>' . $w['title'] . '</b></span></li>') . '<li>' . I['check'] . '<span><b>Тиснение на коже, кожзаме и древесине</b></span></li></ul>
      <p class="laser__note">Цена — ' . $MATRIX['price'] . ', зависит от размера и сложности рисунка. Срок — ' . $MATRIX['term'] . '.</p>
      <div class="hero__cta">
        <a class="btn" href="/matricy/">Подробнее о матрицах ' . I['arrow'] . '</a>
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
      <span class="reviews__num">' . $C['ozonStats']['rating'] . '</span>
      <span class="reviews__stars">' . str_repeat(I['star'], 5) . '</span>
      <p>Средняя оценка покупателей<br>' . $C['ozonStats']['reviews'] . ' отзывов · ' . $C['ozonStats']['orders'] . ' заказов</p>
    </div>
    <div class="reviews__txt">
      <h2>Нам доверяют покупатели</h2>
      <p>Оценки оставлены реальными покупателями наших клейм на маркетплейсе — мы их не переписываем и не редактируем. ' . $C['returnRate'] . ' клиентов возвращаются за новыми заказами.</p>
      <a class="link-quiet" href="' . $C['ozonSeller'] . '" target="_blank" rel="noopener">Посмотреть отзывы на Ozon</a>
    </div>
  </div>
</section>

<section class="sec sec--tint" id="delivery">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Оплата и доставка</p><h2>Удобно платить, быстро получать</h2></div>
    <div class="pay">
      <div class="pay__item reveal"><h3>Оплата</h3><p>Банковской картой, переводом на расчётный счёт или наличными при самовывозе. После подтверждения оплаты приступаем к работе.</p></div>
      <div class="pay__item reveal"><h3>Доставка</h3><p>Отправляем СДЭК и Почтой России. Стоимость доставки оплачивает покупатель, сроки зависят от способа и адреса.</p></div>
      <div class="pay__item reveal"><h3>Самовывоз</h3><p>' . $C['address'] . '. Заранее предупредите о визите по телефону.</p></div>
      <div class="pay__item reveal"><h3>Для юрлиц</h3><p>Работаем по договору: выставляем счёт, предоставляем чек и гарантию. Подходит для закупки на предприятие.</p></div>
    </div>
  </div>
</section>

<section class="sec" id="faq">
  <div class="wrap faq-wrap">
    <div class="sec__head reveal"><p class="eyebrow">Вопросы</p><h2>Частые вопросы</h2></div>
    <div class="faq">' . map_join($faq, fn($f) => '<details class="reveal"><summary>' . $f[0] . '</summary><p>' . $f[1] . '</p></details>') . '</div>
  </div>
</section>

<section class="sec sec--dark" id="contacts">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Контакты</p>
      <h2>Нет нужного клейма? Сделаем по вашему эскизу</h2>
      <p class="lead">Оставьте телефон — менеджер свяжется в течение часа в рабочее время и предложит решение.</p>
      <ul class="contacts__list">
        <li><a href="tel:' . $C['phoneRaw'] . '">' . I['phone'] . $C['phone'] . '</a></li>
        <li><a href="mailto:' . $C['email'] . '">' . I['mail'] . $C['email'] . '</a></li>
        <li><span>' . I['pin'] . $C['address'] . '</span></li>
      </ul>
      <div class="contacts__msg">
        <a class="btn btn--max" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Написать в MAX</a>
      </div>
    </div>
    <div class="contacts__form reveal">' . lead_form(['id' => 'contacts', 'title' => 'Заявка на изготовление', 'withConfig' => true]) . '</div>
  </div>
  <div class="wrap map reveal"><iframe title="' . $C['brand'] . ' на карте" src="https://yandex.ru/map-widget/v1/?ll=' . $C['geo']['lon'] . '%2C' . $C['geo']['lat'] . '&amp;z=16&amp;pt=' . $C['geo']['lon'] . '%2C' . $C['geo']['lat'] . '%2Cpm2rdm" loading="lazy" allowfullscreen></iframe></div>
</section>';

    return layout([
        'title' => $C['brand'] . ' — ударные клейма и гидравлические прессы | ' . $C['city'],
        'description' => 'Ударные клейма по металлу: ОТК, EAC, марки стали, знаки — ' . pos_n(count($STAMPS)) . ' в наличии, от ' . rub($minPrice) . '. Изготовление клейм по эскизу от 1 дня. Гидравлические прессы с ЧПУ. Доставка по России.',
        'canonical' => '/', 'body' => $body, 'bodyClass' => 'home',
        'jsonld' => [org_ld(), faq_ld($faq)],
    ]);
}

// Вопросы и ответы — из site.json, в виде пар [вопрос, ответ]
function faq_list(): array { return array_map(fn($f) => [$f['q'], $f['a']], S::$site['faq']); }

// ---------- каталог ----------
function page_catalog(): string
{
    $C = S::$c; $STAMPS = S::$stamps; $minPrice = S::$minPrice;
    $body = '
<section class="page-hero page-hero--split">
  <div class="wrap page-hero__in">
    <div>
      <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Каталог клейм</span></nav>
      <h1>Ударные клейма по металлу</h1>
      <p class="lead lead--dark">ОТК, знаки соответствия, марки стали, символы и наборы — со склада и по вашему эскизу. Нужно своё клеймо? <a href="/#constructor">Соберите его в конструкторе</a>.</p>
      <ul class="press-kpi press-kpi--fit">
        <li><b>' . count($STAMPS) . '</b><span>клейма в наличии</span></li>
        <li><b>50–58 HRC</b><span>сталь 9ХС и У8А, закалка</span></li>
        <li><b>от 1 дня</b><span>клеймо по эскизу</span></li>
      </ul>
    </div>
    <div class="page-hero__img page-hero__img--stamps">
      <img src="/img/products/3826282219/1.webp" alt="Ударное клеймо У8А, рабочая часть крупным планом" width="750" height="1000" fetchpriority="high">
      <img class="page-hero__img2" src="/img/products/3539590846/1-s.webp" alt="Набор ударных клейм ОТК в треугольнике" width="300" height="400" loading="lazy">
    </div>
  </div>
</section>
<section class="sec sec--catalog">
  <div class="wrap">
    <div class="filters" id="filters">
      <div class="chips" role="group" aria-label="Категории">
        <button type="button" class="chip is-on" data-cat="all">Все <span>' . count($STAMPS) . '</span></button>
        ' . map_join(array_values(array_filter(S::$cats, fn($c) => cat_count($c['id']))), fn($c) => '<button type="button" class="chip" data-cat="' . $c['id'] . '">' . $c['short'] . ' <span>' . cat_count($c['id']) . '</span></button>') . '
      </div>
      <div class="filters__right">
        <label class="search">' . I['search'] . '<input type="search" id="q" placeholder="Поиск: ОТК, 12Х18Н10Т, якорь…" aria-label="Поиск по каталогу"></label>
        <select id="sort" aria-label="Сортировка"><option value="order">По популярности</option><option value="asc">Сначала дешевле</option><option value="desc">Сначала дороже</option></select>
      </div>
    </div>
    <p class="cat-desc" id="cat-desc"></p>
    <div class="grid" id="grid">' . map_join($STAMPS, fn($p, $i) => card($p, $i < 4)) . '</div>
    <p class="empty" id="empty" hidden>Ничего не нашлось. <button type="button" class="link" data-order>Оставьте заявку</button> — сделаем клеймо по вашему эскизу.</p>
    <aside class="cta-band">
      <div><h2>Не нашли нужное клеймо?</h2><p>Изготовим по эскизу от 1 дня: любой текст, номер, логотип, шрифт от 1 мм.</p></div>
      <div class="cta-band__btns"><a class="btn btn--lg" href="/#constructor">Конструктор клейма</a><button class="btn btn--lg btn--ghost" type="button" data-order>Оставить заявку</button></div>
    </aside>
  </div>
</section>
<script>window.CATS=' . jsenc((object)array_column(S::$cats, 'desc', 'id')) . '</script>';
    return layout([
        'title' => 'Каталог ударных клейм по металлу — ОТК, EAC, марки стали | ' . $C['brand'],
        'description' => 'Каталог ударных клейм: ОТК, EAC, РСТ, марки стали (12Х18Н10Т, AISI 316, 09Г2С), символы и знаки. ' . pos_n(count($STAMPS)) . ' в наличии от ' . rub($minPrice) . '. Заказ напрямую у производителя.',
        'canonical' => '/catalog/', 'body' => $body, 'bodyClass' => 'catalog',
        'jsonld' => [crumbs_ld([['Главная', '/'], ['Каталог клейм', '/catalog/']]), [
            '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Каталог ударных клейм',
            'itemListElement' => map_join_arr($STAMPS, fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $C['siteUrl'] . '/catalog/' . $p['slug'] . '/', 'name' => $p['title']]),
        ]],
    ]);
}

// Текст описания на странице клейма — по категории (site.json → catText)
function cat_text(string $id): string { return S::$site['catText'][$id] ?? ''; }

function page_product(array $p): string
{
    $C = S::$c;
    $related = array_slice(array_values(array_filter(S::$stamps, fn($x) => $x['category'] === $p['category'] && $x['sku'] !== $p['sku'])), 0, 4);
    $sp = $p['specs'] ?? [];
    $specRows = array_values(array_filter([
        ['Категория', cat_name($p['category'])],
        !empty($sp['steel']) ? ['Сталь', $sp['steel']] : null,
        !empty($sp['hrc']) ? ['Твёрдость рабочей части', $sp['hrc'] . ' HRC'] : null,
        !empty($sp['font']) ? ['Высота символов', $sp['font']] : null,
        !empty($sp['base']) ? ['Диаметр заготовки', $sp['base']] : null,
        ['Способ нанесения', 'Ударный — молотком или под прессом'],
        ['Артикул', $p['sku']],
    ]));
    $ct = cat_text($p['category']);
    $body = '
<section class="sec sec--product">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><a href="/catalog/">Каталог</a><span>/</span><a href="/catalog/#' . $p['category'] . '">' . cat_name($p['category']) . '</a><span>/</span><span>' . esc($p['short']) . '</span></nav>
    <div class="prod">
      <div class="gallery" data-gallery>
        <button type="button" class="gallery__main zoom" data-lb-open="prod" aria-label="Увеличить фото"><img src="' . img($p, 1) . '" alt="' . esc($p['title']) . '" width="750" height="1000" fetchpriority="high"></button>
        <div hidden>' . map_join($p['images'], fn($n) => '<a href="' . img($p, $n) . '" data-lb="prod" aria-label="Фото ' . $n . '"></a>') . '</div>
        ' . (count($p['images']) > 1 ? '<div class="gallery__thumbs">' . map_join($p['images'], fn($n, $i) => '<button type="button" class="' . ($i ? '' : 'is-on') . '" data-src="' . img($p, $n) . '" aria-label="Фото ' . $n . '"><img src="' . img($p, $n, true) . '" alt="" width="75" height="100" loading="lazy"></button>') . '</div>' : '') . '
      </div>
      <div class="prod__info">
        <span class="card__cat">' . cat_name($p['category']) . '</span>
        <h1>' . esc($p['title']) . '</h1>
        ' . (!empty($p['rating']) ? '<span class="rating rating--lg">' . I['star'] . $p['rating'] . '<small>' . esc($p['reviews'] ?? '') . ' покупателей</small></span>' : '') . '
        <div class="prod__buy">
          <div class="price price--lg"><b>' . rub($p['price']) . '</b>' . (!empty($p['oldPrice']) ? '<s>' . rub($p['oldPrice']) . '</s>' : '') . '</div>
          <div class="prod__btns">
            <button class="btn btn--lg" type="button" data-order data-product="' . esc($p['title']) . ' (арт. ' . $p['sku'] . ')">Заказать ' . I['arrow'] . '</button>
            <a class="btn btn--lg btn--ghost-dark" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Спросить в MAX</a>
          </div>
          <p class="prod__hint">Для юрлиц — по счёту с договором. Нужен другой текст или размер? <a href="/#constructor">Соберите своё клеймо</a>.</p>
        </div>
        <dl class="specs">' . map_join($specRows, fn($r) => '<div><dt>' . $r[0] . '</dt><dd>' . esc($r[1]) . '</dd></div>') . '</dl>
        <div class="prod__txt">
          <h2>Описание</h2>
          <p>' . $ct . '</p>
          <p>Клеймо изготовлено из инструментальной стали с закалкой рабочей части, поэтому даёт чёткий оттиск на стали, цветных металлах, пластике и коже и не сминается при многократных ударах.</p>
        </div>
      </div>
    </div>
    ' . ($related ? '<div class="related"><h2>Похожие клейма</h2><div class="grid">' . map_join($related, fn($x) => card($x)) . '</div></div>' : '') . '
  </div>
</section>';
    return layout([
        'title' => $p['title'] . ' — купить за ' . rub($p['price']) . ' | ' . $C['brand'],
        'description' => $p['title'] . '. Цена ' . rub($p['price']) . ', в наличии. ' . ($ct !== '' ? explode('.', $ct)[0] . '.' : '') . ' Заказ напрямую у производителя с доставкой по России.',
        'canonical' => '/catalog/' . $p['slug'] . '/', 'body' => $body, 'ogImage' => img($p, 1), 'bodyClass' => 'product',
        'jsonld' => [crumbs_ld([['Главная', '/'], ['Каталог', '/catalog/'], [cat_name($p['category']), '/catalog/#' . $p['category']], [$p['short'], '/catalog/' . $p['slug'] . '/']]), [
            '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['title'], 'sku' => (string)$p['sku'],
            'image' => array_map(fn($n) => $C['siteUrl'] . img($p, $n), $p['images']), 'brand' => ['@type' => 'Brand', 'name' => $C['brand']], 'category' => cat_name($p['category']),
            'offers' => ['@type' => 'Offer', 'price' => $p['price'], 'priceCurrency' => 'RUB', 'availability' => 'https://schema.org/InStock', 'url' => $C['siteUrl'] . '/catalog/' . $p['slug'] . '/'],
        ]],
    ]);
}

// ---------- прессы ----------
function page_press(): string
{
    $C = S::$c; $PRESS = S::$press; $P = S::$pressProduct;
    $body = '
<section class="press-hero">
  <div class="wrap press-hero__in">
    <div class="reveal">
      <nav class="crumbs crumbs--light" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Прессы</span></nav>
      <p class="eyebrow eyebrow--light">Изготовление прессов · ' . mb_strtolower($PRESS['availability']) . '</p>
      <h1>Электрогидравлический пресс ' . $PRESS['model'] . '</h1>
      <p class="lead">' . $PRESS['lead'] . '</p>
      <ul class="press-kpi">' . map_join($PRESS['kpi'], fn($k) => '<li><b>' . $k['value'] . '</b><span>' . $k['label'] . '</span></li>') . '</ul>
      <div class="hero__cta">
        <button class="btn btn--lg" type="button" data-order data-product="Пресс ' . $PRESS['model'] . '">Запросить КП ' . I['arrow'] . '</button>
        <a class="btn btn--lg btn--ghost" href="tel:' . $C['phoneRaw'] . '">' . I['phone'] . $C['phone'] . '</a>
      </div>
    </div>
    <button type="button" class="press-hero__img zoom reveal" data-lb-open="press" data-lb-start="0" aria-label="Увеличить фото пресса"><img src="' . img($P, 1) . '" alt="Пресс ' . $PRESS['model'] . '" width="750" height="1000" fetchpriority="high"></button>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Применение</p><h2>Один пресс — пять задач</h2></div>
    <div class="uses">' . map_join($PRESS['uses'], fn($u, $i) => '<div class="use reveal"><b>0' . ($i + 1) . '</b><h3>' . $u['title'] . '</h3><p>' . $u['text'] . '</p></div>') . '</div>
  </div>
</section>

<section class="sec sec--tint">
  <div class="wrap press-ctrl">
    <div class="reveal">
      <p class="eyebrow">Управление</p>
      <h2>Три способа управления и ЧПУ с записью испытаний</h2>
      <ol class="ctrl-list">' . map_join($PRESS['control'], fn($c) => '<li><h3>' . $c['title'] . '</h3><p>' . $c['text'] . '</p></li>') . '</ol>
      <h3 class="mt">Режимы системы ЧПУ</h3>
      <ul class="ticks">' . map_join($PRESS['modes'], fn($m) => '<li>' . I['check'] . $m . '</li>') . '</ul>
      <h3 class="mt">Дополнительные опции</h3>
      <ul class="ticks">' . map_join($PRESS['options'], fn($m) => '<li>' . I['check'] . $m . '</li>') . '</ul>
      <p class="note">' . $PRESS['custom'] . '</p>
    </div>
    <button type="button" class="press-ctrl__img zoom reveal" data-lb-open="press" data-lb-start="3" aria-label="Увеличить фото панели ЧПУ"><img src="' . img($P, 4) . '" alt="Панель ЧПУ пресса" width="750" height="1000" loading="lazy"></button>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Характеристики</p><h2>Технические данные ' . $PRESS['model'] . '</h2></div>
    <dl class="specs specs--big reveal">' . map_join($PRESS['specs'], fn($s) => '<div><dt>' . $s['name'] . '</dt><dd>' . $s['value'] . '</dd></div>') . '</dl>
    <div class="press-gal reveal">' . map_join($P['images'], fn($n) => '<a href="' . img($P, $n) . '" data-lb="press" aria-label="Увеличить фото ' . $n . '"><img src="' . img($P, $n, true) . '" alt="Пресс ' . $PRESS['model'] . ', фото ' . $n . '" width="300" height="400" loading="lazy"></a>') . '</div>
  </div>
</section>

<section class="sec sec--dark">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Пресс под вашу задачу</p>
      <h2>Опишите задачу — подготовим предложение</h2>
      <p class="lead">Усилие, рабочее поле, алгоритм работы ЧПУ, встраивание в линию — обсудим, что нужно вашему производству.</p>
      <ul class="contacts__list">
        <li><a href="tel:' . $C['phoneRaw'] . '">' . I['phone'] . $C['phone'] . '</a></li>
        <li><a href="mailto:' . $C['email'] . '">' . I['mail'] . $C['email'] . '</a></li>
      </ul>
    </div>
    <div class="contacts__form reveal">' . lead_form(['id' => 'press', 'title' => 'Запрос по прессу']) . '</div>
  </div>
</section>';
    return layout([
        'title' => 'Гидравлический пресс ' . $PRESS['model'] . ' с ЧПУ, 50 тонн — изготовление | ' . $C['brand'],
        'description' => 'Электрогидравлический пресс ' . $PRESS['model'] . ': усилие 50 т, ход штока 200 мм, рабочее поле 600×600 мм, ЧПУ, пульт ДУ. Запрессовка, маркировка, гибка, штамповка, испытания. Изготовление под задачу.',
        'canonical' => '/pressy/', 'body' => $body, 'ogImage' => img($P, 1), 'bodyClass' => 'press',
        'jsonld' => [crumbs_ld([['Главная', '/'], ['Прессы', '/pressy/']]), [
            '@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'Электрогидравлический пресс ' . $PRESS['model'], 'sku' => (string)$P['sku'],
            'image' => array_map(fn($n) => $C['siteUrl'] . img($P, $n), $P['images']), 'brand' => ['@type' => 'Brand', 'name' => $C['brand']], // цену не указываем: зависит от комплектации
        ]],
    ]);
}

// ---------- услуги (гравировка, матрицы) ----------
// Каждая услуга — своя страница по образцу страницы клейма; карточка в списке ведёт на неё, как карточка товара.
function full_img(string $src): string { return $src !== '' ? preg_replace('/-s\.(jpg|webp)$/', '.$1', $src) : $src; }

function services(): array
{
    $L = S::$laser; $M = S::$matrix;
    $g = [
        'laser' => [
            'base' => '/gravirovka/', 'name' => 'Лазерная гравировка', 'order' => 'Гравировка', 'related' => 'Другие услуги гравировки',
            'hint' => $L['note'],
            'specs' => array_merge(array_map(fn($f) => [ucfirst_u($f['label']), $f['value']], $L['facts']), [['Оборудование', 'Оптоволоконный лазер']]),
            'steps' => [
                ['Макет', 'Пришлите эскиз, логотип, фото или текст — подготовим и согласуем макет.'],
                ['Расчёт', 'Назовём цену и срок с учётом материала, размера и тиража.'],
                ['Гравировка', 'Наносим на оптоволоконном лазере — от 1 дня.'],
                ['Получение', 'СДЭК, Почта России или самовывоз в Перми.'],
            ],
            'items' => array_map(function ($s) {
                $own = !str_starts_with($s['page'], '/');
                return ['t' => $s['title'], 'd' => $s['text'], 'price' => $s['price'], 'src' => $s['photo'], 'extra' => $s['photos'] ?? [], 'own' => $own, 'href' => $own ? '/gravirovka/' . $s['page'] . '/' : $s['page']];
            }, $L['services']),
        ],
        'matrix' => [
            'base' => '/matricy/', 'name' => 'Матрицы и пуансоны', 'order' => 'Матрица', 'related' => 'Другие работы',
            'hint' => 'Точную цену назовём по логотипу или образцу. Для юрлиц — по счёту с договором.',
            'specs' => [['Цена', $M['price'] . ', зависит от размера и сложности рисунка'], ['Срок изготовления', $M['term']], ['Тиснение на', 'колпачках, крышках и пробках, коже, кожзаме, древесине'], ['Нанесение рисунка', 'Оптоволоконный лазер']],
            'steps' => [
                ['Макет', 'Пришлите логотип, эскиз или образец изделия и размеры посадочного места.'],
                ['Согласование', 'Подготовим макет рисунка, назовём цену и срок.'],
                ['Изготовление', 'Готовим заготовку и наносим рисунок на оптоволоконном лазере — ' . $M['term'] . '.'],
                ['Получение', 'СДЭК, Почта России или самовывоз в Перми.'],
            ],
            'items' => array_map(fn($w) => ['t' => $w['title'], 'd' => $w['text'], 'price' => $M['price'], 'src' => $w['photo'], 'extra' => [], 'own' => true, 'href' => '/matricy/' . $w['slug'] . '/'], $M['works']),
        ],
    ];
    foreach ($g as $k => $grp) foreach ($grp['items'] as $i => $x) { $g[$k]['items'][$i]['i'] = $i; $g[$k]['items'][$i]['product'] = $grp['order'] . ': ' . $x['t']; }
    return $g;
}
function steps_list(array $g): string { return '<ol class="ctrl-list">' . map_join($g['steps'], fn($s) => '<li><h3>' . $s[0] . '</h3><p>' . $s[1] . '</p></li>') . '</ol>'; }

// Карточка услуги — как карточка товара: фото и название ведут на страницу услуги, кнопка — заявка
function service_card(array $s): string
{
    return '<article class="use use--img use--link reveal">' . ($s['src'] ? '<a class="use__img" href="' . $s['href'] . '" tabindex="-1" aria-hidden="true"><img src="' . $s['src'] . '" alt="" loading="lazy"></a>' : '') . '<b>0' . ($s['i'] + 1) . '</b><h3><a href="' . $s['href'] . '">' . $s['t'] . '</a></h3><p>' . $s['d'] . '</p>
  <div class="use__foot"><span class="use__price' . ($s['price'] ? '' : ' use__price--ask') . '">' . ($s['price'] ?: 'по макету') . '</span><button class="btn btn--sm" type="button" data-order data-product="' . esc($s['product']) . '" aria-label="Заказать: ' . esc($s['t']) . '">Заказать</button></div></article>';
}

function page_service(array $g, array $s): string
{
    $C = S::$c;
    $big = full_img($s['src']);
    $others = array_values(array_filter($g['items'], fn($x) => $x['i'] !== $s['i']));
    $photos = array_merge([$big], $s['extra']);
    $thumb = fn($u) => $u === $big ? $s['src'] : preg_replace('/.(jpg|webp)$/', '-s.$1', $u);
    $minPrice = $s['price'] ? (int)preg_replace('/\D/u', '', $s['price']) : 0;
    $body = '
<section class="sec sec--product">
  <div class="wrap">
    <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><a href="' . $g['base'] . '">' . $g['name'] . '</a><span>/</span><span>' . esc($s['t']) . '</span></nav>
    <div class="prod">
      <div class="gallery gallery--fit" data-gallery>
        <button type="button" class="gallery__main zoom" data-lb-open="svc" aria-label="Увеличить фото"><img src="' . $big . '" alt="' . esc($s['t']) . '" fetchpriority="high"></button>
        <div hidden>' . map_join($photos, fn($u, $i) => '<a href="' . $u . '" data-lb="svc" aria-label="' . esc($s['t']) . ', фото ' . ($i + 1) . '"></a>') . '</div>
        ' . (count($photos) > 1 ? '<div class="gallery__thumbs">' . map_join($photos, fn($u, $i) => '<button type="button" class="' . ($i ? '' : 'is-on') . '" data-src="' . $u . '" aria-label="Фото ' . ($i + 1) . '"><img src="' . $thumb($u) . '" alt="" loading="lazy"></button>') . '</div>' : '') . '
      </div>
      <div class="prod__info">
        <span class="card__cat">' . $g['name'] . '</span>
        <h1>' . esc($s['t']) . '</h1>
        <div class="prod__buy">
          <div class="price price--lg"><b>' . ($s['price'] ?: 'Цена по макету') . '</b></div>
          <div class="prod__btns">
            <button class="btn btn--lg" type="button" data-order data-product="' . esc($s['product']) . '">Заказать ' . I['arrow'] . '</button>
            <a class="btn btn--lg btn--ghost-dark" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Спросить в MAX</a>
          </div>
          <p class="prod__hint">' . $g['hint'] . '</p>
        </div>
        <dl class="specs">' . map_join($g['specs'], fn($r) => '<div><dt>' . $r[0] . '</dt><dd>' . esc($r[1]) . '</dd></div>') . '</dl>
        <div class="prod__txt">
          <h2>Описание</h2>
          <p>' . $s['d'] . '</p>
          <h2>Как работаем</h2>
          ' . steps_list($g) . '
        </div>
      </div>
    </div>
    <div class="related"><h2>' . $g['related'] . '</h2><div class="uses uses--3">' . map_join($others, fn($x) => service_card($x)) . '</div></div>
  </div>
</section>';
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'Service', 'name' => $s['t'], 'description' => $s['d'], 'serviceType' => $g['name'], 'areaServed' => 'RU', 'image' => $C['siteUrl'] . $big,
    ];
    if ($minPrice) $ld['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'RUB', 'priceSpecification' => ['@type' => 'PriceSpecification', 'minPrice' => $minPrice, 'priceCurrency' => 'RUB']];
    $ld['provider'] = provider_ld();
    return layout([
        'title' => $s['t'] . ' — ' . mb_strtolower($g['name']) . ' в Перми' . ($s['price'] ? ', ' . $s['price'] : '') . ' | ' . $C['brand'],
        'description' => $s['d'] . ' ' . ($s['price'] ? 'Цена ' . $s['price'] : 'Цена по макету') . ', расчёт по эскизу или образцу. Изготовление в Перми, доставка по России.',
        'canonical' => $s['href'], 'body' => $body, 'ogImage' => $big, 'bodyClass' => 'product',
        'jsonld' => [crumbs_ld([['Главная', '/'], [$g['name'], $g['base']], [$s['t'], $s['href']]]), $ld],
    ]);
}

// ---------- лазерная гравировка (дополнительная услуга) ----------
function page_laser(): string
{
    $C = S::$c; $LASER = S::$laser; $SVC = S::$svc;
    $examples = array_slice(array_values(array_filter(S::$stamps, fn($p) => !empty($p['rating']))), 0, 4);
    $body = '
<section class="page-hero page-hero--split">
  <div class="wrap page-hero__in">
    <div>
      <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Лазерная гравировка</span></nav>
      <p class="eyebrow eyebrow--light">Дополнительная услуга</p>
      <h1>Лазерная гравировка и маркировка</h1>
      <p class="lead">' . $LASER['lead'] . '</p>
      <ul class="press-kpi press-kpi--3">' . map_join($LASER['facts'], fn($f) => '<li><b>' . $f['value'] . '</b><span>' . $f['label'] . '</span></li>') . '</ul>
      <div class="hero__cta">
        <button class="btn btn--lg" type="button" data-order data-product="Лазерная гравировка">Рассчитать по макету ' . I['arrow'] . '</button>
        <a class="btn btn--lg btn--ghost" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Отправить эскиз в MAX</a>
      </div>
    </div>
    <div class="page-hero__img">
      <img src="' . $LASER['hero'] . '" alt="Металлические бирки с лазерной маркировкой" width="1400" height="1050" fetchpriority="high">
      <img class="page-hero__img2" src="' . $LASER['hero2'] . '" alt="Латунное клише для тиснения на столе лазера" width="480" height="640" loading="lazy">
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Что гравируем</p><h2>Маркировка под вашу задачу</h2><p class="sec__sub">' . $LASER['note'] . '</p></div>
    <div class="uses uses--3">' . map_join($SVC['laser']['items'], fn($x) => service_card($x)) . '</div>
  </div>
</section>

<section class="sec sec--tint">
  <div class="wrap laser">
    <div class="laser__txt reveal">
      <p class="eyebrow">Как работаем</p>
      <h2>От макета до готового изделия</h2>
      ' . steps_list($SVC['laser']) . '
    </div>
    <figure class="laser__video reveal"><video src="' . $LASER['video']['src'] . '" poster="' . $LASER['video']['poster'] . '" controls playsinline preload="none" width="540" height="960" aria-label="' . $LASER['video']['title'] . '"></video><figcaption>' . $LASER['video']['title'] . '</figcaption></figure>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head sec__head--row reveal">
      <div><p class="eyebrow">Основное производство</p><h2>Ударные клейма — на том же лазере</h2><p class="sec__sub">Клейма ОТК, знаки соответствия, марки стали и символы — в наличии и по эскизу.</p></div>
      <a class="btn btn--ghost-dark" href="/catalog/">Каталог клейм ' . I['arrow'] . '</a>
    </div>
    <div class="grid">' . map_join($examples, fn($p) => card($p)) . '</div>
  </div>
</section>

<section class="sec sec--dark">
  <div class="wrap contacts">
    <div class="contacts__info reveal">
      <p class="eyebrow eyebrow--light">Заявка на гравировку</p>
      <h2>Пришлите макет — рассчитаем стоимость</h2>
      <p class="lead">Подойдёт эскиз, фото, логотип в любом формате или просто описание задачи.</p>
      <ul class="contacts__list">
        <li><a href="tel:' . $C['phoneRaw'] . '">' . I['phone'] . $C['phone'] . '</a></li>
        <li><a href="mailto:' . $C['email'] . '">' . I['mail'] . $C['email'] . '</a></li>
      </ul>
      <div class="contacts__msg">
        <a class="btn btn--max" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Написать в MAX</a>
      </div>
    </div>
    <div class="contacts__form reveal">' . lead_form(['id' => 'laser', 'title' => 'Расчёт гравировки']) . '</div>
  </div>
</section>';
    return layout([
        'title' => 'Лазерная гравировка в Перми — шильдики, таблички, клише | ' . $C['brand'],
        'description' => 'Лазерная гравировка и маркировка на оптоволоконном лазере: шильдики и таблички от 1 000 ₽, бирки, клише для тиснения кожи, логотипы и номера на деталях. Металл, пластик, кожа. От 1 дня, доставка по России.',
        'canonical' => '/gravirovka/', 'body' => $body, 'bodyClass' => 'laser-page',
        'jsonld' => [crumbs_ld([['Главная', '/'], ['Лазерная гравировка', '/gravirovka/']]), [
            '@context' => 'https://schema.org', '@type' => 'Service', 'name' => 'Лазерная гравировка и маркировка', 'serviceType' => 'Лазерная гравировка', 'areaServed' => 'RU',
            'provider' => provider_ld(),
        ]],
    ]);
}

// ---------- матрицы и пуансоны ----------
function page_matrix(): string
{
    $C = S::$c; $MATRIX = S::$matrix; $SVC = S::$svc;
    $body = '
<section class="page-hero page-hero--split">
  <div class="wrap page-hero__in">
    <div>
      <nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>Матрицы и пуансоны</span></nav>
      <p class="eyebrow eyebrow--light">Изготовление на заказ</p>
      <h1>Изготовление матриц и пуансонов</h1>
      <p class="lead">' . $MATRIX['lead'] . '</p>
      <ul class="press-kpi press-kpi--fit">' . map_join($MATRIX['facts'], fn($f) => '<li><b>' . $f['value'] . '</b><span>' . $f['label'] . '</span></li>') . '</ul>
      <div class="hero__cta">
        <button class="btn btn--lg" type="button" data-order data-product="Матрица и пуансон">Рассчитать по образцу ' . I['arrow'] . '</button>
        <a class="btn btn--lg btn--ghost" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Отправить логотип в MAX</a>
      </div>
    </div>
    <div class="page-hero__img">
      <img src="' . $MATRIX['hero'] . '" alt="Матрицы и колпачки с тиснением логотипов" width="1400" height="1050" fetchpriority="high">
      <img class="page-hero__img2" src="' . $MATRIX['hero2'] . '" alt="Пара «матрица — пуансон»" width="640" height="480" loading="lazy">
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec__head reveal"><p class="eyebrow">Что делаем</p><h2>Инструмент для тиснения под ваш логотип</h2><p class="sec__sub">' . $MATRIX['note'] . '</p></div>
    <div class="uses uses--3">' . map_join($SVC['matrix']['items'], fn($x) => service_card($x)) . '</div>
  </div>
</section>

<section class="sec sec--tint">
  <div class="wrap laser">
    <div class="laser__txt reveal">
      <p class="eyebrow">Как работаем</p>
      <h2>От логотипа до готового комплекта</h2>
      ' . steps_list($SVC['matrix']) . '
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
        <li><a href="tel:' . $C['phoneRaw'] . '">' . I['phone'] . $C['phone'] . '</a></li>
        <li><a href="mailto:' . $C['email'] . '">' . I['mail'] . $C['email'] . '</a></li>
      </ul>
      <div class="contacts__msg">
        <a class="btn btn--max" href="' . $C['max'] . '" target="_blank" rel="noopener">' . I['max'] . 'Написать в MAX</a>
      </div>
    </div>
    <div class="contacts__form reveal">' . lead_form(['id' => 'matrix', 'title' => 'Расчёт матрицы']) . '</div>
  </div>
</section>';
    return layout([
        'title' => 'Изготовление матриц и пуансонов для тиснения в Перми | ' . $C['brand'],
        'description' => 'Матрицы и пуансоны для тиснения логотипов от 4 000 ₽, срок от 3 дней: колпачки, крышки и пробки, кожа, кожзам, древесина. Рисунок на оптоволоконном лазере: надписи по кругу, гербы, орнаменты. Серийные комплекты, расчёт по логотипу или образцу.',
        'canonical' => '/matricy/', 'body' => $body, 'ogImage' => $MATRIX['hero'], 'bodyClass' => 'laser-page',
        'jsonld' => [crumbs_ld([['Главная', '/'], ['Матрицы и пуансоны', '/matricy/']]), [
            '@context' => 'https://schema.org', '@type' => 'Service', 'name' => 'Изготовление матриц и пуансонов', 'serviceType' => 'Изготовление матриц для тиснения', 'areaServed' => 'RU', 'offers' => ['@type' => 'Offer', 'priceCurrency' => 'RUB', 'priceSpecification' => ['@type' => 'PriceSpecification', 'minPrice' => 4000, 'priceCurrency' => 'RUB']],
            'provider' => provider_ld(),
        ]],
    ]);
}

// ---------- юридические страницы ----------
function page_legal(string $slug, string $title, string $html): string
{
    $C = S::$c;
    return layout([
        'title' => $title . ' | ' . $C['brand'], 'description' => $title . ' — ' . $C['legalName'], 'canonical' => '/' . $slug . '/', 'bodyClass' => 'legal',
        'body' => '<section class="sec"><div class="wrap legal-txt"><nav class="crumbs" aria-label="Навигация"><a href="/">Главная</a><span>/</span><span>' . $title . '</span></nav><h1>' . $title . '</h1>' . $html . '</div></section>',
    ]);
}

function legal_pages(): array
{
    $C = S::$c;
    $updRu = implode('.', array_reverse(explode('-', S::$updated)));
    return [
        'politika' => ['Политика конфиденциальности', '
<p>Редакция от ' . $updRu . '. Политика составлена в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ «О персональных данных» и определяет порядок обработки персональных данных посетителей сайта ' . $C['siteUrl'] . '.</p>
<h2>1. Оператор</h2>
<p>' . $C['legalName'] . ', ИНН ' . $C['inn'] . ', ОГРН ' . $C['ogrn'] . '. Адрес: ' . $C['address'] . '. E-mail для обращений: <a href="mailto:' . $C['email'] . '">' . $C['email'] . '</a>.</p>
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
<p>Вы можете запросить сведения о своих данных, потребовать их уточнения или удаления, а также отозвать согласие, написав на <a href="mailto:' . $C['email'] . '">' . $C['email'] . '</a>. Мы ответим в срок, установленный законом.</p>
<h2>9. Защита данных</h2>
<p>Оператор принимает организационные и технические меры для защиты персональных данных от неправомерного доступа, изменения и распространения.</p>'],
        'soglasie' => ['Согласие на обработку персональных данных', '
<p>Оставляя заявку на сайте ' . $C['siteUrl'] . ', я в соответствии с Федеральным законом № 152-ФЗ «О персональных данных» свободно, своей волей и в своём интересе даю согласие ' . $C['legalName'] . ' (ИНН ' . $C['inn'] . ', ОГРН ' . $C['ogrn'] . ', адрес: ' . $C['address'] . ', e-mail: ' . $C['email'] . ') на обработку моих персональных данных.</p>
<h2>Данные</h2><p>Имя, номер телефона, текст сообщения, прикреплённые файлы.</p>
<h2>Цели</h2><ul><li>обработка заявки и обратная связь со мной, в том числе через мессенджеры и e-mail;</li><li>консультации по товарам и услугам;</li><li>заключение и исполнение договора.</li></ul>
<h2>Действия с данными</h2><p>Сбор, запись, систематизация, накопление, хранение, уточнение, использование, передача (в объёме, необходимом для доставки заказа), удаление и уничтожение — с использованием средств автоматизации и без них.</p>
<h2>Срок и отзыв</h2><p>Согласие действует до достижения целей обработки или до его отзыва. Отозвать согласие можно, направив письмо на <a href="mailto:' . $C['email'] . '">' . $C['email'] . '</a>.</p>
<p>Подробнее — в <a href="/politika/">политике конфиденциальности</a>.</p>'],
    ];
}

function page_404(): string
{
    return layout(['title' => 'Страница не найдена | ' . S::$c['brand'], 'description' => 'Страница не найдена', 'canonical' => '/404.html', 'bodyClass' => 'legal',
        'body' => '<section class="sec"><div class="wrap legal-txt"><h1>Страница не найдена</h1><p>Возможно, клеймо переехало. Загляните в <a href="/catalog/">каталог</a> или на <a href="/">главную</a>.</p></div></section>']);
}
