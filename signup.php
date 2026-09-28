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

    /* Tab Styling matching new mockup */
    .tab-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    @media(max-width: 480px) {
        .tab-grid {
            grid-template-columns: 1fr;
        }
    }

    .card-tab {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        text-align: left;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .card-tab:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .card-tab.active {
        border-color: var(--primary);
        box-shadow: 0 0 0 1px var(--primary);
    }

    .card-tab-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        background: #eff6ff;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-bottom: 1rem;
        transition: all 0.2s;
    }

    .card-tab.active .card-tab-icon {
        background: var(--primary);
        color: #fff;
    }

    .card-tab-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-main);
        margin-bottom: 0.35rem;
    }

    .card-tab-desc {
        font-size: 0.8rem;
        color: var(--text-muted);
        line-height: 1.4;
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
                Get Started</h2>
            <p id="regSubtitle" style="color:var(--text-muted); font-size:.95rem;">Select your account type to continue
            </p>
        </div>

        <div class="tab-grid">
            <button type="button" id="tabEmployee" class="card-tab" onclick="switchRegType('employee')">
                <div class="card-tab-icon"><i class="fas fa-user-tie"></i></div>
                <div class="card-tab-title">Join Company</div>
                <div class="card-tab-desc">Register as Executive (Freelance) to join an existing organization.</div>
            </button>
            <button type="button" id="tabCompany" class="card-tab" onclick="switchRegType('company')">
                <div class="card-tab-icon"><i class="fas fa-building"></i></div>
                <div class="card-tab-title">Register Company</div>
                <div class="card-tab-desc">Create a new organization account and administrator profile.</div>
            </button>
        </div>

        <div id="formWrapper" style="display:none; animation: fadeUp 0.4s ease-out forwards;">
            <div id="errorBox"
                style="background:#fee2e2; color:#b91c1c; padding:1rem; border-radius:10px; font-size:.875rem; margin-bottom:1.5rem; display:none; border:1px solid #fecaca;">
            </div>
            <div id="successBox"
                style="background:#dcfce7; color:#15803d; padding:1rem; border-radius:10px; font-size:.875rem; margin-bottom:1.5rem; display:none; border:1px solid #bbf7d0; text-align:center; font-weight:600;">
            </div>

            <form id="registerForm" enctype="multipart/form-data">

                <!-- Join Company fields -->
                <div class="form-group" id="companySelectGroup">
                    <label class="form-label" for="companySelect">Select Company</label>
                    <select name="company_id" id="companySelect" class="form-control" required>
                        <option value="" disabled selected>Loading companies...</option>
                    </select>
                </div>

                <!-- Register Company fields -->
                <div id="newCompanyGroup" style="display:none; margin-bottom: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0 !important;">
                        <label class="form-label" for="companyNameInput">Company Name</label>
                        <input type="text" name="company_name" id="companyNameInput" class="form-control"
                            placeholder="e.g. Acme Corp">
                    </div>
                    <input type="hidden" name="subdomain" id="subdomainInput">
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
                    <div class="form-group" id="empTypeGroup">
                        <label class="form-label">Employee Type</label>
                        <select name="type" class="form-control">
                            <option value="permanent" selected>Permanent</option>
                            <option value="freelance">Freelance</option>
                        </select>
                    </div>
                    <div class="form-group" id="profilePhotoGroup">
                        <label class="form-label">Profile Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
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
    const companySelect = document.getElementById('companySelect');
    let currentType = null;

    document.getElementById('companyNameInput').addEventListener('input', function (e) {
        const subdomain = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        document.getElementById('subdomainInput').value = subdomain;
    });

    // Fetch companies on load
    async function fetchCompanies() {
        try {
            const response = await fetch('<?= APP_URL ?>/public/index.php/api/public_companies.php');
            const companies = await response.json();

            companySelect.innerHTML = '<option value="" disabled selected>-- Select Your Company --</option>';
            companies.forEach(company => {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name;
                companySelect.appendChild(option);
            });
        } catch (error) {
            console.error('Error fetching companies:', error);
            companySelect.innerHTML = '<option value="" disabled>Error loading companies</option>';
        }
    }

    fetchCompanies();

    function switchRegType(type) {
        currentType = type;

        const tabEmp = document.getElementById('tabEmployee');
        const tabComp = document.getElementById('tabCompany');
        const formWrapper = document.getElementById('formWrapper');

        // Reveal the form wrapper
        formWrapper.style.display = 'block';

        // Update active tab styling
        tabEmp.classList.remove('active');
        tabComp.classList.remove('active');

        if (type === 'employee') {
            tabEmp.classList.add('active');

            document.getElementById('companySelectGroup').style.display = 'block';
            document.getElementById('companySelect').required = true;

            document.getElementById('newCompanyGroup').style.display = 'none';
            document.getElementById('companyNameInput').required = false;
            document.getElementById('subdomainInput').required = false;

            document.getElementById('empTypeGroup').style.display = 'block';
            document.getElementById('profilePhotoGroup').style.display = 'block';

            document.getElementById('regTitle').textContent = 'Get Started';
            document.getElementById('regSubtitle').textContent = 'Create your company account in seconds';
        } else {
            tabComp.classList.add('active');

            document.getElementById('companySelectGroup').style.display = 'none';
            document.getElementById('companySelect').required = false;

            document.getElementById('newCompanyGroup').style.display = 'block';
            document.getElementById('companyNameInput').required = true;
            document.getElementById('subdomainInput').required = true;

            document.getElementById('empTypeGroup').style.display = 'none';
            document.getElementById('profilePhotoGroup').style.display = 'none';

            document.getElementById('regTitle').textContent = 'Register Company';
            document.getElementById('regSubtitle').textContent = 'Register a new company and administrator account';
        }
    }

    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        errorBox.style.display = 'none';
        successBox.style.display = 'none';
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';

        const formData = new FormData(registerForm);
        const apiEndpoint = currentType === 'employee'
            ? '<?= APP_URL ?>/public/index.php/api/register.php'
            : '<?= APP_URL ?>/public/index.php/api/register_company.php';

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