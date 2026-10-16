/* Админка ТЕХ-ЭНЕРГО: разделы, формы по описанию полей с сервера, товары каталога, заявки, история, корзина. */
(function () {
  'use strict';
  var A = window.ADMIN;
  var BOOT = null, dirty = false, saving = false, skipHash = false, formCtl = null;

  // ---------- утилиты ----------
  function $(s, r) { return (r || document).querySelector(s); }
  function h(tag, attrs) {
    var el = document.createElement(tag);
    if (attrs) for (var k in attrs) {
      var v = attrs[k];
      if (v == null || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
      else el.setAttribute(k, v === true ? '' : v);
    }
    for (var i = 2; i < arguments.length; i++) add(el, arguments[i]);
    return el;
  }
  function add(el, c) {
    if (c == null || c === false) return;
    if (Array.isArray(c)) c.forEach(function (x) { add(el, x); });
    else el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  }
  function icon(name) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    s.setAttribute('class', 'ic'); s.setAttribute('aria-hidden', 'true');
    var u = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    u.setAttribute('href', '#i-' + name); s.appendChild(u);
    return s;
  }
  function prodImg(sku, n, small) { return '/img/products/' + sku + '/' + n + (small ? '-s' : '') + '.webp'; }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function rub(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽'; }

  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || (opts.body || opts.form ? 'POST' : 'GET'), headers: { 'X-CSRF': A.csrf }, credentials: 'same-origin' };
    if (opts.form) init.body = opts.form;
    else if (opts.body) { init.body = JSON.stringify(opts.body); init.headers['Content-Type'] = 'application/json'; }
    return fetch(A.api + path, init).then(function (r) {
      return r.json().catch(function () { throw new Error('Сервер ответил ошибкой (' + r.status + ')'); });
    }).then(function (j) {
      if (j.relogin) { location.href = A.prefix + '/login'; throw new Error(j.error); }
      if (!j.ok) throw new Error(j.error || 'Ошибка');
      return j;
    });
  }

  function toast(msg, type, link) {
    var t = h('div', { class: 'toast' + (type === 'err' ? ' toast--err' : '') }, msg, link ? [' ', h('a', { href: link.href, target: '_blank', rel: 'noopener', text: link.text })] : null);
    $('#toasts').appendChild(t);
    setTimeout(function () { t.remove(); }, type === 'err' ? 12000 : 4500);
  }

  function setDirty(v) {
    dirty = v;
    var bar = $('.savebar');
    if (bar) {
      bar.classList.toggle('is-dirty', v);
      $('.savebar__msg', bar).textContent = v ? 'Есть несохранённые изменения' : 'Все изменения сохранены';
    }
  }
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  function setTitle(t, actions) {
    $('#pageTitle').textContent = t;
    document.title = t + ' — управление сайтом';
    var a = $('#topActions'); a.innerHTML = '';
    add(a, actions || []);
  }
  function view() { return $('#view'); }
  function loading() { view().innerHTML = ''; view().appendChild(h('p', { class: 'loading', text: 'Загрузка…' })); }
  function openSiteBtn(path, label) { return path ? h('a', { class: 'btn btn--ghost btn--sm', href: path, target: '_blank', rel: 'noopener' }, (label || 'Открыть на сайте') + ' ↗') : null; }

  // ---------- модальные окна ----------
  function modal(title, body, foot, small) {
    var box = h('div', { class: 'modal__box' + (small ? ' modal__box--sm' : '') },
      h('div', { class: 'modal__head' }, h('h3', { text: title }), h('button', { class: 'ibtn', type: 'button', 'aria-label': 'Закрыть', onclick: function () { close(); } }, '✕')),
      h('div', { class: 'modal__body' }, body),
      foot ? h('div', { class: 'modal__foot' }, foot) : null);
    var wrap = h('div', { class: 'modal', onclick: function (e) { if (e.target === wrap) close(); } }, box);
    function onKey(e) { if (e.key === 'Escape') close(); }
    function close() { wrap.remove(); document.removeEventListener('keydown', onKey); }
    document.addEventListener('keydown', onKey);
    document.body.appendChild(wrap);
    return { close: close, box: box };
  }
  function confirmBox(title, text, okLabel, danger) {
    return new Promise(function (resolve) {
      var m;
      var ok = h('button', { class: 'btn ' + (danger ? 'btn--danger' : 'btn--primary'), type: 'button', text: okLabel || 'Да', onclick: function () { m.close(); resolve(true); } });
      m = modal(title, h('p', { text: text }), [h('button', { class: 'btn', type: 'button', text: 'Отмена', onclick: function () { m.close(); resolve(false); } }), ok], true);
      ok.focus();
    });
  }

  // ---------- загрузка фото ----------
  // Крупное фото с телефона уменьшаем в браузере (поворот по EXIF браузер учитывает сам):
  // так загрузка быстрее и не упирается в лимит размера файла на хостинге.
  function shrink(file) {
    if (file.size < 1.5 * 1024 * 1024 || !window.createImageBitmap) return Promise.resolve(file);
    var steps = [[2560, 0.9], [2048, 0.85], [1600, 0.8]];
    return createImageBitmap(file, { imageOrientation: 'from-image' }).then(function (bmp) {
      function attempt(i) {
        var k = Math.min(1, steps[i][0] / Math.max(bmp.width, bmp.height));
        var c = document.createElement('canvas');
        c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        return new Promise(function (res) { c.toBlob(res, 'image/jpeg', steps[i][1]); }).then(function (b) {
          if (b && b.size > 1.8 * 1024 * 1024 && i < steps.length - 1) return attempt(i + 1);
          return b && b.size < file.size ? b : file;
        });
      }
      return attempt(0);
    }).catch(function () { return file; });
  }
  function fileInput(multiple, onFiles) {
    var inp = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: multiple, hidden: true });
    inp.addEventListener('change', function () { if (inp.files.length) onFiles(inp.files); inp.value = ''; });
    return inp;
  }
  // Последовательная загрузка: path — адрес API, extra — поля формы. resolve(массив ответов)
  function uploadEach(files, path, extra) {
    var list = Array.prototype.filter.call(files, function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
    if (!list.length) { toast('Выберите фото в формате JPG, PNG или WebP', 'err'); return Promise.resolve([]); }
    var out = [];
    toast('Загружаем фото: ' + list.length + ' шт. Не закрывайте страницу…');
    return list.reduce(function (p, f) {
      return p.then(function () { return shrink(f); }).then(function (f2) {
        var fd = new FormData(); fd.append('file', f2, f.name);
        Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
        return api(path, { form: fd }).then(function (j) { out.push(j); }).catch(function (e) { toast(f.name + ': ' + e.message, 'err'); });
      });
    }, Promise.resolve()).then(function () { return out; });
  }

  // =====================================================================
  // ПОЛЯ ФОРМЫ. Каждое поле: { el, get() }
  // =====================================================================
  function wrapFld(fd, control, extra) {
    return h('div', { class: 'fld' },
      fd.label ? h('span', { class: 'fld__label' }, fd.label, fd.required ? h('i', { class: 'req', text: ' *' }) : null, extra || null) : null,
      control,
      fd.hint ? h('span', { class: 'fld__hint', text: fd.hint }) : null);
  }
  function autosize(t) { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; }

  function textControl(fd, value, onChange) {
    var multi = fd.type === 'textarea';
    var el = multi ? h('textarea', { class: 'inp', rows: fd.rows || 3 }) : h('input', { type: 'text', class: 'inp' });
    el.value = value == null ? '' : value;
    var cnt = fd.counter ? h('span', { class: 'fld__count' }) : null;
    function upd() { if (cnt) { cnt.textContent = el.value.length + ' / ' + fd.counter; cnt.classList.toggle('is-over', el.value.length > fd.counter); } if (multi) autosize(el); }
    el.addEventListener('input', function () { upd(); onChange(); });
    setTimeout(upd, 0);
    return { el: el, cnt: cnt, get: function () { return el.value; } };
  }

  var F = {};
  F.head = function (fd) { return { el: h('div', { class: 'form-head' }, h('h2', { text: fd.label }), fd.hint ? h('p', { text: fd.hint }) : null) }; };
  F.text = F.textarea = F.url = F.email = function (fd, v, ch) { var c = textControl(fd, v, ch); return { el: wrapFld(fd, c.el, c.cnt), get: c.get }; };
  F.number = function (fd, v, ch) {
    var el = h('input', { type: 'number', class: 'inp', style: 'max-width:180px', min: fd.min != null ? fd.min : null }); el.value = v == null ? '' : v;
    el.addEventListener('input', ch);
    return { el: wrapFld(fd, el), get: function () { return el.value === '' ? null : Number(el.value); } };
  };
  F.float = function (fd, v, ch) {
    var el = h('input', { type: 'text', inputmode: 'decimal', class: 'inp', style: 'max-width:180px' }); el.value = v == null ? '' : v;
    el.addEventListener('input', ch);
    return { el: wrapFld(fd, el), get: function () { return el.value.replace(',', '.').trim(); } };
  };
  F.bool = function (fd, v, ch) {
    var cb = h('input', { type: 'checkbox' }); cb.checked = !!v; cb.addEventListener('change', ch);
    return { el: h('div', { class: 'fld' }, h('label', { class: 'check' }, cb, h('span', {}, h('b', { text: fd.label }), fd.hint ? h('span', { class: 'fld__hint', text: fd.hint }) : null))), get: function () { return cb.checked; } };
  };
  F.catsel = function (fd, v, ch) {
    var s = h('select', { class: 'sel' }, BOOT.cats.map(function (c) { return h('option', { value: c.id, text: c.name }); }));
    s.value = v || ''; s.addEventListener('change', ch);
    return { el: wrapFld(fd, s), get: function () { return s.value; } };
  };

  // Список строк с кнопками «вверх/вниз/удалить»
  F.list = function (fd, v, ch) {
    var box = h('div', { class: 'lst' });
    var rows = [];
    function row(val) {
      var c = textControl({ type: fd.multiline ? 'textarea' : 'text', rows: 2 }, val, ch);
      var r = { el: null, c: c };
      r.el = h('div', { class: 'lst__row' }, c.el, h('div', { class: 'lst__tools' },
        h('button', { class: 'ibtn', type: 'button', title: 'Выше', onclick: function () { move(r, -1); } }, '↑'),
        h('button', { class: 'ibtn', type: 'button', title: 'Ниже', onclick: function () { move(r, 1); } }, '↓'),
        h('button', { class: 'ibtn ibtn--del', type: 'button', title: 'Удалить', onclick: function () { rows.splice(rows.indexOf(r), 1); draw(); ch(); } }, '✕')));
      return r;
    }
    function move(r, d) { var i = rows.indexOf(r), j = i + d; if (j < 0 || j >= rows.length) return; rows.splice(i, 1); rows.splice(j, 0, r); draw(); ch(); }
    function draw() { box.innerHTML = ''; rows.forEach(function (r) { box.appendChild(r.el); }); box.appendChild(addBtn); }
    var addBtn = h('button', { class: 'add', type: 'button', text: '+ Добавить пункт', onclick: function () { var r = row(''); rows.push(r); draw(); r.c.el.focus(); ch(); } });
    (v || []).forEach(function (x) { rows.push(row(x)); });
    draw();
    return { el: wrapFld(fd, box), get: function () { return rows.map(function (r) { return r.c.get(); }); } };
  };

  // Список карточек с вложенными полями (вопросы, характеристики, услуги…). fixed — без добавления и удаления
  F.items = function (fd, v, ch) {
    var box = h('div', { class: 'items' });
    var list = [];
    function summary(val) {
      for (var i = 0; i < fd.item.length; i++) { var s = fd.item[i]; if ((s.type === 'text' || s.type === 'textarea') && val[s.key]) return String(val[s.key]); }
      return '';
    }
    function card(val, open) {
      var subs = fd.item.map(function (s) { return { key: s.key, f: field(s, val[s.key], function () { titleUpd(); ch(); }) }; });
      var title = h('div', { class: 'item__title item__toggle' });
      var it = { subs: subs };
      function titleUpd() { var cur = it.get(); var i = list.indexOf(it) + 1; title.textContent = ''; add(title, [(fd.itemName || 'Элемент') + ' ' + i, h('small', { text: summary(cur).slice(0, 80) })]); }
      it.get = function () { var o = clone(val); subs.forEach(function (x) { o[x.key] = x.f.get(); }); return o; }; // поля вне формы сохраняются
      it.el = h('div', { class: 'item' + (fd.collapsed && !open ? ' is-collapsed' : '') },
        h('div', { class: 'item__head' }, title,
          fd.fixed ? null : [
            h('button', { class: 'ibtn', type: 'button', title: 'Выше', onclick: function () { move(it, -1); } }, '↑'),
            h('button', { class: 'ibtn', type: 'button', title: 'Ниже', onclick: function () { move(it, 1); } }, '↓'),
            h('button', { class: 'ibtn ibtn--del', type: 'button', title: 'Удалить', onclick: function () {
              confirmBox('Удалить?', 'Удалить «' + ((fd.itemName || 'элемент') + ' ' + (list.indexOf(it) + 1)) + '»? Изменение вступит в силу после сохранения.', 'Удалить', true).then(function (ok) { if (ok) { list.splice(list.indexOf(it), 1); draw(); ch(); } });
            } }, '✕')]),
        h('div', { class: 'item__body' }, subs.map(function (x) { return x.f.el; })));
      title.addEventListener('click', function () { it.el.classList.toggle('is-collapsed'); });
      it.titleUpd = titleUpd;
      return it;
    }
    function move(it, d) { var i = list.indexOf(it), j = i + d; if (j < 0 || j >= list.length) return; list.splice(i, 1); list.splice(j, 0, it); draw(); ch(); }
    function draw() { box.innerHTML = ''; list.forEach(function (it) { box.appendChild(it.el); it.titleUpd(); }); if (!fd.fixed) box.appendChild(addBtn); }
    var addBtn = h('button', { class: 'add', type: 'button', text: '+ Добавить: ' + (fd.itemName || 'элемент').toLowerCase(), onclick: function () {
      var blank = {}; fd.item.forEach(function (s) { blank[s.key] = s.type === 'images' ? [] : s.type === 'bool' ? false : ''; });
      var it = card(blank, true); list.push(it); draw(); ch();
      var f = it.el.querySelector('input,textarea'); if (f) f.focus();
    } });
    (v || []).forEach(function (x) { list.push(card(x, false)); });
    draw();
    return { el: wrapFld(fd, box), get: function () { return list.map(function (it) { return it.get(); }); } };
  };

  // Картинка услуги или первого экрана: путь /img/... Загрузка создаёт крупное фото и превью, в поле — нужное из двух
  F.image = function (fd, v, ch) {
    var cur = v || '';
    var img = h('img', { class: 'thumb', alt: '' });
    var cap = h('span', { class: 'fld__hint' });
    function upd() { img.src = cur; img.style.visibility = cur ? '' : 'hidden'; cap.textContent = cur ? cur.split('/').pop() : 'Фото не выбрано'; }
    upd();
    var inp = fileInput(false, function (files) {
      uploadEach(files, 'image/upload').then(function (r) { if (r[0]) { cur = fd.small ? r[0].small : r[0].full; upd(); ch(); toast(r[0].message); } });
    });
    var box = h('div', { class: 'pick' }, img, h('div', {}, h('button', { class: 'btn btn--sm', type: 'button', text: cur ? 'Заменить фото' : 'Загрузить фото', onclick: function () { inp.click(); } }), inp, h('br'), cap));
    return { el: wrapFld(fd, box), get: function () { return cur; } };
  };

  // Несколько картинок (дополнительные фото на странице услуги): храним крупные
  F.images = function (fd, v, ch) {
    var list = (v || []).slice();
    var box = h('div');
    var inp = fileInput(true, function (files) { uploadEach(files, 'image/upload').then(function (r) { r.forEach(function (x) { list.push(x.full); }); if (r.length) { draw(); ch(); } }); });
    function thumb(u) { return u.replace(/\.(jpg|webp)$/, '-s.$1'); }
    function draw() {
      box.innerHTML = '';
      add(box, [list.length ? h('div', { class: 'gal' }, list.map(function (u, i) {
        return h('div', { class: 'gal__it' }, h('img', { class: 'thumb', src: thumb(u), alt: '', loading: 'lazy', onerror: function () { this.onerror = null; this.src = u; } }),
          h('div', { class: 'gal__tools' },
            h('button', { class: 'ibtn', type: 'button', title: 'Раньше', disabled: i === 0, onclick: function () { list.splice(i - 1, 0, list.splice(i, 1)[0]); draw(); ch(); } }, '←'),
            h('button', { class: 'ibtn', type: 'button', title: 'Позже', disabled: i === list.length - 1, onclick: function () { list.splice(i + 1, 0, list.splice(i, 1)[0]); draw(); ch(); } }, '→'),
            h('button', { class: 'ibtn ibtn--del', type: 'button', title: 'Убрать', onclick: function () { list.splice(i, 1); draw(); ch(); } }, '✕')));
      })) : null, h('button', { class: 'add', type: 'button', text: '+ Загрузить фото', onclick: function () { inp.click(); } }), inp]);
    }
    draw();
    return { el: wrapFld(fd, box), get: function () { return list.slice(); } };
  };

  // Фото товара: номера файлов /img/products/<артикул>/N.webp, порядок = порядок на странице товара
  F.prodphotos = function (fd, v, ch) {
    var list = (v || []).slice();
    var box = h('div');
    var inp = fileInput(true, function (files) {
      uploadEach(files, 'product/photo', { sku: fd.sku }).then(function (r) {
        r.forEach(function (x) { list.push(x.n); });
        if (r.length) { draw(); ch(); toast('Фото загружено: ' + r.length + '. Нажмите «Сохранить», чтобы они появились на сайте.'); }
      });
    });
    function draw() {
      box.innerHTML = '';
      add(box, [list.length ? h('div', { class: 'gal gal--prod' }, list.map(function (n, i) {
        return h('div', { class: 'gal__it' + (i === 0 ? ' is-main' : '') },
          h('a', { href: prodImg(fd.sku, n), target: '_blank', rel: 'noopener', title: 'Открыть крупно' }, h('img', { class: 'thumb thumb--34', src: prodImg(fd.sku, n, true) + '?' + n, alt: '', loading: 'lazy' })),
          i === 0 ? h('span', { class: 'gal__main', text: 'главное' }) : null,
          h('div', { class: 'gal__tools' },
            h('button', { class: 'ibtn', type: 'button', title: 'Раньше', disabled: i === 0, onclick: function () { list.splice(i - 1, 0, list.splice(i, 1)[0]); draw(); ch(); } }, '←'),
            h('button', { class: 'ibtn', type: 'button', title: 'Позже', disabled: i === list.length - 1, onclick: function () { list.splice(i + 1, 0, list.splice(i, 1)[0]); draw(); ch(); } }, '→'),
            h('button', { class: 'ibtn ibtn--del', type: 'button', title: 'Убрать с сайта', onclick: function () { list.splice(i, 1); draw(); ch(); } }, '✕')));
      })) : h('div', { class: 'empty', text: 'Фото нет — товар не показывается в каталоге' }),
      h('button', { class: 'add', type: 'button', text: '+ Загрузить фото', onclick: function () { inp.click(); } }), inp]);
    }
    draw();
    return { el: wrapFld(fd, box), get: function () { return list.slice(); } };
  };

  function field(fd, value, onChange) { return (F[fd.type] || F.text)(fd, value, onChange); }

  // Форма раздела: поля + нижняя панель «Сохранить»
  function buildForm(fields, values, onSave) {
    var ctl = [];
    var form = h('div', { class: 'form' });
    fields.forEach(function (fd) {
      var f = field(fd, fd.bind ? values[fd.bind] : null, function () { setDirty(true); });
      if (fd.bind) ctl.push({ bind: fd.bind, f: f });
      form.appendChild(f.el);
    });
    var btn = h('button', { class: 'btn btn--primary', type: 'button', text: 'Сохранить' });
    var bar = h('div', { class: 'savebar' }, h('span', { class: 'savebar__msg', text: 'Все изменения сохранены' }), btn);
    function collect() { var o = {}; ctl.forEach(function (c) { o[c.bind] = c.f.get(); }); return o; }
    function save() {
      if (saving) return;
      saving = true; btn.disabled = true; btn.textContent = 'Сохраняем…';
      onSave(collect()).then(function () { setDirty(false); }).catch(function (e) { toast(e.message, 'err'); })
        .then(function () { saving = false; btn.disabled = false; btn.textContent = 'Сохранить'; });
    }
    btn.addEventListener('click', save);
    formCtl = { save: save };
    return h('div', {}, form, bar);
  }
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'ы')) { if (formCtl && $('.savebar')) { e.preventDefault(); formCtl.save(); } }
  });

  // =====================================================================
  // ЭКРАНЫ
  // =====================================================================
  var routes = {};

  routes[''] = function () {
    setTitle('Управление сайтом');
    var tile = function (href, ic, title, text) { return h('a', { class: 'tile', href: href }, icon(ic), h('b', { text: title }), h('span', { text: text })); };
    view().appendChild(h('div', {},
      h('p', { class: 'lead-p', text: 'Выберите, что хотите изменить. После нажатия «Сохранить» сайт обновляется сразу.' }),
      h('div', { class: 'tiles' },
        tile('#/products', 'box', 'Товары каталога', 'Цены, названия, фото клейм. Добавить или скрыть товар'),
        tile('#/leads', 'mail', 'Заявки', BOOT.leads ? 'Всего заявок: ' + BOOT.leads : 'Заявки с форм сайта'),
        tile('#/section/company', 'phone', 'Компания и контакты', 'Телефон, почта, MAX, адрес — меняются на всём сайте'),
        tile('#/section/matrix', 'layers', 'Матрицы и пуансоны', 'Цена, срок, работы с фото'),
        tile('#/section/laser', 'laser', 'Лазерная гравировка', 'Услуги, цены, фото'),
        tile('#/section/press', 'press', 'Пресс', 'Описание и характеристики')),
      h('h2', { class: 'sec', text: 'Ещё' }),
      h('div', { class: 'tiles' },
        tile('#/section/categories', 'tag', 'Категории каталога', 'Названия и описания категорий'),
        tile('#/section/faq', 'help', 'Вопросы и ответы', 'Блок «Частые вопросы» на главной'),
        tile('#/settings', 'gear', 'Настройки', 'Куда приходят заявки, пароль')),
      h('h2', { class: 'sec', text: 'Если что-то пошло не так' }),
      h('div', { class: 'tiles' },
        tile('#/history', 'clock', 'История правок', 'Вернуть любую прежнюю версию'),
        tile('#/trash', 'trash', 'Корзина', 'Удалённые товары можно вернуть'))));
  };

  routes.section = function (id) {
    return api('section?id=' + encodeURIComponent(id)).then(function (d) {
      setTitle(d.title, [openSiteBtn(d.url)]);
      view().innerHTML = '';
      view().appendChild(buildForm(d.fields, d.values, function (values) {
        return api('section', { body: { id: id, values: values } }).then(function (j) {
          toast(j.message, null, { href: d.url, text: 'Посмотреть ↗' });
          return refreshBoot().then(function () { rerender(); });
        });
      }));
    });
  };

  routes.products = function () {
    return api('products').then(function (d) {
      setTitle('Товары каталога', [openSiteBtn('/catalog/', 'Каталог на сайте'), h('button', { class: 'btn btn--primary btn--sm', type: 'button', text: '+ Добавить товар', onclick: createProduct })]);
      var list = d.products, q = '';
      var rows = h('div', { class: 'rows' });
      var search = h('input', { type: 'search', class: 'inp', placeholder: 'Найти: название или артикул', style: 'max-width:420px;margin-bottom:12px' });
      search.addEventListener('input', function () { q = search.value.trim().toLowerCase(); draw(); });
      function reorder(i, dir) {
        var j = i + dir; if (j < 0 || j >= list.length) return;
        list.splice(j, 0, list.splice(i, 1)[0]); draw();
        api('products/order', { body: { order: list.map(function (s) { return s.sku; }) } }).then(function (j2) { toast(j2.message); }).catch(function (e) { toast(e.message, 'err'); });
      }
      function draw() {
        rows.innerHTML = '';
        list.forEach(function (p, i) {
          if (q && (p.title + ' ' + p.short + ' ' + p.sku).toLowerCase().indexOf(q) < 0) return;
          rows.appendChild(h('div', { class: 'row' + (p.hidden || !p.photo ? ' row--off' : '') },
            p.photo ? h('img', { class: 'row__img', src: prodImg(p.sku, p.photo, true), alt: '', loading: 'lazy' }) : h('span', { class: 'row__img row__img--none', text: 'нет фото' }),
            h('div', { class: 'row__main' }, h('a', { href: '#/product/' + p.sku, text: p.short }),
              p.hidden ? h('span', { class: 'tag tag--warn', text: 'скрыт' }) : null,
              !p.photo ? h('span', { class: 'tag tag--warn', text: 'нет фото' }) : null,
              p.press ? h('span', { class: 'tag', text: 'фото пресса' }) : null,
              h('p', { text: p.cat + ' · ' + rub(p.price) + ' · арт. ' + p.sku })),
            h('div', { class: 'row__tools' },
              q ? null : h('button', { class: 'ibtn', type: 'button', title: 'Выше в каталоге', disabled: i === 0, onclick: function () { reorder(i, -1); } }, '↑'),
              q ? null : h('button', { class: 'ibtn', type: 'button', title: 'Ниже в каталоге', disabled: i === list.length - 1, onclick: function () { reorder(i, 1); } }, '↓'),
              h('a', { class: 'btn btn--sm', href: '#/product/' + p.sku, text: 'Изменить' }))));
        });
      }
      draw();
      view().innerHTML = '';
      view().appendChild(h('div', {}, h('p', { class: 'lead-p', text: 'Порядок здесь — порядок в каталоге и «по популярности». Стрелки меняют его сразу. Товар без фото на сайте не показывается.' }), search, rows));
    });
  };

  function createProduct() {
    var m;
    var name = h('input', { type: 'text', class: 'inp', placeholder: 'Например: ОТК 12' });
    var cat = h('select', { class: 'sel' }, BOOT.cats.map(function (c) { return h('option', { value: c.id, text: c.name }); }));
    var go = function () {
      if (!name.value.trim()) return name.focus();
      api('product/create', { body: { short: name.value.trim(), category: cat.value } }).then(function (j) { m.close(); toast(j.message); return refreshBoot().then(function () { location.hash = '#/product/' + j.sku; }); }).catch(function (e) { toast(e.message, 'err'); });
    };
    m = modal('Новый товар', h('div', {},
      h('label', { class: 'fld' }, h('span', { class: 'fld__label', text: 'Короткое название' }), name, h('span', { class: 'fld__hint', text: 'Как в карточке каталога. Остальное заполните на следующем шаге.' })),
      h('label', { class: 'fld' }, h('span', { class: 'fld__label', text: 'Категория' }), cat)),
      [h('button', { class: 'btn', type: 'button', text: 'Отмена', onclick: function () { m.close(); } }), h('button', { class: 'btn btn--primary', type: 'button', text: 'Создать', onclick: go })], true);
    name.focus();
  }

  routes.product = function (sku) {
    return api('product?sku=' + encodeURIComponent(sku)).then(function (d) {
      setTitle('Товар: ' + d.title, [
        d.visible ? openSiteBtn(d.url) : null,
        d.press ? null : h('button', { class: 'btn btn--sm btn--danger', type: 'button', text: 'Удалить', onclick: function () {
          confirmBox('Удалить товар?', '«' + d.title + '» уберётся с сайта вместе с фото. Его можно будет вернуть из корзины. Если нужно убрать товар на время — лучше отметьте «Скрыть с сайта».', 'Удалить', true).then(function (ok) {
            if (!ok) return;
            api('product/delete', { body: { sku: sku } }).then(function (j) { toast(j.message); setDirty(false); return refreshBoot().then(function () { location.hash = '#/products'; }); }).catch(function (e) { toast(e.message, 'err'); });
          });
        } })]);
      view().innerHTML = '';
      if (d.press) view().appendChild(h('p', { class: 'alert alert--info', text: 'Фото этого товара показываются на странице «Прессы» (первое — на первом экране, четвёртое — у блока «Управление»).' }));
      else if (!d.visible) view().appendChild(h('p', { class: 'alert alert--info', text: 'Товара сейчас нет на сайте. Чтобы он появился, нужно хотя бы одно фото и снятая галочка «Скрыть с сайта».' }));
      view().appendChild(buildForm(d.fields, d.values, function (values) {
        return api('product', { body: { sku: sku, values: values } }).then(function (j) {
          toast(j.message, null, { href: d.url, text: 'Посмотреть ↗' });
          return refreshBoot().then(function () { rerender(); });
        });
      }));
    });
  };

  routes.leads = function () {
    return api('leads').then(function (d) {
      setTitle('Заявки');
      view().innerHTML = '';
      if (!d.items.length) return view().appendChild(h('div', { class: 'empty', text: 'Заявок пока нет. Они появятся здесь, как только кто-то отправит форму на сайте.' }));
      var SRC = { modal: 'окно «Заказать»', contacts: 'форма на главной', press: 'страница пресса', laser: 'страница гравировки', matrix: 'страница матриц' };
      view().appendChild(h('div', {}, h('p', { class: 'lead-p', text: 'Все заявки с форм сайта, новые сверху. Куда ещё отправлять заявки — в разделе «Настройки».' }),
        h('div', { class: 'tbl-wrap' }, h('table', { class: 'tbl' },
          h('thead', {}, h('tr', {}, ['Когда', 'Имя и телефон', 'Что нужно', 'Откуда'].map(function (t) { return h('th', { text: t }); }))),
          h('tbody', {}, d.items.map(function (l) {
            return h('tr', {},
              h('td', { text: l.date, style: 'white-space:nowrap' }),
              h('td', {}, h('b', { text: l.name || '—' }), h('br'), h('a', { href: 'tel:' + String(l.phone).replace(/[^\d+]/g, ''), text: l.phone })),
              h('td', {}, l.product ? h('div', { text: l.product }) : null, l.config ? h('div', { text: 'Схема: ' + l.config, style: 'font-size:13px' }) : null,
                l.message ? h('div', { text: l.message, style: 'color:#6b7078;white-space:pre-wrap' }) : null,
                l.file ? h('a', { href: A.prefix + '/lead-file?name=' + encodeURIComponent(l.file), text: '📎 ' + l.file.replace(/^[0-9a-f]+_/, '') }) : null),
              h('td', { style: 'font-size:13px;color:#6b7078' }, SRC[l.source] || l.source || ''));
          }))))));
    });
  };

  routes.history = function () {
    return api('revisions').then(function (d) {
      setTitle('История правок');
      view().innerHTML = '';
      if (!d.items.length) return view().appendChild(h('div', { class: 'empty', text: 'Правок пока не было.' }));
      view().appendChild(h('div', {}, h('p', { class: 'lead-p', text: 'При каждом сохранении прежняя версия откладывается сюда. «Вернуть» восстанавливает раздел таким, каким он был перед этой правкой. Текущая версия при этом тоже сохранится в истории.' }),
        h('div', { class: 'tbl-wrap' }, h('table', { class: 'tbl' },
          h('thead', {}, h('tr', {}, h('th', { text: 'Когда сохранили' }), h('th', { text: 'Что' }), h('th'))),
          h('tbody', {}, d.items.map(function (it) {
            return h('tr', {}, h('td', { text: it.date, style: 'white-space:nowrap' }), h('td', { text: it.label }), h('td', { style: 'text-align:right' }, h('button', { class: 'btn btn--sm', type: 'button', text: 'Вернуть как было до этого', onclick: function () {
              confirmBox('Вернуть прежнюю версию?', '«' + it.label + '» станет таким, каким было перед сохранением ' + it.date + '. Сайт обновится сразу.', 'Вернуть').then(function (ok) {
                if (ok) api('revision/restore', { body: { id: it.id } }).then(function (j) { toast(j.message); refreshBoot().then(rerender); }).catch(function (e) { toast(e.message, 'err'); });
              });
            } })));
          }))))));
    });
  };

  routes.trash = function () {
    return api('trash').then(function (d) {
      setTitle('Корзина');
      view().innerHTML = '';
      if (!d.items.length) return view().appendChild(h('div', { class: 'empty', text: 'Корзина пуста.' }));
      view().appendChild(h('div', {}, h('p', { class: 'lead-p', text: 'Удалённые товары хранятся здесь без срока вместе с фото — их можно вернуть в любой момент.' }),
        h('div', { class: 'rows' }, d.items.map(function (it) {
          return h('div', { class: 'row' }, h('span', { class: 'row__ic' }, icon('box')),
            h('div', { class: 'row__main' }, h('b', { text: 'Товар: ' + it.title }), h('p', { text: 'Арт. ' + it.ref + ' · удалён ' + it.date.replace('T', ' ').slice(0, 16) })),
            h('div', { class: 'row__tools' }, h('button', { class: 'btn btn--sm', type: 'button', text: 'Вернуть', onclick: function () {
              api('trash/restore', { body: { id: it.id } }).then(function (j) { toast(j.message); refreshBoot().then(rerender); }).catch(function (e) { toast(e.message, 'err'); });
            } })));
        }))));
    });
  };

  routes.settings = function () {
    return api('settings').then(function (d) {
      setTitle('Настройки');
      var s = d.settings;
      var emails = F.list({ label: 'Почта для заявок', hint: 'Пусто — заявки уходят на почту из раздела «Компания и контакты»: ' + d.companyEmail }, s.leadEmails, function () {});
      var token = textControl({ type: 'text' }, s.tgToken, function () {});
      var chats = F.list({ label: 'Кому в Telegram (Chat ID)', hint: 'Число: личный чат или группа. Узнать свой ID можно у бота @userinfobot. Боту нужно сначала написать /start.' }, s.tgChats, function () {});
      var from = textControl({ type: 'text' }, s.mailFrom, function () {});
      var cur = h('input', { type: 'password', class: 'inp', autocomplete: 'current-password' });
      var nw = h('input', { type: 'password', class: 'inp', autocomplete: 'new-password' });
      view().innerHTML = '';
      add(view(), [
        h('div', { class: 'form' },
          h('div', { class: 'form-head' }, h('h2', { text: 'Куда приходят заявки' }), h('p', { text: 'Каждая заявка с сайта сохраняется в разделе «Заявки» (вместе с прикреплённым файлом) и дополнительно отправляется сюда.' })),
          emails.el,
          wrapFld({ label: 'Токен Telegram-бота', hint: 'Необязательно. Создайте бота у @BotFather и вставьте токен. Пусто — в Telegram не отправлять.' }, token.el),
          chats.el,
          wrapFld({ label: 'Адрес отправителя писем', hint: 'Необязательно. По умолчанию no-reply@ домена сайта.' }, from.el),
          h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px' },
            h('button', { class: 'btn btn--primary', type: 'button', text: 'Сохранить', onclick: function () {
              api('settings', { body: { leadEmails: emails.get(), tgToken: token.get(), tgChats: chats.get(), mailFrom: from.get() } }).then(function (j) { toast(j.message); }).catch(function (e) { toast(e.message, 'err'); });
            } }),
            h('button', { class: 'btn', type: 'button', text: 'Отправить проверочную заявку', onclick: function () {
              api('settings/test-lead', { body: {} }).then(function (j) {
                var r = j.result || {}, parts = [];
                if ('mail' in r) parts.push('почта: ' + (r.mail ? 'отправлено' : 'не отправилось'));
                if (r.tg) Object.keys(r.tg).forEach(function (k) { parts.push('Telegram ' + k + ': ' + (r.tg[k] ? 'доставлено' : 'ошибка')); });
                toast(parts.length ? parts.join(', ') : 'Некуда отправлять: не указаны почта и Telegram', parts.join().indexOf('ошибка') >= 0 || parts.join().indexOf('не отправ') >= 0 ? 'err' : null);
              }).catch(function (e) { toast(e.message, 'err'); });
            } }))),
        h('div', { class: 'form', style: 'margin-top:18px' },
          h('div', { class: 'form-head' }, h('h2', { text: 'Вход в админку' }), h('p', {}, 'Адрес входа: ', h('code', { text: d.secretUrl }), '. Сохраните его в закладки — без него админка не открывается.')),
          wrapFld({ label: 'Текущий пароль' }, cur),
          wrapFld({ label: 'Новый пароль', hint: 'Не короче 8 символов' }, nw),
          h('div', { style: 'margin-bottom:18px' }, h('button', { class: 'btn', type: 'button', text: 'Сменить пароль', onclick: function () {
            api('settings/password', { body: { current: cur.value, password: nw.value } }).then(function (j) { toast(j.message); cur.value = nw.value = ''; }).catch(function (e) { toast(e.message, 'err'); });
          } })))]);
    });
  };

  // ---------- навигация ----------
  function drawNav(active) {
    var nav = $('#nav'); nav.innerHTML = '';
    var groups = {};
    var link = function (href, title, badge) { return h('a', { href: href, class: href === active ? 'is-active' : '' }, h('span', { text: title }), badge ? h('span', { class: 'badge', text: String(badge) }) : null); };
    nav.appendChild(link('#/', 'Обзор'));
    nav.appendChild(h('div', { class: 'side__group', text: 'Каталог' }));
    nav.appendChild(link('#/products', 'Товары (' + BOOT.products + ')'));
    BOOT.nav.forEach(function (n) { (groups[n.group] = groups[n.group] || []).push(n); });
    (groups['Каталог'] || []).forEach(function (n) { nav.appendChild(link('#/section/' + n.id, n.title)); });
    delete groups['Каталог'];
    Object.keys(groups).forEach(function (g) {
      nav.appendChild(h('div', { class: 'side__group', text: g }));
      groups[g].forEach(function (n) { nav.appendChild(link('#/section/' + n.id, n.title)); });
    });
    nav.appendChild(h('div', { class: 'side__group', text: 'Служебное' }));
    nav.appendChild(link('#/leads', 'Заявки', BOOT.leads));
    nav.appendChild(link('#/history', 'История правок'));
    nav.appendChild(link('#/trash', 'Корзина', BOOT.trash));
    nav.appendChild(link('#/settings', 'Настройки'));
  }

  function refreshBoot() { return api('bootstrap').then(function (b) { BOOT = b; drawNav(currentNavHref()); }); }
  function currentNavHref() {
    var p = location.hash.replace(/^#\/?/, '').split('/');
    if (p[0] === 'product') return '#/products';
    return '#/' + p.filter(Boolean).join('/');
  }

  var lastHash = location.hash;
  function rerender() {
    var y = window.scrollY;
    render().then(function () { window.scrollTo(0, y); });
  }
  function render() {
    formCtl = null;
    var p = location.hash.replace(/^#\/?/, '').split('/').map(decodeURIComponent);
    var fn = routes[p[0]] || routes[''];
    drawNav(currentNavHref());
    $('#side').classList.remove('is-open');
    loading();
    setDirty(false);
    return Promise.resolve().then(function () { view().innerHTML = ''; return fn(p[1]); }).catch(function (e) {
      view().innerHTML = ''; view().appendChild(h('p', { class: 'alert alert--err', text: e.message }));
    });
  }
  window.addEventListener('hashchange', function () {
    if (skipHash) { skipHash = false; lastHash = location.hash; return; }
    if (dirty && !window.confirm('Есть несохранённые изменения. Уйти без сохранения?')) { skipHash = true; location.hash = lastHash; return; }
    lastHash = location.hash;
    window.scrollTo(0, 0);
    render();
  });
  $('#menuBtn').addEventListener('click', function () { $('#side').classList.toggle('is-open'); });

  refreshBoot().then(render).catch(function (e) { view().appendChild(h('p', { class: 'alert alert--err', text: e.message })); });
})();
