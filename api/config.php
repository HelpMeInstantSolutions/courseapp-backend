<?php
// backend/api/config.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db_dir = __DIR__ . '/../database';
if (!file_exists($db_dir)) {
    mkdir($db_dir, 0777, true);
}

$db_file = $db_dir . '/courseapp.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 30
    ]);

    $pdo->exec("PRAGMA foreign_keys = ON;");
    $pdo->exec("PRAGMA journal_mode = WAL;");

    initDatabase($pdo);

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => false,
        'message' => 'SQLite DB connection error: ' . $e->getMessage()
    ]);
    exit();
}

function initDatabase($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            phone TEXT,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            icon TEXT DEFAULT 'bi-collection-play',
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
            title TEXT NOT NULL,
            description TEXT,
            thumbnail TEXT,
            price REAL DEFAULT 0.00,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            dailymotion_id TEXT NOT NULL,
            duration TEXT DEFAULT '15:00',
            sort_order INTEGER DEFAULT 1,
            is_free_preview INTEGER DEFAULT 0,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        );
    ");

    // Ensure admin user exists with password 'admin'
    $adminPwd = password_hash('admin', PASSWORD_BCRYPT);
    $checkAdmin = $pdo->prepare("SELECT id FROM admins WHERE username = 'admin'");
    $checkAdmin->execute();
    if (!$checkAdmin->fetch()) {
        $insertAdmin = $pdo->prepare("INSERT INTO admins (username, password, name) VALUES ('admin', ?, 'Super Admin')");
        $insertAdmin->execute([$adminPwd]);
    } else {
        // Update password to 'admin'
        $upAdmin = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
        $upAdmin->execute([$adminPwd]);
    }

    // Seed default categories if empty
    $cStmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($cStmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO categories (id, title, icon) VALUES
            (1, 'Haryana CET & HSSC', 'bi-book-half'),
            (2, 'SSC & Railway Special', 'bi-award'),
            (3, 'Computer & Coding Basics', 'bi-laptop');

            INSERT INTO courses (id, category_id, title, description, thumbnail, price) VALUES
            (1, 1, 'HSSC CET Super Batch 2026', 'Complete syllabus preparation with daily lectures, private class videos and mock tests.', 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=600&auto=format&fit=crop&q=80', 499.00),
            (2, 2, 'Maths & Reasoning Shortcut Masterclass', 'Shortcut tricks and previous year solved questions for all competitive exams.', 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&auto=format&fit=crop&q=80', 299.00);

            INSERT INTO videos (id, course_id, title, description, dailymotion_id, duration, sort_order, is_free_preview) VALUES
            (1, 1, 'Lecture 01: Haryana History & Important Dynasties', 'Introduction to Ancient Haryana & Vedic Period landmarks.', 'x98hbpm', '32:45', 1, 1),
            (2, 1, 'Lecture 02: Haryana Geography, Rivers & Canals', 'Deep dive into Yamuna, Ghaggar and irrigation systems.', 'x98hbpm', '28:10', 2, 0),
            (3, 2, 'Speed Maths: 5-Second Square & Cube Root Tricks', 'Calculation speed hacks for competitive exams.', 'x98hbpm', '18:20', 1, 1);
        ");
    }
}

function sendResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
