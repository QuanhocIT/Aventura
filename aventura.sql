-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 23, 2026 at 04:45 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aventura`
--

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `user_role` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` enum('created','updated','deleted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint UNSIGNED DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_adjustments`
--

CREATE TABLE `billing_adjustments` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `restaurant_subscription_id` bigint UNSIGNED DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `days` int NOT NULL DEFAULT '0',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `coupon_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_invoices`
--

CREATE TABLE `billing_invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `restaurant_subscription_id` bigint UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'payment_success',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `issued_on` date DEFAULT NULL,
  `due_on` date DEFAULT NULL,
  `pdf_path` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `excel_path` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('aventura-cache-dcacbc44a7737aebdda0ba04d9733cb1', 'i:1;', 1779510923),
('aventura-cache-dcacbc44a7737aebdda0ba04d9733cb1:timer', 'i:1779510923;', 1779510923);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `loyalty_points` int UNSIGNED NOT NULL DEFAULT '0',
  `last_order_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_feedback`
--

CREATE TABLE `customer_feedback` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `order_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `submitted_by_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_by_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('new','reviewed','resolved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizen_id_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizen_id_front_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizen_id_back_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_type` enum('full_time','part_time','seasonal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_time',
  `job_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive','terminated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ingredients`
--

CREATE TABLE `ingredients` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `supplier_id` bigint UNSIGNED DEFAULT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `min_stock_level` decimal(12,3) NOT NULL DEFAULT '0.000',
  `reorder_level` decimal(12,3) NOT NULL DEFAULT '0.000',
  `average_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventories`
--

CREATE TABLE `inventories` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `ingredient_id` bigint UNSIGNED NOT NULL,
  `quantity_on_hand` decimal(12,3) NOT NULL DEFAULT '0.000',
  `theoretical_quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `last_counted_at` datetime DEFAULT NULL,
  `last_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_reservations`
--

CREATE TABLE `inventory_reservations` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `ingredient_id` bigint UNSIGNED NOT NULL,
  `reserved_quantity` decimal(12,3) NOT NULL DEFAULT '0.000',
  `status` enum('holding','committed','released') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'holding',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `ingredient_id` bigint UNSIGNED NOT NULL,
  `inventory_id` bigint UNSIGNED DEFAULT NULL,
  `order_id` bigint UNSIGNED DEFAULT NULL,
  `performed_by` bigint UNSIGNED DEFAULT NULL,
  `supplier_id` bigint UNSIGNED DEFAULT NULL,
  `type` enum('purchase','usage','adjustment','waste','return','stocktake') COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reference_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_file_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `occurred_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `requested_by` bigint UNSIGNED DEFAULT NULL,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `leave_type` enum('annual','sick','unpaid','emergency','resignation') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_assets`
--

CREATE TABLE `media_assets` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `uploaded_by` bigint UNSIGNED DEFAULT NULL,
  `attachable_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachable_id` bigint UNSIGNED DEFAULT NULL,
  `collection` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `media_type` enum('image','document','video','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `disk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint UNSIGNED NOT NULL DEFAULT '0',
  `width` int UNSIGNED DEFAULT NULL,
  `height` int UNSIGNED DEFAULT NULL,
  `checksum_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT '1',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2025_08_14_170933_add_two_factor_columns_to_users_table', 1),
(5, '2026_05_14_151320_create_permission_tables', 1),
(6, '2026_05_14_151321_create_pulse_tables', 1),
(7, '2026_05_15_041600_add_restaurant_columns_to_users_table', 1),
(8, '2026_05_15_041700_create_subscription_plans_table', 1),
(9, '2026_05_15_041710_create_restaurants_table', 1),
(10, '2026_05_15_041720_create_restaurant_subscriptions_table', 1),
(11, '2026_05_15_041730_create_restaurant_branches_table', 1),
(12, '2026_05_15_041740_create_restaurant_settings_table', 1),
(13, '2026_05_15_041800_create_employees_table', 1),
(14, '2026_05_15_041900_create_restaurant_layout_tables', 1),
(15, '2026_05_15_042000_create_inventory_catalog_tables', 1),
(16, '2026_05_15_042100_create_order_tables', 1),
(17, '2026_05_15_042200_create_shift_and_salary_tables', 1),
(18, '2026_05_15_042300_create_feedback_and_audit_tables', 1),
(19, '2026_05_15_042400_add_domain_foreign_keys', 1),
(20, '2026_05_15_042500_seed_default_roles_and_permissions', 1),
(21, '2026_05_15_043000_create_media_assets_table', 1),
(22, '2026_05_15_043010_add_soft_deletes_to_domain_tables', 1),
(23, '2026_05_15_043020_create_restaurant_revenue_summaries_table', 1),
(24, '2026_05_18_090000_add_event_to_audit_logs_table', 1),
(25, '2026_05_18_103000_apply_pending_schema_fixes', 1),
(26, '2026_05_20_131500_add_google_id_to_users_table', 1),
(27, '2026_05_22_030000_create_billing_tables', 1);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\User', 8);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `table_id` bigint UNSIGNED DEFAULT NULL,
  `customer_id` bigint UNSIGNED DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `cashier_user_id` bigint UNSIGNED DEFAULT NULL,
  `order_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('dine_in','takeaway','delivery','qr') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dine_in',
  `status` enum('pending','confirmed','preparing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','partial','paid','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `service_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `note` text COLLATE utf8mb4_unicode_ci,
  `confirmed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','sent','preparing','served','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_to_kitchen_at` datetime DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `order_id` bigint UNSIGNED NOT NULL,
  `processed_by` bigint UNSIGNED DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','card','ewallet','mixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `status` enum('pending','paid','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cash_received` decimal(12,2) DEFAULT NULL,
  `change_amount` decimal(12,2) DEFAULT NULL,
  `transaction_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_transaction_code` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhooks`
--

CREATE TABLE `payment_webhooks` (
  `id` bigint UNSIGNED NOT NULL,
  `provider` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `headers` json DEFAULT NULL,
  `payload` json NOT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'manage_tenants', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(2, 'manage_restaurants', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(3, 'manage_staff', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(4, 'manage_menu', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(5, 'manage_inventory', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(6, 'create_order', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(7, 'view_order', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(8, 'payment_order', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(9, 'view_kitchen_order', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(10, 'update_food_status', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(11, 'view_report', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(12, 'manage_salary', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(13, 'manage_schedule', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(14, 'manage_feedback', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(15, 'view_audit_log', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `preparation_time_minutes` int UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `track_inventory` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `display_order` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_recipes`
--

CREATE TABLE `product_recipes` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `ingredient_id` bigint UNSIGNED NOT NULL,
  `unit_id` bigint UNSIGNED NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `waste_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pulse_aggregates`
--

CREATE TABLE `pulse_aggregates` (
  `id` bigint UNSIGNED NOT NULL,
  `bucket` int UNSIGNED NOT NULL,
  `period` mediumint UNSIGNED NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `aggregate` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(20,2) NOT NULL,
  `count` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pulse_aggregates`
--

INSERT INTO `pulse_aggregates` (`id`, `bucket`, `period`, `type`, `key`, `aggregate`, `value`, `count`) VALUES
(17, 1779413940, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(18, 1779413760, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(19, 1779413760, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 5.00, NULL),
(20, 1779412320, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 33.00, NULL),
(21, 1779413940, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 9.00, NULL),
(22, 1779413760, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 10.00, NULL),
(23, 1779413760, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 24.00, NULL),
(24, 1779412320, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 80.00, NULL),
(41, 1779414000, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(42, 1779414000, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1:timer', 'count', 2.00, NULL),
(43, 1779413760, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1:timer', 'count', 2.00, NULL),
(44, 1779413760, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1:timer', 'count', 2.00, NULL),
(45, 1779412320, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1:timer', 'count', 2.00, NULL),
(49, 1779414060, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(50, 1779413760, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(51, 1779413760, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(52, 1779412320, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 5.00, NULL),
(53, 1779414060, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1072.00, NULL),
(54, 1779413760, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1072.00, NULL),
(55, 1779413760, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1072.00, NULL),
(56, 1779412320, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2640.00, NULL),
(57, 1779414120, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(58, 1779414120, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(59, 1779414120, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 7.00, NULL),
(60, 1779414120, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 8.00, NULL),
(77, 1779414240, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(78, 1779414240, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(85, 1779414720, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(86, 1779414480, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(87, 1779414720, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(88, 1779414480, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 5.00, NULL),
(97, 1779414780, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(101, 1779415020, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(102, 1779414840, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(103, 1779413760, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(104, 1779412320, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 2.00, NULL),
(105, 1779415020, 60, 'slow_user_request', '7', 'count', 1.00, NULL),
(106, 1779414840, 360, 'slow_user_request', '7', 'count', 1.00, NULL),
(107, 1779413760, 1440, 'slow_user_request', '7', 'count', 1.00, NULL),
(108, 1779412320, 10080, 'slow_user_request', '7', 'count', 2.00, NULL),
(109, 1779415020, 60, 'user_request', '7', 'count', 2.00, NULL),
(110, 1779414840, 360, 'user_request', '7', 'count', 3.00, NULL),
(111, 1779413760, 1440, 'user_request', '7', 'count', 3.00, NULL),
(112, 1779412320, 10080, 'user_request', '7', 'count', 9.00, NULL),
(113, 1779415020, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2061.00, NULL),
(114, 1779414840, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2061.00, NULL),
(115, 1779413760, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2061.00, NULL),
(116, 1779412320, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2061.00, NULL),
(121, 1779415080, 60, 'user_request', '7', 'count', 1.00, NULL),
(125, 1779415140, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(126, 1779414840, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(127, 1779413760, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(128, 1779412320, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 12.00, NULL),
(129, 1779415140, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(130, 1779414840, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(131, 1779415140, 60, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(132, 1779414840, 360, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(133, 1779413760, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(134, 1779412320, 10080, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 3.00, NULL),
(135, 1779415140, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(136, 1779414840, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(141, 1779415140, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 15158.00, NULL),
(142, 1779414840, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 15158.00, NULL),
(143, 1779413760, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 15158.00, NULL),
(144, 1779412320, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 15158.00, NULL),
(145, 1779415140, 60, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415184.00, NULL),
(146, 1779414840, 360, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415184.00, NULL),
(147, 1779413760, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415184.00, NULL),
(148, 1779412320, 10080, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415705.00, NULL),
(149, 1779415320, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(150, 1779415200, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(151, 1779415200, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(152, 1779415320, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(153, 1779415200, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(154, 1779415200, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 14.00, NULL),
(157, 1779415500, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(158, 1779415500, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(165, 1779415560, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(166, 1779415560, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 4.00, NULL),
(167, 1779415200, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 6.00, NULL),
(168, 1779415560, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(169, 1779415560, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(170, 1779415560, 60, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(171, 1779415560, 360, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 2.00, NULL),
(172, 1779415200, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 2.00, NULL),
(177, 1779415560, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 3603.00, NULL),
(178, 1779415560, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 3939.00, NULL),
(179, 1779415200, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 3939.00, NULL),
(180, 1779415560, 60, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415577.00, NULL),
(181, 1779415560, 360, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415705.00, NULL),
(182, 1779415200, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415705.00, NULL),
(185, 1779415680, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(186, 1779415680, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(187, 1779415560, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(188, 1779415680, 60, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(189, 1779415680, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(201, 1779415680, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 3939.00, NULL),
(202, 1779415680, 60, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779415705.00, NULL),
(209, 1779415800, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 2.00, NULL),
(210, 1779415800, 60, 'slow_user_request', '8', 'count', 2.00, NULL),
(211, 1779415560, 360, 'slow_user_request', '8', 'count', 2.00, NULL),
(212, 1779415200, 1440, 'slow_user_request', '8', 'count', 4.00, NULL),
(213, 1779412320, 10080, 'slow_user_request', '8', 'count', 11.00, NULL),
(214, 1779415800, 60, 'user_request', '8', 'count', 6.00, NULL),
(215, 1779415560, 360, 'user_request', '8', 'count', 6.00, NULL),
(216, 1779415200, 1440, 'user_request', '8', 'count', 25.00, NULL),
(217, 1779412320, 10080, 'user_request', '8', 'count', 220.00, NULL),
(218, 1779415800, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(219, 1779415800, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(229, 1779415800, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1511.00, NULL),
(269, 1779415860, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(270, 1779415560, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(271, 1779415200, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(272, 1779415860, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(273, 1779415560, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(274, 1779415200, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(275, 1779412320, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 2.00, NULL),
(277, 1779415860, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2170.00, NULL),
(278, 1779415560, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2170.00, NULL),
(279, 1779415200, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2170.00, NULL),
(280, 1779415860, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 1692.00, NULL),
(281, 1779415560, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 1692.00, NULL),
(282, 1779415200, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 1692.00, NULL),
(283, 1779412320, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 2107.00, NULL),
(285, 1779415920, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(286, 1779415920, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(287, 1779415920, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(288, 1779415920, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(289, 1779415920, 60, 'user_request', '8', 'count', 7.00, NULL),
(290, 1779415920, 360, 'user_request', '8', 'count', 11.00, NULL),
(291, 1779415920, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(292, 1779415920, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(293, 1779415920, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 5.00, NULL),
(294, 1779415920, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 5.00, NULL),
(305, 1779415920, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1442.00, NULL),
(306, 1779415920, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1442.00, NULL),
(341, 1779416220, 60, 'user_request', '8', 'count', 4.00, NULL),
(357, 1779416280, 60, 'user_request', '8', 'count', 4.00, NULL),
(358, 1779416280, 360, 'user_request', '8', 'count', 8.00, NULL),
(373, 1779416340, 60, 'user_request', '8', 'count', 1.00, NULL),
(377, 1779416520, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(378, 1779416280, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(379, 1779416520, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(380, 1779416280, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(381, 1779416520, 60, 'user_request', '8', 'count', 3.00, NULL),
(382, 1779416520, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(383, 1779416280, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(384, 1779416520, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(385, 1779416280, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(397, 1779416520, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1648.00, NULL),
(398, 1779416280, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1648.00, NULL),
(409, 1779416700, 60, 'user_request', '8', 'count', 4.00, NULL),
(410, 1779416640, 360, 'user_request', '8', 'count', 22.00, NULL),
(411, 1779416640, 1440, 'user_request', '8', 'count', 64.00, NULL),
(413, 1779416700, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(414, 1779416640, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(415, 1779416640, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(416, 1779416700, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(417, 1779416640, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(418, 1779416640, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 8.00, NULL),
(433, 1779416760, 60, 'user_request', '8', 'count', 4.00, NULL),
(449, 1779416820, 60, 'user_request', '8', 'count', 11.00, NULL),
(477, 1779416820, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 2.00, NULL),
(478, 1779416640, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 2.00, NULL),
(479, 1779416640, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 4.00, NULL),
(480, 1779416820, 60, 'slow_user_request', '8', 'count', 2.00, NULL),
(481, 1779416640, 360, 'slow_user_request', '8', 'count', 2.00, NULL),
(482, 1779416640, 1440, 'slow_user_request', '8', 'count', 4.00, NULL),
(483, 1779416820, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(484, 1779416820, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(497, 1779416820, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1255.00, NULL),
(498, 1779416640, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1255.00, NULL),
(499, 1779416640, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1287.00, NULL),
(529, 1779416880, 60, 'user_request', '8', 'count', 1.00, NULL),
(533, 1779416940, 60, 'user_request', '8', 'count', 2.00, NULL),
(541, 1779417060, 60, 'user_request', '8', 'count', 8.00, NULL),
(542, 1779417000, 360, 'user_request', '8', 'count', 20.00, NULL),
(561, 1779417060, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(562, 1779417000, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(563, 1779417060, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(564, 1779417000, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(565, 1779417060, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(566, 1779417000, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(567, 1779417060, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(568, 1779417000, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(581, 1779417060, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1287.00, NULL),
(582, 1779417000, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1287.00, NULL),
(593, 1779417120, 60, 'user_request', '8', 'count', 2.00, NULL),
(601, 1779417180, 60, 'user_request', '8', 'count', 1.00, NULL),
(605, 1779417240, 60, 'user_request', '8', 'count', 3.00, NULL),
(617, 1779417300, 60, 'user_request', '8', 'count', 6.00, NULL),
(641, 1779417360, 60, 'user_request', '8', 'count', 4.00, NULL),
(642, 1779417360, 360, 'user_request', '8', 'count', 12.00, NULL),
(649, 1779417360, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(650, 1779417360, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(651, 1779417360, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(652, 1779417360, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(653, 1779417360, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(654, 1779417360, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(655, 1779417360, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(656, 1779417360, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(669, 1779417360, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1211.00, NULL),
(670, 1779417360, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1211.00, NULL),
(677, 1779417480, 60, 'user_request', '8', 'count', 5.00, NULL),
(678, 1779417480, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(679, 1779417480, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(705, 1779417540, 60, 'user_request', '8', 'count', 1.00, NULL),
(709, 1779417600, 60, 'user_request', '8', 'count', 2.00, NULL),
(717, 1779417720, 60, 'user_request', '8', 'count', 1.00, NULL),
(718, 1779417720, 360, 'user_request', '8', 'count', 10.00, NULL),
(721, 1779417840, 60, 'user_request', '8', 'count', 1.00, NULL),
(725, 1779417900, 60, 'user_request', '8', 'count', 4.00, NULL),
(729, 1779417900, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(730, 1779417720, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(731, 1779417900, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(732, 1779417720, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(749, 1779417960, 60, 'user_request', '8', 'count', 4.00, NULL),
(765, 1779418020, 60, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(766, 1779417720, 360, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(767, 1779416640, 1440, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(768, 1779412320, 10080, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 6.00, NULL),
(769, 1779418020, 60, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779418037.00, NULL),
(770, 1779417720, 360, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779418037.00, NULL),
(771, 1779416640, 1440, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779418037.00, NULL),
(772, 1779412320, 10080, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779422018.00, NULL),
(773, 1779418080, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(774, 1779418080, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(775, 1779418080, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(776, 1779418080, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(777, 1779418080, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(778, 1779418080, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(781, 1779418080, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2640.00, NULL),
(782, 1779418080, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2640.00, NULL),
(783, 1779418080, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2640.00, NULL),
(784, 1779418080, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 2107.00, NULL),
(785, 1779418080, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 2107.00, NULL),
(786, 1779418080, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 'max', 2107.00, NULL),
(789, 1779418080, 60, 'user_request', '8', 'count', 9.00, NULL),
(790, 1779418080, 360, 'user_request', '8', 'count', 12.00, NULL),
(791, 1779418080, 1440, 'user_request', '8', 'count', 66.00, NULL),
(792, 1779418080, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(793, 1779418080, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(794, 1779418080, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 7.00, NULL),
(795, 1779418080, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(796, 1779418080, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(797, 1779418080, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 9.00, NULL),
(833, 1779418080, 60, 'user_request', '7', 'count', 2.00, NULL),
(834, 1779418080, 360, 'user_request', '7', 'count', 3.00, NULL),
(835, 1779418080, 1440, 'user_request', '7', 'count', 3.00, NULL),
(841, 1779418260, 60, 'user_request', '7', 'count', 1.00, NULL),
(845, 1779418320, 60, 'user_request', '8', 'count', 3.00, NULL),
(846, 1779418320, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(847, 1779418320, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(865, 1779418560, 60, 'user_request', '8', 'count', 2.00, NULL),
(866, 1779418440, 360, 'user_request', '8', 'count', 16.00, NULL),
(873, 1779418620, 60, 'user_request', '8', 'count', 8.00, NULL),
(893, 1779418620, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(894, 1779418440, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(895, 1779418620, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(896, 1779418440, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(913, 1779418680, 60, 'user_request', '8', 'count', 5.00, NULL),
(925, 1779418680, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(926, 1779418680, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(941, 1779418740, 60, 'user_request', '8', 'count', 1.00, NULL),
(945, 1779418860, 60, 'user_request', '8', 'count', 3.00, NULL),
(946, 1779418800, 360, 'user_request', '8', 'count', 15.00, NULL),
(957, 1779418980, 60, 'user_request', '8', 'count', 5.00, NULL),
(965, 1779418980, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(966, 1779418800, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(967, 1779418980, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(968, 1779418800, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(985, 1779419100, 60, 'user_request', '8', 'count', 7.00, NULL),
(1013, 1779419160, 60, 'user_request', '8', 'count', 3.00, NULL),
(1014, 1779419160, 360, 'user_request', '8', 'count', 23.00, NULL),
(1017, 1779419160, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1018, 1779419160, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1019, 1779419160, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1020, 1779419160, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(1033, 1779419220, 60, 'user_request', '8', 'count', 5.00, NULL),
(1053, 1779419280, 60, 'user_request', '8', 'count', 1.00, NULL),
(1057, 1779419340, 60, 'user_request', '8', 'count', 10.00, NULL),
(1069, 1779419340, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1070, 1779419340, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1109, 1779419460, 60, 'user_request', '8', 'count', 4.00, NULL),
(1125, 1779419520, 60, 'user_request', '8', 'count', 9.00, NULL),
(1126, 1779419520, 360, 'user_request', '8', 'count', 9.00, NULL),
(1127, 1779419520, 1440, 'user_request', '8', 'count', 17.00, NULL),
(1141, 1779419520, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1142, 1779419520, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1143, 1779419520, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1144, 1779419520, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1145, 1779419520, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1146, 1779419520, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(1173, 1779419880, 60, 'user_request', '8', 'count', 1.00, NULL),
(1174, 1779419880, 360, 'user_request', '8', 'count', 1.00, NULL),
(1177, 1779420420, 60, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', 1.00, NULL),
(1178, 1779420240, 360, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', 1.00, NULL),
(1179, 1779419520, 1440, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', 1.00, NULL),
(1180, 1779412320, 10080, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', 1.00, NULL),
(1181, 1779420420, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(1182, 1779420240, 360, 'slow_user_request', '8', 'count', 2.00, NULL),
(1183, 1779419520, 1440, 'slow_user_request', '8', 'count', 2.00, NULL),
(1184, 1779420420, 60, 'user_request', '8', 'count', 2.00, NULL),
(1185, 1779420240, 360, 'user_request', '8', 'count', 6.00, NULL),
(1189, 1779420420, 60, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', 14823.00, NULL),
(1190, 1779420240, 360, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', 14823.00, NULL),
(1191, 1779419520, 1440, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', 14823.00, NULL),
(1192, 1779412320, 10080, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', 14823.00, NULL),
(1197, 1779420480, 60, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(1198, 1779420240, 360, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(1199, 1779419520, 1440, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(1201, 1779420480, 60, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779420491.00, NULL),
(1202, 1779420240, 360, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779420491.00, NULL),
(1203, 1779419520, 1440, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779420491.00, NULL),
(1205, 1779420480, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1206, 1779420240, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1207, 1779419520, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1209, 1779420480, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1037.00, NULL),
(1210, 1779420240, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1037.00, NULL),
(1211, 1779419520, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1037.00, NULL),
(1213, 1779420540, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1214, 1779420240, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1215, 1779419520, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1216, 1779420540, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(1217, 1779420540, 60, 'user_request', '8', 'count', 4.00, NULL),
(1218, 1779420540, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1219, 1779420240, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1220, 1779420540, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1221, 1779420240, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1233, 1779420540, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1701.00, NULL),
(1234, 1779420240, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1701.00, NULL),
(1235, 1779419520, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1701.00, NULL),
(1253, 1779420660, 60, 'user_request', '8', 'count', 1.00, NULL),
(1254, 1779420600, 360, 'user_request', '8', 'count', 1.00, NULL),
(1257, 1779421260, 60, 'user_request', '8', 'count', 7.00, NULL),
(1258, 1779420960, 360, 'user_request', '8', 'count', 7.00, NULL),
(1259, 1779420960, 1440, 'user_request', '8', 'count', 48.00, NULL),
(1277, 1779421260, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1278, 1779420960, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1279, 1779420960, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 7.00, NULL),
(1280, 1779421260, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1281, 1779420960, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1282, 1779420960, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 19.00, NULL),
(1293, 1779421320, 60, 'user_request', '8', 'count', 5.00, NULL),
(1294, 1779421320, 360, 'user_request', '8', 'count', 31.00, NULL),
(1297, 1779421320, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1298, 1779421320, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 14.00, NULL),
(1317, 1779421380, 60, 'user_request', '8', 'count', 5.00, NULL),
(1321, 1779421380, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1322, 1779421320, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(1323, 1779421380, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1349, 1779421440, 60, 'user_request', '8', 'count', 7.00, NULL),
(1365, 1779421440, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1366, 1779421440, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1385, 1779421500, 60, 'user_request', '8', 'count', 8.00, NULL),
(1389, 1779421500, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 5.00, NULL),
(1417, 1779421500, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1433, 1779421560, 60, 'user_request', '8', 'count', 2.00, NULL),
(1434, 1779421560, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1445, 1779421620, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1446, 1779421320, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1447, 1779420960, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1448, 1779421620, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(1449, 1779421320, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(1450, 1779420960, 1440, 'slow_user_request', '8', 'count', 1.00, NULL),
(1451, 1779421620, 60, 'user_request', '8', 'count', 4.00, NULL),
(1457, 1779421620, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1339.00, NULL),
(1458, 1779421320, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1339.00, NULL),
(1459, 1779420960, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1339.00, NULL),
(1465, 1779421620, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1466, 1779421320, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1467, 1779420960, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1468, 1779421620, 60, 'slow_user_request', '7', 'count', 1.00, NULL),
(1469, 1779421320, 360, 'slow_user_request', '7', 'count', 1.00, NULL),
(1470, 1779420960, 1440, 'slow_user_request', '7', 'count', 1.00, NULL),
(1471, 1779421620, 60, 'user_request', '7', 'count', 3.00, NULL),
(1472, 1779421320, 360, 'user_request', '7', 'count', 3.00, NULL),
(1473, 1779420960, 1440, 'user_request', '7', 'count', 3.00, NULL),
(1477, 1779421620, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1050.00, NULL),
(1478, 1779421320, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1050.00, NULL),
(1479, 1779420960, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1050.00, NULL),
(1489, 1779421620, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1490, 1779421620, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1505, 1779421980, 60, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(1506, 1779421680, 360, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(1507, 1779420960, 1440, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'count', 2.00, NULL),
(1509, 1779421980, 60, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779422018.00, NULL),
(1510, 1779421680, 360, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779422018.00, NULL),
(1511, 1779420960, 1440, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 'max', 1779422018.00, NULL),
(1513, 1779422040, 60, 'user_request', '8', 'count', 6.00, NULL),
(1514, 1779422040, 360, 'user_request', '8', 'count', 10.00, NULL),
(1521, 1779422040, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1522, 1779422040, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1523, 1779422040, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1524, 1779422040, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(1549, 1779422160, 60, 'user_request', '8', 'count', 3.00, NULL),
(1557, 1779422160, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1558, 1779422220, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1569, 1779422220, 60, 'user_request', '8', 'count', 1.00, NULL),
(1573, 1779422520, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1574, 1779422400, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1575, 1779422400, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1576, 1779422400, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1577, 1779422520, 60, 'slow_user_request', '8', 'count', 3.00, NULL),
(1578, 1779422400, 360, 'slow_user_request', '8', 'count', 3.00, NULL),
(1579, 1779422400, 1440, 'slow_user_request', '8', 'count', 4.00, NULL),
(1580, 1779422400, 10080, 'slow_user_request', '8', 'count', 4.00, NULL),
(1581, 1779422520, 60, 'user_request', '8', 'count', 8.00, NULL),
(1582, 1779422400, 360, 'user_request', '8', 'count', 17.00, NULL),
(1583, 1779422400, 1440, 'user_request', '8', 'count', 26.00, NULL),
(1584, 1779422400, 10080, 'user_request', '8', 'count', 30.00, NULL),
(1585, 1779422520, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 5037.00, NULL),
(1586, 1779422400, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 5037.00, NULL),
(1587, 1779422400, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 5037.00, NULL),
(1588, 1779422400, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 5037.00, NULL),
(1589, 1779422520, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1590, 1779422400, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1591, 1779422400, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1592, 1779422400, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1593, 1779422520, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1594, 1779422400, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 5.00, NULL),
(1595, 1779422400, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(1596, 1779422400, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(1601, 1779422520, 60, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1602, 1779422400, 360, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1603, 1779422400, 1440, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1604, 1779422400, 10080, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1613, 1779422520, 60, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', 1328.00, NULL),
(1614, 1779422400, 360, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', 1328.00, NULL),
(1615, 1779422400, 1440, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', 1328.00, NULL),
(1616, 1779422400, 10080, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', 1328.00, NULL),
(1625, 1779422520, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1626, 1779422400, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1627, 1779422400, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1628, 1779422400, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1641, 1779422520, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1519.00, NULL),
(1642, 1779422400, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1519.00, NULL),
(1643, 1779422400, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1519.00, NULL),
(1644, 1779422400, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1519.00, NULL),
(1653, 1779422580, 60, 'user_request', '8', 'count', 6.00, NULL),
(1669, 1779422580, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1681, 1779422700, 60, 'user_request', '8', 'count', 3.00, NULL),
(1693, 1779422940, 60, 'user_request', '8', 'count', 8.00, NULL),
(1694, 1779422760, 360, 'user_request', '8', 'count', 9.00, NULL),
(1705, 1779422940, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1706, 1779422760, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1707, 1779422940, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1708, 1779422760, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1721, 1779422940, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 1.00, NULL),
(1722, 1779422760, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 1.00, NULL),
(1723, 1779422400, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 1.00, NULL),
(1724, 1779422400, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 1.00, NULL),
(1725, 1779422940, 60, 'slow_user_request', '8', 'count', 1.00, NULL);
INSERT INTO `pulse_aggregates` (`id`, `bucket`, `period`, `type`, `key`, `aggregate`, `value`, `count`) VALUES
(1726, 1779422760, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(1727, 1779422940, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 1.00, NULL),
(1728, 1779422760, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 1.00, NULL),
(1729, 1779422400, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 1.00, NULL),
(1730, 1779422400, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 1.00, NULL),
(1737, 1779422940, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 13546.00, NULL),
(1738, 1779422760, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 13546.00, NULL),
(1739, 1779422400, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 13546.00, NULL),
(1740, 1779422400, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 13546.00, NULL),
(1741, 1779422940, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779422976.00, NULL),
(1742, 1779422760, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779422976.00, NULL),
(1743, 1779422400, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779422976.00, NULL),
(1744, 1779422400, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779422976.00, NULL),
(1753, 1779423060, 60, 'user_request', '8', 'count', 1.00, NULL),
(1757, 1779424740, 60, 'user_request', '8', 'count', 4.00, NULL),
(1758, 1779424560, 360, 'user_request', '8', 'count', 4.00, NULL),
(1759, 1779423840, 1440, 'user_request', '8', 'count', 4.00, NULL),
(1773, 1779510360, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'count', 1.00, NULL),
(1774, 1779510240, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'count', 1.00, NULL),
(1775, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'count', 1.00, NULL),
(1776, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'count', 1.00, NULL),
(1777, 1779510360, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'max', 16011.00, NULL),
(1778, 1779510240, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'max', 16011.00, NULL),
(1779, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'max', 16011.00, NULL),
(1780, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 'max', 16011.00, NULL),
(1781, 1779510360, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1782, 1779510240, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1783, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1784, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(1785, 1779510360, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1919.00, NULL),
(1786, 1779510240, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1919.00, NULL),
(1787, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1919.00, NULL),
(1788, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1919.00, NULL),
(1789, 1779510840, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1790, 1779510600, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1791, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1792, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1793, 1779510840, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2187.00, NULL),
(1794, 1779510600, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2187.00, NULL),
(1795, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2187.00, NULL),
(1796, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2187.00, NULL),
(1797, 1779510840, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1798, 1779510600, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1799, 1779510240, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1800, 1779503040, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1801, 1779510840, 60, 'slow_user_request', '8', 'count', 4.00, NULL),
(1802, 1779510600, 360, 'slow_user_request', '8', 'count', 7.00, NULL),
(1803, 1779510240, 1440, 'slow_user_request', '8', 'count', 7.00, NULL),
(1804, 1779503040, 10080, 'slow_user_request', '8', 'count', 7.00, NULL),
(1805, 1779510840, 60, 'user_request', '8', 'count', 11.00, NULL),
(1806, 1779510600, 360, 'user_request', '8', 'count', 15.00, NULL),
(1807, 1779510240, 1440, 'user_request', '8', 'count', 36.00, NULL),
(1808, 1779503040, 10080, 'user_request', '8', 'count', 36.00, NULL),
(1809, 1779510840, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1810, 1779510600, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1811, 1779510240, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1812, 1779503040, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1813, 1779510840, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1814, 1779510600, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1815, 1779510240, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1816, 1779503040, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1817, 1779510840, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 2233.00, NULL),
(1818, 1779510600, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 2233.00, NULL),
(1819, 1779510240, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 2233.00, NULL),
(1820, 1779503040, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 2233.00, NULL),
(1841, 1779510840, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 3.00, NULL),
(1842, 1779510600, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 6.00, NULL),
(1843, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 6.00, NULL),
(1844, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 6.00, NULL),
(1845, 1779510840, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 3.00, NULL),
(1846, 1779510600, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 6.00, NULL),
(1847, 1779510240, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 6.00, NULL),
(1848, 1779503040, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 6.00, NULL),
(1857, 1779510840, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 3934.00, NULL),
(1858, 1779510600, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 3934.00, NULL),
(1859, 1779510240, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 3934.00, NULL),
(1860, 1779503040, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 3934.00, NULL),
(1861, 1779510840, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779510888.00, NULL),
(1862, 1779510600, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779510947.00, NULL),
(1863, 1779510240, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779510947.00, NULL),
(1864, 1779503040, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779510947.00, NULL),
(1921, 1779510900, 60, 'user_request', '8', 'count', 4.00, NULL),
(1925, 1779510900, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'count', 3.00, NULL),
(1926, 1779510900, 60, 'slow_user_request', '8', 'count', 3.00, NULL),
(1927, 1779510900, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'count', 3.00, NULL),
(1941, 1779510900, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 'max', 1292.00, NULL),
(1942, 1779510900, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 'max', 1779510947.00, NULL),
(1997, 1779511020, 60, 'user_request', '8', 'count', 3.00, NULL),
(1998, 1779510960, 360, 'user_request', '8', 'count', 12.00, NULL),
(2009, 1779511080, 60, 'user_request', '8', 'count', 3.00, NULL),
(2021, 1779511140, 60, 'user_request', '8', 'count', 3.00, NULL),
(2033, 1779511260, 60, 'user_request', '8', 'count', 3.00, NULL),
(2045, 1779511320, 60, 'user_request', '8', 'count', 4.00, NULL),
(2046, 1779511320, 360, 'user_request', '8', 'count', 9.00, NULL),
(2061, 1779511380, 60, 'user_request', '8', 'count', 5.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pulse_entries`
--

CREATE TABLE `pulse_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `timestamp` int UNSIGNED NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pulse_entries`
--

INSERT INTO `pulse_entries` (`id`, `timestamp`, `type`, `key`, `value`) VALUES
(5, 1779413952, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(6, 1779413952, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(7, 1779413957, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(8, 1779413957, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(9, 1779413959, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(10, 1779413960, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(11, 1779413973, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(12, 1779413973, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(13, 1779413978, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(14, 1779413978, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(15, 1779414003, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(16, 1779414003, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1:timer', NULL),
(17, 1779414003, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1:timer', NULL),
(18, 1779414108, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 1072),
(19, 1779414127, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(20, 1779414127, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(21, 1779414134, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(22, 1779414135, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(23, 1779414140, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(24, 1779414140, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(25, 1779414161, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(26, 1779414161, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(27, 1779414253, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(28, 1779414253, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(29, 1779414746, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(30, 1779414747, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(31, 1779414777, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(32, 1779414777, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(33, 1779414781, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(34, 1779414781, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(35, 1779415063, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 2061),
(36, 1779415063, 'slow_user_request', '7', NULL),
(37, 1779415063, 'user_request', '7', NULL),
(38, 1779415065, 'user_request', '7', NULL),
(39, 1779415117, 'user_request', '7', NULL),
(40, 1779415183, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 15158),
(41, 1779415184, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(42, 1779415184, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779415184),
(43, 1779415199, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(44, 1779415367, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(45, 1779415368, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(46, 1779415529, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(47, 1779415529, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(48, 1779415576, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 3603),
(49, 1779415577, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(50, 1779415577, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779415577),
(51, 1779415580, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(52, 1779415705, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 3939),
(53, 1779415705, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(54, 1779415705, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779415705),
(55, 1779415709, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(56, 1779415821, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1511),
(57, 1779415821, 'slow_user_request', '8', NULL),
(58, 1779415821, 'user_request', '8', NULL),
(59, 1779415822, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(60, 1779415823, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(61, 1779415823, 'user_request', '8', NULL),
(62, 1779415828, 'user_request', '8', NULL),
(63, 1779415831, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1349),
(64, 1779415831, 'slow_user_request', '8', NULL),
(65, 1779415831, 'user_request', '8', NULL),
(66, 1779415831, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(67, 1779415832, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(68, 1779415833, 'user_request', '8', NULL),
(69, 1779415836, 'user_request', '8', NULL),
(70, 1779415901, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 2170),
(71, 1779415903, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 1692),
(72, 1779415922, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1442),
(73, 1779415922, 'slow_user_request', '8', NULL),
(74, 1779415922, 'user_request', '8', NULL),
(75, 1779415923, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(76, 1779415924, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(77, 1779415924, 'user_request', '8', NULL),
(78, 1779415945, 'user_request', '8', NULL),
(79, 1779415946, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(80, 1779415946, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(81, 1779415946, 'user_request', '8', NULL),
(82, 1779415953, 'user_request', '8', NULL),
(83, 1779415957, 'user_request', '8', NULL),
(84, 1779415958, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(85, 1779415958, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(86, 1779415958, 'user_request', '8', NULL),
(87, 1779416253, 'user_request', '8', NULL),
(88, 1779416256, 'user_request', '8', NULL),
(89, 1779416263, 'user_request', '8', NULL),
(90, 1779416273, 'user_request', '8', NULL),
(91, 1779416280, 'user_request', '8', NULL),
(92, 1779416285, 'user_request', '8', NULL),
(93, 1779416287, 'user_request', '8', NULL),
(94, 1779416290, 'user_request', '8', NULL),
(95, 1779416350, 'user_request', '8', NULL),
(96, 1779416522, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1648),
(97, 1779416522, 'slow_user_request', '8', NULL),
(98, 1779416522, 'user_request', '8', NULL),
(99, 1779416523, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(100, 1779416524, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(101, 1779416524, 'user_request', '8', NULL),
(102, 1779416527, 'user_request', '8', NULL),
(103, 1779416730, 'user_request', '8', NULL),
(104, 1779416735, 'user_request', '8', NULL),
(105, 1779416736, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(106, 1779416736, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(107, 1779416736, 'user_request', '8', NULL),
(108, 1779416755, 'user_request', '8', NULL),
(109, 1779416811, 'user_request', '8', NULL),
(110, 1779416812, 'user_request', '8', NULL),
(111, 1779416814, 'user_request', '8', NULL),
(112, 1779416815, 'user_request', '8', NULL),
(113, 1779416840, 'user_request', '8', NULL),
(114, 1779416841, 'user_request', '8', NULL),
(115, 1779416844, 'user_request', '8', NULL),
(116, 1779416848, 'user_request', '8', NULL),
(117, 1779416854, 'user_request', '8', NULL),
(118, 1779416855, 'user_request', '8', NULL),
(119, 1779416859, 'user_request', '8', NULL),
(120, 1779416865, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1255),
(121, 1779416865, 'slow_user_request', '8', NULL),
(122, 1779416865, 'user_request', '8', NULL),
(123, 1779416866, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(124, 1779416867, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(125, 1779416867, 'user_request', '8', NULL),
(126, 1779416870, 'user_request', '8', NULL),
(127, 1779416878, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1219),
(128, 1779416878, 'slow_user_request', '8', NULL),
(129, 1779416878, 'user_request', '8', NULL),
(130, 1779416878, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(131, 1779416879, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(132, 1779416880, 'user_request', '8', NULL),
(133, 1779416946, 'user_request', '8', NULL),
(134, 1779416948, 'user_request', '8', NULL),
(135, 1779417093, 'user_request', '8', NULL),
(136, 1779417095, 'user_request', '8', NULL),
(137, 1779417099, 'user_request', '8', NULL),
(138, 1779417100, 'user_request', '8', NULL),
(139, 1779417104, 'user_request', '8', NULL),
(140, 1779417107, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1287),
(141, 1779417107, 'slow_user_request', '8', NULL),
(142, 1779417107, 'user_request', '8', NULL),
(143, 1779417108, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(144, 1779417109, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(145, 1779417109, 'user_request', '8', NULL),
(146, 1779417113, 'user_request', '8', NULL),
(147, 1779417166, 'user_request', '8', NULL),
(148, 1779417170, 'user_request', '8', NULL),
(149, 1779417187, 'user_request', '8', NULL),
(150, 1779417292, 'user_request', '8', NULL),
(151, 1779417295, 'user_request', '8', NULL),
(152, 1779417298, 'user_request', '8', NULL),
(153, 1779417301, 'user_request', '8', NULL),
(154, 1779417344, 'user_request', '8', NULL),
(155, 1779417348, 'user_request', '8', NULL),
(156, 1779417352, 'user_request', '8', NULL),
(157, 1779417358, 'user_request', '8', NULL),
(158, 1779417358, 'user_request', '8', NULL),
(159, 1779417362, 'user_request', '8', NULL),
(160, 1779417368, 'user_request', '8', NULL),
(161, 1779417385, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1211),
(162, 1779417385, 'slow_user_request', '8', NULL),
(163, 1779417385, 'user_request', '8', NULL),
(164, 1779417386, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(165, 1779417387, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(166, 1779417387, 'user_request', '8', NULL),
(167, 1779417515, 'user_request', '8', NULL),
(168, 1779417515, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(169, 1779417515, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(170, 1779417515, 'user_request', '8', NULL),
(171, 1779417521, 'user_request', '8', NULL),
(172, 1779417522, 'user_request', '8', NULL),
(173, 1779417532, 'user_request', '8', NULL),
(174, 1779417553, 'user_request', '8', NULL),
(175, 1779417641, 'user_request', '8', NULL),
(176, 1779417643, 'user_request', '8', NULL),
(177, 1779417771, 'user_request', '8', NULL),
(178, 1779417882, 'user_request', '8', NULL),
(179, 1779417904, 'user_request', '8', NULL),
(180, 1779417932, 'user_request', '8', NULL),
(181, 1779417932, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(182, 1779417933, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(183, 1779417933, 'user_request', '8', NULL),
(184, 1779417935, 'user_request', '8', NULL),
(185, 1779417984, 'user_request', '8', NULL),
(186, 1779417986, 'user_request', '8', NULL),
(187, 1779417991, 'user_request', '8', NULL),
(188, 1779417994, 'user_request', '8', NULL),
(189, 1779418037, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 1779418037),
(190, 1779418037, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 1779418037),
(191, 1779418083, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 2640),
(192, 1779418085, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/127.0.0.1:5174\\/__inertia_ssr\"]', 2107),
(193, 1779418105, 'user_request', '8', NULL),
(194, 1779418105, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(195, 1779418105, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(196, 1779418105, 'user_request', '8', NULL),
(197, 1779418107, 'user_request', '8', NULL),
(198, 1779418109, 'user_request', '8', NULL),
(199, 1779418111, 'user_request', '8', NULL),
(200, 1779418112, 'user_request', '8', NULL),
(201, 1779418114, 'user_request', '8', NULL),
(202, 1779418117, 'user_request', '8', NULL),
(203, 1779418128, 'user_request', '8', NULL),
(204, 1779418132, 'user_request', '7', NULL),
(205, 1779418133, 'user_request', '7', NULL),
(206, 1779418317, 'user_request', '7', NULL),
(207, 1779418320, 'user_request', '8', NULL),
(208, 1779418320, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(209, 1779418321, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(210, 1779418321, 'user_request', '8', NULL),
(211, 1779418323, 'user_request', '8', NULL),
(212, 1779418585, 'user_request', '8', NULL),
(213, 1779418586, 'user_request', '8', NULL),
(214, 1779418638, 'user_request', '8', NULL),
(215, 1779418649, 'user_request', '8', NULL),
(216, 1779418657, 'user_request', '8', NULL),
(217, 1779418658, 'user_request', '8', NULL),
(218, 1779418661, 'user_request', '8', NULL),
(219, 1779418663, 'user_request', '8', NULL),
(220, 1779418664, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(221, 1779418664, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(222, 1779418664, 'user_request', '8', NULL),
(223, 1779418667, 'user_request', '8', NULL),
(224, 1779418718, 'user_request', '8', NULL),
(225, 1779418722, 'user_request', '8', NULL),
(226, 1779418726, 'user_request', '8', NULL),
(227, 1779418728, 'user_request', '8', NULL),
(228, 1779418728, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(229, 1779418729, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(230, 1779418729, 'user_request', '8', NULL),
(231, 1779418765, 'user_request', '8', NULL),
(232, 1779418860, 'user_request', '8', NULL),
(233, 1779418866, 'user_request', '8', NULL),
(234, 1779418878, 'user_request', '8', NULL),
(235, 1779418991, 'user_request', '8', NULL),
(236, 1779418994, 'user_request', '8', NULL),
(237, 1779418997, 'user_request', '8', NULL),
(238, 1779418997, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(239, 1779418998, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(240, 1779418998, 'user_request', '8', NULL),
(241, 1779419019, 'user_request', '8', NULL),
(242, 1779419109, 'user_request', '8', NULL),
(243, 1779419111, 'user_request', '8', NULL),
(244, 1779419133, 'user_request', '8', NULL),
(245, 1779419139, 'user_request', '8', NULL),
(246, 1779419146, 'user_request', '8', NULL),
(247, 1779419150, 'user_request', '8', NULL),
(248, 1779419153, 'user_request', '8', NULL),
(249, 1779419160, 'user_request', '8', NULL),
(250, 1779419163, 'user_request', '8', NULL),
(251, 1779419163, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(252, 1779419163, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(253, 1779419163, 'user_request', '8', NULL),
(254, 1779419251, 'user_request', '8', NULL),
(255, 1779419255, 'user_request', '8', NULL),
(256, 1779419258, 'user_request', '8', NULL),
(257, 1779419262, 'user_request', '8', NULL),
(258, 1779419266, 'user_request', '8', NULL),
(259, 1779419319, 'user_request', '8', NULL),
(260, 1779419351, 'user_request', '8', NULL),
(261, 1779419357, 'user_request', '8', NULL),
(262, 1779419359, 'user_request', '8', NULL),
(263, 1779419362, 'user_request', '8', NULL),
(264, 1779419362, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(265, 1779419363, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(266, 1779419363, 'user_request', '8', NULL),
(267, 1779419367, 'user_request', '8', NULL),
(268, 1779419367, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(269, 1779419367, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(270, 1779419367, 'user_request', '8', NULL),
(271, 1779419370, 'user_request', '8', NULL),
(272, 1779419372, 'user_request', '8', NULL),
(273, 1779419373, 'user_request', '8', NULL),
(274, 1779419462, 'user_request', '8', NULL),
(275, 1779419466, 'user_request', '8', NULL),
(276, 1779419469, 'user_request', '8', NULL),
(277, 1779419475, 'user_request', '8', NULL),
(278, 1779419522, 'user_request', '8', NULL),
(279, 1779419526, 'user_request', '8', NULL),
(280, 1779419529, 'user_request', '8', NULL),
(281, 1779419530, 'user_request', '8', NULL),
(282, 1779419532, 'user_request', '8', NULL),
(283, 1779419532, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(284, 1779419533, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(285, 1779419533, 'user_request', '8', NULL),
(286, 1779419537, 'user_request', '8', NULL),
(287, 1779419537, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(288, 1779419537, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(289, 1779419537, 'user_request', '8', NULL),
(290, 1779419579, 'user_request', '8', NULL),
(291, 1779419892, 'user_request', '8', NULL),
(292, 1779420446, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 14823),
(293, 1779420446, 'slow_user_request', '8', NULL),
(294, 1779420446, 'user_request', '8', NULL),
(295, 1779420461, 'user_request', '8', NULL),
(296, 1779420491, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 1779420491),
(297, 1779420491, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 1779420491),
(298, 1779420534, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 1037),
(299, 1779420546, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1701),
(300, 1779420546, 'slow_user_request', '8', NULL),
(301, 1779420546, 'user_request', '8', NULL),
(302, 1779420547, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(303, 1779420548, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(304, 1779420548, 'user_request', '8', NULL),
(305, 1779420552, 'user_request', '8', NULL),
(306, 1779420552, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(307, 1779420553, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(308, 1779420553, 'user_request', '8', NULL),
(309, 1779420700, 'user_request', '8', NULL),
(310, 1779421307, 'user_request', '8', NULL),
(311, 1779421310, 'user_request', '8', NULL),
(312, 1779421311, 'user_request', '8', NULL),
(313, 1779421312, 'user_request', '8', NULL),
(314, 1779421315, 'user_request', '8', NULL),
(315, 1779421318, 'user_request', '8', NULL),
(316, 1779421318, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(317, 1779421319, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(318, 1779421319, 'user_request', '8', NULL),
(319, 1779421354, 'user_request', '8', NULL),
(320, 1779421368, 'user_request', '8', NULL),
(321, 1779421368, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(322, 1779421369, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(323, 1779421369, 'user_request', '8', NULL),
(324, 1779421379, 'user_request', '8', NULL),
(325, 1779421379, 'user_request', '8', NULL),
(326, 1779421382, 'user_request', '8', NULL),
(327, 1779421397, 'user_request', '8', NULL),
(328, 1779421398, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(329, 1779421398, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(330, 1779421398, 'user_request', '8', NULL),
(331, 1779421425, 'user_request', '8', NULL),
(332, 1779421425, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(333, 1779421425, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(334, 1779421425, 'user_request', '8', NULL),
(335, 1779421461, 'user_request', '8', NULL),
(336, 1779421478, 'user_request', '8', NULL),
(337, 1779421484, 'user_request', '8', NULL),
(338, 1779421487, 'user_request', '8', NULL),
(339, 1779421491, 'user_request', '8', NULL),
(340, 1779421491, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(341, 1779421491, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(342, 1779421492, 'user_request', '8', NULL),
(343, 1779421498, 'user_request', '8', NULL),
(344, 1779421502, 'user_request', '8', NULL),
(345, 1779421540, 'user_request', '8', NULL),
(346, 1779421541, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(347, 1779421541, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(348, 1779421541, 'user_request', '8', NULL),
(349, 1779421545, 'user_request', '8', NULL),
(350, 1779421545, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(351, 1779421545, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(352, 1779421545, 'user_request', '8', NULL),
(353, 1779421549, 'user_request', '8', NULL),
(354, 1779421555, 'user_request', '8', NULL),
(355, 1779421555, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(356, 1779421556, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(357, 1779421556, 'user_request', '8', NULL),
(358, 1779421568, 'user_request', '8', NULL),
(359, 1779421568, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(360, 1779421568, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(361, 1779421568, 'user_request', '8', NULL),
(362, 1779421635, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 1339),
(363, 1779421635, 'slow_user_request', '8', NULL),
(364, 1779421635, 'user_request', '8', NULL),
(365, 1779421644, 'user_request', '8', NULL),
(366, 1779421654, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1050),
(367, 1779421654, 'slow_user_request', '7', NULL),
(368, 1779421654, 'user_request', '7', NULL),
(369, 1779421655, 'user_request', '7', NULL),
(370, 1779421660, 'user_request', '7', NULL),
(371, 1779421662, 'user_request', '8', NULL),
(372, 1779421663, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(373, 1779421663, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(374, 1779421663, 'user_request', '8', NULL),
(375, 1779422018, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 1779422018),
(376, 1779422018, 'exception', '[\"Symfony\\\\Component\\\\Console\\\\Exception\\\\CommandNotFoundException\",\"vendor\\\\symfony\\\\console\\\\Application.php:759\"]', 1779422018),
(377, 1779422041, 'user_request', '8', NULL),
(378, 1779422044, 'user_request', '8', NULL),
(379, 1779422047, 'user_request', '8', NULL),
(380, 1779422047, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(381, 1779422048, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(382, 1779422048, 'user_request', '8', NULL),
(383, 1779422057, 'user_request', '8', NULL),
(384, 1779422057, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(385, 1779422057, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(386, 1779422057, 'user_request', '8', NULL),
(387, 1779422189, 'user_request', '8', NULL),
(388, 1779422192, 'user_request', '8', NULL),
(389, 1779422219, 'user_request', '8', NULL),
(390, 1779422219, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(391, 1779422220, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(392, 1779422220, 'user_request', '8', NULL),
(393, 1779422552, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 5037),
(394, 1779422552, 'slow_user_request', '8', NULL),
(395, 1779422552, 'user_request', '8', NULL),
(396, 1779422557, 'user_request', '8', NULL),
(397, 1779422557, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(398, 1779422558, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(399, 1779422558, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 1328),
(400, 1779422558, 'slow_user_request', '8', NULL),
(401, 1779422558, 'user_request', '8', NULL),
(402, 1779422559, 'user_request', '8', NULL),
(403, 1779422567, 'user_request', '8', NULL),
(404, 1779422570, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1519),
(405, 1779422570, 'slow_user_request', '8', NULL),
(406, 1779422570, 'user_request', '8', NULL),
(407, 1779422570, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(408, 1779422571, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(409, 1779422571, 'user_request', '8', NULL),
(410, 1779422577, 'user_request', '8', NULL),
(411, 1779422582, 'user_request', '8', NULL),
(412, 1779422582, 'user_request', '8', NULL),
(413, 1779422585, 'user_request', '8', NULL),
(414, 1779422589, 'user_request', '8', NULL),
(415, 1779422592, 'user_request', '8', NULL),
(416, 1779422592, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(417, 1779422593, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(418, 1779422593, 'user_request', '8', NULL),
(419, 1779422713, 'user_request', '8', NULL),
(420, 1779422741, 'user_request', '8', NULL),
(421, 1779422743, 'user_request', '8', NULL),
(422, 1779422962, 'user_request', '8', NULL),
(423, 1779422968, 'user_request', '8', NULL),
(424, 1779422970, 'user_request', '8', NULL),
(425, 1779422973, 'user_request', '8', NULL),
(426, 1779422973, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(427, 1779422974, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(428, 1779422974, 'user_request', '8', NULL),
(429, 1779422976, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 13546),
(430, 1779422976, 'slow_user_request', '8', NULL),
(431, 1779422976, 'user_request', '8', NULL),
(432, 1779422976, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779422976),
(433, 1779422993, 'user_request', '8', NULL),
(434, 1779422999, 'user_request', '8', NULL),
(435, 1779423091, 'user_request', '8', NULL),
(436, 1779424769, 'user_request', '8', NULL),
(437, 1779424771, 'user_request', '8', NULL),
(438, 1779424774, 'user_request', '8', NULL),
(439, 1779424778, 'user_request', '8', NULL),
(440, 1779510387, 'slow_request', '[\"GET\",\"\\/super-admin\\/billing\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\BillingController@index\"]', 16011),
(441, 1779510404, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 1919),
(442, 1779510846, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 2187),
(443, 1779510863, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 2233),
(444, 1779510863, 'slow_user_request', '8', NULL),
(445, 1779510863, 'user_request', '8', NULL),
(446, 1779510863, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(447, 1779510865, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(448, 1779510865, 'user_request', '8', NULL),
(449, 1779510867, 'user_request', '8', NULL),
(450, 1779510869, 'user_request', '8', NULL),
(451, 1779510871, 'user_request', '8', NULL),
(452, 1779510872, 'user_request', '8', NULL),
(453, 1779510874, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 3934),
(454, 1779510874, 'slow_user_request', '8', NULL),
(455, 1779510874, 'user_request', '8', NULL),
(456, 1779510874, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779510874),
(457, 1779510881, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 1213),
(458, 1779510881, 'slow_user_request', '8', NULL),
(459, 1779510881, 'user_request', '8', NULL),
(460, 1779510881, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779510881),
(461, 1779510884, 'user_request', '8', NULL),
(462, 1779510886, 'user_request', '8', NULL),
(463, 1779510887, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 1197),
(464, 1779510887, 'slow_user_request', '8', NULL),
(465, 1779510887, 'user_request', '8', NULL),
(466, 1779510888, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779510888),
(467, 1779510905, 'user_request', '8', NULL),
(468, 1779510906, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 1292),
(469, 1779510906, 'slow_user_request', '8', NULL),
(470, 1779510906, 'user_request', '8', NULL),
(471, 1779510907, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779510907),
(472, 1779510912, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 1211),
(473, 1779510912, 'slow_user_request', '8', NULL),
(474, 1779510912, 'user_request', '8', NULL),
(475, 1779510912, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779510912),
(476, 1779510947, 'slow_request', '[\"GET\",\"\\/super-admin\\/accounts\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController@index\"]', 1269),
(477, 1779510947, 'slow_user_request', '8', NULL),
(478, 1779510947, 'user_request', '8', NULL),
(479, 1779510947, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\AccountController.php:52\"]', 1779510947),
(480, 1779511051, 'user_request', '8', NULL),
(481, 1779511058, 'user_request', '8', NULL),
(482, 1779511071, 'user_request', '8', NULL),
(483, 1779511118, 'user_request', '8', NULL),
(484, 1779511120, 'user_request', '8', NULL),
(485, 1779511138, 'user_request', '8', NULL),
(486, 1779511141, 'user_request', '8', NULL),
(487, 1779511146, 'user_request', '8', NULL),
(488, 1779511149, 'user_request', '8', NULL),
(489, 1779511309, 'user_request', '8', NULL),
(490, 1779511313, 'user_request', '8', NULL),
(491, 1779511317, 'user_request', '8', NULL),
(492, 1779511341, 'user_request', '8', NULL),
(493, 1779511358, 'user_request', '8', NULL),
(494, 1779511360, 'user_request', '8', NULL),
(495, 1779511367, 'user_request', '8', NULL),
(496, 1779511387, 'user_request', '8', NULL),
(497, 1779511418, 'user_request', '8', NULL),
(498, 1779511422, 'user_request', '8', NULL),
(499, 1779511426, 'user_request', '8', NULL),
(500, 1779511431, 'user_request', '8', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pulse_values`
--

CREATE TABLE `pulse_values` (
  `id` bigint UNSIGNED NOT NULL,
  `timestamp` int UNSIGNED NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurants`
--

CREATE TABLE `restaurants` (
  `id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED DEFAULT NULL,
  `owner_user_id` bigint UNSIGNED DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  `currency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `status` enum('active','expired','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `subscription_started_at` date DEFAULT NULL,
  `subscription_ends_at` date DEFAULT NULL,
  `trial_ends_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_branches`
--

CREATE TABLE `restaurant_branches` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_user_id` bigint UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_revenue_summaries`
--

CREATE TABLE `restaurant_revenue_summaries` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `summary_type` enum('daily','weekly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `scope_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'restaurant',
  `summary_date` date NOT NULL,
  `order_count` int UNSIGNED NOT NULL DEFAULT '0',
  `completed_order_count` int UNSIGNED NOT NULL DEFAULT '0',
  `cancelled_order_count` int UNSIGNED NOT NULL DEFAULT '0',
  `gross_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `service_charge_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `refund_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cash_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `bank_transfer_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `card_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `ewallet_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `mixed_revenue` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cogs_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `gross_profit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `average_order_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `first_order_at` datetime DEFAULT NULL,
  `last_order_at` datetime DEFAULT NULL,
  `calculated_at` datetime DEFAULT NULL,
  `source` enum('system','manual','rebuild') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_settings`
--

CREATE TABLE `restaurant_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `key_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_subscriptions`
--

CREATE TABLE `restaurant_subscriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED NOT NULL,
  `status` enum('trial','active','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `renewal_at` datetime DEFAULT NULL,
  `transaction_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grace_ends_at` timestamp NULL DEFAULT NULL,
  `last_notified_at` timestamp NULL DEFAULT NULL,
  `last_paid_at` timestamp NULL DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `meta` json DEFAULT NULL,
  `billing_meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_tables`
--

CREATE TABLE `restaurant_tables` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `area_id` bigint UNSIGNED NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int UNSIGNED NOT NULL DEFAULT '2',
  `status` enum('available','occupied','reserved','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(2, 'owner', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(3, 'manager', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(4, 'cashier', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(5, 'kitchen', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(6, 'inventory_staff', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58'),
(7, 'customer', 'web', '2026-05-21 18:34:58', '2026-05-21 18:34:58');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(2, 2),
(3, 2),
(4, 2),
(5, 2),
(6, 2),
(7, 2),
(8, 2),
(9, 2),
(10, 2),
(11, 2),
(12, 2),
(13, 2),
(14, 2),
(15, 2),
(3, 3),
(4, 3),
(5, 3),
(6, 3),
(7, 3),
(8, 3),
(9, 3),
(10, 3),
(11, 3),
(12, 3),
(13, 3),
(14, 3),
(15, 3),
(6, 4),
(7, 4),
(8, 4),
(11, 4),
(7, 5),
(9, 5),
(10, 5),
(5, 6),
(7, 6),
(7, 7);

-- --------------------------------------------------------

--
-- Table structure for table `salaries`
--

CREATE TABLE `salaries` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `pay_period_start` date NOT NULL,
  `pay_period_end` date NOT NULL,
  `base_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','approved','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_adjustments`
--

CREATE TABLE `salary_adjustments` (
  `id` bigint UNSIGNED NOT NULL,
  `salary_id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `type` enum('bonus','penalty','cash_shortage','inventory_loss','violation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint UNSIGNED DEFAULT NULL,
  `reference_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_assignments`
--

CREATE TABLE `schedule_assignments` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `shift_id` bigint UNSIGNED NOT NULL,
  `scheduled_date` date NOT NULL,
  `check_in_at` datetime DEFAULT NULL,
  `check_out_at` datetime DEFAULT NULL,
  `status` enum('scheduled','checked_in','completed','absent','leave_approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('0Z04nf0ryPuBqpzvrEUk4bILOdhoO3ZTIiFpPXOz', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.121.0 Chrome/142.0.7444.265 Electron/39.8.8 Safari/537.36', 'eyJfdG9rZW4iOiJrNGRLUVVNQWFGME02Zkh5VFN0UDAyd2Z0b0dsQUg4QjRwZzVtWWpjIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779418794),
('HHSELpGCrJgMhb62VJMyRAGlSybjh8xHfkYzfqB2', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJwZW9LRWR6TjI4ZGoxWVhibFV4MkdOeFZSZzE2d0xheGw4YnZzYjlSIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3N1cGVyLWFkbWluXC9kYXNoYm9hcmQifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779510406),
('jcwY5ZB7YRyAqNEfduEiSrbmj6ZaHKUWrl87TuGf', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.121.0 Chrome/142.0.7444.265 Electron/39.8.8 Safari/537.36', 'eyJfdG9rZW4iOiJIRFlkVmNib2lhZlVRM3JBNkUxNENvNU81WnNoS3FoUlVLM0RLTVY0IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779510848),
('NSLjT6BvHCi4GHclFoi51qwhZK9B2Ql2qGoOHOG2', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJqVXBiRTJONFlyVWFvaGdEb2dsV3QzUG1zQjc4SjhwWmV0cjd2TjcxIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3N1cGVyLWFkbWluXC9kYXNoYm9hcmQifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zdXBlci1hZG1pblwvYWNjb3VudHMiLCJyb3V0ZSI6InN1cGVyYWRtaW4uYWNjb3VudHMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OH0=', 1779511431),
('nYtV0W8q7I65o7Q4hPtSXejCRURH4bg7xyM5s2Sq', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJ1OVg1NWNXOVV2SjhyYW1RdGVTeHRaMDJCNDVqZ3JzbGE0a2VVMHliIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJ1cmwiOltdLCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2Rhc2hib2FyZCIsInJvdXRlIjoiZGFzaGJvYXJkIn0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjo4fQ==', 1779417994),
('PT5NPRwKUQVvYg4eteqCZYyxT8CaCnzcPPumOBRA', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJzTm5hckhCV0tzRXA1NEdSd2hkZEpPenRQYzk0d2tJUTF4S2UwVVBZIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2Rhc2hib2FyZCJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL2Rhc2hib2FyZCIsInJvdXRlIjoiZGFzaGJvYXJkIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779420462),
('ToGzsDnxfcAaakaAKQLJwIShFP0a9CnGz1AaWSVh', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJVdmZKUUNxbXI4QnhOM1pST1lzM0xwcE16WHhUNW5XdExzSHoyWEVQIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OCwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zdXBlci1hZG1pblwvZGFzaGJvYXJkIiwicm91dGUiOiJzdXBlcmFkbWluLmRhc2hib2FyZCJ9fQ==', 1779424779);

-- --------------------------------------------------------

--
-- Table structure for table `shift_closings`
--

CREATE TABLE `shift_closings` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `shift_id` bigint UNSIGNED NOT NULL,
  `closing_date` date NOT NULL,
  `cashier_user_id` bigint UNSIGNED DEFAULT NULL,
  `confirmed_by` bigint UNSIGNED DEFAULT NULL,
  `expected_cash` decimal(12,2) NOT NULL DEFAULT '0.00',
  `actual_cash` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cash_difference` decimal(12,2) NOT NULL DEFAULT '0.00',
  `transfer_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `other_expense_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','submitted','confirmed','disputed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `billing_cycle` enum('monthly','quarterly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `max_branches` int UNSIGNED DEFAULT NULL,
  `max_tables` int UNSIGNED DEFAULT NULL,
  `max_users` int UNSIGNED DEFAULT NULL,
  `features` json DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `code`, `name`, `price`, `billing_cycle`, `max_branches`, `max_tables`, `max_users`, `features`, `status`, `created_at`, `updated_at`) VALUES
(1, 'free', 'Free', 0.00, 'monthly', 1, 10, 5, '{\"analytics\": false, \"multi_branch\": false, \"ai_prediction\": false}', 'active', '2026-05-21 18:34:51', '2026-05-21 18:34:51'),
(2, 'pro', 'Pro', 499000.00, 'monthly', NULL, NULL, NULL, '{\"analytics\": true, \"multi_branch\": true, \"ai_prediction\": true}', 'active', '2026-05-21 18:34:51', '2026-05-21 18:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('mass','volume','count') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'count',
  `base_unit_id` bigint UNSIGNED DEFAULT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `restaurant_id`, `branch_id`, `name`, `email`, `google_id`, `phone`, `avatar_url`, `status`, `last_login_at`, `email_verified_at`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(7, NULL, NULL, 'Văn Quân Lê', 'tamh77573@gmail.com', '116627655680193977921', NULL, NULL, 'active', '2026-05-21 20:47:35', '2026-05-21 18:57:45', '$2y$12$CPzcHzVy2GbSBbemh11qOuL8xe1inGoTfxx56XnEMAnsjiijzsZeK', NULL, NULL, NULL, 'SlHO84DRdAxN7pMMQkDLiPHb1IxTS5p6QyF6m4UaqtDHpRYXp0vz3tkImpUL', '2026-05-21 18:57:45', '2026-05-21 20:47:35'),
(8, NULL, NULL, 'Super Admin', 'superadmin@aventura.local', NULL, NULL, NULL, 'active', '2026-05-22 21:34:24', '2026-05-22 02:06:06', '$2y$12$ZghJTLFaGWysJ1AgiqXwBupETSOMwYHrfcCwTVsyraLgK1TnDuS4S', NULL, NULL, NULL, NULL, NULL, '2026-05-22 21:34:24');

-- --------------------------------------------------------

--
-- Table structure for table `violation_reports`
--

CREATE TABLE `violation_reports` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `employee_id` bigint UNSIGNED NOT NULL,
  `reported_by` bigint UNSIGNED DEFAULT NULL,
  `violation_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `penalty_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `occurred_at` datetime NOT NULL,
  `status` enum('open','reviewed','resolved','dismissed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_shifts`
--

CREATE TABLE `work_shifts` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_overnight` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `areas_branch_code_unique` (`branch_id`,`code`),
  ADD KEY `areas_restaurant_status_index` (`restaurant_id`,`status`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_logs_branch_id_foreign` (`branch_id`),
  ADD KEY `audit_logs_subject_index` (`subject_type`,`subject_id`),
  ADD KEY `audit_logs_user_index` (`user_id`),
  ADD KEY `audit_logs_restaurant_created_at_action_index` (`restaurant_id`,`created_at`,`action`);

--
-- Indexes for table `billing_adjustments`
--
ALTER TABLE `billing_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `billing_adjustments_restaurant_subscription_id_foreign` (`restaurant_subscription_id`),
  ADD KEY `billing_adjustments_created_by_foreign` (`created_by`),
  ADD KEY `billing_adjustments_restaurant_type_index` (`restaurant_id`,`type`);

--
-- Indexes for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `billing_invoices_invoice_number_unique` (`invoice_number`),
  ADD KEY `billing_invoices_restaurant_subscription_id_foreign` (`restaurant_subscription_id`),
  ADD KEY `billing_invoices_restaurant_type_index` (`restaurant_id`,`type`),
  ADD KEY `billing_invoices_status_due_index` (`status`,`due_on`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customers_restaurant_phone_index` (`restaurant_id`,`phone`),
  ADD KEY `customers_branch_index` (`branch_id`);

--
-- Indexes for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_feedback_branch_id_foreign` (`branch_id`),
  ADD KEY `customer_feedback_customer_id_foreign` (`customer_id`),
  ADD KEY `customer_feedback_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `customer_feedback_order_index` (`order_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employees_restaurant_code_unique` (`restaurant_id`,`employee_code`),
  ADD UNIQUE KEY `employees_user_id_unique` (`user_id`),
  ADD KEY `employees_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `employees_branch_index` (`branch_id`),
  ADD KEY `employees_role_id_foreign` (`role_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ingredients_restaurant_sku_unique` (`restaurant_id`,`sku`),
  ADD KEY `ingredients_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `ingredients_branch_index` (`branch_id`),
  ADD KEY `ingredients_supplier_index` (`supplier_id`),
  ADD KEY `ingredients_unit_index` (`unit_id`);

--
-- Indexes for table `inventories`
--
ALTER TABLE `inventories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `inventories_branch_ingredient_unique` (`branch_id`,`ingredient_id`),
  ADD KEY `inventories_ingredient_id_foreign` (`ingredient_id`),
  ADD KEY `inventories_restaurant_ingredient_index` (`restaurant_id`,`ingredient_id`),
  ADD KEY `inventories_updated_by_index` (`updated_by`);

--
-- Indexes for table `inventory_reservations`
--
ALTER TABLE `inventory_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_reservations_order_id_foreign` (`order_id`),
  ADD KEY `inventory_reservations_ingredient_id_foreign` (`ingredient_id`),
  ADD KEY `inv_reservations_restaurant_expires_status_index` (`restaurant_id`,`expires_at`,`status`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_transactions_branch_id_foreign` (`branch_id`),
  ADD KEY `inventory_transactions_performed_by_foreign` (`performed_by`),
  ADD KEY `inventory_transactions_supplier_id_foreign` (`supplier_id`),
  ADD KEY `inventory_transactions_restaurant_type_date_index` (`restaurant_id`,`type`,`occurred_at`),
  ADD KEY `inventory_transactions_order_index` (`order_id`),
  ADD KEY `inventory_transactions_ingredient_index` (`ingredient_id`),
  ADD KEY `inventory_transactions_inventory_index` (`inventory_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leave_requests_requested_by_foreign` (`requested_by`),
  ADD KEY `leave_requests_approved_by_foreign` (`approved_by`),
  ADD KEY `leave_requests_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `leave_requests_employee_index` (`employee_id`);

--
-- Indexes for table `media_assets`
--
ALTER TABLE `media_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `media_assets_branch_id_foreign` (`branch_id`),
  ADD KEY `media_assets_attachable_type_attachable_id_index` (`attachable_type`,`attachable_id`),
  ADD KEY `media_assets_restaurant_collection_index` (`restaurant_id`,`collection`),
  ADD KEY `media_assets_attachable_index` (`attachable_type`,`attachable_id`),
  ADD KEY `media_assets_type_created_index` (`media_type`,`created_at`),
  ADD KEY `media_assets_uploaded_by_index` (`uploaded_by`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_restaurant_number_unique` (`restaurant_id`,`order_number`),
  ADD KEY `orders_table_id_foreign` (`table_id`),
  ADD KEY `orders_created_by_foreign` (`created_by`),
  ADD KEY `orders_cancelled_by_foreign` (`cancelled_by`),
  ADD KEY `orders_restaurant_status_created_index` (`restaurant_id`,`status`,`created_at`),
  ADD KEY `orders_branch_table_index` (`branch_id`,`table_id`),
  ADD KEY `orders_customer_index` (`customer_id`),
  ADD KEY `orders_cashier_index` (`cashier_user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_product_id_foreign` (`product_id`),
  ADD KEY `order_items_order_status_index` (`order_id`,`status`),
  ADD KEY `order_items_restaurant_product_index` (`restaurant_id`,`product_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payments_processed_by_foreign` (`processed_by`),
  ADD KEY `payments_restaurant_status_paid_index` (`restaurant_id`,`status`,`paid_at`),
  ADD KEY `payments_order_index` (`order_id`),
  ADD KEY `payments_branch_index` (`branch_id`);

--
-- Indexes for table `payment_webhooks`
--
ALTER TABLE `payment_webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_webhooks_provider_transaction_index` (`provider`,`transaction_code`),
  ADD KEY `payment_webhooks_provider_status_index` (`provider`,`status`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_restaurant_code_unique` (`restaurant_id`,`code`),
  ADD UNIQUE KEY `products_restaurant_slug_unique` (`restaurant_id`,`slug`),
  ADD KEY `products_restaurant_status_index` (`restaurant_id`,`is_active`,`is_available`),
  ADD KEY `products_category_index` (`category_id`),
  ADD KEY `products_branch_index` (`branch_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_categories_restaurant_slug_unique` (`restaurant_id`,`slug`),
  ADD KEY `product_categories_branch_id_foreign` (`branch_id`),
  ADD KEY `product_categories_restaurant_status_index` (`restaurant_id`,`status`);

--
-- Indexes for table `product_recipes`
--
ALTER TABLE `product_recipes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_recipes_product_ingredient_unique` (`product_id`,`ingredient_id`),
  ADD KEY `product_recipes_ingredient_id_foreign` (`ingredient_id`),
  ADD KEY `product_recipes_restaurant_product_index` (`restaurant_id`,`product_id`),
  ADD KEY `product_recipes_unit_index` (`unit_id`);

--
-- Indexes for table `pulse_aggregates`
--
ALTER TABLE `pulse_aggregates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pulse_aggregates_bucket_period_type_aggregate_key_hash_unique` (`bucket`,`period`,`type`,`aggregate`,`key_hash`),
  ADD KEY `pulse_aggregates_period_bucket_index` (`period`,`bucket`),
  ADD KEY `pulse_aggregates_type_index` (`type`),
  ADD KEY `pulse_aggregates_period_type_aggregate_bucket_index` (`period`,`type`,`aggregate`,`bucket`);

--
-- Indexes for table `pulse_entries`
--
ALTER TABLE `pulse_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pulse_entries_timestamp_index` (`timestamp`),
  ADD KEY `pulse_entries_type_index` (`type`),
  ADD KEY `pulse_entries_key_hash_index` (`key_hash`),
  ADD KEY `pulse_entries_timestamp_type_key_hash_value_index` (`timestamp`,`type`,`key_hash`,`value`);

--
-- Indexes for table `pulse_values`
--
ALTER TABLE `pulse_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pulse_values_type_key_hash_unique` (`type`,`key_hash`),
  ADD KEY `pulse_values_timestamp_index` (`timestamp`),
  ADD KEY `pulse_values_type_index` (`type`);

--
-- Indexes for table `restaurants`
--
ALTER TABLE `restaurants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurants_code_unique` (`code`),
  ADD UNIQUE KEY `restaurants_slug_unique` (`slug`),
  ADD KEY `restaurants_plan_status_index` (`plan_id`,`status`),
  ADD KEY `restaurants_owner_index` (`owner_user_id`);

--
-- Indexes for table `restaurant_branches`
--
ALTER TABLE `restaurant_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurant_branches_restaurant_code_unique` (`restaurant_id`,`code`),
  ADD KEY `restaurant_branches_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `restaurant_branches_manager_index` (`manager_user_id`);

--
-- Indexes for table `restaurant_revenue_summaries`
--
ALTER TABLE `restaurant_revenue_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurant_revenue_summaries_unique_scope` (`restaurant_id`,`scope_key`,`summary_type`,`summary_date`),
  ADD KEY `restaurant_revenue_summaries_restaurant_type_date_index` (`restaurant_id`,`summary_type`,`summary_date`),
  ADD KEY `restaurant_revenue_summaries_branch_index` (`branch_id`);

--
-- Indexes for table `restaurant_settings`
--
ALTER TABLE `restaurant_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurant_settings_scope_key_unique` (`restaurant_id`,`branch_id`,`key_name`),
  ADD KEY `restaurant_settings_branch_id_foreign` (`branch_id`);

--
-- Indexes for table `restaurant_subscriptions`
--
ALTER TABLE `restaurant_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurant_subscriptions_transaction_code_unique` (`transaction_code`),
  ADD KEY `restaurant_subscriptions_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `restaurant_subscriptions_plan_index` (`plan_id`),
  ADD KEY `restaurant_subscriptions_status_renewal_index` (`status`,`renewal_at`);

--
-- Indexes for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `restaurant_tables_area_name_unique` (`area_id`,`name`),
  ADD KEY `restaurant_tables_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `restaurant_tables_branch_index` (`branch_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `salaries`
--
ALTER TABLE `salaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `salaries_employee_period_unique` (`employee_id`,`pay_period_start`,`pay_period_end`),
  ADD KEY `salaries_approved_by_foreign` (`approved_by`),
  ADD KEY `salaries_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `salaries_branch_index` (`branch_id`);

--
-- Indexes for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salary_adjustments_employee_id_foreign` (`employee_id`),
  ADD KEY `salary_adjustments_salary_index` (`salary_id`),
  ADD KEY `salary_adjustments_restaurant_type_index` (`restaurant_id`,`type`);

--
-- Indexes for table `schedule_assignments`
--
ALTER TABLE `schedule_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `schedule_assignments_unique` (`employee_id`,`shift_id`,`scheduled_date`),
  ADD KEY `schedule_assignments_shift_id_foreign` (`shift_id`),
  ADD KEY `schedule_assignments_approved_by_foreign` (`approved_by`),
  ADD KEY `schedule_assignments_restaurant_date_status_index` (`restaurant_id`,`scheduled_date`,`status`),
  ADD KEY `schedule_assignments_branch_index` (`branch_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `shift_closings`
--
ALTER TABLE `shift_closings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `shift_closings_unique` (`restaurant_id`,`branch_id`,`shift_id`,`closing_date`),
  ADD KEY `shift_closings_branch_id_foreign` (`branch_id`),
  ADD KEY `shift_closings_shift_id_foreign` (`shift_id`),
  ADD KEY `shift_closings_cashier_user_id_foreign` (`cashier_user_id`),
  ADD KEY `shift_closings_confirmed_by_foreign` (`confirmed_by`),
  ADD KEY `shift_closings_status_index` (`restaurant_id`,`status`,`closing_date`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscription_plans_code_unique` (`code`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `suppliers_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `suppliers_branch_index` (`branch_id`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `units_scope_symbol_unique` (`restaurant_id`,`symbol`),
  ADD KEY `units_base_unit_id_foreign` (`base_unit_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_google_id_unique` (`google_id`),
  ADD KEY `users_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `users_branch_index` (`branch_id`);

--
-- Indexes for table `violation_reports`
--
ALTER TABLE `violation_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `violation_reports_branch_id_foreign` (`branch_id`),
  ADD KEY `violation_reports_reported_by_foreign` (`reported_by`),
  ADD KEY `violation_reports_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `violation_reports_employee_index` (`employee_id`);

--
-- Indexes for table `work_shifts`
--
ALTER TABLE `work_shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `work_shifts_restaurant_code_unique` (`restaurant_id`,`code`),
  ADD KEY `work_shifts_branch_index` (`branch_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_adjustments`
--
ALTER TABLE `billing_adjustments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventories`
--
ALTER TABLE `inventories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_reservations`
--
ALTER TABLE `inventory_reservations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_assets`
--
ALTER TABLE `media_assets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_webhooks`
--
ALTER TABLE `payment_webhooks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_recipes`
--
ALTER TABLE `product_recipes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pulse_aggregates`
--
ALTER TABLE `pulse_aggregates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2081;

--
-- AUTO_INCREMENT for table `pulse_entries`
--
ALTER TABLE `pulse_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=501;

--
-- AUTO_INCREMENT for table `pulse_values`
--
ALTER TABLE `pulse_values`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurants`
--
ALTER TABLE `restaurants`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `restaurant_branches`
--
ALTER TABLE `restaurant_branches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurant_revenue_summaries`
--
ALTER TABLE `restaurant_revenue_summaries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurant_settings`
--
ALTER TABLE `restaurant_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurant_subscriptions`
--
ALTER TABLE `restaurant_subscriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `salaries`
--
ALTER TABLE `salaries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_assignments`
--
ALTER TABLE `schedule_assignments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_closings`
--
ALTER TABLE `shift_closings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `violation_reports`
--
ALTER TABLE `violation_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_shifts`
--
ALTER TABLE `work_shifts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `areas_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `areas_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_logs_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing_adjustments`
--
ALTER TABLE `billing_adjustments`
  ADD CONSTRAINT `billing_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billing_adjustments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_adjustments_restaurant_subscription_id_foreign` FOREIGN KEY (`restaurant_subscription_id`) REFERENCES `restaurant_subscriptions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing_invoices`
--
ALTER TABLE `billing_invoices`
  ADD CONSTRAINT `billing_invoices_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_invoices_restaurant_subscription_id_foreign` FOREIGN KEY (`restaurant_subscription_id`) REFERENCES `restaurant_subscriptions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  ADD CONSTRAINT `customer_feedback_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customer_feedback_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `employees_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employees_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ingredients`
--
ALTER TABLE `ingredients`
  ADD CONSTRAINT `ingredients_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ingredients_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ingredients_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ingredients_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `inventories`
--
ALTER TABLE `inventories`
  ADD CONSTRAINT `inventories_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventories_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventories_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_reservations`
--
ALTER TABLE `inventory_reservations`
  ADD CONSTRAINT `inventory_reservations_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_reservations_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_reservations_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_inventory_id_foreign` FOREIGN KEY (`inventory_id`) REFERENCES `inventories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_performed_by_foreign` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_transactions_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leave_requests_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media_assets`
--
ALTER TABLE `media_assets`
  ADD CONSTRAINT `media_assets_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `media_assets_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `media_assets_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_cashier_user_id_foreign` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_table_id_foreign` FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `order_items_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_categories_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_recipes`
--
ALTER TABLE `product_recipes`
  ADD CONSTRAINT `product_recipes_ingredient_id_foreign` FOREIGN KEY (`ingredient_id`) REFERENCES `ingredients` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `product_recipes_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recipes_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_recipes_unit_id_foreign` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `restaurants`
--
ALTER TABLE `restaurants`
  ADD CONSTRAINT `restaurants_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restaurants_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `restaurant_branches`
--
ALTER TABLE `restaurant_branches`
  ADD CONSTRAINT `restaurant_branches_manager_user_id_foreign` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restaurant_branches_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restaurant_revenue_summaries`
--
ALTER TABLE `restaurant_revenue_summaries`
  ADD CONSTRAINT `restaurant_revenue_summaries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `restaurant_revenue_summaries_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restaurant_settings`
--
ALTER TABLE `restaurant_settings`
  ADD CONSTRAINT `restaurant_settings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_settings_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restaurant_subscriptions`
--
ALTER TABLE `restaurant_subscriptions`
  ADD CONSTRAINT `restaurant_subscriptions_plan_id_foreign` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `restaurant_subscriptions_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  ADD CONSTRAINT `restaurant_tables_area_id_foreign` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_tables_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `restaurant_tables_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salaries`
--
ALTER TABLE `salaries`
  ADD CONSTRAINT `salaries_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salaries_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `salaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salaries_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  ADD CONSTRAINT `salary_adjustments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_adjustments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_adjustments_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_assignments`
--
ALTER TABLE `schedule_assignments`
  ADD CONSTRAINT `schedule_assignments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_assignments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `schedule_assignments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_assignments_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedule_assignments_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `work_shifts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_closings`
--
ALTER TABLE `shift_closings`
  ADD CONSTRAINT `shift_closings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shift_closings_cashier_user_id_foreign` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shift_closings_confirmed_by_foreign` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shift_closings_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shift_closings_shift_id_foreign` FOREIGN KEY (`shift_id`) REFERENCES `work_shifts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `suppliers_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_base_unit_id_foreign` FOREIGN KEY (`base_unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `units_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `violation_reports`
--
ALTER TABLE `violation_reports`
  ADD CONSTRAINT `violation_reports_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `violation_reports_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `violation_reports_reported_by_foreign` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `violation_reports_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_shifts`
--
ALTER TABLE `work_shifts`
  ADD CONSTRAINT `work_shifts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `restaurant_branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `work_shifts_restaurant_id_foreign` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
