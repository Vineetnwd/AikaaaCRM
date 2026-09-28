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

try {
    $action = $_GET['action'] ?? 'search';

    if ($action === 'services') {
        // Fetch all predefined services
        $services = $db->fetchAll("SELECT id, name FROM requirements WHERE company_id = ? ORDER BY name ASC", [$company_id]);
        
        // Fetch unique custom service names from leads
        $customLeads = $db->fetchAll("SELECT custom_services FROM leads WHERE company_id = ? AND custom_services IS NOT NULL AND custom_services != '' AND custom_services != '[]'", [$company_id]);
        $customServiceNames = [];
        foreach ($customLeads as $lead) {
            $data = json_decode($lead['custom_services'], true);
            if (is_array($data)) {
                foreach ($data as $item) {
                    if (!empty($item['name'])) {
                        $customServiceNames[] = $item['name'];
                    }
                }
            }
        }
        $customServiceNames = array_unique($customServiceNames);
        sort($customServiceNames);

        echo json_encode([
            'predefined' => $services,
            'custom' => $customServiceNames
        ]);
        exit;
    }

    if ($action === 'search') {
        $serviceId = $_GET['service_id'] ?? null;
        $customName = $_GET['custom_name'] ?? null;

        $query = "SELECT DISTINCT l.customer_id as id, l.name, l.mobile, l.email, l.created_at as lead_date 
                  FROM leads l ";
        $params = [];

        if ($serviceId) {
            $query .= "JOIN lead_requirements lr ON l.id = lr.lead_id 
                       WHERE lr.requirement_id = ? AND l.company_id = ?";
            $params = [$serviceId, $company_id];
        } elseif ($customName) {
            $query .= "WHERE l.company_id = ? AND JSON_SEARCH(l.custom_services, 'one', ?, NULL, '$[*].name') IS NOT NULL";
            $params = [$company_id, $customName];
        } else {
            echo json_encode([]);
            exit;
        }

        $query .= " ORDER BY l.created_at DESC";
        $results = $db->fetchAll($query, $params);
        echo json_encode($results);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
