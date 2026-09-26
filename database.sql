-- =======================================================================
-- Online Notes Sharing Platform - Database Schema & Seed Data
-- Database: online_notes_db
-- =======================================================================

CREATE DATABASE IF NOT EXISTS `online_notes_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `online_notes_db`;

-- -----------------------------------------------------------------------
-- Table 1: users
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255) DEFAULT 'default_avatar.png',
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `status` ENUM('active', 'blocked') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 2: categories
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 3: notes
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `subject` VARCHAR(150) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(50) NOT NULL,
    `file_size` INT NOT NULL DEFAULT 0,
    `downloads` INT NOT NULL DEFAULT 0,
    `views` INT NOT NULL DEFAULT 0,
    `status` ENUM('approved', 'pending', 'rejected') DEFAULT 'approved',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
    INDEX `idx_notes_user` (`user_id`),
    INDEX `idx_notes_category` (`category_id`),
    INDEX `idx_notes_subject` (`subject`),
    INDEX `idx_notes_status` (`status`),
    INDEX `idx_notes_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 4: groups
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_groups_creator` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 5: group_members
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `group_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_group_user` (`group_id`, `user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_gm_group` (`group_id`),
    INDEX `idx_gm_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 6: likes
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_note_like` (`note_id`, `user_id`),
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_likes_note` (`note_id`),
    INDEX `idx_likes_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 7: saved_notes
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_saved_note` (`note_id`, `user_id`),
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_saved_note` (`note_id`),
    INDEX `idx_saved_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 8: downloads
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `downloads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `user_id` INT NULL,
    `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_downloads_note` (`note_id`),
    INDEX `idx_downloads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 9: ratings
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ratings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_note_rating` (`note_id`, `user_id`),
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_ratings_note` (`note_id`),
    INDEX `idx_ratings_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 10: comments
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_comments_note` (`note_id`),
    INDEX `idx_comments_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 11: note_shares
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_shares` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `sender_id` INT NOT NULL,
    `receiver_email` VARCHAR(150) NOT NULL,
    `message` TEXT NULL,
    `shared_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_shares_note` (`note_id`),
    INDEX `idx_shares_sender` (`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 12: notifications
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'general',
    `note_id` INT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE SET NULL,
    INDEX `idx_notif_user` (`user_id`),
    INDEX `idx_notif_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 13: reports
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `reason` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('pending', 'reviewed', 'dismissed', 'resolved') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_reports_note` (`note_id`),
    INDEX `idx_reports_user` (`user_id`),
    INDEX `idx_reports_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================
-- SEED DATA
-- =======================================================================

-- Users (passwords hashed using BCRYPT: admin123, student123)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `profile_image`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@notes.com', '$2y$10$8qz.OCz5AsQuQIYzO/BoJu0mxMGqYuXb.Hd7B1ylQs8GYOnhJAj0y', 'default_avatar.png', 'admin', 'active'),
(2, 'Alex Johnson', 'alex@student.com', '$2y$10$nrBasmowM3Rm6Zp9gNNFs.5Q9wHu8o.YuKQ1lacf4zelVCEceWUwG', 'default_avatar.png', 'user', 'active'),
(3, 'Sarah Williams', 'sarah@student.com', '$2y$10$nrBasmowM3Rm6Zp9gNNFs.5Q9wHu8o.YuKQ1lacf4zelVCEceWUwG', 'default_avatar.png', 'user', 'active'),
(4, 'David Miller', 'david@student.com', '$2y$10$nrBasmowM3Rm6Zp9gNNFs.5Q9wHu8o.YuKQ1lacf4zelVCEceWUwG', 'default_avatar.png', 'user', 'active')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Computer Science', 'Data structures, algorithms, web development, cloud computing, AI, and systems programming.'),
(2, 'Mathematics', 'Calculus, linear algebra, discrete mathematics, statistics, and numerical methods.'),
(3, 'Physics', 'Classical mechanics, electromagnetism, thermodynamics, quantum physics, and optics.'),
(4, 'Business & Economics', 'Financial accounting, microeconomics, macroeconomics, marketing strategy, and management.'),
(5, 'Medicine & Healthcare', 'Anatomy, pharmacology, biochemistry, physiology, and clinical diagnostics.'),
(6, 'Engineering', 'Electrical, mechanical, civil, chemical, and aerospace engineering study modules.'),
(7, 'Law & Governance', 'Constitutional law, criminal law, contracts, international relations, and legal writing.'),
(8, 'Literature & Languages', 'World literature, linguistics, grammar structures, composition, and communication.')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Sample Notes
INSERT INTO `notes` (`id`, `user_id`, `category_id`, `title`, `description`, `subject`, `file_name`, `file_path`, `file_type`, `file_size`, `downloads`, `views`, `status`) VALUES
(1, 2, 1, 'Data Structures & Algorithms Comprehensive Guide', 'In-depth notes on trees, graphs, dynamic programming, sorting, complexity analysis and interview patterns.', 'Data Structures', 'data_structures_guide.pdf', 'uploads/notes/sample_data_structures.pdf', 'application/pdf', 1048576, 142, 530, 'approved'),
(2, 3, 2, 'Multivariable Calculus & Vector Fields Cheatsheet', 'Complete lecture summary covering double integrals, Green theorem, Stokes theorem, and gradient vectors.', 'Calculus III', 'calculus_vector_cheatsheet.pdf', 'uploads/notes/sample_calculus_notes.pdf', 'application/pdf', 845200, 98, 320, 'approved'),
(3, 2, 3, 'Quantum Physics & Wave Mechanics Core Notes', 'Wave-particle duality, Schrodinger equation, potential wells, and quantum harmonic oscillators.', 'Quantum Mechanics', 'quantum_physics_core.pdf', 'uploads/notes/sample_quantum_physics.pdf', 'application/pdf', 1258291, 76, 280, 'approved'),
(4, 4, 4, 'Financial Accounting & Balance Sheet Analysis', 'Step-by-step balance sheet breakdowns, cash flow statements, ratio analysis, and corporate reporting.', 'Financial Accounting', 'financial_accounting_summary.pdf', 'uploads/notes/sample_finance_accounting.pdf', 'application/pdf', 924100, 64, 215, 'approved'),
(5, 3, 1, 'Machine Learning & Deep Neural Networks Handbook', 'Supervised learning, CNNs, RNNs, backpropagation derivations, regularization, and gradient descent.', 'Machine Learning', 'machine_learning_handbook.pdf', 'uploads/notes/sample_machine_learning.pdf', 'application/pdf', 1572864, 185, 680, 'approved')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Sample Study Groups
INSERT INTO `groups` (`id`, `name`, `description`, `created_by`) VALUES
(1, 'CS Algorithms & LeetCode Club', 'A collaborative study group for practicing data structures, algorithms, and technical interview preparation.', 2),
(2, 'Calculus & Advanced Math Circle', 'Peer problem solving for differential equations, linear algebra, and multivariable calculus exams.', 3),
(3, 'Pre-Med & Biology Focus Group', 'Comprehensive group for discussing biochemistry, human physiology, and MCAT prep questions.', 4)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Group Members
INSERT INTO `group_members` (`group_id`, `user_id`) VALUES
(1, 2),
(1, 3),
(1, 4),
(2, 3),
(2, 2),
(3, 4),
(3, 2)
ON DUPLICATE KEY UPDATE `group_id`=`group_id`;

-- Sample Likes
INSERT INTO `likes` (`note_id`, `user_id`) VALUES
(1, 3),
(1, 4),
(2, 2),
(3, 3),
(5, 2),
(5, 4)
ON DUPLICATE KEY UPDATE `note_id`=`note_id`;

-- Sample Saved Notes
INSERT INTO `saved_notes` (`note_id`, `user_id`) VALUES
(1, 3),
(2, 2),
(5, 2)
ON DUPLICATE KEY UPDATE `note_id`=`note_id`;

-- Sample Ratings
INSERT INTO `ratings` (`note_id`, `user_id`, `rating`, `review`) VALUES
(1, 3, 5, 'Super clear explanations of AVL trees and dynamic programming! Helped me ace my midterm exam.'),
(1, 4, 5, 'Exceptional quality notes. Clean diagrams and well-annotated code examples.'),
(2, 2, 4, 'Very well organized vector calculus summary. Really liked the examples on Green theorem.'),
(5, 2, 5, 'Best machine learning cheatsheet on the platform. Mathematical formulas are crystal clear.')
ON DUPLICATE KEY UPDATE `note_id`=`note_id`;

-- Sample Comments
INSERT INTO `comments` (`note_id`, `user_id`, `comment`) VALUES
(1, 3, 'Thank you so much for sharing this! Do you have sample problems for Graph Dijkstra?'),
(1, 2, 'Glad it helped Sarah! I will upload the Graph theory problem set this weekend.'),
(5, 4, 'The CNN filter visualizations in section 4 are brilliant. Great work!')
ON DUPLICATE KEY UPDATE `note_id`=`note_id`;

-- Sample Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `note_id`, `is_read`) VALUES
(2, 'Welcome to Online Notes Sharing!', 'Start exploring shared lecture notes or upload your own to help fellow students.', 'welcome', NULL, 1),
(2, 'New Rating Received', 'Sarah Williams rated your note "Data Structures & Algorithms Comprehensive Guide" 5 stars.', 'rating', 1, 0),
(3, 'Group Activity', 'Alex Johnson shared a new note inside "CS Algorithms & LeetCode Club".', 'group', 1, 0)
ON DUPLICATE KEY UPDATE `id`=`id`;
