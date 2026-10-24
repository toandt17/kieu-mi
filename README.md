# Quản lý bán hàng — Kiều Mi

Ứng dụng quản trị bán hàng dùng trên điện thoại và xuất web. Backend Laravel, giao diện Flutter (tham khảo phong cách `app_noi_bo`).

## Tính năng

- Tài khoản quản trị / nhân viên, đăng nhập email hoặc số điện thoại
- Sản phẩm: hình ảnh, giá niêm yết, số lượng đã nhập, tồn kho, đã bán
- Nhập kho: tăng tồn, ghi số lượng nhập
- Đơn hàng nhiều sản phẩm: tự chọn **giá bán ra** từng dòng (có thể khác giá niêm yết)
- Hủy đơn thì hoàn kho
- Tổng quan doanh thu ngày/tháng, đơn gần đây, hàng sắp hết

## Chạy local

### 1. API Laravel

```bash
cd backend
copy .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```

API: `http://localhost:8000/api`

Tài khoản mẫu:

| Vai trò    | Email               | Mật khẩu |
|------------|---------------------|----------|
| Quản trị   | `admin@kieumi.vn`   | `password` |
| Nhân viên  | `nhanvien@kieumi.vn`| `password` |

### 2. Giao diện Flutter (web / điện thoại)

```bash
cd frontend
flutter pub get
flutter run -d chrome
```

Xuất web:

```bash
cd frontend
flutter build web --release
```

Nếu mở web trên điện thoại cùng Wi-Fi, chạy API với IP máy tính và truyền URL:

```bash
php artisan serve --host=0.0.0.0 --port=8000
flutter run -d chrome --dart-define=API_BASE_URL=http://192.168.x.x:8000/api
```

## Đưa lên hosting bằng Git (cPanel)

Repo: https://github.com/toandt17/kieu-mi.git

1. Trong cPanel bấm **Git Version Control** → **Create**.
2. Clone URL: `https://github.com/toandt17/kieu-mi.git`
3. Repository Path: `/home/fkssbbakhosting/repositories/kieu-mi`  
   (đừng clone thẳng vào folder website)
4. Bấm **Update** / **Deploy** để chạy `.cpanel.yml`  
   (copy giao diện + Laravel vào `mi.daothanhtoan.com.vn`)
5. Tạo file `.env` trên server:

```bash
cp /home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel/.env.example \
   /home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel/.env
```

Sửa DB, `APP_URL=https://mi.daothanhtoan.com.vn`, `APP_KEY`, rồi:

```bash
cd /home/fkssbbakhosting/mi.daothanhtoan.com.vn/laravel
composer install --no-dev
php artisan key:generate
php artisan migrate --seed
```

## Cấu trúc

```
backend/     Laravel 12 + Sanctum
frontend/    Flutter (web, Android, iOS)
deploy/      file cho hosting
```
