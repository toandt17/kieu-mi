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
    '/home/fkssbbakhosting/public_html/mi.daothanhtoan.com/laravel',
    '/home/fkssbbakhosting/repositories/kieu-mi/backend',
    dirname(__DIR__).'/mi.daothanhtoan.com/laravel',
    '/home/fkssbbakhosting/mi.daothanhtoan.com/laravel',
    '/home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel',
] as $path) {
    if (is_file($path.'/bootstrap/app.php') && is_file($path.'/vendor/autoload.php')) {
        $laravel = $path;
        break;
    }
}

if ($laravel === null) {
    http_response_code(503);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Chưa có vendor Laravel. Trong File Manager, copy repositories/kieu-mi/backend vào public_html/mi.daothanhtoan.com và đặt tên folder là laravel.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (file_exists($maintenance = $laravel.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravel.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravel.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
