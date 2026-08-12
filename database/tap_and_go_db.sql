-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2026 at 12:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tap_and_go_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_logs`
--

CREATE TABLE `access_logs` (
  `log_id` int(11) NOT NULL,
  `card_uid` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `access_type` enum('entry','exit') DEFAULT 'entry',
  `access_status` enum('granted','denied') DEFAULT 'granted',
  `alert_triggered` tinyint(1) DEFAULT 0,
  `power_source` enum('main','battery') DEFAULT 'main',
  `reason` varchar(255) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_logs`
--

INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES
(1, '0A1FD006', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-07 18:08:39', '2026-08-07 10:08:39', '2026-08-07 10:08:39'),
(2, '85241249', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-07 18:14:23', '2026-08-07 10:14:23', '2026-08-07 10:14:23'),
(3, '85241249', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-07 18:14:39', '2026-08-07 10:14:39', '2026-08-07 10:14:39'),
(4, '85241249', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-07 18:21:50', '2026-08-07 10:21:50', '2026-08-07 10:21:50'),
(5, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-07 18:22:15', '2026-08-07 10:22:15', '2026-08-07 10:22:15'),
(6, '65661B49', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-07 18:23:04', '2026-08-07 10:23:04', '2026-08-07 10:23:04'),
(7, '0A1FD006', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-08 15:47:25', '2026-08-08 07:47:25', '2026-08-08 07:47:25'),
(8, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-08 15:47:36', '2026-08-08 07:47:36', '2026-08-08 07:47:36'),
(9, '0A1FD006', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-08 15:48:38', '2026-08-08 07:48:38', '2026-08-08 07:48:38'),
(10, '85241249', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-08 15:48:47', '2026-08-08 07:48:47', '2026-08-08 07:48:47'),
(11, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-08 15:49:42', '2026-08-08 07:49:42', '2026-08-08 07:49:42'),
(12, '0A1FD006', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-08 16:10:41', '2026-08-08 08:10:41', '2026-08-08 08:10:41'),
(13, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-08 16:35:13', '2026-08-08 08:35:13', '2026-08-08 08:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_hash` varchar(64) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('administrator','staff','manager') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `math_attempts` int(11) DEFAULT 0,
  `math_blocked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `otp_enabled` tinyint(1) DEFAULT 1,
  `login_attempts` int(11) DEFAULT 0,
  `login_blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `otp_expiry`, `full_name`, `email`, `email_hash`, `email_verified`, `avatar`, `role`, `is_active`, `math_attempts`, `math_blocked_until`, `last_login`, `created_at`, `updated_at`, `otp_enabled`, `login_attempts`, `login_blocked_until`) VALUES
(1, 'admin_chelsea', '$2a$12$3PYMMlfyil2JMr43rbJlf.p8QLIvKEH8SfyvsIuU7ruJ4ByhzBCdq', NULL, 'Chellsea Albano', 'albanochellsea30@gmail.com', '40d9222232106ee0a94ab60ed286fd82a57c664c5cd6b9633057afbe3f1862fe', 1, 'uploads/avatars/avatar_1_1786189553.png', 'administrator', 1, 0, NULL, '2026-08-10 11:41:27', '2026-08-07 09:12:16', '2026-08-10 03:41:27', 1, 0, NULL),
(2, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'System Administrator', 'admin@tapandgo.com', '2f22d7fa3ba2ba8d9aafe7993f39b0fc72dc12337f58b2874e7f9c4ab00961b3', 1, NULL, 'administrator', 1, 0, NULL, NULL, '2026-08-07 09:12:16', '2026-08-07 09:12:16', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admission_records`
--

CREATE TABLE `admission_records` (
  `admission_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `semester_sy` varchar(50) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `home_address` text DEFAULT NULL,
  `school_last` varchar(255) DEFAULT NULL,
  `school_address` text DEFAULT NULL,
  `strand_track` varchar(100) DEFAULT NULL,
  `course_taken` varchar(100) DEFAULT NULL,
  `year_level_old` varchar(20) DEFAULT NULL,
  `former_bh` varchar(255) DEFAULT NULL,
  `former_address` text DEFAULT NULL,
  `guardian_name` varchar(255) NOT NULL,
  `guardian_contact` varchar(20) NOT NULL,
  `room_assignment` varchar(50) NOT NULL,
  `student_signature` varchar(255) NOT NULL,
  `status` enum('pending','active','inactive') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_records`
--

INSERT INTO `admission_records` (`admission_id`, `user_id`, `semester_sy`, `age`, `birth_date`, `home_address`, `school_last`, `school_address`, `strand_track`, `course_taken`, `year_level_old`, `former_bh`, `former_address`, `guardian_name`, `guardian_contact`, `room_assignment`, `student_signature`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '1st semester 2025-2026', 18, '2007-11-14', 'Aurora Alicia Isabela', '0', 'Paddad alicia isabela', '', 'BAELS', '', '', 'Aurora Alicia Isabela', 'Jefferson Albano', '09539976519', '1', 'Kristel Jade P. Albano', 'pending', '2026-08-07 09:43:03', '2026-08-07 09:43:03');

-- --------------------------------------------------------

--
-- Table structure for table `alert_logs`
--

CREATE TABLE `alert_logs` (
  `alert_id` int(11) NOT NULL,
  `card_uid` varchar(50) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `alert_type` enum('unauthorized','system','warning') DEFAULT 'unauthorized',
  `reason` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `delivery_status` enum('pending','resolved') DEFAULT 'pending',
  `resolved_at` datetime DEFAULT NULL,
  `access_type` enum('entry','exit') DEFAULT 'entry',
  `card_type` varchar(20) DEFAULT 'unknown',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alert_logs`
--

INSERT INTO `alert_logs` (`alert_id`, `card_uid`, `user_name`, `alert_type`, `reason`, `timestamp`, `delivery_status`, `resolved_at`, `access_type`, `card_type`, `created_at`, `updated_at`) VALUES
(3, '65661B49', 'Unknown', 'unauthorized', 'Unauthorized access attempt with card: 65661B49', '2026-08-07 18:23:04', 'resolved', '2026-08-08 15:50:45', 'entry', 'unknown', '2026-08-07 10:23:04', '2026-08-08 07:50:45'),
(4, '65661B49', 'Unknown Card', 'unauthorized', 'Unknown card detected: 65661B49', '2026-08-07 18:23:04', 'resolved', '2026-08-08 15:50:45', 'entry', 'unknown', '2026-08-07 10:23:04', '2026-08-08 07:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `admin_id`, `title`, `content`, `priority`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'CONCERN', 'MAKE it QUICK', 'medium', 1, '2026-08-07 12:48:43', '2026-08-07 12:48:43');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Profile Update', 'Updated profile: Chellsea Albano', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-07 10:24:03'),
(2, 1, 'Add Staff', 'Added staff: Mylene C. Samiling (STAFF-001)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-07 12:37:02'),
(3, 1, 'Add Announcement', 'Added announcement: CONCERN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-07 12:48:43'),
(4, 1, 'Password Reset Approved', 'Approved reset for student: Kristel Jade P. Albano - Token generated', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 03:16:00'),
(5, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:12:41'),
(6, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:12:54'),
(7, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:17:32'),
(8, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:26:23'),
(9, 1, 'Update Resident Email', 'Updated resident: Kristel Jade P. Albano', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:29:28'),
(10, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:30:26'),
(11, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:31:50'),
(12, 1, 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:32:17'),
(13, 1, 'Send Resident Email', 'Sent email to 1 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 05:33:18'),
(14, 1, 'Send Staff Email', 'Sent email to 1 staff members', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 06:55:17'),
(15, 1, 'Send Staff Email', 'Sent email to 1 staff members', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 06:57:53'),
(16, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:50:45'),
(17, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:50:49'),
(18, 1, 'Send Staff Email', 'Sent email to 1 staff members', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:51:06'),
(19, 1, 'Send Resident Email', 'Sent email to 1 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:52:19'),
(20, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:30'),
(21, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:31'),
(22, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:32'),
(23, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:32'),
(24, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:32'),
(25, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:33'),
(26, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:33'),
(27, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:34'),
(28, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:35'),
(29, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:35'),
(30, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:35'),
(31, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:36'),
(32, 1, 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 07:55:36'),
(33, 1, 'Concern Response', 'Responded to concern ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 08:39:03'),
(34, 1, 'Create Backup', 'Created backup: backup_2026-08-08_14-33-26.sql', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 12:33:26'),
(35, 1, 'Export Data', 'Exported residents as csv', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 12:37:06'),
(36, 1, 'Export Data', 'Exported residents as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 12:47:04'),
(37, 1, 'Export Data', 'Exported residents as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 12:55:26'),
(38, 1, 'Export Data', 'Exported access_logs as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 12:55:50'),
(39, 1, 'Export Data', 'Exported residents as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:06:06'),
(40, 1, 'Export Data', 'Exported access_logs as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:06:20'),
(41, 1, 'Export Data', 'Exported visitors as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:06:45'),
(42, 1, 'Export Data', 'Exported audit_logs as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:07:14'),
(43, 1, 'Export Data', 'Exported alerts as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:07:28'),
(44, 1, 'Export Data', 'Exported rfid_cards as CSV', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:07:39'),
(45, 1, 'Student Password Update', 'Updated password for student ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-10 03:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `available_rfid_cards`
--

CREATE TABLE `available_rfid_cards` (
  `card_id` int(11) NOT NULL,
  `card_uid` varchar(50) NOT NULL,
  `card_type` enum('resident','staff','visitor') DEFAULT 'resident',
  `status` enum('available','assigned','lost') DEFAULT 'available',
  `date_added` date DEFAULT curdate(),
  `added_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `available_rfid_cards`
--

INSERT INTO `available_rfid_cards` (`card_id`, `card_uid`, `card_type`, `status`, `date_added`, `added_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, '0A1FD006', 'resident', 'assigned', '2026-08-07', NULL, '', '2026-08-07 09:47:25', '2026-08-07 09:47:57');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `log_id` int(11) NOT NULL,
  `recipient_type` enum('staff','resident','visitor') NOT NULL DEFAULT 'staff',
  `recipient_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`) VALUES
(1, 'resident', 1, 'albanokristel14@gmail.com', 'Kristel Jade P. Albano', 'bhvh', 'xaswfw4', 1, '2026-08-08 13:33:18'),
(2, 'staff', 1, 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'hfytdtyhhhfgf', 1, '2026-08-08 14:55:17'),
(3, 'staff', 1, 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'zghjkl3456789ojhbnkl', 1, '2026-08-08 14:57:53'),
(4, 'staff', 1, 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'ka', 1, '2026-08-08 15:51:06'),
(5, 'resident', 1, 'albanokristel14@gmail.com', 'Kristel Jade P. Albano', 'mabaho', 'swascwsyfu3', 1, '2026-08-08 15:52:19');

-- --------------------------------------------------------

--
-- Table structure for table `email_update_logs`
--

CREATE TABLE `email_update_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_email` varchar(255) NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `math_logs`
--

CREATE TABLE `math_logs` (
  `log_id` int(11) NOT NULL,
  `user_type` enum('admin','staff','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `math_question` text NOT NULL,
  `user_answer` varchar(255) NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `notification_type` enum('unauthorized','buzzer','system') DEFAULT 'unauthorized',
  `card_uid` varchar(50) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `card_type` varchar(20) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `access_type` enum('entry','exit') DEFAULT 'entry',
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `notification_type`, `card_uid`, `user_name`, `card_type`, `reason`, `access_type`, `status`, `created_at`, `read_at`, `expires_at`) VALUES
(1, 'unauthorized', '85241249', 'Visitor (Expired)', 'visitor', 'Expired visitor card: 85241249', 'entry', 'unread', '2026-08-07 18:14:23', NULL, '2026-08-07 19:14:23'),
(2, 'unauthorized', '85241249', 'Visitor (Expired)', 'visitor', 'Expired visitor card: 85241249', 'entry', 'unread', '2026-08-07 18:14:39', NULL, '2026-08-07 19:14:39'),
(3, 'unauthorized', '65661B49', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 65661B49', 'entry', 'unread', '2026-08-07 18:23:04', NULL, '2026-08-07 19:23:04'),
(4, 'unauthorized', '65661B49', 'Unknown Card', 'unknown', 'Unknown card detected: 65661B49', 'entry', 'unread', '2026-08-07 18:23:04', NULL, '2026-08-07 19:23:04');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_id_number` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','denied','completed') DEFAULT 'pending',
  `reset_token` varchar(100) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `admin_response` text DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`request_id`, `student_id`, `student_name`, `student_id_number`, `username`, `email`, `reason`, `status`, `reset_token`, `token_expires_at`, `admin_response`, `requested_at`, `responded_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kristel Jade P. Albano', 'STU-2026-3808', 'Kristel Jade', 'albanokristel14@gmail.com', '', 'completed', '05e01a399e8147033fe0e9b5d88c6ede99c45b62c89aa6f45f7adeb00d26f4c0', '2026-08-09 05:53:41', '', '2026-08-08 11:05:17', '2026-08-08 11:22:02', '2026-08-08 03:05:17', '2026-08-08 03:54:26');

-- --------------------------------------------------------

--
-- Table structure for table `puzzle_attempts`
--

CREATE TABLE `puzzle_attempts` (
  `attempt_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('staff','student') NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resident_profiles`
--

CREATE TABLE `resident_profiles` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_registered` date DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `home_address` text DEFAULT NULL,
  `cp_no` varchar(20) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `dialect` varchar(50) DEFAULT NULL,
  `emergency_name` varchar(255) DEFAULT NULL,
  `emergency_relationship` varchar(50) DEFAULT NULL,
  `emergency_address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resident_profiles`
--

INSERT INTO `resident_profiles` (`profile_id`, `user_id`, `date_registered`, `course`, `year_level`, `gender`, `birth_date`, `age`, `home_address`, `cp_no`, `religion`, `dialect`, `emergency_name`, `emergency_relationship`, `emergency_address`, `emergency_contact`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-08-07', 'BAELS', '2nd Year', 'Female', '2007-11-14', 18, 'Aurora Alicia Isabela', NULL, 'Catholic', 'Tagalog', 'Jefferson Albano', 'Father', 'Aurora Alicia Isabela', '09539976519', '2026-08-07 09:40:16', '2026-08-07 09:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `rfid_cards`
--

CREATE TABLE `rfid_cards` (
  `card_id` int(11) NOT NULL,
  `card_uid` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `card_type` enum('resident','staff','visitor') DEFAULT 'resident',
  `issued_date` date DEFAULT curdate(),
  `expiry_date` date DEFAULT NULL,
  `validity_end` date DEFAULT NULL,
  `status` enum('active','deactivated','lost','expired') DEFAULT 'active',
  `visitor_name` varchar(255) DEFAULT NULL,
  `visitor_phone` varchar(20) DEFAULT NULL,
  `purpose_of_visit` varchar(255) DEFAULT NULL,
  `resident_visited` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_cards`
--

INSERT INTO `rfid_cards` (`card_id`, `card_uid`, `user_id`, `card_type`, `issued_date`, `expiry_date`, `validity_end`, `status`, `visitor_name`, `visitor_phone`, `purpose_of_visit`, `resident_visited`, `created_at`, `updated_at`) VALUES
(1, '0A1FD006', 1, 'resident', '2026-08-07', '2027-08-07', NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-07 09:47:57', '2026-08-07 09:47:57'),
(2, '85241249', 1, 'visitor', '2026-08-07', NULL, NULL, 'active', 'jeff albano', '09918256154', 'VISIT', 1, '2026-08-07 09:51:23', '2026-08-07 10:21:38');

-- --------------------------------------------------------

--
-- Table structure for table `saved_logins`
--

CREATE TABLE `saved_logins` (
  `saved_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_logins`
--

INSERT INTO `saved_logins` (`saved_id`, `admin_id`, `device_name`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `is_active`) VALUES
(1, 1, 'Chrome Browser', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 20:10:40', '2026-08-10 10:19:42', 1);

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `card_uid` varchar(255) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`log_id`, `event_type`, `card_uid`, `user_name`, `details`, `timestamp`) VALUES
(1, 'unauthorized_access', '85241249', 'Visitor (Expired)', 'Expired visitor card: 85241249 | Type: entry', '2026-08-07 18:14:23'),
(2, 'unauthorized_access', '85241249', 'Visitor (Expired)', 'Expired visitor card: 85241249 | Type: entry', '2026-08-07 18:14:39'),
(3, 'unauthorized_access', '65661B49', 'Unknown Card', 'Unknown card detected: 65661B49 | Type: entry', '2026-08-07 18:23:04');

-- --------------------------------------------------------

--
-- Table structure for table `staff_audit_logs`
--

CREATE TABLE `staff_audit_logs` (
  `log_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_users`
--

CREATE TABLE `staff_users` (
  `staff_id` int(11) NOT NULL,
  `staff_id_number` varchar(50) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `login_attempts` int(11) DEFAULT 0,
  `login_blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_users`
--

INSERT INTO `staff_users` (`staff_id`, `staff_id_number`, `full_name`, `email`, `department`, `phone`, `password_hash`, `avatar`, `created_at`, `updated_at`, `status`, `last_login`, `is_active`, `login_attempts`, `login_blocked_until`) VALUES
(1, 'STAFF-001', 'Mylene C. Samiling', 'mylenesamiling@gmail.com', 'Domitory Management', '09558271369', '$2y$10$cqgLe/JXVWz2qTBcVO8Xf.pESLHgBzdvGWRopOzMDpYhdrTAeY3vu', 'uploads/staff_photos/staff_1_1786106233.jpg', '2026-08-07 12:37:02', '2026-08-10 03:39:33', 'active', NULL, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_audit_logs`
--

CREATE TABLE `student_audit_logs` (
  `log_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_concerns`
--

CREATE TABLE `student_concerns` (
  `concern_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_id_number` varchar(50) NOT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `category` enum('maintenance','security','cleanliness','noise','other') DEFAULT 'other',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','in_progress','resolved','closed') DEFAULT 'pending',
  `admin_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_concerns`
--

INSERT INTO `student_concerns` (`concern_id`, `student_id`, `student_name`, `student_id_number`, `room_number`, `subject`, `message`, `category`, `priority`, `status`, `admin_response`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kristel Jade P. Albano', 'STU-2026-3808', '1', 'mabaho', 'dwqfdw3g', 'maintenance', 'medium', 'resolved', 'ok', '2026-08-07 13:26:19', '2026-08-08 08:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `student_users`
--

CREATE TABLE `student_users` (
  `student_id` int(11) NOT NULL,
  `student_id_number` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `course` varchar(50) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `login_attempts` int(11) DEFAULT 0,
  `login_blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_users`
--

INSERT INTO `student_users` (`student_id`, `student_id_number`, `full_name`, `username`, `course`, `year_level`, `email`, `profile_photo`, `password_hash`, `phone`, `room_number`, `resident_id`, `is_active`, `created_at`, `updated_at`, `login_attempts`, `login_blocked_until`) VALUES
(1, 'STU-2026-3808', 'Kristel Jade P. Albano', 'Kristel Jade', 'BAELS', '2nd Year', 'albanokristel14@gmail.com', NULL, '$2y$10$iTbWmePKvh47AF/maXaWdeColMlZczH7pxvxlz1Zx/2VQ89Vpwu1q', '09539976519', '1', 1, 1, '2026-08-07 09:44:23', '2026-08-10 03:00:39', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_configuration`
--

CREATE TABLE `system_configuration` (
  `config_id` int(11) NOT NULL,
  `config_key` varchar(255) NOT NULL,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `room_number` varchar(50) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `status` enum('pending','active','inactive','deleted') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL COMMENT 'Path to profile photo',
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `student_id`, `room_number`, `contact_number`, `email`, `password_hash`, `status`, `created_at`, `updated_at`, `profile_photo`, `is_active`) VALUES
(1, 'Kristel Jade P. Albano', 'STU-2026-8234', '1', '09539976519', 'albanokristel14@gmail.com', NULL, 'active', '2026-08-07 09:40:16', '2026-08-08 05:29:28', 'uploads/resident_photos/1786098269_1.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`setting_id`, `admin_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 1, 'dark_mode', 'true', '2026-08-08 07:55:30', '2026-08-08 07:55:36');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_logs`
--

CREATE TABLE `visitor_logs` (
  `visitor_log_id` int(11) NOT NULL,
  `visitor_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `visitor_phone` varchar(20) DEFAULT NULL,
  `visitor_contact` varchar(20) DEFAULT NULL,
  `resident_visited` int(11) NOT NULL,
  `purpose_of_visit` varchar(255) DEFAULT NULL,
  `temporary_card_uid` varchar(50) DEFAULT NULL,
  `validity_start` date DEFAULT curdate(),
  `validity_end` date DEFAULT NULL,
  `entry_timestamp` datetime DEFAULT NULL,
  `exit_timestamp` datetime DEFAULT NULL,
  `access_status` enum('pending','granted','denied','exited') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitor_logs`
--

INSERT INTO `visitor_logs` (`visitor_log_id`, `visitor_name`, `phone`, `relationship`, `visitor_phone`, `visitor_contact`, `resident_visited`, `purpose_of_visit`, `temporary_card_uid`, `validity_start`, `validity_end`, `entry_timestamp`, `exit_timestamp`, `access_status`, `created_at`, `updated_at`) VALUES
(1, 'jeff albano', '09918256154', 'ANAK', NULL, NULL, 1, 'VISIT', '85241249', '2026-08-07', '2026-08-14', '2026-08-08 15:48:47', NULL, 'granted', '2026-08-07 10:21:38', '2026-08-08 07:48:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_logs`
--
ALTER TABLE `access_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_card_uid` (`card_uid`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_access_type` (`access_type`),
  ADD KEY `idx_access_status` (`access_status`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_email_hash` (`email_hash`);

--
-- Indexes for table `admission_records`
--
ALTER TABLE `admission_records`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_semester` (`semester_sy`);

--
-- Indexes for table `alert_logs`
--
ALTER TABLE `alert_logs`
  ADD PRIMARY KEY (`alert_id`),
  ADD KEY `idx_card_uid` (`card_uid`),
  ADD KEY `idx_status` (`delivery_status`),
  ADD KEY `idx_type` (`alert_type`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_access_type` (`access_type`),
  ADD KEY `idx_resolved_at` (`resolved_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `available_rfid_cards`
--
ALTER TABLE `available_rfid_cards`
  ADD PRIMARY KEY (`card_id`),
  ADD UNIQUE KEY `card_uid` (`card_uid`),
  ADD KEY `added_by` (`added_by`),
  ADD KEY `idx_card_uid` (`card_uid`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_card_type` (`card_type`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_recipient_type` (`recipient_type`),
  ADD KEY `idx_sent_by` (`sent_by`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `email_update_logs`
--
ALTER TABLE `email_update_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_admin_id` (`admin_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `math_logs`
--
ALTER TABLE `math_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_requested_at` (`requested_at`),
  ADD KEY `idx_token` (`reset_token`);

--
-- Indexes for table `puzzle_attempts`
--
ALTER TABLE `puzzle_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- Indexes for table `resident_profiles`
--
ALTER TABLE `resident_profiles`
  ADD PRIMARY KEY (`profile_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  ADD PRIMARY KEY (`card_id`),
  ADD UNIQUE KEY `card_uid` (`card_uid`),
  ADD KEY `resident_visited` (`resident_visited`),
  ADD KEY `idx_card_uid` (`card_uid`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_card_type` (`card_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiry_date` (`expiry_date`);

--
-- Indexes for table `saved_logins`
--
ALTER TABLE `saved_logins`
  ADD PRIMARY KEY (`saved_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `staff_audit_logs`
--
ALTER TABLE `staff_audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `staff_users`
--
ALTER TABLE `staff_users`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `staff_id_number` (`staff_id_number`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_staff_id_number` (`staff_id_number`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `student_audit_logs`
--
ALTER TABLE `student_audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `student_concerns`
--
ALTER TABLE `student_concerns`
  ADD PRIMARY KEY (`concern_id`);

--
-- Indexes for table `student_users`
--
ALTER TABLE `student_users`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_id_number` (`student_id_number`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_student_id` (`student_id_number`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `system_configuration`
--
ALTER TABLE `system_configuration`
  ADD PRIMARY KEY (`config_id`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `unique_admin_setting` (`admin_id`,`setting_key`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  ADD PRIMARY KEY (`visitor_log_id`),
  ADD KEY `idx_resident_visited` (`resident_visited`),
  ADD KEY `idx_temporary_card` (`temporary_card_uid`),
  ADD KEY `idx_access_status` (`access_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_logs`
--
ALTER TABLE `access_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admission_records`
--
ALTER TABLE `admission_records`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `alert_logs`
--
ALTER TABLE `alert_logs`
  MODIFY `alert_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `available_rfid_cards`
--
ALTER TABLE `available_rfid_cards`
  MODIFY `card_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_update_logs`
--
ALTER TABLE `email_update_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `math_logs`
--
ALTER TABLE `math_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `puzzle_attempts`
--
ALTER TABLE `puzzle_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident_profiles`
--
ALTER TABLE `resident_profiles`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  MODIFY `card_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `saved_logins`
--
ALTER TABLE `saved_logins`
  MODIFY `saved_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_audit_logs`
--
ALTER TABLE `staff_audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_users`
--
ALTER TABLE `staff_users`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_audit_logs`
--
ALTER TABLE `student_audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_concerns`
--
ALTER TABLE `student_concerns`
  MODIFY `concern_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_users`
--
ALTER TABLE `student_users`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_configuration`
--
ALTER TABLE `system_configuration`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  MODIFY `visitor_log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admission_records`
--
ALTER TABLE `admission_records`
  ADD CONSTRAINT `admission_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `available_rfid_cards`
--
ALTER TABLE `available_rfid_cards`
  ADD CONSTRAINT `available_rfid_cards_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD CONSTRAINT `password_reset_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_users` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `resident_profiles`
--
ALTER TABLE `resident_profiles`
  ADD CONSTRAINT `resident_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  ADD CONSTRAINT `rfid_cards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `rfid_cards_ibfk_2` FOREIGN KEY (`resident_visited`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `saved_logins`
--
ALTER TABLE `saved_logins`
  ADD CONSTRAINT `saved_logins_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  ADD CONSTRAINT `visitor_logs_ibfk_1` FOREIGN KEY (`resident_visited`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
