-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2025 at 03:33 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `railway_pro`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_claims_view`
-- (See below for the actual view)
--
CREATE TABLE `active_claims_view` (
`id` int(11)
,`claim_reference` varchar(20)
,`claimant_name` varchar(255)
,`phone` varchar(20)
,`email` varchar(255)
,`claim_description` text
,`status` enum('pending','approved','rejected','under_review')
,`submission_date` timestamp
,`item_name` varchar(255)
,`item_description` text
,`location_found` varchar(255)
,`date_found` date
,`username` varchar(50)
,`first_name` varchar(50)
,`last_name` varchar(50)
);

-- --------------------------------------------------------

--
-- Table structure for table `claims`
--

CREATE TABLE `claims` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `action` enum('pending','approved','rejected','under_review') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_date` timestamp NULL DEFAULT NULL,
  `claim_reference` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `claims`
--

INSERT INTO `claims` (`id`, `item_id`, `name`, `phone`, `email`, `description`, `proof_image`, `user_id`, `submission_date`, `action`, `admin_notes`, `processed_by`, `processed_date`, `claim_reference`) VALUES
(1, 2, 'akmal zuhairi', '0172339190', 'zhaeri01@gmail.com', 'ada number phone saya dalam wallet tu', NULL, 18, '2025-06-13 22:42:17', 'under_review', 'ok i will contact u asap', 1, '2025-06-13 22:43:21', 'CLM202506140001');

--
-- Triggers `claims`
--
DELIMITER $$
CREATE TRIGGER `generate_claim_reference` BEFORE INSERT ON `claims` FOR EACH ROW BEGIN
    DECLARE ref_num VARCHAR(20);
    SET ref_num = CONCAT('CLM', YEAR(CURDATE()), LPAD(MONTH(CURDATE()), 2, '0'), LPAD(DAY(CURDATE()), 2, '0'), LPAD(NEW.id + 1, 4, '0'));
    SET NEW.claim_reference = ref_num;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_claim_status_change` AFTER UPDATE ON `claims` FOR EACH ROW BEGIN
    IF NEW.action != OLD.action THEN
        INSERT INTO system_logs (user_id, action, description, ip_address) 
        VALUES (NEW.processed_by, 'claim_status_changed', 
                CONCAT('Claim #', NEW.claim_reference, ' status changed from ', OLD.action, ' to ', NEW.action),
                NULL);
        
        -- Create notification for user
        INSERT INTO notifications (user_id, type, title, message, related_id, related_type)
        VALUES (NEW.user_id, 'claim_status', 
                CONCAT('Claim Status Update - ', NEW.claim_reference),
                CONCAT('Your claim for item has been ', NEW.action, '. ', COALESCE(NEW.admin_notes, '')),
                NEW.id, 'claim');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `dashboard_stats`
-- (See below for the actual view)
--
CREATE TABLE `dashboard_stats` (
`total_users` bigint(21)
,`unclaimed_items` bigint(21)
,`pending_claims` bigint(21)
,`pending_feedback` bigint(21)
,`active_lost_items` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `train_number` varchar(20) DEFAULT NULL,
  `route` varchar(255) DEFAULT NULL,
  `travel_date` date DEFAULT NULL,
  `feedback_type` enum('complaint','suggestion','compliment','general') NOT NULL DEFAULT 'general',
  `category` enum('cleanliness','punctuality','staff_behavior','facilities','safety','ticketing','other') NOT NULL DEFAULT 'other',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `rating` tinyint(1) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `status` enum('pending','in_progress','resolved','closed') DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `admin_response` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `response_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `user_id`, `train_number`, `route`, `travel_date`, `feedback_type`, `category`, `subject`, `message`, `rating`, `status`, `priority`, `admin_response`, `responded_by`, `response_date`, `created_at`, `updated_at`) VALUES
(2, 18, 'k005', 'seremban to kl', '2025-06-10', 'complaint', 'staff_behavior', 'rude staff', 'staff sangat tidak ramah', 1, 'resolved', 'high', '', 1, '2025-06-13 22:43:59', '2025-06-13 22:39:41', '2025-06-13 22:43:59');

-- --------------------------------------------------------

--
-- Table structure for table `found_items`
--

CREATE TABLE `found_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `image` longblob DEFAULT NULL,
  `status` enum('claimed','unclaimed') DEFAULT 'unclaimed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `location_found` varchar(255) DEFAULT 'Unknown',
  `date_found` date DEFAULT curdate(),
  `found_by` varchar(100) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `category` enum('electronics','clothing','documents','jewelry','bags','other') DEFAULT 'other',
  `color` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `found_items`
--

INSERT INTO `found_items` (`id`, `item_name`, `description`, `image`, `status`, `created_at`, `updated_at`, `location_found`, `date_found`, `found_by`, `staff_id`, `category`, `color`, `brand`, `additional_notes`) VALUES
(1, 'iPhone 13', 'Black iPhone 13 with blue case', NULL, 'unclaimed', '2025-01-15 08:30:00', '2025-01-15 08:30:00', 'Platform 3, KL Sentral', '2025-01-15', 'Security Officer Ahmad', 1, 'electronics', 'Black', 'Apple', 'Found near bench'),
(2, 'Leather Wallet', 'Brown leather wallet with multiple card slots', NULL, 'unclaimed', '2025-01-16 14:20:00', '2025-06-08 18:49:21', 'Train Coach B, Seat 15', '2025-01-16', 'Cleaner Staff Maria', 1, 'other', 'Brown', NULL, 'Contains some cards'),
(3, 'Backpack', 'Blue Adidas backpack, medium size', NULL, 'unclaimed', '2025-01-17 09:45:00', '2025-06-13 22:34:28', 'Waiting Area, Ipoh Station', '2025-01-17', 'Station Officer Kumar', 1, 'bags', 'Blue', 'Adidas', 'Has laptop compartment'),
(4, 'keyboard', 'brand blackshark', NULL, 'unclaimed', '2025-06-08 18:49:47', '2025-06-08 18:49:47', 'icirty\'', '2025-06-08', '', NULL, 'electronics', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `lost_items`
--

CREATE TABLE `lost_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` enum('electronics','clothing','documents','jewelry','bags','other') DEFAULT 'other',
  `color` varchar(50) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `lost_location` varchar(255) DEFAULT NULL,
  `lost_date` date DEFAULT NULL,
  `train_number` varchar(20) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `reward_offered` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','found','closed') DEFAULT 'active',
  `reference_number` varchar(20) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lost_items`
--

INSERT INTO `lost_items` (`id`, `user_id`, `item_name`, `description`, `category`, `color`, `brand`, `lost_location`, `lost_date`, `train_number`, `contact_phone`, `contact_email`, `reward_offered`, `status`, `reference_number`, `additional_notes`, `created_at`, `updated_at`) VALUES
(2, 4, 'inhealer', 'around 3pm', 'other', '', '', 'sungai besi', '2025-06-03', '', '0193982382', 'wansulhaisyraf@gmail.com', 0.00, 'found', 'LST202506090001', '', '2025-06-08 19:37:41', '2025-06-08 19:53:27'),
(3, 18, 'iphone 17', 'casing kucing', 'electronics', '', '', 'salak selatan', '2025-06-12', '', '01998383873', 'zhaeri03@gmail.com', 0.00, 'found', 'LST202506140001', '', '2025-06-13 22:37:46', '2025-06-13 22:44:12'),
(4, 18, 'headphone ugreen', 'ada sticker batman', 'electronics', 'white', 'ugreen', 'sungai besi', '2025-06-04', '', '01938383744', 'zhaeri01@gmail.com', 0.00, 'active', 'LST202506140001', '', '2025-06-14 00:06:02', '2025-06-14 00:06:02');

--
-- Triggers `lost_items`
--
DELIMITER $$
CREATE TRIGGER `generate_lost_item_reference` BEFORE INSERT ON `lost_items` FOR EACH ROW BEGIN
    DECLARE ref_num VARCHAR(20);
    SET ref_num = CONCAT('LST', YEAR(CURDATE()), LPAD(MONTH(CURDATE()), 2, '0'), LPAD(DAY(CURDATE()), 2, '0'), LPAD(NEW.id + 1, 4, '0'));
    SET NEW.reference_number = ref_num;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('claim_status','feedback_response','system','general') NOT NULL DEFAULT 'general',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `related_id`, `related_type`, `created_at`) VALUES
(1, 18, 'claim_status', 'Claim Status Update - CLM202506140001', 'Your claim for item has been under_review. ok i will contact u asap', 0, 1, 'claim', '2025-06-13 22:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expiry` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expiry`, `is_used`, `created_at`, `ip_address`) VALUES
(2, 18, 'bbaec5947cf6c85f8b110b9165d17e0cdd031ae55ef85a02aec9699650a48cdc', '2025-06-14 08:11:29', 1, '2025-06-13 23:11:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `station` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_id`, `name`, `position`, `department`, `station`, `contact_number`, `email`, `is_active`, `created_at`) VALUES
(1, 'ST001', 'Ahmad bin Ali', 'Security Officer', 'Security', 'KL Sentral', '012-3456789', 'ahmad@railway.com', 1, '2025-01-01 00:00:00'),
(2, 'ST002', 'Maria Santos', 'Cleaning Staff', 'Maintenance', 'KL Sentral', '012-9876543', 'maria@railway.com', 1, '2025-01-01 00:00:00'),
(3, 'ST003', 'Kumar Selvam', 'Station Officer', 'Operations', 'Ipoh Station', '019-1234567', 'kumar@railway.com', 1, '2025-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:08:12'),
(2, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:08:22'),
(3, 18, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:09:21'),
(4, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:09:47'),
(5, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:09:53'),
(6, 18, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:18:31'),
(7, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:18:42'),
(8, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:19:22'),
(9, NULL, 'failed to login', 'User \'admin\' failed to login - invalid password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:19:34'),
(10, NULL, 'failed to login', 'User \'admin\' failed to login - invalid password', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:19:43'),
(11, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:19:57'),
(12, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:20:11'),
(13, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:22:05'),
(14, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:22:13'),
(15, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:22:25'),
(16, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:32:31'),
(17, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:32:47'),
(18, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:32:58'),
(19, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:35:58'),
(20, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:36:03'),
(21, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:36:15'),
(22, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:42:38'),
(23, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:42:50'),
(24, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:43:01'),
(25, 1, 'claim_status_changed', 'Claim #CLM202506140001 status changed from pending to under_review', NULL, NULL, '2025-06-13 22:43:21'),
(26, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 22:59:35'),
(27, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:04:35'),
(28, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:04:58'),
(29, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:09:54'),
(30, NULL, 'requested password reset', 'User \'akmal03\' requested password reset - reset link sent to email', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:11:32'),
(31, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:12:05'),
(32, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:17:13'),
(33, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:17:47'),
(34, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:20:56'),
(35, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:21:13'),
(36, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:21:24'),
(37, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:22:19'),
(38, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:22:32'),
(39, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:24:10'),
(40, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:24:16'),
(41, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:24:29'),
(42, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:26:58'),
(43, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:42:31'),
(44, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:42:49'),
(45, 1, 'logged out', 'User \'admin\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:51:11'),
(46, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:51:24'),
(47, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-13 23:51:36'),
(48, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 00:49:38'),
(49, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:01:44'),
(50, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:11:59'),
(51, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:12:39'),
(52, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout - session duration: 00:00:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:12:46'),
(53, 18, 'logged out', 'User \'akmal03\' logged out - session duration: 00:00:07', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:12:46'),
(54, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:15:32'),
(55, NULL, 'logged in successfully', 'User \'akmal03\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:20:04'),
(56, 18, 'completed 2FA verification', 'User \'akmal03\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:20:52'),
(57, 18, 'logged out', 'User \'akmal03\' logged out - user initiated logout - session duration: 00:02:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:23:42'),
(58, 18, 'logged out', 'User \'akmal03\' logged out - session duration: 00:02:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:23:43'),
(59, NULL, 'logged in successfully', 'User \'admin\' logged in successfully - 2FA verification sent', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:24:00'),
(60, 1, 'completed 2FA verification', 'User \'admin\' completed 2FA verification - login successful', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:24:07'),
(61, 1, 'logged out', 'User \'admin\' logged out - user initiated logout - session duration: 00:02:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:26:22'),
(62, 1, 'logged out', 'User \'admin\' logged out - session duration: 00:02:15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', '2025-06-14 01:26:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `id_number` varchar(12) DEFAULT NULL,
  `id_number_hash` varchar(255) DEFAULT NULL,
  `enrollment_id` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `contact_number` varchar(15) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `email`, `id_number`, `id_number_hash`, `enrollment_id`, `password`, `role`, `created_at`, `updated_at`, `contact_number`, `address`, `is_active`, `last_login`) VALUES
(1, 'admin', 'System', 'Administrator', 'akmalzuhairi01@gmail.com', '900101010101', '$2y$10$VVSU/kQ0DKhxB//0goGRVuZ6VPKEl4VE9sLnlhOZ.18pQd04DOIEG', NULL, '$2y$10$T5kbqKSFaBr2Ys246vKY3OuZp6zBzfqDqGDYiVnq8YrFljwKWF5p.', 'admin', '2025-01-02 21:02:49', '2025-06-14 01:26:22', '01120212090', 'Railway Headquarters', 1, '2025-06-14 01:26:22'),
(4, 'sulha03', 'wan', 'sulha', 'wansulhaisyraf@gmail.com', NULL, NULL, NULL, '$2y$10$Gxg9FR1QGXulHmq8k4LrVeCYc6DgHd54xWlD77Ug0yHFvClgzN0Ra', 'user', '2025-06-08 19:35:52', '2025-06-08 19:35:52', NULL, NULL, 1, NULL),
(12, 'testfix', 'Test', 'Fix', 'testfix@test.com', '030101123456', '$2y$10$sFJOLaB7EZCNkvJHmcc8f.dLJeNkZYWO5K11Q3bhu9M5PMgw7KlZG', NULL, '$2y$10$w6X0HO6qAptpjbio8LB4b.0Dew1kqGj//Tdgc1uOEdYa0i0ly/nL2', 'user', '2025-06-13 18:51:47', '2025-06-13 20:34:59', NULL, NULL, 1, NULL),
(13, 'amar03', 'amar', 'syahmi', 'amar20003@gmail.com', '030304160156', '$2y$10$K5J2tqH6FVEeS8nTouneDuWnRJVuMUZy1qvWFTY91/kZ4Izv839ay', NULL, '$2y$10$ZFUozHk/eJeRiBgzMMJ3SusMpL0PbXUEfw6Es/RdAwwL46cX7eID.', 'user', '2025-06-13 19:15:51', '2025-06-13 20:34:59', NULL, NULL, 1, NULL),
(14, 'amar003', 'amar', 'syahmi', 'amar2003@gmail.com', '030207160154', '$2y$10$PXM3YYXMhiVg2EaW..Lj2eU/UPRjueVVnQjzMPNBFu0R8LiKSvAuS', NULL, '$2y$10$spE.tUmX72ViDPaP8ICs2eeC3kNnNtEyACH4NyhvidyTFD/nBVvTi', 'user', '2025-06-13 19:25:22', '2025-06-13 20:34:59', NULL, NULL, 1, NULL),
(15, 'testuser', 'Test', 'User', 'test@example.com', '020101123456', '$2y$10$DlWV4f4LeHQiNEdk5IMjt.aN4LNkgv1TdxKWpRJn9y4hxOPKIbZXa', 'A2025001', '$2y$10$GJjj9tns6/XLiIQb7fcPr.fL0Ygg26RKl6DqV8qe5Hgqw7qD6yCa.', 'user', '2025-06-13 20:11:31', '2025-06-13 20:34:59', NULL, NULL, 1, NULL),
(16, 'user2020', 'Old', 'User', 'user2020@test.com', NULL, NULL, 'A2020001', '$2y$10$T5kbqKSFaBr2Ys246vKY3OuZp6zBzfqDqGDYiVnq8YrFljwKWF5p.', 'user', '2019-12-31 16:00:00', '2025-06-13 20:14:41', NULL, NULL, 1, NULL),
(17, 'user2019', 'Very', 'Old', 'user2019@test.com', NULL, NULL, 'A2019001', '$2y$10$T5kbqKSFaBr2Ys246vKY3OuZp6zBzfqDqGDYiVnq8YrFljwKWF5p.', 'user', '2018-12-31 16:00:00', '2025-06-13 20:14:41', NULL, NULL, 1, NULL),
(18, 'akmal03', 'akmal', 'zuhairi', 'zhaeri03@gmail.com', '030207160157', '$2y$10$fBMm8DifgtJ4nP6/eXLwKunvs.wWOcFKPcnMRVfS2XBGlM8ZpH0OS', 'A2025002', '$2y$10$UYKzNzSCdW/Jnxs8C5egc.57X92/wpuKjSWgb.LPenWC/y.D7/58K', 'user', '2025-06-13 20:27:33', '2025-06-14 01:23:43', '0172339190', 'no s45 lorong 1 kampung baru sri puchong', 1, '2025-06-14 01:23:43'),
(19, 'akkk002', 'alert(&#039;xss&#039;)', '43434', 'test11@test.com', NULL, '$2y$10$cY65UczRqKFNd0Zs8RSLF.1BMPqpjkUqUrsnRtUiry7lygFlMzZgu', 'A2025003', '$2y$10$F4muaI4Q.lzwUUh9EoRA6e99hID.UE6S5OEHQVlXElcW1Aj.mlCWG', 'user', '2025-06-13 20:42:55', '2025-06-13 22:55:38', NULL, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `verification_attempts`
--

CREATE TABLE `verification_attempts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `expiry` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_attempts`
--

INSERT INTO `verification_attempts` (`id`, `user_id`, `verification_code`, `expiry`, `is_used`, `created_at`, `ip_address`, `user_agent`) VALUES
(1, 1, '922771', '2025-06-08 20:27:14', 0, '2025-06-08 18:17:17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(2, 1, '414821', '2025-06-08 20:29:46', 0, '2025-06-08 18:19:49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(3, 1, '706947', '2025-06-08 20:33:06', 0, '2025-06-08 18:23:09', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(4, 1, '111627', '2025-06-08 20:33:09', 1, '2025-06-08 18:23:12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(6, 1, '595842', '2025-06-08 21:42:27', 1, '2025-06-08 19:32:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(7, 4, '041139', '2025-06-08 21:46:16', 1, '2025-06-08 19:36:20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(8, 1, '139487', '2025-06-08 21:48:13', 1, '2025-06-08 19:38:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(9, 4, '131178', '2025-06-08 22:18:12', 1, '2025-06-08 20:08:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(10, 1, '300682', '2025-06-08 23:05:49', 1, '2025-06-08 20:55:52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(13, 1, '299419', '2025-06-13 21:41:15', 1, '2025-06-13 19:31:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(14, 1, '387697', '2025-06-13 21:44:34', 1, '2025-06-13 19:34:37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(15, 15, '638711', '2025-06-13 22:36:28', 0, '2025-06-13 20:26:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(16, 18, '746416', '2025-06-13 22:38:07', 1, '2025-06-13 20:28:10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(17, 18, '795341', '2025-06-13 23:15:52', 1, '2025-06-13 21:05:55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(18, 18, '246112', '2025-06-13 23:24:10', 1, '2025-06-13 21:14:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(19, 1, '919624', '2025-06-13 23:45:43', 1, '2025-06-13 21:35:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(20, 18, '849594', '2025-06-14 00:18:19', 1, '2025-06-13 22:08:22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(21, 1, '144506', '2025-06-14 00:19:18', 1, '2025-06-13 22:09:21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(22, 18, '510130', '2025-06-14 00:19:50', 1, '2025-06-13 22:09:53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(23, 18, '208392', '2025-06-14 00:28:27', 1, '2025-06-13 22:18:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(24, 1, '411498', '2025-06-14 00:29:54', 1, '2025-06-13 22:19:57', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(25, 18, '938324', '2025-06-14 00:32:10', 1, '2025-06-13 22:22:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(26, 1, '485143', '2025-06-14 00:42:44', 1, '2025-06-13 22:32:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(27, 18, '682395', '2025-06-14 00:46:00', 1, '2025-06-13 22:36:03', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(28, 1, '982727', '2025-06-14 00:52:47', 1, '2025-06-13 22:42:50', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(29, 1, '759995', '2025-06-14 01:14:32', 1, '2025-06-13 23:04:35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(30, 18, '512946', '2025-06-14 01:22:02', 0, '2025-06-13 23:12:05', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(31, 18, '102389', '2025-06-14 01:27:10', 1, '2025-06-13 23:17:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(32, 1, '894765', '2025-06-14 01:31:10', 1, '2025-06-13 23:21:13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(33, 1, '377943', '2025-06-14 01:32:16', 1, '2025-06-13 23:22:19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(34, 18, '351850', '2025-06-14 01:34:13', 1, '2025-06-13 23:24:16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(35, 1, '790007', '2025-06-14 01:52:28', 1, '2025-06-13 23:42:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(36, 18, '842631', '2025-06-14 02:01:20', 1, '2025-06-13 23:51:24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(37, 18, '596888', '2025-06-14 03:11:41', 0, '2025-06-14 01:01:44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(38, 18, '889224', '2025-06-14 03:21:56', 1, '2025-06-14 01:11:59', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(39, 18, '823896', '2025-06-14 03:25:28', 0, '2025-06-14 01:15:31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(40, 18, '108014', '2025-06-14 03:30:00', 1, '2025-06-14 01:20:04', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(41, 1, '797264', '2025-06-14 03:33:57', 1, '2025-06-14 01:24:00', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0');

-- --------------------------------------------------------

--
-- Table structure for table `verification_failures`
--

CREATE TABLE `verification_failures` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_code` varchar(6) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lockout_until` datetime DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_failures`
--

INSERT INTO `verification_failures` (`id`, `user_id`, `ip_address`, `attempted_code`, `created_at`, `lockout_until`, `user_agent`) VALUES
(1, 1, '::1', '982727', '2025-06-13 23:04:47', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(2, 18, '::1', '512946', '2025-06-13 23:17:30', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(3, 18, '::1', '596888', '2025-06-14 01:12:12', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0'),
(4, 18, '::1', '823896', '2025-06-14 01:20:20', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0');

-- --------------------------------------------------------

--
-- Structure for view `active_claims_view`
--
DROP TABLE IF EXISTS `active_claims_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_claims_view`  AS SELECT `c`.`id` AS `id`, `c`.`claim_reference` AS `claim_reference`, `c`.`name` AS `claimant_name`, `c`.`phone` AS `phone`, `c`.`email` AS `email`, `c`.`description` AS `claim_description`, `c`.`action` AS `status`, `c`.`submission_date` AS `submission_date`, `fi`.`item_name` AS `item_name`, `fi`.`description` AS `item_description`, `fi`.`location_found` AS `location_found`, `fi`.`date_found` AS `date_found`, `u`.`username` AS `username`, `u`.`first_name` AS `first_name`, `u`.`last_name` AS `last_name` FROM ((`claims` `c` join `found_items` `fi` on(`c`.`item_id` = `fi`.`id`)) join `users` `u` on(`c`.`user_id` = `u`.`id`)) WHERE `c`.`action` in ('pending','under_review') ;

-- --------------------------------------------------------

--
-- Structure for view `dashboard_stats`
--
DROP TABLE IF EXISTS `dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `dashboard_stats`  AS SELECT (select count(0) from `users` where `users`.`role` = 'user') AS `total_users`, (select count(0) from `found_items` where `found_items`.`status` = 'unclaimed') AS `unclaimed_items`, (select count(0) from `claims` where `claims`.`action` = 'pending') AS `pending_claims`, (select count(0) from `feedback` where `feedback`.`status` = 'pending') AS `pending_feedback`, (select count(0) from `lost_items` where `lost_items`.`status` = 'active') AS `active_lost_items` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `fk_claims_users` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_submission_date` (`submission_date`),
  ADD KEY `fk_claims_processed_by` (`processed_by`),
  ADD KEY `idx_claim_reference` (`claim_reference`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feedback_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_travel_date` (`travel_date`),
  ADD KEY `fk_feedback_responded_by` (`responded_by`);

--
-- Indexes for table `found_items`
--
ALTER TABLE `found_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_found` (`date_found`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_location` (`location_found`),
  ADD KEY `fk_found_items_staff` (`staff_id`);

--
-- Indexes for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lost_items_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_lost_date` (`lost_date`),
  ADD KEY `idx_reference_number` (`reference_number`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_user` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_type` (`type`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expiry` (`expiry`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD KEY `idx_station` (`station`),
  ADD KEY `idx_department` (`department`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logs_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `enrollment_id` (`enrollment_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `verification_attempts`
--
ALTER TABLE `verification_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_verification` (`user_id`,`verification_code`),
  ADD KEY `idx_expiry` (`expiry`);

--
-- Indexes for table `verification_failures`
--
ALTER TABLE `verification_failures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `claims`
--
ALTER TABLE `claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `found_items`
--
ALTER TABLE `found_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lost_items`
--
ALTER TABLE `lost_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `verification_attempts`
--
ALTER TABLE `verification_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `verification_failures`
--
ALTER TABLE `verification_failures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `found_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claims_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_claims_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_responded_by` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `found_items`
--
ALTER TABLE `found_items`
  ADD CONSTRAINT `fk_found_items_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lost_items`
--
ALTER TABLE `lost_items`
  ADD CONSTRAINT `fk_lost_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `verification_attempts`
--
ALTER TABLE `verification_attempts`
  ADD CONSTRAINT `verification_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_failures`
--
ALTER TABLE `verification_failures`
  ADD CONSTRAINT `verification_failures_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `cleanup_expired_data` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-08 18:01:41' ON COMPLETION PRESERVE ENABLE DO BEGIN
    -- Delete expired verification attempts
    DELETE FROM verification_attempts 
    WHERE expiry < NOW() OR is_used = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Delete expired password reset tokens
    DELETE FROM password_resets 
    WHERE expiry < NOW() OR is_used = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    -- Delete old verification failures (keep for 30 days)
    DELETE FROM verification_failures 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Delete old system logs (keep for 90 days)
    DELETE FROM system_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
