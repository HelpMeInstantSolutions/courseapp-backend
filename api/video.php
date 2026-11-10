<?php
// backend/api/video.php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);
$vidIdParam = trim($_GET['video_id'] ?? '');

if ($id <= 0 && empty($vidIdParam)) {
    sendResponse(false, "Video ID is required", null, 400);
}

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT v.*, c.title as course_title, c.thumbnail as course_thumbnail 
                           FROM videos v 
                           JOIN courses c ON v.course_id = c.id 
                           WHERE v.id = ? AND v.status = 1");
    $stmt->execute([$id]);
    $video = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT v.*, c.title as course_title, c.thumbnail as course_thumbnail 
                           FROM videos v 
                           JOIN courses c ON v.course_id = c.id 
                           WHERE v.dailymotion_id = ? AND v.status = 1");
    $stmt->execute([$vidIdParam]);
    $video = $stmt->fetch();
}

if (!$video) {
    // If not in database, create fallback structure
    $cleanId = $vidIdParam ?: 'x98hbpm';
    $video = [
        'id' => 0,
        'title' => 'Video Stream',
        'dailymotion_id' => $cleanId
    ];
}

$dmId = $video['dailymotion_id'];
$cleanId = $dmId;
if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
    $cleanId = $matches[1];
}
$video['clean_dm_id'] = $cleanId;

// Dailymotion API Credentials for Signed Private Playback
$apiKey = '2f565bdf5ec09e7464db';
$apiSecret = '6494629f246182ce9acd3c066a7ab5fae5598a06';

$accessToken = getDailymotionAccessToken($apiKey, $apiSecret);
$video['access_token'] = $accessToken;

// Construct Authorized Geo Player with Access Token and Native Handshake
if ($accessToken) {
    $video['embed_url'] = "https://geo.dailymotion.com/player/xtv3w.html?video=" . $cleanId . "&customConfig[accessToken]=" . urlencode($accessToken) . "&autoplay=1&mute=0&controls=1";
} else {
    $video['embed_url'] = "https://geo.dailymotion.com/player/xtv3w.html?video=" . $cleanId . "&autoplay=1&mute=0&controls=1";
}

sendResponse(true, "Video details retrieved with OAuth access token", $video);

function getDailymotionAccessToken($key, $secret) {
    // Cache token in local file for performance (10 hours validity)
    $cacheFile = __DIR__ . '/../database/dm_token.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['expires_at']) && time() < ($cached['expires_at'] - 300)) {
            return $cached['token'];
        }
    }

    $ch = curl_init('https://api.dailymotion.com/oauth/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $key,
        'client_secret' => $secret,
        'scope' => 'read'
    ]));
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $res = json_decode($response, true);
        if (isset($res['access_token'])) {
            $token = $res['access_token'];
            $expiresAt = time() + ($res['expires_in'] ?? 36000);
            @file_put_contents($cacheFile, json_encode([
                'token' => $token,
                'expires_at' => $expiresAt
            ]));
            return $token;
        }
    }
    return null;
}
