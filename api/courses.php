<?php
// backend/api/courses.php - Course, Chapter, and Video API (Course -> Chapter -> Video)
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $sql = "SELECT c.*, cat.title as category_name, 
                       (SELECT COUNT(*) FROM chapters ch WHERE ch.course_id = c.id AND ch.status = 1) as total_chapters,
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

        $stmt = $pdo->prepare("SELECT c.*, cat.title as category_name,
                               (SELECT COUNT(*) FROM chapters ch WHERE ch.course_id = c.id AND ch.status = 1) as total_chapters,
                               (SELECT COUNT(*) FROM videos v WHERE v.course_id = c.id AND v.status = 1) as total_videos 
                               FROM courses c 
                               LEFT JOIN categories cat ON c.category_id = cat.id 
                               WHERE c.id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        if (!$course) {
            sendResponse(false, "Course not found", null, 404);
        }

        // Fetch chapters for this course
        $chStmt = $pdo->prepare("SELECT id, course_id, title, description, sort_order 
                                 FROM chapters 
                                 WHERE course_id = ? AND status = 1 
                                 ORDER BY sort_order ASC, id ASC");
        $chStmt->execute([$id]);
        $chapters = $chStmt->fetchAll();

        // Fetch all videos for this course
        $vStmt = $pdo->prepare("SELECT id, course_id, chapter_id, title, description, dailymotion_id, duration, sort_order, is_free_preview 
                                FROM videos 
                                WHERE course_id = ? AND status = 1 
                                ORDER BY sort_order ASC, id ASC");
        $vStmt->execute([$id]);
        $allVideos = $vStmt->fetchAll();

        // Group videos by chapter
        $videosByChapter = [];
        $unassignedVideos = [];

        foreach ($allVideos as $v) {
            $cId = (int)($v['chapter_id'] ?? 0);
            if ($cId > 0) {
                $videosByChapter[$cId][] = $v;
            } else {
                $unassignedVideos[] = $v;
            }
        }

        $groupedChapters = [];
        foreach ($chapters as $ch) {
            $chId = (int)$ch['id'];
            $ch['videos'] = $videosByChapter[$chId] ?? [];
            $ch['total_videos'] = count($ch['videos']);
            $groupedChapters[] = $ch;
        }

        // If any videos are unassigned (or no chapters exist), wrap them in a chapter
        if (!empty($unassignedVideos)) {
            $groupedChapters[] = [
                'id' => 0,
                'course_id' => $id,
                'title' => empty($chapters) ? 'All Lectures' : 'General / Additional Lectures',
                'description' => 'Course lectures',
                'sort_order' => 999,
                'total_videos' => count($unassignedVideos),
                'videos' => $unassignedVideos
            ];
        }

        $course['chapters'] = $groupedChapters;
        $course['videos'] = $allVideos; // preserve flat list for backwards compatibility

        sendResponse(true, "Course details retrieved", $course);
        break;

    case 'chapters':
        $courseId = (int)($_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            sendResponse(false, "Course ID is required", null, 400);
        }
        $stmt = $pdo->prepare("SELECT ch.*, 
                               (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id AND v.status = 1) as total_videos 
                               FROM chapters ch 
                               WHERE ch.course_id = ? AND ch.status = 1 
                               ORDER BY ch.sort_order ASC, ch.id ASC");
        $stmt->execute([$courseId]);
        $chapters = $stmt->fetchAll();
        sendResponse(true, "Chapters retrieved", $chapters);
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
