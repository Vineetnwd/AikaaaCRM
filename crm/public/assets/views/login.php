<?php
$showExpiredPopup = isset($subscriptionExpired) || (isset($_GET['expired']) && $_GET['expired'] == '1');
$page_title = 'Login';
include __DIR__ . '/../../../../includes/header.php';
?>

<style>
    /* Login Page Styling */
    .login-wrapper {
        min-height: calc(100vh - var(--nav-h, 80px));
        display: flex;
        align-items: center;
        justify-content: center;
        padding: calc(var(--nav-h, 80px) + 2.5rem) 1rem 3.5rem;
        background: var(--bg-gradient, linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%));
    }
    .login-card {
        width: 100%;
        max-width: 440px;
        background: #ffffff;
        padding: 2.5rem 2.25rem;
        border-radius: 1.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
    }
    .login-card-logo {
        text-align: center;
        margin-bottom: 1.5rem;
        display: block;
        color: var(--primary, #4f46e5);
    }
    .form-group {
        margin-bottom: 1.25rem;
    }
    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: #334155;
    }
    .form-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.75rem;
        font-size: 0.95rem;
        color: #0f172a;
        background: #fff;
        transition: all 0.2s;
        outline: none;
        font-family: inherit;
    }
    .form-input:focus {
        border-color: var(--primary, #4f46e5);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
    }
    .login-btn {
        width: 100%;
        padding: 0.85rem;
        background: var(--primary, #4f46e5);
        color: white;
        border: none;
        border-radius: 0.75rem;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.2s;
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }
    .login-btn:hover {
        background: var(--primary-dark, #4338ca);
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35);
    }
    .error-msg {
        background: #fee2e2;
        color: #b91c1c;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        font-size: 0.875rem;
        margin-bottom: 1.25rem;
        border: 1px solid #fecaca;
        text-align: center;
        font-weight: 500;
    }
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 1rem;
    }
    .modal-content {
        background: #fff;
        max-width: 440px;
        width: 100%;
        text-align: center;
        padding: 2.5rem 2rem;
        border-radius: 1.5rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }
</style>

<main class="login-wrapper">
    <div class="login-card animate delay-1">
        <a href="<?= web_url('index.php') ?>" class="login-card-logo">
            <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" style="height: 52px; width: auto; object-fit: contain; margin: 0 auto; display: block;">
            <?php else: ?>
                <span style="font-size: 1.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary, #4f46e5);">
                    <i class="fas fa-rocket"></i> <?= htmlspecialchars($site_name ?? 'Aikaaa CRM') ?>
                </span>
            <?php endif; ?>
        </a>
        <h2 style="text-align:center; margin-bottom: 0.35rem; font-size: 1.5rem; font-weight: 800; color: var(--text-main, #0f172a);">Welcome back</h2>
        <p style="text-align:center; color: #64748b; margin-bottom: 1.75rem; font-size: 0.875rem;">Enter your credentials to access your account</p>
        
        <div id="errorBox" class="error-msg" <?= isset($loginError) ? 'style="display:block"' : 'style="display:none"' ?>>
            <?= htmlspecialchars($loginError ?? '') ?>
        </div>

        <form id="loginForm" method="POST" action="">
            <div class="form-group">
                <label class="form-label">Email or Mobile</label>
                <input type="text" name="identifier" class="form-input" placeholder="e.g. name@company.com or 9876543210" value="<?= htmlspecialchars($_POST['identifier'] ?? $_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Password</span>
                    <a href="<?= APP_URL ?>/public/index.php/forgot_password" style="font-size: 0.75rem; color: var(--primary, #4f46e5); text-decoration: none; font-weight: 600;">Forgot Password?</a>
                </label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="login-btn">
                <span>Log In</span> <i class="fas fa-arrow-right" style="font-size: 0.85rem;"></i>
            </button>
        </form>

        <p style="text-align:center; margin-top: 1.75rem; font-size: 0.875rem; color: #64748b;">
            Don't have an account? <a href="<?= web_url('signup.php') ?>" style="color: var(--primary, #4f46e5); font-weight: 700; text-decoration: none;">Register Now</a>
        </p>
    </div>
</main>

<?php if ($showExpiredPopup): ?>
    <!-- Subscription Expired Modal -->
    <div class="modal-overlay" style="display: flex;">
        <div class="modal-content">
            <div style="width: 72px; height: 72px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; font-size: 2rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem; letter-spacing: -0.025em;">Subscription Expired</h2>
            <p style="font-size: 0.875rem; color: #64748b; line-height: 1.6; margin-bottom: 2rem;">
                Your company's CRM subscription has expired. Please contact your administrator or renew your subscription to restore full access.
            </p>
            <button onclick="closeExpiredModal()" class="login-btn" style="background: #ef4444 !important; margin: 0; padding: 0.75rem 1.5rem; font-weight: 700; width: 100%;">
                Understood
            </button>
        </div>
    </div>
    <script>
        function closeExpiredModal() {
            document.querySelector('.modal-overlay').style.display = 'none';
            const url = new URL(window.location);
            url.searchParams.delete('expired');
            window.history.replaceState({}, document.title, url);
        }
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../../../includes/footer.php'; ?>

