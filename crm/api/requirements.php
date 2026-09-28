<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Requirement.php';

use Core\Auth;
use Core\Requirement;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$requirementModel = new Requirement();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;
        $lead_id = $_GET['lead_id'] ?? null;
        $action = $_GET['action'] ?? null;

        if ($action === 'customers' && $id) {
            echo json_encode($requirementModel->getRequirementCustomers($id));
        } elseif ($id) {
            echo json_encode($requirementModel->find($id));
        } elseif ($lead_id) {
            echo json_encode($requirementModel->getLeadRequirements($lead_id));
        } else {
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit_val = $_GET['limit'] ?? '15';
            $limit = ($limit_val === 'all') ? 1000000 : max(1, (int)$limit_val);
            $search = $_GET['search'] ?? '';
            echo json_encode($requirementModel->all($page, $limit, $search));
        }
    } elseif ($method === 'POST') {
        if (Auth::isExecutive()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['name'])) {
            throw new Exception('Requirement name is required');
        }
        $id = $requirementModel->create($input);
        echo json_encode(['success' => true, 'id' => $id]);
    } elseif ($method === 'PUT') {
        if (Auth::isExecutive()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['id'])) {
            throw new Exception('Requirement ID is required');
        }
        $requirementModel->update($input['id'], $input);
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (Auth::isExecutive()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            throw new Exception('Requirement ID is required');
        }
        $requirementModel->delete($id);
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
