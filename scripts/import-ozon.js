// Превращает сырую выгрузку Ozon (data/ozon_raw.json) в каталог сайта (data/products.json).
// Запускать после новой выгрузки: node scripts/import-ozon.js
// Ручные правки названий/категорий — в OVERRIDES ниже, а не в products.json (он перезаписывается).
const fs = require('fs'), path = require('path');
const raw = require('../data/ozon_raw.json');

// Названия для позиций, у которых на Ozon общее имя «Клеймо ударное» (взяты с фото товара)
const OVERRIDES = {
  4840849029: { title: 'Клеймо «Серп и молот в звезде»', category: 'symbols' },
  5900439548: { title: 'Клеймо «Скрещённые молоты»', category: 'symbols' },
  5900452062: { title: 'Клеймо «Стрела в звезде»', category: 'symbols' },
  4840579836: { title: 'Клеймо «Знак ГОСТ»', category: 'quality' },
  5900454782: { hidden: true }, // на Ozon одно фото пустой заготовки — на сайт не выводим
  3781589530: { category: 'equipment', short: 'Пресс с ЧПУ 50 т' },
  3539590846: { category: 'equipment', title: 'Набор клейм «ОТК в треугольнике» по металлу', short: 'Набор «ОТК в треугольнике»' },
  // Короткие имена для карточек, где в названии на Ozon перечислены характеристики
  1850277466: { short: 'EAC 4 мм, У8А' },
  1833963871: { short: 'EAC 4×6 мм' },
  1798634539: { short: 'EAC 5 мм, 9ХС' },
  3308309654: { short: 'Российский знак качества' },
  1770827744: { short: 'Серп и молот 8 мм' },
  1732628770: { short: 'ОТК 6-9 в круге' },
  1779655705: { short: 'EAC в круге 12 мм' },
  2548573394: { short: 'ОТК 6, ⌀10 мм' },
  1985239792: { short: 'РСТ 8 мм' },
  3308290687: { short: 'ОТК в треугольнике' },
  1732593481: { short: 'ОТК 14×18 мм' },
  3308331834: { short: 'ОТК в рамке 10×10' },
  2582159163: { short: 'Буква К' },
  3601058204: { short: 'Буква Ж' },
  2748751111: { short: 'Знак заземления' },
  3358521255: { short: 'Знак качества СССР' },
  3069272594: { short: 'ОТК 3,5 мм' },
  2021151840: { short: 'ОТК 8 мм' },
  4838138933: { title: 'Ударное клеймо «Наковальня и молот» по металлу', short: 'Наковальня и молот' },
  3388572078: { title: 'Ударное клеймо «Герб РФ» (двуглавый орёл) по металлу', short: 'Герб РФ' },
  3583109849: { title: 'Ударное клеймо «RU — Сделано в России» по металлу', short: 'RU' },
  1732593481: { title: 'Клеймо ОТК для пресса, в рамке 14×18 мм, сталь 9ХС, 50-55 HRC', short: 'ОТК для пресса 14×18', category: 'otk' },
};


// Характеристики, напечатанные на главном фото товара (сталь, заготовка, высота символа). HRC на фото — 53-58.
// Формат: sku: [сталь, заготовка мм, высота символа/фигуры мм | null]
const PHOTO_SPECS = {
  2548412438: ['9ХС', 20], 1732570493: ['У8А', 10, 3], 3583109849: ['У8А', 10], 3308309654: ['9ХС', 20],
  4838138933: ['9ХС', 20, 10], 4840849029: ['9ХС', 20, 13], 3044088820: ['У8А', 10, 7], 3987977621: ['У8А', 10, 5],
  3545537905: ['9ХС', 20], 5900439548: ['9ХС', 20], 5900452062: ['9ХС', 20], 3069182365: ['9ХС', 20, 10],
  1770827744: ['9ХС', 20, 8], 4840579836: ['9ХС', 20], 3069294801: ['У8А', 10, 3], 1732628770: ['У8А', 10],
  1779655705: ['9ХС', 20, 13], 3527475090: ['9ХС', 20, 10], 3358521255: ['9ХС', 20], 1985239792: ['9ХС', 20],
  3534859694: ['9ХС', 20, 10], 3069272594: ['У8А', 10, 3.5], 3308290687: ['9ХС', 20, 10], 1732593481: ['9ХС', 30],
  3651952709: ['9ХС', 20, 10], 2021151840: ['9ХС', 20, 8], 3388572078: ['9ХС', 20, 12], 2877903203: ['9ХС', 20, 3.5],
  3601058204: ['9ХС', 20], 3528415556: ['У8А', 10], 3308331834: ['9ХС', 20, 10], 3069287045: ['У8А', 10, 3],
  2748751111: ['9ХС', 20, 15], 3078324787: ['У8А', 10, 3], 3069193681: ['9ХС', 20, 10], 3315163401: ['9ХС', 20, 10],
};

const CATEGORIES = [
  { id: 'otk', name: 'Клейма ОТК', short: 'ОТК', desc: 'Клейма отдела технического контроля: ОТК в треугольнике, в рамке, в круге, с номером контролёра.' },
  { id: 'quality', name: 'Знаки соответствия и качества', short: 'EAC, РСТ, знаки', desc: 'EAC, РСТ, RU, «Сделано в России», знаки качества и ГОСТ.' },
  { id: 'steel', name: 'Марки стали', short: 'Марки стали', desc: 'Клейма с обозначением марки металла: 12Х18Н10Т, AISI 316, 09Г2С и другие.' },
  { id: 'symbols', name: 'Символы и литеры', short: 'Символы', desc: 'Наковальня, якорь РС, герб, заземление, буквы и фигурные знаки.' },
  { id: 'equipment', name: 'Наборы клейм', short: 'Наборы', desc: 'Наборы клейм для участков, где работают несколько контролёров.' },
];

function categorize(t) {
  if (/^Пресс|набор/i.test(t)) return 'equipment';
  if (/ОТК/i.test(t)) return 'otk';
  if (/EAC|ЕАС|РСТ|\(RU\)|MADE IN|СДЕЛАНО|знак качества|ГОСТ/i.test(t)) return 'quality';
  if (/\((?:\d{2,}[ХГНМТ]|AISI|\d{3}L|9ХС|У8А|40Х|30Х13|20Х13|09Г2С)/i.test(t)) return 'steel';
  return 'symbols';
}

function cleanTitle(t) {
  return t
    .replace(/клемо/i, 'клеймо').replace(/Росийский/i, 'Российский')
    .replace(/\(\s+/g, '(').replace(/\s+\)/g, ')')
    .replace(/\.$/, '').replace(/\s{2,}/g, ' ').trim();
}

// Короткое имя для карточки: «Ударное клеймо (ОТК2) по металлу» -> «ОТК2»
function shortName(t) {
  const m = t.match(/\(([^)]+)\)/);
  if (m && !/^\s*\d+\s*мм/i.test(m[1])) return m[1].trim();
  return t.replace(/^(Ударное\s+)?(клеймо|Клеймо)\s+(ударное\s+)?/i, '').replace(/,?\s*по металлу\.?/i, '').replace(/^«|»$/g, '').trim();
}

const num = s => s ? Number(s.replace(/[^\d]/g, '')) : null;
function specs(t) {
  const s = {};
  const steel = t.match(/сталь\s+(9ХС|У8А)/i); if (steel) s.steel = steel[1].toUpperCase();
  const hrc = t.match(/(\d{2}-\d{2})\s*HRC/i); if (hrc) s.hrc = hrc[1];
  const font = t.match(/(?:шрифт|высота символов|H-)\s*(\d+)\s*мм/i); if (font) s.font = font[1] + ' мм';
  const base = t.match(/(?:основании|заготовке)\s*(\d+)\s*мм/i); if (base) s.base = base[1] + ' мм';
  return s;
}

function mergeSpecs(t, ph) {
  if (!ph) return t;
  const [steel, base, h] = ph;
  return { steel, hrc: '53-58', base: base + ' мм', ...(h ? { font: String(h).replace('.', ',') + ' мм' } : {}), ...t };
}

const slugMap = { а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'e',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'h',ц:'ts',ч:'ch',ш:'sh',щ:'sch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya' };
const slugify = (s, sku) => s.toLowerCase().split('').map(c => slugMap[c] ?? c).join('')
  .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60).replace(/-$/, '') + '-' + sku;

const products = raw.filter(r => !(OVERRIDES[r.sku] || {}).hidden).map((r, i) => {
  const o = OVERRIDES[r.sku] || {};
  const title = o.title || cleanTitle(r.title);
  const [price, oldPrice] = r.price.map(num);
  const imgDir = path.join(__dirname, '../public/img/products', String(r.sku));
  const images = r.imgs.map((_, k) => k + 1).filter(k => fs.existsSync(path.join(imgDir, `${k}.webp`)));
  return {
    sku: r.sku,
    slug: slugify(o.short || shortName(title), r.sku),
    title,
    short: o.short || shortName(title),
    category: o.category || categorize(title),
    price, oldPrice: oldPrice || null,
    rating: r.labels[0] && /^\d[.,]\d$/.test(r.labels[0]) ? r.labels[0].replace('.', ',') : null,
    reviews: r.labels[1] && /отзыв/.test(r.labels[1]) ? r.labels[1] : null,
    specs: mergeSpecs(specs(title), PHOTO_SPECS[r.sku]),
    images,
    ozon: `https://www.ozon.ru/product/${r.sku}/`,
    order: i,
  };
});

fs.writeFileSync(path.join(__dirname, '../data/products.json'),
  JSON.stringify({ updated: '2026-10-12', source: 'ozon.ru/seller/teh-enerdgy-2374593', categories: CATEGORIES, products }, null, 1) + '\n');
const by = {}; products.forEach(p => by[p.category] = (by[p.category] || 0) + 1);
console.log(products.length, by, 'без фото:', products.filter(p => !p.images.length).map(p => p.sku).join(','));
