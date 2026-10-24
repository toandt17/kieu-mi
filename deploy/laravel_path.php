<?php

function kieu_mi_laravel_path(): ?string
{
    $candidates = [
        __DIR__.'/laravel',
        dirname(__DIR__).'/mi.daothanhtoan.com/laravel',
        dirname(__DIR__).'/mi.daothanhtoan.com.vn/laravel',
        '/home/fkssbbakhosting/mi.daothanhtoan.com/laravel',
        '/home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel',
        '/home/fkssbbakhosting/repositories/kieu-mi/backend',
    ];

    foreach ($candidates as $path) {
        if (is_file($path.'/artisan') && is_file($path.'/bootstrap/app.php')) {
            return $path;
        }
    }

    return null;
}

function kieu_mi_sql_path(): ?string
{
    $candidates = [
        __DIR__.'/database.sql',
        '/home/fkssbbakhosting/repositories/kieu-mi/deploy/database.sql',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}
