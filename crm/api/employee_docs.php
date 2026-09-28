<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Employee.php';

use Core\Auth;
use Core\Employee;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$employeeModel = new Employee();
$method = $_SERVER['REQUEST_METHOD'];
$companyId = Auth::companyId();

// GET: List docs for an employee
if ($method === 'GET') {
    $empId = intval($_GET['employee_id'] ?? 0);
    if (!$empId) {
        echo json_encode(['error' => 'employee_id required']);
        exit;
    }
    echo json_encode($employeeModel->getDocuments($empId));
    exit;
}

// POST: Upload a doc
if ($method === 'POST') {
    $empId   = intval($_POST['employee_id'] ?? 0);
    $docName = trim($_POST['doc_name'] ?? '');

    if (!$empId || !$docName) {
        http_response_code(400);
        echo json_encode(['error' => 'employee_id and doc_name are required']);
        exit;
    }

    if (!isset($_FILES['doc_file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['doc_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload error: ' . $file['error']]);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowedExts)) {
        http_response_code(400);
        echo json_encode(['error' => 'Only JPG, PNG, WEBP, and PDF files are allowed.']);
        exit;
    }

    $fileType   = ($ext === 'pdf') ? 'pdf' : 'image';
    $uploadDir  = __DIR__ . '/../public/uploads/employee_docs/' . $empId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newName  = time() . '_' . rand(1000, 9999) . '.' . $ext;
    $destPath = $uploadDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        exit;
    }

    chmod($destPath, 0644);
    $relativePath = 'uploads/employee_docs/' . $empId . '/' . $newName;

    $db = Core\Database::getInstance();
    $db->insert('employee_documents', [
        'employee_id' => $empId,
        'company_id'  => $companyId,
        'doc_name'    => $docName,
        'file_path'   => $relativePath,
        'file_type'   => $fileType,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);

    echo json_encode(['success' => true, 'file_path' => $relativePath, 'file_type' => $fileType, 'doc_name' => $docName]);
    exit;
}

// DELETE: Remove a doc
if ($method === 'DELETE') {
    if (Auth::isExecutive()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied: Executives cannot delete documents.']);
        exit;
    }
    
    $docId = intval($_GET['id'] ?? 0);
    if (!$docId) {
        http_response_code(400);
        echo json_encode(['error' => 'id required']);
        exit;
    }
    try {
        $employeeModel->deleteDocument($docId);
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
