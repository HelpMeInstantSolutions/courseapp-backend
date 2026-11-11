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

        // Get owner bearer token (auto refreshed)
        $token = getDailymotionAccessToken();

        $queryDm = function($videoId, $bearer) {
            $url = "https://api.dailymotion.com/video/" . $videoId . "?fields=id,title,duration,thumbnail_url,private";

            // Method 1: native stream socket (bypasses curl proxy/route issues on shared hosts)
            $headers = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json\r\n";
            if ($bearer) {
                $headers .= "Authorization: Bearer " . $bearer . "\r\n";
            }
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => $headers,
                    'timeout' => 10,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $ctx = stream_context_create($opts);
            $res = @file_get_contents($url, false, $ctx);
            if ($res) {
                $decoded = json_decode($res, true);
                if ($decoded && is_array($decoded) && (isset($decoded['id']) || isset($decoded['error']))) {
                    return $decoded;
                }
            }

            // Method 2: cURL
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $cHeaders = ["Accept: application/json"];
            if ($bearer) {
                $cHeaders[] = "Authorization: Bearer " . $bearer;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $cHeaders);
            $cRes = curl_exec($ch);
            $curlErr = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $decoded = $cRes ? json_decode($cRes, true) : null;
            if (!$decoded) {
                return [
                    '_raw' => $cRes ?: $res,
                    '_curl_err' => $curlErr,
                    '_http_code' => $httpCode,
                    '_bearer_len' => $bearer ? strlen($bearer) : 0
                ];
            }
            return $decoded;
        };

        $json = $queryDm($dmId, $token);

        // If access token was expired / invalid, force refresh and try once more
        if (isset($json['error'])) {
            $errMsg = strtolower($json['error']['message'] ?? '');
            $errCode = (int)($json['error']['code'] ?? 0);
            if (strpos($errMsg, 'token') !== false || strpos($errMsg, 'expired') !== false || $errCode === 401) {
                $token = getDailymotionAccessToken(true);
                $json = $queryDm($dmId, $token);
            }
        }

        // If still failing with token, fallback to unauthenticated query (public videos)
        if (!isset($json['id']) && $token) {
            $fallback = $queryDm($dmId, null);
            if (isset($fallback['id'])) {
                $json = $fallback;
            }
        }

        if ($json && isset($json['id'])) {
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
            $err = $json['error']['message'] ?? (!empty($json['_curl_err']) ? $json['_curl_err'] : "Video not found on Dailymotion. Please verify ID or URL.");
            if ($token && is_array($json)) {
                $json['_token'] = $token;
            }
            sendResponse(false, $err, $json, 404);
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
