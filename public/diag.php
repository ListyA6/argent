<?php

/**
 * TEMPORARY deployment diagnostic. Delete once the site is up.
 * Access: /diag.php?key=<SETUP_KEY from server .env>
 */

$base = dirname(__DIR__);

// Gate on SETUP_KEY from the server's .env (never hardcoded — repo is public).
$envPath = $base.'/.env';
if (file_exists($envPath)) {
    $setupKey = '';
    foreach (file($envPath) as $line) {
        if (str_starts_with(trim($line), 'SETUP_KEY=')) {
            $setupKey = trim(substr(trim($line), 10));
            break;
        }
    }
    if ($setupKey === '' || ! hash_equals($setupKey, $_GET['key'] ?? '')) {
        http_response_code(403);
        exit('forbidden');
    }
}

header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        echo "\n\nFATAL: {$e['message']}\n{$e['file']}:{$e['line']}\n";
    }
    echo "\nmemory peak: ".round(memory_get_peak_usage(true) / 1048576, 1)." MB / limit ".ini_get('memory_limit')."\n";
});

echo 'PHP: '.PHP_VERSION."\n";
echo '.env: '.(file_exists($envPath) ? 'present' : 'MISSING')."\n\n";

foreach (['openssl', 'mbstring', 'pdo_mysql', 'curl', 'bcmath', 'gmp', 'ctype', 'fileinfo', 'dom', 'xml', 'session', 'tokenizer'] as $ext) {
    echo str_pad($ext, 12).': '.(extension_loaded($ext) ? 'yes' : '** MISSING **')."\n";
}
echo "\n";

foreach (['storage', 'storage/framework', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/logs', 'bootstrap/cache'] as $dir) {
    $p = "$base/$dir";
    echo str_pad($dir, 32).': '.(is_dir($p) ? (is_writable($p) ? 'writable' : '** NOT WRITABLE **') : '** MISSING **')."\n";
}
echo "\n";

try {
    require $base.'/vendor/autoload.php';
    echo "autoload: OK\n";
    $app = require $base.'/bootstrap/app.php';
    echo "app instance: OK\n";
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $resp = $kernel->handle(Illuminate\Http\Request::create('/up'));
    echo 'handle /up: '.$resp->getStatusCode()."\n";
} catch (Throwable $t) {
    echo "\nBOOT ERROR: ".get_class($t).': '.$t->getMessage()."\n";
    echo $t->getFile().':'.$t->getLine()."\n\n";
    echo substr($t->getTraceAsString(), 0, 3000)."\n";
}

$log = "$base/storage/logs/laravel.log";
if (file_exists($log)) {
    $lines = file($log);
    echo "\n--- laravel.log (last 40 lines) ---\n".implode('', array_slice($lines, -40));
}
