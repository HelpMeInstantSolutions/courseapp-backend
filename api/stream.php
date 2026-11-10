<?php
// backend/api/stream.php - Direct Raw HLS Stream Extractor (Zero Watermark / Native ExoPlayer Engine)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$dmId = trim($_GET['id'] ?? ($_GET['video_id'] ?? ''));
if (empty($dmId)) {
    echo json_encode(["status" => false, "message" => "Video ID is required", "data" => null], JSON_UNESCAPED_SLASHES);
    exit();
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

// 2. Fetch authenticated embed URL from Dailymotion API
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
$videoTitle = $dmData['title'] ?? 'Video Lecture';

// Extract token or fallback to dmId
$tokenOrId = $dmId;
if ($embedUrl && preg_match('/video=([a-zA-Z0-9]+)/', $embedUrl, $vm)) {
    $tokenOrId = $vm[1];
}

// 3. Request player metadata endpoint with the private token
$metaUrl = "https://www.dailymotion.com/player/metadata/video/" . $tokenOrId;
$ch = curl_init($metaUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Referer: https://geo.dailymotion.com/player.html?video=" . $tokenOrId,
    "Origin: https://geo.dailymotion.com"
]);
$metaRes = curl_exec($ch);
curl_close($ch);

$metaJson = json_decode($metaRes, true);
$rawManifestUrl = null;

if (isset($metaJson['qualities']['auto'][0]['url'])) {
    $rawManifestUrl = $metaJson['qualities']['auto'][0]['url'];
}

// Fallback regex search in raw metadata
if (!$rawManifestUrl && preg_match('/"url":"(https:[^"]+m3u8[^"]*)"/', $metaRes, $m)) {
    $rawManifestUrl = stripslashes($m[1]);
}

if (!$rawManifestUrl) {
    echo json_encode([
        "status" => false,
        "message" => "Could not extract direct stream manifest for video " . $dmId,
        "data" => null
    ], JSON_UNESCAPED_SLASHES);
    exit();
}

echo json_encode([
    "status" => true,
    "message" => "Raw direct HLS stream ready (Zero Watermark / Native ExoPlayer)",
    "data" => [
        "id" => $dmId,
        "title" => $videoTitle,
        "stream_url" => $rawManifestUrl,
        "master_manifest_url" => $rawManifestUrl,
        "headers" => [
            "Referer" => "https://geo.dailymotion.com/",
            "Origin" => "https://geo.dailymotion.com",
            "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36"
        ]
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
