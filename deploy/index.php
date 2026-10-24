<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = preg_replace('#^/index\.php#', '', $uri) ?: '/';
$isApi = (bool) preg_match('#^/(api|up|storage)(/|$)#', $uri);

function kieu_mi_frontend_file(string $name): ?string
{
    foreach ([
        __DIR__.'/home.html',
        __DIR__.'/'.$name,
        '/home/fkssbbakhosting/repositories/kieu-mi/deploy/home.html',
        '/home/fkssbbakhosting/repositories/kieu-mi/frontend/build/web/'.$name,
        '/home/fkssbbakhosting/repositories/kieu-mi/frontend/web/'.$name,
    ] as $path) {
        if (is_file($path) && filesize($path) > 50) {
            return $path;
        }
    }

    return null;
}

function kieu_mi_send_html(string $path): void
{
    header('Content-Type: text/html; charset=UTF-8');
    readfile($path);
    exit;
}

if (! $isApi) {
    $page = kieu_mi_frontend_file('login.html') ?? kieu_mi_frontend_file('index.html');
    if ($page !== null) {
        kieu_mi_send_html($page);
    }
}

define('LARAVEL_START', microtime(true));

$laravel = null;
foreach ([
    __DIR__.'/laravel',
    dirname(__DIR__).'/mi.daothanhtoan.com/laravel',
    dirname(__DIR__).'/mi.daothanhtoan.com.vn/laravel',
    '/home/fkssbbakhosting/mi.daothanhtoan.com/laravel',
    '/home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel',
    '/home/fkssbbakhosting/repositories/kieu-mi/backend',
] as $path) {
    if (is_file($path.'/bootstrap/app.php') && is_file($path.'/vendor/autoload.php')) {
        $laravel = $path;
        break;
    }
}

if ($laravel === null) {
    if ($isApi) {
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'API chưa sẵn sàng. Mở /setup.php để cài đặt.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<p>Chưa thấy trang đăng nhập. Mở <a href="/setup.php">setup.php</a> hoặc Deploy lại trên Git.</p>';
    exit;
}

if (file_exists($maintenance = $laravel.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravel.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravel.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
