<?php
// backend/api/courses.php - Multilevel Batch, Subject, Chapter & Video API
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
        $sql = "SELECT c.*, cat.title as category_name, 
                       (SELECT COUNT(*) FROM subjects s WHERE s.course_id = c.id AND s.status = 1) as total_subjects,
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

        sendResponse(true, "Batches retrieved successfully", $courses);
        break;

    case 'subjects':
        $courseId = (int)($_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            sendResponse(false, "Batch ID is required", null, 400);
        }

        $stmt = $pdo->prepare("SELECT s.*, 
                               (SELECT COUNT(*) FROM chapters ch WHERE ch.subject_id = s.id AND (ch.parent_id = 0 OR ch.parent_id IS NULL) AND ch.status = 1) as total_chapters,
                               (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id AND v.status = 1) as total_videos 
                               FROM subjects s 
                               WHERE s.course_id = ? AND s.status = 1 
                               ORDER BY s.sort_order ASC, s.id ASC");
        $stmt->execute([$courseId]);
        $subjects = $stmt->fetchAll();

        sendResponse(true, "Subjects retrieved successfully", $subjects);
        break;

    case 'chapters':
        $subjectId = (int)($_GET['subject_id'] ?? 0);
        $courseId = (int)($_GET['course_id'] ?? 0);
        $userId = (int)($_GET['user_id'] ?? 0);

        if ($subjectId <= 0 && $courseId <= 0) {
            sendResponse(false, "Subject ID or Batch ID is required", null, 400);
        }

        // 1. Fetch parent chapters
        $chSql = "SELECT ch.*, s.title as subject_title, s.faculty_name
                  FROM chapters ch 
                  LEFT JOIN subjects s ON ch.subject_id = s.id
                  WHERE ch.status = 1 AND (ch.parent_id = 0 OR ch.parent_id IS NULL)";
        $chParams = [];
        if ($subjectId > 0) {
            $chSql .= " AND ch.subject_id = ?";
            $chParams[] = $subjectId;
        } else {
            $chSql .= " AND ch.course_id = ?";
            $chParams[] = $courseId;
        }
        $chSql .= " ORDER BY ch.sort_order ASC, ch.id ASC";

        $stmt = $pdo->prepare($chSql);
        $stmt->execute($chParams);
        $mainChapters = $stmt->fetchAll();

        // 2. Fetch all sub-chapters / folders
        $subSql = "SELECT ch.* FROM chapters ch WHERE ch.status = 1 AND ch.parent_id > 0";
        $subParams = [];
        if ($subjectId > 0) {
            $subSql .= " AND ch.subject_id = ?";
            $subParams[] = $subjectId;
        } else {
            $subSql .= " AND ch.course_id = ?";
            $subParams[] = $courseId;
        }
        $subSql .= " ORDER BY ch.sort_order ASC, ch.id ASC";
        $subStmt = $pdo->prepare($subSql);
        $subStmt->execute($subParams);
        $allSubChapters = $subStmt->fetchAll();

        $subsByParent = [];
        foreach ($allSubChapters as $sc) {
            $subsByParent[(int)$sc['parent_id']][] = $sc;
        }

        // 3. Fetch all videos for this subject/course with optional user progress
        $vSql = "SELECT v.*, 
                        p.last_position_seconds, p.percent_watched, p.is_completed
                 FROM videos v 
                 LEFT JOIN user_video_progress p ON p.video_id = v.id AND p.user_id = ?
                 WHERE v.status = 1";
        $vParams = [$userId];

        if ($subjectId > 0) {
            $vSql .= " AND v.subject_id = ?";
            $vParams[] = $subjectId;
        } else {
            $vSql .= " AND v.course_id = ?";
            $vParams[] = $courseId;
        }
        $vSql .= " ORDER BY v.sort_order ASC, v.id ASC";

        $vStmt = $pdo->prepare($vSql);
        $vStmt->execute($vParams);
        $allVideos = $vStmt->fetchAll();

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

        // 4. Assemble hierarchy
        $structured = [];
        foreach ($mainChapters as $mch) {
            $mchId = (int)$mch['id'];
            $directVideos = $videosByChapter[$mchId] ?? [];
            $mch['videos'] = $directVideos;
            $mch['total_videos'] = count($directVideos);

            // Sub chapters / folders inside this chapter
            $subFolders = $subsByParent[$mchId] ?? [];
            foreach ($subFolders as &$subF) {
                $subFId = (int)$subF['id'];
                $subVideos = $videosByChapter[$subFId] ?? [];
                $subF['videos'] = $subVideos;
                $subF['total_videos'] = count($subVideos);
            }
            $mch['sub_chapters'] = $subFolders;
            $mch['total_sub_chapters'] = count($subFolders);

            $structured[] = $mch;
        }

        // Unassigned videos fallback chapter
        if (!empty($unassignedVideos)) {
            $structured[] = [
                'id' => 0,
                'course_id' => $courseId,
                'subject_id' => $subjectId,
                'parent_id' => 0,
                'title' => 'General / Additional Lectures',
                'description' => 'Uncategorized lessons',
                'pdf_url' => '',
                'sort_order' => 999,
                'total_videos' => count($unassignedVideos),
                'total_sub_chapters' => 0,
                'sub_chapters' => [],
                'videos' => $unassignedVideos
            ];
        }

        sendResponse(true, "Chapters retrieved successfully", [
            'chapters' => $structured,
            'total_chapters' => count($structured),
            'total_videos' => count($allVideos)
        ]);
        break;

    case 'detail':
        $id = (int)($_GET['id'] ?? 0);
        $userId = (int)($_GET['user_id'] ?? 0);
        if ($id <= 0) {
            sendResponse(false, "Invalid batch ID", null, 400);
        }

        $stmt = $pdo->prepare("SELECT c.*, cat.title as category_name,
                               (SELECT COUNT(*) FROM subjects s WHERE s.course_id = c.id AND s.status = 1) as total_subjects,
                               (SELECT COUNT(*) FROM chapters ch WHERE ch.course_id = c.id AND ch.status = 1) as total_chapters,
                               (SELECT COUNT(*) FROM videos v WHERE v.course_id = c.id AND v.status = 1) as total_videos 
                               FROM courses c 
                               LEFT JOIN categories cat ON c.category_id = cat.id 
                               WHERE c.id = ?");
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        if (!$course) {
            sendResponse(false, "Batch not found", null, 404);
        }

        // Fetch subjects
        $subStmt = $pdo->prepare("SELECT s.*, 
                                  (SELECT COUNT(*) FROM chapters ch WHERE ch.subject_id = s.id AND (ch.parent_id = 0 OR ch.parent_id IS NULL) AND ch.status = 1) as total_chapters,
                                  (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id AND v.status = 1) as total_videos 
                                  FROM subjects s 
                                  WHERE s.course_id = ? AND s.status = 1 
                                  ORDER BY s.sort_order ASC, s.id ASC");
        $subStmt->execute([$id]);
        $course['subjects'] = $subStmt->fetchAll();

        // Also fetch flat chapters & videos for backward compatibility with older app version
        $chStmt = $pdo->prepare("SELECT id, course_id, subject_id, parent_id, title, description, pdf_url, sort_order 
                                 FROM chapters 
                                 WHERE course_id = ? AND status = 1 
                                 ORDER BY sort_order ASC, id ASC");
        $chStmt->execute([$id]);
        $chapters = $chStmt->fetchAll();

        $vStmt = $pdo->prepare("SELECT v.*, p.last_position_seconds, p.percent_watched, p.is_completed
                                FROM videos v 
                                LEFT JOIN user_video_progress p ON p.video_id = v.id AND p.user_id = ?
                                WHERE v.course_id = ? AND v.status = 1 
                                ORDER BY v.sort_order ASC, v.id ASC");
        $vStmt->execute([$userId, $id]);
        $allVideos = $vStmt->fetchAll();

        $videosByChapter = [];
        foreach ($allVideos as $v) {
            $cId = (int)($v['chapter_id'] ?? 0);
            $videosByChapter[$cId][] = $v;
        }

        foreach ($chapters as &$ch) {
            $chId = (int)$ch['id'];
            $ch['videos'] = $videosByChapter[$chId] ?? [];
            $ch['total_videos'] = count($ch['videos']);
        }

        $course['chapters'] = $chapters;
        $course['videos'] = $allVideos;

        sendResponse(true, "Batch details retrieved", $course);
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
