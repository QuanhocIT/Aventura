-- BepsoViet complete MySQL schema
-- Source baseline: current Laravel migrations + business entities extracted from `bao_cao_quan_ly_nha_hang`
-- Target: MySQL 8.0+

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `bepso_viet`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bepso_viet`;

DROP TABLE IF EXISTS `pulse_aggregates`;
DROP TABLE IF EXISTS `pulse_entries`;
DROP TABLE IF EXISTS `pulse_values`;
DROP TABLE IF EXISTS `failed_jobs`;
DROP TABLE IF EXISTS `job_batches`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `cache_locks`;
DROP TABLE IF EXISTS `cache`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `role_has_permissions`;
DROP TABLE IF EXISTS `model_has_roles`;
DROP TABLE IF EXISTS `model_has_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `customer_feedback`;
DROP TABLE IF EXISTS `violation_reports`;
DROP TABLE IF EXISTS `salary_adjustments`;
DROP TABLE IF EXISTS `salaries`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `shift_closings`;
DROP TABLE IF EXISTS `schedule_assignments`;
DROP TABLE IF EXISTS `work_shifts`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `product_recipes`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `inventory_transactions`;
DROP TABLE IF EXISTS `inventories`;
DROP TABLE IF EXISTS `ingredients`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `units`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `restaurant_tables`;
DROP TABLE IF EXISTS `areas`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `restaurant_settings`;
DROP TABLE IF EXISTS `restaurant_branches`;
DROP TABLE IF EXISTS `restaurant_subscriptions`;
DROP TABLE IF EXISTS `subscription_plans`;
DROP TABLE IF EXISTS `restaurants`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `two_factor_secret` TEXT NULL,
  `two_factor_recovery_codes` TEXT NULL,
  `two_factor_confirmed_at` TIMESTAMP NULL DEFAULT NULL,
  `phone` VARCHAR(20) NULL,
  `avatar_url` VARCHAR(2048) NULL,
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `users_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` VARCHAR(255) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `payload` LONGTEXT NOT NULL,
  `last_activity` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `subscription_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` ENUM('monthly', 'quarterly', 'yearly') NOT NULL DEFAULT 'monthly',
  `max_branches` INT UNSIGNED NULL,
  `max_tables` INT UNSIGNED NULL,
  `max_users` INT UNSIGNED NULL,
  `features` JSON NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id` BIGINT UNSIGNED NULL,
  `owner_user_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `tax_code` VARCHAR(50) NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `address` VARCHAR(500) NULL,
  `logo_url` VARCHAR(2048) NULL,
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  `currency` VARCHAR(10) NOT NULL DEFAULT 'VND',
  `status` ENUM('active', 'expired', 'suspended') NOT NULL DEFAULT 'active',
  `subscription_started_at` DATE NULL,
  `subscription_ends_at` DATE NULL,
  `trial_ends_at` DATE NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurants_code_unique` (`code`),
  UNIQUE KEY `restaurants_slug_unique` (`slug`),
  KEY `restaurants_plan_status_index` (`plan_id`, `status`),
  KEY `restaurants_owner_index` (`owner_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `plan_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('trial', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'trial',
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `renewal_at` DATETIME NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `meta` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `restaurant_subscriptions_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `restaurant_subscriptions_plan_index` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_branches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `address` VARCHAR(500) NULL,
  `manager_user_id` BIGINT UNSIGNED NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurant_branches_restaurant_code_unique` (`restaurant_id`, `code`),
  KEY `restaurant_branches_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `restaurant_branches_manager_index` (`manager_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `key_name` VARCHAR(100) NOT NULL,
  `value` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurant_settings_scope_key_unique` (`restaurant_id`, `branch_id`, `key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `employees` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `employee_code` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(255) NOT NULL,
  `date_of_birth` DATE NULL,
  `gender` ENUM('male', 'female', 'other') NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `address` VARCHAR(500) NULL,
  `citizen_id_number` VARCHAR(50) NULL,
  `citizen_id_front_url` VARCHAR(2048) NULL,
  `citizen_id_back_url` VARCHAR(2048) NULL,
  `hire_date` DATE NULL,
  `employment_type` ENUM('full_time', 'part_time', 'seasonal') NOT NULL DEFAULT 'full_time',
  `job_title` VARCHAR(100) NULL,
  `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive', 'terminated') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_restaurant_code_unique` (`restaurant_id`, `employee_code`),
  UNIQUE KEY `employees_user_id_unique` (`user_id`),
  KEY `employees_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `employees_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `areas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `areas_branch_code_unique` (`branch_id`, `code`),
  KEY `areas_restaurant_status_index` (`restaurant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `restaurant_tables` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `area_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `qr_code` VARCHAR(255) NULL,
  `capacity` INT UNSIGNED NOT NULL DEFAULT 2,
  `status` ENUM('available', 'occupied', 'reserved', 'inactive') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `restaurant_tables_area_name_unique` (`area_id`, `name`),
  KEY `restaurant_tables_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `restaurant_tables_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `display_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_restaurant_slug_unique` (`restaurant_id`, `slug`),
  KEY `product_categories_restaurant_status_index` (`restaurant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(50) NOT NULL,
  `symbol` VARCHAR(20) NOT NULL,
  `type` ENUM('mass', 'volume', 'count') NOT NULL DEFAULT 'count',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `units_scope_symbol_unique` (`restaurant_id`, `symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `contact_name` VARCHAR(255) NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `address` VARCHAR(500) NULL,
  `notes` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suppliers_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `suppliers_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ingredients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `supplier_id` BIGINT UNSIGNED NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(100) NULL,
  `category_name` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `min_stock_level` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `reorder_level` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `average_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ingredients_restaurant_sku_unique` (`restaurant_id`, `sku`),
  KEY `ingredients_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `ingredients_branch_index` (`branch_id`),
  KEY `ingredients_supplier_index` (`supplier_id`),
  KEY `ingredients_unit_index` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `ingredient_id` BIGINT UNSIGNED NOT NULL,
  `quantity_on_hand` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `theoretical_quantity` DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  `last_counted_at` DATETIME NULL,
  `last_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `inventories_branch_ingredient_unique` (`branch_id`, `ingredient_id`),
  KEY `inventories_restaurant_ingredient_index` (`restaurant_id`, `ingredient_id`),
  KEY `inventories_updated_by_index` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `ingredient_id` BIGINT UNSIGNED NOT NULL,
  `inventory_id` BIGINT UNSIGNED NULL,
  `order_id` BIGINT UNSIGNED NULL,
  `performed_by` BIGINT UNSIGNED NULL,
  `supplier_id` BIGINT UNSIGNED NULL,
  `type` ENUM('purchase', 'usage', 'adjustment', 'waste', 'return', 'stocktake') NOT NULL,
  `direction` ENUM('in', 'out') NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `unit_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reference_code` VARCHAR(100) NULL,
  `invoice_file_url` VARCHAR(2048) NULL,
  `notes` TEXT NULL,
  `occurred_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `inventory_transactions_restaurant_type_date_index` (`restaurant_id`, `type`, `occurred_at`),
  KEY `inventory_transactions_order_index` (`order_id`),
  KEY `inventory_transactions_ingredient_index` (`ingredient_id`),
  KEY `inventory_transactions_inventory_index` (`inventory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `products` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `category_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `image_url` VARCHAR(2048) NULL,
  `price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `preparation_time_minutes` INT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_available` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `track_inventory` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_restaurant_code_unique` (`restaurant_id`, `code`),
  UNIQUE KEY `products_restaurant_slug_unique` (`restaurant_id`, `slug`),
  KEY `products_restaurant_status_index` (`restaurant_id`, `is_active`, `is_available`),
  KEY `products_category_index` (`category_id`),
  KEY `products_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_recipes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `ingredient_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL,
  `waste_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_recipes_product_ingredient_unique` (`product_id`, `ingredient_id`),
  KEY `product_recipes_restaurant_product_index` (`restaurant_id`, `product_id`),
  KEY `product_recipes_unit_index` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `full_name` VARCHAR(255) NULL,
  `phone` VARCHAR(20) NULL,
  `email` VARCHAR(255) NULL,
  `gender` ENUM('male', 'female', 'other') NULL,
  `date_of_birth` DATE NULL,
  `notes` TEXT NULL,
  `loyalty_points` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_order_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customers_restaurant_phone_index` (`restaurant_id`, `phone`),
  KEY `customers_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `table_id` BIGINT UNSIGNED NULL,
  `customer_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `cashier_user_id` BIGINT UNSIGNED NULL,
  `order_number` VARCHAR(50) NOT NULL,
  `channel` ENUM('dine_in', 'takeaway', 'delivery', 'qr') NOT NULL DEFAULT 'dine_in',
  `status` ENUM('pending', 'confirmed', 'preparing', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid', 'partial', 'paid', 'refunded') NOT NULL DEFAULT 'unpaid',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `service_charge` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `note` TEXT NULL,
  `confirmed_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `cancelled_at` DATETIME NULL,
  `cancelled_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_restaurant_number_unique` (`restaurant_id`, `order_number`),
  KEY `orders_restaurant_status_created_index` (`restaurant_id`, `status`, `created_at`),
  KEY `orders_branch_table_index` (`branch_id`, `table_id`),
  KEY `orders_customer_index` (`customer_id`),
  KEY `orders_cashier_index` (`cashier_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `product_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'sent', 'preparing', 'served', 'cancelled') NOT NULL DEFAULT 'pending',
  `notes` VARCHAR(500) NULL,
  `sent_to_kitchen_at` DATETIME NULL,
  `prepared_at` DATETIME NULL,
  `served_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_status_index` (`order_id`, `status`),
  KEY `order_items_restaurant_product_index` (`restaurant_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `order_id` BIGINT UNSIGNED NOT NULL,
  `processed_by` BIGINT UNSIGNED NULL,
  `payment_method` ENUM('cash', 'bank_transfer', 'card', 'ewallet', 'mixed') NOT NULL DEFAULT 'cash',
  `status` ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cash_received` DECIMAL(12,2) NULL,
  `change_amount` DECIMAL(12,2) NULL,
  `transaction_code` VARCHAR(100) NULL,
  `paid_at` DATETIME NULL,
  `meta` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payments_restaurant_status_paid_index` (`restaurant_id`, `status`, `paid_at`),
  KEY `payments_order_index` (`order_id`),
  KEY `payments_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `work_shifts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_overnight` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `work_shifts_restaurant_code_unique` (`restaurant_id`, `code`),
  KEY `work_shifts_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schedule_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `shift_id` BIGINT UNSIGNED NOT NULL,
  `scheduled_date` DATE NOT NULL,
  `check_in_at` DATETIME NULL,
  `check_out_at` DATETIME NULL,
  `status` ENUM('scheduled', 'checked_in', 'completed', 'absent', 'leave_approved') NOT NULL DEFAULT 'scheduled',
  `notes` VARCHAR(500) NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schedule_assignments_unique` (`employee_id`, `shift_id`, `scheduled_date`),
  KEY `schedule_assignments_restaurant_date_status_index` (`restaurant_id`, `scheduled_date`, `status`),
  KEY `schedule_assignments_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `shift_closings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `shift_id` BIGINT UNSIGNED NOT NULL,
  `closing_date` DATE NOT NULL,
  `cashier_user_id` BIGINT UNSIGNED NULL,
  `confirmed_by` BIGINT UNSIGNED NULL,
  `expected_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `actual_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cash_difference` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `transfer_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `other_expense_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `notes` TEXT NULL,
  `status` ENUM('draft', 'submitted', 'confirmed', 'disputed') NOT NULL DEFAULT 'draft',
  `closed_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shift_closings_unique` (`restaurant_id`, `branch_id`, `shift_id`, `closing_date`),
  KEY `shift_closings_status_index` (`restaurant_id`, `status`, `closing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leave_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `requested_by` BIGINT UNSIGNED NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `leave_type` ENUM('annual', 'sick', 'unpaid', 'emergency', 'resignation') NOT NULL DEFAULT 'annual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `leave_requests_employee_index` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salaries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `pay_period_start` DATE NOT NULL,
  `pay_period_end` DATE NOT NULL,
  `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `bonus_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `deduction_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('draft', 'approved', 'paid') NOT NULL DEFAULT 'draft',
  `approved_by` BIGINT UNSIGNED NULL,
  `paid_at` DATETIME NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salaries_employee_period_unique` (`employee_id`, `pay_period_start`, `pay_period_end`),
  KEY `salaries_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `salaries_branch_index` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `salary_adjustments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_id` BIGINT UNSIGNED NOT NULL,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('bonus', 'penalty', 'cash_shortage', 'inventory_loss', 'violation') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(500) NULL,
  `reference_id` BIGINT UNSIGNED NULL,
  `reference_type` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_adjustments_salary_index` (`salary_id`),
  KEY `salary_adjustments_restaurant_type_index` (`restaurant_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `violation_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `reported_by` BIGINT UNSIGNED NULL,
  `violation_type` VARCHAR(100) NOT NULL,
  `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'low',
  `description` TEXT NOT NULL,
  `penalty_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `occurred_at` DATETIME NOT NULL,
  `status` ENUM('open', 'reviewed', 'resolved', 'dismissed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `violation_reports_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `violation_reports_employee_index` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_feedback` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `order_id` BIGINT UNSIGNED NULL,
  `customer_id` BIGINT UNSIGNED NULL,
  `submitted_by_name` VARCHAR(255) NULL,
  `submitted_by_phone` VARCHAR(20) NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `content` TEXT NULL,
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('new', 'reviewed', 'resolved') NOT NULL DEFAULT 'new',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_feedback_restaurant_status_index` (`restaurant_id`, `status`),
  KEY `customer_feedback_order_index` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurant_id` BIGINT UNSIGNED NULL,
  `branch_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `user_role` VARCHAR(100) NULL,
  `action` VARCHAR(100) NOT NULL,
  `subject_type` VARCHAR(150) NULL,
  `subject_id` BIGINT UNSIGNED NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_restaurant_action_created_index` (`restaurant_id`, `action`, `created_at`),
  KEY `audit_logs_subject_index` (`subject_type`, `subject_id`),
  KEY `audit_logs_user_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `guard_name` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`, `model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `model_has_roles` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `model_type` VARCHAR(255) NOT NULL,
  `model_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `model_id`, `model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`, `model_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_has_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`permission_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` VARCHAR(255) NOT NULL,
  `value` MEDIUMTEXT NOT NULL,
  `expiration` BIGINT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` VARCHAR(255) NOT NULL,
  `owner` VARCHAR(255) NOT NULL,
  `expiration` BIGINT NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `attempts` SMALLINT UNSIGNED NOT NULL,
  `reserved_at` INT UNSIGNED NULL,
  `available_at` INT UNSIGNED NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `total_jobs` INT NOT NULL,
  `pending_jobs` INT NOT NULL,
  `failed_jobs` INT NOT NULL,
  `failed_job_ids` LONGTEXT NOT NULL,
  `options` MEDIUMTEXT NULL,
  `cancelled_at` INT NULL,
  `created_at` INT NOT NULL,
  `finished_at` INT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` VARCHAR(255) NOT NULL,
  `connection` TEXT NOT NULL,
  `queue` TEXT NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `exception` LONGTEXT NOT NULL,
  `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pulse_values` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` INT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `key` MEDIUMTEXT NOT NULL,
  `key_hash` BINARY(16) GENERATED ALWAYS AS (UNHEX(MD5(`key`))) VIRTUAL,
  `value` MEDIUMTEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_values_type_key_hash_unique` (`type`, `key_hash`),
  KEY `pulse_values_timestamp_index` (`timestamp`),
  KEY `pulse_values_type_index` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pulse_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `timestamp` INT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `key` MEDIUMTEXT NOT NULL,
  `key_hash` BINARY(16) GENERATED ALWAYS AS (UNHEX(MD5(`key`))) VIRTUAL,
  `value` BIGINT NULL,
  PRIMARY KEY (`id`),
  KEY `pulse_entries_timestamp_index` (`timestamp`),
  KEY `pulse_entries_type_index` (`type`),
  KEY `pulse_entries_key_hash_index` (`key_hash`),
  KEY `pulse_entries_compound_index` (`timestamp`, `type`, `key_hash`, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pulse_aggregates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket` INT UNSIGNED NOT NULL,
  `period` MEDIUMINT UNSIGNED NOT NULL,
  `type` VARCHAR(255) NOT NULL,
  `key` MEDIUMTEXT NOT NULL,
  `key_hash` BINARY(16) GENERATED ALWAYS AS (UNHEX(MD5(`key`))) VIRTUAL,
  `aggregate` VARCHAR(255) NOT NULL,
  `value` DECIMAL(20,2) NOT NULL,
  `count` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pulse_aggregates_unique` (`bucket`, `period`, `type`, `aggregate`, `key_hash`),
  KEY `pulse_aggregates_period_bucket_index` (`period`, `bucket`),
  KEY `pulse_aggregates_type_index` (`type`),
  KEY `pulse_aggregates_query_index` (`period`, `type`, `aggregate`, `bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `restaurants`
  ADD CONSTRAINT `restaurants_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restaurants_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `restaurant_subscriptions`
  ADD CONSTRAINT `restaurant_subscriptions_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT;

ALTER TABLE `restaurant_branches`
  ADD CONSTRAINT `restaurant_branches_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_branches_manager_user_id_foreign` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `restaurant_settings`
  ADD CONSTRAINT `restaurant_settings_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_settings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE;

ALTER TABLE `users`
  ADD CONSTRAINT `users_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `employees`
  ADD CONSTRAINT `employees_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employees_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `areas`
  ADD CONSTRAINT `areas_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `areas_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE;

ALTER TABLE `restaurant_tables`
  ADD CONSTRAINT `restaurant_tables_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_tables_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_tables_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_categories_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `suppliers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `ingredients`
  ADD CONSTRAINT `ingredients_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ingredients_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ingredients_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ingredients_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT;

ALTER TABLE `inventories`
  ADD CONSTRAINT `inventories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventories_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventories_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_inventory_id_foreign` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

ALTER TABLE `products`
  ADD CONSTRAINT `products_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `product_recipes`
  ADD CONSTRAINT `product_recipes_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recipes_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recipes_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `product_recipes_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT;

ALTER TABLE `customers`
  ADD CONSTRAINT `customers_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_table_id_foreign` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_cashier_user_id_foreign` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;

ALTER TABLE `payments`
  ADD CONSTRAINT `payments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `work_shifts`
  ADD CONSTRAINT `work_shifts_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_shifts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL;

ALTER TABLE `schedule_assignments`
  ADD CONSTRAINT `schedule_assignments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_assignments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_assignments_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `work_shifts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_assignments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `shift_closings`
  ADD CONSTRAINT `shift_closings_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_closings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shift_closings_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `work_shifts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_closings_cashier_user_id_foreign` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shift_closings_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `salaries`
  ADD CONSTRAINT `salaries_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salaries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salaries_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `salary_adjustments`
  ADD CONSTRAINT `salary_adjustments_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_adjustments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_adjustments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

ALTER TABLE `violation_reports`
  ADD CONSTRAINT `violation_reports_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violation_reports_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `violation_reports_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violation_reports_reported_by_foreign` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `customer_feedback`
  ADD CONSTRAINT `customer_feedback_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_feedback_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL;

ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_logs_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

INSERT INTO `subscription_plans`
  (`id`, `code`, `name`, `price`, `billing_cycle`, `max_branches`, `max_tables`, `max_users`, `features`, `status`, `created_at`, `updated_at`)
VALUES
  (1, 'free', 'Free', 0.00, 'monthly', 1, 10, 5, JSON_OBJECT('analytics', false, 'multi_branch', false, 'ai_prediction', false), 'active', NOW(), NOW()),
  (2, 'pro', 'Pro', 499000.00, 'monthly', NULL, NULL, NULL, JSON_OBJECT('analytics', true, 'multi_branch', true, 'ai_prediction', true), 'active', NOW(), NOW());

INSERT INTO `permissions`
  (`id`, `name`, `guard_name`, `created_at`, `updated_at`)
VALUES
  (1, 'manage_tenants', 'web', NOW(), NOW()),
  (2, 'manage_restaurants', 'web', NOW(), NOW()),
  (3, 'manage_staff', 'web', NOW(), NOW()),
  (4, 'manage_menu', 'web', NOW(), NOW()),
  (5, 'manage_inventory', 'web', NOW(), NOW()),
  (6, 'create_order', 'web', NOW(), NOW()),
  (7, 'view_order', 'web', NOW(), NOW()),
  (8, 'payment_order', 'web', NOW(), NOW()),
  (9, 'view_kitchen_order', 'web', NOW(), NOW()),
  (10, 'update_food_status', 'web', NOW(), NOW()),
  (11, 'view_report', 'web', NOW(), NOW()),
  (12, 'manage_salary', 'web', NOW(), NOW()),
  (13, 'manage_schedule', 'web', NOW(), NOW()),
  (14, 'manage_feedback', 'web', NOW(), NOW()),
  (15, 'view_audit_log', 'web', NOW(), NOW());

INSERT INTO `roles`
  (`id`, `name`, `guard_name`, `created_at`, `updated_at`)
VALUES
  (1, 'super_admin', 'web', NOW(), NOW()),
  (2, 'owner', 'web', NOW(), NOW()),
  (3, 'manager', 'web', NOW(), NOW()),
  (4, 'cashier', 'web', NOW(), NOW()),
  (5, 'kitchen', 'web', NOW(), NOW()),
  (6, 'inventory_staff', 'web', NOW(), NOW()),
  (7, 'customer', 'web', NOW(), NOW());

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
  (1, 1), (2, 1), (3, 1), (4, 1), (5, 1), (6, 1), (7, 1), (8, 1), (9, 1), (10, 1), (11, 1), (12, 1), (13, 1), (14, 1), (15, 1),
  (2, 2), (3, 2), (4, 2), (5, 2), (6, 2), (7, 2), (8, 2), (9, 2), (10, 2), (11, 2), (12, 2), (13, 2), (14, 2), (15, 2),
  (3, 3), (4, 3), (5, 3), (6, 3), (7, 3), (8, 3), (9, 3), (10, 3), (11, 3), (12, 3), (13, 3), (14, 3), (15, 3),
  (6, 4), (7, 4), (8, 4), (11, 4),
  (7, 5), (9, 5), (10, 5),
  (5, 6), (7, 6),
  (7, 7);

SET FOREIGN_KEY_CHECKS = 1;
