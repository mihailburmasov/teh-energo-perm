// ТЕХ-ЭНЕРГО — клиентские скрипты (без зависимостей)
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const store = {
    get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* приватный режим */ } },
  };

  // ---------- шапка и меню ----------
  const hdr = $('#hdr');
  const onScroll = () => hdr && hdr.classList.toggle('is-solid', scrollY > 40);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();
  const burger = $('#burger'), nav = $('#nav');
  if (burger) {
    burger.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open);
      hdr.classList.add('is-solid');
    });
    $$('a', nav).forEach(a => a.addEventListener('click', () => { nav.classList.remove('is-open'); burger.setAttribute('aria-expanded', 'false'); }));
  }

  // ---------- появление блоков ----------
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } }), { rootMargin: '0px 0px -8% 0px' });
    $$('.reveal').forEach((el, i) => { el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });
  } else $$('.reveal').forEach(el => el.classList.add('is-in'));

  // ---------- cookie ----------
  const ck = $('#cookie');
  if (ck && !store.get('cookie-ok')) { ck.hidden = false; $('#cookie-ok').addEventListener('click', () => { store.set('cookie-ok', '1'); ck.hidden = true; }); }

  // ---------- модалка заявки ----------
  const modal = $('#order');
  let lastFocus = null;
  function openOrder(product, sub) {
    if (!modal) return;
    lastFocus = document.activeElement;
    const f = $('form', modal);
    f.reset(); $('.form__msg', f).textContent = ''; $('.form__msg', f).className = 'form__msg';
    f.elements.product.value = product || '';
    $('#order-title').textContent = product ? 'Заказ' : 'Заявка';
    $('#order-sub').textContent = sub || (product ? product : 'Перезвоним в рабочее время, уточним детали и сроки.');
    modal.hidden = false; document.body.style.overflow = 'hidden';
    setTimeout(() => f.elements.phone.focus(), 60);
  }
  function closeOrder() { if (!modal || modal.hidden) return; modal.hidden = true; document.body.style.overflow = ''; lastFocus && lastFocus.focus(); }
  document.addEventListener('click', e => {
    const t = e.target.closest('[data-order]');
    if (t) { e.preventDefault(); openOrder(t.dataset.product); }
    if (e.target.closest('[data-close]')) closeOrder();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeOrder();
    if (e.key === 'Tab' && modal && !modal.hidden) { // удерживаем фокус внутри окна
      const f = $$('button, a[href], input:not([type=hidden]):not([tabindex="-1"]), textarea, select', modal).filter(x => x.offsetParent);
      if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  });

  // ---------- формы ----------
  function maskPhone(el) {
    el.addEventListener('input', () => {
      let d = el.value.replace(/\D/g, '');
      if (!d) { el.value = ''; return; }
      if (d[0] === '8') d = '7' + d.slice(1); if (d[0] !== '7') d = '7' + d;
      d = d.slice(0, 11);
      const p = [d.slice(1, 4), d.slice(4, 7), d.slice(7, 9), d.slice(9, 11)];
      el.value = '+7' + (p[0] ? ` (${p[0]}` : '') + (p[0].length === 3 ? ')' : '') + (p[1] ? ` ${p[1]}` : '') + (p[2] ? `-${p[2]}` : '') + (p[3] ? `-${p[3]}` : '');
    });
  }
  $$('form[data-lead]').forEach(form => {
    maskPhone(form.elements.phone);
    const file = form.elements.file;
    if (file) file.addEventListener('change', () => {
      const t = $('.file__txt', form), f = file.files[0];
      if (!f) return;
      if (f.size > 10 * 1024 * 1024) { file.value = ''; t.firstChild.textContent = 'Файл больше 10 МБ — пришлите в мессенджер'; return; }
      t.firstChild.textContent = f.name + ' ';
    });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const msg = $('.form__msg', form), btn = $('button[type=submit]', form);
      const phone = form.elements.phone, digits = phone.value.replace(/\D/g, '');
      msg.className = 'form__msg';
      phone.closest('.fld').classList.toggle('is-bad', digits.length < 11);
      if (digits.length < 11) { msg.textContent = 'Укажите телефон полностью — 11 цифр.'; msg.classList.add('is-err'); phone.focus(); return; }
      if (!form.elements.consent.checked) { msg.textContent = 'Нужно согласие на обработку персональных данных.'; msg.classList.add('is-err'); return; }
      btn.disabled = true; msg.textContent = 'Отправляем…';
      try {
        const r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok) throw new Error(j.error || 'send');
        form.reset();
        msg.textContent = 'Спасибо! Заявка отправлена — свяжемся с вами в рабочее время.'; msg.classList.add('is-ok');
      } catch (err) {
        const B = document.body.dataset;
        msg.innerHTML = `Не удалось отправить заявку. Позвоните <a href="tel:${B.phoneRaw}">${B.phone}</a> или напишите в <a href="${B.tg}" target="_blank" rel="noopener">Telegram</a>.`;
        msg.classList.add('is-err');
      } finally { btn.disabled = false; }
    });
  });

  // ---------- галерея товара ----------
  $$('[data-gallery]').forEach(g => {
    const main = $('.gallery__main img', g);
    $$('.gallery__thumbs button', g).forEach(b => b.addEventListener('click', () => {
      main.src = b.dataset.src;
      $$('.gallery__thumbs button', g).forEach(x => x.classList.toggle('is-on', x === b));
    }));
  });

  // ---------- каталог: фильтры, поиск, сортировка ----------
  const grid = $('#grid');
  if (grid && $('#filters')) {
    const cards = $$('.card', grid), chips = $$('.chip'), q = $('#q'), sort = $('#sort'), empty = $('#empty'), desc = $('#cat-desc');
    let cat = 'all';
    const apply = () => {
      const s = q.value.trim().toLowerCase().replace(/ё/g, 'е');
      let n = 0;
      cards.forEach(c => {
        const ok = (cat === 'all' || c.dataset.cat === cat) && (!s || c.dataset.q.replace(/ё/g, 'е').includes(s));
        c.classList.toggle('is-hidden', !ok); if (ok) n++;
      });
      empty.hidden = n > 0;
      chips.forEach(ch => ch.classList.toggle('is-on', ch.dataset.cat === cat));
      desc.textContent = cat === 'all' ? '' : (window.CATS || {})[cat] || '';
    };
    const sorter = () => {
      const k = sort.value;
      cards.sort((a, b) => k === 'asc' ? a.dataset.price - b.dataset.price : k === 'desc' ? b.dataset.price - a.dataset.price : a.dataset.order - b.dataset.order)
        .forEach(c => grid.appendChild(c));
    };
    const fromHash = () => { const h = location.hash.slice(1); cat = chips.some(c => c.dataset.cat === h) ? h : 'all'; apply(); };
    chips.forEach(ch => ch.addEventListener('click', () => { cat = ch.dataset.cat; history.replaceState(null, '', cat === 'all' ? location.pathname : '#' + cat); apply(); }));
    q.addEventListener('input', apply);
    sort.addEventListener('change', sorter);
    addEventListener('hashchange', fromHash);
    fromHash();
  }

  // ---------- конструктор клейма ----------
  const svg = $('#c-svg');
  if (svg) {
    const NS = 'http://www.w3.org/2000/svg';
    const el = (tag, attrs, parent) => { const n = document.createElementNS(NS, tag); for (const k in attrs) n.setAttribute(k, attrs[k]); parent && parent.appendChild(n); return n; };
    const st = { text: $('#c-text'), h: $('#c-h'), d: $('#c-d'), qty: $('#c-qty') };
    const stage = $('#c-stage'), cap = $('#c-cap'), hOut = $('#c-h-out');
    let view = 'imprint';
    const val = name => ($(`input[name="${name}"]:checked`) || {}).value;

    // общие defs: металл, шлифовка, тиснение
    svg.innerHTML = `<defs>
      <radialGradient id="g-die" cx="40%" cy="35%" r="75%"><stop offset="0" stop-color="#5b616b"/><stop offset=".55" stop-color="#2c3038"/><stop offset="1" stop-color="#14161a"/></radialGradient>
      <linearGradient id="g-plate" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#d9dde3"/><stop offset=".45" stop-color="#b5bbc4"/><stop offset="1" stop-color="#8e959f"/></linearGradient>
      <pattern id="p-brush" width="400" height="4" patternUnits="userSpaceOnUse"><rect width="400" height="1" y="1" fill="#fff" opacity=".18"/><rect width="400" height="1" y="3" fill="#000" opacity=".06"/></pattern>
      <filter id="f-engrave" x="-10%" y="-10%" width="120%" height="120%"><feOffset dx="0" dy="1.6" in="SourceAlpha" result="o"/><feFlood flood-color="#fff" flood-opacity=".7"/><feComposite in2="o" operator="in" result="hi"/><feOffset dx="0" dy="-1.4" in="SourceAlpha" result="o2"/><feFlood flood-color="#1b1e23" flood-opacity=".75"/><feComposite in2="o2" operator="in" result="sh"/><feMerge><feMergeNode in="hi"/><feMergeNode in="sh"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
      <filter id="f-raise"><feDropShadow dx="0" dy="2" stdDeviation="1.2" flood-color="#000" flood-opacity=".8"/></filter>
    </defs><g id="c-root"></g>`;
    const root = $('#c-root', svg);

    function frameShape(kind, cx, cy, w, h, g, attrs) {
      const pad = 0.28;
      if (kind === 'circle') return el('circle', { cx, cy, r: Math.max(w, h) / 2 * (1 + pad) + 6, ...attrs }, g);
      if (kind === 'square') { const s = Math.max(w * (1 + pad), h * (1 + pad * 2)); const W = Math.max(w * (1 + pad), s * .8); return el('rect', { x: cx - W / 2, y: cy - h * (1 + pad * 2) / 2 - 4, width: W, height: h * (1 + pad * 2) + 8, rx: 4, ...attrs }, g); }
      const poly = pts => el('polygon', { points: pts.map(p => p.join(',')).join(' '), ...attrs }, g);
      if (kind === 'triangle') { const side = Math.max(w * 1.45 + h * 1.2, h * 3.2); const H = side * Math.sqrt(3) / 2; const top = cy - H * 0.62; return poly([[cx, top], [cx + side / 2, top + H], [cx - side / 2, top + H]]); }
      if (kind === 'pentagon') { const R = Math.max(w, h * 1.6) / 2 * 1.45; return poly([...Array(5)].map((_, i) => { const a = -Math.PI / 2 + i * 2 * Math.PI / 5; return [cx + R * Math.cos(a), cy + 4 + R * Math.sin(a)]; })); }
      return null;
    }

    function draw() {
      const text = (st.text.value || ' ').toUpperCase();
      const hmm = +st.h.value, dmm = +st.d.value, frame = val('c-frame');
      hOut.textContent = hmm;
      root.innerHTML = '';
      const R = 160, cx = 200, cy = 200, pxPerMm = (2 * R) / dmm;
      const isDie = view === 'die';
      // фон
      if (isDie) {
        el('circle', { cx, cy, r: R + 14, fill: '#0b0c0f' }, root);
        el('circle', { cx, cy, r: R, fill: 'url(#g-die)', stroke: '#5c626c', 'stroke-width': 2 }, root);
      } else {
        el('rect', { x: 0, y: 0, width: 400, height: 400, rx: 22, fill: 'url(#g-plate)' }, root);
        el('rect', { x: 0, y: 0, width: 400, height: 400, rx: 22, fill: 'url(#p-brush)' }, root);
        el('circle', { cx, cy, r: R, fill: 'none', stroke: '#6b727c', 'stroke-dasharray': '3 6', 'stroke-width': 1, opacity: .6 }, root);
      }
      const g = el('g', isDie ? { transform: `translate(${2 * cx} 0) scale(-1 1)`, filter: 'url(#f-raise)' } : { filter: 'url(#f-engrave)' }, root);
      const color = isDie ? '#c9ced6' : '#5d646e';
      const fontPx = hmm * pxPerMm / 0.72; // высота заглавной ≈ 0.72 кегля
      const t = el('text', { x: cx, y: cy, 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-family': 'Manrope, Arial, sans-serif', 'font-weight': 800, 'font-size': fontPx, fill: color }, g);
      t.textContent = text;
      let w = t.getComputedTextLength();
      const capH = hmm * pxPerMm;
      const maxW = frame === 'triangle' ? R * 1.05 : frame === 'none' ? R * 1.8 : R * 1.45;
      const natW = w;
      let squeezed = false;
      if (w > maxW) { t.setAttribute('textLength', maxW); t.setAttribute('lengthAdjust', 'spacingAndGlyphs'); w = maxW; squeezed = true; }
      if (frame === 'triangle') t.setAttribute('y', cy + capH * 0.35);
      const shape = frameShape(frame, cx, cy, w, capH, g, { fill: 'none', stroke: color, 'stroke-width': Math.max(2, pxPerMm * 0.45), 'stroke-linejoin': 'round' });
      let tooBig = (squeezed && natW > maxW * 1.2) || capH > R * 1.5;
      if (shape) { const b = shape.getBBox(); if (b.width > 2 * R * 1.04 || b.height > 2 * R * 1.04) tooBig = true; }
      cap.textContent = tooBig
        ? `Надпись не помещается на заготовке ⌀${dmm} мм — уменьшите шрифт, сократите текст или выберите заготовку больше.`
        : `${isDie ? 'Рабочая часть (зеркально)' : 'Оттиск'} · символы ${hmm} мм · заготовка ⌀${dmm} мм · сталь ${val('c-steel')}`;
      cap.style.color = tooBig ? '#ffb21a' : '';
    }

    $$('input, select', $('.cons__ctrl')).forEach(i => i.addEventListener('input', draw));
    $$('.cons__tabs button').forEach(b => b.addEventListener('click', () => {
      view = b.dataset.view; stage.dataset.view = view;
      $$('.cons__tabs button').forEach(x => x.setAttribute('aria-selected', x === b));
      draw();
    }));
    $('#c-send').addEventListener('click', () => {
      const frames = { none: 'без рамки', circle: 'в круге', triangle: 'в треугольнике', square: 'в квадрате', pentagon: 'в пятиугольнике' };
      const conf = `Клеймо «${st.text.value.trim() || '—'}» ${frames[val('c-frame')]}, символы ${st.h.value} мм, заготовка ⌀${st.d.value} мм, сталь ${val('c-steel')}, ${Math.max(1, +st.qty.value || 1)} шт.`;
      openOrder(conf, conf);
    });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);
    draw();
  }
})();
