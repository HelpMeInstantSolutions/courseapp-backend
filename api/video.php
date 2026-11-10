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
    $cleanId = $vidIdParam ?: 'x992mce';
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

$token = getOwnerToken();

// Query Dailymotion API directly with Owner Bearer Token to get authenticated embed URL
$authorizedEmbedUrl = "https://geo.dailymotion.com/player/xtv3w.html?video=" . $cleanId . "&autoplay=1&mute=0&controls=1";

if ($token) {
    $ch = curl_init("https://api.dailymotion.com/video/" . $cleanId . "?fields=id,title,embed_url,private");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $token]);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64)");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);

    if ($res) {
        $dmJson = json_decode($res, true);
        if (isset($dmJson['embed_url']) && !empty($dmJson['embed_url'])) {
            $authorizedEmbedUrl = $dmJson['embed_url'] . (strpos($dmJson['embed_url'], '?') !== false ? '&' : '?') . "autoplay=1&mute=0&controls=1";
            $video['title'] = $dmJson['title'] ?? $video['title'];
        }
    }
}

$video['embed_url'] = $authorizedEmbedUrl;
$video['access_token'] = $token;

sendResponse(true, "Video details retrieved with Owner Authorized Private Stream", $video);

function getOwnerToken() {
    $tokenFile = __DIR__ . '/../database/dm_user_token.json';
    if (!file_exists($tokenFile)) {
        return null;
    }

    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (!$tokenData || !isset($tokenData['access_token'])) {
        return null;
    }

    // If token expired, auto refresh using refresh_token
    if (isset($tokenData['expires_at']) && time() > ($tokenData['expires_at'] - 300) && isset($tokenData['refresh_token'])) {
        $ch = curl_init('https://api.dailymotion.com/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => '2f565bdf5ec09e7464db',
            'client_secret' => '6494629f246182ce9acd3c066a7ab5fae5598a06',
            'refresh_token' => $tokenData['refresh_token']
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        if ($res) {
            $newTokens = json_decode($res, true);
            if (isset($newTokens['access_token'])) {
                $newTokens['expires_at'] = time() + ($newTokens['expires_in'] ?? 36000);
                file_put_contents($tokenFile, json_encode($newTokens));
                return $newTokens['access_token'];
            }
        }
    }

    return $tokenData['access_token'];
}
