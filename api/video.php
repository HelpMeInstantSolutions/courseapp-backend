<?php
// backend/api/video.php
require_once __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    sendResponse(false, "Video ID is required", null, 400);
}

$stmt = $pdo->prepare("SELECT v.*, c.title as course_title, c.thumbnail as course_thumbnail 
                       FROM videos v 
                       JOIN courses c ON v.course_id = c.id 
                       WHERE v.id = ? AND v.status = 1");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) {
    sendResponse(false, "Video not found or disabled", null, 404);
}

// Generate Dailymotion embed URL
$dmId = $video['dailymotion_id'];

// If full URL was entered, extract ID
if (preg_match('/(?:dailymotion\.com\/(?:video|hub)\/|dai\.ly\/)([a-zA-Z0-9]+)/', $dmId, $matches)) {
    $dmId = $matches[1];
}

$video['clean_dm_id'] = $dmId;
$video['embed_url'] = "https://www.dailymotion.com/embed/video/" . $dmId . "?autoplay=1&mute=0&controls=1&ui-theme=dark";

sendResponse(true, "Video details retrieved", $video);
