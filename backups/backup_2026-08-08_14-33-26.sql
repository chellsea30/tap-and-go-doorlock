-- Tap-and-Go Doorlock Database Backup
-- Generated: 2026-08-08 14:33:26
-- Tables: access_logs, admin_users, admission_records, alert_logs, announcements, audit_log, audit_logs, available_rfid_cards, email_logs, email_update_logs, login_attempts, math_logs, notifications, password_reset_requests, puzzle_attempts, resident_profiles, rfid_cards, saved_logins, security_logs, staff_audit_logs, staff_users, student_audit_logs, student_concerns, student_users, system_configuration, user_settings, users, visitor_logs

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `access_logs`;
CREATE TABLE `access_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `card_uid` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `access_type` enum('entry','exit') DEFAULT 'entry',
  `access_status` enum('granted','denied') DEFAULT 'granted',
  `alert_triggered` tinyint(1) DEFAULT 0,
  `power_source` enum('main','battery') DEFAULT 'main',
  `reason` varchar(255) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_card_uid` (`card_uid`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_access_type` (`access_type`),
  KEY `idx_access_status` (`access_status`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('1', '0A1FD006', '1', 'entry', 'granted', '0', 'main', NULL, '2026-08-07 18:08:39', '2026-08-07 18:08:39', '2026-08-07 18:08:39');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('2', '85241249', NULL, 'entry', 'denied', '1', 'main', NULL, '2026-08-07 18:14:23', '2026-08-07 18:14:23', '2026-08-07 18:14:23');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('3', '85241249', NULL, 'entry', 'denied', '1', 'main', NULL, '2026-08-07 18:14:39', '2026-08-07 18:14:39', '2026-08-07 18:14:39');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('4', '85241249', '1', 'entry', 'granted', '0', 'main', NULL, '2026-08-07 18:21:50', '2026-08-07 18:21:50', '2026-08-07 18:21:50');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('5', '0A1FD006', '1', 'exit', 'granted', '0', 'main', NULL, '2026-08-07 18:22:15', '2026-08-07 18:22:15', '2026-08-07 18:22:15');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('6', '65661B49', NULL, 'entry', 'denied', '1', 'main', NULL, '2026-08-07 18:23:04', '2026-08-07 18:23:04', '2026-08-07 18:23:04');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('7', '0A1FD006', '1', 'entry', 'granted', '0', 'main', NULL, '2026-08-08 15:47:25', '2026-08-08 15:47:25', '2026-08-08 15:47:25');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('8', '0A1FD006', '1', 'exit', 'granted', '0', 'main', NULL, '2026-08-08 15:47:36', '2026-08-08 15:47:36', '2026-08-08 15:47:36');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('9', '0A1FD006', '1', 'entry', 'granted', '0', 'main', NULL, '2026-08-08 15:48:38', '2026-08-08 15:48:38', '2026-08-08 15:48:38');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('10', '85241249', '1', 'entry', 'granted', '0', 'main', NULL, '2026-08-08 15:48:47', '2026-08-08 15:48:47', '2026-08-08 15:48:47');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('11', '0A1FD006', '1', 'exit', 'granted', '0', 'main', NULL, '2026-08-08 15:49:42', '2026-08-08 15:49:42', '2026-08-08 15:49:42');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('12', '0A1FD006', '1', 'entry', 'granted', '0', 'main', NULL, '2026-08-08 16:10:41', '2026-08-08 16:10:41', '2026-08-08 16:10:41');
INSERT INTO `access_logs` (`log_id`, `card_uid`, `user_id`, `access_type`, `access_status`, `alert_triggered`, `power_source`, `reason`, `timestamp`, `created_at`, `updated_at`) VALUES ('13', '0A1FD006', '1', 'exit', 'granted', '0', 'main', NULL, '2026-08-08 16:35:13', '2026-08-08 16:35:13', '2026-08-08 16:35:13');

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`),
  KEY `idx_email_hash` (`email_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `otp_expiry`, `full_name`, `email`, `email_hash`, `email_verified`, `avatar`, `role`, `is_active`, `math_attempts`, `math_blocked_until`, `last_login`, `created_at`, `updated_at`, `otp_enabled`) VALUES ('1', 'admin_chelsea', '$2a$12$3PYMMlfyil2JMr43rbJlf.p8QLIvKEH8SfyvsIuU7ruJ4ByhzBCdq', NULL, 'Chellsea Albano', 'albanochellsea30@gmail.com', '40d9222232106ee0a94ab60ed286fd82a57c664c5cd6b9633057afbe3f1862fe', '1', 'uploads/avatars/avatar_1_1786189553.png', 'administrator', '1', '0', NULL, '2026-08-08 20:33:12', '2026-08-07 17:12:16', '2026-08-08 20:33:12', '1');
INSERT INTO `admin_users` (`admin_id`, `username`, `password_hash`, `otp_expiry`, `full_name`, `email`, `email_hash`, `email_verified`, `avatar`, `role`, `is_active`, `math_attempts`, `math_blocked_until`, `last_login`, `created_at`, `updated_at`, `otp_enabled`) VALUES ('2', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'System Administrator', 'admin@tapandgo.com', '2f22d7fa3ba2ba8d9aafe7993f39b0fc72dc12337f58b2874e7f9c4ab00961b3', '1', NULL, 'administrator', '1', '0', NULL, NULL, '2026-08-07 17:12:16', '2026-08-07 17:12:16', '1');

DROP TABLE IF EXISTS `admission_records`;
CREATE TABLE `admission_records` (
  `admission_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`admission_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_semester` (`semester_sy`),
  CONSTRAINT `admission_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admission_records` (`admission_id`, `user_id`, `semester_sy`, `age`, `birth_date`, `home_address`, `school_last`, `school_address`, `strand_track`, `course_taken`, `year_level_old`, `former_bh`, `former_address`, `guardian_name`, `guardian_contact`, `room_assignment`, `student_signature`, `status`, `created_at`, `updated_at`) VALUES ('1', '1', '1st semester 2025-2026', '18', '2007-11-14', 'Aurora Alicia Isabela', '0', 'Paddad alicia isabela', '', 'BAELS', '', '', 'Aurora Alicia Isabela', 'Jefferson Albano', '09539976519', '1', 'Kristel Jade P. Albano', 'pending', '2026-08-07 17:43:03', '2026-08-07 17:43:03');

DROP TABLE IF EXISTS `alert_logs`;
CREATE TABLE `alert_logs` (
  `alert_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`alert_id`),
  KEY `idx_card_uid` (`card_uid`),
  KEY `idx_status` (`delivery_status`),
  KEY `idx_type` (`alert_type`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_access_type` (`access_type`),
  KEY `idx_resolved_at` (`resolved_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `alert_logs` (`alert_id`, `card_uid`, `user_name`, `alert_type`, `reason`, `timestamp`, `delivery_status`, `resolved_at`, `access_type`, `card_type`, `created_at`, `updated_at`) VALUES ('3', '65661B49', 'Unknown', 'unauthorized', 'Unauthorized access attempt with card: 65661B49', '2026-08-07 18:23:04', 'resolved', '2026-08-08 15:50:45', 'entry', 'unknown', '2026-08-07 18:23:04', '2026-08-08 15:50:45');
INSERT INTO `alert_logs` (`alert_id`, `card_uid`, `user_name`, `alert_type`, `reason`, `timestamp`, `delivery_status`, `resolved_at`, `access_type`, `card_type`, `created_at`, `updated_at`) VALUES ('4', '65661B49', 'Unknown Card', 'unauthorized', 'Unknown card detected: 65661B49', '2026-08-07 18:23:04', 'resolved', '2026-08-08 15:50:45', 'entry', 'unknown', '2026-08-07 18:23:04', '2026-08-08 15:50:45');

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`announcement_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `announcements` (`announcement_id`, `admin_id`, `title`, `content`, `priority`, `is_active`, `created_at`, `updated_at`) VALUES ('1', '1', 'CONCERN', 'MAKE it QUICK', 'medium', '1', '2026-08-07 20:48:43', '2026-08-07 20:48:43');

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('1', '1', 'Profile Update', 'Updated profile: Chellsea Albano', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-07 18:24:03');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('2', '1', 'Add Staff', 'Added staff: Mylene C. Samiling (STAFF-001)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-07 20:37:02');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('3', '1', 'Add Announcement', 'Added announcement: CONCERN', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-07 20:48:43');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('4', '1', 'Password Reset Approved', 'Approved reset for student: Kristel Jade P. Albano - Token generated', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 11:16:00');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('5', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:12:41');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('6', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:12:54');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('7', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:17:32');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('8', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:26:23');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('9', '1', 'Update Resident Email', 'Updated resident: Kristel Jade P. Albano', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:29:28');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('10', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:30:26');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('11', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:31:50');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('12', '1', 'Send Resident Email', 'Sent email to 0 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:32:17');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('13', '1', 'Send Resident Email', 'Sent email to 1 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 13:33:18');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('14', '1', 'Send Staff Email', 'Sent email to 1 staff members', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 14:55:17');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('15', '1', 'Send Staff Email', 'Sent email to 1 staff members', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 14:57:53');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('16', '1', 'Resolve All Alerts', 'Resolved 2 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:50:45');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('17', '1', 'Resolve All Alerts', 'Resolved 0 alerts', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:50:49');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('18', '1', 'Send Staff Email', 'Sent email to 1 staff members', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:51:06');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('19', '1', 'Send Resident Email', 'Sent email to 1 residents', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:52:19');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('20', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:30');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('21', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:31');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('22', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:32');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('23', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:32');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('24', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:32');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('25', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:33');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('26', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:33');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('27', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:34');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('28', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:35');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('29', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:35');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('30', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:35');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('31', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:36');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('32', '1', 'Settings Update', 'Updated system settings', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 15:55:36');
INSERT INTO `audit_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES ('33', '1', 'Concern Response', 'Responded to concern ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 16:39:03');

DROP TABLE IF EXISTS `available_rfid_cards`;
CREATE TABLE `available_rfid_cards` (
  `card_id` int(11) NOT NULL AUTO_INCREMENT,
  `card_uid` varchar(50) NOT NULL,
  `card_type` enum('resident','staff','visitor') DEFAULT 'resident',
  `status` enum('available','assigned','lost') DEFAULT 'available',
  `date_added` date DEFAULT curdate(),
  `added_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `card_uid` (`card_uid`),
  KEY `added_by` (`added_by`),
  KEY `idx_card_uid` (`card_uid`),
  KEY `idx_status` (`status`),
  KEY `idx_card_type` (`card_type`),
  CONSTRAINT `available_rfid_cards_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `available_rfid_cards` (`card_id`, `card_uid`, `card_type`, `status`, `date_added`, `added_by`, `notes`, `created_at`, `updated_at`) VALUES ('1', '0A1FD006', 'resident', 'assigned', '2026-08-07', NULL, '', '2026-08-07 17:47:25', '2026-08-07 17:47:57');

DROP TABLE IF EXISTS `email_logs`;
CREATE TABLE `email_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('staff','resident','visitor') NOT NULL DEFAULT 'staff',
  `recipient_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sent_by` int(11) NOT NULL,
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_recipient_type` (`recipient_type`),
  KEY `idx_sent_by` (`sent_by`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`) VALUES ('1', 'resident', '1', 'albanokristel14@gmail.com', 'Kristel Jade P. Albano', 'bhvh', 'xaswfw4', '1', '2026-08-08 13:33:18');
INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`) VALUES ('2', 'staff', '1', 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'hfytdtyhhhfgf', '1', '2026-08-08 14:55:17');
INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`) VALUES ('3', 'staff', '1', 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'zghjkl3456789ojhbnkl', '1', '2026-08-08 14:57:53');
INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`) VALUES ('4', 'staff', '1', 'mylenesamiling@gmail.com', 'Mylene C. Samiling', 'mabaho', 'ka', '1', '2026-08-08 15:51:06');
INSERT INTO `email_logs` (`log_id`, `recipient_type`, `recipient_id`, `recipient_email`, `recipient_name`, `subject`, `message`, `sent_by`, `sent_at`) VALUES ('5', 'resident', '1', 'albanokristel14@gmail.com', 'Kristel Jade P. Albano', 'mabaho', 'swascwsyfu3', '1', '2026-08-08 15:52:19');

DROP TABLE IF EXISTS `email_update_logs`;
CREATE TABLE `email_update_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_email` varchar(255) NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `last_attempt` datetime DEFAULT NULL,
  `blocked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `math_logs`;
CREATE TABLE `math_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_type` enum('admin','staff','student') NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `math_question` text NOT NULL,
  `user_answer` varchar(255) NOT NULL,
  `correct_answer` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_type` enum('unauthorized','buzzer','system') DEFAULT 'unauthorized',
  `card_uid` varchar(50) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `card_type` varchar(20) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `access_type` enum('entry','exit') DEFAULT 'entry',
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`notification_id`, `notification_type`, `card_uid`, `user_name`, `card_type`, `reason`, `access_type`, `status`, `created_at`, `read_at`, `expires_at`) VALUES ('1', 'unauthorized', '85241249', 'Visitor (Expired)', 'visitor', 'Expired visitor card: 85241249', 'entry', 'unread', '2026-08-07 18:14:23', NULL, '2026-08-07 19:14:23');
INSERT INTO `notifications` (`notification_id`, `notification_type`, `card_uid`, `user_name`, `card_type`, `reason`, `access_type`, `status`, `created_at`, `read_at`, `expires_at`) VALUES ('2', 'unauthorized', '85241249', 'Visitor (Expired)', 'visitor', 'Expired visitor card: 85241249', 'entry', 'unread', '2026-08-07 18:14:39', NULL, '2026-08-07 19:14:39');
INSERT INTO `notifications` (`notification_id`, `notification_type`, `card_uid`, `user_name`, `card_type`, `reason`, `access_type`, `status`, `created_at`, `read_at`, `expires_at`) VALUES ('3', 'unauthorized', '65661B49', 'Unknown', 'unknown', 'Unauthorized access attempt with card: 65661B49', 'entry', 'unread', '2026-08-07 18:23:04', NULL, '2026-08-07 19:23:04');
INSERT INTO `notifications` (`notification_id`, `notification_type`, `card_uid`, `user_name`, `card_type`, `reason`, `access_type`, `status`, `created_at`, `read_at`, `expires_at`) VALUES ('4', 'unauthorized', '65661B49', 'Unknown Card', 'unknown', 'Unknown card detected: 65661B49', 'entry', 'unread', '2026-08-07 18:23:04', NULL, '2026-08-07 19:23:04');

DROP TABLE IF EXISTS `password_reset_requests`;
CREATE TABLE `password_reset_requests` (
  `request_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  KEY `idx_requested_at` (`requested_at`),
  KEY `idx_token` (`reset_token`),
  CONSTRAINT `password_reset_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_users` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `password_reset_requests` (`request_id`, `student_id`, `student_name`, `student_id_number`, `username`, `email`, `reason`, `status`, `reset_token`, `token_expires_at`, `admin_response`, `requested_at`, `responded_at`, `created_at`, `updated_at`) VALUES ('1', '1', 'Kristel Jade P. Albano', 'STU-2026-3808', 'Kristel Jade', 'albanokristel14@gmail.com', '', 'completed', '05e01a399e8147033fe0e9b5d88c6ede99c45b62c89aa6f45f7adeb00d26f4c0', '2026-08-09 05:53:41', '', '2026-08-08 11:05:17', '2026-08-08 11:22:02', '2026-08-08 11:05:17', '2026-08-08 11:54:26');

DROP TABLE IF EXISTS `puzzle_attempts`;
CREATE TABLE `puzzle_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('staff','student') NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_user_type` (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `resident_profiles`;
CREATE TABLE `resident_profiles` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`profile_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `resident_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `resident_profiles` (`profile_id`, `user_id`, `date_registered`, `course`, `year_level`, `gender`, `birth_date`, `age`, `home_address`, `cp_no`, `religion`, `dialect`, `emergency_name`, `emergency_relationship`, `emergency_address`, `emergency_contact`, `created_at`, `updated_at`) VALUES ('1', '1', '2026-08-07', 'BAELS', '2nd Year', 'Female', '2007-11-14', '18', 'Aurora Alicia Isabela', NULL, 'Catholic', 'Tagalog', 'Jefferson Albano', 'Father', 'Aurora Alicia Isabela', '09539976519', '2026-08-07 17:40:16', '2026-08-07 17:41:48');

DROP TABLE IF EXISTS `rfid_cards`;
CREATE TABLE `rfid_cards` (
  `card_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `card_uid` (`card_uid`),
  KEY `resident_visited` (`resident_visited`),
  KEY `idx_card_uid` (`card_uid`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_card_type` (`card_type`),
  KEY `idx_status` (`status`),
  KEY `idx_expiry_date` (`expiry_date`),
  CONSTRAINT `rfid_cards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `rfid_cards_ibfk_2` FOREIGN KEY (`resident_visited`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rfid_cards` (`card_id`, `card_uid`, `user_id`, `card_type`, `issued_date`, `expiry_date`, `validity_end`, `status`, `visitor_name`, `visitor_phone`, `purpose_of_visit`, `resident_visited`, `created_at`, `updated_at`) VALUES ('1', '0A1FD006', '1', 'resident', '2026-08-07', '2027-08-07', NULL, 'active', NULL, NULL, NULL, NULL, '2026-08-07 17:47:57', '2026-08-07 17:47:57');
INSERT INTO `rfid_cards` (`card_id`, `card_uid`, `user_id`, `card_type`, `issued_date`, `expiry_date`, `validity_end`, `status`, `visitor_name`, `visitor_phone`, `purpose_of_visit`, `resident_visited`, `created_at`, `updated_at`) VALUES ('2', '85241249', '1', 'visitor', '2026-08-07', NULL, NULL, 'active', 'jeff albano', '09918256154', 'VISIT', '1', '2026-08-07 17:51:23', '2026-08-07 18:21:38');

DROP TABLE IF EXISTS `saved_logins`;
CREATE TABLE `saved_logins` (
  `saved_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`saved_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_last_activity` (`last_activity`),
  CONSTRAINT `saved_logins_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `saved_logins` (`saved_id`, `admin_id`, `device_name`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `is_active`) VALUES ('1', '1', 'Chrome Browser', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0', '2026-08-08 20:10:40', '2026-08-08 20:33:26', '1');

DROP TABLE IF EXISTS `security_logs`;
CREATE TABLE `security_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(50) NOT NULL,
  `card_uid` varchar(255) NOT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `security_logs` (`log_id`, `event_type`, `card_uid`, `user_name`, `details`, `timestamp`) VALUES ('1', 'unauthorized_access', '85241249', 'Visitor (Expired)', 'Expired visitor card: 85241249 | Type: entry', '2026-08-07 18:14:23');
INSERT INTO `security_logs` (`log_id`, `event_type`, `card_uid`, `user_name`, `details`, `timestamp`) VALUES ('2', 'unauthorized_access', '85241249', 'Visitor (Expired)', 'Expired visitor card: 85241249 | Type: entry', '2026-08-07 18:14:39');
INSERT INTO `security_logs` (`log_id`, `event_type`, `card_uid`, `user_name`, `details`, `timestamp`) VALUES ('3', 'unauthorized_access', '65661B49', 'Unknown Card', 'Unknown card detected: 65661B49 | Type: entry', '2026-08-07 18:23:04');

DROP TABLE IF EXISTS `staff_audit_logs`;
CREATE TABLE `staff_audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `staff_users`;
CREATE TABLE `staff_users` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `staff_id_number` (`staff_id_number`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_staff_id_number` (`staff_id_number`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `staff_users` (`staff_id`, `staff_id_number`, `full_name`, `email`, `department`, `phone`, `password_hash`, `avatar`, `created_at`, `updated_at`, `status`, `last_login`, `is_active`) VALUES ('1', 'STAFF-001', 'Mylene C. Samiling', 'mylenesamiling@gmail.com', 'Domitory Management', '09558271369', '$2y$10$cqgLe/JXVWz2qTBcVO8Xf.pESLHgBzdvGWRopOzMDpYhdrTAeY3vu', 'uploads/staff_photos/staff_1_1786106233.jpg', '2026-08-07 20:37:02', '2026-08-07 20:37:13', 'active', NULL, '1');

DROP TABLE IF EXISTS `student_audit_logs`;
CREATE TABLE `student_audit_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `student_concerns`;
CREATE TABLE `student_concerns` (
  `concern_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`concern_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_concerns` (`concern_id`, `student_id`, `student_name`, `student_id_number`, `room_number`, `subject`, `message`, `category`, `priority`, `status`, `admin_response`, `created_at`, `updated_at`) VALUES ('1', '1', 'Kristel Jade P. Albano', 'STU-2026-3808', '1', 'mabaho', 'dwqfdw3g', 'maintenance', 'medium', 'resolved', 'ok', '2026-08-07 21:26:19', '2026-08-08 16:39:03');

DROP TABLE IF EXISTS `student_users`;
CREATE TABLE `student_users` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `student_id_number` (`student_id_number`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_student_id` (`student_id_number`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `student_users` (`student_id`, `student_id_number`, `full_name`, `username`, `course`, `year_level`, `email`, `profile_photo`, `password_hash`, `phone`, `room_number`, `resident_id`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'STU-2026-3808', 'Kristel Jade P. Albano', 'Kristel Jade', 'BAELS', '2nd Year', 'albanokristel14@gmail.com', NULL, '$2y$10$zAlId0nFTUw200DZuXoKjuwFNarC7N4bmnBay9il3GlC0ql6C0epS', '09539976519', '1', '1', '1', '2026-08-07 17:44:23', '2026-08-08 11:54:26');

DROP TABLE IF EXISTS `system_configuration`;
CREATE TABLE `system_configuration` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(255) NOT NULL,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`config_id`),
  KEY `idx_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `user_settings`;
CREATE TABLE `user_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_admin_setting` (`admin_id`,`setting_key`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_setting_key` (`setting_key`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_settings` (`setting_id`, `admin_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES ('1', '1', 'dark_mode', 'true', '2026-08-08 15:55:30', '2026-08-08 15:55:36');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `student_id` (`student_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`user_id`, `full_name`, `student_id`, `room_number`, `contact_number`, `email`, `password_hash`, `status`, `created_at`, `updated_at`, `profile_photo`, `is_active`) VALUES ('1', 'Kristel Jade P. Albano', 'STU-2026-8234', '1', '09539976519', 'albanokristel14@gmail.com', NULL, 'active', '2026-08-07 17:40:16', '2026-08-08 13:29:28', 'uploads/resident_photos/1786098269_1.jpg', '1');

DROP TABLE IF EXISTS `visitor_logs`;
CREATE TABLE `visitor_logs` (
  `visitor_log_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`visitor_log_id`),
  KEY `idx_resident_visited` (`resident_visited`),
  KEY `idx_temporary_card` (`temporary_card_uid`),
  KEY `idx_access_status` (`access_status`),
  CONSTRAINT `visitor_logs_ibfk_1` FOREIGN KEY (`resident_visited`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `visitor_logs` (`visitor_log_id`, `visitor_name`, `phone`, `relationship`, `visitor_phone`, `visitor_contact`, `resident_visited`, `purpose_of_visit`, `temporary_card_uid`, `validity_start`, `validity_end`, `entry_timestamp`, `exit_timestamp`, `access_status`, `created_at`, `updated_at`) VALUES ('1', 'jeff albano', '09918256154', 'ANAK', NULL, NULL, '1', 'VISIT', '85241249', '2026-08-07', '2026-08-14', '2026-08-08 15:48:47', NULL, 'granted', '2026-08-07 18:21:38', '2026-08-08 15:48:47');

SET FOREIGN_KEY_CHECKS=1;
