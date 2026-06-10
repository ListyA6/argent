<?php

/**
 * TEMPORARY deployment diagnostic. Delete once the site is up.
 * Access: /diag.php?key=<SETUP_KEY from server .env>
 */

$base = dirname(__DIR__);

// Gate on SETUP_KEY from the server's .env (never hardcoded — repo is public).
$envPath = $base.'/.env';
$env = [];
if (file_exists($envPath)) {
    foreach (file($envPath) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }
    if (($env['SETUP_KEY'] ?? '') === '' || ! hash_equals($env['SETUP_KEY'], $_GET['key'] ?? '')) {
        http_response_code(403);
        exit('forbidden');
    }
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');
while (ob_get_level()) { ob_end_flush(); }

function say(string $s): void { echo $s."\n"; flush(); }

register_shutdown_function(function () {
    $e = error_get_last();
    say($e ? "\nlast php error: [{$e['type']}] {$e['message']} @ {$e['file']}:{$e['line']}" : "\nlast php error: none");
    say('memory peak: '.round(memory_get_peak_usage(true) / 1048576, 1).' MB');
    $log = dirname(__DIR__).'/storage/logs/laravel.log';
    if (file_exists($log)) {
        say("\n--- laravel.log tail ---");
        say(implode('', array_slice(file($log), -30)));
    } else {
        say('laravel.log: does not exist');
    }
});

say('PHP '.PHP_VERSION.' | disabled: '.(ini_get('disable_functions') ?: '(none)'));

/* --- direct DB test with .env creds --- */
try {
    $pdo = new PDO(
        'mysql:host='.($env['DB_HOST'] ?? 'localhost').';port='.($env['DB_PORT'] ?? '3306').';dbname='.($env['DB_DATABASE'] ?? ''),
        $env['DB_USERNAME'] ?? '',
        $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    say('DB connect: OK — '.count($tables).' tables ('.implode(', ', array_slice($tables, 0, 12)).')');
} catch (Throwable $t) {
    say('DB connect: FAILED — '.$t->getMessage());
}

/* --- bootstrap with step markers --- */
/* --- .env structural lint (keys + line lengths only, no values) --- */
say('--- .env lint ---');
foreach (file($envPath) as $i => $l) {
    $t = rtrim($l, "\r\n");
    $issues = '';
    if ($i === 0 && str_starts_with($t, "\xEF\xBB\xBF")) { $issues .= ' [BOM]'; }
    if (preg_match('/[\x{201C}\x{201D}\x{2018}\x{2019}\x{00A0}]/u', $t)) { $issues .= ' [smart-quote/nbsp]'; }
    if ($t !== '' && ! str_starts_with(ltrim($t), '#') && ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $t)) { $issues .= ' [NOT key=value]'; }
    $key = str_contains($t, '=') ? explode('=', $t, 2)[0] : $t;
    say(sprintf('%2d %-22s len=%d%s', $i + 1, substr($key, 0, 22), strlen($t), $issues));
}

say('step: require autoload');
require $base.'/vendor/autoload.php';

say('step: dotenv parse');
try {
    Dotenv\Dotenv::createImmutable($base)->load();
    say('dotenv: OK');
} catch (Throwable $t) {
    say('dotenv FAILED: '.get_class($t).': '.$t->getMessage());
}

say('step: make app');
$app = require $base.'/bootstrap/app.php';

say('step: make kernel');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

say('step: handle /up');
try {
    $resp = $kernel->handle($req = Illuminate\Http\Request::create('https://financetracker.sidestudio.id/up'));
    say('handle /up -> '.$resp->getStatusCode());
} catch (Throwable $t) {
    say('handle /up THREW: '.get_class($t).': '.$t->getMessage().' @ '.$t->getFile().':'.$t->getLine());
}

say('step: handle /');
try {
    $resp = $kernel->handle(Illuminate\Http\Request::create('https://financetracker.sidestudio.id/'));
    say('handle / -> '.$resp->getStatusCode());
    if ($resp->getStatusCode() >= 500) {
        say(substr(strip_tags($resp->getContent()), 0, 800));
    }
} catch (Throwable $t) {
    say('handle / THREW: '.get_class($t).': '.$t->getMessage().' @ '.$t->getFile().':'.$t->getLine());
}

say('done');
