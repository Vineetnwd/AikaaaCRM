<?php
$showExpiredPopup = isset($subscriptionExpired) || (isset($_GET['expired']) && $_GET['expired'] == '1');
require_once __DIR__ . '/../../../../data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= htmlspecialchars($site_name ?? 'Aikaa CRM') ?></title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <?php if (!empty($favicon_url)): ?>
        <link rel="icon" href="<?= htmlspecialchars($favicon_url) ?>">
    <?php endif; ?>
    <style>
        :root {
            --primary: <?= htmlspecialchars($data['theme_color_hex'] ?? '#4f46e5') ?>;
            --primary-hover: <?= htmlspecialchars($data['theme_color_hex'] ?? '#4f46e5') ?>;
        }
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            border: 1px solid #e2e8f0;
        }
        .login-logo {
            text-align: center;
            /*margin-bottom: 2rem;*/
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-hover, #4f46e5);
            text-decoration: none;
            display: block;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
        }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-hover, #4f46e5);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .login-btn {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary-hover, #4f46e5); /* Fallback hex */
            background: var(--primary, var(--primary-hover, #4f46e5));
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 1rem;
            display: block;
        }
        .login-btn:hover {
            background: var(--primary, #4338ca);
        }
        .error-msg {
            background: #fee2e2;
            color: #b91c1c;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: none;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <a href="#" class="login-logo">
            <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" style="height: 170px; width: auto; object-fit: contain;">
            <?php else: ?>
                <i class="fas fa-rocket"></i> <?= htmlspecialchars($site_name ?? 'Aikaa CRM') ?>
            <?php endif; ?>
        </a>
        <h2 style="text-align:center; margin-bottom: 0.5rem; font-size: 1.5rem;">Welcome back</h2>
        <p style="text-align:center; color: #64748b; margin-bottom: 2rem; font-size: 0.875rem;">Enter your credentials to access your account</p>
        
        <div id="errorBox" class="error-msg" <?= isset($loginError) ? 'style="display:block"' : '' ?>>
            <?= $loginError ?? '' ?>
        </div>

        <form id="loginForm" method="POST" action="">
            <div class="form-group">
                <label class="form-label">Email or Mobile</label>
                <input type="text" name="identifier" class="form-input" placeholder="Email or Mobile" value="<?= htmlspecialchars($_POST['identifier'] ?? $_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex; justify-content:space-between;">
                    Password
                    <a href="<?= APP_URL ?>/public/index.php/forgot_password" style="font-size: 0.75rem; color: var(--primary); text-decoration: none;">Forgot Password?</a>
                </label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="login-btn">
                Log In
            </button>
        </form>

        <p style="text-align:center; margin-top: 2rem; font-size: 0.875rem; color: #64748b;">
            Don't have an account? <a href="../../../signup.php" style="color: var(--primary-hover, #4f46e5); font-weight: 600; text-decoration: none;">Sign Up</a>
        </p>
    </div>

    <?php if ($showExpiredPopup): ?>
        <!-- Subscription Expired Modal -->
        <div class="modal-overlay" style="display: flex;">
            <div class="modal-content" style="max-width: 420px; width: 90%; text-align: center; padding: 2.5rem 2rem; border-radius: 1.5rem; border: 1px solid rgba(255,255,255,0.4); box-shadow: 0 25px 50px -12px rgba(239, 68, 68, 0.25);">
                <div style="width: 72px; height: 72px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; font-size: 2rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem; letter-spacing: -0.025em;">Subscription Expired</h2>
                <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin-bottom: 2rem;">
                    Your company's CRM subscription has expired. Please contact your service provider immediately to renew your subscription and continue services.
                </p>
                <button onclick="closeExpiredModal()" class="login-btn" style="background: #ef4444 !important; margin: 0; padding: 0.75rem 1.5rem; font-weight: 700; width: 100%;">
                    Understood
                </button>
            </div>
        </div>
        <script>
            function closeExpiredModal() {
                document.querySelector('.modal-overlay').style.display = 'none';
                // Clean URL parameter
                const url = new URL(window.location);
                url.searchParams.delete('expired');
                window.history.replaceState({}, document.title, url);
            }
        </script>
    <?php endif; ?>
</body>
</html>
