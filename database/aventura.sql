-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 20, 2026 at 04:44 PM
-- Server version: 8.0.30
-- PHP Version: 8.3.6

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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `restaurant_id`, `branch_id`, `user_id`, `user_role`, `event`, `action`, `subject_type`, `subject_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 1, 3, 'cashier', 'created', 'seed_demo_order', 'App\\Models\\Order', 1, NULL, '{\"order_number\": \"ORD-DEMO-001\", \"total_amount\": 100000}', '127.0.0.1', 'database-seeder', '2026-05-19 22:20:35');

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
('aventura-cache-a2c520e03217b53389c41abc5727cff1', 'i:2;', 1779295271),
('aventura-cache-a2c520e03217b53389c41abc5727cff1:timer', 'i:1779295271;', 1779295271),
('aventura-cache-dcacbc44a7737aebdda0ba04d9733cb1', 'i:1;', 1779294817),
('aventura-cache-dcacbc44a7737aebdda0ba04d9733cb1:timer', 'i:1779294817;', 1779294817);

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

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `restaurant_id`, `branch_id`, `full_name`, `phone`, `email`, `gender`, `date_of_birth`, `notes`, `loyalty_points`, `last_order_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'Khach Demo', '0988888888', NULL, NULL, NULL, NULL, 120, '2026-05-20 05:20:35', '2026-05-19 22:20:35', '2026-05-19 22:20:35', NULL);

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

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `restaurant_id`, `branch_id`, `user_id`, `employee_code`, `full_name`, `date_of_birth`, `gender`, `phone`, `email`, `address`, `citizen_id_number`, `citizen_id_front_url`, `citizen_id_back_url`, `hire_date`, `employment_type`, `job_title`, `base_salary`, `status`, `created_at`, `updated_at`, `role_id`, `deleted_at`) VALUES
(1, 1, 1, 1, 'EMP-001', 'Owner Demo', NULL, NULL, '0900000001', 'owner@bepso.test', NULL, '079099442483', NULL, NULL, '2026-02-20', 'full_time', 'Owner', '9000000.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', 2, NULL),
(2, 1, 1, 2, 'EMP-002', 'Manager Demo', NULL, NULL, '0900000002', 'manager@bepso.test', NULL, '079064118870', NULL, NULL, '2026-02-20', 'full_time', 'Manager', '9000000.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', 3, NULL),
(3, 1, 1, 3, 'EMP-003', 'Cashier Demo', NULL, NULL, '0900000003', 'cashier@bepso.test', NULL, '079097396823', NULL, NULL, '2026-02-20', 'full_time', 'Cashier', '9000000.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', 4, NULL),
(4, 1, 1, 4, 'EMP-004', 'Kitchen Demo', NULL, NULL, '0900000004', 'kitchen@bepso.test', NULL, '079030918262', NULL, NULL, '2026-02-20', 'full_time', 'Kitchen', '9000000.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', 5, NULL),
(5, 1, 1, 5, 'EMP-005', 'Inventory Demo', NULL, NULL, '0900000005', 'inventory@bepso.test', NULL, '079082172907', NULL, NULL, '2026-02-20', 'full_time', 'Inventory Staff', '9000000.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', 6, NULL);

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

--
-- Dumping data for table `ingredients`
--

INSERT INTO `ingredients` (`id`, `restaurant_id`, `branch_id`, `supplier_id`, `unit_id`, `name`, `sku`, `category_name`, `description`, `min_stock_level`, `reorder_level`, `average_cost`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 1, 'Thit bo', 'BEEF-001', 'Thit', NULL, '1000.000', '2000.000', '280.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL),
(2, 1, 1, 1, 1, 'Banh pho', 'NOODLE-001', 'Kho', NULL, '2000.000', '5000.000', '40.00', 'active', '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

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
(1, 1, 1, 1, '8000.000', '7800.000', '2026-05-20 05:20:34', '280.00', 5, '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(2, 1, 1, 2, '15000.000', '14900.000', '2026-05-20 05:20:34', '40.00', 5, '2026-05-19 22:20:34', '2026-05-19 22:20:34');

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
(26, '2026_05_20_131500_add_google_id_to_users_table', 2);

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

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `restaurant_id`, `branch_id`, `table_id`, `customer_id`, `created_by`, `cashier_user_id`, `order_number`, `channel`, `status`, `payment_status`, `subtotal`, `discount_amount`, `service_charge`, `tax_amount`, `total_amount`, `note`, `confirmed_at`, `completed_at`, `cancelled_at`, `cancelled_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 1, 3, 3, 'ORD-DEMO-001', 'dine_in', 'completed', 'paid', '100000.00', '0.00', '0.00', '0.00', '100000.00', NULL, '2026-05-20 05:00:35', '2026-05-20 05:15:35', NULL, NULL, '2026-05-19 22:20:35', '2026-05-19 22:20:35', NULL);

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

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `restaurant_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `discount_amount`, `line_total`, `status`, `notes`, `sent_to_kitchen_at`, `prepared_at`, `served_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, '1.00', '65000.00', '0.00', '65000.00', 'served', NULL, '2026-05-20 05:01:35', '2026-05-20 05:08:35', '2026-05-20 05:13:35', '2026-05-19 22:20:35', '2026-05-19 22:20:35'),
(2, 1, 1, 2, '1.00', '35000.00', '0.00', '35000.00', 'served', NULL, '2026-05-20 05:01:35', '2026-05-20 05:10:35', '2026-05-20 05:14:35', '2026-05-19 22:20:35', '2026-05-19 22:20:35');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `restaurant_id`, `branch_id`, `order_id`, `processed_by`, `payment_method`, `status`, `amount`, `cash_received`, `change_amount`, `transaction_code`, `gateway_transaction_code`, `paid_at`, `meta`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 3, 'cash', 'paid', '100000.00', '100000.00', '0.00', 'PAY-DEMO-001', NULL, '2026-05-20 05:15:35', '{\"source\": \"demo-seeder\"}', '2026-05-19 22:20:35', '2026-05-19 22:20:35');

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

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `restaurant_id`, `branch_id`, `category_id`, `code`, `name`, `slug`, `description`, `image_url`, `price`, `cost_price`, `preparation_time_minutes`, `is_active`, `is_available`, `is_featured`, `track_inventory`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 1, 'PHO-BO', 'Pho bo tai', 'pho-bo-tai', 'Pho bo tai truyen thong', NULL, '65000.00', '28000.00', 12, 1, 1, 0, 1, '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL),
(2, 1, 1, 1, 'TRA-DAO', 'Tra dao', 'tra-dao', 'Tra dao mat lanh', NULL, '35000.00', '12000.00', 5, 1, 1, 0, 0, '2026-05-19 22:20:34', '2026-05-19 22:20:34', NULL);

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
  `notes` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_recipes`
--

INSERT INTO `product_recipes` (`id`, `restaurant_id`, `product_id`, `ingredient_id`, `unit_id`, `quantity`, `waste_rate`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, '120.000', '2.00', NULL, '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(2, 1, 1, 2, 1, '180.000', '1.00', NULL, '2026-05-19 22:20:35', '2026-05-19 22:20:35');

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
(1, 1779254460, 60, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(2, 1779254280, 360, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(3, 1779253920, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(4, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '2.00', NULL),
(5, 1779254460, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(6, 1779254280, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(7, 1779253920, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '3.00', NULL),
(8, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '3.00', NULL),
(9, 1779254460, 60, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '12538.00', NULL),
(10, 1779254280, 360, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '12538.00', NULL),
(11, 1779253920, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '12538.00', NULL),
(12, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '12538.00', NULL),
(13, 1779254460, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '14231.00', NULL),
(14, 1779254280, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '14231.00', NULL),
(15, 1779253920, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '14231.00', NULL),
(16, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '14231.00', NULL),
(17, 1779254580, 60, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', '1.00', NULL),
(18, 1779254280, 360, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', '1.00', NULL),
(19, 1779253920, 1440, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', '2.00', NULL),
(20, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', '2.00', NULL),
(21, 1779254580, 60, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', '5231.00', NULL),
(22, 1779254280, 360, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', '5231.00', NULL),
(23, 1779253920, 1440, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', '5231.00', NULL),
(24, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', '5231.00', NULL),
(25, 1779254700, 60, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', '1.00', NULL),
(26, 1779254640, 360, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'count', '1.00', NULL),
(27, 1779254700, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', '1.00', NULL),
(28, 1779254640, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', '1.00', NULL),
(29, 1779253920, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', '1.00', NULL),
(30, 1779251040, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'count', '1.00', NULL),
(33, 1779254700, 60, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', '2795.00', NULL),
(34, 1779254640, 360, 'slow_request', '[\"GET\",\"\\/forgot-password\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\PasswordResetLinkController@create\"]', 'max', '2795.00', NULL),
(35, 1779254700, 60, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', '2158.00', NULL),
(36, 1779254640, 360, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', '2158.00', NULL),
(37, 1779253920, 1440, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', '2158.00', NULL),
(38, 1779251040, 10080, 'slow_outgoing_request', '[\"POST\",\"http:\\/\\/[::1]:5173\\/__inertia_ssr\"]', 'max', '2158.00', NULL),
(41, 1779255300, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(42, 1779255000, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(43, 1779253920, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(44, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(45, 1779255300, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1316.00', NULL),
(46, 1779255000, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1316.00', NULL),
(47, 1779253920, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1316.00', NULL),
(48, 1779251040, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1316.00', NULL),
(49, 1779255300, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '2.00', NULL),
(50, 1779255000, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '2.00', NULL),
(51, 1779255300, 60, 'cache_miss', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '1.00', NULL),
(52, 1779255000, 360, 'cache_miss', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '1.00', NULL),
(53, 1779253920, 1440, 'cache_miss', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '1.00', NULL),
(54, 1779251040, 10080, 'cache_miss', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '1.00', NULL),
(55, 1779255300, 60, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '3.00', NULL),
(56, 1779255000, 360, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '3.00', NULL),
(57, 1779253920, 1440, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '3.00', NULL),
(58, 1779251040, 10080, 'cache_hit', 'aa3dec81a446685560671e3fda3b7bbc', 'count', '3.00', NULL),
(61, 1779255300, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2053.00', NULL),
(62, 1779255000, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2053.00', NULL),
(65, 1779255300, 60, 'slow_user_request', '1', 'count', '1.00', NULL),
(66, 1779255000, 360, 'slow_user_request', '1', 'count', '1.00', NULL),
(67, 1779253920, 1440, 'slow_user_request', '1', 'count', '1.00', NULL),
(68, 1779251040, 10080, 'slow_user_request', '1', 'count', '2.00', NULL),
(69, 1779255300, 60, 'user_request', '1', 'count', '1.00', NULL),
(70, 1779255000, 360, 'user_request', '1', 'count', '1.00', NULL),
(71, 1779253920, 1440, 'user_request', '1', 'count', '1.00', NULL),
(72, 1779251040, 10080, 'user_request', '1', 'count', '8.00', NULL),
(85, 1779255360, 60, 'user_request', '1', 'count', '4.00', NULL),
(86, 1779255360, 360, 'user_request', '1', 'count', '7.00', NULL),
(87, 1779255360, 1440, 'user_request', '1', 'count', '7.00', NULL),
(101, 1779255420, 60, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(102, 1779255360, 360, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(103, 1779255360, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(104, 1779255420, 60, 'slow_user_request', '1', 'count', '1.00', NULL),
(105, 1779255360, 360, 'slow_user_request', '1', 'count', '1.00', NULL),
(106, 1779255360, 1440, 'slow_user_request', '1', 'count', '1.00', NULL),
(107, 1779255420, 60, 'user_request', '1', 'count', '3.00', NULL),
(113, 1779255420, 60, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '2889.00', NULL),
(114, 1779255360, 360, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '2889.00', NULL),
(115, 1779255360, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '2889.00', NULL),
(125, 1779255480, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(126, 1779255360, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(127, 1779255360, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(128, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(129, 1779255480, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '1751.00', NULL),
(130, 1779255360, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '1751.00', NULL),
(131, 1779255360, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '1751.00', NULL),
(132, 1779251040, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '1751.00', NULL),
(133, 1779255480, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(134, 1779255360, 360, 'slow_user_request', '6', 'count', '1.00', NULL),
(135, 1779255360, 1440, 'slow_user_request', '6', 'count', '1.00', NULL),
(136, 1779251040, 10080, 'slow_user_request', '6', 'count', '1.00', NULL),
(137, 1779255480, 60, 'user_request', '6', 'count', '3.00', NULL),
(138, 1779255360, 360, 'user_request', '6', 'count', '3.00', NULL),
(139, 1779255360, 1440, 'user_request', '6', 'count', '3.00', NULL),
(140, 1779251040, 10080, 'user_request', '6', 'count', '3.00', NULL),
(154, 1779280980, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(155, 1779280920, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(156, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(157, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(158, 1779280980, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '2302.00', NULL),
(159, 1779280920, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '2302.00', NULL),
(160, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '2302.00', NULL),
(161, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '2302.00', NULL),
(162, 1779281100, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(163, 1779280920, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(164, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(165, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(166, 1779281100, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'count', '1.00', NULL),
(167, 1779280920, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'count', '1.00', NULL),
(168, 1779279840, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'count', '1.00', NULL),
(169, 1779271200, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'count', '1.00', NULL),
(170, 1779281100, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '3096.00', NULL),
(171, 1779280920, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '3096.00', NULL),
(172, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '3096.00', NULL),
(173, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '3096.00', NULL),
(174, 1779281100, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'max', '1779281113.00', NULL),
(175, 1779280920, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'max', '1779281113.00', NULL),
(176, 1779279840, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'max', '1779281113.00', NULL),
(177, 1779271200, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController.php:15\"]', 'max', '1779281113.00', NULL),
(178, 1779281160, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(179, 1779280920, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(180, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(181, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(182, 1779281160, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1111.00', NULL),
(183, 1779280920, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1111.00', NULL),
(184, 1779279840, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1111.00', NULL),
(185, 1779271200, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1111.00', NULL),
(186, 1779281460, 60, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(187, 1779281280, 360, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(188, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(189, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'count', '1.00', NULL),
(190, 1779281460, 60, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '1293.00', NULL),
(191, 1779281280, 360, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '1293.00', NULL),
(192, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '1293.00', NULL),
(193, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@create\"]', 'max', '1293.00', NULL),
(194, 1779281460, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '1.00', NULL),
(195, 1779281280, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(196, 1779281280, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(197, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '2.00', NULL),
(198, 1779281460, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '3622.00', NULL),
(199, 1779281280, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '3622.00', NULL),
(200, 1779281280, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '3622.00', NULL),
(201, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '3622.00', NULL),
(202, 1779281520, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '1.00', NULL),
(203, 1779281520, 60, 'slow_user_request', '7', 'count', '1.00', NULL),
(204, 1779281280, 360, 'slow_user_request', '7', 'count', '1.00', NULL),
(205, 1779281280, 1440, 'slow_user_request', '7', 'count', '1.00', NULL),
(206, 1779281280, 10080, 'slow_user_request', '7', 'count', '1.00', NULL),
(207, 1779281520, 60, 'user_request', '7', 'count', '3.00', NULL),
(208, 1779281280, 360, 'user_request', '7', 'count', '3.00', NULL),
(209, 1779281280, 1440, 'user_request', '7', 'count', '5.00', NULL),
(210, 1779281280, 10080, 'user_request', '7', 'count', '5.00', NULL),
(214, 1779281520, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '1249.00', NULL),
(226, 1779281820, 60, 'user_request', '7', 'count', '2.00', NULL),
(227, 1779281640, 360, 'user_request', '7', 'count', '2.00', NULL),
(234, 1779281820, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(235, 1779281640, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(236, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(237, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '2.00', NULL),
(238, 1779281820, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1129.00', NULL),
(239, 1779281640, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1129.00', NULL),
(240, 1779281280, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1129.00', NULL),
(241, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1898.00', NULL),
(242, 1779283440, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(243, 1779283440, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(244, 1779282720, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(245, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '4.00', NULL),
(246, 1779283440, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(247, 1779283440, 360, 'slow_user_request', '6', 'count', '1.00', NULL),
(248, 1779282720, 1440, 'slow_user_request', '6', 'count', '1.00', NULL),
(249, 1779281280, 10080, 'slow_user_request', '6', 'count', '4.00', NULL),
(250, 1779283440, 60, 'user_request', '6', 'count', '2.00', NULL),
(251, 1779283440, 360, 'user_request', '6', 'count', '2.00', NULL),
(252, 1779282720, 1440, 'user_request', '6', 'count', '2.00', NULL),
(253, 1779281280, 10080, 'user_request', '6', 'count', '25.00', NULL),
(254, 1779283440, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2116.00', NULL),
(255, 1779283440, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2116.00', NULL),
(256, 1779282720, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2116.00', NULL),
(257, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2116.00', NULL),
(262, 1779284640, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(263, 1779284520, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '2.00', NULL),
(264, 1779284160, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '2.00', NULL),
(265, 1779284640, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(266, 1779284520, 360, 'slow_user_request', '6', 'count', '2.00', NULL),
(267, 1779284160, 1440, 'slow_user_request', '6', 'count', '2.00', NULL),
(268, 1779284640, 60, 'user_request', '6', 'count', '5.00', NULL),
(269, 1779284520, 360, 'user_request', '6', 'count', '11.00', NULL),
(270, 1779284160, 1440, 'user_request', '6', 'count', '11.00', NULL),
(274, 1779284640, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1501.00', NULL),
(275, 1779284520, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1501.00', NULL),
(276, 1779284160, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1501.00', NULL),
(294, 1779284700, 60, 'user_request', '6', 'count', '1.00', NULL),
(298, 1779284760, 60, 'user_request', '6', 'count', '5.00', NULL),
(302, 1779284760, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(303, 1779284760, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(314, 1779284760, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1009.00', NULL),
(330, 1779285180, 60, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'count', '2.00', NULL),
(331, 1779284880, 360, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'count', '2.00', NULL),
(332, 1779284160, 1440, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'count', '2.00', NULL),
(333, 1779281280, 10080, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'count', '2.00', NULL),
(334, 1779285180, 60, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'max', '1779285215.00', NULL),
(335, 1779284880, 360, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'max', '1779285215.00', NULL),
(336, 1779284160, 1440, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'max', '1779285215.00', NULL),
(337, 1779281280, 10080, 'exception', '[\"Illuminate\\\\Database\\\\QueryException\",\"database\\\\seeders\\\\system\\\\SubscriptionPlanSeeder.php:52\"]', 'max', '1779285215.00', NULL),
(338, 1779285780, 60, 'user_request', '6', 'count', '7.00', NULL),
(339, 1779285600, 360, 'user_request', '6', 'count', '10.00', NULL),
(340, 1779285600, 1440, 'user_request', '6', 'count', '10.00', NULL),
(366, 1779285840, 60, 'user_request', '6', 'count', '3.00', NULL),
(378, 1779285900, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(379, 1779285600, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(380, 1779285600, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(381, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(382, 1779285900, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(383, 1779285600, 360, 'slow_user_request', '8', 'count', '1.00', NULL),
(384, 1779285600, 1440, 'slow_user_request', '8', 'count', '2.00', NULL),
(385, 1779281280, 10080, 'slow_user_request', '8', 'count', '3.00', NULL),
(386, 1779285900, 60, 'user_request', '8', 'count', '2.00', NULL),
(387, 1779285600, 360, 'user_request', '8', 'count', '2.00', NULL),
(388, 1779285600, 1440, 'user_request', '8', 'count', '18.00', NULL),
(389, 1779281280, 10080, 'user_request', '8', 'count', '22.00', NULL),
(390, 1779285900, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(391, 1779285600, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(392, 1779285600, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(393, 1779281280, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(394, 1779285900, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(395, 1779285600, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(396, 1779285600, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(397, 1779281280, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(398, 1779285900, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '1255.00', NULL),
(399, 1779285600, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '1255.00', NULL),
(400, 1779285600, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '1255.00', NULL),
(401, 1779281280, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '1255.00', NULL),
(406, 1779285960, 60, 'user_request', '8', 'count', '1.00', NULL),
(407, 1779285960, 360, 'user_request', '8', 'count', '1.00', NULL),
(410, 1779286560, 60, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(411, 1779286320, 360, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(412, 1779285600, 1440, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(413, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(414, 1779286560, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(415, 1779286320, 360, 'slow_user_request', '8', 'count', '1.00', NULL),
(416, 1779286560, 60, 'user_request', '8', 'count', '6.00', NULL),
(417, 1779286320, 360, 'user_request', '8', 'count', '6.00', NULL),
(422, 1779286560, 60, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', '1205.00', NULL),
(423, 1779286320, 360, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', '1205.00', NULL),
(424, 1779285600, 1440, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', '1205.00', NULL),
(425, 1779281280, 10080, 'slow_request', '[\"GET\",\"\\/dashboard\",\"\\\\Inertia\\\\Controller\"]', 'max', '1205.00', NULL),
(434, 1779286560, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(435, 1779286320, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(436, 1779286560, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(437, 1779286320, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(454, 1779286680, 60, 'user_request', '8', 'count', '8.00', NULL),
(455, 1779286680, 360, 'user_request', '8', 'count', '9.00', NULL),
(456, 1779286680, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', '3.00', NULL),
(457, 1779286680, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', '4.00', NULL),
(458, 1779285600, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', '4.00', NULL),
(459, 1779281280, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', '4.00', NULL),
(462, 1779286680, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', '1779286734.00', NULL),
(463, 1779286680, 360, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', '1779286749.00', NULL),
(464, 1779285600, 1440, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', '1779286749.00', NULL),
(465, 1779281280, 10080, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', '1779286749.00', NULL),
(510, 1779286740, 60, 'user_request', '8', 'count', '1.00', NULL),
(511, 1779286740, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'count', '1.00', NULL),
(518, 1779286740, 60, 'exception', '[\"Error\",\"app\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController.php:100\"]', 'max', '1779286749.00', NULL),
(522, 1779291180, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(523, 1779291000, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(524, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'count', '1.00', NULL),
(525, 1779291180, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(526, 1779291000, 360, 'slow_user_request', '8', 'count', '1.00', NULL),
(527, 1779289920, 1440, 'slow_user_request', '8', 'count', '1.00', NULL),
(528, 1779291180, 60, 'user_request', '8', 'count', '1.00', NULL),
(529, 1779291000, 360, 'user_request', '8', 'count', '4.00', NULL),
(530, 1779289920, 1440, 'user_request', '8', 'count', '4.00', NULL),
(534, 1779291180, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1898.00', NULL),
(535, 1779291000, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1898.00', NULL),
(536, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@redirectToGoogle\"]', 'max', '1898.00', NULL),
(538, 1779291240, 60, 'user_request', '8', 'count', '3.00', NULL),
(550, 1779291240, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(551, 1779291000, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(552, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(553, 1779291240, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(554, 1779291000, 360, 'slow_user_request', '6', 'count', '1.00', NULL),
(555, 1779289920, 1440, 'slow_user_request', '6', 'count', '1.00', NULL),
(556, 1779291240, 60, 'user_request', '6', 'count', '2.00', NULL),
(557, 1779291000, 360, 'user_request', '6', 'count', '2.00', NULL),
(558, 1779289920, 1440, 'user_request', '6', 'count', '2.00', NULL),
(562, 1779291240, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1116.00', NULL),
(563, 1779291000, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1116.00', NULL),
(564, 1779289920, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1116.00', NULL),
(570, 1779291480, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'count', '2.00', NULL),
(571, 1779291360, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'count', '2.00', NULL),
(572, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'count', '2.00', NULL),
(573, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'count', '2.00', NULL),
(574, 1779291480, 60, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'max', '2512.00', NULL),
(575, 1779291360, 360, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'max', '2512.00', NULL),
(576, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'max', '2512.00', NULL),
(577, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/super-admin\\/restaurants\\/{restaurant}\",\"App\\\\Http\\\\Controllers\\\\SuperAdmin\\\\RestaurantController@show\"]', 'max', '2512.00', NULL),
(578, 1779291480, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(579, 1779291360, 360, 'slow_user_request', '6', 'count', '2.00', NULL),
(580, 1779291360, 1440, 'slow_user_request', '6', 'count', '5.00', NULL),
(581, 1779291360, 10080, 'slow_user_request', '6', 'count', '6.00', NULL),
(582, 1779291480, 60, 'user_request', '6', 'count', '4.00', NULL),
(583, 1779291360, 360, 'user_request', '6', 'count', '7.00', NULL),
(584, 1779291360, 1440, 'user_request', '6', 'count', '19.00', NULL),
(585, 1779291360, 10080, 'user_request', '6', 'count', '32.00', NULL),
(606, 1779291540, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(607, 1779291360, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(608, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(609, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'count', '1.00', NULL),
(610, 1779291540, 60, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1114.00', NULL),
(611, 1779291360, 360, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1114.00', NULL),
(612, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1114.00', NULL),
(613, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@create\"]', 'max', '1114.00', NULL),
(614, 1779291540, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(615, 1779291360, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(616, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '3.00', NULL),
(617, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '4.00', NULL),
(618, 1779291540, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(619, 1779291540, 60, 'user_request', '6', 'count', '3.00', NULL),
(626, 1779291540, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2678.00', NULL),
(627, 1779291360, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2678.00', NULL),
(628, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2678.00', NULL),
(629, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2678.00', NULL),
(638, 1779291540, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(639, 1779291360, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '2.00', NULL),
(640, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '2.00', NULL),
(641, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '2.00', NULL),
(642, 1779291540, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(643, 1779291360, 360, 'slow_user_request', '8', 'count', '3.00', NULL),
(644, 1779291360, 1440, 'slow_user_request', '8', 'count', '4.00', NULL),
(645, 1779291360, 10080, 'slow_user_request', '8', 'count', '4.00', NULL),
(646, 1779291540, 60, 'user_request', '8', 'count', '3.00', NULL),
(647, 1779291360, 360, 'user_request', '8', 'count', '8.00', NULL),
(648, 1779291360, 1440, 'user_request', '8', 'count', '10.00', NULL),
(649, 1779291360, 10080, 'user_request', '8', 'count', '25.00', NULL),
(650, 1779291540, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(651, 1779291360, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(652, 1779291360, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(653, 1779291360, 10080, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '4.00', NULL),
(654, 1779291540, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(655, 1779291360, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(656, 1779291360, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '2.00', NULL),
(657, 1779291360, 10080, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '4.00', NULL),
(658, 1779291540, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2515.00', NULL),
(659, 1779291360, 360, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2618.00', NULL),
(660, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2618.00', NULL),
(661, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2618.00', NULL),
(670, 1779291600, 60, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', '1.00', NULL),
(671, 1779291360, 360, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', '1.00', NULL),
(672, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', '1.00', NULL),
(673, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'count', '1.00', NULL),
(674, 1779291600, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(675, 1779291600, 60, 'user_request', '8', 'count', '1.00', NULL),
(682, 1779291600, 60, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', '1062.00', NULL),
(683, 1779291360, 360, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', '1062.00', NULL),
(684, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', '1062.00', NULL),
(685, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/logout\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@destroy\"]', 'max', '1062.00', NULL),
(686, 1779291660, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '1.00', NULL),
(687, 1779291360, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '1.00', NULL),
(688, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '1.00', NULL),
(689, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'count', '1.00', NULL),
(690, 1779291660, 60, 'slow_user_request', '9', 'count', '1.00', NULL),
(691, 1779291360, 360, 'slow_user_request', '9', 'count', '1.00', NULL),
(692, 1779291360, 1440, 'slow_user_request', '9', 'count', '1.00', NULL),
(693, 1779291360, 10080, 'slow_user_request', '9', 'count', '1.00', NULL),
(694, 1779291660, 60, 'user_request', '9', 'count', '4.00', NULL),
(695, 1779291360, 360, 'user_request', '9', 'count', '4.00', NULL),
(696, 1779291360, 1440, 'user_request', '9', 'count', '8.00', NULL),
(697, 1779291360, 10080, 'user_request', '9', 'count', '14.00', NULL),
(698, 1779291660, 60, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '2045.00', NULL),
(699, 1779291360, 360, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '2045.00', NULL),
(700, 1779291360, 1440, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '2045.00', NULL),
(701, 1779291360, 10080, 'slow_request', '[\"POST\",\"\\/register\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\RegisteredUserController@store\"]', 'max', '2045.00', NULL),
(714, 1779291660, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'count', '1.00', NULL),
(715, 1779291660, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(716, 1779291660, 60, 'user_request', '8', 'count', '4.00', NULL),
(717, 1779291660, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(718, 1779291660, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(734, 1779291660, 60, 'slow_request', '[\"POST\",\"\\/login\",\"Laravel\\\\Fortify\\\\Http\\\\Controllers\\\\AuthenticatedSessionController@store\"]', 'max', '2618.00', NULL);
INSERT INTO `pulse_aggregates` (`id`, `bucket`, `period`, `type`, `key`, `aggregate`, `value`, `count`) VALUES
(750, 1779291780, 60, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', '1.00', NULL),
(751, 1779291720, 360, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', '1.00', NULL),
(752, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', '1.00', NULL),
(753, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'count', '1.00', NULL),
(754, 1779291780, 60, 'slow_user_request', '8', 'count', '1.00', NULL),
(755, 1779291720, 360, 'slow_user_request', '8', 'count', '1.00', NULL),
(756, 1779291780, 60, 'user_request', '8', 'count', '2.00', NULL),
(757, 1779291720, 360, 'user_request', '8', 'count', '2.00', NULL),
(762, 1779291780, 60, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', '1285.00', NULL),
(763, 1779291720, 360, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', '1285.00', NULL),
(764, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', '1285.00', NULL),
(765, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/settings\\/profile\",\"App\\\\Http\\\\Controllers\\\\Settings\\\\ProfileController@edit\"]', 'max', '1285.00', NULL),
(770, 1779291780, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(771, 1779291720, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(772, 1779291780, 60, 'slow_user_request', '6', 'count', '2.00', NULL),
(773, 1779291720, 360, 'slow_user_request', '6', 'count', '2.00', NULL),
(774, 1779291780, 60, 'user_request', '6', 'count', '3.00', NULL),
(775, 1779291720, 360, 'user_request', '6', 'count', '3.00', NULL),
(782, 1779291780, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2311.00', NULL),
(783, 1779291720, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '2311.00', NULL),
(790, 1779291780, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(791, 1779291720, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(792, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(793, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'count', '1.00', NULL),
(802, 1779291780, 60, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '1200.00', NULL),
(803, 1779291720, 360, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '1200.00', NULL),
(804, 1779291360, 1440, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '1200.00', NULL),
(805, 1779291360, 10080, 'slow_request', '[\"GET\",\"\\/\",\"\\\\Inertia\\\\Controller\"]', 'max', '1200.00', NULL),
(806, 1779292200, 60, 'user_request', '6', 'count', '1.00', NULL),
(807, 1779292080, 360, 'user_request', '6', 'count', '3.00', NULL),
(810, 1779292380, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(811, 1779292080, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(812, 1779292380, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(813, 1779292080, 360, 'slow_user_request', '6', 'count', '1.00', NULL),
(814, 1779292380, 60, 'user_request', '6', 'count', '2.00', NULL),
(822, 1779292380, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1161.00', NULL),
(823, 1779292080, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1161.00', NULL),
(830, 1779292440, 60, 'user_request', '6', 'count', '1.00', NULL),
(831, 1779292440, 360, 'user_request', '6', 'count', '6.00', NULL),
(834, 1779292560, 60, 'user_request', '6', 'count', '5.00', NULL),
(842, 1779292560, 60, 'user_request', '9', 'count', '4.00', NULL),
(843, 1779292440, 360, 'user_request', '9', 'count', '4.00', NULL),
(844, 1779292560, 60, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(845, 1779292440, 360, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(846, 1779291360, 1440, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(847, 1779291360, 10080, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '3.00', NULL),
(848, 1779292560, 60, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(849, 1779292440, 360, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(850, 1779291360, 1440, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(851, 1779291360, 10080, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '5.00', NULL),
(878, 1779292800, 60, 'user_request', '6', 'count', '8.00', NULL),
(879, 1779292800, 360, 'user_request', '6', 'count', '10.00', NULL),
(880, 1779292800, 1440, 'user_request', '6', 'count', '10.00', NULL),
(910, 1779292980, 60, 'user_request', '6', 'count', '2.00', NULL),
(918, 1779292980, 60, 'user_request', '8', 'count', '5.00', NULL),
(919, 1779292800, 360, 'user_request', '8', 'count', '5.00', NULL),
(920, 1779292800, 1440, 'user_request', '8', 'count', '5.00', NULL),
(921, 1779292980, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(922, 1779292800, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(923, 1779292800, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(924, 1779292980, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(925, 1779292800, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(926, 1779292800, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(946, 1779292980, 60, 'user_request', '9', 'count', '1.00', NULL),
(947, 1779292800, 360, 'user_request', '9', 'count', '2.00', NULL),
(948, 1779292800, 1440, 'user_request', '9', 'count', '2.00', NULL),
(949, 1779292980, 60, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(950, 1779292800, 360, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(951, 1779292800, 1440, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(952, 1779293040, 60, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(953, 1779292800, 360, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(954, 1779292800, 1440, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(958, 1779293040, 60, 'user_request', '9', 'count', '1.00', NULL),
(962, 1779294720, 60, 'user_request', '9', 'count', '2.00', NULL),
(963, 1779294600, 360, 'user_request', '9', 'count', '2.00', NULL),
(964, 1779294240, 1440, 'user_request', '9', 'count', '4.00', NULL),
(970, 1779294720, 60, 'user_request', '8', 'count', '3.00', NULL),
(971, 1779294600, 360, 'user_request', '8', 'count', '3.00', NULL),
(972, 1779294240, 1440, 'user_request', '8', 'count', '10.00', NULL),
(973, 1779294720, 60, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(974, 1779294600, 360, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(975, 1779294240, 1440, 'cache_miss', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(976, 1779294720, 60, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(977, 1779294600, 360, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(978, 1779294240, 1440, 'cache_hit', 'dcacbc44a7737aebdda0ba04d9733cb1', 'count', '1.00', NULL),
(990, 1779295200, 60, 'user_request', '8', 'count', '7.00', NULL),
(991, 1779294960, 360, 'user_request', '8', 'count', '7.00', NULL),
(992, 1779295200, 60, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(993, 1779294960, 360, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(994, 1779294240, 1440, 'cache_miss', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(995, 1779295200, 60, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '1.00', NULL),
(996, 1779294960, 360, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '3.00', NULL),
(997, 1779294240, 1440, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '3.00', NULL),
(1026, 1779295200, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(1027, 1779294960, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(1028, 1779294240, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'count', '1.00', NULL),
(1029, 1779295200, 60, 'slow_user_request', '6', 'count', '1.00', NULL),
(1030, 1779294960, 360, 'slow_user_request', '6', 'count', '1.00', NULL),
(1031, 1779294240, 1440, 'slow_user_request', '6', 'count', '1.00', NULL),
(1032, 1779295200, 60, 'user_request', '6', 'count', '3.00', NULL),
(1033, 1779294960, 360, 'user_request', '6', 'count', '3.00', NULL),
(1034, 1779294240, 1440, 'user_request', '6', 'count', '3.00', NULL),
(1038, 1779295200, 60, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1000.00', NULL),
(1039, 1779294960, 360, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1000.00', NULL),
(1040, 1779294240, 1440, 'slow_request', '[\"GET\",\"\\/auth\\/google\\/callback\",\"App\\\\Http\\\\Controllers\\\\Auth\\\\GoogleController@handleGoogleCallback\"]', 'max', '1000.00', NULL),
(1050, 1779295260, 60, 'user_request', '9', 'count', '2.00', NULL),
(1051, 1779294960, 360, 'user_request', '9', 'count', '2.00', NULL),
(1052, 1779295260, 60, 'cache_hit', 'a2c520e03217b53389c41abc5727cff1', 'count', '2.00', NULL);

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
(225, 1779295264, 'user_request', '9', NULL);

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
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qr_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int UNSIGNED NOT NULL DEFAULT '2',
  `status` enum('available','occupied','reserved','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
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
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
('5GT7yRUuWgOpth4s25qeslVisuwApKg57c6HWQhY', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJVRmlJMlhRdlBYNVNGeG5nTEVHR08xOTJIQjVhNEFTRXp6bDdCd1c4IiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3N1cGVyLWFkbWluXC9kYXNoYm9hcmQifSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9zdXBlci1hZG1pblwvZGFzaGJvYXJkIiwicm91dGUiOiJzdXBlcmFkbWluLmRhc2hib2FyZCJ9LCJfZmxhc2giOnsib2xkIjpbXSwibmV3IjpbXX19', 1779285727),
('9ON4u08UQxYDvrg980Oqdei3OPsFLnZngOozQuGj', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJRUGVDNEI3RXNBQXo0Wjd4ZFdqcTNtMW9vdTRTblZmVURkOHlTN0l2IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779281017),
('aTrPA8GzgYxfUfgb8MoWOrqeK3gddZ71ClB37Fi5', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJJb1VsdXZ2Q2M0OGZtUnZBSDFaazJNYkhubVNKQXQ2Rmg3QjNtS3NUIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779282116),
('bO2KoDljJjUSgyLTovRNYv8Iw477ganDcWRRo2qS', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJkSHI2NmVkb0JLVjhjelRVcVpNNjRqdWZ1SzRlU2MzcDJNUk81UWdUIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779281050),
('D9s7rF2A7taTaYLu4GewWJk0939S3W1Ah0znK3fD', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJYRllSTnVZekV6dVdncDVKMzRqU0xsNWhESHN6eHhtMVdzZXNpMFk4IiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9yZWdpc3RlciIsInJvdXRlIjoicmVnaXN0ZXIifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779282117),
('E9RS1uOXrq4X8Y5URboM7swyMhMpwE1TorGbnIvR', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJYMmpUT1hLWmRsS0tnN2pLckE3Y1VwSVhib3VEOVA1OGZwQzJ4a1pqIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779281036),
('hWZKpJyKyIokyJzoXoScUuU1jZRfqHbUHF50Pcee', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJZdzJCWEVVZ1ZGeDlZTk5qTzlRVXUwNUlneXNIWEIwTU9jVUs4MU1VIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvYXZlbnR1cmEudGVzdFwvcmVnaXN0ZXIiLCJyb3V0ZSI6InJlZ2lzdGVyIn19', 1779255324),
('lSgvXKN7yIUFMnpDISMEXOsqlJeWSZJpz9vJfStX', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJKWHVhcFMyQnZuS2FiMmpHSEFoUGRmeFI1bjBTZFVCOUFkeXJaSU5kIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJob21lIn0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779281030),
('PJHuuVXcUdOksBu54ctDZpEw2neqwMCEUVkU5tLF', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJxQTJEQ2FBcm9XTVNWNkQwNjhZd3hJYUdFbE1sV0NUZjBlYUE2WTVwIiwidXJsIjp7ImludGVuZGVkIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3N1cGVyLWFkbWluXC9yZXN0YXVyYW50c1wvMSJ9LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDBcL3N1cGVyLWFkbWluXC9yZXN0YXVyYW50c1wvMSIsInJvdXRlIjoic3VwZXJhZG1pbi5yZXN0YXVyYW50cy5zaG93In0sIl9mbGFzaCI6eyJvbGQiOltdLCJuZXciOltdfX0=', 1779291499),
('RWIKFqKVrZ80coGyV4442Q4zvbd8ShSWn5IYM85L', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiIyckxMTDFrb0QxcU1yYWFPZ1YxSzBPdGJzVlhUSjY1VFFKeEVDSHZpIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJ1cmwiOltdLCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6NiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cL2xvY2FsaG9zdFwvQXZlbnR1cmFcL3B1YmxpY1wvZGFzaGJvYXJkIiwicm91dGUiOiJkYXNoYm9hcmQifX0=', 1779255502),
('UtEEYbYIIJ5ddFbKt6rijrQMC5sPhiazSCFIB4FE', 9, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36 Edg/148.0.0.0', 'eyJfdG9rZW4iOiJUb3VVR3dURm5FWjUxZDlKakhleE52cnlPQXJGVkV6dmJKTjlrTGREIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6OX0=', 1779295264),
('znrHgMJdQDYu0VXR5NPvfwB4eqbDlYMzQ2Vway5X', NULL, '127.0.0.1', 'curl/7.84.0', 'eyJfdG9rZW4iOiJQYjdYMnc4MlZHdzFqRHZSZ1RxRk5NTVY4cFlXU0dMckRESlZoVnIwIiwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC9sb2dpbiIsInJvdXRlIjoibG9naW4ifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119fQ==', 1779281642);

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
(1, 'FREE', 'Miễn phí', '0.00', 'monthly', 1, 10, 5, '{\"realtime\": false, \"max_areas\": 2, \"ai_features\": false, \"api_rate_limit\": 60, \"max_storage_mb\": 500, \"advanced_analytics\": false}', 'active', '2026-05-19 22:20:23', '2026-05-20 06:59:13'),
(2, 'PRO', 'Cao cấp', '299000.00', 'monthly', NULL, NULL, NULL, '{\"realtime\": true, \"max_areas\": null, \"ai_features\": true, \"api_rate_limit\": 600, \"max_storage_mb\": 10240, \"advanced_analytics\": true}', 'active', '2026-05-19 22:20:23', '2026-05-20 06:59:13');

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
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbol` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('mass','volume','count') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'count',
  `base_unit_id` bigint UNSIGNED DEFAULT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1.0000',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `restaurant_id`, `name`, `symbol`, `type`, `base_unit_id`, `conversion_factor`, `created_at`, `updated_at`) VALUES
(1, 1, 'Gram', 'g', 'mass', NULL, '1.0000', '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(2, 1, 'Ly', 'ly', 'count', NULL, '1.0000', '2026-05-19 22:20:34', '2026-05-19 22:20:34');

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
(1, 1, 1, 'Owner Demo', 'owner@bepso.test', NULL, '0900000001', NULL, 'active', NULL, '2026-05-19 22:20:33', '$2y$12$4iqbuuoQrULJNd6t83JcnOAZQR.zsnGdoSyn016qRQK7sC6S/a7wm', NULL, NULL, NULL, '5Xc1hFQBvQ66wXgiGFqRPbq4v95LtROYltZxN1kweGW3MmtkfiINKSbWoBPA', '2026-05-19 22:20:33', '2026-05-19 22:20:34'),
(2, 1, 1, 'Manager Demo', 'manager@bepso.test', NULL, '0900000002', NULL, 'active', NULL, '2026-05-19 22:20:33', '$2y$12$WSaksyDLJ5YA1ruRug./6ucfa6SGzePvf/cULRN6g5bva2jXz/l3a', NULL, NULL, NULL, NULL, '2026-05-19 22:20:33', '2026-05-19 22:20:34'),
(3, 1, 1, 'Cashier Demo', 'cashier@bepso.test', NULL, '0900000003', NULL, 'active', NULL, '2026-05-19 22:20:34', '$2y$12$5xBbxdbgjIwBrlU0OiLkJuo6O7B1PnPYOAD.hcAz/6imt3AAXRjbS', NULL, NULL, NULL, NULL, '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(4, 1, 1, 'Kitchen Demo', 'kitchen@bepso.test', NULL, '0900000004', NULL, 'active', NULL, '2026-05-19 22:20:34', '$2y$12$l1u6mv0DZzJb5Z27diufhOJSmjLnc.dGi3fMvkm8442thu9TPvHJ6', NULL, NULL, NULL, NULL, '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(5, 1, 1, 'Inventory Demo', 'inventory@bepso.test', NULL, '0900000005', NULL, 'active', NULL, '2026-05-19 22:20:34', '$2y$12$N98RNqCwFBi0LnVQPW1rhemWMu0tdLbE5yXgqOvKezmJAy8kHAbzG', NULL, NULL, NULL, NULL, '2026-05-19 22:20:34', '2026-05-19 22:20:34'),
(6, NULL, NULL, 'Dik', 'duongndph53424@gmail.com', '117321937622173959644', NULL, NULL, 'active', '2026-05-20 09:40:39', '2026-05-20 06:24:31', '$2y$12$mEx0YwS81aPAUv7DSOhPWu8PwpRr12g4o5y2d7JG4u4r.FTleAb06', NULL, NULL, NULL, 'dE2YzpTClC3QPNuhkQiyjHZGqfMtv5w8TjVdDKtQ52nmW5leytkHGqyPrrYk', '2026-05-19 22:38:16', '2026-05-20 09:40:39'),
(7, NULL, NULL, 'Dik', 'dikndph53424@gmail.com', NULL, NULL, NULL, 'active', NULL, NULL, '$2y$12$kli2WYOa7sfzjK0BSmWddeW6ENUJUo4kfkvMAwfzycFMRU6O/2YFq', NULL, NULL, NULL, NULL, '2026-05-20 05:52:33', '2026-05-20 05:52:33'),
(8, NULL, NULL, 'Super Admin', 'superadmin@aventura.local', NULL, NULL, NULL, 'active', '2026-05-20 09:32:38', NULL, '$2y$12$2cFtCNibjJSSQld0cFyMm.ONHy5.ZxQrQO4L46ydupUDtGnISGjB.', NULL, NULL, NULL, 'JwpPBTVwekKwtBtTEjPpPN6c32MYuSA71QurXY1Mv7bKxwcFC7mwwrI1nPe4', '2026-05-20 07:04:25', '2026-05-20 09:32:38'),
(9, NULL, NULL, 'Duong', 'dik2610@gmail.com', NULL, NULL, NULL, 'active', '2026-05-20 09:41:04', NULL, '$2y$12$u09pq5Dr4J8z/8ecS6XGO.6xBktHMi4lnzw6JJR/PtSO1iej/Gh.a', NULL, NULL, NULL, NULL, '2026-05-20 08:41:11', '2026-05-20 09:41:04');

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
-- Dumping data for table `work_shifts`
--

INSERT INTO `work_shifts` (`id`, `restaurant_id`, `branch_id`, `name`, `code`, `start_time`, `end_time`, `is_overnight`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Ca sang', 'CA-SANG', '08:00:00', '16:00:00', 0, 'active', '2026-05-19 22:20:35', '2026-05-19 22:20:35');

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1062;

--
-- AUTO_INCREMENT for table `pulse_entries`
--
ALTER TABLE `pulse_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=226;

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
