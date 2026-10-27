-- phpMyAdmin SQL Dump
-- version 5.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2026 at 01:11 PM
-- Server version: 10.4.11-MariaDB
-- PHP Version: 7.4.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `student_leadership_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accomplishments`
--

CREATE TABLE `accomplishments` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `impact_summary` varchar(255) DEFAULT NULL,
  `date_accomplished` date NOT NULL,
  `badge_tag` varchar(50) DEFAULT 'Success',
  `attachment_file` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(20) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachments_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `accomplishment_logs`
--

CREATE TABLE `accomplishment_logs` (
  `id` int(11) NOT NULL,
  `acc_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `log_details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `feedbacks`
--

CREATE TABLE `feedbacks` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT 'Anonymous Student',
  `student_email` varchar(100) DEFAULT NULL,
  `student_grade_section` varchar(50) DEFAULT NULL,
  `category` enum('Suggestion','Complaint','Inquiry','Appreciation') DEFAULT 'Suggestion',
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Pending','Under Review','In Action','Resolved') DEFAULT 'Pending',
  `officer_response` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_logs`
--

CREATE TABLE `feedback_logs` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `officer_name` varchar(100) NOT NULL,
  `officer_role` varchar(50) NOT NULL,
  `status` varchar(50) NOT NULL,
  `reply_message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL,
  `account_type` varchar(20) NOT NULL DEFAULT 'Officer',
  `account_id` int(11) DEFAULT NULL,
  `org_id` int(11) DEFAULT NULL,
  `org_acronym` varchar(30) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(100) NOT NULL,
  `log_details` varchar(255) NOT NULL,
  `ip_address` varchar(60) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` (`id`, `account_type`, `account_id`, `org_id`, `org_acronym`, `user_name`, `user_role`, `log_details`, `ip_address`, `created_at`) VALUES
(1, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '127.0.0.1', '2026-10-21 03:16:36'),
(2, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '127.0.0.1', '2026-10-21 03:04:36'),
(3, 'Officer', 5, 4, 'SLLG', 'Mario B', 'Vice President', 'Mario B (Vice President) logged in to the SLLG Leadership Portal.', '127.0.0.1', '2026-10-21 02:52:36'),
(4, 'Officer', 6, 4, 'SLLG', 'Marie C', 'Officer', 'Marie C (Officer) logged in to the SLLG Leadership Portal.', '127.0.0.1', '2026-10-21 02:40:36'),
(8, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-21 03:59:36'),
(9, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-21 04:00:07'),
(10, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-21 04:27:13'),
(11, 'Student', 1, NULL, 'STUDENT', 'Alex Morgan', 'Student • Grade 11 - STEM', 'Alex Morgan (LRN: 2026-0001) logged in to the Student Portal.', '::1', '2026-10-21 04:27:59'),
(12, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-21 04:46:29'),
(13, 'Officer', 5, 4, 'SLLG', 'Mario B', 'Vice President', 'Mario B (Vice President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-21 05:31:01'),
(14, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-21 05:32:15'),
(15, 'Officer', 6, 4, 'SLLG', 'Marie C', 'Officer', 'Marie C (Officer) logged in to the SLLG Leadership Portal.', '::1', '2026-10-21 05:50:13'),
(16, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-21 08:35:50'),
(17, 'Officer', 7, 4, 'SLLG', 'Juan Dela Cruz', 'Treasurer', 'Juan Dela Cruz (Treasurer) logged in to the SLLG Leadership Portal.', '::1', '2026-10-21 08:36:38'),
(18, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-23 08:14:26'),
(19, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-23 08:15:14'),
(20, 'Officer', 3, 1, 'SSLG', 'administrator', 'Admin', 'administrator (Admin) logged in to the SSLG Leadership Portal.', '127.0.0.1', '2026-10-23 08:19:44'),
(21, 'Officer', 4, 1, 'SSLG', 'Maria A', 'President', 'Maria A (President) logged in to the SSLG Leadership Portal.', '127.0.0.1', '2026-10-23 08:20:19'),
(22, 'Officer', 3, 1, 'SSLG', 'administrator', 'Admin', 'administrator (Admin) logged in to the SSLG Leadership Portal.', '127.0.0.1', '2026-10-23 08:35:13'),
(23, 'Student', 1, NULL, 'STUDENT', 'Alex Morgan', 'Student • Grade 11 - STEM', 'Alex Morgan (LRN: 2026-0001) logged in to the Student Portal.', '::1', '2026-10-25 09:28:38'),
(24, 'Officer', 7, 4, 'SLLG', 'Juan Dela Cruz', 'Treasurer', 'Juan Dela Cruz (Treasurer) logged in to the SLLG Leadership Portal.', '::1', '2026-10-25 09:49:33'),
(25, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-25 10:02:35'),
(26, 'Officer', 6, 4, 'SLLG', 'Marie C', 'Officer', 'Marie C (Officer) logged in to the SLLG Leadership Portal.', '::1', '2026-10-25 10:03:15'),
(27, 'Officer', 6, 4, 'SLLG', 'Marie C', 'Officer', 'Marie C (Officer) logged in to the SLLG Leadership Portal.', '::1', '2026-10-26 11:50:49'),
(28, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-26 11:52:18'),
(29, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-27 11:38:23'),
(30, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-27 11:40:46'),
(31, 'Student', 5, NULL, 'STUDENT', 'Juan Dela Cruz', 'Student • Grade 12- STEM', 'Juan Dela Cruz (LRN: 2026-0003) logged in to the Student Portal.', '::1', '2026-10-27 11:41:23'),
(32, 'Officer', 4, 4, 'SLLG', 'Maria A', 'President', 'Maria A (President) logged in to the SLLG Leadership Portal.', '::1', '2026-10-27 11:41:46'),
(33, 'Officer', 6, 4, 'SLLG', 'Marie C', 'Officer', 'Marie C (Officer) logged in to the SLLG Leadership Portal.', '::1', '2026-10-27 11:43:51'),
(34, 'Officer', 3, 2, 'MIS', 'administrator', 'Admin', 'administrator (Admin) logged in to the MIS Leadership Portal.', '::1', '2026-10-27 11:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `acronym` varchar(20) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'Student Council',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `name`, `acronym`, `logo`, `description`, `category`, `created_at`) VALUES
(2, 'Management Information System', 'MIS', 'logo_1791535811_6ac8aac3a6145.jpg', 'Dedicated to environmental awareness, protection, and sustainability.', 'Special Interest', '2026-10-09 08:35:44'),
(4, 'Supreme Secondary Learner Government', 'SLLG', 'logo_1792455662_6ad6b3eed16b4.jpg', 'to protect and promote the right of every learner to quality, equitable, culture-based, and complete basic education in a child-friendly, gender-sensitive, safe, and motivating environment', 'Student Council', '2026-10-20 00:21:02'),
(5, 'Technical-Vocational-Livelihood', 'TVL', 'logo_1792560808_6ad84ea899f26.jpg', '', 'Academic', '2026-10-21 05:33:28');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` enum('Planning','Ongoing','Accomplished','On Hold') DEFAULT 'Planning',
  `progress_percent` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `target_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `org_id`, `title`, `description`, `status`, `progress_percent`, `start_date`, `target_date`, `created_at`) VALUES
(2, 4, 'TEST', 'TEST', 'Ongoing', 60, '2026-10-20', '2026-11-19', '2026-10-20 05:44:26');

-- --------------------------------------------------------

--
-- Table structure for table `project_logs`
--

CREATE TABLE `project_logs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `log_details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `project_logs`
--

INSERT INTO `project_logs` (`id`, `project_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`, `created_at`) VALUES
(1, 2, NULL, 'System / Leadership', 'Leader', 'Created', 'Project created with title \"TEST\", status \'Ongoing\', progress 25%.', '2026-10-20 05:44:26'),
(2, 2, 3, 'administrator', 'Admin', 'Progress Updated', 'Progress updated from 25% to 60%', '2026-10-21 03:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_lrn` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `grade_section` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_lrn`, `full_name`, `grade_section`, `email`, `password`, `status`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, '2026-0001', 'Alex Morgan', 'Grade 11 - STEM', 'alex.morgan@student.edu.ph', '$2y$10$q0Ukk9uQQ92vvLmHUBJpI.Ft6y4/8R2nrIbGJqaozXC.sd7FF8a9.', 'Approved', NULL, '2026-10-23 15:47:35', '2026-10-12 01:38:50'),
(2, '2026-0002', 'Samantha Cruz', 'Grade 10 - Rizal', 'samantha.cruz@student.edu.ph', '$2y$10$DJiHb/PBlxM8nEwP7i3jhex99G6Bm9MacJIn4oYxmgklSEUZdwdcq', 'Approved', 3, '2026-10-23 16:24:10', '2026-10-12 01:38:51'),
(5, '2026-0003', 'Juan Dela Cruz', 'Grade 12- STEM', 'juan@gmail.com', '$2y$10$dUHysNgmk6Q2.NG7U9A11exvwZx7XI6/caGn8eA/tRGQTfs0iPw9m', 'Approved', 3, '2026-10-27 19:40:57', '2026-10-23 08:13:20');

-- --------------------------------------------------------

--
-- Table structure for table `student_logs`
--

CREATE TABLE `student_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `student_lrn` varchar(50) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `previous_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `log_details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `student_logs`
--

INSERT INTO `student_logs` (`id`, `student_id`, `student_lrn`, `student_name`, `user_id`, `user_name`, `user_role`, `action_type`, `previous_status`, `new_status`, `log_details`, `created_at`) VALUES
(1, 1, '2026-0001', 'Alex Morgan', NULL, 'System Administrator', 'Admin', 'Approved', 'Pending', 'Approved', 'Student account (LRN: 2026-0001, Grade 11 - STEM) status recorded as \'Approved\'.', '2026-10-23 07:47:35'),
(2, 2, '2026-0002', 'Samantha Cruz', 3, 'System Administrator', 'Admin', 'Approved', 'Pending', 'Approved', 'Student account (LRN: 2026-0002, Grade 10 - Rizal) status recorded as \'Approved\'.', '2026-10-23 08:24:10'),
(3, 5, '2026-0003', 'Juan Dela Cruz', 3, 'System Administrator', 'Admin', 'Approved', 'Pending', 'Approved', 'Student account (LRN: 2026-0003, Grade 12- STEM) status recorded as \'Approved\'.', '2026-10-23 08:24:08'),
(7, 5, '2026-0003', 'Juan Dela Cruz', 3, 'administrator', 'Admin', 'Status Changed to Pending', 'Approved', 'Pending', 'Account approval status changed from \'Approved\' to \'Pending\' by administrator (Admin).', '2026-10-27 11:38:51'),
(8, 5, '2026-0003', 'Juan Dela Cruz', 3, 'administrator', 'Admin', 'Approved', 'Pending', 'Approved', 'Account approval status changed from \'Pending\' to \'Approved\' by administrator (Admin). Student is now permitted to sign in to the Student Portal.', '2026-10-27 11:40:57');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('To Do','In Progress','Accomplished','Overdue') DEFAULT 'To Do',
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `deadline` date NOT NULL,
  `assigned_to` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `org_id`, `project_id`, `title`, `description`, `status`, `priority`, `deadline`, `assigned_to`, `created_at`) VALUES
(1, 4, 2, 'TESTING TASK', 'TESTING TASK', 'Accomplished', 'Medium', '2026-10-24', 'Mario B (Vice President), Marie C (Officer)', '2026-10-21 05:29:49'),
(2, 4, 2, 'Finalize event', '', 'Accomplished', 'Medium', '2026-10-30', 'Juan Dela Cruz (Treasurer), Marie C (Officer)', '2026-10-27 11:42:22');

-- --------------------------------------------------------

--
-- Table structure for table `task_logs`
--

CREATE TABLE `task_logs` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) NOT NULL,
  `user_role` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `log_details` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `task_logs`
--

INSERT INTO `task_logs` (`id`, `task_id`, `user_id`, `user_name`, `user_role`, `action_type`, `log_details`, `created_at`) VALUES
(1, 1, 4, 'Maria A', 'President', 'Created', 'Task created with title \"TESTING TASK\", status \'To Do\', priority \'Medium\', deadline Oct 24, 2026, assigned to: Mario B (Vice President), Marie C (Officer).', '2026-10-21 05:29:49'),
(2, 1, 5, 'Mario B', 'Vice President', 'Status Updated', 'Task status changed from \'To Do\' to \'In Progress\'.', '2026-10-21 05:31:29'),
(3, 1, 5, 'Mario B', 'Vice President', 'Status Updated', 'Task status changed from \'In Progress\' to \'Accomplished\'.', '2026-10-21 05:31:41'),
(4, 1, 5, 'Mario B', 'Vice President', 'Status Updated', 'Task status changed from \'Accomplished\' to \'In Progress\'.', '2026-10-21 05:31:46'),
(5, 1, NULL, 'System Auto-Monitor', 'System', 'Auto-Overdue', 'Target completion deadline (Oct 24, 2026) exceeded for task \"TESTING TASK\". Status automatically changed from \'In Progress\' to \'Overdue\'.', '2026-10-25 09:28:38'),
(6, 1, 6, 'Marie C', 'Officer', 'Status Updated', 'Task status changed from \'Overdue\' to \'In Progress\' — Log Note: testtt', '2026-10-25 10:03:43'),
(7, 1, NULL, 'System Auto-Monitor', 'System', 'Auto-Overdue', 'Target completion deadline (Oct 24, 2026) exceeded for task \"TESTING TASK\". Status automatically changed from \'In Progress\' to \'Overdue\'.', '2026-10-25 10:03:43'),
(8, 1, 6, 'Marie C', 'Officer', 'Status Updated', 'Task status changed from \'Overdue\' to \'Accomplished\'.', '2026-10-25 10:04:32'),
(9, 2, 4, 'Maria A', 'President', 'Created', 'Task created with title \"Finalize event\", status \'To Do\', priority \'Medium\', deadline Oct 30, 2026, assigned to: Juan Dela Cruz (Treasurer), Marie C (Officer).', '2026-10-27 11:42:22'),
(10, 2, 6, 'Marie C', 'Officer', 'Status Updated', 'Task status changed from \'To Do\' to \'In Progress\'.', '2026-10-27 11:44:22'),
(11, 2, 6, 'Marie C', 'Officer', 'Status Updated', 'Task status changed from \'In Progress\' to \'Accomplished\'.', '2026-10-27 11:45:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'Officer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `org_id`, `full_name`, `username`, `email`, `password`, `role`, `created_at`) VALUES
(3, 2, 'administrator', 'admin', 'yeso@school.edu.ph', '$2y$10$1Y3eYW5YIuKStNA/ypBpYOgbKpGV9O9cq2Z5tLEybjs.TQNayBpsS', 'Admin', '2026-10-09 08:35:45'),
(4, 4, 'Maria A', 'sslg_leader', 'sslg_leader@school.com', '$2y$10$fIIr/lCvdZA2l9i6Culi0eRodt5S8t9V11JfBaVZB5CPINN.hdooW', 'President', '2026-10-20 00:55:02'),
(5, 4, 'Mario B', 'sslg_vp', 'sslgvp@gmail.com', '$2y$10$T4Vi7WYLS1poEwuUBtpONOQP35RehVA9Nk8nqsyWd3pXMmnCCTDsm', 'Vice President', '2026-10-20 00:56:53'),
(6, 4, 'Marie C', 'sslg_sec', 'sslgsec@gmail.com', '$2y$10$aP6vzQ5CN9vkTJd1Jl37/O/4X1Dr9TmgKwtjRbClJPZ0i/UKDRrf6', 'Officer', '2026-10-20 00:57:16'),
(7, 4, 'Juan Dela Cruz', 'sslg_treasurer', 'sslgTreasurer@gmail.com', '$2y$10$0xuKDHBHrKQdp8NF3o2k5.6GyEJJKZBsEDjSqVWI71zQw6dnqEW3K', 'Treasurer', '2026-10-21 08:36:23');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `base_role` varchar(50) NOT NULL DEFAULT 'Officer',
  `description` varchar(255) DEFAULT NULL,
  `badge_style` varchar(50) NOT NULL DEFAULT 'badge-role-officer',
  `can_manage_users` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_tasks` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_projects` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_accomplishments` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_feedbacks` tinyint(1) NOT NULL DEFAULT 1,
  `can_view_reports` tinyint(1) NOT NULL DEFAULT 1,
  `can_manage_orgs` tinyint(1) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`, `base_role`, `description`, `badge_style`, `can_manage_users`, `can_manage_tasks`, `can_manage_projects`, `can_manage_accomplishments`, `can_manage_feedbacks`, `can_view_reports`, `can_manage_orgs`, `is_system`, `created_at`) VALUES
(1, 'Admin', 'Admin', 'Full system-wide administrator across all student organizations, student accounts, and role library.', 'badge-role-admin', 1, 1, 1, 1, 1, 1, 1, 1, '2026-10-21 03:20:22'),
(2, 'President', 'President', 'Chief executive leader of an organization. Can manage organization officers and assign/manage tasks.', 'badge-role-president', 1, 1, 1, 1, 1, 1, 0, 1, '2026-10-21 03:20:22'),
(3, 'Vice President', 'Vice President', 'Co-executive leader of an organization. Can manage organization officers and assign/manage tasks.', 'badge-role-vp', 1, 1, 1, 1, 1, 1, 0, 1, '2026-10-21 03:20:23'),
(4, 'Secretary', 'Secretary', 'Organization secretary handling documentation, projects, accomplishments, feedbacks, and reports.', 'badge-role-secretary', 0, 0, 1, 1, 1, 1, 0, 1, '2026-10-21 03:20:23'),
(5, 'Treasurer', 'Treasurer', 'Organization treasurer handling financial records, projects, accomplishments, feedbacks, and reports.', 'badge-role-treasurer', 0, 0, 1, 1, 1, 1, 0, 1, '2026-10-21 03:20:23'),
(6, 'Officer', 'Officer', 'General organization officer with access to assigned tasks, projects, accomplishments, feedbacks, and reports.', 'badge-role-officer', 0, 0, 1, 1, 1, 1, 0, 1, '2026-10-21 03:20:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accomplishments`
--
ALTER TABLE `accomplishments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `accomplishment_logs`
--
ALTER TABLE `accomplishment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `acc_id` (`acc_id`);

--
-- Indexes for table `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `feedback_logs`
--
ALTER TABLE `feedback_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `feedback_id` (`feedback_id`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `project_logs`
--
ALTER TABLE `project_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_lrn` (`student_lrn`);

--
-- Indexes for table `student_logs`
--
ALTER TABLE `student_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `org_id` (`org_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `task_logs`
--
ALTER TABLE `task_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `org_id` (`org_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accomplishments`
--
ALTER TABLE `accomplishments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `accomplishment_logs`
--
ALTER TABLE `accomplishment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedbacks`
--
ALTER TABLE `feedbacks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback_logs`
--
ALTER TABLE `feedback_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_logs`
--
ALTER TABLE `project_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_logs`
--
ALTER TABLE `student_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `task_logs`
--
ALTER TABLE `task_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accomplishments`
--
ALTER TABLE `accomplishments`
  ADD CONSTRAINT `accomplishments_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `accomplishment_logs`
--
ALTER TABLE `accomplishment_logs`
  ADD CONSTRAINT `accomplishment_logs_ibfk_1` FOREIGN KEY (`acc_id`) REFERENCES `accomplishments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedbacks`
--
ALTER TABLE `feedbacks`
  ADD CONSTRAINT `feedbacks_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback_logs`
--
ALTER TABLE `feedback_logs`
  ADD CONSTRAINT `feedback_logs_ibfk_1` FOREIGN KEY (`feedback_id`) REFERENCES `feedbacks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_logs`
--
ALTER TABLE `project_logs`
  ADD CONSTRAINT `project_logs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_logs`
--
ALTER TABLE `student_logs`
  ADD CONSTRAINT `student_logs_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `task_logs`
--
ALTER TABLE `task_logs`
  ADD CONSTRAINT `task_logs_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
