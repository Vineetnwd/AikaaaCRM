<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

use Core\Database;
use Core\Auth;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'update_profile') {
        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        
        if (empty($name) || empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Name and Email are required']);
            exit;
        }

        $userModel = new \Core\User();
        try {
            $updateData = [
                'name' => $name,
                'email' => $email
            ];
            
            // Allow updating mobile and other fields if provided
            if (isset($input['mobile'])) $updateData['mobile'] = trim($input['mobile']);
            if (isset($input['designation'])) $updateData['designation'] = trim($input['designation']);
            if (isset($input['department'])) $updateData['department'] = trim($input['department']);
            if (isset($input['address'])) $updateData['address'] = trim($input['address']);
            if (isset($input['gender'])) $updateData['gender'] = $input['gender'];
            if (isset($input['date_of_birth'])) $updateData['date_of_birth'] = $input['date_of_birth'];

            $userModel->update($userId, $updateData);

            // Update session
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            if (isset($updateData['mobile'])) $_SESSION['user']['mobile'] = $updateData['mobile'];

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'update_password') {
        $input = json_decode(file_get_contents('php://input'), true);
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            echo json_encode(['success' => false, 'error' => 'All fields are required']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'error' => 'New passwords do not match']);
            exit;
        }

        $user = $db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
        if (!password_verify($currentPassword, $user['password'])) {
            echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->query("UPDATE users SET password = ? WHERE id = ?", [$hashedPassword, $userId]);

        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        exit;
    }

    if ($action === 'update_dp') {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No image uploaded']);
            exit;
        }

        $file = $_FILES['photo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Only JPG, PNG and WEBP are allowed']);
            exit;
        }

        $uploadDir = __DIR__ . '/../public/uploads/profiles/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                echo json_encode(['success' => false, 'error' => 'Failed to create profiles directory. Check permissions.']);
                exit;
            }
        }

        if (!is_writable($uploadDir)) {
            echo json_encode(['success' => false, 'error' => 'Upload directory is not writable: ' . $uploadDir]);
            exit;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = $userId . '_' . time() . '.' . $extension;
        $targetFile = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Delete old image if exists
            $oldUser = $db->fetchOne("SELECT photo FROM users WHERE id = ?", [$userId]);
            if ($oldUser['photo'] && file_exists($uploadDir . $oldUser['photo'])) {
                unlink($uploadDir . $oldUser['photo']);
            }

            $userModel = new \Core\User();
            $userModel->update($userId, ['photo' => $fileName]);

            // Update session
            $_SESSION['user']['photo'] = $fileName;

            echo json_encode(['success' => true, 'message' => 'Profile picture updated', 'image_url' => APP_URL . '/public/uploads/profiles/' . $fileName]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save image']);
        }
        exit;
    }
    if ($action === 'update_banking') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Find linked employee
        $user = $db->fetchOne("SELECT employee_id, emp_id FROM users WHERE id = ?", [$userId]);
        $empId = $user['employee_id'] ?: ($user['emp_id'] ?? null);
        if (!$empId) {
            echo json_encode(['success' => false, 'error' => 'No linked employee record found']);
            exit;
        }

        $fields = [
            'account_holder_name' => trim($input['account_holder_name'] ?? ''),
            'bank_name'           => trim($input['bank_name'] ?? ''),
            'account_number'      => trim($input['account_number'] ?? ''),
            'ifsc_code'           => trim($input['ifsc_code'] ?? ''),
            'upi_id'              => trim($input['upi_id'] ?? ''),
        ];

        $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
        $values = array_values($fields);
        $values[] = $empId;
        $db->query("UPDATE employees SET $sets WHERE id = ?", $values);

        echo json_encode(['success' => true, 'message' => 'Banking details updated']);
        exit;
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
