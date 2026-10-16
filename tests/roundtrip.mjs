// Каждый раздел и каждый товар загрузить и сохранить без изменений: должно быть «Изменений нет».
// Ловит потерю полей вне формы, null -> "", пустые списки, порядок ключей. После теста: git status data — чисто.
import { login, api, ok } from './lib.mjs';
await login();
const boot = await api('bootstrap');
ok(boot.ok, 'bootstrap');
for (const n of boot.nav) {
  const s = await api('section?id=' + n.id);
  const r = await api('section', { id: n.id, values: s.values });
  ok(r.ok && r.message === 'Изменений нет', `раздел «${n.title}»: ${r.message || r.error}`);
}
const { products } = await api('products');
let bad = 0;
for (const p of products) {
  const d = await api('product?sku=' + p.sku);
  const r = await api('product', { sku: p.sku, values: d.values });
  if (!(r.ok && r.message === 'Изменений нет')) { bad++; console.error(`товар ${p.sku} «${p.short}»: ${r.message || r.error}`); }
}
ok(bad === 0, `товары: ${products.length} сохранены без изменений`);
