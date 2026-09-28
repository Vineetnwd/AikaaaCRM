<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';

use Core\Auth;
use Core\Database;

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$company_id = Auth::companyId();
$method = $_SERVER['REQUEST_METHOD'];

// Helper to handle file uploads
function handleUpload($file, $targetDir)
{
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '.' . $ext;
    $targetFile = $targetDir . '/' . $fileName;
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $fileName;
    }
    return false;
}

$customer_id = ($method === 'GET') ? ($_GET['id'] ?? null) : ($_POST['customer_id'] ?? null);

if (!$customer_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
    exit;
}

$customer = $db->fetchOne("SELECT * FROM customers WHERE id = :id AND company_id = :comp", [
    'id' => $customer_id,
    'comp' => $company_id
]);
if (!$customer) {
    http_response_code(404);
    echo json_encode(['error' => 'Customer not found']);
    exit;
}

// Executive Access Check
if (Auth::isExecutive()) {
    $userId = Auth::userId();
    $empId = Auth::employeeId();

    $checkSql = "SELECT assigned_to, assigned_employee_id, created_by 
                 FROM leads 
                 WHERE (customer_id = :cid OR (mobile != '' AND mobile COLLATE utf8mb4_unicode_ci = :mob COLLATE utf8mb4_unicode_ci)) 
                 AND company_id = :comp";
    $leads = $db->fetchAll($checkSql, [
        'cid' => $customer_id,
        'mob' => $customer['mobile'],
        'comp' => $company_id
    ]);

    $hasAccess = false;
    foreach ($leads as $l) {
        if ($l['assigned_to'] == $userId || $l['assigned_employee_id'] == $empId) {
            $hasAccess = true;
            break;
        }
    }

    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this customer profile']);
        exit;
    }
}

if ($method === 'GET') {
    $docs = $db->fetchAll("SELECT * FROM customer_doc_ledger WHERE customer_id = ? ORDER BY created_at DESC", [$customer_id]);
    $notes = $db->fetchAll("SELECT * FROM customer_notes WHERE customer_id = ? ORDER BY created_at DESC", [$customer_id]);

    echo json_encode([
        'customer' => $customer,
        'docs' => $docs,
        'notes' => $notes
    ]);
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    // $customer and $customer_id are already fetched and checked above

    try {
        if ($action === 'update_profile') {
            $name = $_POST['name'] ?? '';
            $mobile = $_POST['mobile'] ?? '';
            $email = $_POST['email'] ?? '';
            $address = $_POST['address'] ?? '';

            $db->query("UPDATE customers SET name = ?, mobile = ?, email = ?, address = ? WHERE id = ?", [
                $name,
                $mobile,
                $email,
                $address,
                $customer_id
            ]);
            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } elseif ($action === 'upload_photo') {
            if (!isset($_FILES['photo'])) {
                throw new Exception('No photo uploaded');
            }
            $fileName = handleUpload($_FILES['photo'], __DIR__ . '/../public/uploads/customers/photos');
            if ($fileName) {
                // Delete old photo if exists
                if ($customer['photo'] && file_exists(__DIR__ . '/../public/uploads/customers/photos/' . $customer['photo'])) {
                    unlink(__DIR__ . '/../public/uploads/customers/photos/' . $customer['photo']);
                }
                $db->query("UPDATE customers SET photo = ? WHERE id = ?", [$fileName, $customer_id]);
                echo json_encode(['success' => true, 'photo' => $fileName]);
            } else {
                throw new Exception('Upload failed');
            }
        } elseif ($action === 'delete_photo') {
            if ($customer['photo'] && file_exists(__DIR__ . '/../public/uploads/customers/photos/' . $customer['photo'])) {
                unlink(__DIR__ . '/../public/uploads/customers/photos/' . $customer['photo']);
            }
            $db->query("UPDATE customers SET photo = NULL WHERE id = ?", [$customer_id]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'add_doc') {
            $doc_name = $_POST['doc_name'] ?? 'Untitled Doc';
            if (!isset($_FILES['attachment'])) {
                throw new Exception('No file uploaded');
            }
            $fileName = handleUpload($_FILES['attachment'], __DIR__ . '/../public/uploads/customers/docs');
            if ($fileName) {
                $db->query("INSERT INTO customer_doc_ledger (customer_id, doc_name, attachment) VALUES (?, ?, ?)", [
                    $customer_id,
                    $doc_name,
                    $fileName
                ]);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Upload failed');
            }
        } elseif ($action === 'delete_doc') {
            $doc_id = $_POST['doc_id'] ?? null;
            $doc = $db->fetchOne("SELECT * FROM customer_doc_ledger WHERE id = ? AND customer_id = ?", [$doc_id, $customer_id]);
            if ($doc) {
                if (file_exists(__DIR__ . '/../public/uploads/customers/docs/' . $doc['attachment'])) {
                    unlink(__DIR__ . '/../public/uploads/customers/docs/' . $doc['attachment']);
                }
                $db->query("DELETE FROM customer_doc_ledger WHERE id = ?", [$doc_id]);
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Document not found');
            }
        } elseif ($action === 'add_note') {
            $note = $_POST['note'] ?? '';
            if (empty($note)) {
                throw new Exception('Note content required');
            }
            $db->query("INSERT INTO customer_notes (company_id, customer_id, note) VALUES (?, ?, ?)", [
                $company_id,
                $customer_id,
                $note
            ]);
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete_note') {
            $note_id = $_POST['note_id'] ?? null;
            $db->query("DELETE FROM customer_notes WHERE id = ? AND customer_id = ? AND company_id = ?", [
                $note_id,
                $customer_id,
                $company_id
            ]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
