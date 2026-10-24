<?php

declare(strict_types=1);

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function findLaravel(): ?string
{
    foreach ([
        __DIR__.'/laravel',
        dirname(__DIR__).'/mi.daothanhtoan.com/laravel',
        dirname(__DIR__).'/mi.daothanhtoan.com.vn/laravel',
        '/home/fkssbbakhosting/mi.daothanhtoan.com/laravel',
        '/home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel',
        '/home/fkssbbakhosting/repositories/kieu-mi/backend',
    ] as $path) {
        if (is_file($path.'/artisan') && is_file($path.'/bootstrap/app.php')) {
            return $path;
        }
    }

    return null;
}

function findSql(): ?string
{
    foreach ([
        __DIR__.'/database.sql',
        '/home/fkssbbakhosting/repositories/kieu-mi/deploy/database.sql',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function importSql(string $sqlFile, string $db, string $user, string $pass): string
{
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname='.$db.';charset=utf8mb4',
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('Không đọc được database.sql');
    }
    $pdo->exec($sql);

    return 'Đã import database.sql';
}

$laravel = findLaravel();
$done = false;
$error = null;
$log = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = trim((string) ($_POST['db'] ?? ''));
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    $url = trim((string) ($_POST['url'] ?? 'https://mi.daothanhtoan.com'));

    if ($laravel === null) {
        $error = 'Không thấy source Laravel. Trong File Manager, copy folder /home/fkssbbakhosting/repositories/kieu-mi/backend vào thư mục website và đổi tên thành laravel (cùng chỗ với setup.php).';
    } else {
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
        $envPath = $laravel.'/.env';
        if (file_put_contents($envPath, $env) === false) {
            $error = 'Không ghi được file .env trong '.$laravel;
        } else {
            $log = 'Đã ghi .env tại '.$laravel."\n";
            $sql = findSql();
            try {
                if ($sql) {
                    $log .= importSql($sql, $db, $user, $pass)."\n";
                    $done = true;
                } elseif (is_file($laravel.'/vendor/autoload.php')) {
                    $cmd = 'cd '.escapeshellarg($laravel).' && php artisan migrate --seed --force 2>&1';
                    $log .= (string) shell_exec($cmd);
                    $done = $log !== '';
                } else {
                    $error = 'Chưa import được DB. Vào phpMyAdmin import file repositories/kieu-mi/deploy/database.sql';
                }
            } catch (Throwable $e) {
                $error = 'Lỗi database: '.$e->getMessage();
            }
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
        body { font-family: sans-serif; max-width: 560px; margin: 40px auto; padding: 0 16px; }
        label { display: block; margin: 12px 0 4px; font-weight: 600; }
        input { width: 100%; padding: 10px; box-sizing: border-box; }
        button { margin-top: 16px; width: 100%; padding: 12px; font-weight: 700; }
        .ok { color: #0a7; }
        .err { color: #c00; white-space: pre-wrap; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; font-size: 12px; }
        code { background: #f4f4f4; padding: 2px 4px; }
    </style>
</head>
<body>
    <h1>Cài đặt hosting</h1>
    <p>Bản: 20261024-2</p>
    <p>Laravel: <code><?= h($laravel ?? 'chưa thấy') ?></code></p>
    <?php if ($done): ?>
        <p class="ok">Xong. Mở <a href="/">trang chủ</a> — admin@kieumi.vn / password (hoặc nhanvien@kieumi.vn / password).</p>
        <p>Xóa file <code>setup.php</code> trên File Manager.</p>
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
