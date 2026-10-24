SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `listed_price` bigint unsigned NOT NULL DEFAULT 0,
  `stock_qty` int unsigned NOT NULL DEFAULT 0,
  `imported_qty` int unsigned NOT NULL DEFAULT 0,
  `sold_qty` int unsigned NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `note` text,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_sku_unique` (`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `unit_cost` bigint unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_imports_product_id_foreign` (`product_id`),
  KEY `stock_imports_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'completed',
  `total_amount` bigint unsigned NOT NULL DEFAULT 0,
  `note` text,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_code_unique` (`code`),
  KEY `orders_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `listed_price` bigint unsigned NOT NULL DEFAULT 0,
  `sale_price` bigint unsigned NOT NULL,
  `quantity` int unsigned NOT NULL,
  `returned_qty` int unsigned NOT NULL DEFAULT 0,
  `line_total` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `role`, `is_active`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Quản trị Kiều Mi', 'admin@kieumi.vn', '0901234567', 'admin', 1, '$2y$12$xSYN1TOXI6AkOJb0/oChMOPdcTXsXAZP9PKkPx0bIUmIQFTtlbL9a', NOW(), NOW()),
(2, 'Nhân viên bán hàng', 'nhanvien@kieumi.vn', '0912345678', 'staff', 1, '$2y$12$xSYN1TOXI6AkOJb0/oChMOPdcTXsXAZP9PKkPx0bIUmIQFTtlbL9a', NOW(), NOW());

INSERT INTO `products` (`id`, `name`, `sku`, `listed_price`, `stock_qty`, `imported_qty`, `sold_qty`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Son môi Kiều Mi Velvet', 'SM-001', 189000, 37, 40, 3, 1, NOW(), NOW()),
(2, 'Kem dưỡng ẩm ban đêm', 'KD-012', 259000, 25, 25, 0, 1, NOW(), NOW()),
(3, 'Serum vitamin C 15ml', 'SR-015', 329000, 17, 18, 1, 1, NOW(), NOW()),
(4, 'Mặt nạ giấy collagen', 'MN-008', 29000, 75, 80, 5, 1, NOW(), NOW()),
(5, 'Sữa rửa mặt dịu nhẹ', 'SRM-003', 149000, 30, 30, 0, 1, NOW(), NOW()),
(6, 'Nước tẩy trang 300ml', 'TT-300', 119000, 22, 22, 0, 1, NOW(), NOW());

INSERT INTO `stock_imports` (`product_id`, `user_id`, `quantity`, `unit_cost`, `note`, `created_at`, `updated_at`) VALUES
(1, 1, 40, 103950, 'Nhập kho ban đầu', NOW(), NOW()),
(2, 1, 25, 142450, 'Nhập kho ban đầu', NOW(), NOW()),
(3, 1, 18, 180950, 'Nhập kho ban đầu', NOW(), NOW()),
(4, 1, 80, 15950, 'Nhập kho ban đầu', NOW(), NOW()),
(5, 1, 30, 81950, 'Nhập kho ban đầu', NOW(), NOW()),
(6, 1, 22, 65450, 'Nhập kho ban đầu', NOW(), NOW());

CREATE TABLE IF NOT EXISTS `order_returns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `reason` varchar(120) NOT NULL,
  `note` text,
  `total_amount` bigint unsigned NOT NULL DEFAULT 0,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_returns_code_unique` (`code`),
  KEY `order_returns_order_id_foreign` (`order_id`),
  KEY `order_returns_user_id_foreign` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_return_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_return_id` bigint unsigned NOT NULL,
  `order_item_id` bigint unsigned DEFAULT NULL,
  `product_id` bigint unsigned DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int unsigned NOT NULL,
  `sale_price` bigint unsigned NOT NULL DEFAULT 0,
  `line_total` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_return_items_return_id_foreign` (`order_return_id`),
  KEY `order_return_items_item_id_foreign` (`order_item_id`),
  KEY `order_return_items_product_id_foreign` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `orders` (`id`, `code`, `customer_name`, `customer_phone`, `status`, `total_amount`, `note`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'DH2610240001', 'Nguyễn Thị Hoa', '0988111222', 'completed', 475000, 'Đơn mẫu', 2, NOW(), NOW()),
(2, 'DH2610240002', 'Trần Minh Anh', '0977666555', 'completed', 518000, 'Đơn mẫu', 1, NOW(), NOW());

INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `listed_price`, `sale_price`, `quantity`, `line_total`, `created_at`, `updated_at`) VALUES
(1, 1, 'Son môi Kiều Mi Velvet', 189000, 175000, 2, 350000, NOW(), NOW()),
(1, 4, 'Mặt nạ giấy collagen', 29000, 25000, 5, 125000, NOW(), NOW()),
(2, 3, 'Serum vitamin C 15ml', 329000, 329000, 1, 329000, NOW(), NOW()),
(2, 1, 'Son môi Kiều Mi Velvet', 189000, 189000, 1, 189000, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
