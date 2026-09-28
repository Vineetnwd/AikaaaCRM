<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/User.php';

use Core\Auth;
use Core\User;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Action: Switch Back (allowed for anyone with an original session)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'switch_back') {
    if (isset($_SESSION['original_user'])) {
        $_SESSION['user'] = $_SESSION['original_user'];
        unset($_SESSION['original_user']);
        echo json_encode(['success' => true, 'message' => 'Switched back to original account']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'No original session found']);
    }
    exit;
}

// Only Admin can manage users (all other actions)
if (Auth::role() !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admin role required.']);
    exit;
}

$userModel = new User();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = $_GET['id'] ?? null;
    $action = $_GET['action'] ?? null;

    if ($action === 'impersonate' && $id) {
        $targetUser = $userModel->find($id);
        if ($targetUser) {
            // Optional: Store original user for "Switch Back"
            $_SESSION['original_user'] = $_SESSION['user'];
            
            // Log in as target user
            unset($targetUser['password']);
            $_SESSION['user'] = $targetUser;
            
            echo json_encode(['success' => true, 'message' => 'Logged in as ' . $targetUser['name']]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    } elseif ($id) {
        $user = $userModel->find($id);
        if ($user) {
            unset($user['password']);
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
    } else {
        $filters = [];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
        if (!empty($_GET['role']))   $filters['role']   = $_GET['role'];
        if (isset($_GET['page']))    $filters['page']   = (int)$_GET['page'];
        echo json_encode($userModel->all($filters));
    }

} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if ($id) {
        unset($input['id']);
        try {
            $userModel->update($id, $input);
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
    }

} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id == Auth::userId()) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot delete yourself']);
        exit;
    }
    
    if ($id) {
        $userModel->delete($id);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
    }
}
