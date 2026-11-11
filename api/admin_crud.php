<?php
// backend/api/admin_crud.php
require_once __DIR__ . '/config.php';

session_start();

// Helper to get JSON input
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'login':
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$username || !$password) {
            sendResponse(false, "Username and password are required", null, 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        // Check password or fallback to admin123
        if ($admin && (password_verify($password, $admin['password']) || ($username === 'admin' && $password === 'admin123'))) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_name'] = $admin['name'];
            sendResponse(true, "Login successful! Welcome back.", [
                'username' => $admin['username'],
                'name' => $admin['name']
            ]);
        } else {
            sendResponse(false, "Invalid credentials. Please try again.", null, 401);
        }
        break;

    case 'logout':
        session_destroy();
        sendResponse(true, "Logged out successfully");
        break;

    case 'fetch_dm_info':
        $dmId = trim($input['dailymotion_id'] ?? ($_GET['dailymotion_id'] ?? ''));
        if (!$dmId) {
            sendResponse(false, "Dailymotion ID is required", null, 400);
        }

        if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
            $dmId = $matches[1];
        }

        // Get owner bearer token from json
        $tokenFile = __DIR__ . '/../database/dm_user_token.json';
        $token = null;
        if (file_exists($tokenFile)) {
            $tData = json_decode(file_get_contents($tokenFile), true);
            $token = $tData['access_token'] ?? null;
        }

        $url = "https://api.dailymotion.com/video/" . $dmId . "?fields=id,title,duration,thumbnail_url,private";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($token) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $token]);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $json = json_decode($res, true);
            if (isset($json['id'])) {
                // Convert seconds to MM:SS or HH:MM:SS
                $secs = (int)($json['duration'] ?? 0);
                $hrs = floor($secs / 3600);
                $mins = floor(($secs % 3600) / 60);
                $remSecs = $secs % 60;

                if ($hrs > 0) {
                    $formattedDuration = sprintf("%02d:%02d:%02d", $hrs, $mins, $remSecs);
                } else {
                    $formattedDuration = sprintf("%02d:%02d", $mins, $remSecs);
                }

                sendResponse(true, "Video details fetched automatically!", [
                    'id' => $json['id'],
                    'title' => $json['title'] ?? '',
                    'duration' => $formattedDuration,
                    'seconds' => $secs,
                    'thumbnail' => $json['thumbnail_url'] ?? '',
                    'private' => $json['private'] ?? false
                ]);
            } else {
                sendResponse(false, $json['error']['message'] ?? "Video not found", null, 404);
            }
        } else {
            sendResponse(false, "Could not connect to Dailymotion", null, 500);
        }
        break;

    case 'stats':
        $totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $totalChapters = $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn();
        $totalVideos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
        $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        sendResponse(true, "Stats loaded", [
            'courses' => $totalCourses,
            'chapters' => $totalChapters,
            'videos' => $totalVideos,
            'categories' => $totalCategories,
            'users' => $totalUsers
        ]);
        break;

    case 'list_chapters':
        $courseId = (int)($input['course_id'] ?? ($_GET['course_id'] ?? 0));
        if ($courseId > 0) {
            $stmt = $pdo->prepare("SELECT ch.*, c.title as course_title,
                                   (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id) as video_count 
                                   FROM chapters ch 
                                   LEFT JOIN courses c ON ch.course_id = c.id 
                                   WHERE ch.course_id = ? 
                                   ORDER BY ch.sort_order ASC, ch.id ASC");
            $stmt->execute([$courseId]);
        } else {
            $stmt = $pdo->query("SELECT ch.*, c.title as course_title,
                                 (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id) as video_count 
                                 FROM chapters ch 
                                 LEFT JOIN courses c ON ch.course_id = c.id 
                                 ORDER BY ch.course_id DESC, ch.sort_order ASC");
        }
        $chapters = $stmt->fetchAll();
        sendResponse(true, "Chapters retrieved successfully", $chapters);
        break;

    case 'save_chapter':
        $id = (int)($input['id'] ?? 0);
        $courseId = (int)($input['course_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $sortOrder = (int)($input['sort_order'] ?? 1);

        if ($courseId <= 0 || empty($title)) {
            sendResponse(false, "Course and Chapter Title are required", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE chapters SET course_id = ?, title = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$courseId, $title, $description, $sortOrder, $id]);
            sendResponse(true, "Chapter updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO chapters (course_id, title, description, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$courseId, $title, $description, $sortOrder]);
            sendResponse(true, "New Chapter created successfully!");
        }
        break;

    case 'delete_chapter':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid Chapter ID", null, 400);

        // Reset chapter_id on attached videos to 0 (or unassigned)
        $pdo->prepare("UPDATE videos SET chapter_id = 0 WHERE chapter_id = ?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "Chapter deleted successfully!");
        break;

    case 'list_users':
        $stmt = $pdo->query("SELECT id, name, email, phone, status, created_at FROM users ORDER BY id DESC");
        $users = $stmt->fetchAll();
        sendResponse(true, "Users list retrieved", $users);
        break;

    case 'delete_user':
        $userId = (int)($input['id'] ?? 0);
        if ($userId <= 0) sendResponse(false, "Invalid User ID", null, 400);
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        sendResponse(true, "Student account deleted successfully!");
        break;

    case 'save_course':
        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $categoryId = (int)($input['category_id'] ?? 1);
        $description = trim($input['description'] ?? '');
        $thumbnail = trim($input['thumbnail'] ?? '');
        $price = (float)($input['price'] ?? 0.0);

        if (!$title) {
            sendResponse(false, "Course title is required", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, category_id = ?, description = ?, thumbnail = ?, price = ? WHERE id = ?");
            $stmt->execute([$title, $categoryId, $description, $thumbnail, $price, $id]);
            sendResponse(true, "Course updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO courses (title, category_id, description, thumbnail, price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $categoryId, $description, $thumbnail, $price]);
            sendResponse(true, "New course created successfully!");
        }
        break;

    case 'delete_course':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid Course ID", null, 400);
        
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "Course and associated videos deleted successfully!");
        break;

    case 'save_video':
        $id = (int)($input['id'] ?? 0);
        $courseId = (int)($input['course_id'] ?? 0);
        $chapterId = (int)($input['chapter_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $dmId = trim($input['dailymotion_id'] ?? '');
        $duration = trim($input['duration'] ?? '15:00');
        $isPreview = (int)($input['is_free_preview'] ?? 0);
        $sortOrder = (int)($input['sort_order'] ?? 1);

        if ($courseId <= 0 || !$title || !$dmId) {
            sendResponse(false, "Course ID, video title and Dailymotion Video ID are required", null, 400);
        }

        // Clean DM ID
        if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
            $dmId = $matches[1];
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE videos SET course_id = ?, chapter_id = ?, title = ?, dailymotion_id = ?, duration = ?, is_free_preview = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$courseId, $chapterId, $title, $dmId, $duration, $isPreview, $sortOrder, $id]);
            sendResponse(true, "Lesson video updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO videos (course_id, chapter_id, title, dailymotion_id, duration, is_free_preview, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$courseId, $chapterId, $title, $dmId, $duration, $isPreview, $sortOrder]);
            sendResponse(true, "Lesson video added successfully!");
        }
        break;

    case 'delete_video':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid Video ID", null, 400);

        $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "Video deleted successfully!");
        break;

    default:
        sendResponse(false, "Invalid admin action", null, 400);
        break;
}
