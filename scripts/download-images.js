// Скачивает фото товаров с CDN Ozon (уменьшенные WebP) в public/img/products/<sku>/
const fs = require('fs'), path = require('path');
const raw = require('../data/ozon_raw.json');
const out = path.join(__dirname, '../public/img/products');
(async () => {
  for (const p of raw) {
    const dir = path.join(out, String(p.sku)); fs.mkdirSync(dir, { recursive: true });
    for (let i = 0; i < p.imgs.length; i++) {
      for (const [size, suf] of [['wc1000', ''], ['wc400', '-s']]) {
        const f = path.join(dir, `${i + 1}${suf}.webp`);
        if (fs.existsSync(f)) continue;
        const u = p.imgs[i].replace(/(multimedia-[^/]+\/)/, `$1${size}/`);
        let buf = null;
        for (let t = 0; t < 4 && !buf; t++) {
          try { const r = await fetch(u); if (r.ok) buf = Buffer.from(await r.arrayBuffer()); } catch (e) { await new Promise(r => setTimeout(r, 1500)); }
        }
        if (!buf) { console.log('fail', u); continue; }
        fs.writeFileSync(f, buf);
      }
    }
    process.stdout.write('.');
  }
  console.log('\nok');
})();
