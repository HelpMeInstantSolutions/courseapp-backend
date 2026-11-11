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
        $preferredAccountId = (int)($input['account_id'] ?? ($_GET['account_id'] ?? 0));
        if (!$dmId) {
            sendResponse(false, "Dailymotion ID is required", null, 400);
        }

        if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
            $dmId = $matches[1];
        }

        // Query across all connected Dailymotion accounts in pool
        $res = findDmAccountForVideo($pdo, $dmId, $preferredAccountId);

        if ($res && isset($res['video']['id'])) {
            $vData = $res['video'];
            $account = $res['account'];
            $token = $res['token'];

            $secs = (int)($vData['duration'] ?? 0);
            $hrs = floor($secs / 3600);
            $mins = floor(($secs % 3600) / 60);
            $remSecs = $secs % 60;

            if ($hrs > 0) {
                $formattedDuration = sprintf("%02d:%02d:%02d", $hrs, $mins, $remSecs);
            } else {
                $formattedDuration = sprintf("%02d:%02d", $mins, $remSecs);
            }

            sendResponse(true, "Video details fetched automatically!", [
                'id' => $vData['id'],
                'title' => $vData['title'] ?? '',
                'duration' => $formattedDuration,
                'seconds' => $secs,
                'thumbnail' => $vData['thumbnail_url'] ?? '',
                'private' => $vData['private'] ?? false,
                'account_id' => $account ? (int)$account['id'] : 0,
                'account_name' => $account ? $account['name'] : 'Public / Unlisted',
                'channel_name' => $account ? ($account['channel_name'] ?? '') : '',
                '_token' => $token
            ]);
        } else {
            // Get default account token for client-side direct browser fallback
            $fallbackToken = getDmAccountToken($pdo);
            sendResponse(false, "Video not found on any connected Dailymotion account. Please verify ID or URL.", [
                '_token' => $fallbackToken,
                'video_id' => $dmId
            ], 404);
        }
        break;

    case 'list_dm_accounts':
        $stmt = $pdo->query("
            SELECT a.id, a.name, a.channel_name, a.uid, a.email, a.client_id, a.is_default, a.status, a.created_at,
                   (SELECT COUNT(*) FROM videos v WHERE v.dm_account_id = a.id) as video_count
            FROM dm_accounts a
            ORDER BY a.is_default DESC, a.id ASC
        ");
        $accounts = $stmt->fetchAll();
        sendResponse(true, "Dailymotion accounts loaded", $accounts);
        break;

    case 'save_dm_account':
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        $refreshToken = trim($input['refresh_token'] ?? '');
        $isDefault = !empty($input['is_default']) ? 1 : 0;
        $status = isset($input['status']) ? (int)$input['status'] : 1;

        if (!$name || !$clientId || !$clientSecret || !$refreshToken) {
            sendResponse(false, "Account Name, API Key, API Secret, and Refresh Token are all required.", null, 400);
        }

        // Test credentials by requesting token
        $postData = http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        ]);
        $ch = curl_init('https://api.dailymotion.com/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $res = curl_exec($ch);
        curl_close($ch);

        $tData = $res ? json_decode($res, true) : null;
        if (!$tData || empty($tData['access_token'])) {
            $err = $tData['error']['message'] ?? "Failed to connect to Dailymotion. Please verify your API Key, Secret, and Refresh Token.";
            sendResponse(false, $err, $tData, 400);
        }

        $accessToken = $tData['access_token'];
        $expiresAt = time() + (int)($tData['expires_in'] ?? 36000);
        $newRefreshToken = $tData['refresh_token'] ?? $refreshToken;
        $uid = $tData['uid'] ?? '';

        // Query user info to get real channel name and email
        $ch = curl_init("https://api.dailymotion.com/user/me?fields=id,screenname,email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $accessToken, "Accept: application/json"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $uRes = curl_exec($ch);
        curl_close($ch);
        $uData = $uRes ? json_decode($uRes, true) : null;
        $channelName = $uData['screenname'] ?? $name;
        $email = $uData['email'] ?? '';
        if (!empty($uData['id'])) {
            $uid = $uData['id'];
        }

        if ($isDefault) {
            $pdo->exec("UPDATE dm_accounts SET is_default = 0");
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE dm_accounts SET name = ?, channel_name = ?, uid = ?, email = ?, client_id = ?, client_secret = ?, refresh_token = ?, access_token = ?, expires_at = ?, is_default = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $channelName, $uid, $email, $clientId, $clientSecret, $newRefreshToken, $accessToken, $expiresAt, $isDefault, $status, $id]);
            $msg = "Account '{$name}' updated successfully!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $channelName, $uid, $email, $clientId, $clientSecret, $newRefreshToken, $accessToken, $expiresAt, $isDefault, $status]);
            $id = $pdo->lastInsertId();
            $msg = "Dailymotion Account '{$channelName}' connected successfully!";
        }

        sendResponse(true, $msg, [
            'id' => $id,
            'name' => $name,
            'channel_name' => $channelName,
            'uid' => $uid,
            'email' => $email
        ]);
        break;

    case 'delete_dm_account':
        $id = (int)($input['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) {
            sendResponse(false, "Invalid account ID", null, 400);
        }

        $check = $pdo->prepare("SELECT * FROM dm_accounts WHERE id = ?");
        $check->execute([$id]);
        $acc = $check->fetch();
        if (!$acc) {
            sendResponse(false, "Account not found", null, 404);
        }

        $del = $pdo->prepare("DELETE FROM dm_accounts WHERE id = ?");
        $del->execute([$id]);

        // If deleted was default, make another one default
        if ($acc['is_default']) {
            $pdo->exec("UPDATE dm_accounts SET is_default = 1 WHERE id = (SELECT id FROM dm_accounts LIMIT 1)");
        }

        // Unlink videos or set dm_account_id = 0
        $up = $pdo->prepare("UPDATE videos SET dm_account_id = 0 WHERE dm_account_id = ?");
        $up->execute([$id]);

        sendResponse(true, "Dailymotion account removed successfully.");
        break;

    case 'exchange_dm_code':
        $rawCodeInput = trim($input['code'] ?? ($_GET['code'] ?? ''));
        $name = trim($input['name'] ?? '');
        $clientId = trim($input['client_id'] ?? '2f565bdf5ec09e7464db');
        $clientSecret = trim($input['client_secret'] ?? '6494629f246182ce9acd3c066a7ab5fae5598a06');

        if (!$rawCodeInput) {
            sendResponse(false, "Authorization code or redirected URL is required", null, 400);
        }

        // 1. Extract code
        $code = $rawCodeInput;
        if (preg_match('/code=([a-zA-Z0-9]+)/', $rawCodeInput, $m)) {
            $code = $m[1];
        }

        // 2. Build candidate redirect URIs (auto-detect from pasted URL)
        $candidateUris = [];
        if (strpos($rawCodeInput, '?') !== false) {
            $detected = explode('?', $rawCodeInput)[0];
            $candidateUris[] = $detected;
            $candidateUris[] = rtrim($detected, '/');
            $candidateUris[] = rtrim($detected, '/') . '/';
        }
        $candidateUris[] = 'https://courseapp.mybhiwani.in/api/';
        $candidateUris[] = 'https://courseapp.mybhiwani.in/api';
        $candidateUris[] = 'https://insightclasses.in/api';
        $candidateUris = array_values(array_unique($candidateUris));

        $tData = null;
        $usedUri = null;
        $lastErr = null;

        // Try candidate URIs
        foreach ($candidateUris as $curUri) {
            $postData = http_build_query([
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $curUri,
                'code' => $code
            ]);

            $ch = curl_init('https://api.dailymotion.com/oauth/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $res = curl_exec($ch);
            curl_close($ch);

            $parsed = $res ? json_decode($res, true) : null;
            if ($parsed && !empty($parsed['access_token'])) {
                $tData = $parsed;
                $usedUri = $curUri;
                break;
            } else {
                $lastErr = $parsed;
            }
        }

        if (!$tData || empty($tData['access_token']) || empty($tData['refresh_token'])) {
            $err = $lastErr['error']['message'] ?? $lastErr['error_description'] ?? "Failed to exchange authorization code for tokens. The authorization code might have expired (valid for 10 minutes) or has already been used once. Please click Authorize again to generate a fresh link.";
            sendResponse(false, $err, $lastErr, 400);
        }

        $accessToken = $tData['access_token'];
        $refreshToken = $tData['refresh_token'];
        $expiresAt = time() + (int)($tData['expires_in'] ?? 36000);
        $uid = $tData['uid'] ?? '';

        // Query user info to get real channel screenname and email
        $ch = curl_init("https://api.dailymotion.com/user/me?fields=id,screenname,email");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $accessToken, "Accept: application/json"]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $uRes = curl_exec($ch);
        curl_close($ch);
        $uData = $uRes ? json_decode($uRes, true) : null;
        $channelName = $uData['screenname'] ?? ($name ?: 'Channel');
        $email = $uData['email'] ?? '';
        if (!empty($uData['id'])) {
            $uid = $uData['id'];
        }

        $accName = $name ?: ($channelName . ' (' . $uid . ')');

        // Insert or update in dm_accounts
        $stmt = $pdo->prepare("
            INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
        ");
        $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt]);
        $accId = $pdo->lastInsertId();

        sendResponse(true, "Successfully connected account '{$channelName}'!", [
            'id' => $accId,
            'name' => $accName,
            'channel_name' => $channelName,
            'uid' => $uid,
            'email' => $email,
            'refresh_token' => $refreshToken
        ]);
        break;
        $uData = $uRes ? json_decode($uRes, true) : null;
        $channelName = $uData['screenname'] ?? ($name ?: 'Channel');
        $email = $uData['email'] ?? '';
        if (!empty($uData['id'])) {
            $uid = $uData['id'];
        }

        $accName = $name ?: ($channelName . ' (' . $uid . ')');

        // Insert or update in dm_accounts
        $stmt = $pdo->prepare("
            INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
        ");
        $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt]);
        $accId = $pdo->lastInsertId();

        sendResponse(true, "Successfully connected account '{$channelName}'!", [
            'id' => $accId,
            'name' => $accName,
            'channel_name' => $channelName,
            'uid' => $uid,
            'email' => $email,
            'refresh_token' => $refreshToken
        ]);
        break;

    case 'stats':
        $totalCourses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $totalChapters = $pdo->query("SELECT COUNT(*) FROM chapters")->fetchColumn();
        $totalVideos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
        $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalDmAccounts = $pdo->query("SELECT COUNT(*) FROM dm_accounts WHERE status = 1")->fetchColumn();

        sendResponse(true, "Stats loaded", [
            'courses' => $totalCourses,
            'chapters' => $totalChapters,
            'videos' => $totalVideos,
            'categories' => $totalCategories,
            'users' => $totalUsers,
            'dm_accounts' => $totalDmAccounts
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
        $dmAccountId = (int)($input['dm_account_id'] ?? 0);
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

        // If dmAccountId is 0, auto detect which account owns it
        if ($dmAccountId <= 0) {
            $found = findDmAccountForVideo($pdo, $dmId);
            if ($found && !empty($found['account']['id'])) {
                $dmAccountId = (int)$found['account']['id'];
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE videos SET course_id = ?, chapter_id = ?, dm_account_id = ?, title = ?, dailymotion_id = ?, duration = ?, is_free_preview = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$courseId, $chapterId, $dmAccountId, $title, $dmId, $duration, $isPreview, $sortOrder, $id]);
            sendResponse(true, "Lesson video updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO videos (course_id, chapter_id, dm_account_id, title, dailymotion_id, duration, is_free_preview, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$courseId, $chapterId, $dmAccountId, $title, $dmId, $duration, $isPreview, $sortOrder]);
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
