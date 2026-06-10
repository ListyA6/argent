<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0b0c10">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title>Argent</title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icons/icon-192.png">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Marcellus&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body>

<div class="aurora" aria-hidden="true">
  <div class="blob blob-a"></div>
  <div class="blob blob-b"></div>
  <div class="blob blob-c"></div>
</div>
<div class="grain" aria-hidden="true"></div>

{{-- ============ LOCK SCREEN ============ --}}
<section id="lock" class="lock {{ $authed ? 'is-hidden' : '' }}">
  <div class="lock-inner">
    <h1 class="brand">ARGENT</h1>
    <p class="lock-sub">Enter PIN</p>
    <div class="pin-dots" id="pinDots">
      <span></span><span></span><span></span><span></span>
    </div>
    <div class="pinpad" id="pinPad">
      <button data-k="1">1</button><button data-k="2">2</button><button data-k="3">3</button>
      <button data-k="4">4</button><button data-k="5">5</button><button data-k="6">6</button>
      <button data-k="7">7</button><button data-k="8">8</button><button data-k="9">9</button>
      <span></span><button data-k="0">0</button><button data-k="del" class="key-del" aria-label="Delete">⌫</button>
    </div>
  </div>
</section>

{{-- ============ APP ============ --}}
<main id="app" class="app {{ $authed ? '' : 'is-hidden' }}">

  {{-- ---------- ADD ---------- --}}
  <section class="view view-add is-active" id="view-add">
    <header class="topbar">
      <span class="brand brand-sm">ARGENT</span>
      <span class="today-total" id="todayTotal" title="Spent today">Rp 0</span>
    </header>

    <div class="amount-stage">
      <div class="amount-display" id="amountDisplay">
        <span class="amount-rp">Rp</span><span class="amount-num" id="amountNum">0</span>
      </div>
      <div class="burst-layer" id="burstLayer"></div>
    </div>

    <div class="fav-row" id="favRow"></div>

    <div class="item-row glass">
      <input id="itemInput" type="text" placeholder="What did you buy?" autocomplete="off" maxlength="120" enterkeyhint="done">
    </div>

    <div class="cat-row" id="catRow"></div>

    <div class="keypad" id="keypad">
      <button data-k="1">1</button><button data-k="2">2</button><button data-k="3">3</button>
      <button data-k="4">4</button><button data-k="5">5</button><button data-k="6">6</button>
      <button data-k="7">7</button><button data-k="8">8</button><button data-k="9">9</button>
      <button data-k="000" class="key-000">000</button><button data-k="0">0</button><button data-k="del" class="key-del" aria-label="Delete">⌫</button>
    </div>

    <button class="save-btn" id="saveBtn" disabled>
      <span class="save-label">Log expense</span>
      <span class="save-sheen"></span>
    </button>
  </section>

  {{-- ---------- HISTORY ---------- --}}
  <section class="view view-history" id="view-history">
    <header class="topbar">
      <h2 class="view-title">History</h2>
      <div class="month-nav">
        <button class="month-arrow" id="histPrev" aria-label="Previous month">‹</button>
        <span class="month-label" id="histMonthLabel"></span>
        <button class="month-arrow" id="histNext" aria-label="Next month">›</button>
      </div>
    </header>
    <div class="hist-list" id="histList"></div>
  </section>

  {{-- ---------- STATS ---------- --}}
  <section class="view view-stats" id="view-stats">
    <header class="topbar">
      <h2 class="view-title">Stats</h2>
      <div class="month-nav">
        <button class="month-arrow" id="statPrev" aria-label="Previous month">‹</button>
        <span class="month-label" id="statMonthLabel"></span>
        <button class="month-arrow" id="statNext" aria-label="Next month">›</button>
      </div>
    </header>
    <div class="stats-body" id="statsBody"></div>
  </section>

  {{-- ---------- SETTINGS ---------- --}}
  <section class="view view-settings" id="view-settings">
    <header class="topbar">
      <h2 class="view-title">Settings</h2>
    </header>
    <div class="settings-body" id="settingsBody"></div>
  </section>

  {{-- ---------- NAV ---------- --}}
  <nav class="tabbar glass" id="tabbar">
    <button class="tab is-active" data-view="add" aria-label="Add">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg><span>Add</span>
    </button>
    <button class="tab" data-view="history" aria-label="History">
      <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h10"/></svg><span>History</span>
    </button>
    <button class="tab" data-view="stats" aria-label="Stats">
      <svg viewBox="0 0 24 24"><path d="M5 20V10M12 20V4M19 20v-7"/></svg><span>Stats</span>
    </button>
    <button class="tab" data-view="settings" aria-label="Settings">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.09a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.09a1.7 1.7 0 0 0 1.55 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.55 1z"/></svg><span>More</span>
    </button>
  </nav>

  <div class="toast" id="toast"></div>
</main>

<script>
window.ARGENT = {
  authed: @json($authed),
  categories: @json($categories),
  vapidKey: @json($vapidPublicKey),
  csrf: document.querySelector('meta[name="csrf-token"]').content,
};
</script>
<script src="/assets/sfx.js?v=1"></script>
<script src="/assets/app.js?v=1"></script>
</body>
</html>
