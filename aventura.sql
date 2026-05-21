-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 21, 2026 at 07:41 AM
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
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `restaurant_id`, `branch_id`, `name`, `code`, `display_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'Tang tret', 'A', 1, 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `user_role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` enum('created','updated','deleted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint UNSIGNED DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `restaurant_id`, `branch_id`, `user_id`, `user_role`, `event`, `action`, `subject_type`, `subject_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 1, 3, 'cashier', 'created', 'seed_demo_order', 'App\\Models\\Order', 1, NULL, '{\"order_number\": \"ORD-DEMO-001\", \"total_amount\": 100000}', '127.0.0.1', 'database-seeder', '2026-05-19 22:20:35'),
(2, 1, 1, 3, 'cashier', 'created', 'seed_demo_order', 'App\\Models\\Order', 1, NULL, '{\"order_number\": \"ORD-DEMO-001\", \"total_amount\": 100000}', '127.0.0.1', 'database-seeder', '2026-05-20 12:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('aventura-cache-7d57ee2629976602c1fc93f22d4d097a', 'i:1;', 1779345911),
('aventura-cache-7d57ee2629976602c1fc93f22d4d097a:timer', 'i:1779345911;', 1779345911),
('aventura-cache-dcacbc44a7737aebdda0ba04d9733cb1', 'i:3;', 1779349183),
('aventura-cache-dcacbc44a7737aebdda0ba04d9733cb1:timer', 'i:1779349183;', 1779349183),
('aventura-cache-ok@gmail.com|127.0.0.1', 'i:1;', 1779345912),
('aventura-cache-ok@gmail.com|127.0.0.1:timer', 'i:1779345912;', 1779345912);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `loyalty_points` int UNSIGNED NOT NULL DEFAULT '0',
  `last_order_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `restaurant_id`, `branch_id`, `full_name`, `phone`, `email`, `gender`, `date_of_birth`, `notes`, `loyalty_points`, `last_order_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'Khach Demo', '0988888888', NULL, NULL, NULL, NULL, 120, '2026-05-20 19:27:01', '2026-05-19 22:20:35', '2026-05-20 12:27:01', NULL);

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
  `submitted_by_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_by_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('new','reviewed','resolved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
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
  `employee_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizen_id_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizen_id_front_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `citizen_id_back_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `employment_type` enum('full_time','part_time','seasonal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full_time',
  `job_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `base_salary` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive','terminated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `restaurant_id`, `branch_id`, `user_id`, `employee_code`, `full_name`, `date_of_birth`, `gender`, `phone`, `email`, `address`, `citizen_id_number`, `citizen_id_front_url`, `citizen_id_back_url`, `hire_date`, `employment_type`, `job_title`, `base_salary`, `status`, `created_at`, `updated_at`, `role_id`, `deleted_at`) VALUES
(1, 1, 1, 1, 'EMP-001', 'Owner Demo', NULL, NULL, '0900000001', 'owner@bepso.test', NULL, '079069537575', NULL, NULL, '2026-02-20', 'full_time', 'Owner', 9000000.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', 2, NULL),
(2, 1, 1, 2, 'EMP-002', 'Manager Demo', NULL, NULL, '0900000002', 'manager@bepso.test', NULL, '079041150711', NULL, NULL, '2026-02-20', 'full_time', 'Manager', 9000000.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', 3, NULL),
(3, 1, 1, 3, 'EMP-003', 'Cashier Demo', NULL, NULL, '0900000003', 'cashier@bepso.test', NULL, '079043298150', NULL, NULL, '2026-02-20', 'full_time', 'Cashier', 9000000.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', 4, NULL),
(4, 1, 1, 4, 'EMP-004', 'Kitchen Demo', NULL, NULL, '0900000004', 'kitchen@bepso.test', NULL, '079064496885', NULL, NULL, '2026-02-20', 'full_time', 'Kitchen', 9000000.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', 5, NULL),
(5, 1, 1, 5, 'EMP-005', 'Inventory Demo', NULL, NULL, '0900000005', 'inventory@bepso.test', NULL, '079083647414', NULL, NULL, '2026-02-20', 'full_time', 'Inventory Staff', 9000000.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', 6, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `min_stock_level` decimal(12,3) NOT NULL DEFAULT '0.000',
  `reorder_level` decimal(12,3) NOT NULL DEFAULT '0.000',
  `average_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `restaurant_id`, `branch_id`, `supplier_id`, `unit_id`, `name`, `sku`, `category_name`, `description`, `min_stock_level`, `reorder_level`, `average_cost`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 1, 'Thit bo', 'BEEF-001', 'Thit', NULL, 1000.000, 2000.000, 280.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', NULL),
(2, 1, 1, 1, 1, 'Banh pho', 'NOODLE-001', 'Kho', NULL, 2000.000, 5000.000, 40.00, 'active', '2026-05-19 22:20:34', '2026-05-20 12:27:01', NULL);

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

--
-- Dumping data for table `inventories`
--

INSERT INTO `inventories` (`id`, `restaurant_id`, `branch_id`, `ingredient_id`, `quantity_on_hand`, `theoretical_quantity`, `last_counted_at`, `last_cost`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 8000.000, 7800.000, '2026-05-20 19:27:01', 280.00, 5, '2026-05-19 22:20:34', '2026-05-20 12:27:01'),
(2, 1, 1, 2, 15000.000, 14900.000, '2026-05-20 19:27:01', 40.00, 5, '2026-05-19 22:20:34', '2026-05-20 12:27:01');

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
  `status` enum('holding','committed','released') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'holding',
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
  `type` enum('purchase','usage','adjustment','waste','return','stocktake') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('in','out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` decimal(12,3) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reference_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_file_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `leave_type` enum('annual','sick','unpaid','emergency','resignation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'annual',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
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
  `attachable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachable_id` bigint UNSIGNED DEFAULT NULL,
  `collection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `media_type` enum('image','document','video','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'image',
  `disk` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extension` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint UNSIGNED NOT NULL DEFAULT '0',
  `width` int UNSIGNED DEFAULT NULL,
  `height` int UNSIGNED DEFAULT NULL,
  `checksum_sha256` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
(26, '2026_05_20_131500_add_google_id_to_users_table', 2);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint UNSIGNED NOT NULL,
  `model_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(2, 'App\\Models\\Employee', 1),
(2, 'App\\Models\\User', 1),
(3, 'App\\Models\\Employee', 2),
(3, 'App\\Models\\User', 2),
(4, 'App\\Models\\Employee', 3),
(4, 'App\\Models\\User', 3),
(5, 'App\\Models\\Employee', 4),
(5, 'App\\Models\\User', 4),
(6, 'App\\Models\\Employee', 5),
(6, 'App\\Models\\User', 5),
(1, 'App\\Models\\User', 8),
(8, 'App\\Models\\User', 8);

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
  `order_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('dine_in','takeaway','delivery','qr') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dine_in',
  `status` enum('pending','confirmed','preparing','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','partial','paid','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `service_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `restaurant_id`, `branch_id`, `table_id`, `customer_id`, `created_by`, `cashier_user_id`, `order_number`, `channel`, `status`, `payment_status`, `subtotal`, `discount_amount`, `service_charge`, `tax_amount`, `total_amount`, `note`, `confirmed_at`, `completed_at`, `cancelled_at`, `cancelled_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 1, 3, 3, 'ORD-DEMO-001', 'dine_in', 'completed', 'paid', 100000.00, 0.00, 0.00, 0.00, 100000.00, NULL, '2026-05-20 19:07:01', '2026-05-20 19:22:01', NULL, NULL, '2026-05-19 22:20:35', '2026-05-20 12:27:01', NULL);

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
  `status` enum('pending','sent','preparing','served','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_to_kitchen_at` datetime DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `restaurant_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `discount_amount`, `line_total`, `status`, `notes`, `sent_to_kitchen_at`, `prepared_at`, `served_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1.00, 65000.00, 0.00, 65000.00, 'served', NULL, '2026-05-20 19:08:01', '2026-05-20 19:15:01', '2026-05-20 19:20:01', '2026-05-19 22:20:35', '2026-05-20 12:27:01'),
(2, 1, 1, 2, 1.00, 35000.00, 0.00, 35000.00, 'served', NULL, '2026-05-20 19:08:01', '2026-05-20 19:17:01', '2026-05-20 19:21:01', '2026-05-19 22:20:35', '2026-05-20 12:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `payment_method` enum('cash','bank_transfer','card','ewallet','mixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `status` enum('pending','paid','failed','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cash_received` decimal(12,2) DEFAULT NULL,
  `change_amount` decimal(12,2) DEFAULT NULL,
  `transaction_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gateway_transaction_code` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `restaurant_id`, `branch_id`, `order_id`, `processed_by`, `payment_method`, `status`, `amount`, `cash_received`, `change_amount`, `transaction_code`, `gateway_transaction_code`, `paid_at`, `meta`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 3, 'cash', 'paid', 100000.00, 100000.00, 0.00, 'PAY-DEMO-001', NULL, '2026-05-20 19:22:01', '{\"source\": \"demo-seeder\"}', '2026-05-19 22:20:35', '2026-05-20 12:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'manage_tenants', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(2, 'manage_restaurants', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(3, 'manage_staff', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(4, 'manage_menu', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(5, 'manage_inventory', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(6, 'create_order', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(7, 'view_order', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(8, 'payment_order', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(9, 'view_kitchen_order', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(10, 'update_food_status', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(11, 'view_report', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(12, 'manage_salary', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(13, 'manage_schedule', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(14, 'manage_feedback', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(15, 'view_audit_log', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `category_id` bigint UNSIGNED DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `restaurant_id`, `branch_id`, `category_id`, `code`, `name`, `slug`, `description`, `image_url`, `price`, `cost_price`, `preparation_time_minutes`, `is_active`, `is_available`, `is_featured`, `track_inventory`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 'PHO-BO', 'Pho bo tai', 'pho-bo-tai', 'Pho bo tai truyen thong', NULL, 65000.00, 28000.00, 12, 1, 1, 0, 1, '2026-05-19 22:20:34', '2026-05-20 12:27:01', NULL),
(2, 1, 1, 1, 'TRA-DAO', 'Tra dao', 'tra-dao', 'Tra dao mat lanh', NULL, 35000.00, 12000.00, 5, 1, 1, 0, 0, '2026-05-19 22:20:34', '2026-05-20 12:27:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `restaurant_id`, `branch_id`, `name`, `slug`, `description`, `display_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'Mon chinh', 'mon-chinh', 'Danh muc mon an chinh', 1, 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

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
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_recipes`
--

INSERT INTO `product_recipes` (`id`, `restaurant_id`, `product_id`, `ingredient_id`, `unit_id`, `quantity`, `waste_rate`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 120.000, 2.00, NULL, '2026-05-19 22:20:34', '2026-05-20 12:27:01'),
(2, 1, 1, 2, 1, 180.000, 1.00, NULL, '2026-05-19 22:20:35', '2026-05-20 12:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `pulse_aggregates`
--

CREATE TABLE `pulse_aggregates` (
  `id` bigint UNSIGNED NOT NULL,
  `bucket` int UNSIGNED NOT NULL,
  `period` mediumint UNSIGNED NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `aggregate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` decimal(20,2) NOT NULL,
  `count` int UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pulse_aggregates`
--

INSERT INTO `pulse_aggregates` (`id`, `bucket`, `period`, `type`, `key`, `aggregate`, `value`, `count`) VALUES
(4, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', 2.00, NULL),
(8, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 3.00, NULL),
(12, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', 12538.00, NULL),
(16, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 14231.00, NULL),
(20, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', 2.00, NULL),
(24, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', 5231.00, NULL),
(30, 1779251040, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(38, 1779251040, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 2158.00, NULL),
(44, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(48, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1316.00, NULL),
(54, 1779251040, 10080, 'cache_miss', 'aa3dec81a446685560671e3fda3b7bbc', 'count', 1.00, NULL),
(58, 1779251040, 10080, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', 'count', 3.00, NULL),
(68, 1779251040, 10080, 'slow_user_request', '1', 'count', 2.00, NULL),
(72, 1779251040, 10080, 'user_request', '1', 'count', 8.00, NULL),
(128, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 2.00, NULL),
(132, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 1751.00, NULL),
(136, 1779251040, 10080, 'slow_user_request', '6', 'count', 1.00, NULL),
(140, 1779251040, 10080, 'user_request', '6', 'count', 3.00, NULL),
(156, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(157, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(160, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2302.00, NULL),
(161, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2302.00, NULL),
(164, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', 1.00, NULL),
(165, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', 1.00, NULL),
(168, 1779279840, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'count', 1.00, NULL),
(169, 1779271200, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'count', 1.00, NULL),
(172, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', 3096.00, NULL),
(173, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', 3096.00, NULL),
(176, 1779279840, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'max', 1779281113.00, NULL),
(177, 1779271200, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'max', 1779281113.00, NULL),
(180, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(181, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(184, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1111.00, NULL),
(185, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1111.00, NULL),
(188, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', 1.00, NULL),
(189, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', 1.00, NULL),
(192, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', 1293.00, NULL),
(193, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', 1293.00, NULL),
(196, 1779281280, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 2.00, NULL),
(197, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 2.00, NULL),
(200, 1779281280, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 3622.00, NULL),
(201, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 3622.00, NULL),
(205, 1779281280, 1440, 'slow_user_request', '7', 'count', 1.00, NULL),
(206, 1779281280, 10080, 'slow_user_request', '7', 'count', 1.00, NULL),
(209, 1779281280, 1440, 'user_request', '7', 'count', 5.00, NULL),
(210, 1779281280, 10080, 'user_request', '7', 'count', 5.00, NULL),
(236, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', 1.00, NULL),
(237, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', 2.00, NULL),
(240, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', 1129.00, NULL),
(241, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', 1898.00, NULL),
(244, 1779282720, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(245, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 4.00, NULL),
(248, 1779282720, 1440, 'slow_user_request', '6', 'count', 1.00, NULL),
(249, 1779281280, 10080, 'slow_user_request', '6', 'count', 4.00, NULL),
(252, 1779282720, 1440, 'user_request', '6', 'count', 2.00, NULL),
(253, 1779281280, 10080, 'user_request', '6', 'count', 25.00, NULL),
(256, 1779282720, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2116.00, NULL),
(257, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2116.00, NULL),
(264, 1779284160, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 2.00, NULL),
(267, 1779284160, 1440, 'slow_user_request', '6', 'count', 2.00, NULL),
(270, 1779284160, 1440, 'user_request', '6', 'count', 11.00, NULL),
(276, 1779284160, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1501.00, NULL),
(332, 1779284160, 1440, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'count', 2.00, NULL),
(333, 1779281280, 10080, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'count', 2.00, NULL),
(336, 1779284160, 1440, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'max', 1779285215.00, NULL),
(337, 1779281280, 10080, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'max', 1779285215.00, NULL),
(340, 1779285600, 1440, 'user_request', '6', 'count', 10.00, NULL),
(380, 1779285600, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(381, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(384, 1779285600, 1440, 'slow_user_request', '8', 'count', 2.00, NULL),
(385, 1779281280, 10080, 'slow_user_request', '8', 'count', 3.00, NULL),
(388, 1779285600, 1440, 'user_request', '8', 'count', 18.00, NULL),
(389, 1779281280, 10080, 'user_request', '8', 'count', 22.00, NULL),
(392, 1779285600, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(393, 1779281280, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(396, 1779285600, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(397, 1779281280, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(400, 1779285600, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1255.00, NULL),
(401, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1255.00, NULL),
(412, 1779285600, 1440, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(413, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(424, 1779285600, 1440, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', 1205.00, NULL),
(425, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', 1205.00, NULL),
(458, 1779285600, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', 4.00, NULL),
(459, 1779281280, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', 4.00, NULL),
(464, 1779285600, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', 1779286749.00, NULL),
(465, 1779281280, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', 1779286749.00, NULL),
(524, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', 1.00, NULL),
(527, 1779289920, 1440, 'slow_user_request', '8', 'count', 1.00, NULL),
(530, 1779289920, 1440, 'user_request', '8', 'count', 4.00, NULL),
(536, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', 1898.00, NULL),
(552, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(555, 1779289920, 1440, 'slow_user_request', '6', 'count', 1.00, NULL),
(558, 1779289920, 1440, 'user_request', '6', 'count', 2.00, NULL),
(564, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1116.00, NULL),
(572, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'count', 2.00, NULL),
(573, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'count', 2.00, NULL),
(576, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'max', 2512.00, NULL),
(577, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'max', 2512.00, NULL),
(580, 1779291360, 1440, 'slow_user_request', '6', 'count', 5.00, NULL),
(581, 1779291360, 10080, 'slow_user_request', '6', 'count', 6.00, NULL),
(584, 1779291360, 1440, 'user_request', '6', 'count', 19.00, NULL),
(585, 1779291360, 10080, 'user_request', '6', 'count', 32.00, NULL),
(608, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(609, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', 1.00, NULL),
(612, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1114.00, NULL),
(613, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', 1114.00, NULL),
(616, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 3.00, NULL),
(617, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 5.00, NULL),
(628, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2678.00, NULL),
(629, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2678.00, NULL),
(640, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 2.00, NULL),
(641, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 3.00, NULL),
(644, 1779291360, 1440, 'slow_user_request', '8', 'count', 4.00, NULL),
(645, 1779291360, 10080, 'slow_user_request', '8', 'count', 4.00, NULL),
(648, 1779291360, 1440, 'user_request', '8', 'count', 10.00, NULL),
(649, 1779291360, 10080, 'user_request', '8', 'count', 25.00, NULL),
(652, 1779291360, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(653, 1779291360, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 12.00, NULL),
(656, 1779291360, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(657, 1779291360, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 20.00, NULL),
(660, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 2618.00, NULL),
(661, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 13455.00, NULL),
(672, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 1.00, NULL),
(673, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 1.00, NULL),
(684, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 1062.00, NULL),
(685, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 1062.00, NULL),
(688, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 1.00, NULL),
(689, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 1.00, NULL),
(692, 1779291360, 1440, 'slow_user_request', '9', 'count', 1.00, NULL),
(693, 1779291360, 10080, 'slow_user_request', '9', 'count', 1.00, NULL),
(696, 1779291360, 1440, 'user_request', '9', 'count', 8.00, NULL),
(697, 1779291360, 10080, 'user_request', '9', 'count', 14.00, NULL),
(700, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 2045.00, NULL),
(701, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 2045.00, NULL),
(752, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', 1.00, NULL),
(753, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', 1.00, NULL),
(764, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', 1285.00, NULL),
(765, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', 1285.00, NULL),
(792, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(793, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(804, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1200.00, NULL),
(805, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1200.00, NULL),
(846, 1779291360, 1440, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', 1.00, NULL),
(847, 1779291360, 10080, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', 3.00, NULL),
(850, 1779291360, 1440, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', 1.00, NULL),
(851, 1779291360, 10080, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', 5.00, NULL),
(880, 1779292800, 1440, 'user_request', '6', 'count', 10.00, NULL),
(920, 1779292800, 1440, 'user_request', '8', 'count', 5.00, NULL),
(923, 1779292800, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(926, 1779292800, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(948, 1779292800, 1440, 'user_request', '9', 'count', 2.00, NULL),
(951, 1779292800, 1440, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', 1.00, NULL),
(954, 1779292800, 1440, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', 1.00, NULL),
(964, 1779294240, 1440, 'user_request', '9', 'count', 4.00, NULL),
(972, 1779294240, 1440, 'user_request', '8', 'count', 10.00, NULL),
(975, 1779294240, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(978, 1779294240, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(994, 1779294240, 1440, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', 1.00, NULL),
(997, 1779294240, 1440, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', 3.00, NULL),
(1028, 1779294240, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1031, 1779294240, 1440, 'slow_user_request', '6', 'count', 1.00, NULL),
(1034, 1779294240, 1440, 'user_request', '6', 'count', 3.00, NULL),
(1040, 1779294240, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1000.00, NULL),
(1064, 1779297120, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(1067, 1779297120, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 12.00, NULL),
(1072, 1779297120, 1440, 'user_request', '10', 'count', 9.00, NULL),
(1073, 1779291360, 10080, 'user_request', '10', 'count', 17.00, NULL),
(1110, 1779297120, 1440, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(1111, 1779291360, 10080, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 3.00, NULL),
(1114, 1779297120, 1440, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(1115, 1779291360, 10080, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 3.00, NULL),
(1156, 1779298560, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1159, 1779298560, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1164, 1779298560, 1440, 'user_request', '10', 'count', 8.00, NULL),
(1167, 1779298560, 1440, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 2.00, NULL),
(1170, 1779298560, 1440, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 2.00, NULL),
(1200, 1779298560, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1204, 1779298560, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1006.00, NULL),
(1236, 1779300000, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1239, 1779300000, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1242, 1779300000, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(1243, 1779291360, 10080, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(1246, 1779300000, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1252, 1779300000, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 13455.00, NULL),
(1255, 1779300000, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779300318.00, NULL),
(1256, 1779291360, 10080, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779300318.00, NULL),
(1260, 1779304320, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1261, 1779301440, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1264, 1779304320, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2707.00, NULL),
(1265, 1779301440, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 2707.00, NULL),
(1268, 1779305760, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 7.00, NULL),
(1269, 1779301440, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 9.00, NULL),
(1272, 1779305760, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2309.00, NULL),
(1273, 1779301440, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 2309.00, NULL),
(1276, 1779305760, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 6.00, NULL),
(1277, 1779301440, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 6.00, NULL),
(1280, 1779305760, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 8.00, NULL),
(1281, 1779301440, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 8.00, NULL),
(1284, 1779305760, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 5.00, NULL),
(1285, 1779301440, 10080, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 5.00, NULL),
(1288, 1779305760, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 3070.00, NULL),
(1289, 1779301440, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 3070.00, NULL),
(1292, 1779305760, 1440, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779306744.00, NULL),
(1293, 1779301440, 10080, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779306744.00, NULL),
(1350, 1779305760, 1440, 'slow_user_request', '10', 'count', 1.00, NULL),
(1351, 1779301440, 10080, 'slow_user_request', '10', 'count', 1.00, NULL),
(1354, 1779305760, 1440, 'user_request', '10', 'count', 3.00, NULL),
(1355, 1779301440, 10080, 'user_request', '10', 'count', 3.00, NULL),
(1358, 1779305760, 1440, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(1359, 1779301440, 10080, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(1362, 1779305760, 1440, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(1363, 1779301440, 10080, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(1381, 1779305760, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(1382, 1779301440, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(1460, 1779305760, 1440, 'user_request', '8', 'count', 5.00, NULL),
(1461, 1779301440, 10080, 'user_request', '8', 'count', 5.00, NULL),
(1496, 1779307200, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 2.00, NULL),
(1499, 1779307200, 1440, 'slow_user_request', '11', 'count', 2.00, NULL),
(1500, 1779301440, 10080, 'slow_user_request', '11', 'count', 2.00, NULL),
(1503, 1779307200, 1440, 'user_request', '11', 'count', 5.00, NULL),
(1504, 1779301440, 10080, 'user_request', '11', 'count', 5.00, NULL),
(1508, 1779307200, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 1204.00, NULL),
(1538, 1779340200, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1539, 1779339960, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1540, 1779338880, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1541, 1779331680, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(1542, 1779340200, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 3139.00, NULL),
(1543, 1779339960, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 3139.00, NULL),
(1544, 1779338880, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 3139.00, NULL),
(1545, 1779331680, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 3139.00, NULL),
(1546, 1779340260, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1547, 1779339960, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1548, 1779338880, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1549, 1779331680, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', 1.00, NULL),
(1550, 1779340260, 60, 'slow_user_request', '11', 'count', 1.00, NULL),
(1551, 1779339960, 360, 'slow_user_request', '11', 'count', 1.00, NULL),
(1552, 1779338880, 1440, 'slow_user_request', '11', 'count', 1.00, NULL),
(1553, 1779331680, 10080, 'slow_user_request', '11', 'count', 1.00, NULL),
(1554, 1779340260, 60, 'user_request', '11', 'count', 3.00, NULL),
(1555, 1779339960, 360, 'user_request', '11', 'count', 3.00, NULL),
(1556, 1779338880, 1440, 'user_request', '11', 'count', 3.00, NULL),
(1557, 1779331680, 10080, 'user_request', '11', 'count', 3.00, NULL),
(1558, 1779340260, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 3530.00, NULL),
(1559, 1779339960, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 3530.00, NULL),
(1560, 1779338880, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 3530.00, NULL),
(1561, 1779331680, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', 3530.00, NULL),
(1570, 1779340260, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1571, 1779339960, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1572, 1779338880, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1573, 1779331680, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', 1.00, NULL),
(1574, 1779340260, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(1575, 1779339960, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(1576, 1779338880, 1440, 'slow_user_request', '8', 'count', 1.00, NULL),
(1577, 1779331680, 10080, 'slow_user_request', '8', 'count', 2.00, NULL),
(1578, 1779340260, 60, 'user_request', '8', 'count', 4.00, NULL),
(1579, 1779339960, 360, 'user_request', '8', 'count', 4.00, NULL),
(1580, 1779338880, 1440, 'user_request', '8', 'count', 4.00, NULL),
(1581, 1779331680, 10080, 'user_request', '8', 'count', 15.00, NULL),
(1582, 1779340260, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1583, 1779339960, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1584, 1779338880, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1585, 1779331680, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1586, 1779340260, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1587, 1779339960, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1588, 1779338880, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1589, 1779331680, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1590, 1779340260, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1839.00, NULL),
(1591, 1779339960, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1839.00, NULL),
(1592, 1779338880, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1839.00, NULL),
(1593, 1779331680, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', 1839.00, NULL),
(1606, 1779340320, 60, 'user_request', '8', 'count', 2.00, NULL),
(1607, 1779340320, 360, 'user_request', '8', 'count', 6.00, NULL),
(1608, 1779340320, 1440, 'user_request', '8', 'count', 11.00, NULL),
(1614, 1779340380, 60, 'user_request', '8', 'count', 4.00, NULL),
(1630, 1779340980, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'count', 1.00, NULL),
(1631, 1779340680, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'count', 1.00, NULL),
(1632, 1779340320, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'count', 1.00, NULL),
(1633, 1779331680, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'count', 1.00, NULL),
(1634, 1779340980, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(1635, 1779340680, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(1636, 1779340320, 1440, 'slow_user_request', '8', 'count', 1.00, NULL),
(1637, 1779340980, 60, 'user_request', '8', 'count', 2.00, NULL),
(1638, 1779340680, 360, 'user_request', '8', 'count', 2.00, NULL),
(1642, 1779340980, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'max', 6953.00, NULL),
(1643, 1779340680, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'max', 6953.00, NULL),
(1644, 1779340320, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'max', 6953.00, NULL),
(1645, 1779331680, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 'max', 6953.00, NULL),
(1650, 1779341460, 60, 'user_request', '8', 'count', 3.00, NULL),
(1651, 1779341400, 360, 'user_request', '8', 'count', 3.00, NULL),
(1662, 1779341760, 60, 'user_request', '8', 'count', 1.00, NULL),
(1663, 1779341760, 360, 'user_request', '8', 'count', 7.00, NULL),
(1664, 1779341760, 1440, 'user_request', '8', 'count', 1433.00, NULL),
(1665, 1779341760, 10080, 'user_request', '8', 'count', 1793.00, NULL),
(1666, 1779341820, 60, 'user_request', '8', 'count', 3.00, NULL),
(1678, 1779342000, 60, 'user_request', '8', 'count', 3.00, NULL),
(1686, 1779342000, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1687, 1779341760, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(1688, 1779341760, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(1689, 1779341760, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 24.00, NULL),
(1694, 1779342120, 60, 'user_request', '8', 'count', 2.00, NULL),
(1695, 1779342120, 360, 'user_request', '8', 'count', 5.00, NULL),
(1702, 1779342420, 60, 'user_request', '8', 'count', 3.00, NULL),
(1714, 1779342540, 60, 'user_request', '8', 'count', 1.00, NULL),
(1715, 1779342480, 360, 'user_request', '8', 'count', 535.00, NULL),
(1718, 1779342600, 60, 'user_request', '8', 'count', 95.00, NULL),
(1722, 1779342600, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1723, 1779342480, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1724, 1779341760, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1725, 1779341760, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 16.00, NULL),
(1726, 1779342600, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(1727, 1779342480, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(2106, 1779342660, 60, 'user_request', '8', 'count', 141.00, NULL),
(2670, 1779342720, 60, 'user_request', '8', 'count', 147.00, NULL),
(3258, 1779342780, 60, 'user_request', '8', 'count', 151.00, NULL),
(3862, 1779342840, 60, 'user_request', '8', 'count', 141.00, NULL),
(3863, 1779342840, 360, 'user_request', '8', 'count', 886.00, NULL),
(4426, 1779342900, 60, 'user_request', '8', 'count', 147.00, NULL),
(5014, 1779342960, 60, 'user_request', '8', 'count', 155.00, NULL),
(5634, 1779343020, 60, 'user_request', '8', 'count', 152.00, NULL),
(6242, 1779343080, 60, 'user_request', '8', 'count', 147.00, NULL),
(6830, 1779343140, 60, 'user_request', '8', 'count', 144.00, NULL),
(7406, 1779343200, 60, 'user_request', '8', 'count', 78.00, NULL),
(7407, 1779343200, 360, 'user_request', '8', 'count', 230.00, NULL),
(7408, 1779343200, 1440, 'user_request', '8', 'count', 256.00, NULL),
(7414, 1779343200, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(7415, 1779343200, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(7416, 1779343200, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(7417, 1779343200, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(7418, 1779343200, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(7419, 1779343200, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(7726, 1779343260, 60, 'user_request', '8', 'count', 148.00, NULL),
(8318, 1779343320, 60, 'user_request', '8', 'count', 3.00, NULL),
(8322, 1779343320, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8323, 1779343320, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8338, 1779343440, 60, 'user_request', '8', 'count', 1.00, NULL),
(8342, 1779343620, 60, 'user_request', '8', 'count', 4.00, NULL),
(8343, 1779343560, 360, 'user_request', '8', 'count', 12.00, NULL),
(8350, 1779343620, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8351, 1779343560, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(8352, 1779343620, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8353, 1779343560, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(8366, 1779343680, 60, 'user_request', '8', 'count', 3.00, NULL),
(8378, 1779343740, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8379, 1779343740, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(8386, 1779343740, 60, 'user_request', '8', 'count', 2.00, NULL),
(8398, 1779343800, 60, 'user_request', '8', 'count', 3.00, NULL),
(8410, 1779343920, 60, 'user_request', '8', 'count', 3.00, NULL),
(8411, 1779343920, 360, 'user_request', '8', 'count', 9.00, NULL),
(8422, 1779344040, 60, 'user_request', '8', 'count', 4.00, NULL),
(8438, 1779344100, 60, 'user_request', '8', 'count', 2.00, NULL),
(8446, 1779344520, 60, 'user_request', '8', 'count', 1.00, NULL),
(8447, 1779344280, 360, 'user_request', '8', 'count', 5.00, NULL),
(8450, 1779344580, 60, 'user_request', '8', 'count', 4.00, NULL),
(8466, 1779344640, 60, 'user_request', '8', 'count', 2.00, NULL),
(8467, 1779344640, 360, 'user_request', '8', 'count', 9.00, NULL),
(8468, 1779344640, 1440, 'user_request', '8', 'count', 33.00, NULL),
(8474, 1779344700, 60, 'user_request', '8', 'count', 1.00, NULL),
(8478, 1779344760, 60, 'user_request', '8', 'count', 4.00, NULL),
(8494, 1779344940, 60, 'user_request', '8', 'count', 2.00, NULL),
(8502, 1779345000, 60, 'user_request', '8', 'count', 2.00, NULL),
(8503, 1779345000, 360, 'user_request', '8', 'count', 10.00, NULL),
(8504, 1779345000, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8505, 1779345000, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8506, 1779344640, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(8507, 1779345000, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8508, 1779345000, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8509, 1779344640, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 3.00, NULL),
(8518, 1779345180, 60, 'user_request', '8', 'count', 6.00, NULL),
(8542, 1779345300, 60, 'user_request', '8', 'count', 2.00, NULL),
(8550, 1779345360, 60, 'user_request', '8', 'count', 1.00, NULL),
(8551, 1779345360, 360, 'user_request', '8', 'count', 10.00, NULL),
(8554, 1779345420, 60, 'user_request', '8', 'count', 3.00, NULL),
(8558, 1779345420, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8559, 1779345360, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8560, 1779345420, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8561, 1779345360, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8574, 1779345540, 60, 'user_request', '8', 'count', 3.00, NULL),
(8586, 1779345600, 60, 'user_request', '8', 'count', 3.00, NULL),
(8598, 1779345840, 60, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8599, 1779345720, 360, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8600, 1779344640, 1440, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8601, 1779341760, 10080, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8602, 1779345840, 60, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8603, 1779345720, 360, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8604, 1779344640, 1440, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8605, 1779341760, 10080, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', 'count', 1.00, NULL),
(8606, 1779345900, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 1.00, NULL),
(8607, 1779345720, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 1.00, NULL),
(8608, 1779344640, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 1.00, NULL),
(8609, 1779341760, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', 1.00, NULL),
(8610, 1779345900, 60, 'slow_user_request', '12', 'count', 1.00, NULL),
(8611, 1779345720, 360, 'slow_user_request', '12', 'count', 1.00, NULL),
(8612, 1779344640, 1440, 'slow_user_request', '12', 'count', 1.00, NULL),
(8613, 1779341760, 10080, 'slow_user_request', '12', 'count', 1.00, NULL),
(8614, 1779345900, 60, 'user_request', '12', 'count', 6.00, NULL),
(8615, 1779345720, 360, 'user_request', '12', 'count', 6.00, NULL),
(8616, 1779344640, 1440, 'user_request', '12', 'count', 6.00, NULL),
(8617, 1779341760, 10080, 'user_request', '12', 'count', 6.00, NULL),
(8618, 1779345900, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 1213.00, NULL),
(8619, 1779345720, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 1213.00, NULL),
(8620, 1779344640, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 1213.00, NULL),
(8621, 1779341760, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', 1213.00, NULL),
(8642, 1779345900, 60, 'user_request', '8', 'count', 1.00, NULL),
(8643, 1779345720, 360, 'user_request', '8', 'count', 4.00, NULL),
(8644, 1779345900, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8645, 1779345720, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8646, 1779345960, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8647, 1779345720, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8654, 1779345960, 60, 'user_request', '8', 'count', 3.00, NULL),
(8666, 1779346080, 60, 'user_request', '8', 'count', 1.00, NULL),
(8667, 1779346080, 360, 'user_request', '8', 'count', 20.00, NULL),
(8668, 1779346080, 1440, 'user_request', '8', 'count', 26.00, NULL),
(8670, 1779346200, 60, 'user_request', '8', 'count', 1.00, NULL),
(8674, 1779346320, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(8675, 1779346080, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(8676, 1779346080, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(8677, 1779341760, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 2.00, NULL),
(8678, 1779346320, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1750.00, NULL),
(8679, 1779346080, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1750.00, NULL),
(8680, 1779346080, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1750.00, NULL),
(8681, 1779341760, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1750.00, NULL),
(8682, 1779346320, 60, 'user_request', '8', 'count', 5.00, NULL),
(8694, 1779346320, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8695, 1779346080, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8696, 1779346080, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(8697, 1779346320, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8698, 1779346080, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8699, 1779346080, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(8710, 1779346380, 60, 'user_request', '8', 'count', 13.00, NULL),
(8762, 1779346440, 60, 'user_request', '8', 'count', 2.00, NULL),
(8763, 1779346440, 360, 'user_request', '8', 'count', 6.00, NULL),
(8770, 1779346620, 60, 'user_request', '8', 'count', 2.00, NULL),
(8771, 1779346620, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8772, 1779346440, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8773, 1779346620, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8774, 1779346440, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8786, 1779346680, 60, 'user_request', '8', 'count', 2.00, NULL),
(8794, 1779347520, 60, 'user_request', '8', 'count', 3.00, NULL),
(8795, 1779347520, 360, 'user_request', '8', 'count', 12.00, NULL),
(8796, 1779347520, 1440, 'user_request', '8', 'count', 26.00, NULL),
(8797, 1779347520, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8798, 1779347520, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(8799, 1779347520, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(8800, 1779347520, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8801, 1779347520, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(8802, 1779347520, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL),
(8814, 1779347700, 60, 'user_request', '8', 'count', 3.00, NULL),
(8826, 1779347760, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(8827, 1779347520, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL),
(8828, 1779347520, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', 1.00, NULL);
INSERT INTO `pulse_aggregates` (`id`, `bucket`, `period`, `type`, `key`, `aggregate`, `value`, `count`) VALUES
(8829, 1779347760, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(8830, 1779347520, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(8831, 1779347520, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 2.00, NULL),
(8832, 1779341760, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 2.00, NULL),
(8834, 1779347760, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1603.00, NULL),
(8835, 1779347520, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1603.00, NULL),
(8836, 1779347520, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', 1603.00, NULL),
(8837, 1779347760, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 1221.00, NULL),
(8838, 1779347520, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 1221.00, NULL),
(8839, 1779347520, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 2045.00, NULL),
(8840, 1779341760, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 2045.00, NULL),
(8842, 1779347820, 60, 'user_request', '8', 'count', 6.00, NULL),
(8858, 1779347820, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8859, 1779347820, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8874, 1779348180, 60, 'user_request', '8', 'count', 6.00, NULL),
(8875, 1779347880, 360, 'user_request', '8', 'count', 6.00, NULL),
(8890, 1779348180, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8891, 1779347880, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8892, 1779348180, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8893, 1779347880, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8906, 1779348660, 60, 'user_request', '8', 'count', 1.00, NULL),
(8907, 1779348600, 360, 'user_request', '8', 'count', 8.00, NULL),
(8910, 1779348720, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'count', 1.00, NULL),
(8911, 1779348600, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'count', 1.00, NULL),
(8912, 1779347520, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'count', 1.00, NULL),
(8913, 1779341760, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'count', 1.00, NULL),
(8914, 1779348720, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(8915, 1779348600, 360, 'slow_user_request', '8', 'count', 1.00, NULL),
(8916, 1779347520, 1440, 'slow_user_request', '8', 'count', 1.00, NULL),
(8917, 1779341760, 10080, 'slow_user_request', '8', 'count', 3.00, NULL),
(8918, 1779348720, 60, 'user_request', '8', 'count', 5.00, NULL),
(8919, 1779348720, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(8920, 1779348600, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', 1.00, NULL),
(8926, 1779348720, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'max', 2428.00, NULL),
(8927, 1779348600, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'max', 2428.00, NULL),
(8928, 1779347520, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'max', 2428.00, NULL),
(8929, 1779341760, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 'max', 2428.00, NULL),
(8930, 1779348720, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 2045.00, NULL),
(8931, 1779348600, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', 2045.00, NULL),
(8942, 1779348720, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8943, 1779348600, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8944, 1779348720, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8945, 1779348600, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(8958, 1779348780, 60, 'user_request', '8', 'count', 1.00, NULL),
(8962, 1779348840, 60, 'user_request', '8', 'count', 1.00, NULL),
(8966, 1779348960, 60, 'user_request', '8', 'count', 4.00, NULL),
(8967, 1779348960, 360, 'user_request', '8', 'count', 19.00, NULL),
(8968, 1779348960, 1440, 'user_request', '8', 'count', 19.00, NULL),
(8978, 1779348960, 60, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 1.00, NULL),
(8979, 1779348960, 360, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 2.00, NULL),
(8980, 1779348960, 1440, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 2.00, NULL),
(8981, 1779341760, 10080, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 2.00, NULL),
(8982, 1779348960, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(8983, 1779348960, 360, 'slow_user_request', '8', 'count', 2.00, NULL),
(8984, 1779348960, 1440, 'slow_user_request', '8', 'count', 2.00, NULL),
(8985, 1779348960, 60, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(8986, 1779348960, 360, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 2.00, NULL),
(8987, 1779348960, 1440, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 2.00, NULL),
(8988, 1779341760, 10080, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 2.00, NULL),
(8994, 1779348960, 60, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 5129.00, NULL),
(8995, 1779348960, 360, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 5129.00, NULL),
(8996, 1779348960, 1440, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 5129.00, NULL),
(8997, 1779341760, 10080, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 5129.00, NULL),
(8998, 1779348960, 60, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779349016.00, NULL),
(8999, 1779348960, 360, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779349041.00, NULL),
(9000, 1779348960, 1440, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779349041.00, NULL),
(9001, 1779341760, 10080, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779349041.00, NULL),
(9002, 1779349020, 60, 'user_request', '8', 'count', 5.00, NULL),
(9003, 1779349020, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(9004, 1779348960, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(9005, 1779348960, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 2.00, NULL),
(9006, 1779349020, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(9007, 1779348960, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(9008, 1779348960, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 6.00, NULL),
(9026, 1779349020, 60, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', 1.00, NULL),
(9027, 1779349020, 60, 'slow_user_request', '8', 'count', 1.00, NULL),
(9028, 1779349020, 60, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'count', 1.00, NULL),
(9042, 1779349020, 60, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', 3031.00, NULL),
(9043, 1779349020, 60, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 'max', 1779349041.00, NULL),
(9050, 1779349080, 60, 'user_request', '8', 'count', 4.00, NULL),
(9051, 1779349080, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(9052, 1779349080, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 1.00, NULL),
(9074, 1779349140, 60, 'user_request', '8', 'count', 6.00, NULL),
(9075, 1779349140, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', 4.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pulse_entries`
--

CREATE TABLE `pulse_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `timestamp` int UNSIGNED NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pulse_entries`
--

INSERT INTO `pulse_entries` (`id`, `timestamp`, `type`, `key`, `value`) VALUES
(1, 1779254485, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 14231),
(2, 1779254487, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 12538),
(3, 1779254609, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 5231),
(4, 1779254720, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 2795),
(5, 1779254722, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 2158),
(6, 1779255317, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 1316),
(7, 1779255335, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 2053),
(8, 1779255336, 'cache_miss', 'aa3dec81a446685560671e3fda3b7bbc', NULL),
(9, 1779255337, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', NULL),
(10, 1779255358, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1172),
(11, 1779255358, 'slow_user_request', '1', NULL),
(12, 1779255358, 'user_request', '1', NULL),
(13, 1779255358, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', NULL),
(14, 1779255359, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', NULL),
(15, 1779255360, 'user_request', '1', NULL),
(16, 1779255367, 'user_request', '1', NULL),
(17, 1779255380, 'user_request', '1', NULL),
(18, 1779255383, 'user_request', '1', NULL),
(19, 1779255432, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 2889),
(20, 1779255432, 'slow_user_request', '1', NULL),
(21, 1779255432, 'user_request', '1', NULL),
(22, 1779255435, 'user_request', '1', NULL),
(23, 1779255438, 'user_request', '1', NULL),
(24, 1779255484, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 1751),
(25, 1779255495, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 1426),
(26, 1779255495, 'slow_user_request', '6', NULL),
(27, 1779255495, 'user_request', '6', NULL),
(28, 1779255497, 'user_request', '6', NULL),
(29, 1779255502, 'user_request', '6', NULL),
(30, 1779281015, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 2302),
(31, 1779281112, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 3096),
(32, 1779281113, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 1779281113),
(33, 1779281190, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 1111),
(34, 1779281483, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 1293),
(35, 1779281510, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 3622),
(36, 1779281552, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 1249),
(37, 1779281552, 'slow_user_request', '7', NULL),
(38, 1779281552, 'user_request', '7', NULL),
(39, 1779281553, 'user_request', '7', NULL),
(40, 1779281558, 'user_request', '7', NULL),
(41, 1779281820, 'user_request', '7', NULL),
(42, 1779281823, 'user_request', '7', NULL),
(43, 1779281834, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 1129),
(44, 1779283469, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 2116),
(45, 1779283469, 'slow_user_request', '6', NULL),
(46, 1779283469, 'user_request', '6', NULL),
(47, 1779283471, 'user_request', '6', NULL),
(48, 1779284663, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1501),
(49, 1779284663, 'slow_user_request', '6', NULL),
(50, 1779284663, 'user_request', '6', NULL),
(51, 1779284664, 'user_request', '6', NULL),
(52, 1779284675, 'user_request', '6', NULL),
(53, 1779284686, 'user_request', '6', NULL),
(54, 1779284687, 'user_request', '6', NULL),
(55, 1779284733, 'user_request', '6', NULL),
(56, 1779284764, 'user_request', '6', NULL),
(57, 1779284769, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1009),
(58, 1779284769, 'slow_user_request', '6', NULL),
(59, 1779284769, 'user_request', '6', NULL),
(60, 1779284770, 'user_request', '6', NULL),
(61, 1779284774, 'user_request', '6', NULL),
(62, 1779284778, 'user_request', '6', NULL),
(63, 1779285215, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 1779285215),
(64, 1779285215, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 1779285215),
(65, 1779285795, 'user_request', '6', NULL),
(66, 1779285796, 'user_request', '6', NULL),
(67, 1779285798, 'user_request', '6', NULL),
(68, 1779285801, 'user_request', '6', NULL),
(69, 1779285805, 'user_request', '6', NULL),
(70, 1779285812, 'user_request', '6', NULL),
(71, 1779285813, 'user_request', '6', NULL),
(72, 1779285893, 'user_request', '6', NULL),
(73, 1779285894, 'user_request', '6', NULL),
(74, 1779285897, 'user_request', '6', NULL),
(75, 1779285948, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1255),
(76, 1779285948, 'slow_user_request', '8', NULL),
(77, 1779285948, 'user_request', '8', NULL),
(78, 1779285948, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(79, 1779285949, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(80, 1779285949, 'user_request', '8', NULL),
(81, 1779285961, 'user_request', '8', NULL),
(82, 1779286590, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 1205),
(83, 1779286590, 'slow_user_request', '8', NULL),
(84, 1779286590, 'user_request', '8', NULL),
(85, 1779286591, 'user_request', '8', NULL),
(86, 1779286597, 'user_request', '8', NULL),
(87, 1779286603, 'user_request', '8', NULL),
(88, 1779286603, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(89, 1779286604, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(90, 1779286604, 'user_request', '8', NULL),
(91, 1779286619, 'user_request', '8', NULL),
(92, 1779286690, 'user_request', '8', NULL),
(93, 1779286691, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 1779286691),
(94, 1779286699, 'user_request', '8', NULL),
(95, 1779286707, 'user_request', '8', NULL),
(96, 1779286708, 'user_request', '8', NULL),
(97, 1779286712, 'user_request', '8', NULL),
(98, 1779286712, 'user_request', '8', NULL),
(99, 1779286720, 'user_request', '8', NULL),
(100, 1779286720, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 1779286720),
(101, 1779286734, 'user_request', '8', NULL),
(102, 1779286734, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 1779286734),
(103, 1779286749, 'user_request', '8', NULL),
(104, 1779286749, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 1779286749),
(105, 1779291238, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 1898),
(106, 1779291238, 'slow_user_request', '8', NULL),
(107, 1779291238, 'user_request', '8', NULL),
(108, 1779291240, 'user_request', '8', NULL),
(109, 1779291244, 'user_request', '8', NULL),
(110, 1779291246, 'user_request', '8', NULL),
(111, 1779291250, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1116),
(112, 1779291250, 'slow_user_request', '6', NULL),
(113, 1779291250, 'user_request', '6', NULL),
(114, 1779291251, 'user_request', '6', NULL),
(115, 1779291498, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 1493),
(116, 1779291520, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 2512),
(117, 1779291520, 'slow_user_request', '6', NULL),
(118, 1779291520, 'user_request', '6', NULL),
(119, 1779291524, 'user_request', '6', NULL),
(120, 1779291529, 'user_request', '6', NULL),
(121, 1779291537, 'user_request', '6', NULL),
(122, 1779291554, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 1114),
(123, 1779291564, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 2678),
(124, 1779291564, 'slow_user_request', '6', NULL),
(125, 1779291564, 'user_request', '6', NULL),
(126, 1779291567, 'user_request', '6', NULL),
(127, 1779291572, 'user_request', '6', NULL),
(128, 1779291577, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 2515),
(129, 1779291577, 'slow_user_request', '8', NULL),
(130, 1779291577, 'user_request', '8', NULL),
(131, 1779291578, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(132, 1779291580, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(133, 1779291580, 'user_request', '8', NULL),
(134, 1779291583, 'user_request', '8', NULL),
(135, 1779291640, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 1062),
(136, 1779291640, 'slow_user_request', '8', NULL),
(137, 1779291640, 'user_request', '8', NULL),
(138, 1779291670, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 2045),
(139, 1779291670, 'slow_user_request', '9', NULL),
(140, 1779291670, 'user_request', '9', NULL),
(141, 1779291672, 'user_request', '9', NULL),
(142, 1779291680, 'user_request', '9', NULL),
(143, 1779291689, 'user_request', '9', NULL),
(144, 1779291699, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 2618),
(145, 1779291699, 'slow_user_request', '8', NULL),
(146, 1779291699, 'user_request', '8', NULL),
(147, 1779291700, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(148, 1779291701, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(149, 1779291702, 'user_request', '8', NULL),
(150, 1779291704, 'user_request', '8', NULL),
(151, 1779291707, 'user_request', '8', NULL),
(152, 1779291809, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 1285),
(153, 1779291809, 'slow_user_request', '8', NULL),
(154, 1779291809, 'user_request', '8', NULL),
(155, 1779291811, 'user_request', '8', NULL),
(156, 1779291817, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 2311),
(157, 1779291817, 'slow_user_request', '6', NULL),
(158, 1779291817, 'user_request', '6', NULL),
(159, 1779291820, 'user_request', '6', NULL),
(160, 1779291836, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 1200),
(161, 1779291836, 'slow_user_request', '6', NULL),
(162, 1779291836, 'user_request', '6', NULL),
(163, 1779292251, 'user_request', '6', NULL),
(164, 1779292437, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1161),
(165, 1779292437, 'slow_user_request', '6', NULL),
(166, 1779292437, 'user_request', '6', NULL),
(167, 1779292438, 'user_request', '6', NULL),
(168, 1779292442, 'user_request', '6', NULL),
(169, 1779292563, 'user_request', '6', NULL),
(170, 1779292568, 'user_request', '6', NULL),
(171, 1779292573, 'user_request', '9', NULL),
(172, 1779292573, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', NULL),
(173, 1779292573, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', NULL),
(174, 1779292574, 'user_request', '9', NULL),
(175, 1779292576, 'user_request', '9', NULL),
(176, 1779292589, 'user_request', '9', NULL),
(177, 1779292593, 'user_request', '6', NULL),
(178, 1779292594, 'user_request', '6', NULL),
(179, 1779292611, 'user_request', '6', NULL),
(180, 1779292806, 'user_request', '6', NULL),
(181, 1779292834, 'user_request', '6', NULL),
(182, 1779292837, 'user_request', '6', NULL),
(183, 1779292837, 'user_request', '6', NULL),
(184, 1779292839, 'user_request', '6', NULL),
(185, 1779292844, 'user_request', '6', NULL),
(186, 1779292845, 'user_request', '6', NULL),
(187, 1779292855, 'user_request', '6', NULL),
(188, 1779292982, 'user_request', '6', NULL),
(189, 1779293014, 'user_request', '6', NULL),
(190, 1779293019, 'user_request', '8', NULL),
(191, 1779293019, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(192, 1779293020, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(193, 1779293020, 'user_request', '8', NULL),
(194, 1779293025, 'user_request', '8', NULL),
(195, 1779293032, 'user_request', '8', NULL),
(196, 1779293033, 'user_request', '8', NULL),
(197, 1779293039, 'user_request', '9', NULL),
(198, 1779293039, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', NULL),
(199, 1779293040, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', NULL),
(200, 1779293040, 'user_request', '9', NULL),
(201, 1779294734, 'user_request', '9', NULL),
(202, 1779294748, 'user_request', '9', NULL),
(203, 1779294757, 'user_request', '8', NULL),
(204, 1779294757, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(205, 1779294758, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(206, 1779294758, 'user_request', '8', NULL),
(207, 1779294761, 'user_request', '8', NULL),
(208, 1779295210, 'user_request', '8', NULL),
(209, 1779295211, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', NULL),
(210, 1779295211, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', NULL),
(211, 1779295211, 'user_request', '8', NULL),
(212, 1779295214, 'user_request', '8', NULL),
(213, 1779295220, 'user_request', '8', NULL),
(214, 1779295221, 'user_request', '8', NULL),
(215, 1779295229, 'user_request', '8', NULL),
(216, 1779295234, 'user_request', '8', NULL),
(217, 1779295238, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1000),
(218, 1779295238, 'slow_user_request', '6', NULL),
(219, 1779295238, 'user_request', '6', NULL),
(220, 1779295239, 'user_request', '6', NULL),
(221, 1779295258, 'user_request', '6', NULL),
(222, 1779295263, 'user_request', '9', NULL),
(223, 1779295263, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', NULL),
(224, 1779295264, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', NULL),
(225, 1779295264, 'user_request', '9', NULL),
(226, 1779298036, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(227, 1779298036, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(228, 1779298180, 'user_request', '10', NULL),
(229, 1779298181, 'user_request', '10', NULL),
(230, 1779298192, 'user_request', '10', NULL),
(231, 1779298192, 'user_request', '10', NULL),
(232, 1779298200, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(233, 1779298200, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(234, 1779298328, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(235, 1779298328, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(236, 1779298331, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(237, 1779298331, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(238, 1779298388, 'user_request', '10', NULL),
(239, 1779298389, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(240, 1779298389, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(241, 1779298389, 'user_request', '10', NULL),
(242, 1779298454, 'user_request', '10', NULL),
(243, 1779298458, 'user_request', '10', NULL),
(244, 1779298458, 'user_request', '10', NULL),
(245, 1779298475, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(246, 1779298475, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(247, 1779298478, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(248, 1779298478, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(249, 1779298519, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(250, 1779298519, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(251, 1779298523, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(252, 1779298523, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(253, 1779298655, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(254, 1779298655, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(255, 1779298672, 'user_request', '10', NULL),
(256, 1779298672, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(257, 1779298673, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(258, 1779298673, 'user_request', '10', NULL),
(259, 1779299318, 'user_request', '10', NULL),
(260, 1779299318, 'user_request', '10', NULL),
(261, 1779299324, 'user_request', '10', NULL),
(262, 1779299331, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(263, 1779299331, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(264, 1779299334, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1006),
(265, 1779299391, 'user_request', '10', NULL),
(266, 1779299391, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(267, 1779299391, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(268, 1779299392, 'user_request', '10', NULL),
(269, 1779299395, 'user_request', '10', NULL),
(270, 1779299771, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(271, 1779299772, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(272, 1779300317, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 13455),
(273, 1779300318, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(274, 1779300318, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779300318),
(275, 1779300330, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(276, 1779305320, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 2707),
(277, 1779305990, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 2309),
(278, 1779306082, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1151),
(279, 1779306082, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(280, 1779306082, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779306082),
(281, 1779306083, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(282, 1779306093, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1102),
(283, 1779306116, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1243),
(284, 1779306116, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(285, 1779306116, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779306116),
(286, 1779306117, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(287, 1779306265, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1035),
(288, 1779306270, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1004),
(289, 1779306274, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1068),
(290, 1779306281, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1023),
(291, 1779306281, 'slow_user_request', '10', NULL),
(292, 1779306281, 'user_request', '10', NULL),
(293, 1779306282, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(294, 1779306282, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(295, 1779306282, 'user_request', '10', NULL),
(296, 1779306287, 'user_request', '10', NULL),
(297, 1779306327, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 2406),
(298, 1779306327, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(299, 1779306327, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779306327),
(300, 1779306329, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(301, 1779306634, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 2400),
(302, 1779306635, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(303, 1779306635, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779306635),
(304, 1779306637, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(305, 1779306744, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 3070),
(306, 1779306744, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(307, 1779306744, 'exception', '[\"RuntimeException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779306744),
(308, 1779306747, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(309, 1779306750, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1011),
(310, 1779306965, 'user_request', '8', NULL),
(311, 1779306966, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(312, 1779306966, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(313, 1779306966, 'user_request', '8', NULL),
(314, 1779306973, 'user_request', '8', NULL),
(315, 1779307011, 'user_request', '8', NULL),
(316, 1779307015, 'user_request', '8', NULL),
(317, 1779307021, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1230),
(318, 1779307390, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1204),
(319, 1779307390, 'slow_user_request', '11', NULL),
(320, 1779307390, 'user_request', '11', NULL),
(321, 1779307391, 'user_request', '11', NULL),
(322, 1779307395, 'user_request', '11', NULL),
(323, 1779307399, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 1001),
(324, 1779307399, 'slow_user_request', '11', NULL),
(325, 1779307399, 'user_request', '11', NULL),
(326, 1779307400, 'user_request', '11', NULL),
(327, 1779340248, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 3139),
(328, 1779340260, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 3530),
(329, 1779340260, 'slow_user_request', '11', NULL),
(330, 1779340260, 'user_request', '11', NULL),
(331, 1779340264, 'user_request', '11', NULL),
(332, 1779340281, 'user_request', '11', NULL),
(333, 1779340295, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 1839),
(334, 1779340295, 'slow_user_request', '8', NULL),
(335, 1779340295, 'user_request', '8', NULL),
(336, 1779340295, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(337, 1779340297, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(338, 1779340297, 'user_request', '8', NULL),
(339, 1779340311, 'user_request', '8', NULL),
(340, 1779340316, 'user_request', '8', NULL),
(341, 1779340320, 'user_request', '8', NULL),
(342, 1779340341, 'user_request', '8', NULL),
(343, 1779340407, 'user_request', '8', NULL),
(344, 1779340409, 'user_request', '8', NULL),
(345, 1779340422, 'user_request', '8', NULL),
(346, 1779340436, 'user_request', '8', NULL),
(347, 1779340993, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@index\"]', 6953),
(348, 1779340993, 'slow_user_request', '8', NULL),
(349, 1779340993, 'user_request', '8', NULL),
(350, 1779341005, 'user_request', '8', NULL),
(351, 1779341476, 'user_request', '8', NULL),
(352, 1779341479, 'user_request', '8', NULL),
(353, 1779341486, 'user_request', '8', NULL),
(354, 1779341780, 'user_request', '8', NULL),
(355, 1779341830, 'user_request', '8', NULL),
(356, 1779341838, 'user_request', '8', NULL),
(357, 1779341842, 'user_request', '8', NULL),
(358, 1779342003, 'user_request', '8', NULL),
(359, 1779342006, 'user_request', '8', NULL),
(360, 1779342050, 'user_request', '8', NULL),
(361, 1779342050, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(362, 1779342051, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(363, 1779342155, 'user_request', '8', NULL),
(364, 1779342156, 'user_request', '8', NULL),
(365, 1779342451, 'user_request', '8', NULL),
(366, 1779342455, 'user_request', '8', NULL),
(367, 1779342475, 'user_request', '8', NULL),
(368, 1779342587, 'user_request', '8', NULL),
(369, 1779342605, 'user_request', '8', NULL),
(370, 1779342619, 'user_request', '8', NULL),
(371, 1779342620, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(372, 1779342620, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(373, 1779342620, 'user_request', '8', NULL),
(374, 1779342621, 'user_request', '8', NULL),
(375, 1779342621, 'user_request', '8', NULL),
(376, 1779342622, 'user_request', '8', NULL),
(377, 1779342622, 'user_request', '8', NULL),
(378, 1779342623, 'user_request', '8', NULL),
(379, 1779342623, 'user_request', '8', NULL),
(380, 1779342623, 'user_request', '8', NULL),
(381, 1779342624, 'user_request', '8', NULL),
(382, 1779342624, 'user_request', '8', NULL),
(383, 1779342625, 'user_request', '8', NULL),
(384, 1779342625, 'user_request', '8', NULL),
(385, 1779342626, 'user_request', '8', NULL),
(386, 1779342626, 'user_request', '8', NULL),
(387, 1779342626, 'user_request', '8', NULL),
(388, 1779342627, 'user_request', '8', NULL),
(389, 1779342627, 'user_request', '8', NULL),
(390, 1779342628, 'user_request', '8', NULL),
(391, 1779342628, 'user_request', '8', NULL),
(392, 1779342628, 'user_request', '8', NULL),
(393, 1779342629, 'user_request', '8', NULL),
(394, 1779342629, 'user_request', '8', NULL),
(395, 1779342630, 'user_request', '8', NULL),
(396, 1779342630, 'user_request', '8', NULL),
(397, 1779342631, 'user_request', '8', NULL),
(398, 1779342631, 'user_request', '8', NULL),
(399, 1779342631, 'user_request', '8', NULL),
(400, 1779342632, 'user_request', '8', NULL),
(401, 1779342632, 'user_request', '8', NULL),
(402, 1779342633, 'user_request', '8', NULL),
(403, 1779342633, 'user_request', '8', NULL),
(404, 1779342634, 'user_request', '8', NULL),
(405, 1779342634, 'user_request', '8', NULL),
(406, 1779342634, 'user_request', '8', NULL),
(407, 1779342635, 'user_request', '8', NULL),
(408, 1779342635, 'user_request', '8', NULL),
(409, 1779342636, 'user_request', '8', NULL),
(410, 1779342636, 'user_request', '8', NULL),
(411, 1779342637, 'user_request', '8', NULL),
(412, 1779342637, 'user_request', '8', NULL),
(413, 1779342637, 'user_request', '8', NULL),
(414, 1779342638, 'user_request', '8', NULL),
(415, 1779342638, 'user_request', '8', NULL),
(416, 1779342639, 'user_request', '8', NULL),
(417, 1779342639, 'user_request', '8', NULL),
(418, 1779342640, 'user_request', '8', NULL),
(419, 1779342640, 'user_request', '8', NULL),
(420, 1779342640, 'user_request', '8', NULL),
(421, 1779342641, 'user_request', '8', NULL),
(422, 1779342641, 'user_request', '8', NULL),
(423, 1779342642, 'user_request', '8', NULL),
(424, 1779342642, 'user_request', '8', NULL),
(425, 1779342643, 'user_request', '8', NULL),
(426, 1779342643, 'user_request', '8', NULL),
(427, 1779342643, 'user_request', '8', NULL),
(428, 1779342644, 'user_request', '8', NULL),
(429, 1779342644, 'user_request', '8', NULL),
(430, 1779342645, 'user_request', '8', NULL),
(431, 1779342645, 'user_request', '8', NULL),
(432, 1779342646, 'user_request', '8', NULL),
(433, 1779342646, 'user_request', '8', NULL),
(434, 1779342646, 'user_request', '8', NULL),
(435, 1779342647, 'user_request', '8', NULL),
(436, 1779342647, 'user_request', '8', NULL),
(437, 1779342648, 'user_request', '8', NULL),
(438, 1779342648, 'user_request', '8', NULL),
(439, 1779342649, 'user_request', '8', NULL),
(440, 1779342649, 'user_request', '8', NULL),
(441, 1779342649, 'user_request', '8', NULL),
(442, 1779342650, 'user_request', '8', NULL),
(443, 1779342650, 'user_request', '8', NULL),
(444, 1779342651, 'user_request', '8', NULL),
(445, 1779342651, 'user_request', '8', NULL),
(446, 1779342652, 'user_request', '8', NULL),
(447, 1779342652, 'user_request', '8', NULL),
(448, 1779342652, 'user_request', '8', NULL),
(449, 1779342653, 'user_request', '8', NULL),
(450, 1779342653, 'user_request', '8', NULL),
(451, 1779342654, 'user_request', '8', NULL),
(452, 1779342654, 'user_request', '8', NULL),
(453, 1779342654, 'user_request', '8', NULL),
(454, 1779342655, 'user_request', '8', NULL),
(455, 1779342655, 'user_request', '8', NULL),
(456, 1779342656, 'user_request', '8', NULL),
(457, 1779342656, 'user_request', '8', NULL),
(458, 1779342657, 'user_request', '8', NULL),
(459, 1779342657, 'user_request', '8', NULL),
(460, 1779342657, 'user_request', '8', NULL),
(461, 1779342658, 'user_request', '8', NULL),
(462, 1779342658, 'user_request', '8', NULL),
(463, 1779342659, 'user_request', '8', NULL),
(464, 1779342659, 'user_request', '8', NULL),
(465, 1779342659, 'user_request', '8', NULL),
(466, 1779342660, 'user_request', '8', NULL),
(467, 1779342660, 'user_request', '8', NULL),
(468, 1779342661, 'user_request', '8', NULL),
(469, 1779342661, 'user_request', '8', NULL),
(470, 1779342661, 'user_request', '8', NULL),
(471, 1779342662, 'user_request', '8', NULL),
(472, 1779342662, 'user_request', '8', NULL),
(473, 1779342663, 'user_request', '8', NULL),
(474, 1779342663, 'user_request', '8', NULL),
(475, 1779342663, 'user_request', '8', NULL),
(476, 1779342664, 'user_request', '8', NULL),
(477, 1779342664, 'user_request', '8', NULL),
(478, 1779342665, 'user_request', '8', NULL),
(479, 1779342665, 'user_request', '8', NULL),
(480, 1779342665, 'user_request', '8', NULL),
(481, 1779342666, 'user_request', '8', NULL),
(482, 1779342666, 'user_request', '8', NULL),
(483, 1779342667, 'user_request', '8', NULL),
(484, 1779342667, 'user_request', '8', NULL),
(485, 1779342668, 'user_request', '8', NULL),
(486, 1779342668, 'user_request', '8', NULL),
(487, 1779342668, 'user_request', '8', NULL),
(488, 1779342669, 'user_request', '8', NULL),
(489, 1779342669, 'user_request', '8', NULL),
(490, 1779342670, 'user_request', '8', NULL),
(491, 1779342670, 'user_request', '8', NULL),
(492, 1779342671, 'user_request', '8', NULL),
(493, 1779342671, 'user_request', '8', NULL),
(494, 1779342672, 'user_request', '8', NULL),
(495, 1779342673, 'user_request', '8', NULL),
(496, 1779342673, 'user_request', '8', NULL),
(497, 1779342674, 'user_request', '8', NULL),
(498, 1779342674, 'user_request', '8', NULL),
(499, 1779342675, 'user_request', '8', NULL),
(500, 1779342675, 'user_request', '8', NULL),
(501, 1779342675, 'user_request', '8', NULL),
(502, 1779342676, 'user_request', '8', NULL),
(503, 1779342676, 'user_request', '8', NULL),
(504, 1779342677, 'user_request', '8', NULL),
(505, 1779342677, 'user_request', '8', NULL),
(506, 1779342677, 'user_request', '8', NULL),
(507, 1779342678, 'user_request', '8', NULL),
(508, 1779342678, 'user_request', '8', NULL),
(509, 1779342679, 'user_request', '8', NULL),
(510, 1779342679, 'user_request', '8', NULL),
(511, 1779342679, 'user_request', '8', NULL),
(512, 1779342680, 'user_request', '8', NULL),
(513, 1779342680, 'user_request', '8', NULL),
(514, 1779342681, 'user_request', '8', NULL),
(515, 1779342681, 'user_request', '8', NULL),
(516, 1779342681, 'user_request', '8', NULL),
(517, 1779342682, 'user_request', '8', NULL),
(518, 1779342682, 'user_request', '8', NULL),
(519, 1779342683, 'user_request', '8', NULL),
(520, 1779342683, 'user_request', '8', NULL),
(521, 1779342684, 'user_request', '8', NULL),
(522, 1779342684, 'user_request', '8', NULL),
(523, 1779342684, 'user_request', '8', NULL),
(524, 1779342685, 'user_request', '8', NULL),
(525, 1779342685, 'user_request', '8', NULL),
(526, 1779342686, 'user_request', '8', NULL),
(527, 1779342686, 'user_request', '8', NULL),
(528, 1779342686, 'user_request', '8', NULL),
(529, 1779342687, 'user_request', '8', NULL),
(530, 1779342687, 'user_request', '8', NULL),
(531, 1779342688, 'user_request', '8', NULL),
(532, 1779342688, 'user_request', '8', NULL),
(533, 1779342688, 'user_request', '8', NULL),
(534, 1779342689, 'user_request', '8', NULL),
(535, 1779342689, 'user_request', '8', NULL),
(536, 1779342690, 'user_request', '8', NULL),
(537, 1779342690, 'user_request', '8', NULL),
(538, 1779342691, 'user_request', '8', NULL),
(539, 1779342691, 'user_request', '8', NULL),
(540, 1779342691, 'user_request', '8', NULL),
(541, 1779342692, 'user_request', '8', NULL),
(542, 1779342693, 'user_request', '8', NULL),
(543, 1779342693, 'user_request', '8', NULL),
(544, 1779342694, 'user_request', '8', NULL),
(545, 1779342694, 'user_request', '8', NULL),
(546, 1779342694, 'user_request', '8', NULL),
(547, 1779342695, 'user_request', '8', NULL),
(548, 1779342695, 'user_request', '8', NULL),
(549, 1779342696, 'user_request', '8', NULL),
(550, 1779342696, 'user_request', '8', NULL),
(551, 1779342697, 'user_request', '8', NULL),
(552, 1779342697, 'user_request', '8', NULL),
(553, 1779342698, 'user_request', '8', NULL),
(554, 1779342698, 'user_request', '8', NULL),
(555, 1779342699, 'user_request', '8', NULL),
(556, 1779342699, 'user_request', '8', NULL),
(557, 1779342700, 'user_request', '8', NULL),
(558, 1779342700, 'user_request', '8', NULL),
(559, 1779342700, 'user_request', '8', NULL),
(560, 1779342701, 'user_request', '8', NULL),
(561, 1779342701, 'user_request', '8', NULL),
(562, 1779342702, 'user_request', '8', NULL),
(563, 1779342702, 'user_request', '8', NULL),
(564, 1779342702, 'user_request', '8', NULL),
(565, 1779342703, 'user_request', '8', NULL),
(566, 1779342703, 'user_request', '8', NULL),
(567, 1779342704, 'user_request', '8', NULL),
(568, 1779342704, 'user_request', '8', NULL),
(569, 1779342704, 'user_request', '8', NULL),
(570, 1779342705, 'user_request', '8', NULL),
(571, 1779342705, 'user_request', '8', NULL),
(572, 1779342706, 'user_request', '8', NULL),
(573, 1779342706, 'user_request', '8', NULL),
(574, 1779342706, 'user_request', '8', NULL),
(575, 1779342707, 'user_request', '8', NULL),
(576, 1779342707, 'user_request', '8', NULL),
(577, 1779342708, 'user_request', '8', NULL),
(578, 1779342708, 'user_request', '8', NULL),
(579, 1779342708, 'user_request', '8', NULL),
(580, 1779342709, 'user_request', '8', NULL),
(581, 1779342709, 'user_request', '8', NULL),
(582, 1779342710, 'user_request', '8', NULL),
(583, 1779342710, 'user_request', '8', NULL),
(584, 1779342710, 'user_request', '8', NULL),
(585, 1779342711, 'user_request', '8', NULL),
(586, 1779342711, 'user_request', '8', NULL),
(587, 1779342712, 'user_request', '8', NULL),
(588, 1779342712, 'user_request', '8', NULL),
(589, 1779342713, 'user_request', '8', NULL),
(590, 1779342713, 'user_request', '8', NULL),
(591, 1779342713, 'user_request', '8', NULL),
(592, 1779342714, 'user_request', '8', NULL),
(593, 1779342714, 'user_request', '8', NULL),
(594, 1779342714, 'user_request', '8', NULL),
(595, 1779342715, 'user_request', '8', NULL),
(596, 1779342715, 'user_request', '8', NULL),
(597, 1779342716, 'user_request', '8', NULL),
(598, 1779342716, 'user_request', '8', NULL),
(599, 1779342716, 'user_request', '8', NULL),
(600, 1779342717, 'user_request', '8', NULL),
(601, 1779342717, 'user_request', '8', NULL),
(602, 1779342718, 'user_request', '8', NULL),
(603, 1779342718, 'user_request', '8', NULL),
(604, 1779342718, 'user_request', '8', NULL),
(605, 1779342719, 'user_request', '8', NULL),
(606, 1779342719, 'user_request', '8', NULL),
(607, 1779342720, 'user_request', '8', NULL),
(608, 1779342720, 'user_request', '8', NULL),
(609, 1779342720, 'user_request', '8', NULL),
(610, 1779342721, 'user_request', '8', NULL),
(611, 1779342721, 'user_request', '8', NULL),
(612, 1779342722, 'user_request', '8', NULL),
(613, 1779342722, 'user_request', '8', NULL),
(614, 1779342722, 'user_request', '8', NULL),
(615, 1779342723, 'user_request', '8', NULL),
(616, 1779342723, 'user_request', '8', NULL),
(617, 1779342724, 'user_request', '8', NULL),
(618, 1779342724, 'user_request', '8', NULL),
(619, 1779342724, 'user_request', '8', NULL),
(620, 1779342725, 'user_request', '8', NULL),
(621, 1779342725, 'user_request', '8', NULL),
(622, 1779342725, 'user_request', '8', NULL),
(623, 1779342726, 'user_request', '8', NULL),
(624, 1779342726, 'user_request', '8', NULL),
(625, 1779342727, 'user_request', '8', NULL),
(626, 1779342727, 'user_request', '8', NULL),
(627, 1779342728, 'user_request', '8', NULL),
(628, 1779342728, 'user_request', '8', NULL),
(629, 1779342728, 'user_request', '8', NULL),
(630, 1779342729, 'user_request', '8', NULL),
(631, 1779342729, 'user_request', '8', NULL),
(632, 1779342729, 'user_request', '8', NULL),
(633, 1779342730, 'user_request', '8', NULL),
(634, 1779342730, 'user_request', '8', NULL),
(635, 1779342731, 'user_request', '8', NULL),
(636, 1779342731, 'user_request', '8', NULL),
(637, 1779342732, 'user_request', '8', NULL),
(638, 1779342732, 'user_request', '8', NULL),
(639, 1779342732, 'user_request', '8', NULL),
(640, 1779342733, 'user_request', '8', NULL),
(641, 1779342733, 'user_request', '8', NULL),
(642, 1779342734, 'user_request', '8', NULL),
(643, 1779342734, 'user_request', '8', NULL),
(644, 1779342735, 'user_request', '8', NULL),
(645, 1779342735, 'user_request', '8', NULL),
(646, 1779342735, 'user_request', '8', NULL),
(647, 1779342736, 'user_request', '8', NULL),
(648, 1779342736, 'user_request', '8', NULL),
(649, 1779342737, 'user_request', '8', NULL),
(650, 1779342737, 'user_request', '8', NULL),
(651, 1779342737, 'user_request', '8', NULL),
(652, 1779342738, 'user_request', '8', NULL),
(653, 1779342738, 'user_request', '8', NULL),
(654, 1779342739, 'user_request', '8', NULL),
(655, 1779342739, 'user_request', '8', NULL),
(656, 1779342739, 'user_request', '8', NULL),
(657, 1779342740, 'user_request', '8', NULL),
(658, 1779342740, 'user_request', '8', NULL),
(659, 1779342741, 'user_request', '8', NULL),
(660, 1779342741, 'user_request', '8', NULL),
(661, 1779342741, 'user_request', '8', NULL),
(662, 1779342742, 'user_request', '8', NULL),
(663, 1779342742, 'user_request', '8', NULL),
(664, 1779342743, 'user_request', '8', NULL),
(665, 1779342743, 'user_request', '8', NULL),
(666, 1779342744, 'user_request', '8', NULL),
(667, 1779342744, 'user_request', '8', NULL),
(668, 1779342744, 'user_request', '8', NULL),
(669, 1779342745, 'user_request', '8', NULL),
(670, 1779342745, 'user_request', '8', NULL),
(671, 1779342745, 'user_request', '8', NULL),
(672, 1779342746, 'user_request', '8', NULL),
(673, 1779342746, 'user_request', '8', NULL),
(674, 1779342747, 'user_request', '8', NULL),
(675, 1779342747, 'user_request', '8', NULL),
(676, 1779342748, 'user_request', '8', NULL),
(677, 1779342748, 'user_request', '8', NULL),
(678, 1779342748, 'user_request', '8', NULL),
(679, 1779342749, 'user_request', '8', NULL),
(680, 1779342749, 'user_request', '8', NULL),
(681, 1779342750, 'user_request', '8', NULL),
(682, 1779342750, 'user_request', '8', NULL),
(683, 1779342750, 'user_request', '8', NULL),
(684, 1779342751, 'user_request', '8', NULL),
(685, 1779342751, 'user_request', '8', NULL),
(686, 1779342752, 'user_request', '8', NULL),
(687, 1779342752, 'user_request', '8', NULL),
(688, 1779342752, 'user_request', '8', NULL),
(689, 1779342753, 'user_request', '8', NULL),
(690, 1779342753, 'user_request', '8', NULL),
(691, 1779342754, 'user_request', '8', NULL),
(692, 1779342754, 'user_request', '8', NULL),
(693, 1779342754, 'user_request', '8', NULL),
(694, 1779342755, 'user_request', '8', NULL),
(695, 1779342755, 'user_request', '8', NULL),
(696, 1779342755, 'user_request', '8', NULL),
(697, 1779342756, 'user_request', '8', NULL),
(698, 1779342756, 'user_request', '8', NULL),
(699, 1779342757, 'user_request', '8', NULL),
(700, 1779342757, 'user_request', '8', NULL),
(701, 1779342758, 'user_request', '8', NULL),
(702, 1779342758, 'user_request', '8', NULL),
(703, 1779342758, 'user_request', '8', NULL),
(704, 1779342759, 'user_request', '8', NULL),
(705, 1779342759, 'user_request', '8', NULL),
(706, 1779342759, 'user_request', '8', NULL),
(707, 1779342760, 'user_request', '8', NULL),
(708, 1779342760, 'user_request', '8', NULL),
(709, 1779342761, 'user_request', '8', NULL),
(710, 1779342761, 'user_request', '8', NULL),
(711, 1779342761, 'user_request', '8', NULL),
(712, 1779342762, 'user_request', '8', NULL),
(713, 1779342762, 'user_request', '8', NULL),
(714, 1779342763, 'user_request', '8', NULL),
(715, 1779342763, 'user_request', '8', NULL),
(716, 1779342763, 'user_request', '8', NULL),
(717, 1779342764, 'user_request', '8', NULL),
(718, 1779342764, 'user_request', '8', NULL),
(719, 1779342765, 'user_request', '8', NULL),
(720, 1779342765, 'user_request', '8', NULL),
(721, 1779342765, 'user_request', '8', NULL),
(722, 1779342766, 'user_request', '8', NULL),
(723, 1779342766, 'user_request', '8', NULL),
(724, 1779342767, 'user_request', '8', NULL),
(725, 1779342767, 'user_request', '8', NULL),
(726, 1779342767, 'user_request', '8', NULL),
(727, 1779342768, 'user_request', '8', NULL),
(728, 1779342768, 'user_request', '8', NULL),
(729, 1779342769, 'user_request', '8', NULL),
(730, 1779342769, 'user_request', '8', NULL),
(731, 1779342770, 'user_request', '8', NULL),
(732, 1779342770, 'user_request', '8', NULL),
(733, 1779342770, 'user_request', '8', NULL),
(734, 1779342771, 'user_request', '8', NULL),
(735, 1779342771, 'user_request', '8', NULL),
(736, 1779342772, 'user_request', '8', NULL),
(737, 1779342772, 'user_request', '8', NULL),
(738, 1779342773, 'user_request', '8', NULL),
(739, 1779342773, 'user_request', '8', NULL),
(740, 1779342773, 'user_request', '8', NULL),
(741, 1779342774, 'user_request', '8', NULL),
(742, 1779342774, 'user_request', '8', NULL),
(743, 1779342775, 'user_request', '8', NULL),
(744, 1779342775, 'user_request', '8', NULL),
(745, 1779342776, 'user_request', '8', NULL),
(746, 1779342776, 'user_request', '8', NULL),
(747, 1779342776, 'user_request', '8', NULL),
(748, 1779342777, 'user_request', '8', NULL),
(749, 1779342777, 'user_request', '8', NULL),
(750, 1779342778, 'user_request', '8', NULL),
(751, 1779342778, 'user_request', '8', NULL),
(752, 1779342779, 'user_request', '8', NULL),
(753, 1779342779, 'user_request', '8', NULL),
(754, 1779342780, 'user_request', '8', NULL),
(755, 1779342780, 'user_request', '8', NULL),
(756, 1779342780, 'user_request', '8', NULL),
(757, 1779342781, 'user_request', '8', NULL),
(758, 1779342781, 'user_request', '8', NULL),
(759, 1779342781, 'user_request', '8', NULL),
(760, 1779342782, 'user_request', '8', NULL),
(761, 1779342782, 'user_request', '8', NULL),
(762, 1779342783, 'user_request', '8', NULL),
(763, 1779342783, 'user_request', '8', NULL),
(764, 1779342784, 'user_request', '8', NULL),
(765, 1779342784, 'user_request', '8', NULL),
(766, 1779342784, 'user_request', '8', NULL),
(767, 1779342785, 'user_request', '8', NULL),
(768, 1779342785, 'user_request', '8', NULL),
(769, 1779342786, 'user_request', '8', NULL),
(770, 1779342786, 'user_request', '8', NULL),
(771, 1779342786, 'user_request', '8', NULL),
(772, 1779342787, 'user_request', '8', NULL),
(773, 1779342787, 'user_request', '8', NULL),
(774, 1779342788, 'user_request', '8', NULL),
(775, 1779342788, 'user_request', '8', NULL),
(776, 1779342788, 'user_request', '8', NULL),
(777, 1779342789, 'user_request', '8', NULL),
(778, 1779342789, 'user_request', '8', NULL),
(779, 1779342790, 'user_request', '8', NULL),
(780, 1779342790, 'user_request', '8', NULL),
(781, 1779342790, 'user_request', '8', NULL),
(782, 1779342791, 'user_request', '8', NULL),
(783, 1779342791, 'user_request', '8', NULL),
(784, 1779342792, 'user_request', '8', NULL),
(785, 1779342792, 'user_request', '8', NULL),
(786, 1779342792, 'user_request', '8', NULL),
(787, 1779342793, 'user_request', '8', NULL),
(788, 1779342793, 'user_request', '8', NULL),
(789, 1779342794, 'user_request', '8', NULL),
(790, 1779342794, 'user_request', '8', NULL),
(791, 1779342794, 'user_request', '8', NULL),
(792, 1779342795, 'user_request', '8', NULL),
(793, 1779342795, 'user_request', '8', NULL),
(794, 1779342795, 'user_request', '8', NULL),
(795, 1779342796, 'user_request', '8', NULL),
(796, 1779342796, 'user_request', '8', NULL),
(797, 1779342797, 'user_request', '8', NULL),
(798, 1779342797, 'user_request', '8', NULL),
(799, 1779342797, 'user_request', '8', NULL),
(800, 1779342798, 'user_request', '8', NULL),
(801, 1779342798, 'user_request', '8', NULL),
(802, 1779342799, 'user_request', '8', NULL),
(803, 1779342799, 'user_request', '8', NULL),
(804, 1779342799, 'user_request', '8', NULL),
(805, 1779342800, 'user_request', '8', NULL),
(806, 1779342800, 'user_request', '8', NULL),
(807, 1779342801, 'user_request', '8', NULL),
(808, 1779342801, 'user_request', '8', NULL),
(809, 1779342801, 'user_request', '8', NULL),
(810, 1779342802, 'user_request', '8', NULL),
(811, 1779342802, 'user_request', '8', NULL),
(812, 1779342802, 'user_request', '8', NULL),
(813, 1779342803, 'user_request', '8', NULL),
(814, 1779342803, 'user_request', '8', NULL),
(815, 1779342804, 'user_request', '8', NULL),
(816, 1779342804, 'user_request', '8', NULL),
(817, 1779342804, 'user_request', '8', NULL),
(818, 1779342805, 'user_request', '8', NULL),
(819, 1779342805, 'user_request', '8', NULL),
(820, 1779342806, 'user_request', '8', NULL),
(821, 1779342806, 'user_request', '8', NULL),
(822, 1779342806, 'user_request', '8', NULL),
(823, 1779342807, 'user_request', '8', NULL),
(824, 1779342807, 'user_request', '8', NULL),
(825, 1779342807, 'user_request', '8', NULL),
(826, 1779342808, 'user_request', '8', NULL),
(827, 1779342808, 'user_request', '8', NULL),
(828, 1779342809, 'user_request', '8', NULL),
(829, 1779342809, 'user_request', '8', NULL),
(830, 1779342809, 'user_request', '8', NULL),
(831, 1779342810, 'user_request', '8', NULL),
(832, 1779342810, 'user_request', '8', NULL),
(833, 1779342811, 'user_request', '8', NULL),
(834, 1779342811, 'user_request', '8', NULL),
(835, 1779342811, 'user_request', '8', NULL),
(836, 1779342812, 'user_request', '8', NULL),
(837, 1779342812, 'user_request', '8', NULL),
(838, 1779342813, 'user_request', '8', NULL),
(839, 1779342813, 'user_request', '8', NULL),
(840, 1779342813, 'user_request', '8', NULL),
(841, 1779342814, 'user_request', '8', NULL),
(842, 1779342814, 'user_request', '8', NULL),
(843, 1779342814, 'user_request', '8', NULL),
(844, 1779342815, 'user_request', '8', NULL),
(845, 1779342815, 'user_request', '8', NULL),
(846, 1779342816, 'user_request', '8', NULL),
(847, 1779342816, 'user_request', '8', NULL),
(848, 1779342816, 'user_request', '8', NULL),
(849, 1779342817, 'user_request', '8', NULL),
(850, 1779342817, 'user_request', '8', NULL),
(851, 1779342818, 'user_request', '8', NULL),
(852, 1779342818, 'user_request', '8', NULL),
(853, 1779342818, 'user_request', '8', NULL),
(854, 1779342819, 'user_request', '8', NULL),
(855, 1779342819, 'user_request', '8', NULL),
(856, 1779342820, 'user_request', '8', NULL),
(857, 1779342820, 'user_request', '8', NULL),
(858, 1779342820, 'user_request', '8', NULL),
(859, 1779342821, 'user_request', '8', NULL),
(860, 1779342821, 'user_request', '8', NULL),
(861, 1779342821, 'user_request', '8', NULL),
(862, 1779342822, 'user_request', '8', NULL),
(863, 1779342822, 'user_request', '8', NULL),
(864, 1779342823, 'user_request', '8', NULL),
(865, 1779342823, 'user_request', '8', NULL),
(866, 1779342824, 'user_request', '8', NULL),
(867, 1779342824, 'user_request', '8', NULL),
(868, 1779342824, 'user_request', '8', NULL),
(869, 1779342825, 'user_request', '8', NULL),
(870, 1779342825, 'user_request', '8', NULL),
(871, 1779342825, 'user_request', '8', NULL),
(872, 1779342826, 'user_request', '8', NULL),
(873, 1779342826, 'user_request', '8', NULL),
(874, 1779342827, 'user_request', '8', NULL),
(875, 1779342827, 'user_request', '8', NULL),
(876, 1779342828, 'user_request', '8', NULL),
(877, 1779342828, 'user_request', '8', NULL),
(878, 1779342828, 'user_request', '8', NULL),
(879, 1779342829, 'user_request', '8', NULL),
(880, 1779342829, 'user_request', '8', NULL),
(881, 1779342830, 'user_request', '8', NULL),
(882, 1779342830, 'user_request', '8', NULL),
(883, 1779342831, 'user_request', '8', NULL),
(884, 1779342831, 'user_request', '8', NULL),
(885, 1779342832, 'user_request', '8', NULL),
(886, 1779342832, 'user_request', '8', NULL),
(887, 1779342832, 'user_request', '8', NULL),
(888, 1779342833, 'user_request', '8', NULL),
(889, 1779342833, 'user_request', '8', NULL),
(890, 1779342834, 'user_request', '8', NULL),
(891, 1779342834, 'user_request', '8', NULL),
(892, 1779342835, 'user_request', '8', NULL),
(893, 1779342835, 'user_request', '8', NULL),
(894, 1779342835, 'user_request', '8', NULL),
(895, 1779342836, 'user_request', '8', NULL),
(896, 1779342836, 'user_request', '8', NULL),
(897, 1779342837, 'user_request', '8', NULL),
(898, 1779342837, 'user_request', '8', NULL),
(899, 1779342837, 'user_request', '8', NULL),
(900, 1779342838, 'user_request', '8', NULL),
(901, 1779342838, 'user_request', '8', NULL),
(902, 1779342839, 'user_request', '8', NULL),
(903, 1779342839, 'user_request', '8', NULL),
(904, 1779342839, 'user_request', '8', NULL),
(905, 1779342840, 'user_request', '8', NULL),
(906, 1779342840, 'user_request', '8', NULL),
(907, 1779342841, 'user_request', '8', NULL),
(908, 1779342841, 'user_request', '8', NULL),
(909, 1779342841, 'user_request', '8', NULL),
(910, 1779342842, 'user_request', '8', NULL),
(911, 1779342842, 'user_request', '8', NULL),
(912, 1779342843, 'user_request', '8', NULL),
(913, 1779342843, 'user_request', '8', NULL);
INSERT INTO `pulse_entries` (`id`, `timestamp`, `type`, `key`, `value`) VALUES
(914, 1779342844, 'user_request', '8', NULL),
(915, 1779342844, 'user_request', '8', NULL),
(916, 1779342844, 'user_request', '8', NULL),
(917, 1779342845, 'user_request', '8', NULL),
(918, 1779342845, 'user_request', '8', NULL),
(919, 1779342846, 'user_request', '8', NULL),
(920, 1779342846, 'user_request', '8', NULL),
(921, 1779342846, 'user_request', '8', NULL),
(922, 1779342847, 'user_request', '8', NULL),
(923, 1779342848, 'user_request', '8', NULL),
(924, 1779342848, 'user_request', '8', NULL),
(925, 1779342848, 'user_request', '8', NULL),
(926, 1779342849, 'user_request', '8', NULL),
(927, 1779342849, 'user_request', '8', NULL),
(928, 1779342850, 'user_request', '8', NULL),
(929, 1779342850, 'user_request', '8', NULL),
(930, 1779342851, 'user_request', '8', NULL),
(931, 1779342851, 'user_request', '8', NULL),
(932, 1779342852, 'user_request', '8', NULL),
(933, 1779342852, 'user_request', '8', NULL),
(934, 1779342852, 'user_request', '8', NULL),
(935, 1779342853, 'user_request', '8', NULL),
(936, 1779342853, 'user_request', '8', NULL),
(937, 1779342854, 'user_request', '8', NULL),
(938, 1779342854, 'user_request', '8', NULL),
(939, 1779342854, 'user_request', '8', NULL),
(940, 1779342855, 'user_request', '8', NULL),
(941, 1779342855, 'user_request', '8', NULL),
(942, 1779342856, 'user_request', '8', NULL),
(943, 1779342856, 'user_request', '8', NULL),
(944, 1779342857, 'user_request', '8', NULL),
(945, 1779342857, 'user_request', '8', NULL),
(946, 1779342857, 'user_request', '8', NULL),
(947, 1779342858, 'user_request', '8', NULL),
(948, 1779342858, 'user_request', '8', NULL),
(949, 1779342859, 'user_request', '8', NULL),
(950, 1779342859, 'user_request', '8', NULL),
(951, 1779342859, 'user_request', '8', NULL),
(952, 1779342860, 'user_request', '8', NULL),
(953, 1779342860, 'user_request', '8', NULL),
(954, 1779342861, 'user_request', '8', NULL),
(955, 1779342861, 'user_request', '8', NULL),
(956, 1779342862, 'user_request', '8', NULL),
(957, 1779342862, 'user_request', '8', NULL),
(958, 1779342862, 'user_request', '8', NULL),
(959, 1779342863, 'user_request', '8', NULL),
(960, 1779342863, 'user_request', '8', NULL),
(961, 1779342864, 'user_request', '8', NULL),
(962, 1779342864, 'user_request', '8', NULL),
(963, 1779342864, 'user_request', '8', NULL),
(964, 1779342865, 'user_request', '8', NULL),
(965, 1779342865, 'user_request', '8', NULL),
(966, 1779342866, 'user_request', '8', NULL),
(967, 1779342866, 'user_request', '8', NULL),
(968, 1779342866, 'user_request', '8', NULL),
(969, 1779342867, 'user_request', '8', NULL),
(970, 1779342867, 'user_request', '8', NULL),
(971, 1779342868, 'user_request', '8', NULL),
(972, 1779342868, 'user_request', '8', NULL),
(973, 1779342868, 'user_request', '8', NULL),
(974, 1779342869, 'user_request', '8', NULL),
(975, 1779342869, 'user_request', '8', NULL),
(976, 1779342870, 'user_request', '8', NULL),
(977, 1779342870, 'user_request', '8', NULL),
(978, 1779342870, 'user_request', '8', NULL),
(979, 1779342871, 'user_request', '8', NULL),
(980, 1779342871, 'user_request', '8', NULL),
(981, 1779342872, 'user_request', '8', NULL),
(982, 1779342872, 'user_request', '8', NULL),
(983, 1779342873, 'user_request', '8', NULL),
(984, 1779342873, 'user_request', '8', NULL),
(985, 1779342873, 'user_request', '8', NULL),
(986, 1779342874, 'user_request', '8', NULL),
(987, 1779342874, 'user_request', '8', NULL),
(988, 1779342875, 'user_request', '8', NULL),
(989, 1779342875, 'user_request', '8', NULL),
(990, 1779342875, 'user_request', '8', NULL),
(991, 1779342876, 'user_request', '8', NULL),
(992, 1779342876, 'user_request', '8', NULL),
(993, 1779342877, 'user_request', '8', NULL),
(994, 1779342877, 'user_request', '8', NULL),
(995, 1779342878, 'user_request', '8', NULL),
(996, 1779342878, 'user_request', '8', NULL),
(997, 1779342878, 'user_request', '8', NULL),
(998, 1779342879, 'user_request', '8', NULL),
(999, 1779342879, 'user_request', '8', NULL),
(1000, 1779342880, 'user_request', '8', NULL),
(1001, 1779342880, 'user_request', '8', NULL),
(1002, 1779342880, 'user_request', '8', NULL),
(1003, 1779342881, 'user_request', '8', NULL),
(1004, 1779342881, 'user_request', '8', NULL),
(1005, 1779342882, 'user_request', '8', NULL),
(1006, 1779342882, 'user_request', '8', NULL),
(1007, 1779342882, 'user_request', '8', NULL),
(1008, 1779342883, 'user_request', '8', NULL),
(1009, 1779342883, 'user_request', '8', NULL),
(1010, 1779342884, 'user_request', '8', NULL),
(1011, 1779342884, 'user_request', '8', NULL),
(1012, 1779342885, 'user_request', '8', NULL),
(1013, 1779342885, 'user_request', '8', NULL),
(1014, 1779342885, 'user_request', '8', NULL),
(1015, 1779342886, 'user_request', '8', NULL),
(1016, 1779342886, 'user_request', '8', NULL),
(1017, 1779342887, 'user_request', '8', NULL),
(1018, 1779342887, 'user_request', '8', NULL),
(1019, 1779342887, 'user_request', '8', NULL),
(1020, 1779342888, 'user_request', '8', NULL),
(1021, 1779342888, 'user_request', '8', NULL),
(1022, 1779342889, 'user_request', '8', NULL),
(1023, 1779342889, 'user_request', '8', NULL),
(1024, 1779342890, 'user_request', '8', NULL),
(1025, 1779342890, 'user_request', '8', NULL),
(1026, 1779342891, 'user_request', '8', NULL),
(1027, 1779342891, 'user_request', '8', NULL),
(1028, 1779342892, 'user_request', '8', NULL),
(1029, 1779342892, 'user_request', '8', NULL),
(1030, 1779342893, 'user_request', '8', NULL),
(1031, 1779342893, 'user_request', '8', NULL),
(1032, 1779342894, 'user_request', '8', NULL),
(1033, 1779342894, 'user_request', '8', NULL),
(1034, 1779342894, 'user_request', '8', NULL),
(1035, 1779342895, 'user_request', '8', NULL),
(1036, 1779342895, 'user_request', '8', NULL),
(1037, 1779342896, 'user_request', '8', NULL),
(1038, 1779342896, 'user_request', '8', NULL),
(1039, 1779342896, 'user_request', '8', NULL),
(1040, 1779342897, 'user_request', '8', NULL),
(1041, 1779342897, 'user_request', '8', NULL),
(1042, 1779342898, 'user_request', '8', NULL),
(1043, 1779342898, 'user_request', '8', NULL),
(1044, 1779342899, 'user_request', '8', NULL),
(1045, 1779342899, 'user_request', '8', NULL),
(1046, 1779342900, 'user_request', '8', NULL),
(1047, 1779342900, 'user_request', '8', NULL),
(1048, 1779342901, 'user_request', '8', NULL),
(1049, 1779342901, 'user_request', '8', NULL),
(1050, 1779342902, 'user_request', '8', NULL),
(1051, 1779342902, 'user_request', '8', NULL),
(1052, 1779342903, 'user_request', '8', NULL),
(1053, 1779342904, 'user_request', '8', NULL),
(1054, 1779342904, 'user_request', '8', NULL),
(1055, 1779342904, 'user_request', '8', NULL),
(1056, 1779342905, 'user_request', '8', NULL),
(1057, 1779342905, 'user_request', '8', NULL),
(1058, 1779342906, 'user_request', '8', NULL),
(1059, 1779342906, 'user_request', '8', NULL),
(1060, 1779342907, 'user_request', '8', NULL),
(1061, 1779342907, 'user_request', '8', NULL),
(1062, 1779342907, 'user_request', '8', NULL),
(1063, 1779342908, 'user_request', '8', NULL),
(1064, 1779342908, 'user_request', '8', NULL),
(1065, 1779342909, 'user_request', '8', NULL),
(1066, 1779342909, 'user_request', '8', NULL),
(1067, 1779342910, 'user_request', '8', NULL),
(1068, 1779342910, 'user_request', '8', NULL),
(1069, 1779342910, 'user_request', '8', NULL),
(1070, 1779342911, 'user_request', '8', NULL),
(1071, 1779342911, 'user_request', '8', NULL),
(1072, 1779342912, 'user_request', '8', NULL),
(1073, 1779342912, 'user_request', '8', NULL),
(1074, 1779342913, 'user_request', '8', NULL),
(1075, 1779342913, 'user_request', '8', NULL),
(1076, 1779342913, 'user_request', '8', NULL),
(1077, 1779342914, 'user_request', '8', NULL),
(1078, 1779342914, 'user_request', '8', NULL),
(1079, 1779342915, 'user_request', '8', NULL),
(1080, 1779342915, 'user_request', '8', NULL),
(1081, 1779342915, 'user_request', '8', NULL),
(1082, 1779342916, 'user_request', '8', NULL),
(1083, 1779342916, 'user_request', '8', NULL),
(1084, 1779342917, 'user_request', '8', NULL),
(1085, 1779342917, 'user_request', '8', NULL),
(1086, 1779342917, 'user_request', '8', NULL),
(1087, 1779342918, 'user_request', '8', NULL),
(1088, 1779342918, 'user_request', '8', NULL),
(1089, 1779342919, 'user_request', '8', NULL),
(1090, 1779342919, 'user_request', '8', NULL),
(1091, 1779342920, 'user_request', '8', NULL),
(1092, 1779342920, 'user_request', '8', NULL),
(1093, 1779342921, 'user_request', '8', NULL),
(1094, 1779342921, 'user_request', '8', NULL),
(1095, 1779342921, 'user_request', '8', NULL),
(1096, 1779342922, 'user_request', '8', NULL),
(1097, 1779342922, 'user_request', '8', NULL),
(1098, 1779342922, 'user_request', '8', NULL),
(1099, 1779342923, 'user_request', '8', NULL),
(1100, 1779342923, 'user_request', '8', NULL),
(1101, 1779342924, 'user_request', '8', NULL),
(1102, 1779342924, 'user_request', '8', NULL),
(1103, 1779342924, 'user_request', '8', NULL),
(1104, 1779342925, 'user_request', '8', NULL),
(1105, 1779342925, 'user_request', '8', NULL),
(1106, 1779342926, 'user_request', '8', NULL),
(1107, 1779342926, 'user_request', '8', NULL),
(1108, 1779342926, 'user_request', '8', NULL),
(1109, 1779342927, 'user_request', '8', NULL),
(1110, 1779342927, 'user_request', '8', NULL),
(1111, 1779342927, 'user_request', '8', NULL),
(1112, 1779342928, 'user_request', '8', NULL),
(1113, 1779342928, 'user_request', '8', NULL),
(1114, 1779342929, 'user_request', '8', NULL),
(1115, 1779342929, 'user_request', '8', NULL),
(1116, 1779342930, 'user_request', '8', NULL),
(1117, 1779342930, 'user_request', '8', NULL),
(1118, 1779342930, 'user_request', '8', NULL),
(1119, 1779342931, 'user_request', '8', NULL),
(1120, 1779342931, 'user_request', '8', NULL),
(1121, 1779342932, 'user_request', '8', NULL),
(1122, 1779342932, 'user_request', '8', NULL),
(1123, 1779342932, 'user_request', '8', NULL),
(1124, 1779342933, 'user_request', '8', NULL),
(1125, 1779342933, 'user_request', '8', NULL),
(1126, 1779342934, 'user_request', '8', NULL),
(1127, 1779342934, 'user_request', '8', NULL),
(1128, 1779342934, 'user_request', '8', NULL),
(1129, 1779342935, 'user_request', '8', NULL),
(1130, 1779342935, 'user_request', '8', NULL),
(1131, 1779342935, 'user_request', '8', NULL),
(1132, 1779342936, 'user_request', '8', NULL),
(1133, 1779342936, 'user_request', '8', NULL),
(1134, 1779342937, 'user_request', '8', NULL),
(1135, 1779342937, 'user_request', '8', NULL),
(1136, 1779342937, 'user_request', '8', NULL),
(1137, 1779342938, 'user_request', '8', NULL),
(1138, 1779342938, 'user_request', '8', NULL),
(1139, 1779342938, 'user_request', '8', NULL),
(1140, 1779342939, 'user_request', '8', NULL),
(1141, 1779342939, 'user_request', '8', NULL),
(1142, 1779342940, 'user_request', '8', NULL),
(1143, 1779342940, 'user_request', '8', NULL),
(1144, 1779342941, 'user_request', '8', NULL),
(1145, 1779342941, 'user_request', '8', NULL),
(1146, 1779342941, 'user_request', '8', NULL),
(1147, 1779342942, 'user_request', '8', NULL),
(1148, 1779342942, 'user_request', '8', NULL),
(1149, 1779342943, 'user_request', '8', NULL),
(1150, 1779342943, 'user_request', '8', NULL),
(1151, 1779342943, 'user_request', '8', NULL),
(1152, 1779342944, 'user_request', '8', NULL),
(1153, 1779342944, 'user_request', '8', NULL),
(1154, 1779342945, 'user_request', '8', NULL),
(1155, 1779342945, 'user_request', '8', NULL),
(1156, 1779342945, 'user_request', '8', NULL),
(1157, 1779342946, 'user_request', '8', NULL),
(1158, 1779342946, 'user_request', '8', NULL),
(1159, 1779342946, 'user_request', '8', NULL),
(1160, 1779342947, 'user_request', '8', NULL),
(1161, 1779342947, 'user_request', '8', NULL),
(1162, 1779342948, 'user_request', '8', NULL),
(1163, 1779342948, 'user_request', '8', NULL),
(1164, 1779342948, 'user_request', '8', NULL),
(1165, 1779342949, 'user_request', '8', NULL),
(1166, 1779342949, 'user_request', '8', NULL),
(1167, 1779342949, 'user_request', '8', NULL),
(1168, 1779342950, 'user_request', '8', NULL),
(1169, 1779342950, 'user_request', '8', NULL),
(1170, 1779342951, 'user_request', '8', NULL),
(1171, 1779342951, 'user_request', '8', NULL),
(1172, 1779342952, 'user_request', '8', NULL),
(1173, 1779342952, 'user_request', '8', NULL),
(1174, 1779342952, 'user_request', '8', NULL),
(1175, 1779342953, 'user_request', '8', NULL),
(1176, 1779342953, 'user_request', '8', NULL),
(1177, 1779342953, 'user_request', '8', NULL),
(1178, 1779342954, 'user_request', '8', NULL),
(1179, 1779342954, 'user_request', '8', NULL),
(1180, 1779342955, 'user_request', '8', NULL),
(1181, 1779342955, 'user_request', '8', NULL),
(1182, 1779342955, 'user_request', '8', NULL),
(1183, 1779342956, 'user_request', '8', NULL),
(1184, 1779342956, 'user_request', '8', NULL),
(1185, 1779342956, 'user_request', '8', NULL),
(1186, 1779342957, 'user_request', '8', NULL),
(1187, 1779342957, 'user_request', '8', NULL),
(1188, 1779342958, 'user_request', '8', NULL),
(1189, 1779342958, 'user_request', '8', NULL),
(1190, 1779342958, 'user_request', '8', NULL),
(1191, 1779342959, 'user_request', '8', NULL),
(1192, 1779342959, 'user_request', '8', NULL),
(1193, 1779342960, 'user_request', '8', NULL),
(1194, 1779342960, 'user_request', '8', NULL),
(1195, 1779342960, 'user_request', '8', NULL),
(1196, 1779342961, 'user_request', '8', NULL),
(1197, 1779342961, 'user_request', '8', NULL),
(1198, 1779342962, 'user_request', '8', NULL),
(1199, 1779342962, 'user_request', '8', NULL),
(1200, 1779342962, 'user_request', '8', NULL),
(1201, 1779342963, 'user_request', '8', NULL),
(1202, 1779342963, 'user_request', '8', NULL),
(1203, 1779342963, 'user_request', '8', NULL),
(1204, 1779342964, 'user_request', '8', NULL),
(1205, 1779342964, 'user_request', '8', NULL),
(1206, 1779342965, 'user_request', '8', NULL),
(1207, 1779342965, 'user_request', '8', NULL),
(1208, 1779342965, 'user_request', '8', NULL),
(1209, 1779342966, 'user_request', '8', NULL),
(1210, 1779342966, 'user_request', '8', NULL),
(1211, 1779342967, 'user_request', '8', NULL),
(1212, 1779342967, 'user_request', '8', NULL),
(1213, 1779342967, 'user_request', '8', NULL),
(1214, 1779342968, 'user_request', '8', NULL),
(1215, 1779342968, 'user_request', '8', NULL),
(1216, 1779342969, 'user_request', '8', NULL),
(1217, 1779342969, 'user_request', '8', NULL),
(1218, 1779342970, 'user_request', '8', NULL),
(1219, 1779342970, 'user_request', '8', NULL),
(1220, 1779342970, 'user_request', '8', NULL),
(1221, 1779342971, 'user_request', '8', NULL),
(1222, 1779342971, 'user_request', '8', NULL),
(1223, 1779342972, 'user_request', '8', NULL),
(1224, 1779342972, 'user_request', '8', NULL),
(1225, 1779342972, 'user_request', '8', NULL),
(1226, 1779342973, 'user_request', '8', NULL),
(1227, 1779342973, 'user_request', '8', NULL),
(1228, 1779342973, 'user_request', '8', NULL),
(1229, 1779342974, 'user_request', '8', NULL),
(1230, 1779342974, 'user_request', '8', NULL),
(1231, 1779342975, 'user_request', '8', NULL),
(1232, 1779342975, 'user_request', '8', NULL),
(1233, 1779342975, 'user_request', '8', NULL),
(1234, 1779342976, 'user_request', '8', NULL),
(1235, 1779342976, 'user_request', '8', NULL),
(1236, 1779342977, 'user_request', '8', NULL),
(1237, 1779342977, 'user_request', '8', NULL),
(1238, 1779342977, 'user_request', '8', NULL),
(1239, 1779342978, 'user_request', '8', NULL),
(1240, 1779342978, 'user_request', '8', NULL),
(1241, 1779342979, 'user_request', '8', NULL),
(1242, 1779342979, 'user_request', '8', NULL),
(1243, 1779342979, 'user_request', '8', NULL),
(1244, 1779342980, 'user_request', '8', NULL),
(1245, 1779342980, 'user_request', '8', NULL),
(1246, 1779342981, 'user_request', '8', NULL),
(1247, 1779342981, 'user_request', '8', NULL),
(1248, 1779342981, 'user_request', '8', NULL),
(1249, 1779342982, 'user_request', '8', NULL),
(1250, 1779342982, 'user_request', '8', NULL),
(1251, 1779342983, 'user_request', '8', NULL),
(1252, 1779342983, 'user_request', '8', NULL),
(1253, 1779342983, 'user_request', '8', NULL),
(1254, 1779342984, 'user_request', '8', NULL),
(1255, 1779342984, 'user_request', '8', NULL),
(1256, 1779342984, 'user_request', '8', NULL),
(1257, 1779342985, 'user_request', '8', NULL),
(1258, 1779342985, 'user_request', '8', NULL),
(1259, 1779342986, 'user_request', '8', NULL),
(1260, 1779342986, 'user_request', '8', NULL),
(1261, 1779342986, 'user_request', '8', NULL),
(1262, 1779342987, 'user_request', '8', NULL),
(1263, 1779342987, 'user_request', '8', NULL),
(1264, 1779342988, 'user_request', '8', NULL),
(1265, 1779342988, 'user_request', '8', NULL),
(1266, 1779342989, 'user_request', '8', NULL),
(1267, 1779342989, 'user_request', '8', NULL),
(1268, 1779342989, 'user_request', '8', NULL),
(1269, 1779342990, 'user_request', '8', NULL),
(1270, 1779342990, 'user_request', '8', NULL),
(1271, 1779342990, 'user_request', '8', NULL),
(1272, 1779342991, 'user_request', '8', NULL),
(1273, 1779342991, 'user_request', '8', NULL),
(1274, 1779342992, 'user_request', '8', NULL),
(1275, 1779342992, 'user_request', '8', NULL),
(1276, 1779342992, 'user_request', '8', NULL),
(1277, 1779342993, 'user_request', '8', NULL),
(1278, 1779342993, 'user_request', '8', NULL),
(1279, 1779342993, 'user_request', '8', NULL),
(1280, 1779342994, 'user_request', '8', NULL),
(1281, 1779342994, 'user_request', '8', NULL),
(1282, 1779342995, 'user_request', '8', NULL),
(1283, 1779342995, 'user_request', '8', NULL),
(1284, 1779342995, 'user_request', '8', NULL),
(1285, 1779342996, 'user_request', '8', NULL),
(1286, 1779342996, 'user_request', '8', NULL),
(1287, 1779342997, 'user_request', '8', NULL),
(1288, 1779342997, 'user_request', '8', NULL),
(1289, 1779342997, 'user_request', '8', NULL),
(1290, 1779342998, 'user_request', '8', NULL),
(1291, 1779342998, 'user_request', '8', NULL),
(1292, 1779342998, 'user_request', '8', NULL),
(1293, 1779342999, 'user_request', '8', NULL),
(1294, 1779342999, 'user_request', '8', NULL),
(1295, 1779343000, 'user_request', '8', NULL),
(1296, 1779343000, 'user_request', '8', NULL),
(1297, 1779343000, 'user_request', '8', NULL),
(1298, 1779343001, 'user_request', '8', NULL),
(1299, 1779343001, 'user_request', '8', NULL),
(1300, 1779343001, 'user_request', '8', NULL),
(1301, 1779343002, 'user_request', '8', NULL),
(1302, 1779343002, 'user_request', '8', NULL),
(1303, 1779343003, 'user_request', '8', NULL),
(1304, 1779343003, 'user_request', '8', NULL),
(1305, 1779343003, 'user_request', '8', NULL),
(1306, 1779343004, 'user_request', '8', NULL),
(1307, 1779343004, 'user_request', '8', NULL),
(1308, 1779343004, 'user_request', '8', NULL),
(1309, 1779343005, 'user_request', '8', NULL),
(1310, 1779343005, 'user_request', '8', NULL),
(1311, 1779343006, 'user_request', '8', NULL),
(1312, 1779343006, 'user_request', '8', NULL),
(1313, 1779343006, 'user_request', '8', NULL),
(1314, 1779343007, 'user_request', '8', NULL),
(1315, 1779343007, 'user_request', '8', NULL),
(1316, 1779343008, 'user_request', '8', NULL),
(1317, 1779343008, 'user_request', '8', NULL),
(1318, 1779343008, 'user_request', '8', NULL),
(1319, 1779343009, 'user_request', '8', NULL),
(1320, 1779343009, 'user_request', '8', NULL),
(1321, 1779343009, 'user_request', '8', NULL),
(1322, 1779343010, 'user_request', '8', NULL),
(1323, 1779343010, 'user_request', '8', NULL),
(1324, 1779343011, 'user_request', '8', NULL),
(1325, 1779343011, 'user_request', '8', NULL),
(1326, 1779343011, 'user_request', '8', NULL),
(1327, 1779343012, 'user_request', '8', NULL),
(1328, 1779343012, 'user_request', '8', NULL),
(1329, 1779343013, 'user_request', '8', NULL),
(1330, 1779343013, 'user_request', '8', NULL),
(1331, 1779343013, 'user_request', '8', NULL),
(1332, 1779343014, 'user_request', '8', NULL),
(1333, 1779343014, 'user_request', '8', NULL),
(1334, 1779343014, 'user_request', '8', NULL),
(1335, 1779343015, 'user_request', '8', NULL),
(1336, 1779343015, 'user_request', '8', NULL),
(1337, 1779343016, 'user_request', '8', NULL),
(1338, 1779343016, 'user_request', '8', NULL),
(1339, 1779343016, 'user_request', '8', NULL),
(1340, 1779343017, 'user_request', '8', NULL),
(1341, 1779343017, 'user_request', '8', NULL),
(1342, 1779343017, 'user_request', '8', NULL),
(1343, 1779343018, 'user_request', '8', NULL),
(1344, 1779343018, 'user_request', '8', NULL),
(1345, 1779343019, 'user_request', '8', NULL),
(1346, 1779343019, 'user_request', '8', NULL),
(1347, 1779343019, 'user_request', '8', NULL),
(1348, 1779343020, 'user_request', '8', NULL),
(1349, 1779343020, 'user_request', '8', NULL),
(1350, 1779343021, 'user_request', '8', NULL),
(1351, 1779343021, 'user_request', '8', NULL),
(1352, 1779343021, 'user_request', '8', NULL),
(1353, 1779343022, 'user_request', '8', NULL),
(1354, 1779343022, 'user_request', '8', NULL),
(1355, 1779343023, 'user_request', '8', NULL),
(1356, 1779343023, 'user_request', '8', NULL),
(1357, 1779343024, 'user_request', '8', NULL),
(1358, 1779343024, 'user_request', '8', NULL),
(1359, 1779343024, 'user_request', '8', NULL),
(1360, 1779343025, 'user_request', '8', NULL),
(1361, 1779343025, 'user_request', '8', NULL),
(1362, 1779343025, 'user_request', '8', NULL),
(1363, 1779343026, 'user_request', '8', NULL),
(1364, 1779343026, 'user_request', '8', NULL),
(1365, 1779343027, 'user_request', '8', NULL),
(1366, 1779343027, 'user_request', '8', NULL),
(1367, 1779343027, 'user_request', '8', NULL),
(1368, 1779343028, 'user_request', '8', NULL),
(1369, 1779343028, 'user_request', '8', NULL),
(1370, 1779343029, 'user_request', '8', NULL),
(1371, 1779343029, 'user_request', '8', NULL),
(1372, 1779343029, 'user_request', '8', NULL),
(1373, 1779343030, 'user_request', '8', NULL),
(1374, 1779343030, 'user_request', '8', NULL),
(1375, 1779343031, 'user_request', '8', NULL),
(1376, 1779343031, 'user_request', '8', NULL),
(1377, 1779343031, 'user_request', '8', NULL),
(1378, 1779343032, 'user_request', '8', NULL),
(1379, 1779343032, 'user_request', '8', NULL),
(1380, 1779343033, 'user_request', '8', NULL),
(1381, 1779343033, 'user_request', '8', NULL),
(1382, 1779343034, 'user_request', '8', NULL),
(1383, 1779343034, 'user_request', '8', NULL),
(1384, 1779343034, 'user_request', '8', NULL),
(1385, 1779343035, 'user_request', '8', NULL),
(1386, 1779343035, 'user_request', '8', NULL),
(1387, 1779343036, 'user_request', '8', NULL),
(1388, 1779343036, 'user_request', '8', NULL),
(1389, 1779343036, 'user_request', '8', NULL),
(1390, 1779343037, 'user_request', '8', NULL),
(1391, 1779343037, 'user_request', '8', NULL),
(1392, 1779343037, 'user_request', '8', NULL),
(1393, 1779343038, 'user_request', '8', NULL),
(1394, 1779343038, 'user_request', '8', NULL),
(1395, 1779343039, 'user_request', '8', NULL),
(1396, 1779343039, 'user_request', '8', NULL),
(1397, 1779343040, 'user_request', '8', NULL),
(1398, 1779343040, 'user_request', '8', NULL),
(1399, 1779343040, 'user_request', '8', NULL),
(1400, 1779343041, 'user_request', '8', NULL),
(1401, 1779343041, 'user_request', '8', NULL),
(1402, 1779343042, 'user_request', '8', NULL),
(1403, 1779343042, 'user_request', '8', NULL),
(1404, 1779343042, 'user_request', '8', NULL),
(1405, 1779343043, 'user_request', '8', NULL),
(1406, 1779343043, 'user_request', '8', NULL),
(1407, 1779343044, 'user_request', '8', NULL),
(1408, 1779343044, 'user_request', '8', NULL),
(1409, 1779343044, 'user_request', '8', NULL),
(1410, 1779343045, 'user_request', '8', NULL),
(1411, 1779343045, 'user_request', '8', NULL),
(1412, 1779343046, 'user_request', '8', NULL),
(1413, 1779343046, 'user_request', '8', NULL),
(1414, 1779343046, 'user_request', '8', NULL),
(1415, 1779343047, 'user_request', '8', NULL),
(1416, 1779343047, 'user_request', '8', NULL),
(1417, 1779343048, 'user_request', '8', NULL),
(1418, 1779343048, 'user_request', '8', NULL),
(1419, 1779343048, 'user_request', '8', NULL),
(1420, 1779343049, 'user_request', '8', NULL),
(1421, 1779343049, 'user_request', '8', NULL),
(1422, 1779343049, 'user_request', '8', NULL),
(1423, 1779343050, 'user_request', '8', NULL),
(1424, 1779343050, 'user_request', '8', NULL),
(1425, 1779343051, 'user_request', '8', NULL),
(1426, 1779343051, 'user_request', '8', NULL),
(1427, 1779343051, 'user_request', '8', NULL),
(1428, 1779343052, 'user_request', '8', NULL),
(1429, 1779343052, 'user_request', '8', NULL),
(1430, 1779343052, 'user_request', '8', NULL),
(1431, 1779343053, 'user_request', '8', NULL),
(1432, 1779343053, 'user_request', '8', NULL),
(1433, 1779343054, 'user_request', '8', NULL),
(1434, 1779343054, 'user_request', '8', NULL),
(1435, 1779343054, 'user_request', '8', NULL),
(1436, 1779343055, 'user_request', '8', NULL),
(1437, 1779343055, 'user_request', '8', NULL),
(1438, 1779343055, 'user_request', '8', NULL),
(1439, 1779343056, 'user_request', '8', NULL),
(1440, 1779343056, 'user_request', '8', NULL),
(1441, 1779343057, 'user_request', '8', NULL),
(1442, 1779343057, 'user_request', '8', NULL),
(1443, 1779343057, 'user_request', '8', NULL),
(1444, 1779343058, 'user_request', '8', NULL),
(1445, 1779343058, 'user_request', '8', NULL),
(1446, 1779343059, 'user_request', '8', NULL),
(1447, 1779343059, 'user_request', '8', NULL),
(1448, 1779343059, 'user_request', '8', NULL),
(1449, 1779343060, 'user_request', '8', NULL),
(1450, 1779343060, 'user_request', '8', NULL),
(1451, 1779343061, 'user_request', '8', NULL),
(1452, 1779343061, 'user_request', '8', NULL),
(1453, 1779343061, 'user_request', '8', NULL),
(1454, 1779343062, 'user_request', '8', NULL),
(1455, 1779343062, 'user_request', '8', NULL),
(1456, 1779343062, 'user_request', '8', NULL),
(1457, 1779343063, 'user_request', '8', NULL),
(1458, 1779343063, 'user_request', '8', NULL),
(1459, 1779343064, 'user_request', '8', NULL),
(1460, 1779343064, 'user_request', '8', NULL),
(1461, 1779343064, 'user_request', '8', NULL),
(1462, 1779343065, 'user_request', '8', NULL),
(1463, 1779343065, 'user_request', '8', NULL),
(1464, 1779343066, 'user_request', '8', NULL),
(1465, 1779343066, 'user_request', '8', NULL),
(1466, 1779343066, 'user_request', '8', NULL),
(1467, 1779343067, 'user_request', '8', NULL),
(1468, 1779343067, 'user_request', '8', NULL),
(1469, 1779343067, 'user_request', '8', NULL),
(1470, 1779343068, 'user_request', '8', NULL),
(1471, 1779343068, 'user_request', '8', NULL),
(1472, 1779343069, 'user_request', '8', NULL),
(1473, 1779343069, 'user_request', '8', NULL),
(1474, 1779343069, 'user_request', '8', NULL),
(1475, 1779343070, 'user_request', '8', NULL),
(1476, 1779343070, 'user_request', '8', NULL),
(1477, 1779343070, 'user_request', '8', NULL),
(1478, 1779343071, 'user_request', '8', NULL),
(1479, 1779343071, 'user_request', '8', NULL),
(1480, 1779343072, 'user_request', '8', NULL),
(1481, 1779343072, 'user_request', '8', NULL),
(1482, 1779343073, 'user_request', '8', NULL),
(1483, 1779343073, 'user_request', '8', NULL),
(1484, 1779343073, 'user_request', '8', NULL),
(1485, 1779343074, 'user_request', '8', NULL),
(1486, 1779343074, 'user_request', '8', NULL),
(1487, 1779343075, 'user_request', '8', NULL),
(1488, 1779343075, 'user_request', '8', NULL),
(1489, 1779343075, 'user_request', '8', NULL),
(1490, 1779343076, 'user_request', '8', NULL),
(1491, 1779343076, 'user_request', '8', NULL),
(1492, 1779343077, 'user_request', '8', NULL),
(1493, 1779343077, 'user_request', '8', NULL),
(1494, 1779343077, 'user_request', '8', NULL),
(1495, 1779343078, 'user_request', '8', NULL),
(1496, 1779343078, 'user_request', '8', NULL),
(1497, 1779343079, 'user_request', '8', NULL),
(1498, 1779343079, 'user_request', '8', NULL),
(1499, 1779343079, 'user_request', '8', NULL),
(1500, 1779343080, 'user_request', '8', NULL),
(1501, 1779343080, 'user_request', '8', NULL),
(1502, 1779343080, 'user_request', '8', NULL),
(1503, 1779343081, 'user_request', '8', NULL),
(1504, 1779343081, 'user_request', '8', NULL),
(1505, 1779343082, 'user_request', '8', NULL),
(1506, 1779343082, 'user_request', '8', NULL),
(1507, 1779343082, 'user_request', '8', NULL),
(1508, 1779343083, 'user_request', '8', NULL),
(1509, 1779343083, 'user_request', '8', NULL),
(1510, 1779343084, 'user_request', '8', NULL),
(1511, 1779343084, 'user_request', '8', NULL),
(1512, 1779343084, 'user_request', '8', NULL),
(1513, 1779343085, 'user_request', '8', NULL),
(1514, 1779343085, 'user_request', '8', NULL),
(1515, 1779343086, 'user_request', '8', NULL),
(1516, 1779343086, 'user_request', '8', NULL),
(1517, 1779343086, 'user_request', '8', NULL),
(1518, 1779343087, 'user_request', '8', NULL),
(1519, 1779343087, 'user_request', '8', NULL),
(1520, 1779343087, 'user_request', '8', NULL),
(1521, 1779343088, 'user_request', '8', NULL),
(1522, 1779343088, 'user_request', '8', NULL),
(1523, 1779343089, 'user_request', '8', NULL),
(1524, 1779343089, 'user_request', '8', NULL),
(1525, 1779343089, 'user_request', '8', NULL),
(1526, 1779343090, 'user_request', '8', NULL),
(1527, 1779343090, 'user_request', '8', NULL),
(1528, 1779343091, 'user_request', '8', NULL),
(1529, 1779343091, 'user_request', '8', NULL),
(1530, 1779343091, 'user_request', '8', NULL),
(1531, 1779343092, 'user_request', '8', NULL),
(1532, 1779343092, 'user_request', '8', NULL),
(1533, 1779343093, 'user_request', '8', NULL),
(1534, 1779343093, 'user_request', '8', NULL),
(1535, 1779343093, 'user_request', '8', NULL),
(1536, 1779343094, 'user_request', '8', NULL),
(1537, 1779343094, 'user_request', '8', NULL),
(1538, 1779343094, 'user_request', '8', NULL),
(1539, 1779343095, 'user_request', '8', NULL),
(1540, 1779343095, 'user_request', '8', NULL),
(1541, 1779343096, 'user_request', '8', NULL),
(1542, 1779343096, 'user_request', '8', NULL),
(1543, 1779343096, 'user_request', '8', NULL),
(1544, 1779343097, 'user_request', '8', NULL),
(1545, 1779343097, 'user_request', '8', NULL),
(1546, 1779343098, 'user_request', '8', NULL),
(1547, 1779343098, 'user_request', '8', NULL),
(1548, 1779343099, 'user_request', '8', NULL),
(1549, 1779343099, 'user_request', '8', NULL),
(1550, 1779343099, 'user_request', '8', NULL),
(1551, 1779343100, 'user_request', '8', NULL),
(1552, 1779343100, 'user_request', '8', NULL),
(1553, 1779343101, 'user_request', '8', NULL),
(1554, 1779343101, 'user_request', '8', NULL),
(1555, 1779343101, 'user_request', '8', NULL),
(1556, 1779343102, 'user_request', '8', NULL),
(1557, 1779343102, 'user_request', '8', NULL),
(1558, 1779343103, 'user_request', '8', NULL),
(1559, 1779343103, 'user_request', '8', NULL),
(1560, 1779343103, 'user_request', '8', NULL),
(1561, 1779343104, 'user_request', '8', NULL),
(1562, 1779343104, 'user_request', '8', NULL),
(1563, 1779343104, 'user_request', '8', NULL),
(1564, 1779343105, 'user_request', '8', NULL),
(1565, 1779343105, 'user_request', '8', NULL),
(1566, 1779343106, 'user_request', '8', NULL),
(1567, 1779343106, 'user_request', '8', NULL),
(1568, 1779343106, 'user_request', '8', NULL),
(1569, 1779343107, 'user_request', '8', NULL),
(1570, 1779343107, 'user_request', '8', NULL),
(1571, 1779343108, 'user_request', '8', NULL),
(1572, 1779343108, 'user_request', '8', NULL),
(1573, 1779343108, 'user_request', '8', NULL),
(1574, 1779343109, 'user_request', '8', NULL),
(1575, 1779343109, 'user_request', '8', NULL),
(1576, 1779343110, 'user_request', '8', NULL),
(1577, 1779343110, 'user_request', '8', NULL),
(1578, 1779343110, 'user_request', '8', NULL),
(1579, 1779343111, 'user_request', '8', NULL),
(1580, 1779343111, 'user_request', '8', NULL),
(1581, 1779343112, 'user_request', '8', NULL),
(1582, 1779343112, 'user_request', '8', NULL),
(1583, 1779343112, 'user_request', '8', NULL),
(1584, 1779343113, 'user_request', '8', NULL),
(1585, 1779343113, 'user_request', '8', NULL),
(1586, 1779343114, 'user_request', '8', NULL),
(1587, 1779343114, 'user_request', '8', NULL),
(1588, 1779343114, 'user_request', '8', NULL),
(1589, 1779343115, 'user_request', '8', NULL),
(1590, 1779343115, 'user_request', '8', NULL),
(1591, 1779343115, 'user_request', '8', NULL),
(1592, 1779343116, 'user_request', '8', NULL),
(1593, 1779343116, 'user_request', '8', NULL),
(1594, 1779343117, 'user_request', '8', NULL),
(1595, 1779343117, 'user_request', '8', NULL),
(1596, 1779343117, 'user_request', '8', NULL),
(1597, 1779343118, 'user_request', '8', NULL),
(1598, 1779343118, 'user_request', '8', NULL),
(1599, 1779343119, 'user_request', '8', NULL),
(1600, 1779343119, 'user_request', '8', NULL),
(1601, 1779343119, 'user_request', '8', NULL),
(1602, 1779343120, 'user_request', '8', NULL),
(1603, 1779343120, 'user_request', '8', NULL),
(1604, 1779343121, 'user_request', '8', NULL),
(1605, 1779343121, 'user_request', '8', NULL),
(1606, 1779343122, 'user_request', '8', NULL),
(1607, 1779343122, 'user_request', '8', NULL),
(1608, 1779343122, 'user_request', '8', NULL),
(1609, 1779343123, 'user_request', '8', NULL),
(1610, 1779343123, 'user_request', '8', NULL),
(1611, 1779343124, 'user_request', '8', NULL),
(1612, 1779343124, 'user_request', '8', NULL),
(1613, 1779343125, 'user_request', '8', NULL),
(1614, 1779343125, 'user_request', '8', NULL),
(1615, 1779343126, 'user_request', '8', NULL),
(1616, 1779343126, 'user_request', '8', NULL),
(1617, 1779343127, 'user_request', '8', NULL),
(1618, 1779343127, 'user_request', '8', NULL),
(1619, 1779343128, 'user_request', '8', NULL),
(1620, 1779343128, 'user_request', '8', NULL),
(1621, 1779343128, 'user_request', '8', NULL),
(1622, 1779343129, 'user_request', '8', NULL),
(1623, 1779343129, 'user_request', '8', NULL),
(1624, 1779343130, 'user_request', '8', NULL),
(1625, 1779343130, 'user_request', '8', NULL),
(1626, 1779343131, 'user_request', '8', NULL),
(1627, 1779343131, 'user_request', '8', NULL),
(1628, 1779343131, 'user_request', '8', NULL),
(1629, 1779343132, 'user_request', '8', NULL),
(1630, 1779343132, 'user_request', '8', NULL),
(1631, 1779343133, 'user_request', '8', NULL),
(1632, 1779343133, 'user_request', '8', NULL),
(1633, 1779343134, 'user_request', '8', NULL),
(1634, 1779343134, 'user_request', '8', NULL),
(1635, 1779343134, 'user_request', '8', NULL),
(1636, 1779343135, 'user_request', '8', NULL),
(1637, 1779343135, 'user_request', '8', NULL),
(1638, 1779343136, 'user_request', '8', NULL),
(1639, 1779343136, 'user_request', '8', NULL),
(1640, 1779343137, 'user_request', '8', NULL),
(1641, 1779343137, 'user_request', '8', NULL),
(1642, 1779343137, 'user_request', '8', NULL),
(1643, 1779343138, 'user_request', '8', NULL),
(1644, 1779343138, 'user_request', '8', NULL),
(1645, 1779343139, 'user_request', '8', NULL),
(1646, 1779343139, 'user_request', '8', NULL),
(1647, 1779343140, 'user_request', '8', NULL),
(1648, 1779343140, 'user_request', '8', NULL),
(1649, 1779343140, 'user_request', '8', NULL),
(1650, 1779343141, 'user_request', '8', NULL),
(1651, 1779343141, 'user_request', '8', NULL),
(1652, 1779343142, 'user_request', '8', NULL),
(1653, 1779343142, 'user_request', '8', NULL),
(1654, 1779343143, 'user_request', '8', NULL),
(1655, 1779343143, 'user_request', '8', NULL),
(1656, 1779343143, 'user_request', '8', NULL),
(1657, 1779343144, 'user_request', '8', NULL),
(1658, 1779343144, 'user_request', '8', NULL),
(1659, 1779343145, 'user_request', '8', NULL),
(1660, 1779343145, 'user_request', '8', NULL),
(1661, 1779343146, 'user_request', '8', NULL),
(1662, 1779343146, 'user_request', '8', NULL),
(1663, 1779343146, 'user_request', '8', NULL),
(1664, 1779343147, 'user_request', '8', NULL),
(1665, 1779343147, 'user_request', '8', NULL),
(1666, 1779343148, 'user_request', '8', NULL),
(1667, 1779343148, 'user_request', '8', NULL),
(1668, 1779343148, 'user_request', '8', NULL),
(1669, 1779343149, 'user_request', '8', NULL),
(1670, 1779343149, 'user_request', '8', NULL),
(1671, 1779343150, 'user_request', '8', NULL),
(1672, 1779343150, 'user_request', '8', NULL),
(1673, 1779343150, 'user_request', '8', NULL),
(1674, 1779343151, 'user_request', '8', NULL),
(1675, 1779343151, 'user_request', '8', NULL),
(1676, 1779343152, 'user_request', '8', NULL),
(1677, 1779343152, 'user_request', '8', NULL),
(1678, 1779343152, 'user_request', '8', NULL),
(1679, 1779343153, 'user_request', '8', NULL),
(1680, 1779343153, 'user_request', '8', NULL),
(1681, 1779343154, 'user_request', '8', NULL),
(1682, 1779343154, 'user_request', '8', NULL),
(1683, 1779343154, 'user_request', '8', NULL),
(1684, 1779343155, 'user_request', '8', NULL),
(1685, 1779343155, 'user_request', '8', NULL),
(1686, 1779343155, 'user_request', '8', NULL),
(1687, 1779343156, 'user_request', '8', NULL),
(1688, 1779343156, 'user_request', '8', NULL),
(1689, 1779343156, 'user_request', '8', NULL),
(1690, 1779343157, 'user_request', '8', NULL),
(1691, 1779343157, 'user_request', '8', NULL),
(1692, 1779343158, 'user_request', '8', NULL),
(1693, 1779343158, 'user_request', '8', NULL),
(1694, 1779343158, 'user_request', '8', NULL),
(1695, 1779343159, 'user_request', '8', NULL),
(1696, 1779343159, 'user_request', '8', NULL),
(1697, 1779343160, 'user_request', '8', NULL),
(1698, 1779343160, 'user_request', '8', NULL),
(1699, 1779343160, 'user_request', '8', NULL),
(1700, 1779343161, 'user_request', '8', NULL),
(1701, 1779343161, 'user_request', '8', NULL),
(1702, 1779343162, 'user_request', '8', NULL),
(1703, 1779343162, 'user_request', '8', NULL),
(1704, 1779343162, 'user_request', '8', NULL),
(1705, 1779343163, 'user_request', '8', NULL),
(1706, 1779343163, 'user_request', '8', NULL),
(1707, 1779343164, 'user_request', '8', NULL),
(1708, 1779343164, 'user_request', '8', NULL),
(1709, 1779343164, 'user_request', '8', NULL),
(1710, 1779343165, 'user_request', '8', NULL),
(1711, 1779343165, 'user_request', '8', NULL),
(1712, 1779343166, 'user_request', '8', NULL),
(1713, 1779343166, 'user_request', '8', NULL),
(1714, 1779343167, 'user_request', '8', NULL),
(1715, 1779343167, 'user_request', '8', NULL),
(1716, 1779343167, 'user_request', '8', NULL),
(1717, 1779343168, 'user_request', '8', NULL),
(1718, 1779343168, 'user_request', '8', NULL),
(1719, 1779343169, 'user_request', '8', NULL),
(1720, 1779343169, 'user_request', '8', NULL),
(1721, 1779343169, 'user_request', '8', NULL),
(1722, 1779343170, 'user_request', '8', NULL),
(1723, 1779343170, 'user_request', '8', NULL),
(1724, 1779343171, 'user_request', '8', NULL),
(1725, 1779343171, 'user_request', '8', NULL),
(1726, 1779343172, 'user_request', '8', NULL),
(1727, 1779343172, 'user_request', '8', NULL),
(1728, 1779343172, 'user_request', '8', NULL),
(1729, 1779343173, 'user_request', '8', NULL),
(1730, 1779343173, 'user_request', '8', NULL),
(1731, 1779343174, 'user_request', '8', NULL),
(1732, 1779343174, 'user_request', '8', NULL),
(1733, 1779343175, 'user_request', '8', NULL),
(1734, 1779343175, 'user_request', '8', NULL),
(1735, 1779343175, 'user_request', '8', NULL),
(1736, 1779343176, 'user_request', '8', NULL),
(1737, 1779343176, 'user_request', '8', NULL),
(1738, 1779343177, 'user_request', '8', NULL),
(1739, 1779343177, 'user_request', '8', NULL),
(1740, 1779343177, 'user_request', '8', NULL),
(1741, 1779343178, 'user_request', '8', NULL),
(1742, 1779343178, 'user_request', '8', NULL),
(1743, 1779343179, 'user_request', '8', NULL),
(1744, 1779343179, 'user_request', '8', NULL),
(1745, 1779343180, 'user_request', '8', NULL),
(1746, 1779343180, 'user_request', '8', NULL),
(1747, 1779343180, 'user_request', '8', NULL),
(1748, 1779343181, 'user_request', '8', NULL),
(1749, 1779343181, 'user_request', '8', NULL),
(1750, 1779343182, 'user_request', '8', NULL),
(1751, 1779343182, 'user_request', '8', NULL),
(1752, 1779343182, 'user_request', '8', NULL),
(1753, 1779343183, 'user_request', '8', NULL),
(1754, 1779343183, 'user_request', '8', NULL),
(1755, 1779343184, 'user_request', '8', NULL),
(1756, 1779343184, 'user_request', '8', NULL),
(1757, 1779343184, 'user_request', '8', NULL),
(1758, 1779343185, 'user_request', '8', NULL),
(1759, 1779343185, 'user_request', '8', NULL),
(1760, 1779343186, 'user_request', '8', NULL),
(1761, 1779343186, 'user_request', '8', NULL),
(1762, 1779343186, 'user_request', '8', NULL),
(1763, 1779343187, 'user_request', '8', NULL),
(1764, 1779343188, 'user_request', '8', NULL),
(1765, 1779343188, 'user_request', '8', NULL),
(1766, 1779343188, 'user_request', '8', NULL),
(1767, 1779343189, 'user_request', '8', NULL),
(1768, 1779343189, 'user_request', '8', NULL),
(1769, 1779343190, 'user_request', '8', NULL),
(1770, 1779343190, 'user_request', '8', NULL),
(1771, 1779343191, 'user_request', '8', NULL),
(1772, 1779343191, 'user_request', '8', NULL),
(1773, 1779343191, 'user_request', '8', NULL),
(1774, 1779343192, 'user_request', '8', NULL),
(1775, 1779343192, 'user_request', '8', NULL),
(1776, 1779343193, 'user_request', '8', NULL),
(1777, 1779343194, 'user_request', '8', NULL),
(1778, 1779343194, 'user_request', '8', NULL),
(1779, 1779343195, 'user_request', '8', NULL),
(1780, 1779343195, 'user_request', '8', NULL),
(1781, 1779343196, 'user_request', '8', NULL),
(1782, 1779343196, 'user_request', '8', NULL),
(1783, 1779343196, 'user_request', '8', NULL),
(1784, 1779343197, 'user_request', '8', NULL),
(1785, 1779343197, 'user_request', '8', NULL),
(1786, 1779343198, 'user_request', '8', NULL),
(1787, 1779343198, 'user_request', '8', NULL),
(1788, 1779343199, 'user_request', '8', NULL),
(1789, 1779343199, 'user_request', '8', NULL),
(1790, 1779343199, 'user_request', '8', NULL),
(1791, 1779343200, 'user_request', '8', NULL),
(1792, 1779343204, 'user_request', '8', NULL),
(1793, 1779343227, 'user_request', '8', NULL),
(1794, 1779343228, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(1795, 1779343228, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(1796, 1779343228, 'user_request', '8', NULL),
(1797, 1779343229, 'user_request', '8', NULL),
(1798, 1779343229, 'user_request', '8', NULL),
(1799, 1779343230, 'user_request', '8', NULL),
(1800, 1779343230, 'user_request', '8', NULL),
(1801, 1779343231, 'user_request', '8', NULL),
(1802, 1779343231, 'user_request', '8', NULL),
(1803, 1779343231, 'user_request', '8', NULL),
(1804, 1779343232, 'user_request', '8', NULL),
(1805, 1779343232, 'user_request', '8', NULL),
(1806, 1779343233, 'user_request', '8', NULL),
(1807, 1779343233, 'user_request', '8', NULL),
(1808, 1779343234, 'user_request', '8', NULL),
(1809, 1779343234, 'user_request', '8', NULL),
(1810, 1779343234, 'user_request', '8', NULL),
(1811, 1779343235, 'user_request', '8', NULL),
(1812, 1779343235, 'user_request', '8', NULL),
(1813, 1779343236, 'user_request', '8', NULL),
(1814, 1779343236, 'user_request', '8', NULL),
(1815, 1779343236, 'user_request', '8', NULL),
(1816, 1779343237, 'user_request', '8', NULL),
(1817, 1779343237, 'user_request', '8', NULL),
(1818, 1779343238, 'user_request', '8', NULL),
(1819, 1779343238, 'user_request', '8', NULL),
(1820, 1779343239, 'user_request', '8', NULL),
(1821, 1779343239, 'user_request', '8', NULL),
(1822, 1779343239, 'user_request', '8', NULL),
(1823, 1779343240, 'user_request', '8', NULL),
(1824, 1779343240, 'user_request', '8', NULL),
(1825, 1779343241, 'user_request', '8', NULL),
(1826, 1779343241, 'user_request', '8', NULL),
(1827, 1779343242, 'user_request', '8', NULL),
(1828, 1779343242, 'user_request', '8', NULL),
(1829, 1779343242, 'user_request', '8', NULL),
(1830, 1779343243, 'user_request', '8', NULL),
(1831, 1779343243, 'user_request', '8', NULL),
(1832, 1779343244, 'user_request', '8', NULL),
(1833, 1779343244, 'user_request', '8', NULL),
(1834, 1779343245, 'user_request', '8', NULL),
(1835, 1779343245, 'user_request', '8', NULL),
(1836, 1779343245, 'user_request', '8', NULL),
(1837, 1779343246, 'user_request', '8', NULL),
(1838, 1779343246, 'user_request', '8', NULL),
(1839, 1779343247, 'user_request', '8', NULL),
(1840, 1779343247, 'user_request', '8', NULL),
(1841, 1779343247, 'user_request', '8', NULL),
(1842, 1779343248, 'user_request', '8', NULL),
(1843, 1779343248, 'user_request', '8', NULL),
(1844, 1779343249, 'user_request', '8', NULL),
(1845, 1779343249, 'user_request', '8', NULL),
(1846, 1779343250, 'user_request', '8', NULL),
(1847, 1779343250, 'user_request', '8', NULL),
(1848, 1779343250, 'user_request', '8', NULL),
(1849, 1779343251, 'user_request', '8', NULL),
(1850, 1779343251, 'user_request', '8', NULL),
(1851, 1779343252, 'user_request', '8', NULL),
(1852, 1779343252, 'user_request', '8', NULL),
(1853, 1779343252, 'user_request', '8', NULL),
(1854, 1779343253, 'user_request', '8', NULL),
(1855, 1779343253, 'user_request', '8', NULL),
(1856, 1779343254, 'user_request', '8', NULL),
(1857, 1779343254, 'user_request', '8', NULL),
(1858, 1779343255, 'user_request', '8', NULL),
(1859, 1779343255, 'user_request', '8', NULL),
(1860, 1779343255, 'user_request', '8', NULL),
(1861, 1779343256, 'user_request', '8', NULL),
(1862, 1779343256, 'user_request', '8', NULL),
(1863, 1779343257, 'user_request', '8', NULL),
(1864, 1779343257, 'user_request', '8', NULL),
(1865, 1779343257, 'user_request', '8', NULL),
(1866, 1779343258, 'user_request', '8', NULL),
(1867, 1779343258, 'user_request', '8', NULL),
(1868, 1779343259, 'user_request', '8', NULL),
(1869, 1779343259, 'user_request', '8', NULL),
(1870, 1779343259, 'user_request', '8', NULL),
(1871, 1779343260, 'user_request', '8', NULL),
(1872, 1779343260, 'user_request', '8', NULL),
(1873, 1779343261, 'user_request', '8', NULL),
(1874, 1779343261, 'user_request', '8', NULL),
(1875, 1779343261, 'user_request', '8', NULL),
(1876, 1779343262, 'user_request', '8', NULL),
(1877, 1779343262, 'user_request', '8', NULL),
(1878, 1779343263, 'user_request', '8', NULL),
(1879, 1779343263, 'user_request', '8', NULL),
(1880, 1779343263, 'user_request', '8', NULL),
(1881, 1779343264, 'user_request', '8', NULL),
(1882, 1779343264, 'user_request', '8', NULL),
(1883, 1779343265, 'user_request', '8', NULL),
(1884, 1779343265, 'user_request', '8', NULL),
(1885, 1779343265, 'user_request', '8', NULL),
(1886, 1779343266, 'user_request', '8', NULL),
(1887, 1779343266, 'user_request', '8', NULL),
(1888, 1779343267, 'user_request', '8', NULL),
(1889, 1779343267, 'user_request', '8', NULL),
(1890, 1779343267, 'user_request', '8', NULL),
(1891, 1779343268, 'user_request', '8', NULL),
(1892, 1779343268, 'user_request', '8', NULL),
(1893, 1779343269, 'user_request', '8', NULL),
(1894, 1779343269, 'user_request', '8', NULL),
(1895, 1779343269, 'user_request', '8', NULL),
(1896, 1779343270, 'user_request', '8', NULL),
(1897, 1779343270, 'user_request', '8', NULL),
(1898, 1779343271, 'user_request', '8', NULL),
(1899, 1779343271, 'user_request', '8', NULL),
(1900, 1779343271, 'user_request', '8', NULL),
(1901, 1779343272, 'user_request', '8', NULL),
(1902, 1779343272, 'user_request', '8', NULL),
(1903, 1779343272, 'user_request', '8', NULL),
(1904, 1779343273, 'user_request', '8', NULL),
(1905, 1779343273, 'user_request', '8', NULL),
(1906, 1779343274, 'user_request', '8', NULL),
(1907, 1779343274, 'user_request', '8', NULL),
(1908, 1779343275, 'user_request', '8', NULL),
(1909, 1779343275, 'user_request', '8', NULL),
(1910, 1779343275, 'user_request', '8', NULL),
(1911, 1779343276, 'user_request', '8', NULL),
(1912, 1779343276, 'user_request', '8', NULL),
(1913, 1779343276, 'user_request', '8', NULL),
(1914, 1779343277, 'user_request', '8', NULL),
(1915, 1779343277, 'user_request', '8', NULL),
(1916, 1779343278, 'user_request', '8', NULL),
(1917, 1779343278, 'user_request', '8', NULL),
(1918, 1779343278, 'user_request', '8', NULL),
(1919, 1779343279, 'user_request', '8', NULL),
(1920, 1779343279, 'user_request', '8', NULL),
(1921, 1779343280, 'user_request', '8', NULL),
(1922, 1779343280, 'user_request', '8', NULL),
(1923, 1779343280, 'user_request', '8', NULL),
(1924, 1779343281, 'user_request', '8', NULL),
(1925, 1779343281, 'user_request', '8', NULL),
(1926, 1779343282, 'user_request', '8', NULL),
(1927, 1779343282, 'user_request', '8', NULL),
(1928, 1779343282, 'user_request', '8', NULL),
(1929, 1779343283, 'user_request', '8', NULL),
(1930, 1779343283, 'user_request', '8', NULL),
(1931, 1779343284, 'user_request', '8', NULL),
(1932, 1779343284, 'user_request', '8', NULL),
(1933, 1779343284, 'user_request', '8', NULL),
(1934, 1779343285, 'user_request', '8', NULL),
(1935, 1779343285, 'user_request', '8', NULL),
(1936, 1779343285, 'user_request', '8', NULL),
(1937, 1779343286, 'user_request', '8', NULL),
(1938, 1779343286, 'user_request', '8', NULL),
(1939, 1779343287, 'user_request', '8', NULL),
(1940, 1779343287, 'user_request', '8', NULL),
(1941, 1779343287, 'user_request', '8', NULL),
(1942, 1779343288, 'user_request', '8', NULL),
(1943, 1779343288, 'user_request', '8', NULL),
(1944, 1779343289, 'user_request', '8', NULL),
(1945, 1779343289, 'user_request', '8', NULL),
(1946, 1779343289, 'user_request', '8', NULL),
(1947, 1779343290, 'user_request', '8', NULL),
(1948, 1779343290, 'user_request', '8', NULL),
(1949, 1779343291, 'user_request', '8', NULL),
(1950, 1779343291, 'user_request', '8', NULL),
(1951, 1779343292, 'user_request', '8', NULL),
(1952, 1779343292, 'user_request', '8', NULL),
(1953, 1779343292, 'user_request', '8', NULL),
(1954, 1779343293, 'user_request', '8', NULL),
(1955, 1779343293, 'user_request', '8', NULL),
(1956, 1779343294, 'user_request', '8', NULL),
(1957, 1779343294, 'user_request', '8', NULL),
(1958, 1779343294, 'user_request', '8', NULL),
(1959, 1779343295, 'user_request', '8', NULL),
(1960, 1779343295, 'user_request', '8', NULL),
(1961, 1779343296, 'user_request', '8', NULL),
(1962, 1779343296, 'user_request', '8', NULL),
(1963, 1779343297, 'user_request', '8', NULL),
(1964, 1779343297, 'user_request', '8', NULL),
(1965, 1779343297, 'user_request', '8', NULL),
(1966, 1779343298, 'user_request', '8', NULL),
(1967, 1779343298, 'user_request', '8', NULL),
(1968, 1779343298, 'user_request', '8', NULL),
(1969, 1779343299, 'user_request', '8', NULL),
(1970, 1779343299, 'user_request', '8', NULL),
(1971, 1779343300, 'user_request', '8', NULL),
(1972, 1779343300, 'user_request', '8', NULL),
(1973, 1779343300, 'user_request', '8', NULL),
(1974, 1779343301, 'user_request', '8', NULL),
(1975, 1779343301, 'user_request', '8', NULL),
(1976, 1779343302, 'user_request', '8', NULL),
(1977, 1779343302, 'user_request', '8', NULL),
(1978, 1779343303, 'user_request', '8', NULL),
(1979, 1779343303, 'user_request', '8', NULL),
(1980, 1779343303, 'user_request', '8', NULL),
(1981, 1779343304, 'user_request', '8', NULL),
(1982, 1779343304, 'user_request', '8', NULL),
(1983, 1779343305, 'user_request', '8', NULL),
(1984, 1779343305, 'user_request', '8', NULL),
(1985, 1779343306, 'user_request', '8', NULL),
(1986, 1779343306, 'user_request', '8', NULL),
(1987, 1779343306, 'user_request', '8', NULL),
(1988, 1779343307, 'user_request', '8', NULL),
(1989, 1779343307, 'user_request', '8', NULL),
(1990, 1779343308, 'user_request', '8', NULL),
(1991, 1779343308, 'user_request', '8', NULL),
(1992, 1779343308, 'user_request', '8', NULL),
(1993, 1779343309, 'user_request', '8', NULL),
(1994, 1779343309, 'user_request', '8', NULL),
(1995, 1779343310, 'user_request', '8', NULL),
(1996, 1779343310, 'user_request', '8', NULL),
(1997, 1779343310, 'user_request', '8', NULL),
(1998, 1779343311, 'user_request', '8', NULL),
(1999, 1779343311, 'user_request', '8', NULL),
(2000, 1779343311, 'user_request', '8', NULL),
(2001, 1779343312, 'user_request', '8', NULL),
(2002, 1779343312, 'user_request', '8', NULL),
(2003, 1779343313, 'user_request', '8', NULL),
(2004, 1779343313, 'user_request', '8', NULL),
(2005, 1779343314, 'user_request', '8', NULL),
(2006, 1779343314, 'user_request', '8', NULL),
(2007, 1779343315, 'user_request', '8', NULL),
(2008, 1779343315, 'user_request', '8', NULL),
(2009, 1779343316, 'user_request', '8', NULL),
(2010, 1779343316, 'user_request', '8', NULL),
(2011, 1779343316, 'user_request', '8', NULL),
(2012, 1779343317, 'user_request', '8', NULL),
(2013, 1779343317, 'user_request', '8', NULL),
(2014, 1779343318, 'user_request', '8', NULL),
(2015, 1779343318, 'user_request', '8', NULL),
(2016, 1779343319, 'user_request', '8', NULL),
(2017, 1779343319, 'user_request', '8', NULL),
(2018, 1779343319, 'user_request', '8', NULL),
(2019, 1779343320, 'user_request', '8', NULL),
(2020, 1779343336, 'user_request', '8', NULL),
(2021, 1779343336, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL);
INSERT INTO `pulse_entries` (`id`, `timestamp`, `type`, `key`, `value`) VALUES
(2022, 1779343336, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2023, 1779343336, 'user_request', '8', NULL),
(2024, 1779343492, 'user_request', '8', NULL),
(2025, 1779343622, 'user_request', '8', NULL),
(2026, 1779343648, 'user_request', '8', NULL),
(2027, 1779343668, 'user_request', '8', NULL),
(2028, 1779343668, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2029, 1779343668, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2030, 1779343668, 'user_request', '8', NULL),
(2031, 1779343712, 'user_request', '8', NULL),
(2032, 1779343714, 'user_request', '8', NULL),
(2033, 1779343717, 'user_request', '8', NULL),
(2034, 1779343744, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2035, 1779343745, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2036, 1779343751, 'user_request', '8', NULL),
(2037, 1779343751, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2038, 1779343752, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2039, 1779343752, 'user_request', '8', NULL),
(2040, 1779343809, 'user_request', '8', NULL),
(2041, 1779343814, 'user_request', '8', NULL),
(2042, 1779343819, 'user_request', '8', NULL),
(2043, 1779343960, 'user_request', '8', NULL),
(2044, 1779343963, 'user_request', '8', NULL),
(2045, 1779343974, 'user_request', '8', NULL),
(2046, 1779344088, 'user_request', '8', NULL),
(2047, 1779344094, 'user_request', '8', NULL),
(2048, 1779344096, 'user_request', '8', NULL),
(2049, 1779344098, 'user_request', '8', NULL),
(2050, 1779344100, 'user_request', '8', NULL),
(2051, 1779344101, 'user_request', '8', NULL),
(2052, 1779344546, 'user_request', '8', NULL),
(2053, 1779344586, 'user_request', '8', NULL),
(2054, 1779344597, 'user_request', '8', NULL),
(2055, 1779344600, 'user_request', '8', NULL),
(2056, 1779344603, 'user_request', '8', NULL),
(2057, 1779344686, 'user_request', '8', NULL),
(2058, 1779344690, 'user_request', '8', NULL),
(2059, 1779344756, 'user_request', '8', NULL),
(2060, 1779344789, 'user_request', '8', NULL),
(2061, 1779344793, 'user_request', '8', NULL),
(2062, 1779344797, 'user_request', '8', NULL),
(2063, 1779344813, 'user_request', '8', NULL),
(2064, 1779344979, 'user_request', '8', NULL),
(2065, 1779344986, 'user_request', '8', NULL),
(2066, 1779345023, 'user_request', '8', NULL),
(2067, 1779345023, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2068, 1779345024, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2069, 1779345024, 'user_request', '8', NULL),
(2070, 1779345183, 'user_request', '8', NULL),
(2071, 1779345186, 'user_request', '8', NULL),
(2072, 1779345191, 'user_request', '8', NULL),
(2073, 1779345198, 'user_request', '8', NULL),
(2074, 1779345202, 'user_request', '8', NULL),
(2075, 1779345238, 'user_request', '8', NULL),
(2076, 1779345308, 'user_request', '8', NULL),
(2077, 1779345310, 'user_request', '8', NULL),
(2078, 1779345370, 'user_request', '8', NULL),
(2079, 1779345429, 'user_request', '8', NULL),
(2080, 1779345443, 'user_request', '8', NULL),
(2081, 1779345444, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2082, 1779345444, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2083, 1779345444, 'user_request', '8', NULL),
(2084, 1779345550, 'user_request', '8', NULL),
(2085, 1779345552, 'user_request', '8', NULL),
(2086, 1779345572, 'user_request', '8', NULL),
(2087, 1779345638, 'user_request', '8', NULL),
(2088, 1779345641, 'user_request', '8', NULL),
(2089, 1779345646, 'user_request', '8', NULL),
(2090, 1779345851, 'cache_miss', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(2091, 1779345852, 'cache_hit', '7d57ee2629976602c1fc93f22d4d097a', NULL),
(2092, 1779345904, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 1213),
(2093, 1779345904, 'slow_user_request', '12', NULL),
(2094, 1779345904, 'user_request', '12', NULL),
(2095, 1779345905, 'user_request', '12', NULL),
(2096, 1779345909, 'user_request', '12', NULL),
(2097, 1779345923, 'user_request', '12', NULL),
(2098, 1779345940, 'user_request', '12', NULL),
(2099, 1779345945, 'user_request', '12', NULL),
(2100, 1779345959, 'user_request', '8', NULL),
(2101, 1779345959, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2102, 1779345960, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2103, 1779345960, 'user_request', '8', NULL),
(2104, 1779345973, 'user_request', '8', NULL),
(2105, 1779345997, 'user_request', '8', NULL),
(2106, 1779346087, 'user_request', '8', NULL),
(2107, 1779346246, 'user_request', '8', NULL),
(2108, 1779346337, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 1750),
(2109, 1779346351, 'user_request', '8', NULL),
(2110, 1779346356, 'user_request', '8', NULL),
(2111, 1779346359, 'user_request', '8', NULL),
(2112, 1779346366, 'user_request', '8', NULL),
(2113, 1779346366, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2114, 1779346366, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2115, 1779346366, 'user_request', '8', NULL),
(2116, 1779346384, 'user_request', '8', NULL),
(2117, 1779346384, 'user_request', '8', NULL),
(2118, 1779346391, 'user_request', '8', NULL),
(2119, 1779346405, 'user_request', '8', NULL),
(2120, 1779346407, 'user_request', '8', NULL),
(2121, 1779346408, 'user_request', '8', NULL),
(2122, 1779346409, 'user_request', '8', NULL),
(2123, 1779346410, 'user_request', '8', NULL),
(2124, 1779346415, 'user_request', '8', NULL),
(2125, 1779346418, 'user_request', '8', NULL),
(2126, 1779346419, 'user_request', '8', NULL),
(2127, 1779346420, 'user_request', '8', NULL),
(2128, 1779346429, 'user_request', '8', NULL),
(2129, 1779346472, 'user_request', '8', NULL),
(2130, 1779346477, 'user_request', '8', NULL),
(2131, 1779346671, 'user_request', '8', NULL),
(2132, 1779346671, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2133, 1779346672, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2134, 1779346672, 'user_request', '8', NULL),
(2135, 1779346705, 'user_request', '8', NULL),
(2136, 1779346712, 'user_request', '8', NULL),
(2137, 1779347575, 'user_request', '8', NULL),
(2138, 1779347576, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2139, 1779347576, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2140, 1779347576, 'user_request', '8', NULL),
(2141, 1779347579, 'user_request', '8', NULL),
(2142, 1779347719, 'user_request', '8', NULL),
(2143, 1779347722, 'user_request', '8', NULL),
(2144, 1779347727, 'user_request', '8', NULL),
(2145, 1779347816, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 1603),
(2146, 1779347818, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 1221),
(2147, 1779347823, 'user_request', '8', NULL),
(2148, 1779347828, 'user_request', '8', NULL),
(2149, 1779347832, 'user_request', '8', NULL),
(2150, 1779347834, 'user_request', '8', NULL),
(2151, 1779347836, 'user_request', '8', NULL),
(2152, 1779347836, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2153, 1779347837, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2154, 1779347837, 'user_request', '8', NULL),
(2155, 1779348182, 'user_request', '8', NULL),
(2156, 1779348186, 'user_request', '8', NULL),
(2157, 1779348192, 'user_request', '8', NULL),
(2158, 1779348206, 'user_request', '8', NULL),
(2159, 1779348208, 'user_request', '8', NULL),
(2160, 1779348209, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2161, 1779348209, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2162, 1779348209, 'user_request', '8', NULL),
(2163, 1779348686, 'user_request', '8', NULL),
(2164, 1779348720, 'slow_request', '[\"GET\",\"\\/super-admin\\/dashboard\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\DashboardController@index\"]', 2428),
(2165, 1779348720, 'slow_user_request', '8', NULL),
(2166, 1779348720, 'user_request', '8', NULL),
(2167, 1779348722, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 2045),
(2168, 1779348737, 'user_request', '8', NULL),
(2169, 1779348741, 'user_request', '8', NULL),
(2170, 1779348744, 'user_request', '8', NULL),
(2171, 1779348744, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2172, 1779348745, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2173, 1779348745, 'user_request', '8', NULL),
(2174, 1779348833, 'user_request', '8', NULL),
(2175, 1779348890, 'user_request', '8', NULL),
(2176, 1779349005, 'user_request', '8', NULL),
(2177, 1779349011, 'user_request', '8', NULL),
(2178, 1779349011, 'user_request', '8', NULL),
(2179, 1779349015, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 5129),
(2180, 1779349015, 'slow_user_request', '8', NULL),
(2181, 1779349015, 'user_request', '8', NULL),
(2182, 1779349016, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779349016),
(2183, 1779349029, 'user_request', '8', NULL),
(2184, 1779349030, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2185, 1779349030, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2186, 1779349030, 'user_request', '8', NULL),
(2187, 1779349034, 'user_request', '8', NULL),
(2188, 1779349036, 'user_request', '8', NULL),
(2189, 1779349041, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 3031),
(2190, 1779349041, 'slow_user_request', '8', NULL),
(2191, 1779349041, 'user_request', '8', NULL),
(2192, 1779349041, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"app\\\\Http\\\\Middleware\\\\HandleAppearance.php:21\"]', 1779349041),
(2193, 1779349122, 'user_request', '8', NULL),
(2194, 1779349123, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2195, 1779349123, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2196, 1779349123, 'user_request', '8', NULL),
(2197, 1779349129, 'user_request', '8', NULL),
(2198, 1779349132, 'user_request', '8', NULL),
(2199, 1779349140, 'user_request', '8', NULL),
(2200, 1779349140, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2201, 1779349141, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2202, 1779349141, 'user_request', '8', NULL),
(2203, 1779349144, 'user_request', '8', NULL),
(2204, 1779349147, 'user_request', '8', NULL),
(2205, 1779349160, 'user_request', '8', NULL),
(2206, 1779349160, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2207, 1779349160, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', NULL),
(2208, 1779349161, 'user_request', '8', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pulse_values`
--

CREATE TABLE `pulse_values` (
  `id` bigint UNSIGNED NOT NULL,
  `timestamp` int UNSIGNED NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` binary(16) GENERATED ALWAYS AS (unhex(md5(`key`))) VIRTUAL,
  `value` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurants`
--

CREATE TABLE `restaurants` (
  `id` bigint UNSIGNED NOT NULL,
  `plan_id` bigint UNSIGNED DEFAULT NULL,
  `owner_user_id` bigint UNSIGNED DEFAULT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Ho_Chi_Minh',
  `currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `status` enum('active','expired','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `subscription_started_at` date DEFAULT NULL,
  `subscription_ends_at` date DEFAULT NULL,
  `trial_ends_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `restaurants`
--

INSERT INTO `restaurants` (`id`, `plan_id`, `owner_user_id`, `code`, `name`, `slug`, `tax_code`, `phone`, `email`, `address`, `logo_url`, `timezone`, `currency`, `status`, `subscription_started_at`, `subscription_ends_at`, `trial_ends_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 2, 1, 'FNBVIET-DEMO', 'Aventura Demo', 'aventura-demo', NULL, '02873000001', 'hello@bepso.test', '1 Nguyen Hue, Quan 1, TP.HCM', NULL, 'Asia/Ho_Chi_Minh', 'VND', 'active', '2026-05-20', '2026-06-20', NULL, '2026-05-19 22:20:33', '2026-05-20 07:18:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_branches`
--

CREATE TABLE `restaurant_branches` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manager_user_id` bigint UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `restaurant_branches`
--

INSERT INTO `restaurant_branches` (`id`, `restaurant_id`, `code`, `name`, `phone`, `email`, `address`, `manager_user_id`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Q1', 'Chi nhanh Quan 1', '02873000002', 'q1@bepso.test', '1 Nguyen Hue, Quan 1, TP.HCM', 2, 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_revenue_summaries`
--

CREATE TABLE `restaurant_revenue_summaries` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `summary_type` enum('daily','weekly','monthly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'daily',
  `scope_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'restaurant',
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
  `source` enum('system','manual','rebuild') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
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
  `key_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `status` enum('trial','active','expired','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `renewal_at` datetime DEFAULT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `meta` json DEFAULT NULL,
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
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int UNSIGNED NOT NULL DEFAULT '2',
  `status` enum('available','occupied','reserved','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `restaurant_tables`
--

INSERT INTO `restaurant_tables` (`id`, `restaurant_id`, `branch_id`, `area_id`, `name`, `qr_code`, `capacity`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 'A1', 'QR-A1', 4, 'occupied', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL),
(2, 1, 1, 1, 'A2', 'QR-A2', 4, 'available', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(2, 'owner', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(3, 'manager', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(4, 'cashier', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(5, 'kitchen', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(6, 'inventory_staff', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(7, 'customer', 'web', '2026-05-19 22:20:31', '2026-05-19 22:20:31'),
(8, 'admin', 'web', '2026-05-20 07:04:24', '2026-05-20 07:04:24');

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
  `status` enum('draft','approved','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
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
  `type` enum('bonus','penalty','cash_shortage','inventory_loss','violation') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint UNSIGNED DEFAULT NULL,
  `reference_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
  `status` enum('scheduled','checked_in','completed','absent','leave_approved') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedule_assignments`
--

INSERT INTO `schedule_assignments` (`id`, `restaurant_id`, `branch_id`, `employee_id`, `shift_id`, `scheduled_date`, `check_in_at`, `check_out_at`, `status`, `notes`, `approved_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 3, 1, '2026-05-20', NULL, NULL, 'scheduled', NULL, 2, '2026-05-19 22:20:35', '2026-05-19 22:20:35');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('L5dEtCddj7EEMmjCnNNtF6kuZGgzYZvp3QAKZBay', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.121.0 Chrome/142.0.7444.265 Electron/39.8.8 Safari/537.36', 'eyJfdG9rZW4iOiJpWmFvZzVrYlA4MTRRVHFnRHhYZ2JMUTBpd1BRWWQzUUNoOXNRSjd4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zdXBlci1hZG1pblwvcmVzdGF1cmFudHMiLCJyb3V0ZSI6InN1cGVyYWRtaW4ucmVzdGF1cmFudHMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OH0=', 1779349133),
('LDXLQiKQzG5uegLSayCsOM6NMjWm7r7Qi1tVI5Z9', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJTd0E4M3lzNHh3QjJLS0hkT09VQ2xld2RDVTJXSWR0V29zWFNvQ0M2IiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDAiLCJyb3V0ZSI6ImhvbWUifSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjh9', 1779349161),
('pX3hMfod0tnLDLvbM5jB1X0B12De9hJ3M0Jnx384', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiIyS0xBT0hZZWpCR21yMTNYSE52akF6UjV5V2NwdE5MZFJORWQ3cGxkIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OCwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zdXBlci1hZG1pblwvZGFzaGJvYXJkIiwicm91dGUiOiJzdXBlcmFkbWluLmRhc2hib2FyZCJ9fQ==', 1779346087),
('v98M5dIPFxwdCdoTiKUkfA6QkU50ExibbW8eDHgM', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJ1WnQ4RWtBZHpuWHl1Qm8wZTl1NGJYY2xycWsyTXM4M09XbVZSalZlIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zdXBlci1hZG1pblwvZGFzaGJvYXJkIiwicm91dGUiOiJzdXBlcmFkbWluLmRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX0sImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjo4fQ==', 1779345186),
('xeexGdEje6KdGPc2u4Oy3wDdQee2idfB6Xe8qToT', 8, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJRNjVOOGcyNEN0WHVEUTlzTjZXaDF2WDJMREpmcmJLaWlHV004QkhBIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OH0=', 1779342050);

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
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','submitted','confirmed','disputed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
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
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `billing_cycle` enum('monthly','quarterly','yearly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `max_branches` int UNSIGNED DEFAULT NULL,
  `max_tables` int UNSIGNED DEFAULT NULL,
  `max_users` int UNSIGNED DEFAULT NULL,
  `features` json DEFAULT NULL,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `code`, `name`, `price`, `billing_cycle`, `max_branches`, `max_tables`, `max_users`, `features`, `status`, `created_at`, `updated_at`) VALUES
(1, 'FREE', 'Miễn phí', 0.00, 'monthly', 1, 10, 5, '{\"realtime\": false, \"max_areas\": 2, \"ai_features\": false, \"api_rate_limit\": 60, \"max_storage_mb\": 500, \"advanced_analytics\": false}', 'active', '2026-05-19 22:20:23', '2026-05-20 12:26:59'),
(2, 'PRO', 'Cao cấp', 299000.00, 'monthly', NULL, NULL, NULL, '{\"realtime\": true, \"max_areas\": null, \"ai_features\": true, \"api_rate_limit\": 600, \"max_storage_mb\": 10240, \"advanced_analytics\": true}', 'active', '2026-05-19 22:20:23', '2026-05-20 12:26:59');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED NOT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `restaurant_id`, `branch_id`, `name`, `contact_name`, `phone`, `email`, `address`, `notes`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'Nha cung cap Demo', 'Tran Demo', '0911111111', NULL, NULL, NULL, 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('mass','volume','count') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'count',
  `base_unit_id` bigint UNSIGNED DEFAULT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `restaurant_id`, `name`, `symbol`, `type`, `base_unit_id`, `conversion_factor`, `created_at`, `updated_at`) VALUES
(1, 1, 'Gram', 'g', 'mass', NULL, 1.0000, '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(2, 1, 'Ly', 'ly', 'count', NULL, 1.0000, '2026-05-19 22:20:34', '2026-05-19 22:20:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `restaurant_id` bigint UNSIGNED DEFAULT NULL,
  `branch_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `google_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `two_factor_secret` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `two_factor_recovery_codes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `restaurant_id`, `branch_id`, `name`, `email`, `google_id`, `phone`, `avatar_url`, `status`, `last_login_at`, `email_verified_at`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Owner Demo', 'owner@bepso.test', NULL, '0900000001', NULL, 'active', NULL, '2026-05-20 12:26:59', '$2y$12$cnVFBzEuhV/bOcMpHnv91OA3eFjyEHekGLbjfgn4f8vu71PaPdK5u', NULL, NULL, NULL, '5Xc1hFQBvQ66wXgiGFqRPbq4v95LtROYltZxN1kweGW3MmtkfiINKSbWoBPA', '2026-05-19 22:20:33', '2026-05-20 12:26:59'),
(2, 1, 1, 'Manager Demo', 'manager@bepso.test', NULL, '0900000002', NULL, 'active', NULL, '2026-05-20 12:27:00', '$2y$12$mhC8PRxbEm1dW/aOvEwZO.PdXhH6coAKEDT3VYVIbuVCjLAf5zTNe', NULL, NULL, NULL, NULL, '2026-05-19 22:20:33', '2026-05-20 12:27:00'),
(3, 1, 1, 'Cashier Demo', 'cashier@bepso.test', NULL, '0900000003', NULL, 'active', NULL, '2026-05-20 12:27:00', '$2y$12$NYu2UnV77hXs6mMCOupnG.v47zZbrvrw1lk8ExUTY9ICptLxLNmvy', NULL, NULL, NULL, NULL, '2026-05-19 22:20:34', '2026-05-20 12:27:00'),
(4, 1, 1, 'Kitchen Demo', 'kitchen@bepso.test', NULL, '0900000004', NULL, 'active', NULL, '2026-05-20 12:27:00', '$2y$12$wu/H6YgMVA3mxaTobjFerekAVN09iKbRNvrMDiBPB6Llt9tdSC022', NULL, NULL, NULL, NULL, '2026-05-19 22:20:34', '2026-05-20 12:27:00'),
(5, 1, 1, 'Inventory Demo', 'inventory@bepso.test', NULL, '0900000005', NULL, 'active', NULL, '2026-05-20 12:27:00', '$2y$12$osz6.dpmx5YQZMlpJFu.0.WgNLawJZRVSf6b447JdxMVya5Ky6mum', NULL, NULL, NULL, NULL, '2026-05-19 22:20:34', '2026-05-20 12:27:00'),
(6, NULL, NULL, 'Dik', 'duongndph53424@gmail.com', '117321937622173959644', NULL, NULL, 'active', '2026-05-20 09:40:39', '2026-05-20 06:24:31', '$2y$12$mEx0YwS81aPAUv7DSOhPWu8PwpRr12g4o5y2d7JG4u4r.FTleAb06', NULL, NULL, NULL, 'dE2YzpTClC3QPNuhkQiyjHZGqfMtv5w8TjVdDKtQ52nmW5leytkHGqyPrrYk', '2026-05-19 22:38:16', '2026-05-20 09:40:39'),
(7, NULL, NULL, 'Dik', 'dikndph53424@gmail.com', NULL, NULL, NULL, 'active', NULL, NULL, '$2y$12$kli2WYOa7sfzjK0BSmWddeW6ENUJUo4kfkvMAwfzycFMRU6O/2YFq', NULL, NULL, NULL, NULL, '2026-05-20 05:52:33', '2026-05-20 05:52:33'),
(8, NULL, NULL, 'Super Admin', 'superadmin@aventura.local', NULL, NULL, NULL, 'active', '2026-05-21 00:39:20', NULL, '$2y$12$fqh5lJ3S9dMQa2dFQppwkumqvlKl081AS4D.P18M9AE76GvRDHx8.', NULL, NULL, NULL, 'W4bijK6eQs6XB8E33nYgKsCuRKvuIq64jEgniCkjEiLkMJlI9lulYN33MiVP', '2026-05-20 07:04:25', '2026-05-21 00:39:20'),
(9, NULL, NULL, 'Duong', 'dik2610@gmail.com', NULL, NULL, NULL, 'active', '2026-05-20 09:41:04', NULL, '$2y$12$u09pq5Dr4J8z/8ecS6XGO.6xBktHMi4lnzw6JJR/PtSO1iej/Gh.a', NULL, NULL, NULL, NULL, '2026-05-20 08:41:11', '2026-05-20 09:41:04'),
(10, NULL, NULL, 'Quân Lê', 'ok@gmail.com', NULL, NULL, NULL, 'active', '2026-05-20 12:44:42', NULL, '$2y$12$3YO.U56VGNkl5vs8uj8Yl.hBgfi6mOuJnYQRqLsS5HbB.mcwfk44G', NULL, NULL, NULL, NULL, '2026-05-20 10:29:41', '2026-05-20 12:44:42'),
(11, NULL, NULL, 'Văn Quân Lê', 'tamh77573@gmail.com', '116627655680193977921', NULL, NULL, 'active', '2026-05-20 22:11:03', '2026-05-20 13:03:11', '$2y$12$d5K44ZvrPHxxS6TJzHeEw.ql9Nh8W3l3dxW4ng323dPIQb/nFI5Ri', NULL, NULL, NULL, 'kfE5IDCOlzlaBgkryGVtrOjlOjRuHAMg7h6wgQqFUSvnbKqBd31Ft5mS5kvR', '2026-05-20 13:03:11', '2026-05-20 22:11:03'),
(12, NULL, NULL, 'CHIEN', 'msaa230905@gmail.com', NULL, NULL, NULL, 'active', '2026-05-20 23:45:05', NULL, '$2y$12$mr3Nk6h1XCiPe9jsQXtJKOp.rpwxeanCDwigIk4b.2VxgJwEs5slu', NULL, NULL, NULL, NULL, '2026-05-20 23:45:05', '2026-05-20 23:45:05');

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
  `violation_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high','critical') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'low',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `penalty_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `occurred_at` datetime NOT NULL,
  `status` enum('open','reviewed','resolved','dismissed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
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
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_overnight` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_shifts`
--

INSERT INTO `work_shifts` (`id`, `restaurant_id`, `branch_id`, `name`, `code`, `start_time`, `end_time`, `is_overnight`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Ca sang', 'CA-SANG', '08:00:00', '16:00:00', 0, 'active', '2026-05-19 22:20:35', '2026-05-20 12:27:01');

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
  ADD KEY `restaurant_subscriptions_restaurant_status_index` (`restaurant_id`,`status`),
  ADD KEY `restaurant_subscriptions_plan_index` (`plan_id`);

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_feedback`
--
ALTER TABLE `customer_feedback`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ingredients`
--
ALTER TABLE `ingredients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `inventories`
--
ALTER TABLE `inventories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_recipes`
--
ALTER TABLE `product_recipes`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pulse_aggregates`
--
ALTER TABLE `pulse_aggregates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9106;

--
-- AUTO_INCREMENT for table `pulse_entries`
--
ALTER TABLE `pulse_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2209;

--
-- AUTO_INCREMENT for table `pulse_values`
--
ALTER TABLE `pulse_values`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurants`
--
ALTER TABLE `restaurants`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `restaurant_branches`
--
ALTER TABLE `restaurant_branches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurant_tables`
--
ALTER TABLE `restaurant_tables`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shift_closings`
--
ALTER TABLE `shift_closings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `violation_reports`
--
ALTER TABLE `violation_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_shifts`
--
ALTER TABLE `work_shifts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
