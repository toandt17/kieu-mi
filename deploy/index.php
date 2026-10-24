<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

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
    http_response_code(500);
    echo 'Chưa thấy Laravel. Copy folder repositories/kieu-mi/backend thành laravel trong thư mục website.';
    exit;
}

if (file_exists($maintenance = $laravel.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravel.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravel.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
