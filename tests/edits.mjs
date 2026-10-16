// Правки через админку и их результат на собранном сайте. Запуск на локальном сервере (php -S ... app/dev-router.php),
// после теста: git checkout data && git clean -fd public/img (тест создаёт товар и фото).
import fs from 'node:fs';
import { login, api, ok, req, BASE } from './lib.mjs';
const DIST = 'dist';
const page = p => fs.readFileSync(`${DIST}${p}index.html`, 'utf8');
const jpeg = fs.readFileSync(process.env.TEST_JPEG || 'tests/test.jpg');
const file = (name = 'photo.jpg') => { const fd = new FormData(); fd.append('file', new Blob([jpeg], { type: 'image/jpeg' }), name); return fd; };

await login();
const SKU = '2582159163', URL = '/catalog/bukva-k-2582159163/';
let d = await api('product?sku=' + SKU);
const P = 'products.json#products.{svc}.';
const price0 = d.values[P + 'price'];

// цена
let r = await api('product', { sku: SKU, values: { ...d.values, [P + 'price']: 2900 } });
ok(r.ok, 'цена сохранена: ' + (r.message || r.error));
ok(page(URL).includes('<b>2 900 ₽</b>'), 'новая цена на странице товара');
ok(page('/catalog/').includes('data-price="2900"'), 'новая цена в каталоге');

// откат по истории
const h = await api('revisions');
r = await api('revision/restore', { id: h.items[0].id });
ok(r.ok && page(URL).includes('<b>' + price0.toLocaleString('ru-RU').replace(/ /g, ' ') + ' ₽</b>'), 'откат по истории вернул цену');

// неверное значение — отказ, данные не меняются
const before = fs.readFileSync('data/products.json', 'utf8');
r = await api('product', { sku: SKU, values: { ...d.values, [P + 'price']: 0 } });
ok(!r.ok && /Цена/.test(r.error), 'цена 0 отклонена: ' + r.error);
r = await api('product', { sku: SKU, values: { ...d.values, [P + 'images']: [1, 99] } });
ok(!r.ok && /фото 99/.test(r.error), 'несуществующее фото отклонено');
ok(fs.readFileSync('data/products.json', 'utf8') === before, 'данные не изменились');

// экранирование: кавычки и угловые скобки в названии
d = await api('product?sku=' + SKU);
r = await api('product', { sku: SKU, values: { ...d.values, [P + 'short']: 'Буква «К» <b>' } });
ok(r.ok && page(URL).includes('Буква «К» &lt;b&gt;') && !page(URL).includes('Буква «К» <b>'), 'название экранируется');
await api('product', { sku: SKU, values: d.values });

// телефон: ссылка tel: обновляется сама
const c = await api('section?id=company');
r = await api('section', { id: 'company', values: { ...c.values, 'company.json#phone': '8 (912) 000-11-22' } });
ok(r.ok && page('/').includes('href="tel:+79120001122"') && page('/').includes('8 (912) 000-11-22'), 'телефон и ссылка tel: на сайте');
r = await api('section', { id: 'company', values: { ...c.values, 'company.json#phone': '123' } });
ok(!r.ok, 'короткий телефон отклонён');
await api('section', { id: 'company', values: c.values });
ok(JSON.parse(fs.readFileSync('data/company.json', 'utf8')).phoneRaw === '+79082779546', 'телефон вернулся');

// скрыть товар — страница убирается, вернуть — появляется
r = await api('product', { sku: SKU, values: { ...d.values, [P + 'hidden']: true } });
ok(r.ok && !fs.existsSync(`${DIST}${URL}index.html`) && !page('/catalog/').includes(URL), 'скрытый товар убран с сайта');
await api('product', { sku: SKU, values: d.values });
ok(fs.existsSync(`${DIST}${URL}index.html`), 'товар вернулся');

// товар, на который ведёт редирект со старого сайта, скрыть нельзя
const OTK5 = '1732570493';
const d5 = await api('product?sku=' + OTK5);
r = await api('product', { sku: OTK5, values: { ...d5.values, [P + 'hidden']: true } });
ok(!r.ok && /редирект/.test(r.error) && fs.existsSync(`${DIST}/catalog/otk5-1732570493/index.html`), 'защита редиректа: ' + (r.error || '').slice(0, 90));

// новый товар: создаётся скрытым, фото, показ, удаление в корзину, возврат
r = await api('product/create', { short: 'Тест Ж', category: 'symbols' });
ok(r.ok, 'товар создан: арт. ' + r.sku);
const NEW = r.sku;
const fd = file(); fd.append('sku', NEW);
r = await api('product/photo', null, fd);
ok(r.ok && r.n === 1 && fs.existsSync(`public/img/products/${NEW}/1.webp`) && fs.existsSync(`public/img/products/${NEW}/1-s.webp`), 'фото товара загружено и уменьшено');
d = await api('product?sku=' + NEW);
r = await api('product', { sku: NEW, values: { ...d.values, [P + 'images']: [1], [P + 'hidden']: false, [P + 'price']: 1234 } });
const NEWURL = d.url;
ok(r.ok && page(NEWURL).includes('1 234 ₽') && page('/catalog/').includes(NEWURL), 'новый товар на сайте: ' + NEWURL);
r = await api('product/delete', { sku: NEW });
ok(r.ok && !fs.existsSync(`${DIST}${NEWURL}index.html`) && !fs.existsSync(`public/img/products/${NEW}`), 'удалён в корзину вместе с фото');
const t = await api('trash');
r = await api('trash/restore', { id: t.items[0].id });
ok(r.ok && fs.existsSync(`public/img/products/${NEW}/1.webp`), 'возвращён из корзины: ' + r.message);
d = await api('product?sku=' + NEW);
ok(d.values[P + 'hidden'] === true, 'после возврата — скрыт');

// товар пресса удалить нельзя
r = await api('product/delete', { sku: '3781589530' });
ok(!r.ok, 'товар пресса не удаляется');

// картинка услуги
r = await api('image/upload', null, file());
ok(r.ok && fs.existsSync('public' + r.small) && fs.existsSync('public' + r.full), 'картинка услуги загружена: ' + r.small);
const L = await api('section?id=laser');
const svc = structuredClone(L.values['laser.json#services']);
svc[0].photo = r.small;
let r2 = await api('section', { id: 'laser', values: { ...L.values, 'laser.json#services': svc } });
ok(r2.ok && page('/gravirovka/').includes(r.small) && page('/gravirovka/shildiki-i-tablichki/').includes(r.full), 'новое фото услуги на сайте');
svc[0].photo = '/img/nope.jpg';
r2 = await api('section', { id: 'laser', values: { ...L.values, 'laser.json#services': svc } });
ok(!r2.ok, 'несуществующая картинка отклонена');
await api('section', { id: 'laser', values: L.values });

// заявка с файлом: журнал, файл в админке
const lf = new FormData();
for (const [k, v] of Object.entries({ name: 'Тест', phone: '+7 (900) 111-22-33', message: 'Проверка', product: 'Буква К (арт. 2582159163)', source: 'modal', consent: '1', website: '' })) lf.append(k, v);
lf.append('file', new Blob([jpeg], { type: 'image/jpeg' }), 'эскиз.jpg');
const lr = await (await fetch(BASE + '/send.php', { method: 'POST', body: lf })).json();
ok(lr.ok, 'заявка принята');
const leads = await api('leads');
const L0 = leads.items[0];
ok(L0.phone === '+7 (900) 111-22-33' && L0.file, 'заявка в журнале с файлом ' + L0.file);
const dl = await req('/admin/lead-file?name=' + encodeURIComponent(L0.file));
ok(dl.status === 200 && (await dl.arrayBuffer()).byteLength === jpeg.length, 'вложение скачивается из админки');
const bad = new FormData(); bad.append('phone', '123'); bad.append('consent', '1');
ok((await fetch(BASE + '/send.php', { method: 'POST', body: bad })).status === 400, 'заявка без телефона отклонена');

// без входа — нельзя
const anon = await fetch(BASE + '/admin/api/products');
ok(anon.status === 401 || anon.status === 404, 'API без входа закрыт: ' + anon.status);
const anonFile = await fetch(BASE + '/admin/lead-file?name=' + encodeURIComponent(L0.file), { redirect: 'manual' });
ok(anonFile.status !== 200, 'вложение без входа не отдаётся: ' + anonFile.status);
