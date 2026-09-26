-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: altaria.proxy.rlwy.net:19735
-- Generation Time: Sep 26, 2026 at 06:29 PM
-- Server version: 9.4.0
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
  `log_id` int NOT NULL,
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `access_type` enum('entry','exit') COLLATE utf8mb4_general_ci DEFAULT 'entry',
  `access_status` enum('granted','denied') COLLATE utf8mb4_general_ci DEFAULT 'granted',
  `alert_triggered` tinyint(1) DEFAULT '0',
  `power_source` enum('main','battery','online','offline_sync') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'main',
  `reason` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_logs`
--

INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES
(27, 'E51CF746', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-14 07:57:29', '2026-08-13 23:57:29', '2026-08-13 23:57:29'),
(28, '85241249', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-14 08:30:54', '2026-08-14 00:30:54', '2026-08-14 00:30:54'),
(29, '85241249', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-14 08:31:03', '2026-08-14 00:31:03', '2026-08-14 00:31:03'),
(30, '85241249', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-14 08:32:10', '2026-08-14 00:32:10', '2026-08-14 00:32:10'),
(31, '85241249', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-14 08:33:40', '2026-08-14 00:33:40', '2026-08-14 00:33:40'),
(32, '85241249', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-14 08:34:07', '2026-08-14 00:34:07', '2026-08-14 00:34:07'),
(33, 'E51CF746', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-14 08:34:17', '2026-08-14 00:34:17', '2026-08-14 00:34:17'),
(34, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-14 08:34:49', '2026-08-14 00:34:49', '2026-08-14 00:34:49'),
(35, '656DEF46', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-15 13:55:57', '2026-08-15 05:55:57', '2026-08-15 05:55:57'),
(36, '0A1FD006', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-15 16:51:28', '2026-08-15 08:51:28', '2026-08-15 08:51:28'),
(37, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-15 16:51:43', '2026-08-15 08:51:43', '2026-08-15 08:51:43'),
(38, '656DEF46', NULL, 'entry', 'denied', 1, 'main', NULL, '2026-08-15 16:52:59', '2026-08-15 08:52:59', '2026-08-15 08:52:59'),
(39, '0A1FD006', 1, 'entry', 'granted', 0, 'main', NULL, '2026-08-15 16:54:14', '2026-08-15 08:54:14', '2026-08-15 08:54:14'),
(40, '0A1FD006', 1, 'exit', 'granted', 0, 'main', NULL, '2026-08-15 16:54:29', '2026-08-15 08:54:29', '2026-08-15 08:54:29'),
(41, '0A1FD006', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-25 14:41:34', '2026-08-25 06:41:34', '2026-08-25 06:41:34'),
(42, '0A1FD006', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-25 14:42:34', '2026-08-25 06:42:34', '2026-08-25 06:42:34'),
(43, '0A1FD006', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-25 14:43:27', '2026-08-25 06:43:27', '2026-08-25 06:43:27'),
(44, '0A1FD006', 1, 'exit', 'granted', 0, 'offline_sync', NULL, '2026-08-25 14:43:29', '2026-08-25 06:43:29', '2026-08-25 06:43:29'),
(45, '0A1FD006', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-27 20:02:07', '2026-08-27 12:02:07', '2026-08-27 12:02:07'),
(46, '0A1FD006', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-27 20:32:23', '2026-08-27 12:32:23', '2026-08-27 12:32:23'),
(47, 'E5380849', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 14:57:35', '2026-08-30 06:57:35', '2026-08-30 06:57:35'),
(48, '0A1FD006', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 14:57:59', '2026-08-30 06:57:59', '2026-08-30 06:57:59'),
(49, '55790B49', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 14:59:25', '2026-08-30 06:59:25', '2026-08-30 06:59:25'),
(50, '55790B49', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 15:04:16', '2026-08-30 07:04:16', '2026-08-30 07:04:16'),
(51, '0A1FD006', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 15:04:24', '2026-08-30 07:04:24', '2026-08-30 07:04:24'),
(52, 'E5380849', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 15:04:32', '2026-08-30 07:04:32', '2026-08-30 07:04:32'),
(53, 'E5380849', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 15:33:15', '2026-08-30 07:33:15', '2026-08-30 07:33:15'),
(54, '55790B49', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 15:33:27', '2026-08-30 07:33:27', '2026-08-30 07:33:27'),
(55, '656DEF46', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 15:48:25', '2026-08-30 07:48:25', '2026-08-30 07:48:25'),
(56, '656DEF46', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 15:49:45', '2026-08-30 07:49:45', '2026-08-30 07:49:45'),
(57, 'B447CB06', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 16:01:42', '2026-08-30 08:01:42', '2026-08-30 08:01:42'),
(58, '656DEF46', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 16:02:03', '2026-08-30 08:02:03', '2026-08-30 08:02:03'),
(59, 'D59A0F49', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 16:02:21', '2026-08-30 08:02:21', '2026-08-30 08:02:21'),
(60, 'D59A0F49', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 16:10:23', '2026-08-30 08:10:23', '2026-08-30 08:10:23'),
(61, '0A1FD006', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:12:30', '2026-08-30 08:12:30', '2026-08-30 08:12:30'),
(62, '0A1FD006', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 16:13:06', '2026-08-30 08:13:06', '2026-08-30 08:13:06'),
(63, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:14:34', '2026-08-30 08:14:34', '2026-08-30 08:14:34'),
(64, 'E5380849', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-08-30 16:15:30', '2026-08-30 08:15:30', '2026-08-30 08:15:30'),
(65, '453C2049', 2, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:16:10', '2026-08-30 08:16:10', '2026-08-30 08:16:10'),
(66, 'E5380849', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:16:25', '2026-08-30 08:16:25', '2026-08-30 08:16:25'),
(67, '55790B49', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:17:33', '2026-08-30 08:17:33', '2026-08-30 08:17:33'),
(68, '55790B49', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:17:53', '2026-08-30 08:17:53', '2026-08-30 08:17:53'),
(69, 'E5380849', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:18:19', '2026-08-30 08:18:19', '2026-08-30 08:18:19'),
(70, 'E5380849', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:18:33', '2026-08-30 08:18:33', '2026-08-30 08:18:33'),
(71, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:22:58', '2026-08-30 08:22:58', '2026-08-30 08:22:58'),
(72, '453C2049', 2, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:23:07', '2026-08-30 08:23:07', '2026-08-30 08:23:07'),
(73, '656DEF46', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:23:25', '2026-08-30 08:23:25', '2026-08-30 08:23:25'),
(74, '55790B49', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:24:03', '2026-08-30 08:24:03', '2026-08-30 08:24:03'),
(75, '656DEF46', 1, 'exit', 'granted', 0, 'online', NULL, '2026-08-30 16:24:18', '2026-08-30 08:24:18', '2026-08-30 08:24:18'),
(76, '656DEF46', 1, 'entry', 'granted', 0, 'online', NULL, '2026-08-30 16:25:17', '2026-08-30 08:25:17', '2026-08-30 08:25:17'),
(77, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-02 10:16:43', '2026-09-02 02:16:43', '2026-09-02 02:16:43'),
(78, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-02 10:17:14', '2026-09-02 02:17:14', '2026-09-02 02:17:14'),
(79, '656DEF46', 4, 'entry', 'granted', 0, 'online', NULL, '2026-09-02 10:31:38', '2026-09-02 02:31:38', '2026-09-02 02:31:38'),
(80, '59049CDE', 4, 'entry', 'granted', 0, 'online', NULL, '2026-09-02 10:39:51', '2026-09-02 02:39:51', '2026-09-02 02:39:51'),
(81, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-02 10:41:12', '2026-09-02 02:41:12', '2026-09-02 02:41:12'),
(82, '453C2049', 2, 'exit', 'granted', 0, 'online', NULL, '2026-09-02 10:41:21', '2026-09-02 02:41:21', '2026-09-02 02:41:21'),
(83, '59049CDE', 4, 'exit', 'granted', 0, 'online', NULL, '2026-09-02 10:41:58', '2026-09-02 02:41:58', '2026-09-02 02:41:58'),
(84, '59049CDE', 4, 'entry', 'granted', 0, 'online', NULL, '2026-09-02 10:45:28', '2026-09-02 02:45:28', '2026-09-02 02:45:28'),
(85, '656DEF46', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-02 10:46:12', '2026-09-02 02:46:12', '2026-09-02 02:46:12'),
(86, '59049CDE', 4, 'exit', 'granted', 0, 'offline_sync', NULL, '2026-09-05 16:02:35', '2026-09-05 08:02:35', '2026-09-05 08:02:35'),
(87, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-05 16:07:29', '2026-09-05 08:07:29', '2026-09-05 08:07:29'),
(88, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-05 16:12:37', '2026-09-05 08:12:37', '2026-09-05 08:12:37'),
(89, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-05 16:13:48', '2026-09-05 08:13:48', '2026-09-05 08:13:48'),
(90, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-05 16:16:13', '2026-09-05 08:16:13', '2026-09-05 08:16:13'),
(91, 'B447CB06', 3, 'entry', 'granted', 0, 'offline_sync', NULL, '2026-09-06 08:12:43', '2026-09-06 00:12:43', '2026-09-06 00:12:43'),
(92, 'B447CB06', 3, 'exit', 'granted', 0, 'offline_sync', NULL, '2026-09-06 08:12:46', '2026-09-06 00:12:46', '2026-09-06 00:12:46'),
(93, 'B447CB06', 3, 'entry', 'granted', 0, 'offline_sync', NULL, '2026-09-06 08:12:47', '2026-09-06 00:12:47', '2026-09-06 00:12:47'),
(94, 'B447CB06', 3, 'exit', 'granted', 0, 'offline_sync', NULL, '2026-09-06 08:12:49', '2026-09-06 00:12:49', '2026-09-06 00:12:49'),
(95, 'B447CB06', 3, 'entry', 'granted', 0, 'offline_sync', NULL, '2026-09-06 08:12:51', '2026-09-06 00:12:51', '2026-09-06 00:12:51'),
(96, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-07 10:25:31', '2026-09-07 02:25:31', '2026-09-07 02:25:31'),
(97, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 10:25:54', '2026-09-07 02:25:54', '2026-09-07 02:25:54'),
(98, 'B447CB06', 3, 'exit', 'granted', 0, 'offline_sync', NULL, '2026-09-07 15:59:05', '2026-09-07 07:59:05', '2026-09-07 07:59:05'),
(99, 'B447CB06', 3, 'entry', 'granted', 0, 'offline_sync', NULL, '2026-09-07 15:59:07', '2026-09-07 07:59:07', '2026-09-07 07:59:07'),
(100, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-07 15:59:36', '2026-09-07 07:59:36', '2026-09-07 07:59:36'),
(101, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 16:05:33', '2026-09-07 08:05:33', '2026-09-07 08:05:33'),
(102, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 16:05:52', '2026-09-07 08:05:52', '2026-09-07 08:05:52'),
(103, 'ED29C906', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-07 16:08:04', '2026-09-07 08:08:04', '2026-09-07 08:08:04'),
(104, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 16:12:29', '2026-09-07 08:12:29', '2026-09-07 08:12:29'),
(105, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 16:12:36', '2026-09-07 08:12:36', '2026-09-07 08:12:36'),
(106, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 16:12:44', '2026-09-07 08:12:44', '2026-09-07 08:12:44'),
(107, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 16:12:53', '2026-09-07 08:12:53', '2026-09-07 08:12:53'),
(108, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-07 20:33:46', '2026-09-07 12:33:46', '2026-09-07 12:33:46'),
(109, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 20:34:11', '2026-09-07 12:34:11', '2026-09-07 12:34:11'),
(110, '25829DEE', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-07 22:26:44', '2026-09-07 14:26:44', '2026-09-07 14:26:44'),
(111, '35D3BBEE', 6, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 22:27:22', '2026-09-07 14:27:22', '2026-09-07 14:27:22'),
(112, '253D30EE', 5, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 22:27:30', '2026-09-07 14:27:30', '2026-09-07 14:27:30'),
(113, '453C2049', 2, 'exit', 'granted', 0, 'online', NULL, '2026-09-07 22:27:52', '2026-09-07 14:27:52', '2026-09-07 14:27:52'),
(114, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-07 23:18:33', '2026-09-07 15:18:33', '2026-09-07 15:18:33'),
(115, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-07 23:40:10', '2026-09-07 15:40:10', '2026-09-07 15:40:10'),
(116, '253D30EE', 5, 'exit', 'granted', 0, 'online', NULL, '2026-09-07 23:41:38', '2026-09-07 15:41:38', '2026-09-07 15:41:38'),
(117, '25829DEE', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-07 23:51:10', '2026-09-07 15:51:10', '2026-09-07 15:51:10'),
(118, '25829DEE', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 00:09:52', '2026-09-07 16:09:52', '2026-09-07 16:09:52'),
(119, '25829DEE', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 00:14:03', '2026-09-07 16:14:03', '2026-09-07 16:14:03'),
(120, '25829DEE', 1, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 00:16:18', '2026-09-07 16:16:18', '2026-09-07 16:16:18'),
(121, 'D59A0F49', 4, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 00:17:00', '2026-09-07 16:17:00', '2026-09-07 16:17:00'),
(122, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 00:53:02', '2026-09-07 16:53:02', '2026-09-07 16:53:02'),
(123, '85241249', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 00:53:12', '2026-09-07 16:53:12', '2026-09-07 16:53:12'),
(124, 'E51CF746', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 01:08:46', '2026-09-07 17:08:46', '2026-09-07 17:08:46'),
(125, 'ED29C906', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 01:09:38', '2026-09-07 17:09:38', '2026-09-07 17:09:38'),
(126, 'ED29C906', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 01:12:37', '2026-09-07 17:12:37', '2026-09-07 17:12:37'),
(127, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-08 10:47:41', '2026-09-08 02:47:41', '2026-09-08 02:47:41'),
(128, '453C2049', 2, 'exit', 'granted', 0, 'online', NULL, '2026-09-08 10:47:59', '2026-09-08 02:47:59', '2026-09-08 02:47:59'),
(129, '35D3BBEE', 6, 'exit', 'granted', 0, 'online', NULL, '2026-09-08 10:48:09', '2026-09-08 02:48:09', '2026-09-08 02:48:09'),
(130, '35D3BBEE', 6, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 10:53:27', '2026-09-08 02:53:27', '2026-09-08 02:53:27'),
(131, '35D3BBEE', 6, 'exit', 'granted', 0, 'online', NULL, '2026-09-08 10:54:00', '2026-09-08 02:54:00', '2026-09-08 02:54:00'),
(132, '453C2049', 2, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 10:55:17', '2026-09-08 02:55:17', '2026-09-08 02:55:17'),
(133, '453C2049', 2, 'exit', 'granted', 0, 'online', NULL, '2026-09-08 10:55:50', '2026-09-08 02:55:50', '2026-09-08 02:55:50'),
(134, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 16:14:17', '2026-09-08 08:14:17', '2026-09-08 08:14:17'),
(135, '3583E3EE', 8, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 16:22:56', '2026-09-08 08:22:56', '2026-09-08 08:22:56'),
(136, '3583E3EE', 8, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 16:25:13', '2026-09-08 08:25:13', '2026-09-08 08:25:13'),
(137, 'B447CB06', 3, 'exit', 'granted', 0, 'online', NULL, '2026-09-08 16:28:37', '2026-09-08 08:28:37', '2026-09-08 08:28:37'),
(138, '3583E3EE', NULL, 'entry', 'denied', 1, 'online', NULL, '2026-09-08 16:33:03', '2026-09-08 08:33:03', '2026-09-08 08:33:03'),
(139, 'B447CB06', 3, 'entry', 'granted', 0, 'online', NULL, '2026-09-08 16:34:17', '2026-09-08 08:34:17', '2026-09-08 08:34:17'),
(140, 'B447CB06', NULL, 'exit', 'denied', 1, 'online', NULL, '2026-09-08 16:35:25', '2026-09-08 08:35:25', '2026-09-08 08:35:25'),
(141, '453C2049', 2, 'entry', 'denied', 1, 'online', NULL, '2026-09-18 14:38:52', '2026-09-18 06:38:52', '2026-09-18 06:38:52'),
(142, 'B447CB06', 3, 'exit', 'denied', 1, 'online', NULL, '2026-09-18 14:48:09', '2026-09-18 06:48:09', '2026-09-18 06:48:09'),
(143, '453C2049', 2, 'exit', 'denied', 1, 'online', NULL, '2026-09-18 14:51:13', '2026-09-18 06:51:13', '2026-09-18 06:51:13'),
(144, '453C2049', 2, 'entry', 'denied', 1, 'online', NULL, '2026-09-18 15:07:00', '2026-09-18 07:07:00', '2026-09-18 07:07:00'),
(145, 'B447CB06', 3, 'exit', 'denied', 1, 'online', NULL, '2026-09-18 15:08:43', '2026-09-18 07:08:43', '2026-09-18 07:08:43');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `admin_id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email_hash` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('administrator','staff','manager') COLLATE utf8mb4_general_ci DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT '1',
  `math_attempts` int DEFAULT '0',
  `math_blocked_until` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `otp_enabled` tinyint(1) DEFAULT '1',
  `login_attempts` int DEFAULT '0',
  `login_blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `otp_expiry`, `full_name`, `email`, `email_hash`, `email_verified`, `avatar`, `role`, `is_active`, `math_attempts`, `math_blocked_until`, `last_login`, `created_at`, `updated_at`, `otp_enabled`, `login_attempts`, `login_blocked_until`) VALUES
(1, 'admin_chelsea', '$2a$12$3PYMMlfyil2JMr43rbJlf.p8QLIvKEH8SfyvsIuU7ruJ4ByhzBCdq', NULL, 'Chellsea Albano', 'albanochellsea30@gmail.com', '40d9222232106ee0a94ab60ed286fd82a57c664c5cd6b9633057afbe3f1862fe', 1, 'uploads/avatars/avatar_1_1786189553.png', 'administrator', 1, 0, NULL, '2026-09-25 19:56:05', '2026-08-07 09:12:16', '2026-09-25 11:56:05', 1, 0, NULL),
(2, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'System Administrator', 'admin@tapandgo.com', '2f22d7fa3ba2ba8d9aafe7993f39b0fc72dc12337f58b2874e7f9c4ab00961b3', 1, NULL, 'administrator', 1, 0, NULL, NULL, '2026-08-07 09:12:16', '2026-08-07 09:12:16', 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admission_records`
--

CREATE TABLE `admission_records` (
  `admission_id` int NOT NULL,
  `user_id` int NOT NULL,
  `semester_sy` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `age` int DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `home_address` text COLLATE utf8mb4_general_ci,
  `school_last` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_address` text COLLATE utf8mb4_general_ci,
  `strand_track` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `course_taken` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level_old` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `former_bh` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `former_address` text COLLATE utf8mb4_general_ci,
  `guardian_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `guardian_contact` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `room_assignment` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `student_signature` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission_records`
--

INSERT INTO `admission_records` (`admission_id`, `user_id`, `semester_sy`, `age`, `birth_date`, `home_address`, `school_last`, `school_address`, `strand_track`, `course_taken`, `year_level_old`, `former_bh`, `former_address`, `guardian_name`, `guardian_contact`, `room_assignment`, `student_signature`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '1st semester 2025-2026', 18, '2007-11-14', 'Aurora Alicia Isabela', '0', 'Paddad alicia isabela', '', 'BAELS', '', '', 'Aurora Alicia Isabela', 'Jefferson Albano', '09539976519', '1', 'Kristel Jade P. Albano', 'active', '2026-08-07 09:43:03', '2026-08-23 11:48:17'),
(2, 2, '1st semester 2026-2027', 21, '2004-10-30', 'ALICIA', '0', 'Paddad alicia isabela', '', '', '', '', '', 'DAVID P. ALBANO', '09584217784', 'Not Assigned', 'MHAE P. ALBANO', 'active', '2026-08-30 07:28:32', '2026-08-30 07:28:32'),
(3, 3, '1st semester 2026-2027', 24, '2002-01-10', 'CANIGUING ECHAGUE ISABELA', '0', 'SANTO DOMINGO ECHAGUE, ISABELA', '', 'BSIT', '4th Year', 'NA', 'NA', 'ROBERT R. VELASCO', '09058602804', 'Not Assigned', 'JESSICA B. VELASCO', 'active', '2026-09-02 02:04:44', '2026-09-02 02:04:44'),
(4, 4, '1st semester 2026-2027', 22, '2004-04-27', 'SAN FABIAN ECHAGUE ISABELA', '0', 'SAN GUILLERMO ISABELA', '', 'BSIT', '4th Year', '', '', 'JERRYMIE A. ABAYA', '09655577201', 'Not Assigned', 'JESSICA A. ABAYA', 'active', '2026-09-02 02:26:48', '2026-09-02 02:26:48'),
(5, 5, '1st Semester, SY 2026-2027', 22, '2004-04-17', 'SAN FABIAN', '0', '', '', 'BSIT', '4th Year', '', '', 'JERRYMIE A. ABAYA', '09885776255', 'Not Assigned', 'JESSICA A. ABAYA', 'active', '2026-09-06 11:49:29', '2026-09-06 11:49:29'),
(6, 6, '1st Semester, SY 2026-2027', 21, '2004-09-17', 'SAN ANTONIO UGAD ECHAGUE, ISABELA', '0', '', '', '', '', '', '', 'ALFONSO AQUINO', '09488216479', 'Not Assigned', 'FRANCISCA P. AQUINO', 'active', '2026-09-07 12:35:50', '2026-09-07 12:35:50'),
(7, 7, '1st Semester, SY 2026-2027', 18, '2007-11-14', 'FORTUNE EAST AURORA ALICIA ISABELA', '0', 'PADDAD ALICIA ISABELA', '', 'BAELS', '2nd Year', '', '', 'JEFFERSON B. ALBANO', '09539976519', 'Not Assigned', 'KRISTEL JADE P. ALBANO', 'active', '2026-09-08 03:17:18', '2026-09-08 03:17:18'),
(8, 8, '1st Semester, SY 2026-2027', 21, '2004-10-07', 'AURORA ALICIA', '0', '', '', '', '', '', '', 'DWAYNE P. ALBANO', '09558201193', 'Not Assigned', 'CHELLS P. ALBANO', 'active', '2026-09-08 08:23:53', '2026-09-08 08:23:53');

-- --------------------------------------------------------

--
-- Table structure for table `alert_logs`
--

CREATE TABLE `alert_logs` (
  `alert_id` int NOT NULL,
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alert_type` enum('unauthorized','system','warning') COLLATE utf8mb4_general_ci DEFAULT 'unauthorized',
  `reason` text COLLATE utf8mb4_general_ci,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `delivery_status` enum('pending','resolved') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `resolved_at` datetime DEFAULT NULL,
  `access_type` enum('entry','exit') COLLATE utf8mb4_general_ci DEFAULT 'entry',
  `card_type` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'unknown',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alert_logs`
--

INSERT INTO `alert_logs` (`alert_id`, `card_uid`, `user_name`, `alert_type`, `reason`, `timestamp`, `delivery_status`, `resolved_at`, `access_type`, `card_type`, `created_at`, `updated_at`) VALUES
(52, '85241249', 'jeff albano (Visitor)', 'unauthorized', 'Expired visitor card: 85241249', '2026-09-08 00:53:12', 'resolved', '2026-09-08 09:53:51', 'entry', 'visitor', '2026-09-07 16:53:12', '2026-09-08 01:53:51'),
(54, 'E51CF746', 'Unknown Card', 'unauthorized', 'Unknown card detected: E51CF746', '2026-09-08 01:08:46', 'resolved', '2026-09-08 09:53:51', 'entry', 'unknown', '2026-09-07 17:08:46', '2026-09-08 01:53:51'),
(56, 'ED29C906', 'Unknown Card', 'unauthorized', 'Unknown card detected: ED29C906', '2026-09-08 01:09:38', 'resolved', '2026-09-08 09:53:51', 'entry', 'unknown', '2026-09-07 17:09:38', '2026-09-08 01:53:51'),
(58, 'ED29C906', 'Unknown Card', 'unauthorized', 'Unknown card detected: ED29C906', '2026-09-08 01:12:37', 'resolved', '2026-09-08 09:53:51', 'entry', 'unknown', '2026-09-07 17:12:37', '2026-09-08 01:53:51'),
(59, '3583E3EE', 'Inactive Card', 'unauthorized', 'Inactive card detected: 3583E3EE', '2026-09-08 16:33:03', 'resolved', '2026-09-14 11:02:07', 'entry', 'resident', '2026-09-08 08:33:03', '2026-09-14 03:02:07'),
(60, 'B447CB06', 'Inactive Card', 'unauthorized', 'Inactive card detected: B447CB06', '2026-09-08 16:35:25', 'resolved', '2026-09-14 11:02:07', 'exit', 'resident', '2026-09-08 08:35:25', '2026-09-14 03:02:07');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `priority` enum('low','medium','high') COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `admin_id`, `title`, `content`, `priority`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'CONCERN', 'MAKE it QUICK', 'medium', 1, '2026-08-07 12:48:43', '2026-09-06 03:03:21');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int NOT NULL,
  `admin_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
(45, 1, 'Student Password Update', 'Updated password for student ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-10 03:00:14'),
(46, 1, 'Resolve All Alerts', 'Resolved 4 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 01:54:31'),
(47, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 01:54:35'),
(48, 1, 'Register RFID Card', 'Registered visitor RFID card: 55790B49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:20:40'),
(49, 1, 'Register Visitor', 'Registered visitor: Jeff Albano (Duration: 30 days)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:25:02'),
(50, 1, 'Visitor Check In', 'Checked in visitor ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:25:53'),
(51, 1, 'Delete Visitor', 'Deleted visitor record ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:32:43'),
(52, 1, 'Register RFID Card', 'Registered visitor RFID card: 55790B49', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:35:54'),
(53, 1, 'Register Visitor', 'Registered visitor: Jeff Albano (Duration: 30 days)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:36:16'),
(54, 1, 'Visitor Check In', 'Checked in visitor ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:36:36'),
(55, 1, 'Visitor Check In', 'Checked in visitor ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:36:44'),
(56, 1, 'Visitor Check In', 'Checked in visitor ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:38:10'),
(57, 1, 'Visitor Check Out', 'Checked out visitor ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:38:18'),
(58, 1, 'Visitor Check Out', 'Checked out visitor ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:38:23'),
(59, 1, 'Deactivate RFID', 'Deactivated visitor card: 85241249', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:42:43'),
(60, 1, 'Deactivate RFID', 'Deactivated visitor card: 85241249', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:42:48'),
(61, 1, 'Deactivate RFID', 'Deactivated visitor card: 85241249', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:48:28'),
(62, 1, 'Deactivate RFID', 'Deactivated visitor card: 85241249', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:52:07'),
(63, 1, 'Deactivate RFID', 'Deactivated visitor card: 85241249', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 02:52:30'),
(64, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 08:52:43'),
(65, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 08:52:47'),
(66, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-15 08:52:49'),
(67, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 10:13:26'),
(68, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 10:13:31'),
(69, 1, 'Add Announcement', 'Added announcement: agsywqr224', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 11:57:57'),
(70, 1, 'Add Announcement', 'Added announcement: agsywqr224', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 11:59:15'),
(71, 1, 'Export Data', 'Exported residents as CSV', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 11:59:54'),
(72, 1, 'Password Reset Approved', 'Approved reset for student: Kristel Jade P. Albano - Token generated', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-23 10:20:53'),
(73, 1, 'Update Staff Card', 'Updated card for staff ID: 1 to E5380849', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 06:45:49'),
(74, 1, 'Export Data', 'Exported residents as CSV', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:02:19'),
(75, 1, 'Export Data', 'Exported residents as CSV', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:08:20'),
(76, 1, 'Export Data', 'Exported access_logs as CSV', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:08:47'),
(77, 1, 'Export Data', 'Exported visitors as CSV', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:09:01'),
(78, 1, 'Student Registration', 'Registered student: MHAE P. ALBANO (STU-2026-7950)', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:30:33'),
(79, 1, 'Room Assignment', 'Assigned user ID 2 to Room 1', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:30:54'),
(80, 1, 'Delete RFID', 'Deleted RFID card E5380849', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:31:06'),
(81, 1, 'Delete RFID', 'Deleted RFID card E5380849', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:31:12'),
(82, 1, 'Delete RFID', 'Deleted RFID card E5380849', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:31:14'),
(83, 1, 'Register RFID', 'Registered RFID card 01AFD006 for user ID: 2', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:31:59'),
(84, 1, 'Remove Staff Card', 'Removed card for staff ID: 1', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:33:52'),
(85, 1, 'Update Staff Card', 'Updated card for staff ID: 1 to E5380849', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:33:59'),
(86, 1, 'Resolve Alert', 'Resolved alert ID: 14', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:48:09'),
(87, 1, 'Resolve Alert', 'Resolved alert ID: 14', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:48:12'),
(88, 1, 'Resolve Alert', 'Resolved alert ID: 14', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:48:23'),
(89, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:21'),
(90, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:24'),
(91, 1, 'Delete Alert', 'Deleted alert ID: 16', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:27'),
(92, 1, 'Delete Alert', 'Deleted alert ID: 16', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:29'),
(93, 1, 'Delete Alert', 'Deleted alert ID: 14', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:30'),
(94, 1, 'Delete Alert', 'Deleted alert ID: 15', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:32'),
(95, 1, 'Delete Alert', 'Deleted alert ID: 15', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:34'),
(96, 1, 'Delete Alert', 'Deleted alert ID: 15', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 07:49:43'),
(97, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:01:28'),
(98, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:01:31'),
(99, 1, 'Resolve All Alerts', 'Resolved 1 alerts', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:01:41'),
(100, 1, 'Resolve All Alerts', 'Resolved 5 alerts', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:02:50'),
(101, 1, 'Resolve All Alerts', 'Resolved 0 alerts', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:02:53'),
(102, 1, 'Delete Alert', 'Deleted alert ID: 17', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:02:55'),
(103, 1, 'Delete Alert', 'Deleted alert ID: 24', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:02:57'),
(104, 1, 'Delete Alert', 'Deleted alert ID: 24', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:01'),
(105, 1, 'Delete Alert', 'Deleted alert ID: 23', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:05'),
(106, 1, 'Delete Alert', 'Deleted alert ID: 23', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:07'),
(107, 1, 'Delete Alert', 'Deleted alert ID: 22', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:10'),
(108, 1, 'Delete Alert', 'Deleted alert ID: 22', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:14'),
(109, 1, 'Delete Alert', 'Deleted alert ID: 21', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:21'),
(110, 1, 'Delete Alert', 'Deleted alert ID: 21', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:26'),
(111, 1, 'Delete Alert', 'Deleted alert ID: 20', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:03:28'),
(112, 1, 'Delete RFID', 'Deleted RFID card E5380849', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:11:06'),
(113, 1, 'Delete RFID', 'Deleted RFID card 0A1FD006', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:12:52'),
(114, 1, 'Delete RFID', 'Deleted RFID card 01AFD006', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:13:27'),
(115, 1, 'Register RFID', 'Registered RFID card 453C2049 for user ID: 2', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:13:51'),
(116, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:14:10'),
(117, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:14:16'),
(118, 1, 'Resolve Alert', 'Resolved alert ID: 28', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:14:41'),
(119, 1, 'Delete Alert', 'Deleted alert ID: 28', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:14:45'),
(120, 1, 'Remove Staff Card', 'Removed card for staff ID: 1', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:15:36'),
(121, 1, 'Update Staff Card', 'Updated card for staff ID: 1 to E5380849', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:15:41'),
(122, 1, 'Resolve All Alerts', 'Resolved 1 alerts', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:15:54'),
(123, 1, 'Delete Alert', 'Deleted alert ID: 30', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:15:57'),
(124, 1, 'Resolve Alert', 'Resolved alert ID: 31', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:16:39'),
(125, 1, 'Delete Alert', 'Deleted alert ID: 31', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:16:42'),
(126, 1, 'Delete Alert', 'Deleted alert ID: 27', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:16:51'),
(127, 1, 'Delete Alert', 'Deleted alert ID: 27', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:16:54'),
(128, 1, 'Delete Alert', 'Deleted alert ID: 26', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:16:58'),
(129, 1, 'Delete Alert', 'Deleted alert ID: 26', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:17:03'),
(130, 1, 'Delete Alert', 'Deleted alert ID: 19', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:17:05'),
(131, 1, 'Delete Alert', 'Deleted alert ID: 19', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:17:07'),
(132, 1, 'Delete Alert', 'Deleted alert ID: 18', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:17:09'),
(133, 1, 'Delete Alert', 'Deleted alert ID: 18', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:17:12'),
(134, 1, 'Resolve Alert', 'Resolved alert ID: 32', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:18:36'),
(135, 1, 'Delete Alert', 'Deleted alert ID: 33', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:18:40'),
(136, 1, 'Delete Alert', 'Deleted alert ID: 33', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:18:43'),
(137, 1, 'Remove Staff Card', 'Removed card for staff ID: 1', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:19:19'),
(138, 1, 'Update Staff Card', 'Updated card for staff ID: 1 to 656DEF46', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:19:33'),
(139, 1, 'Delete Announcement', 'Deleted announcement ID: 3', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:19:57'),
(140, 1, 'Delete Announcement', 'Deleted announcement ID: 2', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:19:59'),
(141, 1, 'Resolve Alert', 'Resolved alert ID: 35', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:24:24'),
(142, 1, 'Resolve All Alerts', 'Resolved 1 alerts', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 08:24:28'),
(143, 1, 'Delete RFID', 'Deleted RFID card 656DEF46', '100.64.0.9', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-31 01:56:31'),
(144, 1, 'Send Resident Email', 'Sent email to 0 residents', '100.64.0.2', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-31 12:07:33'),
(145, 1, 'Export Data', 'Exported residents as CSV', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 00:37:19'),
(146, 1, 'Export Data', 'Exported visitors as CSV', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 00:37:51'),
(147, 1, 'Room Assignment', 'Assigned user ID 3 to Room 5', '100.64.0.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:05:02'),
(148, 1, 'Student Registration', 'Registered student: JESSICA B. VELASCO (STU-2026-8498)', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:07:37'),
(149, 1, 'Register RFID', 'Registered RFID card B447CB06 for user ID: 3', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:11:48'),
(150, 1, 'Room Assignment', 'Assigned user ID 4 to Room 5', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:26:59'),
(151, 1, 'Student Registration', 'Registered student: JESSICA A. ABAYA (STU-2026-0489)', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:28:10'),
(152, 1, 'Register RFID', 'Registered RFID card 656DEF46 for user ID: 4', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:29:28'),
(153, 1, 'Delete RFID', 'Deleted RFID card 656DEF46', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:38:02'),
(154, 1, 'Register RFID', 'Registered RFID card 59049CDE for user ID: 4', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:38:39'),
(155, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:40:11'),
(156, 1, 'Resolve All Alerts', 'Resolved 1 alerts', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:49:28'),
(157, 1, 'Resolve Alert', 'Resolved alert ID: 40', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:49:30'),
(158, 1, 'Update Resident Email', 'Updated resident: MHAE P. ALBANO', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 02:49:59'),
(159, 1, 'Export Data', 'Exported visitors as CSV', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 05:52:30'),
(160, 1, 'Toggle Announcement', 'Toggled announcement ID: 1', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 18:02:04'),
(161, 1, 'Toggle Announcement', 'Toggled announcement ID: 1', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 03:03:21'),
(162, 1, 'Register RFID Card', 'Registered visitor RFID card: D59AOF49', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:40:47'),
(163, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:43:03'),
(164, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:43:08'),
(165, 1, 'Register RFID Card', 'Registered visitor RFID card: D59AOF49', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:43:27'),
(166, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:43:27'),
(167, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:43:45'),
(168, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:44:09'),
(169, 1, 'Register RFID Card', 'Registered visitor RFID card: D59A0F49', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:44:50'),
(170, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:44:50'),
(171, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:45:07'),
(172, 1, 'Register Visitor', 'Registered encrypted visitor: JERRYMIE A. ABAYA (Duration: 1 Week, 7 days)', '100.64.0.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:48:09'),
(173, 1, 'Delete RFID', 'Deleted visitor card: D59AOF49', '100.64.0.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:48:09'),
(174, 1, 'Visitor Check In', 'Checked in: JERRYMIE A. ABAYA', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 08:48:39'),
(175, 1, 'Password Reset Approved', 'Approved reset for student: JESSICA B. VELASCO - Token generated', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 14:33:25'),
(176, 1, 'Password Reset Approved', 'Approved reset for student: JESSICA B. VELASCO - Token generated', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 02:40:15'),
(177, 1, 'Student Registration', 'Registered student: FRANCISCA P. AQUINO (STU-2026-6511)', '100.64.0.14', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 06:23:06'),
(178, 1, 'Resolve All Alerts', 'Resolved 1 alerts', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:32:40'),
(179, 1, 'Delete RFID', 'Deleted RFID card 59049CDE', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:36:04'),
(180, 1, 'Register RFID', 'Registered RFID card 35D3BBEE for user ID: 6', '100.64.0.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:39:31'),
(181, 1, 'Register RFID', 'Registered RFID card 253D30EE for user ID: 5', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:40:27'),
(182, 1, 'Delete Student', 'Deleted student: JESSICA A. ABAYA', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:44:41'),
(183, 1, 'Delete Student', 'Deleted student: Kristel Jade P. Albano', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:44:48'),
(184, 1, 'Room Assignment', 'Assigned user ID 6 to Room 2', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:45:07'),
(185, 1, 'Room Assignment', 'Assigned user ID 5 to Room 3', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:45:19'),
(186, 1, 'Student Registration', 'Registered student: JESSICA A. ABAYA (STU-2026-4122)', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:52:44'),
(187, 1, 'Delete RFID', 'Deleted RFID card D59A0F49', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 12:56:23'),
(188, 1, 'Remove Staff Card', 'Removed card for staff ID: 1', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:10:38'),
(189, 1, 'Update Staff Card', 'Updated card for staff ID: 1 to 258Z9DEE', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:11:16'),
(190, 1, 'Delete RFID', 'Deleted RFID card 258Z9DEE', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:11:47'),
(191, 1, 'Remove Staff Card', 'Removed card for staff ID: 1', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:19:42'),
(192, 1, 'Register Staff Card', 'Registered card 258Z9DEE for staff ID: 1', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:26:49'),
(193, 1, 'Add Staff', 'Added staff: Von Ibasco (STAFF-002)', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:46:56'),
(194, 1, 'Upload Staff Photo', 'Uploaded photo for staff ID: 2', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:54:40'),
(195, 1, 'Register Staff Card', 'Registered card 349950F4 for staff ID: 2', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:55:40'),
(196, 1, 'Register Staff Portal', 'Registered portal account for staff ID: 2', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 13:56:34'),
(197, 1, 'Delete Staff', 'Deleted staff ID: 2', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:05:47'),
(198, 1, 'Add Staff', 'Added staff: hello world (STAFF-002)', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:06:15'),
(199, 1, 'Upload Staff Photo', 'Uploaded photo for staff ID: 3', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:06:32'),
(200, 1, 'Register Staff Card', 'Registered card D59A0F49 for staff ID: 3', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:06:59'),
(201, 1, 'Register Staff Portal', 'Registered portal account for staff ID: 3', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:07:29'),
(202, 1, 'Delete RFID', 'Deleted RFID card D59A0F49', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:29:17'),
(203, 1, 'Delete RFID', 'Deleted RFID card D59A0F49', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:29:27'),
(204, 1, 'Delete RFID', 'Deleted RFID card D59A0F49', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:29:33'),
(205, 1, 'Delete RFID', 'Deleted RFID card D59A0F49', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:29:37'),
(206, 1, 'Delete RFID', 'Deleted RFID card 349950F4', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:29:44'),
(207, 1, 'Delete RFID', 'Deleted RFID card 258Z9DEE', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:29:56'),
(208, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:31:41'),
(209, 1, 'Resolve Alert', 'Resolved alert ID: 44', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:31:45'),
(210, 1, 'Resolve Alert', 'Resolved alert ID: 44', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 14:31:48'),
(211, 1, 'Resolve Alert', 'Resolved alert ID: 46', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 15:51:21'),
(212, 1, 'Delete Alert', 'Deleted alert ID: 44', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 15:51:27'),
(213, 1, 'Delete Alert', 'Deleted alert ID: 44', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 15:51:31'),
(214, 1, 'Delete Alert', 'Deleted alert ID: 46', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 15:51:34'),
(215, 1, 'Delete Alert', 'Deleted alert ID: 46', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 15:51:37'),
(216, 1, 'Delete Staff', 'Deleted staff ID: 3', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:07:26'),
(217, 1, 'Delete Visitor', 'Deleted visitor: JERRYMIE A. ABAYA', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:09:21'),
(218, 1, 'Add Staff', 'Added staff: Dwayne P. Albano (STAFF-002)', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:09:31'),
(219, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:10:24'),
(220, 1, 'Delete Alert', 'Deleted alert ID: 48', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:10:27'),
(221, 1, 'Delete Alert', 'Deleted alert ID: 48', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:10:30'),
(222, 1, 'Remove Staff Card', 'Removed card for staff ID: 1', '100.64.0.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:14:16'),
(223, 1, 'Update Staff Card', 'Updated card for staff ID: 1 to 25829DEE', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:15:35'),
(224, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 16:53:25'),
(225, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-07 17:11:23'),
(226, 1, 'Resolve All Alerts', 'Resolved 4 alerts', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 01:53:51'),
(227, 1, 'Remove Staff Card', 'Removed card for staff ID: 4', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 02:06:17'),
(228, 1, 'Delete Staff', 'Deleted staff ID: 4', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 02:06:24'),
(229, 1, 'Add Staff', 'Added staff: Dwayne P. Albano (STAFF-002)', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 02:07:44'),
(230, 1, 'Delete Staff', 'Deleted staff ID: 5', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 02:08:05'),
(231, 1, 'Register RFID', 'Registered RFID card 349950F4 for user ID: 7', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 03:18:15'),
(232, 1, 'Room Assignment', 'Assigned user ID 7 to Room 4', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 03:18:42'),
(233, 1, 'Deactivate RFID', 'Deactivated RFID card 349950F4', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 03:19:12'),
(234, 1, 'Delete RFID', 'Deleted visitor card: 55790B49', '100.64.0.16', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 03:21:39');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(235, 1, 'Delete Visitor', 'Deleted visitor: Jeff Albano', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 03:21:50'),
(236, 1, 'Delete RFID', 'Deleted RFID card 349950F4', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 03:22:26'),
(237, 1, 'Delete Student', 'Deleted student: FRANCISCA P. AQUINO', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 07:38:03'),
(238, 1, 'Student Registration', 'Registered student: FRANCISCA P. AQUINO (STU-2026-8335)', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 07:38:55'),
(239, 1, 'Register RFID', 'Registered RFID card 3583E3EE for user ID: 8', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:21:12'),
(240, 1, 'Deactivate RFID', 'Deactivated RFID card 3583E3EE', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:22:18'),
(241, 1, 'Deactivate RFID', 'Deactivated RFID card 3583E3EE', '100.64.0.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:22:28'),
(242, 1, 'Activate RFID', 'Activated RFID card 3583E3EE', '100.64.0.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:22:28'),
(243, 1, 'Room Assignment', 'Assigned user ID 8 to Room 4', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:25:46'),
(244, 1, 'Deactivate RFID', 'Deactivated RFID card 3583E3EE', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:32:41'),
(245, 1, 'Deactivate RFID', 'Deactivated RFID card B447CB06', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:35:07'),
(246, 1, 'Activate RFID', 'Activated RFID card B447CB06', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:37:38'),
(247, 1, 'Student Registration', 'Registered student: CHELLS P. ALBANO (STU-2026-6114)', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 08:41:15'),
(248, 1, 'Activate RFID', 'Activated RFID card 3583E3EE', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:01:58'),
(249, 1, 'Resolve All Alerts', 'Resolved 2 alerts', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:02:07'),
(250, 1, 'Delete RFID', 'Deleted RFID card 3583E3EE', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:06:53'),
(251, 1, 'Reject Student', 'Rejected student: CHELLS P. ALBANO (STU-2026-4262)', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:11:43'),
(252, 1, 'Reject Student', 'Rejected student: CHELLS P. ALBANO (STU-2026-4373)', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:15:51'),
(253, 1, 'Reject Student', 'Rejected student: CHELLS P. ALBANO (STU-2026-8570)', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:15:53'),
(254, 1, 'Reject Student', 'Rejected student: CHELLS P. ALBANO (STU-2026-2126)', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:15:56'),
(255, 1, 'Reject Student', 'Rejected student: CHELLS P. ALBANO (STU-2026-9180)', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:20:46'),
(256, 1, 'Approve Student', 'Approved student: CHELLS P. ALBANO (STU-2026-1801)', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:25:14'),
(257, 1, 'Reject Student', 'Rejected student: CHELLS P. ALBANO (STU-2026-6451)', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:28:24'),
(258, 1, 'Room Assignment', 'Assigned user ID 14 to Room 4', '100.64.0.6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-14 03:52:30'),
(259, 1, 'Deactivate RFID', 'Deactivated RFID card 453C2049', '100.64.0.21', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:25:48'),
(260, 1, 'Resolve All Alerts', 'Resolved 1 alerts', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:47:07'),
(261, 1, 'Activate RFID', 'Activated RFID card 453C2049', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:47:19'),
(262, 1, 'Deactivate RFID', 'Deactivated RFID card B447CB06', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:47:36'),
(263, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:48:18'),
(264, 1, 'Resolve Alert', 'Resolved alert ID: 64', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:03'),
(265, 1, 'Resolve Alert', 'Resolved alert ID: 64', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:06'),
(266, 1, 'Delete Alert', 'Deleted alert ID: 64', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:08'),
(267, 1, 'Delete Alert', 'Deleted alert ID: 64', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:12'),
(268, 1, 'Delete Alert', 'Deleted alert ID: 62', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:17'),
(269, 1, 'Delete Alert', 'Deleted alert ID: 62', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:19'),
(270, 1, 'Activate RFID', 'Activated RFID card B447CB06', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:47'),
(271, 1, 'Deactivate RFID', 'Deactivated RFID card 453C2049', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:52'),
(272, 1, 'Activate RFID', 'Activated RFID card B447CB06', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 06:49:52'),
(273, 1, 'Merge Duplicate Alerts', 'Merged 0 duplicates', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:06:01'),
(274, 1, 'Resolve All Alerts', 'Resolved 3 alerts', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:33'),
(275, 1, 'Delete Alert', 'Deleted alert ID: 68', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:40'),
(276, 1, 'Delete Alert', 'Deleted alert ID: 68', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:42'),
(277, 1, 'Delete Alert', 'Deleted alert ID: 67', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:46'),
(278, 1, 'Delete Alert', 'Deleted alert ID: 67', '100.64.0.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:48'),
(279, 1, 'Delete Alert', 'Deleted alert ID: 66', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:50'),
(280, 1, 'Delete Alert', 'Deleted alert ID: 66', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:07:52'),
(281, 1, 'Activate RFID', 'Activated RFID card 453C2049', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:08:08'),
(282, 1, 'Deactivate RFID', 'Deactivated RFID card B447CB06', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:08:12'),
(283, 1, 'Activate RFID', 'Activated RFID card 453C2049', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:08:12'),
(284, 1, 'Resolve Alert', 'Resolved alert ID: 70', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:58:41'),
(285, 1, 'Resolve Alert', 'Resolved alert ID: 70', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:58:43'),
(286, 1, 'Delete Alert', 'Deleted alert ID: 70', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:58:45'),
(287, 1, 'Delete Alert', 'Deleted alert ID: 70', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:58:48'),
(288, 1, 'Activate RFID', 'Activated RFID card B447CB06', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 07:59:11');

-- --------------------------------------------------------

--
-- Table structure for table `available_rfid_cards`
--

CREATE TABLE `available_rfid_cards` (
  `card_id` int NOT NULL,
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `card_type` enum('resident','staff','visitor') COLLATE utf8mb4_general_ci DEFAULT 'resident',
  `status` enum('available','assigned','lost') COLLATE utf8mb4_general_ci DEFAULT 'available',
  `date_added` date DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `available_rfid_cards`
--

INSERT INTO `available_rfid_cards` (`card_id`, `card_uid`, `card_type`, `status`, `date_added`, `added_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, '0A1FD006', 'resident', 'assigned', '2026-08-07', NULL, '', '2026-08-07 09:47:25', '2026-08-07 09:47:57');

-- --------------------------------------------------------

--
-- Table structure for table `current_occupancy`
--

CREATE TABLE `current_occupancy` (
  `occupancy_id` int NOT NULL,
  `user_id` int NOT NULL,
  `card_uid` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `room_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_type` enum('resident','staff','visitor') COLLATE utf8mb4_general_ci DEFAULT 'resident',
  `entry_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `status` enum('inside','outside') COLLATE utf8mb4_general_ci DEFAULT 'inside'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `log_id` int NOT NULL,
  `recipient_type` enum('staff','resident','visitor') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'staff',
  `recipient_id` int NOT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `recipient_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `sent_by` int NOT NULL,
  `sent_at` datetime NOT NULL,
  `status` enum('sent','failed','pending') COLLATE utf8mb4_general_ci DEFAULT 'sent',
  `error_message` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`, `status`, `error_message`) VALUES
(1, 'resident', 1, 'albanokristel14@gmail.com', 'Kristel Jade P. Albano', 'bhvh', 'xaswfw4', 1, '2026-08-08 13:33:18', 'sent', NULL),
(2, 'staff', 1, 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'hfytdtyhhhfgf', 1, '2026-08-08 14:55:17', 'sent', NULL),
(3, 'staff', 1, 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'zghjkl3456789ojhbnkl', 1, '2026-08-08 14:57:53', 'sent', NULL),
(4, 'staff', 1, 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'ka', 1, '2026-08-08 15:51:06', 'sent', NULL),
(5, 'resident', 1, 'albanokristel14@gmail.com', 'Kristel Jade P. Albano', 'mabaho', 'swascwsyfu3', 1, '2026-08-08 15:52:19', 'sent', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_update_logs`
--

CREATE TABLE `email_update_logs` (
  `log_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `user_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `old_email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `new_email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encrypted_visitor_data`
--

CREATE TABLE `encrypted_visitor_data` (
  `visitor_id` int NOT NULL,
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `visitor_name_enc` text COLLATE utf8mb4_general_ci,
  `phone_enc` text COLLATE utf8mb4_general_ci,
  `relationship_enc` text COLLATE utf8mb4_general_ci,
  `purpose_enc` text COLLATE utf8mb4_general_ci,
  `resident_visited_enc` text COLLATE utf8mb4_general_ci,
  `validity_start` date NOT NULL,
  `validity_end` date NOT NULL,
  `created_by` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encrypted_visitor_data`
--

INSERT INTO `encrypted_visitor_data` (`visitor_id`, `card_uid`, `visitor_name_enc`, `phone_enc`, `relationship_enc`, `purpose_enc`, `resident_visited_enc`, `validity_start`, `validity_end`, `created_by`, `ip_address`, `created_at`, `updated_at`) VALUES
(1, 'D59A0F49', 'Q+0UBkcEYIW127UzNA6wF8xXG5Vda7+VCbkO/tjGw1DbkqEuSxhwkEDBrVbEPeEC', 'UhqmE1BYFE4sM1Jd7KuF6r5JF/rWPSGDkQiPyFj6mZw=', 'BDt1swz3E0ocW6Gzqoh1gPbJVfz6sfC4r/ybV/O3s8Q=', 'EoUbwGCPmq+7e0Eh5U6FoaBydzu2iUQSGo0o3du/Qn4=', 'NHIGHGkxMdYRAo7fATlN8bAu4vqygXpxl9RNrRcO5W9YkfCvpfqfyC9Vo6YLVkya', '2026-09-06', '2026-09-13', 1, '100.64.0.16', '2026-09-06 08:48:09', '2026-09-06 08:48:09');

-- --------------------------------------------------------

--
-- Table structure for table `encryption_audit_log`
--

CREATE TABLE `encryption_audit_log` (
  `log_id` int NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `record_id` int NOT NULL,
  `action` enum('encrypt','decrypt','key_rotate','key_generate') COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('success','failed') COLLATE utf8mb4_general_ci DEFAULT 'success',
  `error_message` text COLLATE utf8mb4_general_ci,
  `performed_by` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encryption_key_management`
--

CREATE TABLE `encryption_key_management` (
  `key_id` int NOT NULL,
  `key_version` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `key_value` text COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Encrypted key value',
  `key_algorithm` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'AES-256-CBC',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` date DEFAULT NULL,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encryption_key_management`
--

INSERT INTO `encryption_key_management` (`key_id`, `key_version`, `key_value`, `key_algorithm`, `is_active`, `created_at`, `expires_at`, `created_by`) VALUES
(1, 'v1', 'CHANGE_THIS_TO_YOUR_32_CHAR_KEY_1234567890', 'AES-256-CBC', 1, '2026-09-06 07:57:21', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `encryption_migration_log`
--

CREATE TABLE `encryption_migration_log` (
  `migration_id` int NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `records_processed` int DEFAULT '0',
  `records_encrypted` int DEFAULT '0',
  `records_failed` int DEFAULT '0',
  `status` enum('pending','running','completed','failed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_general_ci,
  `performed_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `encryption_migration_log`
--

INSERT INTO `encryption_migration_log` (`migration_id`, `table_name`, `records_processed`, `records_encrypted`, `records_failed`, `status`, `started_at`, `completed_at`, `error_message`, `performed_by`) VALUES
(1, 'visitor_logs', 2, 2, 0, 'completed', '2026-09-06 07:57:29', '2026-09-06 07:57:42', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int DEFAULT '0',
  `last_attempt` datetime DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `math_logs`
--

CREATE TABLE `math_logs` (
  `log_id` int NOT NULL,
  `user_type` enum('admin','staff','student') COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `math_question` text COLLATE utf8mb4_general_ci NOT NULL,
  `user_answer` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `correct_answer` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int NOT NULL,
  `notification_type` enum('unauthorized','buzzer','system') COLLATE utf8mb4_general_ci DEFAULT 'unauthorized',
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `card_type` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `access_type` enum('entry','exit') COLLATE utf8mb4_general_ci DEFAULT 'entry',
  `status` enum('unread','read') COLLATE utf8mb4_general_ci DEFAULT 'unread',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
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
(4, 'unauthorized', '65661B49', 'Unknown Card', 'unknown', 'Unknown card detected: 65661B49', 'entry', 'unread', '2026-08-07 18:23:04', NULL, '2026-08-07 19:23:04'),
(5, 'unauthorized', 'E51CF746', 'Unknown', 'unknown', 'Unauthorized access attempt with card: E51CF746', 'entry', 'unread', '2026-08-14 07:57:29', NULL, '2026-08-14 08:57:29'),
(6, 'unauthorized', 'E51CF746', 'Unknown Card', 'unknown', 'Unknown card detected: E51CF746', 'entry', 'unread', '2026-08-14 07:57:29', NULL, '2026-08-14 08:57:29'),
(7, 'unauthorized', 'E51CF746', 'Unknown', 'unknown', 'Unauthorized access attempt with card: E51CF746', 'entry', 'unread', '2026-08-14 08:34:17', NULL, '2026-08-14 09:34:17'),
(8, 'unauthorized', 'E51CF746', 'Unknown Card', 'unknown', 'Unknown card detected: E51CF746', 'entry', 'unread', '2026-08-14 08:34:17', NULL, '2026-08-14 09:34:17'),
(9, 'unauthorized', '656DEF46', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-15 13:55:57', NULL, '2026-08-15 14:55:57'),
(10, 'unauthorized', '656DEF46', 'Unknown Card', 'unknown', 'Unknown card detected: 656DEF46', 'entry', 'unread', '2026-08-15 13:55:57', NULL, '2026-08-15 14:55:57'),
(11, 'unauthorized', '656DEF46', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-15 16:52:59', NULL, '2026-08-15 17:52:59'),
(12, 'unauthorized', '656DEF46', 'Unknown Card', 'unknown', 'Unknown card detected: 656DEF46', 'entry', 'unread', '2026-08-15 16:52:59', NULL, '2026-08-15 17:52:59'),
(13, 'unauthorized', 'E5380849', 'Unknown', 'unknown', 'Unauthorized access attempt with card: E5380849', 'entry', 'unread', '2026-08-30 15:33:13', NULL, '2026-08-30 16:33:13'),
(14, 'unauthorized', 'E5380849', 'Unknown Card', 'unknown', 'Unknown card detected: E5380849', 'entry', 'unread', '2026-08-30 15:33:15', NULL, '2026-08-30 16:33:15'),
(15, 'unauthorized', '656DEF46', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-30 15:48:23', NULL, '2026-08-30 16:48:23'),
(16, 'unauthorized', '656DEF46', 'Unknown Card', 'unknown', 'Unknown card detected: 656DEF46', 'entry', 'unread', '2026-08-30 15:48:25', NULL, '2026-08-30 16:48:25'),
(17, 'unauthorized', '656DEF46', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-30 15:49:43', NULL, '2026-08-30 16:49:43'),
(18, 'unauthorized', '656DEF46', 'Unknown Card', 'unknown', 'Unknown card detected: 656DEF46', 'entry', 'unread', '2026-08-30 15:49:45', NULL, '2026-08-30 16:49:45'),
(19, 'unauthorized', 'B447CB06', 'Unknown', 'unknown', 'Unauthorized access attempt with card: B447CB06', 'entry', 'unread', '2026-08-30 16:01:41', NULL, '2026-08-30 17:01:41'),
(20, 'unauthorized', 'B447CB06', 'Unknown Card', 'unknown', 'Unknown card detected: B447CB06', 'entry', 'unread', '2026-08-30 16:01:42', NULL, '2026-08-30 17:01:42'),
(21, 'unauthorized', '656DEF46', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-30 16:02:01', NULL, '2026-08-30 17:02:01'),
(22, 'unauthorized', '656DEF46', 'Unknown Card', 'unknown', 'Unknown card detected: 656DEF46', 'entry', 'unread', '2026-08-30 16:02:03', NULL, '2026-08-30 17:02:03'),
(23, 'unauthorized', 'D59A0F49', 'Unknown', 'unknown', 'Unauthorized access attempt with card: D59A0F49', 'entry', 'unread', '2026-08-30 16:02:20', NULL, '2026-08-30 17:02:20'),
(24, 'unauthorized', 'D59A0F49', 'Unknown Card', 'unknown', 'Unknown card detected: D59A0F49', 'entry', 'unread', '2026-08-30 16:02:21', NULL, '2026-08-30 17:02:21'),
(25, 'unauthorized', 'D59A0F49', 'Unknown', 'unknown', 'Unauthorized access attempt with card: D59A0F49', 'entry', 'unread', '2026-08-30 16:10:21', NULL, '2026-08-30 17:10:21'),
(26, 'unauthorized', 'D59A0F49', 'Unknown Card', 'unknown', 'Unknown card detected: D59A0F49', 'entry', 'unread', '2026-08-30 16:10:23', NULL, '2026-08-30 17:10:23'),
(27, 'unauthorized', '0A1FD006', 'Unknown Card', 'unknown', 'Unknown card detected: 0A1FD006', 'entry', 'unread', '2026-08-30 16:13:06', NULL, '2026-08-30 17:13:06'),
(28, 'unauthorized', '453C2049', 'MHAE P. ALBANO', 'resident', 'Unauthorized access attempt with card: 453C2049', 'entry', 'unread', '2026-08-30 16:14:32', NULL, '2026-08-30 17:14:32'),
(29, 'unauthorized', 'E5380849', 'Unknown', 'unknown', 'Unauthorized access attempt with card: E5380849', 'entry', 'unread', '2026-08-30 16:15:29', NULL, '2026-08-30 17:15:29'),
(30, 'unauthorized', 'E5380849', 'Unknown Card', 'unknown', 'Unknown card detected: E5380849', 'entry', 'unread', '2026-08-30 16:15:30', NULL, '2026-08-30 17:15:30'),
(31, 'unauthorized', 'E5380849', 'Kristel Jade P. Albano', 'staff', 'Unauthorized access attempt with card: E5380849', 'entry', 'unread', '2026-08-30 16:16:23', NULL, '2026-08-30 17:16:23'),
(32, 'unauthorized', 'E5380849', 'Kristel Jade P. Albano', 'staff', 'Unauthorized access attempt with card: E5380849', 'entry', 'unread', '2026-08-30 16:18:18', NULL, '2026-08-30 17:18:18'),
(33, 'unauthorized', 'E5380849', 'Kristel Jade P. Albano', 'staff', 'Unauthorized access attempt with card: E5380849', 'entry', 'unread', '2026-08-30 16:18:32', NULL, '2026-08-30 17:18:32'),
(34, 'unauthorized', '656DEF46', 'Kristel Jade P. Albano', 'staff', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-30 16:23:23', NULL, '2026-08-30 17:23:23'),
(35, 'unauthorized', '656DEF46', 'Kristel Jade P. Albano', 'staff', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-30 16:24:17', NULL, '2026-08-30 17:24:17'),
(36, 'unauthorized', '656DEF46', 'Kristel Jade P. Albano', 'staff', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-08-30 16:25:15', NULL, '2026-08-30 17:25:15'),
(37, 'unauthorized', '656DEF46', 'JESSICA A. ABAYA', 'resident', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-09-02 10:31:36', NULL, '2026-09-02 11:31:36'),
(38, 'unauthorized', '59049CDE', 'JESSICA A. ABAYA', 'resident', 'Unauthorized access attempt with card: 59049CDE', 'entry', 'unread', '2026-09-02 10:39:49', NULL, '2026-09-02 11:39:49'),
(39, 'unauthorized', '656DEF46', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 656DEF46', 'entry', 'unread', '2026-09-02 10:46:10', NULL, '2026-09-02 11:46:10'),
(40, 'unauthorized', '656DEF46', 'Unknown Card', 'unknown', 'Unknown card detected: 656DEF46', 'entry', 'unread', '2026-09-02 10:46:12', NULL, '2026-09-02 11:46:12'),
(41, 'unauthorized', 'ED29C906', 'Unknown', 'unknown', 'Unauthorized access attempt with card: ED29C906', 'entry', 'unread', '2026-09-07 16:08:00', NULL, '2026-09-07 17:08:00'),
(42, 'unauthorized', 'ED29C906', 'Unknown Card', 'unknown', 'Unknown card detected: ED29C906', 'entry', 'unread', '2026-09-07 16:08:04', NULL, '2026-09-07 17:08:04'),
(43, 'unauthorized', '25829DEE', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 25829DEE', 'entry', 'unread', '2026-09-07 22:26:43', NULL, '2026-09-07 23:26:43'),
(44, 'unauthorized', '25829DEE', 'Unknown Card', 'unknown', 'Unknown card detected: 25829DEE', 'entry', 'unread', '2026-09-07 22:26:44', NULL, '2026-09-07 23:26:44'),
(45, 'unauthorized', '25829DEE', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 25829DEE', 'entry', 'unread', '2026-09-07 23:51:08', NULL, '2026-09-08 00:51:08'),
(46, 'unauthorized', '25829DEE', 'Unknown Card', 'unknown', 'Unknown card detected: 25829DEE', 'entry', 'unread', '2026-09-07 23:51:10', NULL, '2026-09-08 00:51:10'),
(47, 'unauthorized', '25829DEE', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 25829DEE', 'entry', 'unread', '2026-09-08 00:09:51', NULL, '2026-09-08 01:09:51'),
(48, 'unauthorized', '25829DEE', 'Unknown Card', 'unknown', 'Unknown card detected: 25829DEE', 'entry', 'unread', '2026-09-08 00:09:52', NULL, '2026-09-08 01:09:52'),
(49, 'unauthorized', '25829DEE', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 25829DEE', 'entry', 'unread', '2026-09-08 00:14:01', NULL, '2026-09-08 01:14:01'),
(50, 'unauthorized', '25829DEE', 'Unknown Card', 'unknown', 'Unknown card detected: 25829DEE', 'entry', 'unread', '2026-09-08 00:14:03', NULL, '2026-09-08 01:14:03'),
(51, 'unauthorized', '85241249', 'jeff albano (Visitor)', 'visitor', 'Unauthorized access attempt with card: 85241249', 'entry', 'unread', '2026-09-08 00:53:11', NULL, '2026-09-08 01:53:11'),
(52, 'unauthorized', '85241249', 'jeff albano (Visitor)', 'visitor', 'Expired visitor card: 85241249', 'entry', 'unread', '2026-09-08 00:53:12', NULL, '2026-09-08 01:53:12'),
(53, 'unauthorized', 'E51CF746', 'Unknown', 'unknown', 'Unauthorized access attempt with card: E51CF746', 'entry', 'unread', '2026-09-08 01:08:45', NULL, '2026-09-08 02:08:45'),
(54, 'unauthorized', 'E51CF746', 'Unknown Card', 'unknown', 'Unknown card detected: E51CF746', 'entry', 'unread', '2026-09-08 01:08:46', NULL, '2026-09-08 02:08:46'),
(55, 'unauthorized', 'ED29C906', 'Unknown', 'unknown', 'Unauthorized access attempt with card: ED29C906', 'entry', 'unread', '2026-09-08 01:09:37', NULL, '2026-09-08 02:09:37'),
(56, 'unauthorized', 'ED29C906', 'Unknown Card', 'unknown', 'Unknown card detected: ED29C906', 'entry', 'unread', '2026-09-08 01:09:38', NULL, '2026-09-08 02:09:38'),
(57, 'unauthorized', 'ED29C906', 'Unknown', 'unknown', 'Unauthorized access attempt with card: ED29C906', 'entry', 'unread', '2026-09-08 01:12:36', NULL, '2026-09-08 02:12:36'),
(58, 'unauthorized', 'ED29C906', 'Unknown Card', 'unknown', 'Unknown card detected: ED29C906', 'entry', 'unread', '2026-09-08 01:12:37', NULL, '2026-09-08 02:12:37'),
(59, 'unauthorized', '3583E3EE', 'Inactive Card', 'resident', 'Inactive card detected: 3583E3EE', 'entry', 'unread', '2026-09-08 16:33:03', NULL, '2026-09-08 17:33:03'),
(60, 'unauthorized', 'B447CB06', 'Inactive Card', 'resident', 'Inactive card detected: B447CB06', 'exit', 'unread', '2026-09-08 16:35:25', NULL, '2026-09-08 17:35:25'),
(61, 'unauthorized', '453C2049', 'MHAE P. ALBANO', 'resident', 'Unauthorized access attempt with card: 453C2049', 'entry', 'unread', '2026-09-18 14:38:50', NULL, '2026-09-18 15:38:50'),
(62, 'unauthorized', '453C2049', 'MHAE P. ALBANO (Deactivated)', 'resident', 'Unauthorized access attempt by: MHAE P. ALBANO (Deactivated)', 'entry', 'unread', '2026-09-18 14:38:52', NULL, '2026-09-18 15:38:52'),
(63, 'unauthorized', 'B447CB06', 'JESSICA B. VELASCO', 'resident', 'Unauthorized access attempt with card: B447CB06', 'entry', 'unread', '2026-09-18 14:48:08', NULL, '2026-09-18 15:48:08'),
(64, 'unauthorized', 'B447CB06', 'JESSICA B. VELASCO (Deactivated)', 'resident', 'Unauthorized access attempt by: JESSICA B. VELASCO (Deactivated)', 'exit', 'unread', '2026-09-18 14:48:09', NULL, '2026-09-18 15:48:09'),
(65, 'unauthorized', '453C2049', 'MHAE P. ALBANO', 'resident', 'Unauthorized access attempt with card: 453C2049', 'entry', 'unread', '2026-09-18 14:51:11', NULL, '2026-09-18 15:51:11'),
(66, 'unauthorized', '453C2049', 'MHAE P. ALBANO (Deactivated)', 'resident', 'Unauthorized access attempt by: MHAE P. ALBANO (Deactivated)', 'exit', 'unread', '2026-09-18 14:51:13', NULL, '2026-09-18 15:51:13'),
(67, 'unauthorized', '453C2049', 'MHAE P. ALBANO', 'resident', 'Unauthorized access attempt with card: 453C2049', 'entry', 'unread', '2026-09-18 15:06:58', NULL, '2026-09-18 16:06:58'),
(68, 'unauthorized', '453C2049', 'MHAE P. ALBANO (Deactivated)', 'resident', 'Unauthorized access attempt by: MHAE P. ALBANO (Deactivated)', 'entry', 'unread', '2026-09-18 15:07:00', NULL, '2026-09-18 16:07:00'),
(69, 'unauthorized', 'B447CB06', 'JESSICA B. VELASCO', 'resident', 'Unauthorized access attempt with card: B447CB06', 'entry', 'unread', '2026-09-18 15:08:42', NULL, '2026-09-18 16:08:42'),
(70, 'unauthorized', 'B447CB06', 'JESSICA B. VELASCO (Deactivated)', 'resident', 'Unauthorized access attempt by: JESSICA B. VELASCO (Deactivated)', 'exit', 'unread', '2026-09-18 15:08:43', NULL, '2026-09-18 16:08:43');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `request_id` int NOT NULL,
  `student_id` int NOT NULL,
  `student_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `student_id_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci,
  `status` enum('pending','approved','denied','completed','expired') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `reset_token` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `admin_response` text COLLATE utf8mb4_general_ci,
  `requested_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`request_id`, `student_id`, `student_name`, `student_id_number`, `username`, `email`, `reason`, `status`, `reset_token`, `token_expires_at`, `admin_response`, `requested_at`, `responded_at`, `created_at`, `updated_at`) VALUES
(4, 3, 'JESSICA B. VELASCO', 'STU-2026-8498', 'Jessy', 'velascojessyca01@gmail.com', 'i forgot', 'expired', 'e30a863c04486588a0f4763fc3bb89b8927e1e874363cf6f78b6c3c5040f65e5', '2026-09-07 22:33:25', '', '2026-09-06 22:18:45', '2026-09-06 22:33:25', '2026-09-06 14:18:45', '2026-09-18 08:50:37'),
(5, 3, 'JESSICA B. VELASCO', 'STU-2026-8498', 'Jessy', 'velascojessyca01@gmail.com', 'i forgot my password', 'expired', '77d36888c8d28751ab69c6c31a60216e0e9700b67b27eb78c3843f2206b92b68', '2026-09-08 10:40:15', '', '2026-09-07 10:39:21', '2026-09-07 10:40:15', '2026-09-07 02:39:21', '2026-09-18 08:50:37');

-- --------------------------------------------------------

--
-- Table structure for table `puzzle_attempts`
--

CREATE TABLE `puzzle_attempts` (
  `attempt_id` int NOT NULL,
  `user_id` int NOT NULL,
  `user_type` enum('staff','student') COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `resident_profiles`
--

CREATE TABLE `resident_profiles` (
  `profile_id` int NOT NULL,
  `user_id` int NOT NULL,
  `date_registered` date DEFAULT NULL,
  `course` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `gender_other` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `age` int DEFAULT NULL,
  `birth_no` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_siblings` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `scholarship` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `allowance_source` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_last` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_address` text COLLATE utf8mb4_general_ci,
  `cultural_origin` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `home_address` text COLLATE utf8mb4_general_ci,
  `cp_no` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `religion` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dialect` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `emergency_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `emergency_relationship` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `emergency_address` text COLLATE utf8mb4_general_ci,
  `emergency_contact` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `father_education` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mother_education` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `father_occupation` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mother_occupation` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parents_marital_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `civil_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `former_boarding_years` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plan_transfer` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plan_transfer_yes` text COLLATE utf8mb4_general_ci,
  `plan_transfer_no` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resident_profiles`
--

INSERT INTO `resident_profiles` (`profile_id`, `user_id`, `date_registered`, `course`, `year_level`, `gender`, `gender_other`, `birth_date`, `age`, `birth_no`, `no_siblings`, `scholarship`, `allowance_source`, `school_last`, `school_address`, `cultural_origin`, `home_address`, `cp_no`, `religion`, `dialect`, `emergency_name`, `emergency_relationship`, `emergency_address`, `emergency_contact`, `created_at`, `updated_at`, `father_education`, `mother_education`, `father_occupation`, `mother_occupation`, `parents_marital_status`, `civil_status`, `former_boarding_years`, `plan_transfer`, `plan_transfer_yes`, `plan_transfer_no`) VALUES
(1, 1, '2026-08-07', 'BAELS', '2nd Year', 'Female', NULL, '2007-11-14', 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Aurora Alicia Isabela', '09059421141', 'Catholic', 'Tagalog', 'Jefferson Albano', 'Father', 'Aurora Alicia Isabela', '09539976519', '2026-08-07 09:40:16', '2026-08-23 11:38:25', 'COLLEGE GRADUATE', 'HIGH SCHOOL GRADUATE', 'FARMER', 'HOUSEWIFE', 'LIVING TOGETHER', 'Single', NULL, NULL, NULL, NULL),
(2, 2, '2026-08-23', 'BSDSA', '4th Year', 'Female', NULL, '2004-10-30', 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ALICIA', NULL, 'CATHOLIC', 'TAGALOG', 'DAVID P. ALBANO', 'FATHER', 'ALICIA', '09584217784', '2026-08-23 11:15:51', '2026-08-23 11:15:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, '2026-09-02', 'BSIT', '4th Year', 'Female', NULL, '2002-01-10', 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CANIGUING ECHAGUE ISABELA', NULL, 'ROMAN CATHOLIC', 'TAGALOG', 'ROBERT R. VELASCO', 'FATHER', 'CANIGUING ECHAGUE ISABELA', '09058602804', '2026-09-02 02:03:48', '2026-09-02 02:03:48', 'ELEMENTARY UNDERGRADUATE', 'ELEMENTARY UNDERGRADUATE', 'FARMER', 'HOUSEWIFE', 'Living Together', 'Single', NULL, NULL, NULL, NULL),
(4, 4, '2026-09-02', 'BSIT', '4th Year', 'Female', NULL, '2004-04-27', 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SAN FABIAN ECHAGUE ISABELA', NULL, 'CATHOLIC', 'TAGALOG', 'JERRYMIE A. ABAYA', 'FATHER', 'SAN FABIAN ECHAGUE ISABELA', '09655577201', '2026-09-02 02:25:25', '2026-09-02 02:25:25', 'VOCATIONAL', 'ELEMENTARY GRADUATE', 'FARMER', 'HOUSEWIFE', 'Living Together', 'Single', NULL, NULL, NULL, NULL),
(5, 5, '2026-09-06', 'BSIT', '4th Year', 'Female', NULL, '2004-04-17', 22, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SAN FABIAN', NULL, 'CATHOLIC', 'TAGALOG', 'JERRYMIE A. ABAYA', 'FATHER', 'SAN FABIAN', '09885776255', '2026-09-06 11:36:08', '2026-09-06 11:36:08', 'VOCATIONAL', 'ELEM UNDERGRADUATE', 'FARMER', 'HOUSEWIFE', 'Living Together', 'Single', NULL, NULL, NULL, NULL),
(6, 6, '2026-09-07', 'BSIT', '4th Year', 'Female', NULL, '2004-09-17', 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SAN ANTONIO UGAD ECHAGUE, ISABELA', NULL, 'CATHOLIC', 'TAGALOG', 'ALFONSO AQUINO', 'FATHER', 'SAN ANTONIO UGAD ECHAGUE, ISABELA', '09488216479', '2026-09-07 06:11:51', '2026-09-07 06:11:51', 'HIGH SCHOOL UNDERGRADUATE', 'ELEMENTARY UNDERGRADUATE', 'CONSTRUCTION WORKER', 'VENDOR', 'Living Together', 'Single', NULL, NULL, NULL, NULL),
(7, 7, '2026-09-08', 'BAELS', '2nd Year', 'Female', NULL, '2007-11-14', 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'FORTUNE EAST AURORA ALICIA ISABELA', NULL, 'CATHOLIC', 'TAGALOG', 'JEFFERSON B. ALBANO', 'FATHER', 'FORTUNE EAST AURORA ALICIA ISABELA', '09539976519', '2026-09-08 03:16:08', '2026-09-08 03:16:08', 'VOCATIONAL', 'VOCATIONAL', 'ELECTRICIAN', 'OFW', 'Living Together', 'Single', NULL, NULL, NULL, NULL),
(8, 8, '2026-09-08', 'BSIT', '4th Year', 'Female', NULL, '2004-10-07', 21, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AURORA ALICIA', NULL, 'CATHOLIC', 'TAGALOG', 'DWAYNE P. ALBANO', 'FATHER', 'AURORA ALICIA', '09558201193', '2026-09-08 08:19:58', '2026-09-08 08:19:58', 'VOCATIONAL', 'VOCATIONAL', 'ELECTRICIAN', 'OFW', 'Living Together', 'Single', NULL, NULL, NULL, NULL),
(9, 14, '2026-09-14', 'BSED', '2nd Year', 'Female', '', '2006-03-07', 20, '2', '1', 'NONE', 'EMWCUEY', 'ALICSW', 'CEJCIUC', 'VDVRVRVRV', 'CMKEAFCESFC', NULL, 'VRBRBVRV', 'VRVRV', 'LINE P. ALBANO', 'FATDUWI', 'DCVEFCEDVIK', '03254789789', '2026-09-14 03:24:06', '2026-09-14 03:24:06', 'VOCATIOANL', 'DSKCJIEV', 'VJRIFVIR', 'VRKJVIR', 'Living Together', 'Single', '1 YEARS', 'Yes', 'NA', ''),
(10, 15, '2026-09-14', 'BSED', '2nd Year', 'Female', '', '2006-03-07', 20, '2', '1', 'NONE', 'EMWCUEY', 'ALICSW', 'CEJCIUC', 'VDVRVRVRV', 'CMKEAFCESFC', NULL, 'VRBRBVRV', 'VRVRV', 'LINE P. ALBANO', 'FATDUWI', 'DCVEFCEDVIK', '03254789789', '2026-09-14 03:25:44', '2026-09-14 03:25:44', 'VOCATIOANL', 'DSKCJIEV', 'VJRIFVIR', 'VRKJVIR', 'Living Together', 'Single', '1 YEARS', 'Yes', 'NA', '');

-- --------------------------------------------------------

--
-- Table structure for table `rfid_cards`
--

CREATE TABLE `rfid_cards` (
  `card_id` int NOT NULL,
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `card_type` enum('resident','staff','visitor') COLLATE utf8mb4_general_ci DEFAULT 'resident',
  `issued_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `validity_end` date DEFAULT NULL,
  `status` enum('active','deactivated','lost','expired') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `visitor_name` text COLLATE utf8mb4_general_ci,
  `visitor_phone` text COLLATE utf8mb4_general_ci,
  `purpose_of_visit` text COLLATE utf8mb4_general_ci,
  `resident_visited` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `encryption_key` varchar(64) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_cards`
--

INSERT INTO `rfid_cards` (`card_id`, `card_uid`, `user_id`, `card_type`, `issued_date`, `expiry_date`, `validity_end`, `status`, `visitor_name`, `visitor_phone`, `purpose_of_visit`, `resident_visited`, `created_at`, `updated_at`, `encryption_key`, `is_encrypted`) VALUES
(2, '85241249', 1, 'visitor', '2026-08-07', NULL, NULL, 'deactivated', 'jeff albano', '09918256154', 'VISIT', 1, '2026-08-07 09:51:23', '2026-08-15 02:42:42', NULL, 0),
(8, '453C2049', 2, 'resident', '2026-08-30', '2027-08-30', NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-30 08:13:51', '2026-09-18 07:08:07', NULL, 0),
(10, 'B447CB06', 3, 'resident', '2026-09-02', '2027-09-02', NULL, 'active', NULL, NULL, NULL, NULL, '2026-09-02 02:11:48', '2026-09-18 07:59:11', NULL, 0),
(16, '35D3BBEE', 6, 'resident', '2026-09-07', '2027-09-07', NULL, 'active', NULL, NULL, NULL, NULL, '2026-09-07 12:39:31', '2026-09-07 12:39:31', NULL, 0),
(17, '253D30EE', 5, 'resident', '2026-09-07', '2027-09-07', NULL, 'active', NULL, NULL, NULL, NULL, '2026-09-07 12:40:27', '2026-09-07 12:40:27', NULL, 0),
(23, '25829DEE', 1, 'staff', '2026-09-08', '2027-09-08', NULL, 'active', NULL, NULL, NULL, NULL, '2026-09-07 16:15:35', '2026-09-07 16:15:35', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `saved_logins`
--

CREATE TABLE `saved_logins` (
  `saved_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `device_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_logins`
--

INSERT INTO `saved_logins` (`saved_id`, `admin_id`, `device_name`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `is_active`) VALUES
(1, 1, 'Chrome Browser', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 20:10:40', '2026-08-15 13:24:38', 1),
(2, 1, 'Chrome Browser', '100.64.0.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 19:59:42', '2026-08-30 15:07:57', 1),
(3, 1, 'Chrome Browser', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 19:59:44', '2026-08-30 15:08:20', 1),
(4, 1, 'Chrome Browser', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 19:59:49', '2026-09-02 13:52:30', 1),
(5, 1, 'Chrome Browser', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 19:59:50', '2026-09-02 08:37:17', 1),
(6, 1, 'Chrome Browser', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 19:59:52', '2026-08-30 15:09:45', 1),
(7, 1, 'Chrome Browser', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-22 19:59:56', '2026-09-02 08:37:51', 1),
(8, 1, 'Chrome Browser', '100.64.0.10', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 10:57:25', '2026-08-31 20:07:05', 1),
(9, 1, 'Chrome Browser', '100.64.0.9', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 10:57:31', '2026-08-27 10:57:31', 1),
(10, 1, 'Chrome Browser', '100.64.0.2', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 10:58:06', '2026-08-27 10:58:06', 1),
(11, 1, 'Chrome Browser', '100.64.0.7', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 10:58:07', '2026-08-27 10:58:07', 1),
(12, 1, 'Chrome Browser', '100.64.0.3', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 10:59:15', '2026-08-27 10:59:17', 1),
(13, 1, 'Chrome Browser', '100.64.0.20', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 11:01:26', '2026-08-27 11:01:26', 1),
(14, 1, 'Chrome Browser', '100.64.0.14', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 11:14:49', '2026-08-27 11:18:02', 1),
(15, 1, 'Chrome Browser', '100.64.0.13', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 11:14:53', '2026-08-27 11:17:32', 1),
(16, 1, 'Chrome Browser', '100.64.0.21', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-27 11:16:39', '2026-08-27 11:16:39', 1),
(17, 1, 'Chrome Browser', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 15:00:58', '2026-08-30 15:02:14', 1),
(18, 1, 'Chrome Browser', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 15:02:14', '2026-08-30 15:02:14', 1),
(19, 1, 'Chrome Browser', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 15:07:59', '2026-09-02 14:37:56', 1),
(20, 1, 'Chrome Browser', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-30 15:08:03', '2026-09-02 14:38:08', 1),
(21, 1, 'Chrome Browser', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-09-02 08:37:09', '2026-09-02 08:37:19', 1),
(22, 1, 'Chrome Browser', '100.64.0.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 11:34:45', '2026-09-04 11:34:45', 1),
(23, 1, 'Chrome Browser', '100.64.0.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 11:34:47', '2026-09-04 11:34:47', 1),
(24, 1, 'Chrome Browser', '100.64.0.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 11:53:11', '2026-09-18 16:29:26', 1),
(25, 1, 'Chrome Browser', '100.64.0.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 11:53:27', '2026-09-04 11:53:27', 1),
(26, 1, 'Chrome Browser', '100.64.0.8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 11:11:29', '2026-09-06 23:16:07', 1),
(27, 1, 'Chrome Browser', '100.64.0.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 11:11:36', '2026-09-06 23:16:11', 1),
(28, 1, 'Chrome Browser', '100.64.0.13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 23:09:20', '2026-09-06 23:09:20', 1),
(29, 1, 'Chrome Browser', '100.64.0.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 23:10:25', '2026-09-06 23:10:25', 1),
(30, 1, 'Chrome Browser', '100.64.0.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 23:12:42', '2026-09-06 23:16:09', 1),
(31, 1, 'Chrome Browser', '100.64.0.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 23:12:53', '2026-09-06 23:16:08', 1),
(32, 1, 'Chrome Browser', '100.64.0.3', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 23:15:58', '2026-09-18 16:29:33', 1),
(33, 1, 'Chrome Browser', '100.64.0.5', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-06 23:16:04', '2026-09-06 23:16:04', 1),
(34, 1, 'Chrome Browser', '100.64.0.18', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 01:07:08', '2026-09-08 01:07:08', 1),
(35, 1, 'Chrome Browser', '100.64.0.17', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-08 01:07:09', '2026-09-08 01:07:09', 1),
(36, 1, 'Chrome Browser', '100.64.0.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-18 16:29:09', '2026-09-18 16:29:13', 1);

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `log_id` int NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `card_uid` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`log_id`, `event_type`, `card_uid`, `user_name`, `details`, `timestamp`) VALUES
(1, 'unauthorized_access', '85241249', 'Visitor (Expired)', 'Expired visitor card: 85241249 | Type: entry', '2026-08-07 18:14:23'),
(2, 'unauthorized_access', '85241249', 'Visitor (Expired)', 'Expired visitor card: 85241249 | Type: entry', '2026-08-07 18:14:39'),
(3, 'unauthorized_access', '65661B49', 'Unknown Card', 'Unknown card detected: 65661B49 | Type: entry', '2026-08-07 18:23:04'),
(4, 'unauthorized_access', 'E51CF746', 'Unknown Card', 'Unknown card detected: E51CF746 | Type: entry', '2026-08-14 07:57:29'),
(5, 'unauthorized_access', 'E51CF746', 'Unknown Card', 'Unknown card detected: E51CF746 | Type: entry', '2026-08-14 08:34:17'),
(6, 'unauthorized_access', '656DEF46', 'Unknown Card', 'Unknown card detected: 656DEF46 | Type: entry', '2026-08-15 13:55:57'),
(7, 'unauthorized_access', '656DEF46', 'Unknown Card', 'Unknown card detected: 656DEF46 | Type: entry', '2026-08-15 16:52:59'),
(8, 'unauthorized_access', 'E5380849', 'Unknown Card', 'Unknown card detected: E5380849 | Type: entry', '2026-08-30 15:33:15'),
(9, 'unauthorized_access', '656DEF46', 'Unknown Card', 'Unknown card detected: 656DEF46 | Type: entry', '2026-08-30 15:48:25'),
(10, 'unauthorized_access', '656DEF46', 'Unknown Card', 'Unknown card detected: 656DEF46 | Type: entry', '2026-08-30 15:49:45'),
(11, 'unauthorized_access', 'B447CB06', 'Unknown Card', 'Unknown card detected: B447CB06 | Type: entry', '2026-08-30 16:01:42'),
(12, 'unauthorized_access', '656DEF46', 'Unknown Card', 'Unknown card detected: 656DEF46 | Type: entry', '2026-08-30 16:02:03'),
(13, 'unauthorized_access', 'D59A0F49', 'Unknown Card', 'Unknown card detected: D59A0F49 | Type: entry', '2026-08-30 16:02:21'),
(14, 'unauthorized_access', 'D59A0F49', 'Unknown Card', 'Unknown card detected: D59A0F49 | Type: entry', '2026-08-30 16:10:23'),
(15, 'unauthorized_access', '0A1FD006', 'Unknown Card', 'Unknown card detected: 0A1FD006 | Type: entry', '2026-08-30 16:13:06'),
(16, 'unauthorized_access', 'E5380849', 'Unknown Card', 'Unknown card detected: E5380849 | Type: entry', '2026-08-30 16:15:30'),
(17, 'unauthorized_access', '656DEF46', 'Unknown Card', 'Unknown card detected: 656DEF46 | Type: entry', '2026-09-02 10:46:12'),
(18, 'unauthorized_access', 'ED29C906', 'Unknown Card', 'Unknown card detected: ED29C906 | Type: entry', '2026-09-07 16:08:04'),
(19, 'unauthorized_access', '25829DEE', 'Unknown Card', 'Unknown card detected: 25829DEE | Type: entry', '2026-09-07 22:26:44'),
(20, 'unauthorized_access', '25829DEE', 'Unknown Card', 'Unknown card detected: 25829DEE | Type: entry', '2026-09-07 23:51:10'),
(21, 'unauthorized_access', '25829DEE', 'Unknown Card', 'Unknown card detected: 25829DEE | Type: entry', '2026-09-08 00:09:52'),
(22, 'unauthorized_access', '25829DEE', 'Unknown Card', 'Unknown card detected: 25829DEE | Type: entry', '2026-09-08 00:14:03'),
(23, 'unauthorized_access', '85241249', 'jeff albano (Visitor)', 'Expired visitor card: 85241249 | Type: entry', '2026-09-08 00:53:12'),
(24, 'unauthorized_access', 'E51CF746', 'Unknown Card', 'Unknown card detected: E51CF746 | Type: entry', '2026-09-08 01:08:46'),
(25, 'unauthorized_access', 'ED29C906', 'Unknown Card', 'Unknown card detected: ED29C906 | Type: entry', '2026-09-08 01:09:38'),
(26, 'unauthorized_access', 'ED29C906', 'Unknown Card', 'Unknown card detected: ED29C906 | Type: entry', '2026-09-08 01:12:37'),
(27, 'unauthorized_access', '3583E3EE', 'Inactive Card', 'Inactive card detected: 3583E3EE | Type: entry', '2026-09-08 16:33:03'),
(28, 'unauthorized_access', 'B447CB06', 'Inactive Card', 'Inactive card detected: B447CB06 | Type: exit', '2026-09-08 16:35:25'),
(29, 'unauthorized_access', '453C2049', 'MHAE P. ALBANO (Deactivated)', 'Unauthorized access attempt by: MHAE P. ALBANO (Deactivated) | Type: entry', '2026-09-18 14:38:52'),
(30, 'unauthorized_access', 'B447CB06', 'JESSICA B. VELASCO (Deactivated)', 'Unauthorized access attempt by: JESSICA B. VELASCO (Deactivated) | Type: exit', '2026-09-18 14:48:09'),
(31, 'unauthorized_access', '453C2049', 'MHAE P. ALBANO (Deactivated)', 'Unauthorized access attempt by: MHAE P. ALBANO (Deactivated) | Type: exit', '2026-09-18 14:51:13'),
(32, 'unauthorized_access', '453C2049', 'MHAE P. ALBANO (Deactivated)', 'Unauthorized access attempt by: MHAE P. ALBANO (Deactivated) | Type: entry', '2026-09-18 15:07:00'),
(33, 'unauthorized_access', 'B447CB06', 'JESSICA B. VELASCO (Deactivated)', 'Unauthorized access attempt by: JESSICA B. VELASCO (Deactivated) | Type: exit', '2026-09-18 15:08:43');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `position` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_audit_logs`
--

CREATE TABLE `staff_audit_logs` (
  `log_id` int NOT NULL,
  `staff_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_logs`
--

CREATE TABLE `staff_logs` (
  `id` int NOT NULL,
  `staff_id` int DEFAULT NULL,
  `action` varchar(50) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_users`
--

CREATE TABLE `staff_users` (
  `staff_id` int NOT NULL,
  `staff_id_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `department` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `card_uid` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `login_attempts` int DEFAULT '0',
  `login_blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_users`
--

INSERT INTO `staff_users` (`staff_id`, `staff_id_number`, `full_name`, `email`, `department`, `card_uid`, `phone`, `password_hash`, `avatar`, `created_at`, `updated_at`, `status`, `last_login`, `is_active`, `login_attempts`, `login_blocked_until`) VALUES
(1, 'STAFF-001', 'Mylene C. Samiling', 'mylenesamiling@gmail.com', 'Domitory Management', '25829DEE', '09558271369', '$2y$10$cqgLe/JXVWz2qTBcVO8Xf.pESLHgBzdvGWRopOzMDpYhdrTAeY3vu', 'uploads/staff_photos/staff_1_1786106233.jpg', '2026-08-07 12:37:02', '2026-09-08 09:14:53', 'active', NULL, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student_audit_logs`
--

CREATE TABLE `student_audit_logs` (
  `log_id` int NOT NULL,
  `student_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `details` text COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_concerns`
--

CREATE TABLE `student_concerns` (
  `concern_id` int NOT NULL,
  `student_id` int NOT NULL,
  `student_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `student_id_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `room_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `subject` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `category` enum('maintenance','security','cleanliness','noise','other') COLLATE utf8mb4_general_ci DEFAULT 'other',
  `priority` enum('low','medium','high') COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `status` enum('pending','in_progress','resolved','closed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `admin_response` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_concerns`
--

INSERT INTO `student_concerns` (`concern_id`, `student_id`, `student_name`, `student_id_number`, `room_number`, `subject`, `message`, `category`, `priority`, `status`, `admin_response`, `created_at`, `updated_at`) VALUES
(1, 1, 'Kristel Jade P. Albano', 'STU-2026-3808', '1', 'mabaho', 'dwqfdw3g', 'maintenance', 'medium', 'resolved', 'ok', '2026-08-07 13:26:19', '2026-08-08 08:39:03');

-- --------------------------------------------------------

--
-- Table structure for table `student_registration_logs`
--

CREATE TABLE `student_registration_logs` (
  `log_id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text,
  `performed_by` varchar(100) DEFAULT 'student',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `student_registration_logs`
--

INSERT INTO `student_registration_logs` (`log_id`, `user_id`, `action`, `details`, `performed_by`, `ip_address`, `created_at`) VALUES
(1, 9, 'rejected', 'Registration rejected: CHELLS P. ALBANO (STU-2026-4262) - Reason: ', 'Chellsea Albano', NULL, '2026-09-14 03:11:43'),
(2, 10, 'rejected', 'Registration rejected: CHELLS P. ALBANO (STU-2026-4373) - Reason: ', 'Chellsea Albano', NULL, '2026-09-14 03:15:51'),
(3, 11, 'rejected', 'Registration rejected: CHELLS P. ALBANO (STU-2026-8570) - Reason: ', 'Chellsea Albano', NULL, '2026-09-14 03:15:53'),
(4, 12, 'rejected', 'Registration rejected: CHELLS P. ALBANO (STU-2026-2126) - Reason: ', 'Chellsea Albano', NULL, '2026-09-14 03:15:56'),
(5, 13, 'rejected', 'Registration rejected: CHELLS P. ALBANO (STU-2026-9180) - Reason: ', 'Chellsea Albano', NULL, '2026-09-14 03:20:46'),
(6, 14, 'self_registration', 'Student self-registered with ID: STU-2026-1801', 'student', '100.64.0.2', '2026-09-14 03:24:06'),
(7, 14, 'approved', 'Registration approved: CHELLS P. ALBANO (STU-2026-1801)', 'Chellsea Albano', NULL, '2026-09-14 03:25:14'),
(8, 15, 'self_registration', 'Student self-registered with ID: STU-2026-6451', 'student', '100.64.0.10', '2026-09-14 03:25:44'),
(9, 14, 'portal_created', 'Student created portal account', 'student', NULL, '2026-09-14 03:27:29'),
(10, 15, 'rejected', 'Registration rejected: CHELLS P. ALBANO (STU-2026-6451) - Reason: ', 'Chellsea Albano', NULL, '2026-09-14 03:28:24');

-- --------------------------------------------------------

--
-- Table structure for table `student_users`
--

CREATE TABLE `student_users` (
  `student_id` int NOT NULL,
  `student_id_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `course` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `year_level` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `room_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `resident_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `login_attempts` int DEFAULT '0',
  `login_blocked_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_users`
--

INSERT INTO `student_users` (`student_id`, `student_id_number`, `full_name`, `username`, `course`, `year_level`, `email`, `profile_photo`, `password_hash`, `phone`, `room_number`, `resident_id`, `is_active`, `created_at`, `updated_at`, `login_attempts`, `login_blocked_until`) VALUES
(2, 'STU-2026-7950', 'MHAE P. ALBANO', 'albanomhae2004@gamil.com', 'BSDSA', '4th Year', 'albanomhae2004@gamil.com', NULL, '$2y$12$6vRB5IKXr/eVSu1IhesgE.Uv57j0H0f.v3XNoBtvP79bnrM/uo4HW', '09762804434', '', 2, 1, '2026-08-30 07:30:33', '2026-08-30 09:03:55', 0, NULL),
(3, 'STU-2026-8498', 'JESSICA B. VELASCO', 'Jessy', 'BSIT', '4th Year', 'velascojessyca01@gmail.com', NULL, '$2y$12$6w3AuM2QUmlLibGSQOaxTOogM.NV8.ZChedsGp6grmGEZSP1y2kiu', '09058602804', '5', 3, 1, '2026-09-02 02:07:37', '2026-09-02 02:07:37', 0, NULL),
(6, 'STU-2026-4122', 'JESSICA A. ABAYA', 'IKANG', 'BSIT', '4th Year', 'abayajessica25@gmail.com', NULL, '$2y$12$G9c6uZW54RnDXaiwNhQZjOsa8ceeRASeHViCA375fKfABoBPrcE8.', '09558201197', '3', 0, 1, '2026-09-07 12:52:44', '2026-09-07 12:52:44', 0, NULL),
(7, 'STU-2026-8335', 'FRANCISCA P. AQUINO', 'ISKA', 'BSIT', '4th Year', 'franciscaperezaquino@gmail.com', NULL, '$2y$12$OoBBO4ZlBTgomXRQG7QE9..h8f.fcTXiSPAOETS2JRSlUjsOiU3Ei', '09488216479', '2', 0, 1, '2026-09-08 07:38:55', '2026-09-08 07:38:55', 0, NULL),
(8, 'STU-2026-6114', 'CHELLS P. ALBANO', 'CHELLS', 'BSIT', '4th Year', 'chellsalbano@gmail.com', NULL, '$2y$12$TfU.nH56kZDtFOlHGm5BUuYSDziclNJ.5guBwoti95LfyN7W8OTzS', '09558201193', '4', 0, 1, '2026-09-08 08:41:15', '2026-09-08 08:41:15', 0, NULL),
(9, 'STU-2026-1801', 'CHELLS P. ALBANO', 'chellspalbano', 'BSED', '2nd Year', 'chells30@gmail.com', NULL, '$2y$12$fr7HvL/bjgXVHRgiMjdVduuP2JMXg/pTomKmrwcouM4byaa4viMtS', '0955862114', '', 14, 1, '2026-09-14 03:27:29', '2026-09-14 03:27:29', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `system_configuration`
--

CREATE TABLE `system_configuration` (
  `config_id` int NOT NULL,
  `config_key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `student_id` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `room_number` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contact_number` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','active','inactive','deleted') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `approval_status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_general_ci,
  `portal_email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `portal_password` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `portal_created_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Path to profile photo',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `student_id`, `room_number`, `contact_number`, `email`, `password_hash`, `status`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`, `portal_email`, `portal_password`, `portal_created_at`, `created_at`, `updated_at`, `profile_photo`, `is_active`) VALUES
(1, 'Kristel Jade P. Albano', 'STU-2026-8234', '1', '09539976519', 'albanokristel14@gmail.com', NULL, 'deleted', 'rejected', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-07 09:40:16', '2026-09-14 03:04:48', 'uploads/resident_photos/1786098269_1.jpg', 1),
(2, 'MHAE P. ALBANO', 'STU-2026-7159', '1', '09762804434', 'albanomhae2004@gamil.com', NULL, 'active', 'approved', 1, '2026-09-14 03:04:46', NULL, NULL, NULL, NULL, '2026-08-23 11:15:51', '2026-09-14 03:04:46', 'uploads/resident_photos/1788696508_2.png', 1),
(3, 'JESSICA B. VELASCO', 'STU-2026-6273', '5', '09058602804', 'jessica.b..velasco@isu.edu.ph', NULL, 'active', 'approved', 1, '2026-09-14 03:04:46', NULL, NULL, NULL, NULL, '2026-09-02 02:03:48', '2026-09-14 03:04:46', 'uploads/resident_photos/1788704062_3.jpg', 1),
(4, 'JESSICA A. ABAYA', 'STU-2026-0273', '5', '09554479081', 'jessica.a..abaya@isu.edu.ph', NULL, 'deleted', 'rejected', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-02 02:25:25', '2026-09-14 03:04:48', 'uploads/resident_photos/1788453524_4.png', 1),
(5, 'JESSICA A. ABAYA', 'STU-2026-6305', '3', '09558201197', 'jessica.a..abaya@isu.edu.ph', NULL, 'active', 'approved', 1, '2026-09-14 03:04:46', NULL, NULL, NULL, NULL, '2026-09-06 11:36:08', '2026-09-14 03:04:46', 'uploads/resident_photos/1788696490_5.png', 1),
(6, 'FRANCISCA P. AQUINO', 'STU-2026-0069', '2', '09488216479', 'francisca.p..aquino@isu.edu.ph', NULL, 'active', 'approved', 1, '2026-09-14 03:04:46', NULL, NULL, NULL, NULL, '2026-09-07 06:11:51', '2026-09-14 03:04:46', NULL, 1),
(7, 'KRISTEL JADE P. ALBANO', 'STU-2026-6531', '4', '0965481247523', 'kristel.jade.p..albano@isu.edu.ph', NULL, 'deleted', 'rejected', NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-08 03:16:08', '2026-09-14 03:04:48', NULL, 1),
(8, 'CHELLS P. ALBANO', 'STU-2026-9781', '4', '09558201193', 'chells.p..albano@isu.edu.ph', NULL, 'deleted', 'approved', 1, '2026-09-14 03:04:46', NULL, NULL, NULL, NULL, '2026-09-08 08:19:58', '2026-09-14 03:06:59', NULL, 1),
(9, 'CHELLS P. ALBANO', 'STU-2026-4262', NULL, '09753662206', 'chells.p..albano@student.isu.edu.ph', NULL, 'deleted', 'rejected', 1, '2026-09-14 11:11:43', '', NULL, NULL, NULL, '2026-09-14 03:09:42', '2026-09-14 03:20:39', 'uploads/resident_photos/1789355382_STU-2026-4262.jpg', 1),
(10, 'CHELLS P. ALBANO', 'STU-2026-4373', NULL, '09753662206', 'chells.p..albano@student.isu.edu.ph', NULL, 'deleted', 'rejected', 1, '2026-09-14 11:15:51', '', NULL, NULL, NULL, '2026-09-14 03:11:53', '2026-09-14 03:20:36', 'uploads/resident_photos/1789355513_STU-2026-4373.jpg', 1),
(11, 'CHELLS P. ALBANO', 'STU-2026-8570', NULL, '09753662206', 'chells.p..albano@student.isu.edu.ph', NULL, 'deleted', 'rejected', 1, '2026-09-14 11:15:53', '', NULL, NULL, NULL, '2026-09-14 03:12:27', '2026-09-14 03:20:33', 'uploads/resident_photos/1789355547_STU-2026-8570.jpg', 1),
(12, 'CHELLS P. ALBANO', 'STU-2026-2126', NULL, '09854612354', 'chells.p..albano@student.isu.edu.ph', NULL, 'deleted', 'rejected', 1, '2026-09-14 11:15:56', '', NULL, NULL, NULL, '2026-09-14 03:14:37', '2026-09-14 03:20:30', 'uploads/resident_photos/1789355677_STU-2026-2126.jpg', 1),
(13, 'CHELLS P. ALBANO', 'STU-2026-9180', NULL, '09854612354', 'chells.p..albano@student.isu.edu.ph', NULL, 'deleted', 'rejected', 1, '2026-09-14 11:20:46', '', NULL, NULL, NULL, '2026-09-14 03:18:59', '2026-09-14 03:20:51', 'uploads/resident_photos/1789355939_STU-2026-9180.jpg', 1),
(14, 'CHELLS P. ALBANO', 'STU-2026-1801', '4', '0955862114', 'chells.p..albano@student.isu.edu.ph', NULL, 'active', 'approved', 1, '2026-09-14 11:25:14', NULL, 'chells30@gmail.com', '$2y$12$fr7HvL/bjgXVHRgiMjdVduuP2JMXg/pTomKmrwcouM4byaa4viMtS', '2026-09-14 11:27:29', '2026-09-14 03:24:06', '2026-09-14 03:52:30', 'uploads/resident_photos/1789356246_STU-2026-1801.jpg', 1),
(15, 'CHELLS P. ALBANO', 'STU-2026-6451', NULL, '0955862114', 'chells.p..albano@student.isu.edu.ph', NULL, 'deleted', 'rejected', 1, '2026-09-14 11:28:24', '', NULL, NULL, NULL, '2026-09-14 03:25:44', '2026-09-14 03:30:30', 'uploads/resident_photos/1789356344_STU-2026-6451.jpg', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `setting_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `setting_key` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `setting_value` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
  `visitor_log_id` int NOT NULL,
  `visitor_name` text COLLATE utf8mb4_general_ci,
  `phone` text COLLATE utf8mb4_general_ci,
  `relationship` text COLLATE utf8mb4_general_ci,
  `visitor_phone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visitor_contact` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `resident_visited` int NOT NULL,
  `purpose_of_visit` text COLLATE utf8mb4_general_ci,
  `temporary_card_uid` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `validity_start` date DEFAULT NULL,
  `validity_end` date DEFAULT NULL,
  `entry_timestamp` datetime DEFAULT NULL,
  `exit_timestamp` datetime DEFAULT NULL,
  `access_status` enum('pending','granted','denied','exited') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_encrypted` tinyint(1) DEFAULT '0' COMMENT '1 = encrypted, 0 = plain text',
  `encrypted_at` datetime DEFAULT NULL COMMENT 'When data was encrypted',
  `encryption_version` varchar(20) COLLATE utf8mb4_general_ci DEFAULT 'AES-256-CBC' COMMENT 'Encryption algorithm used'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitor_logs`
--

INSERT INTO `visitor_logs` (`visitor_log_id`, `visitor_name`, `phone`, `relationship`, `visitor_phone`, `visitor_contact`, `resident_visited`, `purpose_of_visit`, `temporary_card_uid`, `validity_start`, `validity_end`, `entry_timestamp`, `exit_timestamp`, `access_status`, `created_at`, `updated_at`, `is_encrypted`, `encrypted_at`, `encryption_version`) VALUES
(1, 'jeff albano', '09918256154', 'ANAK', NULL, NULL, 1, 'VISIT', '85241249', '2026-08-07', '2026-08-14', '2026-08-14 08:34:07', '2026-08-15 10:38:23', 'granted', '2026-08-07 10:21:38', '2026-09-06 07:57:38', 1, '2026-09-06 07:57:38', 'AES-256-CBC');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_encryption_stats`
-- (See below for the actual view)
--
CREATE TABLE `v_encryption_stats` (
`total_records` bigint
,`encrypted_count` decimal(23,0)
,`plain_text_count` decimal(23,0)
,`encryption_percentage` decimal(29,2)
,`last_encryption_date` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_visitor_encryption_status`
-- (See below for the actual view)
--
CREATE TABLE `v_visitor_encryption_status` (
`visitor_log_id` int
,`visitor_name` text
,`is_encrypted` tinyint(1)
,`encryption_status` varchar(12)
,`encryption_version` varchar(20)
,`encrypted_at` datetime
,`created_at` timestamp
,`security_status` varchar(13)
);

-- --------------------------------------------------------

--
-- Structure for view `v_encryption_stats`
--
DROP TABLE IF EXISTS `v_encryption_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_encryption_stats`  AS SELECT count(0) AS `total_records`, sum((case when (`visitor_logs`.`is_encrypted` = 1) then 1 else 0 end)) AS `encrypted_count`, sum((case when (`visitor_logs`.`is_encrypted` = 0) then 1 else 0 end)) AS `plain_text_count`, round(((sum((case when (`visitor_logs`.`is_encrypted` = 1) then 1 else 0 end)) / count(0)) * 100),2) AS `encryption_percentage`, max(`visitor_logs`.`encrypted_at`) AS `last_encryption_date` FROM `visitor_logs` ;

-- --------------------------------------------------------

--
-- Structure for view `v_visitor_encryption_status`
--
DROP TABLE IF EXISTS `v_visitor_encryption_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `v_visitor_encryption_status`  AS SELECT `visitor_logs`.`visitor_log_id` AS `visitor_log_id`, `visitor_logs`.`visitor_name` AS `visitor_name`, `visitor_logs`.`is_encrypted` AS `is_encrypted`, (case when (`visitor_logs`.`is_encrypted` = 1) then '🔒 ENCRYPTED' else '📝 PLAIN TEXT' end) AS `encryption_status`, `visitor_logs`.`encryption_version` AS `encryption_version`, `visitor_logs`.`encrypted_at` AS `encrypted_at`, `visitor_logs`.`created_at` AS `created_at`, (case when ((`visitor_logs`.`is_encrypted` = 1) and (`visitor_logs`.`encrypted_at` is not null)) then '✅ Secure' when (`visitor_logs`.`is_encrypted` = 0) then '⚠️ Not Secure' else '❓ Unknown' end) AS `security_status` FROM `visitor_logs` ORDER BY `visitor_logs`.`visitor_log_id` DESC ;

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
  ADD UNIQUE KEY `idx_unique_alert_card_time` (`card_uid`,`timestamp`),
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
-- Indexes for table `current_occupancy`
--
ALTER TABLE `current_occupancy`
  ADD PRIMARY KEY (`occupancy_id`),
  ADD UNIQUE KEY `unique_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_card_uid` (`card_uid`);

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
-- Indexes for table `encrypted_visitor_data`
--
ALTER TABLE `encrypted_visitor_data`
  ADD PRIMARY KEY (`visitor_id`),
  ADD UNIQUE KEY `idx_card_uid` (`card_uid`),
  ADD KEY `idx_validity_end` (`validity_end`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `encryption_audit_log`
--
ALTER TABLE `encryption_audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_record_id` (`record_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `encryption_key_management`
--
ALTER TABLE `encryption_key_management`
  ADD PRIMARY KEY (`key_id`),
  ADD UNIQUE KEY `idx_key_version` (`key_version`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `encryption_migration_log`
--
ALTER TABLE `encryption_migration_log`
  ADD PRIMARY KEY (`migration_id`),
  ADD KEY `idx_table_name` (`table_name`),
  ADD KEY `idx_status` (`status`);

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
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_audit_logs`
--
ALTER TABLE `staff_audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `staff_logs`
--
ALTER TABLE `staff_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

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
-- Indexes for table `student_registration_logs`
--
ALTER TABLE `student_registration_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`);

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
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_portal_email` (`portal_email`);

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
  ADD KEY `idx_access_status` (`access_status`),
  ADD KEY `idx_is_encrypted` (`is_encrypted`),
  ADD KEY `idx_encrypted_at` (`encrypted_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_logs`
--
ALTER TABLE `access_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `admin_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admission_records`
--
ALTER TABLE `admission_records`
  MODIFY `admission_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `alert_logs`
--
ALTER TABLE `alert_logs`
  MODIFY `alert_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=289;

--
-- AUTO_INCREMENT for table `available_rfid_cards`
--
ALTER TABLE `available_rfid_cards`
  MODIFY `card_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `current_occupancy`
--
ALTER TABLE `current_occupancy`
  MODIFY `occupancy_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `email_update_logs`
--
ALTER TABLE `email_update_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encrypted_visitor_data`
--
ALTER TABLE `encrypted_visitor_data`
  MODIFY `visitor_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `encryption_audit_log`
--
ALTER TABLE `encryption_audit_log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encryption_key_management`
--
ALTER TABLE `encryption_key_management`
  MODIFY `key_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `encryption_migration_log`
--
ALTER TABLE `encryption_migration_log`
  MODIFY `migration_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `math_logs`
--
ALTER TABLE `math_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `request_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `puzzle_attempts`
--
ALTER TABLE `puzzle_attempts`
  MODIFY `attempt_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident_profiles`
--
ALTER TABLE `resident_profiles`
  MODIFY `profile_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  MODIFY `card_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `saved_logins`
--
ALTER TABLE `saved_logins`
  MODIFY `saved_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_audit_logs`
--
ALTER TABLE `staff_audit_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_logs`
--
ALTER TABLE `staff_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_users`
--
ALTER TABLE `staff_users`
  MODIFY `staff_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `student_audit_logs`
--
ALTER TABLE `student_audit_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_concerns`
--
ALTER TABLE `student_concerns`
  MODIFY `concern_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_registration_logs`
--
ALTER TABLE `student_registration_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `student_users`
--
ALTER TABLE `student_users`
  MODIFY `student_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `system_configuration`
--
ALTER TABLE `system_configuration`
  MODIFY `config_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `setting_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `visitor_logs`
--
ALTER TABLE `visitor_logs`
  MODIFY `visitor_log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `staff_logs`
--
ALTER TABLE `staff_logs`
  ADD CONSTRAINT `staff_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`);

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
