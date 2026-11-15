<?php
// backend/api/queries.php - Student Help & Doubt Queries Desk API
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'submit':
        $userId = (int)($input['user_id'] ?? ($_GET['user_id'] ?? 0));
        $name = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $subject = trim($input['subject'] ?? 'General Doubt');
        $message = trim($input['message'] ?? '');

        if (!$name || !$message) {
            sendResponse(false, "Name and question message are required.", null, 400);
        }

        $stmt = $pdo->prepare("INSERT INTO user_queries (user_id, name, phone, email, subject, message, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$userId, $name, $phone, $email, $subject, $message]);
        $newId = $pdo->lastInsertId();

        sendResponse(true, "Your question / query has been submitted successfully! Admin & faculty will review and reply soon.", [
            'id' => (int)$newId
        ]);
        break;

    case 'list_my_queries':
        $userId = (int)($input['user_id'] ?? ($_GET['user_id'] ?? 0));
        if ($userId <= 0) {
            sendResponse(false, "User ID is required", null, 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM user_queries WHERE user_id = ? ORDER BY id DESC LIMIT 50");
        $stmt->execute([$userId]);
        sendResponse(true, "Queries retrieved", $stmt->fetchAll());
        break;

    case 'list_all':
        $status = trim($input['status'] ?? ($_GET['status'] ?? ''));
        $sql = "SELECT q.*, u.device_model 
                FROM user_queries q 
                LEFT JOIN users u ON q.user_id = u.id 
                WHERE 1=1";
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= " AND q.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY q.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        sendResponse(true, "Queries retrieved", $stmt->fetchAll());
        break;

    case 'reply':
        $id = (int)($input['id'] ?? 0);
        $reply = trim($input['admin_reply'] ?? '');
        $status = trim($input['status'] ?? 'resolved');

        if ($id <= 0) {
            sendResponse(false, "Invalid query ID", null, 400);
        }

        $stmt = $pdo->prepare("UPDATE user_queries SET admin_reply = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$reply, $status, $id]);
        sendResponse(true, "Reply saved and query marked as {$status}!");
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            sendResponse(false, "Invalid query ID", null, 400);
        }

        $stmt = $pdo->prepare("DELETE FROM user_queries WHERE id = ?");
        $stmt->execute([$id]);
        sendResponse(true, "Query deleted successfully.");
        break;

    default:
        sendResponse(false, "Invalid queries action", null, 400);
        break;
}