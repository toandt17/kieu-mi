<?php

declare(strict_types=1);

$repo = '/home/fkssbbakhosting/repositories/kieu-mi';
$home = '/home/fkssbbakhosting';
$lines = [];
$lines[] = 'time='.date('c');
$lines[] = 'cwd='.getcwd();
$lines[] = 'repo='.$repo;
$lines[] = 'repo_ok='.(is_file($repo.'/backend/artisan') ? 'yes' : 'no');

$dests = [];

$userdata = '/etc/userdatadomains';
if (is_readable($userdata)) {
    foreach (file($userdata, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $row) {
        if (stripos($row, 'mi.daothanhtoan.com') === false) {
            continue;
        }
        $parts = explode('==', $row);
        $path = trim((string) end($parts));
        if ($path !== '' && is_dir($path)) {
            $dests[$path] = 'userdatadomains';
        }
    }
}

foreach ([
    $home.'/mi.daothanhtoan.com',
    $home.'/mi.daothanhtoan.com.vn',
    $home.'/public_html',
    $home.'/public_html/mi',
    $home.'/public_html/mi.daothanhtoan.com',
    $home.'/public_html/mi.daothanhtoan.com.vn',
    $home.'/domains/mi.daothanhtoan.com/public_html',
    $home.'/domains/mi.daothanhtoan.com.vn/public_html',
] as $path) {
    if (is_dir($path)) {
        $dests[$path] = $dests[$path] ?? 'candidate';
    }
}

foreach (glob($home.'/*/setup.php') ?: [] as $file) {
    $dir = dirname($file);
    if (strpos($dir, '/repositories/') === false) {
        $dests[$dir] = $dests[$dir] ?? 'existing-setup';
    }
}

foreach (glob($home.'/*/*/setup.php') ?: [] as $file) {
    $dir = dirname($file);
    if (strpos($dir, '/repositories/') === false) {
        $dests[$dir] = $dests[$dir] ?? 'existing-setup';
    }
}

if ($dests === []) {
    $lines[] = 'ERROR=no destination directory found';
    writeLogs($home, $repo, $lines);
    echo implode("\n", $lines)."\n";
    exit(1);
}

foreach ($dests as $dest => $why) {
    $lines[] = "dest={$dest} ({$why})";
    try {
        deployTo($repo, $dest);
        $lines[] = 'ok='.$dest;
    } catch (Throwable $e) {
        $lines[] = 'fail='.$dest.' '.$e->getMessage();
    }
}

writeLogs($home, $repo, $lines);
foreach (array_keys($dests) as $dest) {
    @file_put_contents($dest.'/deploy-log.txt', implode("\n", $lines)."\n");
}

echo implode("\n", $lines)."\n";

function deployTo(string $repo, string $dest): void
{
    $laravel = $dest.'/laravel';
    if (!is_dir($laravel) && !@mkdir($laravel, 0755, true) && !is_dir($laravel)) {
        throw new RuntimeException('mkdir laravel failed');
    }
    @mkdir($dest.'/storage/products', 0755, true);
    @mkdir($laravel.'/storage/framework/cache', 0755, true);
    @mkdir($laravel.'/storage/framework/sessions', 0755, true);
    @mkdir($laravel.'/storage/framework/views', 0755, true);
    @mkdir($laravel.'/storage/logs', 0755, true);
    @mkdir($laravel.'/bootstrap/cache', 0755, true);

    rcopy($repo.'/backend', $laravel, ['.env', 'vendor', 'node_modules', '.git']);
    if (is_dir($repo.'/frontend/build/web')) {
        rcopy($repo.'/frontend/build/web', $dest, []);
    }
    copyFile($repo.'/deploy/index.php', $dest.'/index.php');
    copyFile($repo.'/deploy/.htaccess', $dest.'/.htaccess');
    copyFile($repo.'/deploy/setup.php', $dest.'/setup.php');
    copyFile($repo.'/deploy/database.sql', $dest.'/database.sql');
    copyFile($repo.'/deploy/laravel-deny.htaccess', $laravel.'/.htaccess');
    if (is_file($dest.'/index.html')) {
        @copy($dest.'/index.html', $dest.'/app.html');
    }
    if (is_file($repo.'/deploy/app.html')) {
        @copy($repo.'/deploy/app.html', $dest.'/app.html');
    }
    $loginPage = is_file($repo.'/deploy/home.html')
        ? $repo.'/deploy/home.html'
        : (is_file($dest.'/login.html') ? $dest.'/login.html' : $repo.'/frontend/web/login.html');
    if (is_file($loginPage)) {
        @copy($loginPage, $dest.'/index.html');
        @copy($loginPage, $dest.'/login.html');
    }
    foreach (['apple-touch-icon.png', 'apple-touch-icon-precomposed.png', 'km-192.png', 'km-512.png', 'manifest.json'] as $name) {
        $from = $repo.'/frontend/web/'.$name;
        if (is_file($from)) {
            @copy($from, $dest.'/'.$name);
        }
    }
}

function copyFile(string $from, string $to): void
{
    if (!is_file($from)) {
        throw new RuntimeException('missing '.$from);
    }
    if (!@copy($from, $to)) {
        throw new RuntimeException('copy failed '.$from.' -> '.$to);
    }
}

function rcopy(string $src, string $dst, array $skip): void
{
    if (!is_dir($src)) {
        throw new RuntimeException('missing dir '.$src);
    }
    if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
        throw new RuntimeException('mkdir '.$dst);
    }
    $items = scandir($src);
    if ($items === false) {
        throw new RuntimeException('scandir '.$src);
    }
    foreach ($items as $name) {
        if ($name === '.' || $name === '..' || in_array($name, $skip, true)) {
            continue;
        }
        $from = $src.'/'.$name;
        $to = $dst.'/'.$name;
        if (is_dir($from)) {
            rcopy($from, $to, $skip);
        } else {
            if (!@copy($from, $to)) {
                throw new RuntimeException('copy '.$from);
            }
        }
    }
}

function writeLogs(string $home, string $repo, array $lines): void
{
    $text = implode("\n", $lines)."\n";
    @file_put_contents($home.'/kieu-mi-deploy-log.txt', $text);
}
