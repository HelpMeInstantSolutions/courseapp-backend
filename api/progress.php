<?php
// backend/api/progress.php - Video Progress Tracking & Resume Learning
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? 'save');

switch ($action) {
    case 'save':
        $userId = (int)($input['user_id'] ?? 0);
        $videoId = (int)($input['video_id'] ?? 0);
        $position = (int)($input['position'] ?? 0);
        $duration = (int)($input['duration'] ?? 0);

        if ($userId <= 0 || $videoId <= 0) {
            sendResponse(false, "User ID and Video ID are required", null, 400);
        }

        $percent = 0;
        if ($duration > 0) {
            $percent = min(100, max(0, (int)round(($position / $duration) * 100)));
        }
        $isCompleted = ($percent >= 90) ? 1 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO user_video_progress (user_id, video_id, last_position_seconds, duration_seconds, percent_watched, is_completed, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, datetime('now'))
            ON CONFLICT(user_id, video_id) DO UPDATE SET
                last_position_seconds = excluded.last_position_seconds,
                duration_seconds = MAX(duration_seconds, excluded.duration_seconds),
                percent_watched = MAX(percent_watched, excluded.percent_watched),
                is_completed = CASE WHEN is_completed = 1 THEN 1 ELSE excluded.is_completed END,
                updated_at = datetime('now')
        ");
        $stmt->execute([$userId, $videoId, $position, $duration, $percent, $isCompleted]);

        sendResponse(true, "Progress saved", [
            'position' => $position,
            'duration' => $duration,
            'percent' => $percent,
            'is_completed' => $isCompleted
        ]);
        break;

    case 'get':
        $userId = (int)($_GET['user_id'] ?? 0);
        $videoId = (int)($_GET['video_id'] ?? 0);

        if ($userId <= 0 || $videoId <= 0) {
            sendResponse(false, "User ID and Video ID are required", null, 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM user_video_progress WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        $prog = $stmt->fetch();

        sendResponse(true, "Progress retrieved", $prog ?: [
            'user_id' => $userId,
            'video_id' => $videoId,
            'last_position_seconds' => 0,
            'percent_watched' => 0,
            'is_completed' => 0
        ]);
        break;

    case 'continue_watching':
        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            sendResponse(false, "User ID is required", null, 400);
        }

        $stmt = $pdo->prepare("
            SELECT p.*, v.title as video_title, v.duration as video_duration, v.dailymotion_id, v.pdf_url, v.is_free_preview,
                   ch.title as chapter_title, s.title as subject_title, c.title as batch_title, c.thumbnail as batch_thumbnail
            FROM user_video_progress p
            JOIN videos v ON p.video_id = v.id
            LEFT JOIN chapters ch ON v.chapter_id = ch.id
            LEFT JOIN subjects s ON v.subject_id = s.id
            LEFT JOIN courses c ON v.course_id = c.id
            WHERE p.user_id = ? AND p.is_completed = 0 AND p.last_position_seconds > 5
            ORDER BY p.updated_at DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll();

        sendResponse(true, "Continue watching list", $items);
        break;

    default:
        sendResponse(false, "Invalid progress action", null, 400);
        break;
}
