<?php
// backend/api/courses.php
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $sql = "SELECT c.*, cat.title as category_name, 
                       (SELECT COUNT(*) FROM videos v WHERE v.course_id = c.id AND v.status = 1) as total_videos 
                FROM courses c 
                LEFT JOIN categories cat ON c.category_id = cat.id 
                WHERE c.status = 1";
        
        $params = [];
        if ($categoryId > 0) {
            $sql .= " AND c.category_id = ?";
            $params[] = $categoryId;
        }
        $sql .= " ORDER BY c.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $courses = $stmt->fetchAll();

        sendResponse(true, "Courses retrieved successfully", $courses);
        break;

    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            sendResponse(false, "Invalid course ID", null, 400);
        }

        $stmt = $pdo->prepare("SELECT c.*, cat.title as category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        if (!$course) {
            sendResponse(false, "Course not found", null, 404);
        }

        // Get video lessons
        $vStmt = $pdo->prepare("SELECT id, course_id, title, description, dailymotion_id, duration, sort_order, is_free_preview FROM videos WHERE course_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC");
        $vStmt->execute([$id]);
        $course['videos'] = $vStmt->fetchAll();

        sendResponse(true, "Course details retrieved", $course);
        break;

    case 'categories':
        $stmt = $pdo->query("SELECT id, title, icon, (SELECT COUNT(*) FROM courses c WHERE c.category_id = categories.id AND c.status = 1) as course_count FROM categories WHERE status = 1 ORDER BY id ASC");
        $categories = $stmt->fetchAll();
        sendResponse(true, "Categories retrieved", $categories);
        break;

    default:
        sendResponse(false, "Invalid endpoint action", null, 400);
        break;
}
