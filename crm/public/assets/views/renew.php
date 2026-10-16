<?php
use Core\Auth;
use Core\Database;

if (!Auth::check() || (!Auth::isAdmin() && !Auth::isSuperAdmin())) {
    header("Location: " . APP_URL . "/public/index.php/dashboard");
    exit;
}

$db = Database::getInstance();
$setting = $db->fetchOne("SELECT setting_value FROM website_settings WHERE setting_key = 'pricing_plans'");
$plans = [];
if ($setting && !empty($setting['setting_value'])) {
    $plans = json_decode($setting['setting_value'], true) ?: [];
}

$company = Auth::company();
$currentPlan = $company['plan'] ?? 'Basic';
$expiryDate = $company['expiry_date'] ?? $company['effective_expiry_date'] ?? ($company['subscription_ends_at'] ?? '');
$isExpired = isset($company['is_expired']) ? (bool)$company['is_expired'] : Auth::isSubscriptionExpired();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Subscription | AikaaaCRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .renew-container { padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .plan-card {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
            padding: 2rem; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: all 0.2s; position: relative; overflow: hidden;
        }
        .plan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); border-color: #6366f1; }
        .plan-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
        .plan-price { font-size: 2.5rem; font-weight: 800; color: #6366f1; margin-bottom: 0.5rem; }
        .plan-duration { color: #64748b; font-size: 0.9rem; margin-bottom: 2rem; }
        .plan-features { list-style: none; padding: 0; margin: 0 0 2rem 0; text-align: left; }
        .plan-features li { padding: 0.5rem 0; color: #475569; display: flex; align-items: center; gap: 0.5rem; }
        .plan-features li i { color: #10b981; }
        .btn-renew {
            display: inline-block; width: 100%; padding: 0.75rem 1.5rem;
            background: #6366f1; color: #fff; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-renew:hover { background: #4f46e5; }
        .current-plan-badge {
            position: absolute; top: 1rem; right: -2rem; background: #10b981; color: white;
            padding: 0.25rem 3rem; font-size: 0.75rem; font-weight: 700;
            transform: rotate(45deg);
        }
        .plans-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'partials/sidebar.php'; ?>
    <main class="main-content">
        <div class="renew-container">
            <h2 style="font-size: 2rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">Subscription Plans</h2>
            <p style="color: #64748b; margin-bottom: 2rem;">Choose a plan to renew or upgrade your subscription.</p>
            
            <?php if ($isExpired): ?>
                <div style="background: #fee2e2; border: 1px solid #f87171; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <strong><i class="fas fa-exclamation-triangle"></i> Your subscription has expired.</strong> Please renew your plan to restore full access.
                </div>
            <?php endif; ?>

            <div class="plans-grid">
                <?php foreach ($plans as $plan): ?>
                    <?php if (strtolower($plan['name']) === 'free' || strtolower($plan['name']) === 'trial') continue; ?>
                    <div class="plan-card">
                        <?php if (strtolower($currentPlan) === strtolower($plan['name'])): ?>
                            <div class="current-plan-badge">CURRENT</div>
                        <?php endif; ?>
                        
                        <div class="plan-title"><?= htmlspecialchars($plan['name']) ?></div>
                        <div class="plan-price">₹<?= htmlspecialchars($plan['price']) ?></div>
                        <div class="plan-duration"><?= htmlspecialchars($plan['duration']) ?></div>
                        
                        <ul class="plan-features">
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><i class="fas fa-check-circle"></i> <?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <button class="btn-renew" onclick="startPayment('<?= addslashes($plan['name']) ?>', <?= (float)$plan['price'] * 100 ?>, '<?= addslashes($plan['duration_unit']) ?>', <?= (int)$plan['duration_value'] ?>)">
                            <?= strtolower($currentPlan) === strtolower($plan['name']) ? 'Renew Now' : 'Upgrade' ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<script>
function startPayment(planName, amountPaise, durationUnit, durationValue) {
    if (amountPaise <= 0) {
        Swal.fire('Error', 'Invalid plan price.', 'error');
        return;
    }

    // Show initial loading
    Swal.fire({
        title: 'Preparing Checkout',
        text: 'Connecting to payment gateway...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Step 1: Create an order on our backend
    fetch('<?= APP_URL ?>/public/index.php/api/payment/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            plan_name: planName,
            amount: amountPaise, // in paise
            duration_unit: durationUnit,
            duration_value: durationValue
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            Swal.fire({
                title: 'Order Creation Failed',
                text: data.error || "Unable to initiate payment with Razorpay.",
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            return;
        }

        Swal.close();

        // Step 2: Open Razorpay Checkout
        var options = {
            "key": data.key_id, 
            "amount": amountPaise, 
            "currency": "INR",
            "name": "AikaaaCRM",
            "description": "Subscription Renewal - " + planName,
            "order_id": data.order_id,
            "handler": function (response) {
                // Step 3: Verify Payment on our backend
                verifyPayment(response, planName, durationUnit, durationValue, amountPaise / 100);
            },
            "prefill": {
                "name": "<?= htmlspecialchars(Auth::userName()) ?>",
                "email": "<?= htmlspecialchars(Auth::user()['email'] ?? '') ?>"
            },
            "theme": {
                "color": "#6366f1"
            }
        };

        var rzp = new Razorpay(options);
        rzp.on('payment.failed', function (response){
            console.error('Razorpay payment failed:', response.error);
            Swal.fire({
                title: 'Payment Failed',
                html: `<div style="text-align: left; font-size: 0.9rem; line-height: 1.6;">
                    <p style="margin-bottom: 0.5rem; color: #dc2626;"><strong>${response.error.description || 'Payment was not completed.'}</strong></p>
                    ${response.error.reason ? `<p style="margin: 0; color: #64748b; font-size: 0.8rem;">Reason: ${response.error.reason}</p>` : ''}
                    ${response.error.source ? `<p style="margin: 0; color: #64748b; font-size: 0.8rem;">Source: ${response.error.source}</p>` : ''}
                </div>`,
                icon: 'error',
                confirmButtonText: 'Try Again',
                confirmButtonColor: '#ef4444'
            });
        });

        rzp.open();
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            title: 'Connection Error',
            text: 'An error occurred while connecting to payment service. Please try again.',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    });
}

function verifyPayment(response, planName, durationUnit, durationValue, amountInRupees) {
    // Show verification spinner
    Swal.fire({
        title: 'Verifying Payment',
        text: 'Please wait while we confirm your transaction and update your subscription...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('<?= APP_URL ?>/public/index.php/api/payment/verify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            razorpay_payment_id: response.razorpay_payment_id,
            razorpay_order_id: response.razorpay_order_id,
            razorpay_signature: response.razorpay_signature,
            plan_name: planName,
            amount: amountInRupees,
            duration_unit: durationUnit,
            duration_value: durationValue
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            let timerInterval;
            Swal.fire({
                title: 'Renewal Successful!',
                html: `
                    <div style="font-size: 0.95rem; color: #334155; margin-bottom: 0.75rem;">
                        ${data.message || 'Your subscription has been renewed successfully!'}
                    </div>
                    <div style="font-size: 0.85rem; color: #64748b;">
                        Redirecting to your receipt in <b style="color: #4f46e5; font-size: 1.1rem;">3</b> seconds...
                    </div>
                `,
                icon: 'success',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: '<i class="fa-solid fa-file-invoice"></i> View Receipt Now',
                confirmButtonColor: '#4f46e5',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    const b = Swal.getHtmlContainer().querySelector('b');
                    timerInterval = setInterval(() => {
                        if (b) {
                            const remaining = Math.ceil(Swal.getTimerLeft() / 1000);
                            b.textContent = remaining > 0 ? remaining : 1;
                        }
                    }, 250);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                }
            }).then(() => {
                const targetUrl = data.receipt_url || "<?= APP_URL ?>/public/index.php/subscription_receipt";
                window.location.href = targetUrl;
            });
        } else {
            Swal.fire({
                title: 'Verification Failed',
                html: `
                    <div style="text-align: center; color: #dc2626; font-size: 0.95rem; margin-bottom: 0.5rem;">
                        <strong>${data.error || 'Payment verification could not be completed.'}</strong>
                    </div>
                    <p style="font-size: 0.8rem; color: #64748b; margin: 0;">
                        If money was deducted from your bank/card, please contact support with your Payment ID: <code>${response.razorpay_payment_id}</code>.
                    </p>
                `,
                icon: 'error',
                confirmButtonText: 'Close',
                confirmButtonColor: '#ef4444'
            });
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            title: 'Network Error',
            text: 'An error occurred during verification. Please contact support if your amount was debited.',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
    });
}
</script>
</body>
</html>
