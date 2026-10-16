<?php
// Описание форм админки: какие поля есть у каждого раздела и в какое место данных они пишут.
// bind = "файл#путь.в.json". Сохранять можно только поля, описанные здесь.
declare(strict_types=1);

function f(string $type, string $bind, string $label, string $hint = '', array $o = []): array
{
    return ['type' => $type, 'bind' => $bind, 'label' => $label, 'hint' => $hint] + $o;
}
function k(string $type, string $key, string $label, string $hint = '', array $o = []): array
{
    return ['type' => $type, 'key' => $key, 'label' => $label, 'hint' => $hint] + $o;
}
function head(string $title, string $note = ''): array { return ['type' => 'head', 'label' => $title, 'hint' => $note]; }

const SLUG_RE = '~^[a-z0-9-]{2,80}$~';
const SLUG_ERR = 'только латиница, цифры и дефис, например nadpis-po-krugu';

function value_label_item(): array { return [k('text', 'value', 'Крупно', 'Например: от 1 дня'), k('text', 'label', 'Подпись', 'Например: срок изготовления')]; }
function title_text_item(): array { return [k('text', 'title', 'Заголовок'), k('textarea', 'text', 'Текст', '', ['rows' => 2])]; }

function admin_sections(): array
{
    $S = [];
    $co = 'company.json#';
    $S['company'] = ['title' => 'Компания и контакты', 'group' => 'Общее', 'url' => '/#contacts', 'fields' => [
        head('Контакты', 'Показываются в шапке, подвале, формах, на всех страницах и в юридических документах'),
        f('text', $co . 'phone', 'Телефон', 'Как показывать на сайте, например +7 (908) 277-95-46. Ссылка для звонка обновится сама', ['required' => true]),
        f('email', $co . 'email', 'Электронная почта', 'На неё же приходят заявки, если в «Настройках» не указана другая', ['required' => true]),
        f('url', $co . 'max', 'Ссылка на MAX', 'Начинается с https://max.ru/', ['required' => true]),
        f('url', $co . 'vk', 'Группа ВКонтакте', '', ['required' => true]),
        f('url', $co . 'ozonSeller', 'Магазин на Ozon', 'Ссылка «Посмотреть отзывы на Ozon»', ['required' => true]),
        head('Адрес'),
        f('text', $co . 'address', 'Адрес полностью', 'Например: г. Пермь, ул. Хабаровская, 151', ['required' => true]),
        f('text', $co . 'street', 'Улица и дом', 'Для поисковиков', ['required' => true]),
        f('text', $co . 'city', 'Город', '', ['required' => true]),
        f('float', $co . 'geo.lat', 'Широта точки на карте', 'Из Яндекс Карт: первое число, например 58.04804'),
        f('float', $co . 'geo.lon', 'Долгота точки на карте', 'Второе число, например 56.0884'),
        head('Рейтинг покупателей', 'Показывается на главной и в подвале. Цифры — с Ozon'),
        f('text', $co . 'ozonStats.rating', 'Средняя оценка', 'Например: 4,7', ['required' => true]),
        f('number', $co . 'ozonStats.reviews', 'Отзывов', '', ['required' => true]),
        f('number', $co . 'ozonStats.orders', 'Заказов', '', ['required' => true]),
        f('text', $co . 'returnRate', 'Сколько клиентов возвращаются', 'Например: 80%', ['required' => true]),
        head('Реквизиты', 'В подвале и в политике конфиденциальности'),
        f('text', $co . 'legalName', 'Наименование', '', ['required' => true]),
        f('text', $co . 'inn', 'ИНН', '', ['required' => true, 'pattern' => '~^\d{10,12}$~', 'patternError' => '10 или 12 цифр']),
        f('text', $co . 'ogrn', 'ОГРН', '', ['required' => true, 'pattern' => '~^\d{13,15}$~', 'patternError' => '13 или 15 цифр']),
        f('text', $co . 'director', 'Директор'),
    ]];

    $S['faq'] = ['title' => 'Вопросы и ответы', 'group' => 'Общее', 'url' => '/#faq', 'fields' => [
        head('Частые вопросы на главной', 'Они же попадают в поиск Яндекса и Google как «вопросы и ответы»'),
        f('items', 'site.json#faq', 'Вопросы', '', ['item' => [k('text', 'q', 'Вопрос'), k('textarea', 'a', 'Ответ', '', ['rows' => 3])], 'itemName' => 'Вопрос', 'collapsed' => true]),
    ]];

    $cats = doc_get('products.json')['categories'];
    $catFields = [
        head('Категории каталога', 'Названия в фильтре каталога, подвале и на главной. Описание видно над товарами при выборе категории'),
        f('items', 'products.json#categories', 'Категории', 'Категории не добавляются и не удаляются — товары привязаны к ним', ['item' => [k('text', 'name', 'Название', 'Например: Клейма ОТК'), k('text', 'short', 'Коротко', 'В кнопках фильтра'), k('textarea', 'desc', 'Описание', '', ['rows' => 2])], 'itemName' => 'Категория', 'fixed' => true, 'count' => count($cats), 'collapsed' => true]),
        head('Текст «Описание» на странице клейма', 'Общий для всех клейм категории'),
    ];
    foreach ($cats as $c) $catFields[] = f('textarea', 'site.json#catText.' . $c['id'], $c['name'], '', ['rows' => 3]);
    $S['categories'] = ['title' => 'Категории каталога', 'group' => 'Каталог', 'url' => '/catalog/', 'fields' => $catFields];

    $pr = 'press.json#';
    $S['press'] = ['title' => 'Пресс', 'group' => 'Страницы', 'url' => '/pressy/', 'fields' => [
        head('Основное', 'Фото пресса — в каталоге, товар «' . (product_by_sku((string)doc_get('press.json')['ozonSku'])['short'] ?? 'пресс') . '»'),
        f('text', $pr . 'model', 'Модель', '', ['required' => true]),
        f('text', $pr . 'force', 'Усилие', 'Например: 50 тонн', ['required' => true]),
        f('text', $pr . 'availability', 'Наличие', 'Например: В наличии и под заказ', ['required' => true]),
        f('textarea', $pr . 'lead', 'Текст под заголовком', '', ['rows' => 3, 'required' => true]),
        f('items', $pr . 'kpi', 'Цифры на первом экране', 'Лучше 4', ['item' => value_label_item(), 'itemName' => 'Цифра']),
        head('Применение'),
        f('items', $pr . 'uses', 'Задачи', 'Заголовок блока на сайте — «Один пресс — пять задач»', ['item' => title_text_item(), 'itemName' => 'Задача']),
        head('Управление'),
        f('items', $pr . 'control', 'Способы управления', '', ['item' => title_text_item(), 'itemName' => 'Способ']),
        f('list', $pr . 'modes', 'Режимы системы ЧПУ'),
        f('list', $pr . 'options', 'Дополнительные опции'),
        f('textarea', $pr . 'custom', 'Примечание под опциями', '', ['rows' => 2]),
        head('Характеристики', 'Первые 6 строк показываются и на главной'),
        f('items', $pr . 'specs', 'Характеристики', '', ['item' => [k('text', 'name', 'Параметр'), k('text', 'value', 'Значение')], 'itemName' => 'Строка', 'collapsed' => true]),
    ]];

    $l = 'laser.json#';
    $S['laser'] = ['title' => 'Лазерная гравировка', 'group' => 'Страницы', 'url' => '/gravirovka/', 'fields' => [
        head('Первый экран'),
        f('textarea', $l . 'lead', 'Текст под заголовком', 'Он же на главной в блоке гравировки', ['rows' => 3, 'required' => true]),
        f('items', $l . 'facts', 'Три факта', 'Показываются на первом экране и в характеристиках услуг', ['item' => value_label_item(), 'itemName' => 'Факт']),
        f('image', $l . 'hero', 'Большое фото', '', ['required' => true]),
        f('image', $l . 'hero2', 'Маленькое фото поверх', '', ['required' => true, 'small' => true]),
        f('textarea', $l . 'note', 'Примечание о ценах', 'Под заголовком «Маркировка под вашу задачу» и на страницах услуг', ['rows' => 2]),
        head('Услуги', 'Карточки на странице гравировки; у каждой — своя страница. Первые 5 — и на главной'),
        f('items', $l . 'services', 'Услуги', '', ['itemName' => 'Услуга', 'collapsed' => true, 'item' => [
            k('text', 'title', 'Название', '', ['required' => true]),
            k('textarea', 'text', 'Описание', '', ['rows' => 2, 'required' => true]),
            k('text', 'price', 'Цена', 'Например: от 1 000 ₽. Пусто — «по макету»'),
            k('image', 'photo', 'Фото карточки', '', ['small' => true, 'required' => true]),
            k('images', 'photos', 'Ещё фото на странице услуги', 'Необязательно', ['omitEmpty' => true]),
            k('text', 'page', 'Адрес страницы', 'Латиницей: teh-enerdgy.ru/gravirovka/<адрес>/. У существующих услуг не меняйте — на них ведут ссылки', ['required' => true, 'pattern' => '~^([a-z0-9-]{2,80}|/[a-z0-9/-]+/)$~', 'patternError' => SLUG_ERR]),
        ]]),
    ]];

    $m = 'matrix.json#';
    $S['matrix'] = ['title' => 'Матрицы и пуансоны', 'group' => 'Страницы', 'url' => '/matricy/', 'fields' => [
        head('Цена и срок', 'Показываются на странице матриц, на главной и на страницах работ'),
        f('text', $m . 'price', 'Цена', 'Например: от 4 000 ₽', ['required' => true]),
        f('text', $m . 'term', 'Срок изготовления', 'Например: от 3 дней', ['required' => true]),
        head('Первый экран'),
        f('textarea', $m . 'lead', 'Текст под заголовком', 'Он же на главной в блоке матриц', ['rows' => 3, 'required' => true]),
        f('items', $m . 'facts', 'Три факта', '', ['item' => value_label_item(), 'itemName' => 'Факт']),
        f('image', $m . 'hero', 'Большое фото', '', ['required' => true]),
        f('image', $m . 'hero2', 'Маленькое фото поверх', '', ['required' => true, 'small' => true]),
        f('textarea', $m . 'note', 'Текст над карточками работ', '', ['rows' => 2]),
        head('Работы', 'Карточки на странице матриц; у каждой — своя страница. Первые 4 — и на главной'),
        f('items', $m . 'works', 'Работы', '', ['itemName' => 'Работа', 'collapsed' => true, 'item' => [
            k('text', 'title', 'Название', '', ['required' => true]),
            k('textarea', 'text', 'Описание', '', ['rows' => 2, 'required' => true]),
            k('image', 'photo', 'Фото', '', ['small' => true, 'required' => true]),
            k('text', 'slug', 'Адрес страницы', 'Латиницей: teh-enerdgy.ru/matricy/<адрес>/. У существующих работ не меняйте', ['required' => true, 'pattern' => SLUG_RE, 'patternError' => SLUG_ERR]),
        ]]),
    ]];
    return $S;
}

// Форма товара каталога. {svc} — номер товара в списке products.json (определяется по артикулу при каждом запросе)
function product_fields(string $sku): array
{
    $p = 'products.json#products.{svc}.';
    return [
        head('Основное'),
        f('bool', $p . 'hidden', 'Скрыть с сайта', 'Скрытый товар не показывается в каталоге, его страница убирается'),
        f('text', $p . 'title', 'Полное название', 'Заголовок страницы товара и текст для поисковиков', ['required' => true]),
        f('text', $p . 'short', 'Короткое название', 'В карточке каталога, например «ОТК в треугольнике»', ['required' => true]),
        f('catsel', $p . 'category', 'Категория'),
        f('number', $p . 'price', 'Цена, ₽', '', ['required' => true, 'min' => 1]),
        f('number', $p . 'oldPrice', 'Старая цена, ₽', 'Если указать — цена показывается зачёркнутой со скидкой. Пусто — без скидки'),
        head('Фото', 'Первое фото — главное: оно в карточке каталога. Фото обрезаются до формата 3:4'),
        f('prodphotos', $p . 'images', 'Фото товара', '', ['sku' => $sku]),
        head('Характеристики', 'Пустая строка не показывается'),
        f('text', $p . 'specs.steel', 'Сталь', 'Например: 9ХС'),
        f('text', $p . 'specs.hrc', 'Твёрдость, HRC', 'Например: 53-58'),
        f('text', $p . 'specs.font', 'Высота символов', 'Например: 6 мм'),
        f('text', $p . 'specs.base', 'Диаметр заготовки', 'Например: Ф20'),
        head('Рейтинг', 'С Ozon. Пусто — в карточке «Новинка»'),
        f('text', $p . 'rating', 'Оценка', 'Например: 5,0'),
        f('text', $p . 'reviews', 'Отзывы', 'Например: 5 отзывов'),
    ];
}

// Все поля раздела в плоском виде (для проверки, что сохраняется только разрешённое)
function schema_binds(array $fields): array
{
    $out = [];
    foreach ($fields as $fd) if (!empty($fd['bind'])) $out[$fd['bind']] = $fd;
    return $out;
}
