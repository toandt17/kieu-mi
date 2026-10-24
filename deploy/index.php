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

if (preg_match('#^/app\.html$#', $uri)) {
    foreach ([
        __DIR__.'/app.html',
        '/home/fkssbbakhosting/public_html/mi.daothanhtoan.com/app.html',
        '/home/fkssbbakhosting/repositories/kieu-mi/deploy/app.html',
        '/home/fkssbbakhosting/repositories/kieu-mi/frontend/build/web/index.html',
    ] as $path) {
        if (! is_file($path) || filesize($path) < 200) {
            continue;
        }
        $head = (string) file_get_contents($path, false, null, 0, 240);
        if (str_contains($head, 'Đăng nhập') || str_contains($head, 'id="form"')) {
            continue;
        }
        kieu_mi_send_html($path);
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo <<<'HTML'
<!DOCTYPE html>
<html lang="vi">
<head>
  <base href="/">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#FF5C00">
  <title>Quản lý bán hàng</title>
  <style>
    html,body{margin:0;height:100%;background:#ff5c00}
    #boot{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  </style>
</head>
<body>
  <div id="boot">Đang mở ứng dụng…</div>
  <script>
    try { if (!localStorage.getItem('flutter.auth_token')) location.replace('/'); } catch (e) {}
  </script>
  <script src="flutter_bootstrap.js" async></script>
</body>
</html>
HTML;
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
