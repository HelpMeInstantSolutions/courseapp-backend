-- Database Schema for Course App (courseapp.mybhiwani.in)
CREATE DATABASE IF NOT EXISTS `courseapp_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `courseapp_db`;

-- Admins Table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `icon` VARCHAR(100) DEFAULT 'bi-collection-play',
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Courses Table
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `thumbnail` VARCHAR(500) DEFAULT NULL,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Lessons / Videos Table
CREATE TABLE IF NOT EXISTS `videos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `dailymotion_id` VARCHAR(100) NOT NULL COMMENT 'e.g. x8xyz12 or k3abcde',
  `dailymotion_url` VARCHAR(500) DEFAULT NULL,
  `duration` VARCHAR(50) DEFAULT '15:00',
  `sort_order` INT DEFAULT 0,
  `is_free_preview` TINYINT(1) DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Admin (default: admin / admin123)
INSERT INTO `admins` (`username`, `password`, `name`) 
VALUES ('admin', '$2y$10$tZz27lUu88.3d37jD2o1Qel3RzQx1qH2t97xOfeZqN0Nl9GvI549S', 'Super Admin')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Sample Categories
INSERT INTO `categories` (`id`, `title`, `icon`) VALUES
(1, 'Haryana CET & HSSC', 'bi-book-half'),
(2, 'SSC & Railway Special', 'bi-award'),
(3, 'Computer & Coding Basics', 'bi-laptop')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Sample Course
INSERT INTO `courses` (`id`, `category_id`, `title`, `description`, `thumbnail`, `price`) VALUES
(1, 1, 'HSSC CET Super Batch 2026', 'Complete syllabus preparation with daily lectures, private class videos and mock tests.', 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&auto=format&fit=crop&q=80', 499.00),
(2, 2, 'Maths & Reasoning Shortcut Masterclass', 'Shortcut tricks and previous year solved questions for all competitive exams.', 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&auto=format&fit=crop&q=80', 299.00)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Seed Sample Videos (Dailymotion private IDs)
INSERT INTO `videos` (`id`, `course_id`, `title`, `description`, `dailymotion_id`, `duration`, `sort_order`, `is_free_preview`) VALUES
(1, 1, 'Lecture 01: Haryana History & Important Dynasties', 'Introduction to Ancient Haryana & Vedic Period landmarks.', 'x8q3pq9', '32:45', 1, 1),
(2, 1, 'Lecture 02: Haryana Geography, Rivers & Canals', 'Deep dive into Yamuna, Ghaggar and irrigation systems.', 'x2q3pq9', '28:10', 2, 0),
(3, 2, 'Speed Maths: 5-Second Square & Cube Root Tricks', 'Calculation speed hacks for competitive exams.', 'x8q3pq9', '18:20', 1, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;
