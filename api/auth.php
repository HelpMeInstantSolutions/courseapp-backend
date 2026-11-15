<?php
// backend/api/auth.php - Authentication with Phone, Email, OTP & Device Lock Binding
require_once __DIR__ . '/config.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? ($input['action'] ?? '');

switch ($action) {
    case 'register':
        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $phone = trim($input['phone'] ?? '');
        $password = trim($input['password'] ?? '');
        $deviceId = trim($input['device_id'] ?? '');
        $deviceModel = trim($input['device_model'] ?? 'Android Device');

        if (!$name || !$email || !$password) {
            sendResponse(false, "Name, email and password are required", null, 400);
        }

        if (!$phone) {
            sendResponse(false, "Valid 10-digit mobile number is required", null, 400);
        }

        // Clean phone digits
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) < 10) {
            sendResponse(false, "Please enter a valid 10-digit mobile number", null, 400);
        }

        // Check if email already exists
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            sendResponse(false, "This email address is already registered. Please login.", null, 409);
        }

        // Generate 6-digit OTP code
        $otp = (string)rand(100000, 999999);
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, plain_password, otp_code, is_verified, device_id, device_model, status) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, 1)");
        $stmt->execute([$name, $email, $cleanPhone, $hash, $password, $otp, $deviceId ?: null, $deviceModel]);
        $newId = $pdo->lastInsertId();

        sendResponse(true, "Registration successful! Enter the 6-digit verification code.", [
            'id' => (int)$newId,
            'name' => $name,
            'email' => $email,
            'phone' => $cleanPhone,
            'otp_code' => $otp // Provided in response for seamless development & verification
        ]);
        break;

    case 'verify_otp':
        $userId = (int)($input['user_id'] ?? ($_GET['user_id'] ?? 0));
        $email = strtolower(trim($input['email'] ?? ($_GET['email'] ?? '')));
        $otp = trim($input['otp_code'] ?? ($input['otp'] ?? ''));
        $deviceId = trim($input['device_id'] ?? '');
        $deviceModel = trim($input['device_model'] ?? 'Android Device');

        if (!$otp) {
            sendResponse(false, "Please enter 6-digit OTP code", null, 400);
        }

        $user = null;
        if ($userId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } elseif ($email) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            sendResponse(false, "Student account not found", null, 404);
        }

        // Verify OTP match (or fallback master OTP 123456 for testing)
        if ($user['otp_code'] === $otp || $otp === '123456') {
            $upSql = "UPDATE users SET is_verified = 1, otp_code = NULL";
            $params = [];
            if ($deviceId && empty($user['device_id'])) {
                $upSql .= ", device_id = ?, device_model = ?";
                $params[] = $deviceId;
                $params[] = $deviceModel;
            }
            $upSql .= " WHERE id = ?";
            $params[] = $user['id'];

            $up = $pdo->prepare($upSql);
            $up->execute($params);

            sendResponse(true, "Account verified successfully! Welcome to BatchX.", [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone']
            ]);
        } else {
            sendResponse(false, "Invalid OTP code. Please enter the correct code or check with Admin.", null, 400);
        }
        break;

    case 'resend_otp':
        $userId = (int)($input['user_id'] ?? ($_GET['user_id'] ?? 0));
        $email = strtolower(trim($input['email'] ?? ($_GET['email'] ?? '')));

        $stmt = $pdo->prepare("SELECT id, name, email, phone FROM users WHERE id = ? OR email = ?");
        $stmt->execute([$userId, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(false, "User not found", null, 404);
        }

        $newOtp = (string)rand(100000, 999999);
        $up = $pdo->prepare("UPDATE users SET otp_code = ? WHERE id = ?");
        $up->execute([$newOtp, $user['id']]);

        sendResponse(true, "New OTP code generated successfully!", [
            'otp_code' => $newOtp
        ]);
        break;

    case 'login':
        $email = strtolower(trim($input['email'] ?? ''));
        $password = trim($input['password'] ?? '');
        $deviceId = trim($input['device_id'] ?? '');
        $deviceModel = trim($input['device_model'] ?? 'Android Device');

        if (!$email || !$password) {
            sendResponse(false, "Email and password are required", null, 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse(false, "No student account found with this email / phone", null, 401);
        }

        // Verify password
        $passValid = password_verify($password, $user['password']) || ($user['plain_password'] && $user['plain_password'] === $password);
        if (!$passValid) {
            sendResponse(false, "Incorrect password. Please try again or ask admin to reset.", null, 401);
        }

        // Check if deactivated
        if ((int)$user['status'] === 0) {
            sendResponse(false, "Your account has been deactivated by the administrator. Please contact support.", null, 403);
        }

        // Check if verified
        if ((int)($user['is_verified'] ?? 1) === 0) {
            sendResponse(false, "Account verification pending. Please verify OTP code.", [
                'need_otp' => true,
                'user_id' => (int)$user['id'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'otp_code' => $user['otp_code']
            ], 403);
        }

        // SINGLE DEVICE BINDING LOGIC
        if ($deviceId) {
            if (empty($user['device_id'])) {
                // First login: Bind this device
                $bindStmt = $pdo->prepare("UPDATE users SET device_id = ?, device_model = ? WHERE id = ?");
                $bindStmt->execute([$deviceId, $deviceModel, $user['id']]);
                $user['device_id'] = $deviceId;
                $user['device_model'] = $deviceModel;
            } else if ($user['device_id'] !== $deviceId) {
                // Mismatched device!
                $registeredModel = !empty($user['device_model']) ? $user['device_model'] : 'your registered smartphone';
                sendResponse(false, "Account is already linked to another device ({$registeredModel}). One account can only be used on 1 device. Contact admin to unbind device.", [
                    'device_locked' => true,
                    'bound_model' => $user['device_model']
                ], 403);
            }
        }

        sendResponse(true, "Login successful! Welcome to BatchX.", [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'device_id' => $user['device_id'] ?? ''
        ]);
        break;

    default:
        sendResponse(false, "Invalid auth action", null, 400);
        break;
}