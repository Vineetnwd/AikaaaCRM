<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Aikaa CRM</title>
    <link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: var(--primary-hover, #4f46e5);
            --primary-hover: var(--primary, #4338ca);
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            background: var(--bg-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
            padding: 2rem 1rem;
        }

        .register-card {
            width: 100%;
            max-width: 500px;
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            border: 1px solid var(--border);
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-logo {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
        }

        .register-title {
            text-align: center;
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .register-subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.2s;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .register-btn {
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .register-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .error-msg {
            background: #fee2e2;
            color: #b91c1c;
            padding: 1rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: none;
            border: 1px solid #fecaca;
        }

        .success-msg {
            background: #dcfce7;
            color: #15803d;
            padding: 1rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: none;
            border: 1px solid #bbf7d0;
            text-align: center;
        }

        .footer-text {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .footer-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        /* Modern registration tab switcher */
        .register-tabs {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .register-tab {
            flex: 1;
            padding: 0.625rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s;
            border: none;
            background: transparent;
        }

        .register-tab.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);
        }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="logo-section">
            <a href="#" class="register-logo">
                <i class="fas fa-rocket"></i> Aikaa CRM
            </a>
        </div>
        
        <h2 class="register-title">Get Started</h2>
        <p class="register-subtitle">Create your company account in seconds</p>

        <div class="register-tabs">
            <button type="button" class="register-tab active" onclick="switchRegType('employee')">Join Company</button>
            <button type="button" class="register-tab" onclick="switchRegType('company')">Register Company</button>
        </div>
        
        <div id="errorBox" class="error-msg"></div>
        <div id="successBox" class="success-msg"></div>

        <form id="registerForm" enctype="multipart/form-data">
            <!-- Join Company fields (Select existing company) -->
            <div id="companySelectGroup" class="form-group full-width">
                <label class="form-label">Select Company</label>
                <select name="company_id" id="companySelect" class="form-input" required>
                    <option value="" disabled selected>Loading companies...</option>
                </select>
            </div>

            <!-- Register Company fields (Create new company) -->
            <div id="newCompanyGroup" class="form-grid" style="display: none; grid-column: span 2; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" id="companyNameInput" class="form-input" placeholder="e.g. Acme Corp">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Subdomain Prefix</label>
                    <input type="text" name="subdomain" id="subdomainInput" class="form-input" placeholder="e.g. acme" pattern="[a-zA-Z0-9-]+" title="Only alphanumeric characters and hyphens allowed">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Your Name</label>
                    <input type="text" name="admin_name" class="form-input" placeholder="John Doe" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="admin_email" class="form-input" placeholder="john@company.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mobile Number</label>
                    <input type="tel" name="mobile" class="form-input" placeholder="9876543210" required>
                </div>
                <div class="form-group" id="empTypeGroup">
                    <label class="form-label">Employee Type</label>
                    <select name="type" class="form-input">
                        <option value="permanent" selected>Permanent</option>
                        <option value="freelance">Freelance</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Profile Photo</label>
                    <input type="file" name="photo" class="form-input" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="admin_password" class="form-input" placeholder="••••••••" required minlength="6">
                </div>
            </div>

            <button type="submit" id="submitBtn" class="register-btn">
                <span>Register Now</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <p class="footer-text">
            Already have an account? <a href="<?= APP_URL ?>/public/index.php/login" class="footer-link">Log In</a>
        </p>
    </div>

    <script>
        const registerForm = document.getElementById('registerForm');
        const errorBox = document.getElementById('errorBox');
        const successBox = document.getElementById('successBox');
        const submitBtn = document.getElementById('submitBtn');
        const companySelect = document.getElementById('companySelect');
        let currentType = 'employee';

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
            
            // Toggle active class on tabs
            document.querySelectorAll('.register-tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            // Reset error/success boxes
            errorBox.style.display = 'none';
            successBox.style.display = 'none';

            if (type === 'employee') {
                document.getElementById('companySelectGroup').style.display = 'block';
                document.getElementById('companySelect').required = true;
                
                document.getElementById('newCompanyGroup').style.display = 'none';
                document.getElementById('companyNameInput').required = false;
                document.getElementById('subdomainInput').required = false;

                document.getElementById('empTypeGroup').style.display = 'block';
                
                document.querySelector('.register-subtitle').textContent = 'Create your company account in seconds';
            } else {
                document.getElementById('companySelectGroup').style.display = 'none';
                document.getElementById('companySelect').required = false;
                
                document.getElementById('newCompanyGroup').style.display = 'grid';
                document.getElementById('companyNameInput').required = true;
                document.getElementById('subdomainInput').required = true;

                document.getElementById('empTypeGroup').style.display = 'none';
                
                document.querySelector('.register-subtitle').textContent = 'Register a new company and administrator account';
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
                    submitBtn.innerHTML = '<span>Register Now</span> <i class="fas fa-arrow-right"></i>';
                }
            } catch (error) {
                errorBox.textContent = 'Network error. Please try again.';
                errorBox.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>Register Now</span> <i class="fas fa-arrow-right"></i>';
            }
        });
    </script>
</body>
</html>
