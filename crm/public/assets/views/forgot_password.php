<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../../data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | <?= htmlspecialchars($site_name ?? 'Aikaa CRM') ?></title>
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
            margin-bottom: 1.5rem;
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
            border-radius: 0.5rem;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
            box-sizing: border-box;
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .btn {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .error-msg, .success-msg {
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8125rem;
            margin-bottom: 1.25rem;
            display: none;
            line-height: 1.45;
        }
        .error-msg {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .error-msg.not-found {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }
        .success-msg {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <a href="<?= APP_URL ?>/" class="login-logo">
            <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" style="max-width: 200px; max-height: 80px; object-fit: contain;">
            <?php else: ?>
                <?= htmlspecialchars($site_name ?? 'Aikaa CRM') ?>
            <?php endif; ?>
        </a>

        <p style="text-align: center; color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem;">Enter your email address to receive a new password.</p>

        <div id="errorBox" class="error-msg"></div>
        <div id="successBox" class="success-msg"></div>

        <form id="forgotPasswordForm">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-input" required placeholder="name@example.com">
            </div>

            <button type="submit" id="submitBtn" class="btn">Send New Password</button>
        </form>

        <a href="<?= APP_URL ?>/public/index.php/login" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>

    <script>
        const form = document.getElementById('forgotPasswordForm');
        const submitBtn = document.getElementById('submitBtn');
        const errorBox = document.getElementById('errorBox');
        const successBox = document.getElementById('successBox');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            if (!email) return;

            errorBox.style.display = 'none';
            successBox.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const response = await fetch('<?= APP_URL ?>/public/index.php/api/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });

                const result = await response.json();

                if (response.ok && result.success && result.status === 'mail_sent') {
                    successBox.innerHTML = `
                        <div style="display:flex; align-items:flex-start; gap:0.625rem;">
                            <i class="fas fa-check-circle" style="font-size:1.15rem; margin-top:2px; flex-shrink:0;"></i>
                            <div>
                                <strong style="display:block; font-size:0.875rem; margin-bottom:2px;">Mail Sent Successfully!</strong>
                                <span>${result.message}</span>
                            </div>
                        </div>
                    `;
                    successBox.style.display = 'block';
                    form.reset();
                } else if (result.status === 'not_found' || response.status === 404) {
                    errorBox.className = 'error-msg not-found';
                    errorBox.innerHTML = `
                        <div style="display:flex; align-items:flex-start; gap:0.625rem;">
                            <i class="fas fa-user-slash" style="font-size:1.15rem; margin-top:2px; flex-shrink:0;"></i>
                            <div>
                                <strong style="display:block; font-size:0.875rem; margin-bottom:2px;">Account Not Found</strong>
                                <span>${result.error || 'No registered account found with this email address.'}</span>
                            </div>
                        </div>
                    `;
                    errorBox.style.display = 'block';
                } else {
                    // Failed
                    errorBox.className = 'error-msg';
                    errorBox.innerHTML = `
                        <div style="display:flex; align-items:flex-start; gap:0.625rem;">
                            <i class="fas fa-exclamation-triangle" style="font-size:1.15rem; margin-top:2px; flex-shrink:0;"></i>
                            <div>
                                <strong style="display:block; font-size:0.875rem; margin-bottom:2px;">Delivery Failed</strong>
                                <span>${result.error || 'Failed to dispatch email. Please check your SMTP settings or try again.'}</span>
                            </div>
                        </div>
                    `;
                    errorBox.style.display = 'block';
                }
            } catch (err) {
                errorBox.className = 'error-msg';
                errorBox.innerHTML = `
                    <div style="display:flex; align-items:flex-start; gap:0.625rem;">
                        <i class="fas fa-triangle-exclamation" style="font-size:1.15rem; margin-top:2px; flex-shrink:0;"></i>
                        <div>
                            <strong style="display:block; font-size:0.875rem; margin-bottom:2px;">Connection Error</strong>
                            <span>A network error occurred. Please check your connection and try again.</span>
                        </div>
                    </div>
                `;
                errorBox.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Send New Password';
            }
        });
    </script>
</body>
</html>
