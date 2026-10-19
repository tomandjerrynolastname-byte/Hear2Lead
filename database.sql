-- Student Leadership Management System Database Schema (Clean Version with Logo Upload Support)

CREATE DATABASE IF NOT EXISTS `student_leadership_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `student_leadership_db`;

-- --------------------------------------------------------
-- Table: organizations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `acronym` VARCHAR(20) NOT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `category` VARCHAR(50) DEFAULT 'Student Council',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Data: Organizations
INSERT INTO `organizations` (`id`, `name`, `acronym`, `description`, `category`) VALUES
(1, 'Supreme Secondary Learner Government', 'SSLG', 'The highest student governing body empowering student voice and leadership.', 'Student Council'),
(2, 'Youth for Environment in Schools Organization', 'YES-O', 'Dedicated to environmental awareness, protection, and sustainability.', 'Special Interest'),
(3, 'Computer Studies Society', 'CSS', 'Promoting technological literacy, innovation, and digital excellence.', 'Academic');

-- --------------------------------------------------------
-- Table: users (Leadership Officers & Admins)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_id` INT NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('President', 'Vice President', 'Secretary', 'Treasurer', 'Officer', 'Admin') DEFAULT 'Officer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Officer Accounts (Password is 'admin123' for all demo accounts)
INSERT INTO `users` (`id`, `org_id`, `full_name`, `username`, `email`, `password`, `role`) VALUES
(1, 1, 'Maria Santos', 'sslg_leader', 'president@sslg.edu.ph', '$2y$10$4nJ.i4wlhb/g9xR8fQ1h4.sY1Z2dC54qB6G7V8W9X0Y1Z2dC54qB6', 'President'),
(2, 1, 'Juan Dela Cruz', 'sslg_officer', 'officer@sslg.edu.ph', '$2y$10$8Q6/91M1b.Z5l6M7N8O9P.qR1S2T3U4V5W6X7Y8Z9A0B1C2D3E4F5', 'Secretary'),
(3, 2, 'Angela Reyes', 'yeso_leader', 'yeso@school.edu.ph', '$2y$10$8Q6/91M1b.Z5l6M7N8O9P.qR1S2T3U4V5W6X7Y8Z9A0B1C2D3E4F5', 'President');

-- --------------------------------------------------------
-- Table: projects (Projects & Programs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('Planning', 'Ongoing', 'Accomplished', 'On Hold') DEFAULT 'Planning',
  `progress_percent` INT DEFAULT 0,
  `start_date` DATE DEFAULT NULL,
  `target_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: tasks (Task Organizer / Tracker for Leadership)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_id` INT NOT NULL,
  `project_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `status` ENUM('To Do', 'In Progress', 'Accomplished', 'Overdue') DEFAULT 'To Do',
  `priority` ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
  `deadline` DATE NOT NULL,
  `assigned_to` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: task_logs (Task Creation & Update History with Timestamps)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `task_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `user_name` VARCHAR(100) NOT NULL,
  `user_role` VARCHAR(50) NOT NULL,
  `action_type` VARCHAR(50) NOT NULL,
  `log_details` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: accomplishments
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `accomplishments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NOT NULL,
  `impact_summary` VARCHAR(255) DEFAULT NULL,
  `date_accomplished` DATE NOT NULL,
  `badge_tag` VARCHAR(50) DEFAULT 'Success',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: students (Student Portal Accounts)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_lrn` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(100) NOT NULL,
  `grade_section` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Student Accounts (Password: 'student123')
INSERT INTO `students` (`id`, `student_lrn`, `full_name`, `grade_section`, `email`, `password`) VALUES
(1, '2026-0001', 'Alex Morgan', 'Grade 11 - STEM', 'alex.morgan@student.edu.ph', '$2y$10$8Q6/91M1b.Z5l6M7N8O9P.qR1S2T3U4V5W6X7Y8Z9A0B1C2D3E4F5'),
(2, '2026-0002', 'Samantha Cruz', 'Grade 10 - Rizal', 'samantha.cruz@student.edu.ph', '$2y$10$8Q6/91M1b.Z5l6M7N8O9P.qR1S2T3U4V5W6X7Y8Z9A0B1C2D3E4F5');

-- --------------------------------------------------------
-- Table: feedbacks (Suggestions & Feedbacks from Students - Verified)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedbacks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `org_id` INT NOT NULL,
  `student_id` INT DEFAULT NULL,
  `student_name` VARCHAR(100) NOT NULL,
  `student_email` VARCHAR(100) DEFAULT NULL,
  `student_grade_section` VARCHAR(50) DEFAULT NULL,
  `category` ENUM('Suggestion', 'Complaint', 'Inquiry', 'Appreciation') DEFAULT 'Suggestion',
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('Pending', 'Under Review', 'In Action', 'Resolved') DEFAULT 'Pending',
  `officer_response` TEXT DEFAULT NULL,
  `responded_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`org_id`) REFERENCES `organizations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table: feedback_logs (Multiple Officer Replies with Timestamps)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `feedback_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `feedback_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `officer_name` VARCHAR(100) NOT NULL,
  `officer_role` VARCHAR(50) NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `reply_message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`feedback_id`) REFERENCES `feedbacks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
