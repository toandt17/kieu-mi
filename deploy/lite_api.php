<?php

declare(strict_types=1);

function lite_laravel_dir(): ?string
{
    foreach ([
        __DIR__.'/laravel',
        '/home/fkssbbakhosting/public_html/mi.daothanhtoan.com/laravel',
        '/home/fkssbbakhosting/mi.daothanhtoan.com/laravel',
        '/home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel',
        '/home/fkssbbakhosting/repositories/kieu-mi/backend',
    ] as $path) {
        if (is_file($path.'/.env')) {
            return $path;
        }
    }

    return null;
}

function lite_env(string $dir): array
{
    $out = [];
    foreach (file($dir.'/.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $out[trim($k)] = trim($v, " \t\"'");
    }

    return $out;
}

function lite_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dir = lite_laravel_dir();
    if ($dir === null) {
        throw new RuntimeException('Chưa có file .env Laravel');
    }
    $env = lite_env($dir);
    $pdo = new PDO(
        'mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';port='.($env['DB_PORT'] ?? '3306').';dbname='.($env['DB_DATABASE'] ?? '').';charset=utf8mb4',
        $env['DB_USERNAME'] ?? '',
        $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $pdo;
}

function lite_ok(mixed $data = null, string $message = 'Thành công', int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    $payload = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $payload['data'] = $data;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function lite_page(array $items, int $page, int $last, int $total): never
{
    http_response_code(200);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'message' => 'Thành công',
        'data' => $items,
        'meta' => [
            'current_page' => $page,
            'last_page' => $last,
            'per_page' => max(1, count($items)),
            'total' => $total,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function lite_err(string $message, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function lite_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);

    return is_array($json) ? $json : $_POST;
}

function lite_user_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'role' => $row['role'],
        'is_active' => (bool) $row['is_active'],
    ];
}

function lite_product_row(array $row): array
{
    $url = null;
    if (!empty($row['image_path'])) {
        $url = '/storage/products/'.basename((string) $row['image_path']);
    }

    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'sku' => $row['sku'],
        'listed_price' => (int) $row['listed_price'],
        'stock_qty' => (int) $row['stock_qty'],
        'imported_qty' => (int) $row['imported_qty'],
        'sold_qty' => (int) $row['sold_qty'],
        'image_url' => $url,
        'note' => $row['note'],
        'is_active' => (bool) $row['is_active'],
    ];
}

function lite_auth(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(\d+)\|(.+)/', $header, $m)) {
        lite_err('Chưa đăng nhập', 401);
    }
    $id = (int) $m[1];
    $hash = hash('sha256', $m[2]);
    $pdo = lite_db();
    $st = $pdo->prepare('SELECT * FROM personal_access_tokens WHERE id = ? AND token = ? LIMIT 1');
    $st->execute([$id, $hash]);
    $tok = $st->fetch();
    if (!$tok) {
        lite_err('Phiên đăng nhập hết hạn', 401);
    }
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int) $tok['tokenable_id']]);
    $user = $st->fetch();
    if (!$user || !(int) $user['is_active']) {
        lite_err('Tài khoản không hợp lệ', 401);
    }

    return $user;
}

function lite_handle(string $uri): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $path = preg_replace('#^/api#', '', $uri) ?: '/';
    $path = rtrim($path, '/') ?: '/';

    try {
        if ($method === 'POST' && $path === '/login') {
            $body = lite_body();
            $login = trim((string) ($body['login'] ?? ''));
            $pass = (string) ($body['password'] ?? '');
            $st = lite_db()->prepare('SELECT * FROM users WHERE email = ? OR phone = ? LIMIT 1');
            $st->execute([$login, $login]);
            $user = $st->fetch();
            if (!$user || !password_verify($pass, (string) $user['password'])) {
                lite_err('Email/SĐT hoặc mật khẩu không đúng.', 422);
            }
            if (!(int) $user['is_active']) {
                lite_err('Tài khoản đã bị khóa.', 403);
            }
            $plain = bin2hex(random_bytes(20));
            $pdo = lite_db();
            $ins = $pdo->prepare('INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, created_at, updated_at) VALUES (?,?,?,?,?,?,?)');
            $now = date('Y-m-d H:i:s');
            $ins->execute(['App\\Models\\User', $user['id'], 'mobile', hash('sha256', $plain), '["*"]', $now, $now]);
            lite_ok(['token' => $pdo->lastInsertId().'|'.$plain, 'user' => lite_user_row($user)], 'Đăng nhập thành công');
        }

        $user = lite_auth();
        $pdo = lite_db();

        if ($method === 'POST' && $path === '/logout') {
            lite_ok(null, 'Đã đăng xuất');
        }
        if ($method === 'GET' && $path === '/me') {
            lite_ok(lite_user_row($user));
        }
        if ($method === 'GET' && $path === '/dashboard') {
            $today = date('Y-m-d 00:00:00');
            $month = date('Y-m-01 00:00:00');
            $sum = fn (string $from) => (int) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status='completed' AND created_at >= ".$pdo->quote($from))->fetchColumn();
            $cnt = fn (string $from) => (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='completed' AND created_at >= ".$pdo->quote($from))->fetchColumn();
            $low = $pdo->query('SELECT * FROM products WHERE is_active=1 AND stock_qty<=5 ORDER BY stock_qty ASC LIMIT 6')->fetchAll();
            $recent = $pdo->query('SELECT * FROM orders ORDER BY id DESC LIMIT 6')->fetchAll();
            lite_ok([
                'today_revenue' => $sum($today),
                'month_revenue' => $sum($month),
                'today_orders' => $cnt($today),
                'month_orders' => $cnt($month),
                'product_count' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn(),
                'user_count' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn(),
                'low_stock_count' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1 AND stock_qty<=5')->fetchColumn(),
                'low_stock' => array_map('lite_product_row', $low),
                'recent_orders' => array_map(fn ($o) => lite_order_row($pdo, $o, false), $recent),
            ]);
        }
        if ($method === 'GET' && $path === '/products') {
            $rows = $pdo->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
            lite_page(array_map('lite_product_row', $rows), 1, 1, count($rows));
        }
        if ($method === 'POST' && $path === '/products') {
            $name = trim((string) ($_POST['name'] ?? lite_body()['name'] ?? ''));
            $price = (int) ($_POST['listed_price'] ?? lite_body()['listed_price'] ?? 0);
            $sku = $_POST['sku'] ?? lite_body()['sku'] ?? null;
            $note = $_POST['note'] ?? lite_body()['note'] ?? null;
            $qty = (int) ($_POST['initial_qty'] ?? lite_body()['initial_qty'] ?? 0);
            $active = (int) (($_POST['is_active'] ?? lite_body()['is_active'] ?? 1) ? 1 : 0);
            $now = date('Y-m-d H:i:s');
            $st = $pdo->prepare('INSERT INTO products (name, sku, listed_price, stock_qty, imported_qty, sold_qty, note, is_active, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $st->execute([$name, $sku ?: null, $price, $qty, $qty, 0, $note, $active, $now, $now]);
            $id = (int) $pdo->lastInsertId();
            $row = $pdo->query('SELECT * FROM products WHERE id='.$id)->fetch();
            lite_ok(lite_product_row($row), 'Đã thêm sản phẩm', 201);
        }
        if (preg_match('#^/products/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'GET') {
                $row = $pdo->query('SELECT * FROM products WHERE id='.$id)->fetch();
                $row ? lite_ok(lite_product_row($row)) : lite_err('Không thấy sản phẩm', 404);
            }
            if ($method === 'POST') {
                $name = trim((string) ($_POST['name'] ?? lite_body()['name'] ?? ''));
                $price = (int) ($_POST['listed_price'] ?? lite_body()['listed_price'] ?? 0);
                $sku = $_POST['sku'] ?? lite_body()['sku'] ?? null;
                $note = $_POST['note'] ?? lite_body()['note'] ?? null;
                $active = (int) (($_POST['is_active'] ?? lite_body()['is_active'] ?? 1) ? 1 : 0);
                $pdo->prepare('UPDATE products SET name=?, sku=?, listed_price=?, note=?, is_active=?, updated_at=? WHERE id=?')
                    ->execute([$name, $sku ?: null, $price, $note, $active, date('Y-m-d H:i:s'), $id]);
                $row = $pdo->query('SELECT * FROM products WHERE id='.$id)->fetch();
                lite_ok(lite_product_row($row), 'Đã cập nhật sản phẩm');
            }
            if ($method === 'DELETE') {
                $pdo->prepare('UPDATE products SET is_active=0, updated_at=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $id]);
                lite_ok(['id' => $id], 'Đã ẩn sản phẩm');
            }
        }
        if ($method === 'GET' && $path === '/orders') {
            $rows = $pdo->query('SELECT * FROM orders ORDER BY id DESC')->fetchAll();
            lite_page(array_map(fn ($o) => lite_order_row($pdo, $o, false), $rows), 1, 1, count($rows));
        }
        if ($method === 'POST' && $path === '/orders') {
            $body = lite_body();
            $items = $body['items'] ?? [];
            if (!is_array($items) || $items === []) {
                lite_err('Đơn hàng trống');
            }
            $total = 0;
            $pdo->beginTransaction();
            $code = 'DH'.date('ymdHis');
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO orders (code, customer_name, customer_phone, status, total_amount, note, user_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$code, $body['customer_name'] ?? null, $body['customer_phone'] ?? null, 'completed', 0, $body['note'] ?? null, $user['id'], $now, $now]);
            $oid = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                $pid = (int) ($item['product_id'] ?? 0);
                $qty = max(1, (int) ($item['quantity'] ?? 1));
                $sale = (int) ($item['sale_price'] ?? 0);
                $prod = $pdo->query('SELECT * FROM products WHERE id='.$pid)->fetch();
                if (!$prod) {
                    $pdo->rollBack();
                    lite_err('Sản phẩm không tồn tại');
                }
                $line = $sale * $qty;
                $total += $line;
                $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, listed_price, sale_price, quantity, line_total, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$oid, $pid, $prod['name'], $prod['listed_price'], $sale, $qty, $line, $now, $now]);
                $pdo->prepare('UPDATE products SET stock_qty=GREATEST(0,stock_qty-?), sold_qty=sold_qty+?, updated_at=? WHERE id=?')
                    ->execute([$qty, $qty, $now, $pid]);
            }
            $pdo->prepare('UPDATE orders SET total_amount=? WHERE id=?')->execute([$total, $oid]);
            $pdo->commit();
            $row = $pdo->query('SELECT * FROM orders WHERE id='.$oid)->fetch();
            lite_ok(lite_order_row($pdo, $row, true), 'Đã tạo đơn hàng', 201);
        }
        if (preg_match('#^/orders/(\d+)$#', $path, $m) && $method === 'GET') {
            $row = $pdo->query('SELECT * FROM orders WHERE id='.(int) $m[1])->fetch();
            $row ? lite_ok(lite_order_row($pdo, $row, true)) : lite_err('Không thấy đơn', 404);
        }
        if (preg_match('#^/orders/(\d+)/cancel$#', $path, $m) && $method === 'POST') {
            $pdo->prepare("UPDATE orders SET status='cancelled', updated_at=? WHERE id=?")->execute([date('Y-m-d H:i:s'), (int) $m[1]]);
            $row = $pdo->query('SELECT * FROM orders WHERE id='.(int) $m[1])->fetch();
            lite_ok(lite_order_row($pdo, $row, true));
        }
        if ($method === 'GET' && $path === '/stock-imports') {
            $rows = $pdo->query('SELECT s.*, p.name AS product_name FROM stock_imports s LEFT JOIN products p ON p.id=s.product_id ORDER BY s.id DESC')->fetchAll();
            $items = array_map(fn ($r) => [
                'id' => (int) $r['id'],
                'product_id' => (int) $r['product_id'],
                'product_name' => $r['product_name'],
                'quantity' => (int) $r['quantity'],
                'unit_cost' => $r['unit_cost'] !== null ? (int) $r['unit_cost'] : null,
                'note' => $r['note'],
                'created_at' => $r['created_at'],
            ], $rows);
            lite_page($items, 1, 1, count($items));
        }
        if ($method === 'POST' && $path === '/stock-imports') {
            $body = lite_body();
            $pid = (int) ($body['product_id'] ?? 0);
            $qty = (int) ($body['quantity'] ?? 0);
            $now = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO stock_imports (product_id, user_id, quantity, unit_cost, note, created_at, updated_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$pid, $user['id'], $qty, $body['unit_cost'] ?? null, $body['note'] ?? null, $now, $now]);
            $pdo->prepare('UPDATE products SET stock_qty=stock_qty+?, imported_qty=imported_qty+?, updated_at=? WHERE id=?')
                ->execute([$qty, $qty, $now, $pid]);
            $id = (int) $pdo->lastInsertId();
            lite_ok(['id' => $id, 'product_id' => $pid, 'quantity' => $qty], 'Đã nhập kho', 201);
        }
        if ($method === 'GET' && $path === '/users') {
            $rows = $pdo->query('SELECT * FROM users ORDER BY id DESC')->fetchAll();
            lite_page(array_map('lite_user_row', $rows), 1, 1, count($rows));
        }
        if ($method === 'POST' && $path === '/users') {
            $body = lite_body();
            $now = date('Y-m-d H:i:s');
            $hash = password_hash((string) ($body['password'] ?? 'password'), PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (name,email,phone,role,is_active,password,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$body['name'], $body['email'], $body['phone'] ?? null, $body['role'] ?? 'staff', !empty($body['is_active']) ? 1 : 0, $hash, $now, $now]);
            $row = $pdo->query('SELECT * FROM users WHERE id='.(int) $pdo->lastInsertId())->fetch();
            lite_ok(lite_user_row($row), 'Đã tạo tài khoản', 201);
        }
        if (preg_match('#^/users/(\d+)$#', $path, $m)) {
            $id = (int) $m[1];
            if ($method === 'PUT') {
                $body = lite_body();
                $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, role=?, is_active=?, updated_at=? WHERE id=?')
                    ->execute([$body['name'], $body['email'], $body['phone'] ?? null, $body['role'] ?? 'staff', !empty($body['is_active']) ? 1 : 0, date('Y-m-d H:i:s'), $id]);
                if (!empty($body['password'])) {
                    $pdo->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash((string) $body['password'], PASSWORD_BCRYPT), $id]);
                }
                $row = $pdo->query('SELECT * FROM users WHERE id='.$id)->fetch();
                lite_ok(lite_user_row($row), 'Đã cập nhật tài khoản');
            }
            if ($method === 'DELETE') {
                $pdo->prepare('UPDATE users SET is_active=0 WHERE id=?')->execute([$id]);
                lite_ok(['id' => $id]);
            }
        }

        lite_err('Không hỗ trợ '.$method.' '.$path, 404);
    } catch (Throwable $e) {
        lite_err('Lỗi: '.$e->getMessage(), 500);
    }
}

function lite_order_row(PDO $pdo, array $order, bool $withItems): array
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM order_items WHERE order_id='.(int) $order['id'])->fetchColumn();
    $data = [
        'id' => (int) $order['id'],
        'code' => $order['code'],
        'customer_name' => $order['customer_name'],
        'customer_phone' => $order['customer_phone'],
        'status' => $order['status'],
        'total_amount' => (int) $order['total_amount'],
        'note' => $order['note'],
        'items_count' => $count,
        'created_at' => $order['created_at'],
    ];
    if ($withItems) {
        $items = $pdo->query('SELECT * FROM order_items WHERE order_id='.(int) $order['id'])->fetchAll();
        $data['items'] = array_map(fn ($i) => [
            'id' => (int) $i['id'],
            'product_id' => $i['product_id'] !== null ? (int) $i['product_id'] : null,
            'product_name' => $i['product_name'],
            'listed_price' => (int) $i['listed_price'],
            'sale_price' => (int) $i['sale_price'],
            'quantity' => (int) $i['quantity'],
            'line_total' => (int) $i['line_total'],
        ], $items);
    }

    return $data;
}
