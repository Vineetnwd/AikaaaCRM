<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Lead.php';

use Core\Auth;
use Core\Database;

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}


if (!empty($_POST['payload'])) {
    $input = json_decode($_POST['payload'], true);
} else {
    $input = json_decode(file_get_contents('php://input'), true);
}
$leadIds = !empty($input['lead_ids']) ? $input['lead_ids'] : (!empty($input['lead_id']) ? [$input['lead_id']] : []);
$customerIds = !empty($input['customer_ids']) ? $input['customer_ids'] : (!empty($input['customer_id']) ? [$input['customer_id']] : []);

$invoiceIds = !empty($input['invoice_ids']) ? $input['invoice_ids'] : (!empty($input['invoice_id']) ? [$input['invoice_id']] : []);
$quotationIds = !empty($input['quotation_ids']) ? $input['quotation_ids'] : (!empty($input['quotation_id']) ? [$input['quotation_id']] : []);

if (empty($leadIds) && empty($customerIds) && empty($invoiceIds) && empty($quotationIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Lead ID(s), Customer ID(s), Invoice ID(s) or Quotation ID(s) are required']);
    exit;
}

$companyId = Auth::companyId();
$db = Database::getInstance();

// Fetch Company WA Settings
$company = $db->fetchOne("SELECT name, whatsapp_access_token, whatsapp_number, whatsapp_default_template, whatsapp_default_mapping, whatsapp_header_image FROM companies WHERE id = ?", [$companyId]);

if (empty($company['whatsapp_access_token']) || empty($company['whatsapp_number'])) {
    http_response_code(400);
    echo json_encode(['error' => 'WhatsApp API Access Token and WhatsApp Number must be configured in Settings.']);
    exit;
}

if (empty($company['whatsapp_default_template'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No Default WhatsApp Template configured. Please set it in Settings.']);
    exit;
}

$templateName = $company['whatsapp_default_template'];
$fromNumber = trim($company['whatsapp_number'] ?? '');
if (!empty($fromNumber) && $fromNumber[0] !== '+') {
    $fromNumber = '+' . ltrim($fromNumber, '+');
}
$mapping = array_map('trim', explode(',', $company['whatsapp_default_mapping'] ?? ''));
$customVariables = $input['custom_variables'] ?? [];

$successCount = 0;
$failCount = 0;
$lastErrorDetails = null;

$headerImageLink = $company['whatsapp_header_image'] ?? '';

if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../public/uploads/whatsapp/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileInfo = pathinfo($_FILES['image']['name']);
    $ext = strtolower($fileInfo['extension'] ?? '');
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($ext, $allowedExts)) {
        $filename = 'wa_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
            $headerImageLink = APP_URL . '/public/uploads/whatsapp/' . $filename;
        }
    }
}

$processPerson = function ($lead, $isLead) use ($company, $fromNumber, $templateName, $mapping, $customVariables, $headerImageLink, &$successCount, &$failCount, &$lastErrorDetails) {
    if (!$lead)
        return;

    $mobile = $lead['mobile'] ?: ($lead['customer_mobile'] ?? '');
    if (empty($mobile)) {
        $failCount++;
        return;
    }

    $mobile = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($mobile) == 10) {
        $mobile = '91' . $mobile;
    }

    $dynamicData = [
        'name' => $lead['name'] ?: ($lead['customer_name'] ?? '') ?: 'Customer',
        'mobile' => $mobile,
        'email' => $lead['email'] ?: ($lead['customer_email'] ?? '') ?: 'N/A',
        'address' => $lead['address'] ?: ($lead['customer_address'] ?? '') ?: 'N/A',
        'service' => $lead['service_name'] ?? 'Our Services',
        'status' => ucfirst(str_replace('_', ' ', $lead['status'] ?? 'new')),
        'value' => $lead['value'] ?? '0',
        'link' => $lead['link'] ?? '',
        'invoice_link' => $lead['invoice_link'] ?? '',
        'msg' => $lead['msg'] ?? '',
        'msg2' => $lead['msg2'] ?? ''
    ];

    $parameters = [];
    foreach ($mapping as $index => $key) {
        $cleanKey = trim($key);
        if (!empty($cleanKey)) {
            $isLinkField = ($cleanKey === 'link' || $cleanKey === 'invoice_link' || $cleanKey === 'url' || strpos($cleanKey, 'invoice_print.php') !== false || strpos($cleanKey, 'quotation_print.php') !== false || strpos($cleanKey, 'quotation/print') !== false || strpos($cleanKey, 'token=') !== false || strpos($cleanKey, 'http') === 0);
            
            $customVal = $customVariables[$index] ?? '';

            // If it's a link field and dynamic link is available:
            // If customVal was empty or was the hardcoded template link containing id=94 or matching template key:
            if ($isLinkField && !empty($lead['link'])) {
                if ($customVal === '' || strpos($customVal, 'id=94&token=1482288e887b1186c20c1e260391493a') !== false || $customVal === $cleanKey || (isset($lead['quotation_number']) && strpos($customVal, 'invoice_print.php') !== false)) {
                    $parameters[] = (string) $lead['link'];
                    continue;
                }
            }

            // Smart Variables: If custom input is provided and not empty, use it. Otherwise fallback to dynamic mapped value.
            if ($customVal !== '') {
                $parameters[] = (string) $customVal;
            } else {
                if ($isLinkField && !empty($lead['link'])) {
                    $dynVal = $lead['link'];
                } else {
                    $dynVal = $dynamicData[$cleanKey] ?? $cleanKey;
                    if (empty($dynVal) && $index === 3) {
                        $dynVal = !empty($lead['link']) ? $lead['link'] : ($company['name'] ?? 'Company Name');
                    }
                }
                $parameters[] = $dynVal;
            }
        }
    }

    $payload = [
        "from" => $fromNumber,
        "campaignName" => "crm-api",
        "to" => "+" . ltrim($mobile, '+'),
        "templateName" => $templateName,
        "type" => "template",
        "components" => [
            "body" => [
                "params" => $parameters
            ]
        ]
    ];

    if (!empty($headerImageLink)) {
        $payload["components"]["header"] = [
            "type" => "image",
            "image" => [
                "link" => $headerImageLink
            ]
        ];
    }

    $url = "https://api.aoc-portal.com/v1/whatsapp";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: " . $company['whatsapp_access_token'],
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode >= 200 && $httpcode < 300) {
        $successCount++;
    } else {
        $failCount++;
        $lastErrorDetails = json_decode($response, true) ?: $response;
    }
};

foreach ($leadIds as $id) {
    $lead = $db->fetchOne("SELECT l.*, c.name as customer_name, c.mobile as customer_mobile, c.email as customer_email, c.address as customer_address,
        (SELECT GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') FROM lead_requirements lr JOIN requirements r ON lr.requirement_id = r.id WHERE lr.lead_id = l.id) as service_name
        FROM leads l 
        LEFT JOIN customers c ON l.customer_id = c.id 
        WHERE l.id = ? AND l.company_id = ?", [$id, $companyId]);
    $processPerson($lead, true);
}

foreach ($customerIds as $id) {
    $customer = $db->fetchOne("SELECT c.name, c.mobile, c.email, c.address 
        FROM customers c 
        WHERE c.id = ? AND c.company_id = ?", [$id, $companyId]);
    if ($customer) {
        $customer['status'] = 'customer';
        $customer['service_name'] = '';
        $processPerson($customer, false);
    }
}

foreach ($invoiceIds as $id) {
    $invoice = $db->fetchOne("SELECT i.*, l.name as customer_name, l.mobile as customer_mobile, l.email as customer_email, l.address as customer_address 
        FROM invoices i 
        LEFT JOIN leads l ON i.lead_id = l.id 
        WHERE i.id = ? AND i.company_id = ?", [$id, $companyId]);
    if ($invoice) {
        $invoice['name'] = $invoice['customer_name'] ?: 'Customer';
        $invoice['mobile'] = $invoice['customer_mobile'];
        $invoice['email'] = $invoice['customer_email'];
        $invoice['address'] = $invoice['customer_address'];
        $invoice['status'] = 'Due';
        $invoice['service_name'] = 'Invoice ' . $invoice['invoice_number'];
        $invoice['value'] = $invoice['due_amount'];
        $invoice['msg'] = "Here is your invoice " . $invoice['invoice_number'] . " for the amount of ₹" . number_format($invoice['due_amount'], 2) . ".";
        $invoice['msg2'] = "To view your invoice, please click the link below.👇";

        $invoice['link'] = APP_URL . "/public/assets/views/invoice_print.php?id=" . $id . "&token=" . md5($id . 'your-very-secure-secret-key-123456');
        $invoice['invoice_link'] = $invoice['link'];

        $processPerson($invoice, false);
    }
}

foreach ($quotationIds as $id) {
    $quotation = $db->fetchOne("SELECT q.*, l.name as customer_name, l.mobile as customer_mobile, l.email as customer_email, l.address as customer_address 
        FROM quotations q 
        LEFT JOIN leads l ON q.lead_id = l.id 
        WHERE q.id = ? AND q.company_id = ?", [$id, $companyId]);
    if ($quotation) {
        $quotation['name'] = $quotation['customer_name'] ?: 'Customer';
        $quotation['mobile'] = $quotation['customer_mobile'];
        $quotation['email'] = $quotation['customer_email'];
        $quotation['address'] = $quotation['customer_address'];
        $quotation['status'] = ucfirst($quotation['status']);
        $quotation['service_name'] = 'Quotation ' . $quotation['quotation_number'];
        $quotation['value'] = $quotation['total_amount'];
        $quotation['msg'] = "Here is your project proposal and quotation " . $quotation['quotation_number'] . " for the amount of ₹" . number_format($quotation['total_amount'], 2) . ".";
        $quotation['msg2'] = "We look forward to working with you. Please let us know if you have any questions.👇";

        $quotation['link'] = APP_URL . "/public/index.php/quotation/print?id=" . $id;
        $quotation['invoice_link'] = $quotation['link'];

        $processPerson($quotation, false);
    }
}

if ($failCount > 0 && $successCount == 0) {
    http_response_code(400);
    echo json_encode([
        'error' => 'API Error or No Valid Numbers',
        'details' => $lastErrorDetails
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => "Successfully sent $successCount messages." . ($failCount > 0 ? " ($failCount failed)" : "")
    ]);
}
