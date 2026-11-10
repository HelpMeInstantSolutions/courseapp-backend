<?php
// backend/api/stream.php - Direct Raw HLS Stream Extractor (Zero Watermark / Native Player Engine)
require_once __DIR__ . '/config.php';

$dmId = trim($_GET['id'] ?? ($_GET['video_id'] ?? ''));
if (empty($dmId)) {
    sendResponse(false, "Video ID is required", null, 400);
}

if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
    $dmId = $matches[1];
}

// 1. Get Owner Bearer Token
$tokenFile = __DIR__ . '/../database/dm_user_token.json';
$token = null;
if (file_exists($tokenFile)) {
    $t = json_decode(file_get_contents($tokenFile), true);
    $token = $t['access_token'] ?? null;
}

// 2. Fetch authenticated embed URL
$ch = curl_init("https://api.dailymotion.com/video/" . $dmId . "?fields=id,title,embed_url");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36");
if ($token) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $token]);
}
$res = curl_exec($ch);
curl_close($ch);

$dmData = json_decode($res, true);
$embedUrl = $dmData['embed_url'] ?? null;

if (!$embedUrl) {
    sendResponse(false, "Could not authorize stream with owner credentials", null, 403);
}

// 3. Request player page to extract raw HLS manifest URL
$ch = curl_init($embedUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Referer: https://www.dailymotion.com/",
    "Origin: https://www.dailymotion.com"
]);
$html = curl_exec($ch);
curl_close($ch);

if (!preg_match('/"url":"(https:[^"]+m3u8[^"]*)"/', $html, $m)) {
    sendResponse(false, "Could not extract raw stream manifest", null, 404);
}

$rawManifestUrl = stripslashes($m[1]);

// 4. Fetch the master m3u8 manifest to get individual quality stream URLs
$ch = curl_init($rawManifestUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Referer: https://geo.dailymotion.com/",
    "Origin: https://geo.dailymotion.com"
]);
$masterM3u8 = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$qualities = [];
$bestStream = $rawManifestUrl;

if ($httpCode === 200 && !empty($masterM3u8)) {
    $lines = explode("\n", $masterM3u8);
    $currentQuality = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#EXT-X-STREAM-INF:') === 0) {
            if (preg_match('/NAME="([^"]+)"/', $line, $qm)) {
                $currentQuality = $qm[1] . 'p';
            }
        } else if (!empty($line) && strpos($line, 'http') === 0 && $currentQuality) {
            $qualities[$currentQuality] = $line;
            $currentQuality = null;
        }
    }

    // Pick 720p or highest available as default stream
    if (isset($qualities['720p'])) {
        $bestStream = $qualities['720p'];
    } elseif (isset($qualities['480p'])) {
        $bestStream = $qualities['480p'];
    } elseif (!empty($qualities)) {
        $bestStream = reset($qualities);
    }
}

sendResponse(true, "Raw direct HLS stream ready (Zero Watermark / Native ExoPlayer)", [
    'video_id' => $dmId,
    'title' => $dmData['title'] ?? 'Lecture Video',
    'stream_url' => $bestStream,
    'master_manifest' => $rawManifestUrl,
    'qualities' => $qualities,
    'headers' => [
        'Referer' => 'https://geo.dailymotion.com/',
        'Origin' => 'https://geo.dailymotion.com',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'
    ]
]);
