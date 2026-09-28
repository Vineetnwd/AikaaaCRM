<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Company.php';

use Core\Auth;
use Core\Company;

if (!Auth::check() || !Auth::isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Super Admin role required.']);
    exit;
}

$companyModel = new Company();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? null;
        $id = $_GET['id'] ?? null;
        if ($action === 'logs' && $id) {
            echo json_encode($companyModel->getLogs(intval($id)));
            exit;
        }
        if ($action === 'impersonate') {
            $companyId = intval($_GET['company_id'] ?? $id ?? 0);
            if (!$companyId) {
                http_response_code(400);
                echo json_encode(['error' => 'Company ID is required']);
                exit;
            }
            $targetCompany = $companyModel->find($companyId);
            if (!$targetCompany) {
                http_response_code(404);
                echo json_encode(['error' => 'Company not found']);
                exit;
            }

            $db = \Core\Database::getInstance();
            // 1. Try finding an active admin user for this company
            $targetUser = $db->fetchOne(
                "SELECT * FROM users WHERE company_id = ? AND role = 'admin' AND (LOWER(status) = 'active' OR status IS NULL OR status = '1' OR status = '') LIMIT 1",
                [$companyId]
            );

            // 2. Fallback to any active user in this company
            if (!$targetUser) {
                $targetUser = $db->fetchOne(
                    "SELECT * FROM users WHERE company_id = ? AND (LOWER(status) = 'active' OR status IS NULL OR status = '1' OR status = '') LIMIT 1",
                    [$companyId]
                );
            }

            // 3. Fallback to any user in this company
            if (!$targetUser) {
                $targetUser = $db->fetchOne("SELECT * FROM users WHERE company_id = ? LIMIT 1", [$companyId]);
            }

            if (!$targetUser) {
                http_response_code(404);
                echo json_encode(['error' => 'No user account found for this company to login as.']);
                exit;
            }

            unset($targetUser['password']);
            $token = Auth::generateToken($targetUser, true);

            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => $targetUser,
                'company' => $targetCompany,
                'is_impersonating' => true
            ]);
            exit;
        }
        if ($id) {
            $company = $companyModel->find(intval($id));
            if ($company) {
                echo json_encode($company);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Company not found']);
            }
        } else {
            $filters = [];
            if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
            echo json_encode($companyModel->all($filters));
        }
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Company name is required']);
            exit;
        }
        $id = $companyModel->create($input);
        echo json_encode(['success' => true, 'id' => $id]);

    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Company ID required']);
            exit;
        }
        unset($input['id']);
        $companyModel->update($id, $input);
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Company ID required']);
            exit;
        }
        $companyModel->delete(intval($id));
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
