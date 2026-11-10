<?php
// backend/api/auth.php
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'register':
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = trim($input['password'] ?? '');
        $phone = trim($input['phone'] ?? '');

        if (!$name || !$email || !$password) {
            sendResponse(false, "Name, email and password are required", null, 400);
        }

        // Check if email already exists
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            sendResponse(false, "This email is already registered. Please login.", null, 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hash, $phone]);
        $newId = $pdo->lastInsertId();

        sendResponse(true, "Registration successful! Welcome to Insight Course App.", [
            'id' => (int)$newId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ]);
        break;

    case 'login':
        $email = strtolower(trim($input['email'] ?? ''));
        $password = trim($input['password'] ?? '');

        if (!$email || !$password) {
            sendResponse(false, "Email and password are required", null, 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            sendResponse(true, "Login successful! Welcome back.", [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone']
            ]);
        } else {
            sendResponse(false, "Invalid email or password", null, 401);
        }
        break;

    default:
        sendResponse(false, "Invalid auth action", null, 400);
        break;
}
