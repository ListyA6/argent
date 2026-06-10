# Argent — Listy's Personal Expense Tracker

Single-user PWA. Laravel 12 + MySQL backend, vanilla-JS glassmorphism frontend.
Live at **https://financetracker.sidestudio.id** (Hostinger shared hosting).
Local dev path: `C:\xampp\htdocs\!PROJECTBANK\argent`.

## CRITICAL RULES FOR AGENTS

1. **NEVER push without asking.** After making changes, always ask Listy directly:
   *"Should we push and deploy this now?"* A `git push` to `main` auto-deploys to
   production via the Hostinger webhook. There is **no EAS here** — this is a web
   app, not Expo. The deploy pipeline is: `git push` → Hostinger webhook pulls →
   live in seconds.
2. **Never commit secrets.** `.env` is gitignored and exists in two places only:
   local, and on the server (edited via Hostinger File Manager). The repo is
   **public** — anything committed is world-readable.
3. **`vendor/` IS committed** (pull-only deploy, no composer on server). Before
   pushing dependency changes, run `composer install --no-dev --optimize-autoloader`
   so dev packages don't ship. To get dev tools back locally: `composer install`.
4. **Bump cache versions when changing frontend assets**: the `?v=N` query in
   `resources/views/app.blade.php` AND the `CACHE = 'argent-vN'` constant +
   `SHELL` URLs in `public/sw.js`. Otherwise phones keep the old cached UI.
5. **If migrations or seeders changed**, after deploy visit
   `https://financetracker.sidestudio.id/setup?key=<SETUP_KEY from server .env>`
   to run them (idempotent).

## Local commands (XAMPP PHP, not on PATH)

```powershell
C:\xampp\php\php.exe artisan serve --port=8000   # run locally → http://127.0.0.1:8000 (PIN 1234)
C:\xampp\php\php.exe artisan migrate --force     # local DB: mysql "argent" root/no-password
C:\xampp\php\php.exe "C:\xampp\htdocs\!PROJECTBANK\.tools\composer.phar" <cmd>
C:\xampp\php\php.exe artisan reminders:send      # manually fire due reminders
```

OpenSSL CLI quirk: VAPID/key generation needs `$env:OPENSSL_CONF = "C:\xampp\apache\conf\openssl.cnf"`.

## Architecture

- **Auth**: 4-digit PIN (`APP_PIN` in .env) → session flag; `pin` middleware guards `/api/*`.
- **Backend**: controllers in `app/Http/Controllers` (Expense, Stats, Reminder, Push, Setting, Auth, Setup). Push via `app/Services/PushService.php` (minishlink/web-push, VAPID keys in .env).
- **Reminders**: `reminders:send` command, scheduled every 5 min (`routes/console.php`). On Hostinger, cron runs it; reminder fires once/day per entry within a 45-min window of its time. Content = today's logged expenses (or a nudge if empty).
- **Auto-categorization**: `keyword_rules` table, seeded Indonesian terms; `GET /api/suggest?q=` matches longest keyword; saving with a manually-picked category learns the item's words.
- **Frontend**: one Blade view (`resources/views/app.blade.php`) + `public/assets/app.{css,js}` + `sfx.js` (Web Audio synth — sounds are code, not files) + `public/sw.js` (shell cache, push, notification click).
- **Offline**: failed POSTs queue in localStorage, flushed on reconnect.
- **Timezone**: `Asia/Jakarta` everywhere (config/app.php).

## Deploy shape (Hostinger)

Repo root deploys into the subdomain's `public_html`. Root `.htaccess` rewrites
all traffic into `public/`. Server `.env` holds production values incl.
`SETUP_KEY` and VAPID keys. Database: `u841253279_finance`.

## Design language

Liquid-metal vault: graphite background, drifting aurora blobs, frosted glass
panels, champagne-silver metallic gradients on numbers (Marcellus + Sora fonts).
No emoji. All animation is transform/opacity only — keep it that way, it's what
makes 120 Hz phones feel smooth. Sounds are synthesized per category in `sfx.js`.
