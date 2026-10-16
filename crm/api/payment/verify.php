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
$rzp_payment_id = $input['razorpay_payment_id'] ?? '';
$rzp_order_id = $input['razorpay_order_id'] ?? '';
$rzp_signature = $input['razorpay_signature'] ?? '';
$planName = $input['plan_name'] ?? 'Custom';
$durationUnit = $input['duration_unit'] ?? 'Months';
$durationValue = (int)($input['duration_value'] ?? 1);

if (empty($rzp_payment_id) || empty($rzp_order_id) || empty($rzp_signature)) {
    echo json_encode(['success' => false, 'error' => 'Invalid payment details']);
    exit;
}

$keySecret = '6yC4CeWP1sW1X7afM12qvZBp';

$generated_signature = hash_hmac('sha256', $rzp_order_id . "|" . $rzp_payment_id, $keySecret);

if (hash_equals($generated_signature, $rzp_signature)) {
    // Payment successful
    $db = Database::getInstance();
    $company_id = Auth::companyId();
    $company = Auth::company();
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0.00;
    
    // Auto-create subscription_payments table if it doesn't exist
    try {
        $db->query("CREATE TABLE IF NOT EXISTS subscription_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            user_id INT NULL,
            plan_name VARCHAR(100) NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(10) DEFAULT 'INR',
            duration_value INT DEFAULT 1,
            duration_unit VARCHAR(50) DEFAULT 'Months',
            razorpay_payment_id VARCHAR(100) NULL,
            razorpay_order_id VARCHAR(100) NULL,
            razorpay_signature VARCHAR(255) NULL,
            subscription_starts_at DATETIME NULL,
            subscription_ends_at DATETIME NULL,
            status VARCHAR(50) DEFAULT 'paid',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {}

    // Calculate new expiry date
    $currentExpiry = $company['expiry_date'] ?? $company['effective_expiry_date'] ?? ($company['subscription_ends_at'] ?? null);
    if (empty($currentExpiry) || strtotime($currentExpiry) < time()) {
        $baseDate = date('Y-m-d H:i:s');
    } else {
        $baseDate = date('Y-m-d H:i:s', strtotime($currentExpiry));
    }
    
    $interval = "1 MONTH";
    if (strtolower($durationUnit) === 'days') {
        $interval = "$durationValue DAY";
    } elseif (strtolower($durationUnit) === 'years') {
        $interval = "$durationValue YEAR";
    } else {
        $interval = "$durationValue MONTH";
    }
    
    $newExpiry = date('Y-m-d H:i:s', strtotime($baseDate . " + " . strtolower($interval)));
    $subStartsAt = empty($company['subscription_starts_at']) ? date('Y-m-d H:i:s') : $company['subscription_starts_at'];

    try {
        $db->query("UPDATE companies SET plan = ?, subscription_ends_at = ?, subscription_starts_at = ? WHERE id = ?", [
            strtolower($planName), 
            $newExpiry,
            $subStartsAt,
            $company_id
        ]);

        $paymentId = $db->insert('subscription_payments', [
            'company_id' => $company_id,
            'user_id' => Auth::user()['id'] ?? null,
            'plan_name' => $planName,
            'amount' => $amount,
            'currency' => 'INR',
            'duration_value' => $durationValue,
            'duration_unit' => $durationUnit,
            'razorpay_payment_id' => $rzp_payment_id,
            'razorpay_order_id' => $rzp_order_id,
            'razorpay_signature' => $rzp_signature,
            'subscription_starts_at' => $baseDate,
            'subscription_ends_at' => $newExpiry,
            'status' => 'paid',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        try {
            $db->insert('subscription_logs', [
                'company_id' => $company_id,
                'admin_id' => Auth::user()['id'] ?? null,
                'action' => 'Subscription Renewal',
                'changes' => json_encode([
                    'plan' => ['old' => $company['plan'] ?? 'trial', 'new' => strtolower($planName)],
                    'expiry' => ['old' => $currentExpiry ?: ($company['trial_ends_at'] ?? 'none'), 'new' => $newExpiry],
                    'amount' => '₹' . number_format($amount, 2),
                    'payment_id' => $rzp_payment_id
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {}
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription successfully renewed!',
            'payment_id' => $paymentId,
            'receipt_url' => APP_URL . '/public/index.php/subscription_receipt?payment_id=' . $paymentId,
            'new_expiry' => $newExpiry
        ]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'error' => 'Database update failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Payment verification failed. Signature mismatch.']);
}
