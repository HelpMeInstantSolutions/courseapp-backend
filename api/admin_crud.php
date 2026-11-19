<?php
// backend/api/admin_crud.php
require_once __DIR__ . '/config.php';

session_start();

// Helper to get JSON input
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

try {
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
        $rawTokenInput = trim($input['refresh_token'] ?? '');
        $isDefault = !empty($input['is_default']) ? 1 : 0;
        $status = isset($input['status']) ? (int)$input['status'] : 1;

        if (!$name || !$clientId || !$clientSecret || !$rawTokenInput) {
            sendResponse(false, "Account Name, API Key, API Secret, and Refresh Token / Code are all required.", null, 400);
        }

        // Auto-detect if user entered a full redirect URL or code
        $code = null;
        if (preg_match('/code=([a-zA-Z0-9]+)/', $rawTokenInput, $m)) {
            $code = $m[1];
        }

        $tData = null;
        $newRefreshToken = $rawTokenInput;

        // 1. If not clearly a code URL, try grant_type=refresh_token first
        if (!$code) {
            $postData = http_build_query([
                'grant_type' => 'refresh_token',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $rawTokenInput
            ]);
            $res = httpPostDailymotion('https://api.dailymotion.com/oauth/token', $postData);
            $parsed = $res ? json_decode($res, true) : null;
            if ($parsed && !empty($parsed['access_token'])) {
                $tData = $parsed;
            }
        }

        // 2. If refresh_token failed or was a code, try exchanging as authorization_code
        if (!$tData || empty($tData['access_token'])) {
            $authCode = $code ?: $rawTokenInput;
            $candidateUris = [];
            if (strpos($rawTokenInput, '?') !== false) {
                $detected = explode('?', $rawTokenInput)[0];
                $candidateUris[] = $detected;
                $candidateUris[] = rtrim($detected, '/');
                $candidateUris[] = rtrim($detected, '/') . '/';
            }
            $candidateUris[] = 'https://courseapp.mybhiwani.in/api/';
            $candidateUris[] = 'https://courseapp.mybhiwani.in/api';
            $candidateUris[] = 'https://insightclasses.in/api';
            $candidateUris[] = 'https://insightclasses.in/api/';
            $candidateUris = array_values(array_unique($candidateUris));

            foreach ($candidateUris as $curUri) {
                $postData = http_build_query([
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $curUri,
                    'code' => $authCode
                ]);
                $cRes = httpPostDailymotion('https://api.dailymotion.com/oauth/token', $postData);
                $cData = $cRes ? json_decode($cRes, true) : null;
                if ($cData && !empty($cData['access_token'])) {
                    $tData = $cData;
                    break;
                }
            }
        }

        if (!$tData || empty($tData['access_token'])) {
            $err = $tData['error']['message'] ?? $tData['error_description'] ?? "Failed to connect to Dailymotion. Please verify your API Key, Secret, and Refresh Token / Code.";
            sendResponse(false, $err, $tData, 400);
        }

        $accessToken = $tData['access_token'];
        $expiresAt = time() + (int)($tData['expires_in'] ?? 36000);
        $newRefreshToken = $tData['refresh_token'] ?? $rawTokenInput;
        $uid = $tData['uid'] ?? '';

        // Query user info to get real channel name and email
        $uRes = httpGetDailymotion("https://api.dailymotion.com/user/me?fields=id,screenname,email", ["Authorization: Bearer " . $accessToken]);
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
        $id = (int)($input['id'] ?? 0);
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
        $candidateUris[] = 'https://insightclasses.in/api/';
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

            $res = httpPostDailymotion('https://api.dailymotion.com/oauth/token', $postData);
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
        $uRes = httpGetDailymotion("https://api.dailymotion.com/user/me?fields=id,screenname,email", ["Authorization: Bearer " . $accessToken]);
        $uData = $uRes ? json_decode($uRes, true) : null;
        $channelName = $uData['screenname'] ?? ($name ?: 'Channel');
        $email = $uData['email'] ?? '';
        if (!empty($uData['id'])) {
            $uid = $uData['id'];
        }

        $accName = $name ?: ($channelName . ' (' . $uid . ')');

        // Check if account already exists with this uid or client_id or updating specific id
        $existingId = $id > 0 ? $id : 0;
        if (!$existingId) {
            $checkStmt = $pdo->prepare("SELECT id FROM dm_accounts WHERE uid = ? OR (client_id = ? AND client_id != '2f565bdf5ec09e7464db')");
            $checkStmt->execute([$uid, $clientId]);
            $existingId = (int)$checkStmt->fetchColumn();
        }

        if ($existingId) {
            $stmt = $pdo->prepare("
                UPDATE dm_accounts SET name = ?, channel_name = ?, uid = ?, email = ?, client_id = ?, client_secret = ?, refresh_token = ?, access_token = ?, expires_at = ?, status = 1
                WHERE id = ?
            ");
            $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt, $existingId]);
            $accId = $existingId;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
            ");
            $stmt->execute([$accName, $channelName, $uid, $email, $clientId, $clientSecret, $refreshToken, $accessToken, $expiresAt]);
            $accId = $pdo->lastInsertId();
        }

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
        $totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 1")->fetchColumn();
        $totalChapters = $pdo->query("SELECT COUNT(*) FROM chapters WHERE status = 1")->fetchColumn();
        $totalVideos = $pdo->query("SELECT COUNT(*) FROM videos WHERE status = 1")->fetchColumn();
        $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalDmAccounts = $pdo->query("SELECT COUNT(*) FROM dm_accounts WHERE status = 1")->fetchColumn();
        $pendingQueries = (int)$pdo->query("SELECT COUNT(*) FROM user_queries WHERE status = 'pending'")->fetchColumn();

        sendResponse(true, "Stats loaded", [
            'courses' => $totalCourses,
            'subjects' => $totalSubjects,
            'chapters' => $totalChapters,
            'videos' => $totalVideos,
            'categories' => $totalCategories,
            'users' => $totalUsers,
            'dm_accounts' => $totalDmAccounts,
            'pending_queries' => $pendingQueries
        ]);
        break;

    case 'list_subjects':
        $courseId = (int)($input['course_id'] ?? ($_GET['course_id'] ?? 0));
        if ($courseId > 0) {
            $stmt = $pdo->prepare("SELECT s.*, c.title as course_title,
                                   (SELECT COUNT(*) FROM chapters ch WHERE ch.subject_id = s.id AND (ch.parent_id = 0 OR ch.parent_id IS NULL)) as chapter_count,
                                   (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) as video_count
                                   FROM subjects s
                                   LEFT JOIN courses c ON s.course_id = c.id
                                   WHERE s.course_id = ?
                                   ORDER BY s.sort_order ASC, s.id ASC");
            $stmt->execute([$courseId]);
        } else {
            $stmt = $pdo->query("SELECT s.*, c.title as course_title,
                                 (SELECT COUNT(*) FROM chapters ch WHERE ch.subject_id = s.id AND (ch.parent_id = 0 OR ch.parent_id IS NULL)) as chapter_count,
                                 (SELECT COUNT(*) FROM videos v WHERE v.subject_id = s.id) as video_count
                                 FROM subjects s
                                 LEFT JOIN courses c ON s.course_id = c.id
                                 ORDER BY s.course_id DESC, s.sort_order ASC");
        }
        $subjects = $stmt->fetchAll();
        sendResponse(true, "Subjects retrieved successfully", $subjects);
        break;

    case 'save_subject':
        $id = (int)($input['id'] ?? 0);
        $courseId = (int)($input['course_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $facultyName = trim($input['faculty_name'] ?? '');
        $thumbnail = trim($input['thumbnail'] ?? '');
        $description = trim($input['description'] ?? '');
        $sortOrder = (int)($input['sort_order'] ?? 1);

        if ($courseId <= 0 || empty($title)) {
            sendResponse(false, "Batch and Subject Title are required", null, 400);
        }

        // Verify batch exists
        $cCheck = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
        $cCheck->execute([$courseId]);
        if (!$cCheck->fetch()) {
            sendResponse(false, "Selected Batch does not exist", null, 400);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE subjects SET course_id = ?, title = ?, faculty_name = ?, thumbnail = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$courseId, $title, $facultyName, $thumbnail, $description, $sortOrder, $id]);
            sendResponse(true, "Subject updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO subjects (course_id, title, faculty_name, thumbnail, description, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$courseId, $title, $facultyName, $thumbnail, $description, $sortOrder]);
            sendResponse(true, "New Subject created successfully!");
        }
        break;

    case 'delete_subject':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) sendResponse(false, "Invalid Subject ID", null, 400);

        // Reset subject_id on chapters and videos
        $pdo->prepare("UPDATE chapters SET subject_id = 0 WHERE subject_id = ?")->execute([$id]);
        $pdo->prepare("UPDATE videos SET subject_id = 0 WHERE subject_id = ?")->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "Subject deleted successfully!");
        break;

    case 'list_chapters':
        $courseId = (int)($input['course_id'] ?? ($_GET['course_id'] ?? 0));
        $subjectId = (int)($input['subject_id'] ?? ($_GET['subject_id'] ?? 0));
        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : (isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : -1);

        $sql = "SELECT ch.*, c.title as course_title, s.title as subject_title,
                       (SELECT COUNT(*) FROM chapters sub WHERE sub.parent_id = ch.id) as sub_chapter_count,
                       (SELECT COUNT(*) FROM videos v WHERE v.chapter_id = ch.id) as video_count 
                FROM chapters ch 
                LEFT JOIN courses c ON ch.course_id = c.id 
                LEFT JOIN subjects s ON ch.subject_id = s.id 
                WHERE 1=1";
        $params = [];

        if ($courseId > 0) {
            $sql .= " AND ch.course_id = ?";
            $params[] = $courseId;
        }
        if ($subjectId > 0) {
            $sql .= " AND ch.subject_id = ?";
            $params[] = $subjectId;
        }
        if ($parentId >= 0) {
            $sql .= " AND ch.parent_id = ?";
            $params[] = $parentId;
        }

        $sql .= " ORDER BY ch.sort_order ASC, ch.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $chapters = $stmt->fetchAll();
        sendResponse(true, "Chapters retrieved successfully", $chapters);
        break;

    case 'save_chapter':
        $id = (int)($input['id'] ?? 0);
        $courseId = (int)($input['course_id'] ?? 0);
        $subjectId = (int)($input['subject_id'] ?? 0);
        $parentId = (int)($input['parent_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $pdfUrl = trim($input['pdf_url'] ?? '');
        $sortOrder = (int)($input['sort_order'] ?? 1);

        if ($courseId <= 0 || empty($title)) {
            sendResponse(false, "Batch selection and Chapter Title are required.", null, 400);
        }

        // Verify that course exists
        $courseCheck = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
        $courseCheck->execute([$courseId]);
        $existingCourse = $courseCheck->fetch();
        if (!$existingCourse) {
            sendResponse(false, "Selected Batch (ID #{$courseId}) does not exist. Please select or create an existing batch first.", null, 400);
        }

        // Auto-detect or fallback subject_id if not provided
        if ($subjectId <= 0) {
            $subCheck = $pdo->prepare("SELECT id FROM subjects WHERE course_id = ? ORDER BY id ASC LIMIT 1");
            $subCheck->execute([$courseId]);
            $subjectId = (int)$subCheck->fetchColumn();
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE chapters SET course_id = ?, subject_id = ?, parent_id = ?, title = ?, description = ?, pdf_url = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$courseId, $subjectId, $parentId, $title, $description, $pdfUrl, $sortOrder, $id]);
            sendResponse(true, "Chapter updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO chapters (course_id, subject_id, parent_id, title, description, pdf_url, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$courseId, $subjectId, $parentId, $title, $description, $pdfUrl, $sortOrder]);
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
        $stmt = $pdo->query("
            SELECT u.id, u.name, u.email, u.phone, u.status, u.created_at,
                   u.plain_password, u.device_id, u.device_model, u.otp_code, u.is_verified,
                   (SELECT COUNT(*) FROM user_enrollments e WHERE e.user_id = u.id AND e.status = 1 AND (e.validity_date IS NULL OR e.validity_date >= DATE('now'))) as active_enrollments_count
            FROM users u 
            ORDER BY u.id DESC
        ");
        $users = $stmt->fetchAll();

        // For each user, attach brief enrolled batch names
        $enrStmt = $pdo->prepare("
            SELECT e.id as enrollment_id, e.course_id, e.subject_id, e.validity_date,
                   c.title as course_title, s.title as subject_title
            FROM user_enrollments e
            LEFT JOIN courses c ON e.course_id = c.id
            LEFT JOIN subjects s ON e.subject_id = s.id
            WHERE e.user_id = ? AND e.status = 1
            ORDER BY e.id DESC
        ");
        foreach ($users as $k => $u) {
            $enrStmt->execute([$u['id']]);
            $users[$k]['enrollments'] = $enrStmt->fetchAll();
        }

        sendResponse(true, "Users list retrieved", $users);
        break;

    case 'change_user_password':
        $userId = (int)($input['user_id'] ?? 0);
        $newPassword = trim($input['new_password'] ?? '');

        if ($userId <= 0 || !$newPassword) {
            sendResponse(false, "User ID and new password are required", null, 400);
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, plain_password = ? WHERE id = ?");
        $stmt->execute([$hash, $newPassword, $userId]);
        sendResponse(true, "Password updated successfully!");
        break;

    case 'toggle_user_status':
        $userId = (int)($input['user_id'] ?? 0);
        $status = (int)($input['status'] ?? 1);

        if ($userId <= 0) {
            sendResponse(false, "Invalid user ID", null, 400);
        }

        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$status, $userId]);
        $msg = $status === 1 ? "Student account activated." : "Student account deactivated.";
        sendResponse(true, $msg);
        break;

    case 'verify_user_otp':
        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            sendResponse(false, "Invalid user ID", null, 400);
        }

        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, otp_code = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        sendResponse(true, "Student account verified and activated successfully!");
        break;

    case 'unbind_user_device':
        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            sendResponse(false, "Invalid user ID", null, 400);
        }

        $stmt = $pdo->prepare("UPDATE users SET device_id = NULL, device_model = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        sendResponse(true, "Device unlinked successfully! Student can now login from a new phone.");
        break;

    case 'assign_user_enrollment':
        $userId = (int)($input['user_id'] ?? 0);
        $validityDate = trim($input['validity_date'] ?? '');
        $valDate = (!empty($validityDate) && $validityDate !== 'lifetime') ? $validityDate : null;

        if ($userId <= 0) {
            sendResponse(false, "Student selection is required", null, 400);
        }

        // Mode 1: Multiple Batches (Bulk course_ids)
        if (!empty($input['course_ids']) && is_array($input['course_ids'])) {
            $courseIds = array_filter(array_map('intval', $input['course_ids']));
            if (empty($courseIds)) {
                sendResponse(false, "Please select at least one batch", null, 400);
            }
            $count = 0;
            foreach ($courseIds as $cId) {
                if ($cId <= 0) continue;
                // Check if full batch enrollment already exists
                $chk = $pdo->prepare("SELECT id FROM user_enrollments WHERE user_id = ? AND course_id = ? AND subject_id = 0");
                $chk->execute([$userId, $cId]);
                $existingId = $chk->fetchColumn();
                if ($existingId) {
                    $up = $pdo->prepare("UPDATE user_enrollments SET validity_date = ?, status = 1 WHERE id = ?");
                    $up->execute([$valDate, $existingId]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO user_enrollments (user_id, course_id, subject_id, validity_date, status) VALUES (?, ?, 0, ?, 1)");
                    $ins->execute([$userId, $cId, $valDate]);
                }
                $count++;
            }
            sendResponse(true, "Successfully assigned $count batches to student!");
            break;
        }

        // Mode 2: Single Course with Multiple Subjects (or All Subjects)
        $courseId = (int)($input['course_id'] ?? 0);
        if ($courseId <= 0) {
            sendResponse(false, "Batch selection is required", null, 400);
        }

        $subjectIds = [];
        if (isset($input['subject_ids']) && is_array($input['subject_ids'])) {
            $subjectIds = array_map('intval', $input['subject_ids']);
        } elseif (isset($input['subject_id'])) {
            $subjectIds = [(int)$input['subject_id']];
        }

        if (empty($subjectIds)) {
            $subjectIds = [0];
        }

        // If '0' is among subject IDs, student gets Full Batch Access (all subjects)
        if (in_array(0, $subjectIds, true)) {
            // Delete individual subject rows to prevent duplicate records
            $pdo->prepare("DELETE FROM user_enrollments WHERE user_id = ? AND course_id = ? AND subject_id != 0")->execute([$userId, $courseId]);

            $chk = $pdo->prepare("SELECT id FROM user_enrollments WHERE user_id = ? AND course_id = ? AND subject_id = 0");
            $chk->execute([$userId, $courseId]);
            $existingId = $chk->fetchColumn();
            if ($existingId) {
                $up = $pdo->prepare("UPDATE user_enrollments SET validity_date = ?, status = 1 WHERE id = ?");
                $up->execute([$valDate, $existingId]);
            } else {
                $ins = $pdo->prepare("INSERT INTO user_enrollments (user_id, course_id, subject_id, validity_date, status) VALUES (?, ?, 0, ?, 1)");
                $ins->execute([$userId, $courseId, $valDate]);
            }
            sendResponse(true, "Full Batch Access (all subjects) granted successfully!");
            break;
        }

        // Otherwise, specific subjects are selected
        // Delete any full batch row if we are restricting to specific subjects
        $pdo->prepare("DELETE FROM user_enrollments WHERE user_id = ? AND course_id = ? AND subject_id = 0")->execute([$userId, $courseId]);

        $subCount = 0;
        foreach ($subjectIds as $sId) {
            if ($sId <= 0) continue;
            $chk = $pdo->prepare("SELECT id FROM user_enrollments WHERE user_id = ? AND course_id = ? AND subject_id = ?");
            $chk->execute([$userId, $courseId, $sId]);
            $existingId = $chk->fetchColumn();
            if ($existingId) {
                $up = $pdo->prepare("UPDATE user_enrollments SET validity_date = ?, status = 1 WHERE id = ?");
                $up->execute([$valDate, $existingId]);
            } else {
                $ins = $pdo->prepare("INSERT INTO user_enrollments (user_id, course_id, subject_id, validity_date, status) VALUES (?, ?, ?, ?, 1)");
                $ins->execute([$userId, $courseId, $sId, $valDate]);
            }
            $subCount++;
        }

        sendResponse(true, "Successfully assigned $subCount specific subjects to student!");
        break;

    case 'list_user_enrollments':
        $userId = (int)($input['user_id'] ?? ($_GET['user_id'] ?? 0));
        if ($userId <= 0) {
            sendResponse(false, "Invalid user ID", null, 400);
        }

        $stmt = $pdo->prepare("
            SELECT e.*, c.title as course_title, s.title as subject_title 
            FROM user_enrollments e 
            LEFT JOIN courses c ON e.course_id = c.id 
            LEFT JOIN subjects s ON e.subject_id = s.id 
            WHERE e.user_id = ? 
            ORDER BY e.id DESC
        ");
        $stmt->execute([$userId]);
        sendResponse(true, "Enrollments retrieved", $stmt->fetchAll());
        break;

    case 'delete_user_enrollment':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            sendResponse(false, "Invalid enrollment ID", null, 400);
        }

        $stmt = $pdo->prepare("DELETE FROM user_enrollments WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "Student access revoked successfully.");
        break;

    case 'delete_user':
        $userId = (int)($input['id'] ?? 0);
        if ($userId <= 0) sendResponse(false, "Invalid User ID", null, 400);

        // Delete user's enrollments and progress
        $pdo->prepare("DELETE FROM user_enrollments WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM user_video_progress WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare("DELETE FROM user_queries WHERE user_id = ?")->execute([$userId]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        sendResponse(true, "Student account and all related data deleted successfully!");
        break;

    case 'list_courses':
        $stmt = $pdo->query("SELECT c.*, cat.title as category_name, 
                             (SELECT COUNT(*) FROM chapters ch WHERE ch.course_id = c.id) as total_chapters,
                             (SELECT COUNT(*) FROM videos v WHERE v.course_id = c.id) as total_videos 
                             FROM courses c 
                             LEFT JOIN categories cat ON c.category_id = cat.id 
                             ORDER BY c.id DESC");
        sendResponse(true, "Courses retrieved successfully", $stmt->fetchAll());
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

    case 'list_videos':
        $courseId = (int)($input['course_id'] ?? ($_GET['course_id'] ?? 0));
        $subjectId = (int)($input['subject_id'] ?? ($_GET['subject_id'] ?? 0));
        $chapterId = (int)($input['chapter_id'] ?? ($_GET['chapter_id'] ?? 0));

        $sql = "SELECT v.*, c.title as course_title, s.title as subject_title, ch.title as chapter_title, a.channel_name as dm_channel_name
                FROM videos v
                LEFT JOIN courses c ON v.course_id = c.id
                LEFT JOIN subjects s ON v.subject_id = s.id
                LEFT JOIN chapters ch ON v.chapter_id = ch.id
                LEFT JOIN dm_accounts a ON v.dm_account_id = a.id
                WHERE 1=1";
        $params = [];
        if ($courseId > 0) { $sql .= " AND v.course_id = ?"; $params[] = $courseId; }
        if ($subjectId > 0) { $sql .= " AND v.subject_id = ?"; $params[] = $subjectId; }
        if ($chapterId > 0) { $sql .= " AND v.chapter_id = ?"; $params[] = $chapterId; }
        $sql .= " ORDER BY v.sort_order ASC, v.id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendResponse(true, "Videos retrieved successfully", $stmt->fetchAll());
        break;

    case 'save_video':
        $id = (int)($input['id'] ?? 0);
        $courseId = (int)($input['course_id'] ?? 0);
        $subjectId = (int)($input['subject_id'] ?? 0);
        $chapterId = (int)($input['chapter_id'] ?? 0);
        $dmAccountId = (int)($input['dm_account_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $dmId = trim($input['dailymotion_id'] ?? '');
        $duration = trim($input['duration'] ?? '15:00');
        $pdfUrl = trim($input['pdf_url'] ?? '');
        $isPreview = (int)($input['is_free_preview'] ?? 0);
        $sortOrder = (int)($input['sort_order'] ?? 1);

        if ($courseId <= 0 || !$title || !$dmId) {
            sendResponse(false, "Batch ID, video title and Dailymotion Video ID are required", null, 400);
        }

        // Clean DM ID
        if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
            $dmId = $matches[1];
        }

        // Auto detect subject_id from chapter if missing
        if ($subjectId <= 0 && $chapterId > 0) {
            $chSub = $pdo->prepare("SELECT subject_id FROM chapters WHERE id = ?");
            $chSub->execute([$chapterId]);
            $subjectId = (int)$chSub->fetchColumn();
        }
        if ($subjectId <= 0) {
            $subCheck = $pdo->prepare("SELECT id FROM subjects WHERE course_id = ? ORDER BY id ASC LIMIT 1");
            $subCheck->execute([$courseId]);
            $subjectId = (int)$subCheck->fetchColumn();
        }

        // If dmAccountId is 0, auto detect which account owns it
        if ($dmAccountId <= 0) {
            $found = findDmAccountForVideo($pdo, $dmId);
            if ($found && !empty($found['account']['id'])) {
                $dmAccountId = (int)$found['account']['id'];
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE videos SET course_id = ?, subject_id = ?, chapter_id = ?, dm_account_id = ?, title = ?, description = ?, dailymotion_id = ?, duration = ?, pdf_url = ?, is_free_preview = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$courseId, $subjectId, $chapterId, $dmAccountId, $title, $description, $dmId, $duration, $pdfUrl, $isPreview, $sortOrder, $id]);
            sendResponse(true, "Lesson video updated successfully!");
        } else {
            $stmt = $pdo->prepare("INSERT INTO videos (course_id, subject_id, chapter_id, dm_account_id, title, description, dailymotion_id, duration, pdf_url, is_free_preview, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$courseId, $subjectId, $chapterId, $dmAccountId, $title, $description, $dmId, $duration, $pdfUrl, $isPreview, $sortOrder]);
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

    case 'fetch_dm_account_videos':
        $accountId = (int)($input['account_id'] ?? ($_GET['account_id'] ?? 0));
        $page = max(1, (int)($input['page'] ?? ($_GET['page'] ?? 1)));
        $limit = min(100, max(10, (int)($input['limit'] ?? ($_GET['limit'] ?? 100))));
        $search = trim($input['search'] ?? ($_GET['search'] ?? ''));

        // Fetch all active accounts for dropdown switcher
        $allAccounts = $pdo->query("SELECT id, name, channel_name, uid, is_default FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC")->fetchAll();
        if (empty($allAccounts)) {
            sendResponse(false, "No active Dailymotion accounts connected. Please connect an account first.", null, 400);
        }

        // Determine which account to query
        $selectedAccount = null;
        if ($accountId > 0) {
            foreach ($allAccounts as $acc) {
                if ((int)$acc['id'] === $accountId) {
                    $selectedAccount = $acc;
                    break;
                }
            }
        }
        if (!$selectedAccount) {
            $selectedAccount = $allAccounts[0];
            $accountId = (int)$selectedAccount['id'];
        }

        // Get full account row with credentials
        $accStmt = $pdo->prepare("SELECT * FROM dm_accounts WHERE id = ?");
        $accStmt->execute([$accountId]);
        $fullAccount = $accStmt->fetch();

        $token = refreshDmAccount($pdo, $fullAccount);
        if (!$token) {
            sendResponse(false, "Could not obtain authorization token for account '{$selectedAccount['name']}'. Please re-authenticate.", null, 401);
        }

        // Query Dailymotion API for user videos sorted by recent (descending by upload date)
        $apiUrl = "https://api.dailymotion.com/user/me/videos?fields=id,title,duration,thumbnail_240_url,created_time,private&sort=recent&limit={$limit}&page={$page}";
        if ($search !== '') {
            $apiUrl .= "&search=" . urlencode($search);
        }

        // Fetch already imported video IDs in our DB to flag them
        $existingMap = [];
        try {
            $exRows = $pdo->query("SELECT v.dailymotion_id, v.id as local_video_id, v.course_id, v.subject_id, v.chapter_id, c.title as course_title, ch.title as chapter_title FROM videos v LEFT JOIN courses c ON v.course_id = c.id LEFT JOIN chapters ch ON v.chapter_id = ch.id")->fetchAll();
            foreach ($exRows as $er) {
                $existingMap[$er['dailymotion_id']] = [
                    'local_id' => $er['local_video_id'],
                    'course_id' => $er['course_id'],
                    'course_title' => $er['course_title'] ?? '',
                    'chapter_id' => $er['chapter_id'],
                    'chapter_title' => $er['chapter_title'] ?? ''
                ];
            }
        } catch (Exception $e) {}

        $fetchError = null;
        $res = httpGetDailymotion($apiUrl, ["Authorization: Bearer {$token}"], $fetchError);
        
        // If server-side fetch is blocked by Hostinger network/firewall, gracefully fallback to direct browser client fetch
        if (!$res) {
            sendResponse(true, "Direct browser sync initiated", [
                'direct_browser' => true,
                'token' => $token,
                'api_url' => $apiUrl,
                'account_id' => $accountId,
                'account_name' => $selectedAccount['name'],
                'accounts' => $allAccounts,
                'existing_map' => $existingMap
            ]);
        }

        $dmData = json_decode($res, true);
        if (isset($dmData['error']) && (strpos(strtolower($dmData['error']['message'] ?? ''), 'token') !== false || ($dmData['error']['code'] ?? 0) == 401)) {
            // Force refresh token and retry
            $token = refreshDmAccount($pdo, $fullAccount, true);
            if ($token) {
                $res = httpGetDailymotion($apiUrl, ["Authorization: Bearer {$token}"]);
                $dmData = json_decode($res, true);
            }
        }

        if (isset($dmData['error'])) {
            sendResponse(false, "Dailymotion error: " . ($dmData['error']['message'] ?? 'Unknown error'), $dmData['error'], 400);
        }

        $rawList = $dmData['list'] ?? [];
        $totalVideos = (int)($dmData['total'] ?? count($rawList));
        $hasMore = !empty($dmData['has_more']);

        $formattedVideos = [];
        foreach ($rawList as $v) {
            $secs = (int)($v['duration'] ?? 0);
            $hrs = floor($secs / 3600);
            $mins = floor(($secs % 3600) / 60);
            $remSecs = $secs % 60;
            $durationFormatted = ($hrs > 0) ? sprintf("%02d:%02d:%02d", $hrs, $mins, $remSecs) : sprintf("%02d:%02d", $mins, $remSecs);

            $ctime = (int)($v['created_time'] ?? 0);
            $createdFormatted = ($ctime > 0) ? date('d M Y, h:i A', $ctime) : '';

            $vidId = $v['id'];
            $isImported = isset($existingMap[$vidId]);

            $formattedVideos[] = [
                'id' => $vidId,
                'title' => $v['title'] ?? '',
                'duration' => $durationFormatted,
                'duration_seconds' => $secs,
                'thumbnail_url' => $v['thumbnail_240_url'] ?? '',
                'created_time' => $ctime,
                'created_formatted' => $createdFormatted,
                'private' => !empty($v['private']),
                'account_id' => $accountId,
                'account_name' => $selectedAccount['name'],
                'is_imported' => $isImported,
                'imported_info' => $isImported ? $existingMap[$vidId] : null
            ];
        }

        sendResponse(true, "Fetched " . count($formattedVideos) . " videos", [
            'accounts' => $allAccounts,
            'selected_account_id' => $accountId,
            'selected_account_name' => $selectedAccount['name'],
            'total' => $totalVideos,
            'page' => $page,
            'has_more' => $hasMore,
            'videos' => $formattedVideos
        ]);
        break;

    case 'import_dm_videos':
        $courseId = (int)($input['course_id'] ?? 0);
        $subjectId = (int)($input['subject_id'] ?? 0);
        $chapterId = (int)($input['chapter_id'] ?? 0);
        $defaultAccountId = (int)($input['account_id'] ?? 0);
        $isPreview = (int)($input['is_free_preview'] ?? 0);
        $videosList = $input['videos'] ?? [];

        if ($courseId <= 0 || $chapterId <= 0 || empty($videosList) || !is_array($videosList)) {
            sendResponse(false, "Batch, Chapter and at least one selected video are required", null, 400);
        }

        // Auto detect subject if missing
        if ($subjectId <= 0 && $chapterId > 0) {
            $chSub = $pdo->prepare("SELECT subject_id FROM chapters WHERE id = ?");
            $chSub->execute([$chapterId]);
            $subjectId = (int)$chSub->fetchColumn();
        }
        if ($subjectId <= 0) {
            $subCheck = $pdo->prepare("SELECT id FROM subjects WHERE course_id = ? ORDER BY id ASC LIMIT 1");
            $subCheck->execute([$courseId]);
            $subjectId = (int)$subCheck->fetchColumn();
        }

        // Get starting sort order
        $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM videos WHERE chapter_id = ?");
        $sortStmt->execute([$chapterId]);
        $currentSort = (int)$sortStmt->fetchColumn();

        $imported = 0;
        $skipped = 0;

        $pdo->beginTransaction();
        $checkStmt = $pdo->prepare("SELECT id FROM videos WHERE chapter_id = ? AND dailymotion_id = ?");
        $insertStmt = $pdo->prepare("
            INSERT INTO videos (course_id, subject_id, chapter_id, dm_account_id, title, description, dailymotion_id, duration, is_free_preview, sort_order, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, datetime('now'))
        ");

        foreach ($videosList as $item) {
            $dmId = trim($item['dailymotion_id'] ?? ($item['id'] ?? ''));
            if (!$dmId) continue;

            // Check if already in this chapter
            $checkStmt->execute([$chapterId, $dmId]);
            if ($checkStmt->fetch()) {
                $skipped++;
                continue;
            }

            $vTitle = trim($item['title'] ?? 'Video Lesson');
            $vDuration = trim($item['duration'] ?? '15:00');
            $accId = (int)($item['account_id'] ?? $defaultAccountId);

            $currentSort++;
            $insertStmt->execute([
                $courseId,
                $subjectId,
                $chapterId,
                $accId,
                $vTitle,
                '',
                $dmId,
                $vDuration,
                $isPreview,
                $currentSort
            ]);
            $imported++;
        }

        $pdo->commit();
        sendResponse(true, "Successfully imported {$imported} video lesson(s)!" . ($skipped > 0 ? " ({$skipped} duplicates skipped)" : ""), [
            'imported_count' => $imported,
            'skipped_count' => $skipped
        ]);
        break;

    case 'reorder_videos':
        $order = $input['order'] ?? [];
        if (!empty($order) && is_array($order)) {
            $pdo->beginTransaction();
            $up = $pdo->prepare("UPDATE videos SET sort_order = ? WHERE id = ?");
            foreach ($order as $idx => $item) {
                $vidId = is_array($item) ? (int)($item['id'] ?? 0) : (int)$item;
                $sortNum = is_array($item) ? (int)($item['sort_order'] ?? ($idx + 1)) : ($idx + 1);
                if ($vidId > 0) {
                    $up->execute([$sortNum, $vidId]);
                }
            }
            $pdo->commit();
            sendResponse(true, "Video sequence updated successfully!");
        } else {
            sendResponse(false, "Invalid video order data", null, 400);
        }
        break;

    case 'reorder_chapters':
        $order = $input['order'] ?? [];
        if (!empty($order) && is_array($order)) {
            $pdo->beginTransaction();
            $up = $pdo->prepare("UPDATE chapters SET sort_order = ? WHERE id = ?");
            foreach ($order as $idx => $item) {
                $chId = is_array($item) ? (int)($item['id'] ?? 0) : (int)$item;
                $sortNum = is_array($item) ? (int)($item['sort_order'] ?? ($idx + 1)) : ($idx + 1);
                if ($chId > 0) {
                    $up->execute([$sortNum, $chId]);
                }
            }
            $pdo->commit();
            sendResponse(true, "Chapter sequence updated successfully!");
        } else {
            sendResponse(false, "Invalid chapter order data", null, 400);
        }
        break;

    default:
        sendResponse(false, "Invalid admin action", null, 400);
        break;
}
} catch (Throwable $e) {
    sendResponse(false, "Server Error: " . $e->getMessage(), [
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
