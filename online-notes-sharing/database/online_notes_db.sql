-- =======================================================================
-- NoteShare — Online Notes Sharing Platform (BCA Final Year Project)
-- Database Schema & Comprehensive Seed Data
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
    `bio` TEXT DEFAULT NULL,
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
    `icon` VARCHAR(50) DEFAULT 'bi-folder',
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
    `keywords` VARCHAR(255) DEFAULT '',
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
    INDEX `idx_notes_downloads` (`downloads`),
    INDEX `idx_notes_views` (`views`),
    INDEX `idx_notes_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 4: groups
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `creator_id` INT NOT NULL,
    `category_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`creator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_groups_creator` (`creator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 5: group_members
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `group_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` ENUM('admin', 'member') DEFAULT 'member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_group_user` (`group_id`, `user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 6: likes
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `likes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_like` (`user_id`, `note_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 7: saved_notes
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `saved_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_save` (`user_id`, `note_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 8: downloads
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `downloads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `note_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    INDEX `idx_downloads_note` (`note_id`),
    INDEX `idx_downloads_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 9: ratings
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ratings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_id` INT NOT NULL,
    `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `review` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_note_rating` (`user_id`, `note_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 10: comments
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_id` INT NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    INDEX `idx_comments_note` (`note_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 11: note_shares
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `note_shares` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `note_id` INT NOT NULL,
    `shared_by` INT NOT NULL,
    `share_type` ENUM('email', 'group', 'link') NOT NULL,
    `target_email` VARCHAR(150) DEFAULT NULL,
    `target_group_id` INT DEFAULT NULL,
    `share_token` VARCHAR(64) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`shared_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`target_group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
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
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_notif_user_unread` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 13: reports
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `note_id` INT NOT NULL,
    `reason` ENUM('incorrect_info', 'duplicate', 'inappropriate', 'copyright', 'other') NOT NULL,
    `description` TEXT,
    `status` ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    `admin_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`note_id`) REFERENCES `notes`(`id`) ON DELETE CASCADE,
    INDEX `idx_reports_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 14: study_goals (Unique Feature: Smart Study Mission)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_goals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `goal_date` DATE NOT NULL,
    `target_reads` INT DEFAULT 3,
    `target_downloads` INT DEFAULT 2,
    `target_uploads` INT DEFAULT 1,
    `completed_reads` INT DEFAULT 0,
    `completed_downloads` INT DEFAULT 0,
    `completed_uploads` INT DEFAULT 0,
    `status` ENUM('in_progress', 'completed') DEFAULT 'in_progress',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_goal_date` (`user_id`, `goal_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 15: study_activity (Audit log of user learning events)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_activity` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `activity_type` ENUM('read', 'preview', 'download', 'upload', 'like', 'rating', 'comment') NOT NULL,
    `reference_id` INT DEFAULT NULL,
    `activity_date` DATE NOT NULL,
    `points` INT DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_study_activity_user` (`user_id`, `activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 16: badges (Milestones & Gamification)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `badges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(50) NOT NULL,
    `symbol` VARCHAR(10) DEFAULT '🏆',
    `color` VARCHAR(20) DEFAULT '#4F46E5',
    `category` VARCHAR(50) DEFAULT 'learning',
    `requirement_type` ENUM('missions', 'reads', 'uploads', 'downloads', 'goals_completed', 'ratings') NOT NULL,
    `requirement_count` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 17: user_badges (Awarded Badges)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_badges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `badge_id` INT NOT NULL,
    `awarded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_badge` (`user_id`, `badge_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 18: contact_messages (Contact page inquiries)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- Table 19: admin_settings (System settings)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================================
-- Default Badges Data
-- =======================================================================
INSERT INTO `badges` (`code`, `title`, `description`, `icon`, `symbol`, `color`, `category`, `requirement_type`, `requirement_count`) VALUES
('first_step', 'First Step', 'Complete your first daily study mission', 'bi-seedling', '🌱', '#10B981', 'mission', 'missions', 1),
('knowledge_seeker', 'Knowledge Seeker', 'Read or preview 10 academic notes', 'bi-book-half', '📚', '#3B82F6', 'learning', 'reads', 10),
('knowledge_sharer', 'Knowledge Sharer', 'Upload 5 approved study resources', 'bi-rocket-takeoff-fill', '🚀', '#8B5CF6', 'sharing', 'uploads', 5),
('helpful_learner', 'Helpful Learner', 'Reach 25 downloads of your shared notes', 'bi-hand-thumbs-up-fill', '🤝', '#EC4899', 'impact', 'downloads', 25),
('goal_champion', 'Goal Champion', 'Complete daily goals on 5 different days', 'bi-bullseye', '🎯', '#F59E0B', 'streak', 'goals_completed', 5),
('master_reviewer', 'Master Reviewer', 'Leave 5 ratings and helpful reviews', 'bi-star-fill', '🌟', '#EAB308', 'community', 'ratings', 5)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- =======================================================================
-- Default Admin Settings
-- =======================================================================
INSERT INTO `admin_settings` (`setting_key`, `setting_value`) VALUES
('platform_name', 'NoteShare — Online Notes Sharing Platform'),
('platform_description', 'A collaborative student-driven academic repository for discovering, previewing, and exchanging lecture notes and study resources.'),
('allowed_file_types', 'pdf,doc,docx,ppt,pptx,zip'),
('max_file_size_mb', '25'),
('admin_passkey', 'ADMIN2026'),
('contact_email', 'support@noteshare.edu'),
('enable_study_missions', '1'),
('dark_mode_default', '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- =======================================================================
-- Default Categories
-- =======================================================================
INSERT INTO `categories` (`name`, `description`, `icon`) VALUES
('Computer Science & IT', 'Data structures, algorithms, web engineering, database systems, AI & ML lecture notes', 'bi-cpu-fill'),
('Mathematics & Statistics', 'Calculus, linear algebra, discrete math, probability and numerical methods', 'bi-calculator-fill'),
('Physics & Mechanics', 'Classical mechanics, electromagnetism, wave theory, thermodynamics and quantum physics', 'bi-atom'),
('Business & Management', 'Financial accounting, marketing, organizational behavior and business law', 'bi-briefcase-fill'),
('Electrical & Electronics', 'Circuit theory, signals and systems, digital logic, microprocessors and embedded systems', 'bi-lightning-charge-fill'),
('Chemistry & Material Science', 'Organic chemistry, biochemistry, thermodynamics and physical chemistry', 'bi-droplet-half'),
('Life Sciences & Medicine', 'Human anatomy, genetics, physiology, pathology and microbiology', 'bi-heart-pulse-fill'),
('Civil & Mechanical Eng', 'Fluid mechanics, strength of materials, thermodynamics and structural analysis', 'bi-gear-wide-connected')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);
