<?php

declare(strict_types=1);

$siteRoot = __DIR__;
$laravel = $siteRoot.'/laravel';
$envPath = $laravel.'/.env';

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$done = false;
$error = null;
$log = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = trim((string) ($_POST['db'] ?? ''));
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    $url = trim((string) ($_POST['url'] ?? 'https://mi.daothanhtoan.com'));

    $env = <<<ENV
APP_NAME="Quản lý bán hàng"
APP_ENV=production
APP_KEY=base64:7IQVdXkvJST+g4Ex7SCWq/th0PI/gx1pjrZfkbkkYMc=
APP_DEBUG=false
APP_URL={$url}

APP_LOCALE=vi
APP_FALLBACK_LOCALE=vi
LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE={$db}
DB_USERNAME={$user}
DB_PASSWORD="{$pass}"

SESSION_DRIVER=file
SESSION_LIFETIME=120
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
CACHE_STORE=file
ENV;

    if (! is_dir($laravel)) {
        $error = 'Không thấy folder laravel. Hãy Deploy lại trên Git Version Control.';
    } elseif (file_put_contents($envPath, $env) === false) {
        $error = 'Không ghi được file .env. Chỉnh quyền folder laravel thành 755.';
    } else {
        $artisan = $laravel.'/artisan';
        if (is_file($artisan) && is_file($laravel.'/vendor/autoload.php')) {
            $cmd = 'cd '.escapeshellarg($laravel).' && php artisan migrate --seed --force 2>&1';
            $log = (string) shell_exec($cmd);
            $done = str_contains($log, 'DONE') || str_contains($log, 'successfully') || str_contains(strtolower($log), 'migrated');
            if ($log === '') {
                $error = 'Hosting chặn lệnh Terminal. Hãy import file deploy/database.sql trong phpMyAdmin.';
            }
        } else {
            $error = 'Chưa có vendor. Bấm Deploy lại trên Git, rồi mở lại trang này.';
        }
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cài đặt Quản lý bán hàng</title>
    <style>
        body { font-family: sans-serif; max-width: 520px; margin: 40px auto; padding: 0 16px; }
        label { display: block; margin: 12px 0 4px; font-weight: 600; }
        input { width: 100%; padding: 10px; box-sizing: border-box; }
        button { margin-top: 16px; width: 100%; padding: 12px; font-weight: 700; }
        .ok { color: #0a7; }
        .err { color: #c00; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; font-size: 12px; }
    </style>
</head>
<body>
    <h1>Cài đặt hosting</h1>
    <?php if ($done): ?>
        <p class="ok">Xong. Mở <a href="/">trang chủ</a> và đăng nhập admin@kieumi.vn / password.</p>
        <p>Xóa file setup.php trên File Manager sau khi vào được app.</p>
    <?php else: ?>
        <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
        <form method="post">
            <label>Database</label>
            <input name="db" value="<?= h($_POST['db'] ?? 'fkssbbakhosting_mi') ?>">
            <label>User MySQL</label>
            <input name="user" value="<?= h($_POST['user'] ?? 'fkssbbakhosting_mi') ?>">
            <label>Mật khẩu MySQL</label>
            <input name="pass" type="password" value="<?= h($_POST['pass'] ?? '') ?>">
            <label>URL website</label>
            <input name="url" value="<?= h($_POST['url'] ?? 'https://mi.daothanhtoan.com') ?>">
            <button type="submit">Cài đặt</button>
        </form>
    <?php endif; ?>
    <?php if ($log !== ''): ?><pre><?= h($log) ?></pre><?php endif; ?>
</body>
</html>
