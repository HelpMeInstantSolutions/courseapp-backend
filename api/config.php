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

        CREATE TABLE IF NOT EXISTS chapters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            sort_order INTEGER DEFAULT 1,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS dm_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            channel_name TEXT,
            uid TEXT,
            email TEXT,
            client_id TEXT NOT NULL,
            client_secret TEXT NOT NULL,
            refresh_token TEXT NOT NULL,
            access_token TEXT,
            expires_at INTEGER DEFAULT 0,
            is_default INTEGER DEFAULT 0,
            status INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            chapter_id INTEGER DEFAULT 0,
            dm_account_id INTEGER DEFAULT 0,
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

    // Migrations for videos table
    try {
        $videoCols = $pdo->query("PRAGMA table_info(videos)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('chapter_id', $videoCols)) {
            $pdo->exec("ALTER TABLE videos ADD COLUMN chapter_id INTEGER DEFAULT 0");
        }
        if (!in_array('dm_account_id', $videoCols)) {
            $pdo->exec("ALTER TABLE videos ADD COLUMN dm_account_id INTEGER DEFAULT 0");
        }
    } catch (Exception $e) {}

    // Seed default Dailymotion account if empty
    try {
        $accCount = $pdo->query("SELECT COUNT(*) FROM dm_accounts")->fetchColumn();
        if ($accCount == 0) {
            $tokenFile = __DIR__ . '/../database/dm_user_token.json';
            $tData = file_exists($tokenFile) ? json_decode(@file_get_contents($tokenFile), true) : null;
            $insAcc = $pdo->prepare("INSERT INTO dm_accounts (name, channel_name, uid, email, client_id, client_secret, refresh_token, access_token, expires_at, is_default, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
            $insAcc->execute([
                'Primary Account (Maths)',
                'SSC ROCKSTAR',
                $tData['uid'] ?? 'x2q3pq9',
                'jaatmoviescollection@gmail.com',
                '2f565bdf5ec09e7464db',
                '6494629f246182ce9acd3c066a7ab5fae5598a06',
                $tData['refresh_token'] ?? '66e1c1b82878cc7db7c3b3689800eb7b96091b8a',
                $tData['access_token'] ?? 'dWRrcypdfX59IEJDFV5CPBc6GAFiRhgoNThoDAtyLWkz',
                $tData['expires_at'] ?? (time() + 36000)
            ]);
        }
    } catch (Exception $e) {}

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

/**
 * Retrieve or automatically refresh the Dailymotion Owner Access Token.
 * If expired or within 5 minutes of expiration, refreshes via refresh_token.
 */
function getDailymotionAccessToken($forceRefresh = false) {
    $tokenFile = __DIR__ . '/../database/dm_user_token.json';
    if (!file_exists($tokenFile)) {
        return null;
    }

    $tokenData = json_decode(@file_get_contents($tokenFile), true);
    if (!$tokenData || !is_array($tokenData)) {
        return null;
    }

    $now = time();
    $expiresAt = (int)($tokenData['expires_at'] ?? 0);
    $needsRefresh = $forceRefresh || ($expiresAt === 0) || ($now >= ($expiresAt - 300));

    if ($needsRefresh && !empty($tokenData['refresh_token'])) {
        $postData = http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => '2f565bdf5ec09e7464db',
            'client_secret' => '6494629f246182ce9acd3c066a7ab5fae5598a06',
            'refresh_token' => $tokenData['refresh_token']
        ]);

        $res = null;

        // Method 1: file_get_contents native socket stream
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: Mozilla/5.0\r\nContent-Length: " . strlen($postData) . "\r\n",
                'content' => $postData,
                'timeout' => 12,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $ctx = stream_context_create($opts);
        $res = @file_get_contents('https://api.dailymotion.com/oauth/token', false, $ctx);

        // Method 2: cURL fallback
        if (!$res) {
            $ch = curl_init('https://api.dailymotion.com/oauth/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $res = curl_exec($ch);
            curl_close($ch);
        }

        if ($res) {
            $newTokens = json_decode($res, true);
            if (!empty($newTokens['access_token'])) {
                $tokenData['access_token'] = $newTokens['access_token'];
                if (!empty($newTokens['refresh_token'])) {
                    $tokenData['refresh_token'] = $newTokens['refresh_token'];
                }
                $expiresIn = (int)($newTokens['expires_in'] ?? 36000);
                $tokenData['expires_at'] = $now + $expiresIn;
                @file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return $tokenData['access_token'];
            }
        }
    }

    return $tokenData['access_token'] ?? null;
}

/**
 * Refresh an individual Dailymotion account's access token using its refresh_token.
 */
function refreshDmAccount($pdo, &$account, $forceRefresh = false) {
    if (!$account || empty($account['refresh_token'])) {
        return null;
    }

    $now = time();
    $expiresAt = (int)($account['expires_at'] ?? 0);
    $needsRefresh = $forceRefresh || ($expiresAt === 0) || ($now >= ($expiresAt - 300));

    if (!$needsRefresh && !empty($account['access_token'])) {
        return $account['access_token'];
    }

    $postData = http_build_query([
        'grant_type' => 'refresh_token',
        'client_id' => $account['client_id'],
        'client_secret' => $account['client_secret'],
        'refresh_token' => $account['refresh_token']
    ]);

    $res = null;
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: Mozilla/5.0\r\nContent-Length: " . strlen($postData) . "\r\n",
            'content' => $postData,
            'timeout' => 12,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents('https://api.dailymotion.com/oauth/token', false, $ctx);

    if (!$res) {
        $ch = curl_init('https://api.dailymotion.com/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $res = curl_exec($ch);
        curl_close($ch);
    }

    if ($res) {
        $newTokens = json_decode($res, true);
        if (!empty($newTokens['access_token'])) {
            $account['access_token'] = $newTokens['access_token'];
            if (!empty($newTokens['refresh_token'])) {
                $account['refresh_token'] = $newTokens['refresh_token'];
            }
            $expiresIn = (int)($newTokens['expires_in'] ?? 36000);
            $account['expires_at'] = $now + $expiresIn;

            if (isset($account['id']) && $pdo) {
                try {
                    $up = $pdo->prepare("UPDATE dm_accounts SET access_token = ?, refresh_token = ?, expires_at = ? WHERE id = ?");
                    $up->execute([$account['access_token'], $account['refresh_token'], $account['expires_at'], $account['id']]);
                } catch (Exception $e) {}
            }

            // Sync default account to dm_user_token.json for backwards compatibility
            if (!empty($account['is_default'])) {
                $tokenFile = __DIR__ . '/../database/dm_user_token.json';
                @file_put_contents($tokenFile, json_encode($account, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            return $account['access_token'];
        }
    }

    return $account['access_token'] ?? null;
}

/**
 * Get access token for a specific account or default account
 */
function getDmAccountToken($pdo, $accountId = null, $forceRefresh = false) {
    if (!$pdo) return getDailymotionAccessToken($forceRefresh);

    try {
        if ($accountId && $accountId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM dm_accounts WHERE id = ? AND status = 1");
            $stmt->execute([$accountId]);
            $acc = $stmt->fetch();
            if ($acc) {
                return refreshDmAccount($pdo, $acc, $forceRefresh);
            }
        }

        // Get default or first active account
        $stmt = $pdo->query("SELECT * FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
        $acc = $stmt ? $stmt->fetch() : null;
        if ($acc) {
            return refreshDmAccount($pdo, $acc, $forceRefresh);
        }
    } catch (Exception $e) {}

    return getDailymotionAccessToken($forceRefresh);
}

/**
 * Query Dailymotion across all connected accounts until one succeeds.
 * Returns array: ['account' => $account, 'video' => $videoData, 'token' => $token] or null
 */
function findDmAccountForVideo($pdo, $videoId, $preferredAccountId = 0) {
    $cleanId = $videoId;
    if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $videoId, $m)) {
        $cleanId = $m[1];
    }

    $accounts = [];
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM dm_accounts WHERE status = 1 ORDER BY is_default DESC, id ASC");
            $accounts = $stmt->fetchAll();
        } catch (Exception $e) {}
    }

    // Sort preferred account first if specified
    if ($preferredAccountId > 0) {
        usort($accounts, function($a, $b) use ($preferredAccountId) {
            if ($a['id'] == $preferredAccountId) return -1;
            if ($b['id'] == $preferredAccountId) return 1;
            return 0;
        });
    }

    $queryOne = function($vid, $token) {
        $url = "https://api.dailymotion.com/video/" . $vid . "?fields=id,title,duration,thumbnail_url,private,embed_url";
        $headers = "User-Agent: Mozilla/5.0\r\nAccept: application/json\r\n";
        if ($token) $headers .= "Authorization: Bearer " . $token . "\r\n";
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'header' => $headers, 'timeout' => 8, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $res = @file_get_contents($url, false, $ctx);
        if (!$res) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            if ($token) curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $token, "Accept: application/json"]);
            $res = curl_exec($ch);
            curl_close($ch);
        }
        return $res ? json_decode($res, true) : null;
    };

    // Try each account in pool
    foreach ($accounts as $acc) {
        $token = refreshDmAccount($pdo, $acc);
        $vJson = $queryOne($cleanId, $token);
        if ($vJson && isset($vJson['id'])) {
            return [
                'account' => $acc,
                'video' => $vJson,
                'token' => $token
            ];
        }
    }

    // Fallback: try unauthenticated (public video)
    $pubJson = $queryOne($cleanId, null);
    if ($pubJson && isset($pubJson['id'])) {
        return [
            'account' => null,
            'video' => $pubJson,
            'token' => null
        ];
    }

    return null;
}
