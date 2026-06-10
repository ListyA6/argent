/* ============================================================
   ARGENT — app logic
   ============================================================ */

(function () {
  'use strict';

  const A = window.ARGENT;
  const $ = (s, el) => (el || document).querySelector(s);
  const $$ = (s, el) => [...(el || document).querySelectorAll(s)];
  const fmt = (n) => new Intl.NumberFormat('id-ID').format(n);
  const vibrate = (p) => { try { navigator.vibrate && navigator.vibrate(p); } catch (e) {} };
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const cats = A.categories || [];
  const catById = Object.fromEntries(cats.map((c) => [c.id, c]));

  /* ---------- icons ---------- */

  const ICONS = {
    bowl: '<path d="M4 11h16a8 8 0 0 1-16 0z"/><path d="M9 11V7a3 3 0 0 1 6 0"/>',
    glass: '<path d="M5 4h14l-7 8z"/><path d="M12 12v7M8 21h8"/>',
    egg: '<path d="M12 3c3.5 0 6.5 5.2 6.5 10a6.5 6.5 0 0 1-13 0C5.5 8.2 8.5 3 12 3z"/>',
    fuel: '<path d="M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16"/><path d="M3 21h14"/><path d="M15 9h2a2 2 0 0 1 2 2v5a1.5 1.5 0 0 0 3 0V9l-2.5-2.5"/>',
    bolt: '<path d="M13 2 4.5 13.5H11L9.5 22 19 10h-6.5z"/>',
    gamepad: '<path d="M7 8h10a4 4 0 0 1 4 4v2a3 3 0 0 1-5.4 1.8L14 14h-4l-1.6 1.8A3 3 0 0 1 3 14v-2a4 4 0 0 1 4-4z"/><path d="M8 11v2M7 12h2"/><path d="M16 11.5h.01M18 12.5h.01"/>',
    box: '<path d="M3 8l9-4.5L21 8v8l-9 4.5L3 16z"/><path d="M3 8l9 4.5L21 8M12 12.5V21"/>',
  };

  const iconSvg = (name) => `<svg viewBox="0 0 24 24">${ICONS[name] || ICONS.box}</svg>`;

  /* ---------- api ---------- */

  async function api(path, opts = {}) {
    const res = await fetch(path, {
      credentials: 'same-origin',
      ...opts,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': A.csrf,
        ...(opts.headers || {}),
      },
    });
    if (res.status === 401) { showLock(); throw new Error('locked'); }
    if (res.status === 419) { location.reload(); throw new Error('csrf'); }
    if (!res.ok) throw new Error('http ' + res.status);
    return res.status === 204 ? null : res.json();
  }

  /* ---------- toast ---------- */

  let toastTimer = null;
  function toast(msg) {
    const el = $('#toast');
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove('show'), 2400);
  }

  /* ============================================================
     LOCK SCREEN
     ============================================================ */

  let pinBuf = '';

  function showLock() {
    $('#lock').classList.remove('is-hidden');
    $('#app').classList.add('is-hidden');
    pinBuf = '';
    renderPinDots();
  }

  function renderPinDots() {
    $$('#pinDots span').forEach((d, i) => d.classList.toggle('on', i < pinBuf.length));
  }

  $('#pinPad').addEventListener('click', async (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    const k = btn.dataset.k;
    SFX.warm();

    if (k === 'del') {
      pinBuf = pinBuf.slice(0, -1);
      SFX.play('tick');
      renderPinDots();
      return;
    }

    if (pinBuf.length >= 4) return;
    pinBuf += k;
    SFX.play('tick');
    vibrate(8);
    renderPinDots();

    if (pinBuf.length === 4) {
      try {
        await api('/login', { method: 'POST', body: JSON.stringify({ pin: pinBuf }) });
        SFX.play('unlock');
        vibrate([10, 40, 18]);
        location.reload();
      } catch (err) {
        SFX.play('error');
        vibrate([60, 40, 60]);
        const dots = $('#pinDots');
        dots.classList.add('shake');
        setTimeout(() => { dots.classList.remove('shake'); pinBuf = ''; renderPinDots(); }, 460);
      }
    }
  });

  if (!A.authed) return; // nothing below matters until unlocked

  /* ============================================================
     VIEW SWITCHING
     ============================================================ */

  const views = { add: $('#view-add'), history: $('#view-history'), stats: $('#view-stats'), settings: $('#view-settings') };
  let activeView = 'add';

  $('#tabbar').addEventListener('click', (e) => {
    const tab = e.target.closest('.tab');
    if (!tab || tab.dataset.view === activeView) return;
    SFX.warm();
    SFX.play('tick');
    vibrate(6);
    activeView = tab.dataset.view;
    $$('.tab').forEach((t) => t.classList.toggle('is-active', t === tab));
    Object.entries(views).forEach(([name, el]) => {
      el.classList.remove('is-active');
      if (name === activeView) {
        void el.offsetWidth; // restart entry animation
        el.classList.add('is-active');
      }
    });
    if (activeView === 'history') loadHistory();
    if (activeView === 'stats') loadStats();
    if (activeView === 'settings') renderSettings();
  });

  /* ============================================================
     ADD VIEW
     ============================================================ */

  let amount = '';
  let pickedCat = null;     // user's explicit choice
  let suggestedCat = null;  // server suggestion
  let suggestTimer = null;

  const amountNum = $('#amountNum');
  const itemInput = $('#itemInput');
  const saveBtn = $('#saveBtn');

  function currentCat() {
    return pickedCat || suggestedCat || (cats.find((c) => c.slug === 'misc') || cats[0] || null);
  }

  function renderAmount(bump) {
    const v = amount ? parseInt(amount, 10) : 0;
    amountNum.textContent = fmt(v);
    amountNum.classList.toggle('zero', v === 0);
    if (bump && !reduceMotion) {
      amountNum.classList.remove('bump');
      void amountNum.offsetWidth;
      amountNum.classList.add('bump');
    }
    saveBtn.disabled = v <= 0;
  }

  $('#keypad').addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;
    SFX.warm();
    const k = btn.dataset.k;

    if (k === 'del') {
      amount = amount.slice(0, -1);
    } else if (amount.length < 12) {
      if (k === '000' && amount === '') return;
      amount = (amount + k).replace(/^0+(?=\d)/, '');
      if (amount.length > 12) amount = amount.slice(0, 12);
    }

    SFX.play('tick');
    vibrate(7);
    renderAmount(true);
  });

  /* ----- category chips ----- */

  function renderCatChips() {
    const row = $('#catRow');
    row.innerHTML = '';
    cats.forEach((c) => {
      const b = document.createElement('button');
      b.className = 'cat-chip';
      b.dataset.id = c.id;
      b.style.setProperty('--chip-color', c.color);
      b.innerHTML = iconSvg(c.icon) + `<span>${c.name}</span>`;
      b.addEventListener('click', () => {
        SFX.warm();
        pickedCat = (pickedCat && pickedCat.id === c.id) ? null : c;
        SFX.play('tick');
        vibrate(7);
        highlightCat();
      });
      row.appendChild(b);
    });
    highlightCat();
  }

  function highlightCat(pulse) {
    const active = currentCat();
    $$('.cat-chip').forEach((chip) => {
      const on = active && Number(chip.dataset.id) === active.id;
      chip.classList.toggle('on', on);
      if (on && pulse && !reduceMotion) {
        chip.classList.remove('pulse');
        void chip.offsetWidth;
        chip.classList.add('pulse');
        chip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
      }
    });
  }

  itemInput.addEventListener('input', () => {
    clearTimeout(suggestTimer);
    const q = itemInput.value.trim();
    if (q.length < 2) { suggestedCat = null; highlightCat(); return; }
    suggestTimer = setTimeout(async () => {
      try {
        const r = await api('/api/suggest?q=' + encodeURIComponent(q));
        suggestedCat = r.category_id ? catById[r.category_id] : null;
        if (!pickedCat) highlightCat(true);
      } catch (e) {}
    }, 240);
  });

  itemInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') itemInput.blur(); });

  /* ----- favorites ----- */

  async function loadFavorites() {
    try {
      const favs = await api('/api/favorites');
      const row = $('#favRow');
      row.innerHTML = '';
      favs.forEach((f) => {
        const c = catById[f.category_id];
        const b = document.createElement('button');
        b.className = 'fav-chip';
        b.innerHTML = `<span class="fav-dot" style="background:${c ? c.color : '#888'}"></span>${f.item} <b>${fmt(f.amount)}</b>`;
        b.addEventListener('click', () => {
          SFX.warm();
          itemInput.value = f.item;
          amount = String(f.amount);
          pickedCat = c || null;
          suggestedCat = null;
          renderAmount(true);
          highlightCat(true);
          SFX.play('pop');
          vibrate(8);
        });
        row.appendChild(b);
      });
    } catch (e) {}
  }

  /* ----- today total ----- */

  let todayTotalVal = 0;

  async function loadTodayTotal() {
    try {
      const today = localDate();
      const rows = await api('/api/expenses?date=' + today);
      setTodayTotal(rows.reduce((s, r) => s + r.amount, 0));
    } catch (e) {}
  }

  function setTodayTotal(v, animate) {
    const el = $('#todayTotal');
    if (!animate || reduceMotion) {
      todayTotalVal = v;
      el.textContent = 'Rp ' + fmt(v);
      return;
    }
    const from = todayTotalVal;
    todayTotalVal = v;
    const t0 = performance.now();
    const dur = 600;
    (function step(t) {
      const k = Math.min(1, (t - t0) / dur);
      const eased = 1 - Math.pow(1 - k, 3);
      el.textContent = 'Rp ' + fmt(Math.round(from + (v - from) * eased));
      if (k < 1) requestAnimationFrame(step);
    })(t0);
  }

  function localDate(d) {
    const x = d || new Date();
    return x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0');
  }

  /* ----- save ----- */

  saveBtn.addEventListener('click', async () => {
    const v = parseInt(amount || '0', 10);
    if (v <= 0) return;
    const cat = currentCat();
    if (!cat) { toast('No categories — run /setup first'); return; }

    const item = itemInput.value.trim() || cat.name;
    const learn = !!(pickedCat && (!suggestedCat || suggestedCat.id !== pickedCat.id) && itemInput.value.trim());

    const payload = { item, amount: v, category_id: cat.id, learn };

    saveBtn.disabled = true;
    celebrate(cat, v); // play immediately — never make the user wait for the network

    try {
      await api('/api/expenses', { method: 'POST', body: JSON.stringify(payload) });
      histCache = null;
    } catch (err) {
      if (err.message !== 'locked' && err.message !== 'csrf') {
        queuePush({ ...payload, spent_at: new Date().toISOString() });
        toast('Saved offline — will sync');
      }
    }

    setTodayTotal(todayTotalVal + v, true);
    amount = '';
    itemInput.value = '';
    pickedCat = null;
    suggestedCat = null;
    renderAmount(false);
    highlightCat();
    loadFavorites();
  });

  function celebrate(cat, v) {
    SFX.warm();
    SFX.play(cat.sound_preset);
    setTimeout(() => SFX.play('coin'), 130);
    vibrate([12, 30, 24]);

    saveBtn.classList.remove('fired');
    void saveBtn.offsetWidth;
    saveBtn.classList.add('fired');

    if (reduceMotion) return;

    // particle burst
    const layer = $('#burstLayer');
    const colors = [cat.color, '#e9eaee', '#d8c08a', cat.color];
    for (let i = 0; i < 16; i++) {
      const p = document.createElement('div');
      p.className = 'p';
      p.style.background = colors[i % colors.length];
      layer.appendChild(p);
      const ang = (Math.PI * 2 * i) / 16 + Math.random() * 0.5;
      const dist = 50 + Math.random() * 90;
      const dx = Math.cos(ang) * dist;
      const dy = Math.sin(ang) * dist - 30;
      p.animate(
        [
          { transform: 'translate(0,0) scale(1)', opacity: 1 },
          { transform: `translate(${dx}px,${dy}px) scale(0)`, opacity: 0 },
        ],
        { duration: 550 + Math.random() * 300, easing: 'cubic-bezier(0.16,1,0.3,1)' }
      ).onfinish = () => p.remove();
    }

    // amount flies up into the today-total chip
    const src = $('#amountDisplay').getBoundingClientRect();
    const dst = $('#todayTotal').getBoundingClientRect();
    const fly = document.createElement('div');
    fly.className = 'fly-amount';
    fly.textContent = fmt(v);
    fly.style.left = src.left + src.width / 2 + 'px';
    fly.style.top = src.top + src.height / 2 + 'px';
    fly.style.fontSize = '30px';
    document.body.appendChild(fly);
    const dx = dst.left + dst.width / 2 - (src.left + src.width / 2);
    const dy = dst.top + dst.height / 2 - (src.top + src.height / 2);
    fly.animate(
      [
        { transform: 'translate(-50%,-50%) scale(1)', opacity: 1 },
        { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(0.18)`, opacity: 0.2 },
      ],
      { duration: 620, easing: 'cubic-bezier(0.3,0.7,0.2,1)' }
    ).onfinish = () => fly.remove();
  }

  /* ----- offline queue ----- */

  function queuePush(payload) {
    const q = JSON.parse(localStorage.getItem('argent.queue') || '[]');
    q.push(payload);
    localStorage.setItem('argent.queue', JSON.stringify(q));
  }

  async function flushQueue() {
    let q = JSON.parse(localStorage.getItem('argent.queue') || '[]');
    if (!q.length) return;
    const remaining = [];
    for (const item of q) {
      try {
        await api('/api/expenses', { method: 'POST', body: JSON.stringify(item) });
      } catch (e) {
        remaining.push(item);
      }
    }
    localStorage.setItem('argent.queue', JSON.stringify(remaining));
    if (q.length && !remaining.length) {
      toast('Offline expenses synced');
      loadTodayTotal();
    }
  }

  window.addEventListener('online', flushQueue);

  /* ============================================================
     HISTORY VIEW
     ============================================================ */

  let histMonth = new Date();
  let histCache = null;

  const monthLabel = (d) => d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
  const monthParam = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');

  $('#histPrev').addEventListener('click', () => { histMonth.setMonth(histMonth.getMonth() - 1); histCache = null; loadHistory(); });
  $('#histNext').addEventListener('click', () => { histMonth.setMonth(histMonth.getMonth() + 1); histCache = null; loadHistory(); });

  async function loadHistory() {
    $('#histMonthLabel').textContent = monthLabel(histMonth);
    const now = new Date();
    $('#histNext').disabled = histMonth.getFullYear() === now.getFullYear() && histMonth.getMonth() === now.getMonth();

    const list = $('#histList');
    try {
      const rows = histCache && histCache.key === monthParam(histMonth)
        ? histCache.rows
        : (await api('/api/expenses?month=' + monthParam(histMonth)));
      histCache = { key: monthParam(histMonth), rows };
      renderHistory(rows);
    } catch (e) {
      list.innerHTML = '<div class="empty-note">Could not load. Check connection.</div>';
    }
  }

  function renderHistory(rows) {
    const list = $('#histList');
    list.innerHTML = '';

    if (!rows.length) {
      list.innerHTML = '<div class="empty-note"><span class="brand">ARGENT</span>Nothing logged this month yet.</div>';
      return;
    }

    const byDate = {};
    rows.forEach((r) => { (byDate[r.date] = byDate[r.date] || []).push(r); });

    const today = localDate();
    const yest = localDate(new Date(Date.now() - 864e5));

    Object.keys(byDate).sort().reverse().forEach((date) => {
      const group = document.createElement('div');
      group.className = 'day-group';
      const sum = byDate[date].reduce((s, r) => s + r.amount, 0);
      const d = new Date(date + 'T12:00:00');
      const name = date === today ? 'Today' : date === yest ? 'Yesterday'
        : d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

      group.innerHTML = `<div class="day-head"><span class="day-name">${name}</span><span class="day-sum metal-text">Rp ${fmt(sum)}</span></div>`;

      byDate[date].forEach((r) => {
        const c = catById[r.category_id] || {};
        const card = document.createElement('div');
        card.className = 'exp-card';
        card.style.setProperty('--c', c.color || '#888');
        card.innerHTML = `
          <div class="exp-icon">${iconSvg(c.icon)}</div>
          <div class="exp-mid">
            <div class="exp-item">${esc(r.item)}</div>
            <div class="exp-meta">${c.name || ''} · ${r.time}${r.note ? ' · ' + esc(r.note) : ''}</div>
          </div>
          <div class="exp-amount">Rp ${fmt(r.amount)}</div>
          <button class="exp-del">Delete</button>`;

        // long-press arms delete
        let pressTimer = null;
        card.addEventListener('pointerdown', () => {
          pressTimer = setTimeout(() => {
            $$('.exp-card.armed').forEach((x) => x.classList.remove('armed'));
            card.classList.add('armed');
            vibrate(15);
            SFX.play('tick');
          }, 480);
        });
        ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) =>
          card.addEventListener(ev, () => clearTimeout(pressTimer)));

        card.querySelector('.exp-del').addEventListener('click', async (e) => {
          e.stopPropagation();
          try {
            await api('/api/expenses/' + r.id, { method: 'DELETE' });
            SFX.play('trash');
            vibrate(20);
            card.classList.add('removing');
            setTimeout(() => { histCache = null; loadHistory(); loadTodayTotal(); }, 320);
          } catch (err) { toast('Delete failed'); }
        });

        group.appendChild(card);
      });

      list.appendChild(group);
    });

    // tap anywhere disarms
    list.addEventListener('click', (e) => {
      if (!e.target.closest('.exp-del')) $$('.exp-card.armed').forEach((x) => x.classList.remove('armed'));
    });
  }

  const esc = (s) => String(s).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));

  /* ============================================================
     STATS VIEW
     ============================================================ */

  let statMonth = new Date();

  $('#statPrev').addEventListener('click', () => { statMonth.setMonth(statMonth.getMonth() - 1); loadStats(); });
  $('#statNext').addEventListener('click', () => { statMonth.setMonth(statMonth.getMonth() + 1); loadStats(); });

  async function loadStats() {
    $('#statMonthLabel').textContent = monthLabel(statMonth);
    const now = new Date();
    $('#statNext').disabled = statMonth.getFullYear() === now.getFullYear() && statMonth.getMonth() === now.getMonth();

    const body = $('#statsBody');
    try {
      const s = await api('/api/stats?month=' + monthParam(statMonth));
      renderStats(s);
    } catch (e) {
      body.innerHTML = '<div class="empty-note">Could not load stats.</div>';
    }
  }

  function renderStats(s) {
    const body = $('#statsBody');

    if (!s.count) {
      body.innerHTML = '<div class="empty-note"><span class="brand">ARGENT</span>No expenses this month.</div>';
      return;
    }

    const delta = s.prev_total > 0 ? Math.round(((s.total - s.prev_total) / s.prev_total) * 100) : null;
    const deltaHtml = delta === null ? ''
      : delta >= 0
        ? `<span class="delta-up">▲ ${delta}%</span> vs last month`
        : `<span class="delta-down">▼ ${Math.abs(delta)}%</span> vs last month`;

    /* donut segments */
    const R = 52, C = 2 * Math.PI * R;
    let acc = 0;
    const segs = s.by_category.map((bc) => {
      const cat = catById[bc.category_id] || { color: '#888' };
      const frac = s.total > 0 ? bc.total / s.total : 0;
      const seg = `<circle class="seg" cx="64" cy="64" r="${R}" stroke="${cat.color}"
        stroke-dasharray="${(frac * C).toFixed(1)} ${C.toFixed(1)}"
        stroke-dashoffset="${(-acc * C).toFixed(1)}" transform="rotate(-90 64 64)"/>`;
      acc += frac;
      return seg;
    }).join('');

    const legend = s.by_category.map((bc) => {
      const cat = catById[bc.category_id] || { name: '?', color: '#888' };
      return `<div class="legend-row">
        <span class="legend-dot" style="background:${cat.color}"></span>
        <span class="legend-name">${cat.name}</span>
        <span class="legend-val">${fmt(bc.total)}</span>
        <span class="legend-pct">${bc.pct}%</span>
      </div>`;
    }).join('');

    /* daily bars */
    const maxDay = Math.max(1, ...Object.values(s.daily));
    let barsHtml = '';
    const todayDay = new Date().getDate();
    const isCurrent = monthParam(statMonth) === monthParam(new Date());
    for (let d = 1; d <= s.days_in_month; d++) {
      const v = s.daily[d] || 0;
      const h = Math.max(2, Math.round((v / maxDay) * 88));
      const cls = isCurrent && d === todayDay ? 'bar today' : 'bar';
      barsHtml += `<div class="${cls}" style="height:${h}px;animation-delay:${(d * 12)}ms" title="${d}: ${fmt(v)}"></div>`;
    }

    const topHtml = s.top_items.map((t, i) => `
      <div class="top-row">
        <span class="top-rank">${i + 1}</span>
        <span class="top-name">${esc(t.item)}</span>
        <span class="top-count">×${t.count}</span>
        <span class="top-val">${fmt(t.total)}</span>
      </div>`).join('');

    const budgetHtml = s.budget ? (() => {
      const used = Math.min(1.2, s.total / s.budget);
      const over = s.total > s.budget;
      const projOver = s.projected > s.budget;
      return `<div class="stat-section">
        <h3>Budget</h3>
        <div style="display:flex;justify-content:space-between;font-size:13px">
          <b style="font-variant-numeric:tabular-nums">Rp ${fmt(s.total)}</b>
          <span style="color:var(--ink-faint);font-variant-numeric:tabular-nums">of Rp ${fmt(s.budget)}</span>
        </div>
        <div class="pace-track"><div class="pace-fill ${over ? 'over' : ''}" data-w="${Math.min(100, used * 100)}"></div></div>
        <div class="pace-note">${over ? 'Over budget.' : projOver ? `On pace for Rp ${fmt(s.projected)} — trims needed.` : `Projected Rp ${fmt(s.projected)} — on track.`}</div>
      </div>`;
    })() : '';

    body.innerHTML = `
      <div class="stat-hero">
        <div class="stat-hero-label">Total spent</div>
        <div class="stat-hero-num" id="heroNum">Rp 0</div>
        <div class="stat-hero-sub">${s.count} expenses · ${deltaHtml}</div>
      </div>

      <div class="stat-grid">
        <div class="stat-cell">
          <div class="stat-cell-label">Avg / day</div>
          <div class="stat-cell-num">Rp ${fmt(s.avg_per_day)}</div>
          <div class="stat-cell-sub">${isCurrent ? 'projecting Rp ' + fmt(s.projected) : s.days_in_month + ' days'}</div>
        </div>
        <div class="stat-cell">
          <div class="stat-cell-label">Biggest</div>
          <div class="stat-cell-num">Rp ${fmt(s.biggest.amount)}</div>
          <div class="stat-cell-sub">${esc(s.biggest.item)}</div>
        </div>
      </div>

      ${budgetHtml}

      <div class="stat-section">
        <h3>By category</h3>
        <div class="donut-wrap">
          <svg class="donut" width="128" height="128" viewBox="0 0 128 128">
            <circle class="track" cx="64" cy="64" r="${R}"/>
            ${segs}
          </svg>
          <div class="donut-legend">${legend}</div>
        </div>
      </div>

      <div class="stat-section">
        <h3>Daily</h3>
        <div class="bars">${barsHtml}</div>
        <div class="bars-axis"><span>1</span><span>${Math.ceil(s.days_in_month / 2)}</span><span>${s.days_in_month}</span></div>
      </div>

      <div class="stat-section">
        <h3>Top items</h3>
        ${topHtml}
      </div>`;

    /* animate hero count-up + budget bar */
    const hero = $('#heroNum');
    const t0 = performance.now();
    (function step(t) {
      const k = Math.min(1, (t - t0) / 900);
      hero.textContent = 'Rp ' + fmt(Math.round(s.total * (1 - Math.pow(1 - k, 3))));
      if (k < 1) requestAnimationFrame(step);
    })(t0);

    requestAnimationFrame(() => {
      $$('.pace-fill', body).forEach((el) => {
        el.style.transform = `translateX(${el.dataset.w - 100}%)`;
      });
    });
  }

  /* ============================================================
     SETTINGS VIEW
     ============================================================ */

  async function renderSettings() {
    const body = $('#settingsBody');
    body.innerHTML = `
      <div class="set-section">
        <h3>Reminders</h3>
        <div id="remList"></div>
        <button class="set-btn" id="remAdd">Add reminder</button>
      </div>
      <div class="set-section">
        <h3>Notifications</h3>
        <div class="set-row">
          <span class="set-label">Push notifications<span class="set-sub">Required for reminders on this phone</span></span>
          <button class="switch" id="pushSwitch"></button>
        </div>
        <button class="set-btn" id="pushTest">Send test notification</button>
      </div>
      <div class="set-section">
        <h3>Preferences</h3>
        <div class="set-row">
          <span class="set-label">Sound effects</span>
          <button class="switch ${SFX.enabled ? 'on' : ''}" id="soundSwitch"></button>
        </div>
        <div class="set-row">
          <span class="set-label">Monthly budget<span class="set-sub">Leave empty for none</span></span>
          <input class="budget-input" id="budgetInput" type="text" inputmode="numeric" placeholder="—">
        </div>
      </div>
      <div class="set-section">
        <h3>Data</h3>
        <a class="set-btn" href="/export.csv" download>Export CSV</a>
        <button class="set-btn danger" id="lockBtn">Lock app</button>
      </div>`;

    /* reminders */
    const remList = $('#remList');
    let reminders = [];
    try { reminders = await api('/api/reminders'); } catch (e) {}

    function drawReminders() {
      remList.innerHTML = '';
      if (!reminders.length) {
        remList.innerHTML = '<div class="set-row" style="color:var(--ink-faint);font-size:13px">No reminders yet. Add one — e.g. 12:30 and 21:00.</div>';
        return;
      }
      reminders.forEach((r) => {
        const row = document.createElement('div');
        row.className = 'set-row';
        row.innerHTML = `
          <input class="rem-time" type="time" value="${r.time}">
          <button class="switch ${r.enabled ? 'on' : ''}"></button>
          <button class="rem-del" aria-label="Delete">×</button>`;
        row.querySelector('.rem-time').addEventListener('change', async (e) => {
          try { await api('/api/reminders/' + r.id, { method: 'PATCH', body: JSON.stringify({ time: e.target.value }) }); r.time = e.target.value; SFX.play('tick'); }
          catch (err) { toast('Failed'); }
        });
        row.querySelector('.switch').addEventListener('click', async (e) => {
          const sw = e.currentTarget;
          const on = !sw.classList.contains('on');
          sw.classList.toggle('on', on);
          SFX.play('tick'); vibrate(8);
          try { await api('/api/reminders/' + r.id, { method: 'PATCH', body: JSON.stringify({ enabled: on }) }); r.enabled = on; }
          catch (err) { sw.classList.toggle('on', !on); toast('Failed'); }
        });
        row.querySelector('.rem-del').addEventListener('click', async () => {
          try { await api('/api/reminders/' + r.id, { method: 'DELETE' }); reminders = reminders.filter((x) => x.id !== r.id); SFX.play('trash'); drawReminders(); }
          catch (err) { toast('Failed'); }
        });
        remList.appendChild(row);
      });
    }
    drawReminders();

    $('#remAdd').addEventListener('click', async () => {
      try {
        const r = await api('/api/reminders', { method: 'POST', body: JSON.stringify({ time: '12:30', label: '' }) });
        reminders.push(r);
        SFX.play('pop'); vibrate(8);
        drawReminders();
      } catch (err) { toast('Failed'); }
    });

    /* push */
    const pushSwitch = $('#pushSwitch');
    updatePushSwitch();

    async function updatePushSwitch() {
      const sub = await getPushSubscription();
      pushSwitch.classList.toggle('on', !!sub);
    }

    pushSwitch.addEventListener('click', async () => {
      SFX.play('tick');
      const sub = await getPushSubscription();
      if (sub) {
        try {
          await api('/api/push/unsubscribe', { method: 'POST', body: JSON.stringify({ endpoint: sub.endpoint }) });
          await sub.unsubscribe();
        } catch (e) {}
        toast('Notifications off');
      } else {
        const ok = await enablePush();
        toast(ok ? 'Notifications on' : 'Permission denied or unsupported');
        if (ok) { SFX.play('chime'); vibrate([10, 30, 14]); }
      }
      updatePushSwitch();
    });

    $('#pushTest').addEventListener('click', async () => {
      try {
        const r = await api('/api/push/test', { method: 'POST' });
        toast(r.sent > 0 ? 'Test sent — check notification' : 'No devices subscribed yet');
      } catch (e) { toast('Failed'); }
    });

    /* budget */
    const budgetInput = $('#budgetInput');
    try {
      const st = await api('/api/settings');
      if (st.budget) budgetInput.value = fmt(st.budget);
    } catch (e) {}
    budgetInput.addEventListener('change', async () => {
      const raw = parseInt(budgetInput.value.replace(/\D/g, '') || '0', 10);
      try {
        await api('/api/settings', { method: 'PATCH', body: JSON.stringify({ budget: raw }) });
        budgetInput.value = raw ? fmt(raw) : '';
        SFX.play('ding');
        toast(raw ? 'Budget set' : 'Budget cleared');
      } catch (e) { toast('Failed'); }
    });

    /* sound */
    $('#soundSwitch').addEventListener('click', (e) => {
      SFX.enabled = !SFX.enabled;
      e.currentTarget.classList.toggle('on', SFX.enabled);
      SFX.play('pop');
      vibrate(8);
    });

    /* lock */
    $('#lockBtn').addEventListener('click', async () => {
      try { await api('/logout', { method: 'POST' }); } catch (e) {}
      location.reload();
    });
  }

  /* ============================================================
     PUSH / SERVICE WORKER
     ============================================================ */

  async function getPushSubscription() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return null;
    try {
      const reg = await navigator.serviceWorker.ready;
      return await reg.pushManager.getSubscription();
    } catch (e) { return null; }
  }

  async function enablePush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !A.vapidKey) return false;
    try {
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') return false;
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlB64ToUint8(A.vapidKey),
      });
      const json = sub.toJSON();
      await api('/api/push/subscribe', {
        method: 'POST',
        body: JSON.stringify({ endpoint: sub.endpoint, keys: json.keys }),
      });
      return true;
    } catch (e) { return false; }
  }

  function urlB64ToUint8(s) {
    const pad = '='.repeat((4 - (s.length % 4)) % 4);
    const b64 = (s + pad).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(b64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  }

  /* ============================================================
     INIT
     ============================================================ */

  renderCatChips();
  renderAmount(false);
  loadTodayTotal();
  loadFavorites();
  flushQueue();
})();
