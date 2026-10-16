// Общие помощники тестов админки: вход, запросы к API с cookie и CSRF.
export const BASE = process.env.BASE || 'http://127.0.0.1:8090';
// beget=begetok — cookie защиты Beget от ботов на адресах /admin (браузер ставит её сам скриптом)
const jar = { beget: 'begetok' };
function cookieHeader() { return Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; '); }
function keep(res) {
  for (const c of res.headers.getSetCookie?.() || []) { const [kv] = c.split(';'); const [k, v] = kv.split('='); if (v === '' || /expires=Thu, 01 Jan 1970/i.test(c)) delete jar[k]; else jar[k] = v; }
}
export async function req(path, opts = {}) {
  const res = await fetch(BASE + path, { redirect: 'manual', ...opts, headers: { ...(opts.headers || {}), cookie: cookieHeader() } });
  keep(res);
  return res;
}
let csrf = '';
export async function login(password = process.env.ADMIN_PASS || 'testpass123') {
  if (process.env.SECRET) await req('/admin/' + process.env.SECRET);
  const r = await req('/admin/login', { method: 'POST', body: new URLSearchParams({ password }) });
  if (r.status !== 303) throw new Error('вход не удался: ' + r.status);
  const html = await (await req('/admin/')).text();
  csrf = JSON.parse(html.match(/window\.ADMIN = (\{.*?\});/)[1]).csrf;
}
export async function api(path, body, form) {
  const opts = body || form ? { method: 'POST', headers: { 'X-CSRF': csrf } } : {};
  if (body) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
  if (form) opts.body = form;
  const r = await req('/admin/api/' + path, opts);
  const j = await r.json();
  return j;
}
export function ok(cond, msg) { if (!cond) { console.error('ОШИБКА: ' + msg); process.exitCode = 1; } else console.log('ok  ' + msg); }
