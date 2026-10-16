<?php include 'includes/header.php'; ?>

<style>
    /* Compact Form Overrides */
    .contact-form-card .form-group {
        margin-bottom: 0.75rem !important;
    }

    .contact-form-card .form-label {
        margin-bottom: 0.25rem !important;
        font-size: 0.8rem !important;
    }

    .contact-form-card .form-control {
        padding: 0.6rem 0.875rem !important;
        font-size: 0.9rem !important;
    }

    .contact-form-card .form-row {
        margin-bottom: 0 !important;
        gap: 0.75rem !important;
    }


</style>

<main
    style="background:var(--bg-gradient); min-height:calc(100vh - var(--nav-h)); display:flex; align-items:center; justify-content:center; padding:2rem 1rem;">
    <div class="contact-form-card animate delay-1" style="width:100%; max-width:600px; margin:0 auto; padding:2rem;">

        <div style="text-align:center; margin-bottom:1.5rem;">
            <?php if (!empty($logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo"
                    style="height:48px; width:auto; object-fit:contain; margin:0 auto 1rem; display:block;">
            <?php else: ?>
                <div
                    style="display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; background:var(--primary-light); color:var(--primary); border-radius:50%; font-size:1.5rem; margin-bottom:0.75rem;">
                    <?php if (!empty($favicon_url)): ?>
                        <img src="<?= htmlspecialchars($favicon_url) ?>"
                            style="width:28px; height:28px; object-fit:contain; border-radius:4px;">
                    <?php else: ?>
                        <i class="fas fa-rocket"></i>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <h2 id="regTitle" style="font-size:1.5rem; font-weight:800; color:var(--text-main); margin-bottom:.25rem;">
                Register Company</h2>
            <p id="regSubtitle" style="color:var(--text-muted); font-size:.95rem;">Register a new company and administrator account
            </p>
        </div>

        <div id="formWrapper" style="display:block; animation: fadeUp 0.4s ease-out forwards;">
            <div id="errorBox"
                style="background:#fee2e2; color:#b91c1c; padding:1rem; border-radius:10px; font-size:.875rem; margin-bottom:1.5rem; display:none; border:1px solid #fecaca;">
            </div>
            <div id="successBox"
                style="background:#dcfce7; color:#15803d; padding:1rem; border-radius:10px; font-size:.875rem; margin-bottom:1.5rem; display:none; border:1px solid #bbf7d0; text-align:center; font-weight:600;">
            </div>

            <form id="registerForm" enctype="multipart/form-data">

                <!-- Register Company fields -->
                <div id="newCompanyGroup" style="margin-bottom: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0 !important;">
                        <label class="form-label" for="companyNameInput">Company Name</label>
                        <input type="text" name="company_name" id="companyNameInput" class="form-control"
                            placeholder="e.g. Acme Corp" required>
                    </div>
                    <input type="hidden" name="subdomain" id="subdomainInput" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="admin_name" class="form-control" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="admin_email" class="form-control" placeholder="john@company.com"
                            required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Mobile Number</label>
                        <input type="tel" name="mobile" class="form-control" placeholder="9876543210" required>
                    </div>


                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary"
                    style="width:100%; justify-content:center; padding:.875rem; margin-top:1rem; font-size:1rem;">
                    Register Now <i class="fas fa-arrow-right" style="margin-left:.5rem;"></i>
                </button>
            </form>
        </div>

        <div style="text-align:center; margin-top:2rem; font-size:.9rem; color:var(--text-muted);">
            Already have an account? <a href="<?= APP_URL ?>/public/index.php/login"
                style="color:var(--primary); font-weight:600; text-decoration:none;">Log In</a>
        </div>

    </div>
</main>

<script>
    const registerForm = document.getElementById('registerForm');
    const errorBox = document.getElementById('errorBox');
    const successBox = document.getElementById('successBox');
    const submitBtn = document.getElementById('submitBtn');

    document.getElementById('companyNameInput').addEventListener('input', function (e) {
        const subdomain = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        document.getElementById('subdomainInput').value = subdomain;
    });



    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        errorBox.style.display = 'none';
        successBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';

        const formData = new FormData(registerForm);
        const apiEndpoint = '<?= APP_URL ?>/public/index.php/api/register_company.php';

        try {
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok) {
                successBox.textContent = result.message;
                successBox.style.display = 'block';
                registerForm.style.display = 'none';

                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = '<?= APP_URL ?>/public/index.php/login';
                }, 2000);
            } else {
                errorBox.textContent = result.error || 'Something went wrong';
                errorBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Register Now <i class="fas fa-arrow-right" style="margin-left:.5rem;"></i>';
            }
        } catch (error) {
            errorBox.textContent = 'Network error. Please try again.';
            errorBox.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Register Now <i class="fas fa-arrow-right" style="margin-left:.5rem;"></i>';
        }
    });
</script>

<?php include 'includes/footer.php'; ?>