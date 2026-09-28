<?php
require_once __DIR__ . '/crm/config/config.php';
require_once __DIR__ . '/crm/core/Database.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = \Core\Database::getInstance()->getConnection();

        // Auto-create table if it doesn't exist
        $db->exec("CREATE TABLE IF NOT EXISTS `contact_enquiries` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `first_name` varchar(100) NOT NULL,
            `last_name` varchar(100) NOT NULL,
            `email` varchar(255) NOT NULL,
            `mobile` varchar(20) DEFAULT NULL,
            `subject` varchar(255) DEFAULT NULL,
            `message` text NOT NULL,
            `status` enum('new','read') DEFAULT 'new',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        try {
            $db->exec("ALTER TABLE `contact_enquiries` ADD COLUMN `mobile` varchar(20) DEFAULT NULL AFTER `email`");
        } catch (\Exception $e) {
            // Ignore if column already exists
        }

        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($first_name && $mobile) {
            $stmt = $db->prepare("INSERT INTO contact_enquiries (first_name, last_name, email, mobile, subject, message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $mobile, $subject, $message]);
            $success = true;
        } else {
            $error = 'Please provide your first name and mobile number.';
        }
    } catch (Exception $e) {
        $error = 'Failed to submit enquiry. Please try again later.';
    }
}

include 'includes/header.php';
?>
<main>

    <!-- ══ PAGE HERO ════════════════════════════════════════════ -->
    <div class="page-hero">
        <div class="container">
            <div class="section-badge animate" style="margin-bottom:.875rem;"><i class="fa-solid fa-envelope"></i>
                Contact</div>
            <h1 class="animate delay-1">Get In <span>Touch</span></h1>
            <p class="animate delay-2">Have questions about <?php echo htmlspecialchars($site_name); ?>? Need a demo?
                Our team is here and ready to help.</p>
        </div>
    </div>

    <!-- ══ CONTACT GRID ══════════════════════════════════════════ -->
    <section class="section">
        <div class="container">
            <div class="contact-grid">

                <!-- Left: Info Card -->
                <div class="animate delay-1">
                    <div class="contact-info-card">
                        <div class="contact-info-header">
                            <div
                                style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.6);margin-bottom:.5rem;">
                                Contact Information</div>
                            <h3>We'd Love to Hear From You</h3>
                            <p>Reach out through any channel below and we'll respond within one business day.</p>
                        </div>
                        <div class="contact-info-body">
                            <?php if ($contact_email): ?>
                                <div class="contact-row">
                                    <div class="contact-row-icon"><i class="fas fa-envelope"></i></div>
                                    <div>
                                        <div class="contact-row-label">Email</div>
                                        <div class="contact-row-value"><a
                                                href="mailto:<?php echo htmlspecialchars($contact_email); ?>"><?php echo htmlspecialchars($contact_email); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_phone): ?>
                                <div class="contact-row">
                                    <div class="contact-row-icon green"><i class="fas fa-phone"></i></div>
                                    <div>
                                        <div class="contact-row-label">Phone</div>
                                        <div class="contact-row-value"><a
                                                href="tel:<?php echo preg_replace('/\s+/', '', $contact_phone); ?>"><?php echo htmlspecialchars($contact_phone); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_whatsapp): ?>
                                <div class="contact-row">
                                    <div class="contact-row-icon" style="background:#d1fae5;color:#059669;"><i
                                            class="fab fa-whatsapp"></i></div>
                                    <div>
                                        <div class="contact-row-label">WhatsApp</div>
                                        <div class="contact-row-value">
                                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $contact_whatsapp); ?>"
                                                target="_blank" rel="noopener">
                                                <?php echo htmlspecialchars($contact_whatsapp); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_address): ?>
                                <div class="contact-row">
                                    <div class="contact-row-icon amber"><i class="fas fa-map-marker-alt"></i></div>
                                    <div>
                                        <div class="contact-row-label">Office</div>
                                        <div class="contact-row-value">
                                            <?php echo nl2br(htmlspecialchars($contact_address)); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($contact_hours): ?>
                                <div class="contact-row">
                                    <div class="contact-row-icon" style="background:#fdf4ff;color:#9333ea;"><i
                                            class="fas fa-clock"></i></div>
                                    <div>
                                        <div class="contact-row-label">Hours</div>
                                        <div class="contact-row-value"><?php echo htmlspecialchars($contact_hours); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!$contact_email && !$contact_phone && !$contact_whatsapp && !$contact_address && !$contact_hours): ?>
                                <div style="text-align:center;padding:1.5rem;color:var(--text-muted);font-size:.875rem;">
                                    <i class="fas fa-info-circle"
                                        style="font-size:1.5rem;margin-bottom:.75rem;display:block;opacity:.4;"></i>
                                    Contact details will appear once configured in Website Settings.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Social Icons -->
                        <?php if ($social_facebook || $social_twitter || $social_linkedin || $social_instagram): ?>
                            <div class="contact-social">
                                <?php if ($social_facebook): ?>
                                    <a class="social-btn" href="<?php echo htmlspecialchars($social_facebook); ?>"
                                        target="_blank" rel="noopener" title="Facebook"
                                        style="color:#1877f2;border-color:#bfdbfe;"
                                        onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='#fff'">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($social_twitter): ?>
                                    <a class="social-btn" href="<?php echo htmlspecialchars($social_twitter); ?>"
                                        target="_blank" rel="noopener" title="Twitter"
                                        style="color:#1da1f2;border-color:#bae6fd;"
                                        onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='#fff'">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($social_linkedin): ?>
                                    <a class="social-btn" href="<?php echo htmlspecialchars($social_linkedin); ?>"
                                        target="_blank" rel="noopener" title="LinkedIn"
                                        style="color:#0a66c2;border-color:#bfdbfe;"
                                        onmouseover="this.style.background='#eff6ff'" onmouseout="this.style.background='#fff'">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($social_instagram): ?>
                                    <a class="social-btn" href="<?php echo htmlspecialchars($social_instagram); ?>"
                                        target="_blank" rel="noopener" title="Instagram"
                                        style="color:#e1306c;border-color:#fce7f3;"
                                        onmouseover="this.style.background='#fdf2f8'" onmouseout="this.style.background='#fff'">
                                        <i class="fab fa-instagram"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Form -->
                <div class="animate delay-2">
                    <div class="contact-form-card">
                        <h3><i class="fas fa-paper-plane"
                                style="color:var(--primary);margin-right:.5rem;font-size:1rem;"></i> Send Us a Message
                        </h3>

                        <?php if ($success): ?>
                            <div
                                style="background:#d1fae5; color:#065f46; padding:1rem; border-radius:8px; margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                                <i class="fas fa-check-circle"></i> Your message has been sent successfully! We will get
                                back to you shortly.
                            </div>
                        <?php elseif ($error): ?>
                            <div
                                style="background:#fee2e2; color:#991b1b; padding:1rem; border-radius:8px; margin-bottom:1.5rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form action="contact.php" method="POST" id="contactForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="fname">First Name</label>
                                    <input type="text" class="form-control" id="fname" name="first_name"
                                        placeholder="John" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="lname">Last Name (Optional)</label>
                                    <input type="text" class="form-control" id="lname" name="last_name"
                                        placeholder="Doe">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="email">Email Address (Optional)</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                        placeholder="john@company.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="mobile">Mobile Number</label>
                                    <input type="tel" class="form-control" id="mobile" name="mobile"
                                        placeholder="9876543210" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="subject">Subject</label>
                                <select class="form-control" id="subject" name="subject">
                                    <option value="">Select a topic…</option>
                                    <option value="demo">Request a Demo</option>
                                    <option value="pricing">Pricing Inquiry</option>
                                    <option value="support">Technical Support</option>
                                    <option value="partnership">Partnership</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="message">Message (Optional)</label>
                                <textarea class="form-control" id="message" name="message" rows="5"
                                    placeholder="Tell us how we can help you…"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width:100%;justify-content:center;padding:.875rem;">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ QUICK ACTIONS ════════════════════════════════════════ -->
    <section class="section section-alt">
        <div class="container">
            <div class="section-header animate" style="margin-bottom:2.5rem;">
                <h2 class="section-title">Or Jump Right In</h2>
                <p class="section-subtitle">No need to wait — get started in minutes.</p>
            </div>
            <div
                style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem;max-width:860px;margin:0 auto;">
                <a href="signup.php" style="text-decoration:none;">
                    <div class="feature-card animate delay-1" style="text-align:center;cursor:pointer;">
                        <div class="feature-icon" style="margin:0 auto 1rem;"><i class="fas fa-rocket"></i></div>
                        <h3>Start Free Trial</h3>
                        <p>Set up your company and start tracking leads in under 2 minutes.</p>
                    </div>
                </a>
                <a href="../public/index.php/login" style="text-decoration:none;">
                    <div class="feature-card animate delay-2" style="text-align:center;cursor:pointer;">
                        <div class="feature-icon" style="margin:0 auto 1rem;background:#d1fae5;color:#059669;"><i
                                class="fas fa-sign-in-alt"></i></div>
                        <h3>Login to Dashboard</h3>
                        <p>Already a member? Jump straight into your CRM dashboard.</p>
                    </div>
                </a>
                <a href="plan.php" style="text-decoration:none;">
                    <div class="feature-card animate delay-3" style="text-align:center;cursor:pointer;">
                        <div class="feature-icon" style="margin:0 auto 1rem;background:#fef3c7;color:#d97706;"><i
                                class="fas fa-tag"></i></div>
                        <h3>View Pricing</h3>
                        <p>Compare plans and pick the one that's right for your team size.</p>
                    </div>
                </a>
            </div>
        </div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>