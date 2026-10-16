<?php
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

use Core\Auth;
use Core\Database;

if (!Auth::check() || (!Auth::isAdmin() && !Auth::isSuperAdmin())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? (int)$input['amount'] : 0;

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount']);
    exit;
}

$keyId = 'rzp_live_Tiem2d5K6XCX6a';
$keySecret = '6yC4CeWP1sW1X7afM12qvZBp';

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_USERPWD, $keyId . ':' . $keySecret);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'amount' => $amount,
    'currency' => 'INR',
    'receipt' => 'rcpt_' . Auth::companyId() . '_' . time()
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpStatus === 200 && $response) {
    $data = json_decode($response, true);
    if (isset($data['id'])) {
        echo json_encode([
            'success' => true, 
            'order_id' => $data['id'],
            'key_id' => $keyId
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Could not create Razorpay order', 'details' => $response]);
