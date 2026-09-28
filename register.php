<?php include 'includes/header.php'; ?>

<main>
    <section class="section" style="padding-top: 10rem; min-height: 100vh; display: flex; align-items: center; background-color: var(--bg-light);">
        <div class="container">
            <div class="card" style="max-width: 500px; margin: 0 auto;">
                <h2 class="text-accent" style="text-align: center; margin-bottom: 2rem;">Employee Registration</h2>
                <p style="text-align: center; margin-bottom: 2rem;">Join your company's workspace using your provided company code.</p>
                
                <form action="#" method="POST">
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-user"></i> Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Jane Doe" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="jane@company.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-barcode"></i> Company Code</label>
                        <input type="text" name="company_code" class="form-control" placeholder="CMP-12345" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fa-solid fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        <i class="fa-solid fa-right-to-bracket"></i> Join Workspace
                    </button>
                </form>
                
                <p style="text-align: center; margin-top: 2rem; font-size: 0.9rem;">
                    Already have an account? <a href="../public/index.php/login">Login here</a>
                </p>
                <p style="text-align: center; font-size: 0.9rem;">
                    Want to register a new company? <a href="signup.php">Sign Up here</a>
                </p>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
