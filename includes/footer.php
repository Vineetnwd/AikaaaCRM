<footer class="footer">
    <div class="container">
        <div class="footer-grid">

            <!-- Brand Column -->
            <div>
                <div class="footer-brand">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>"
                             alt="<?php echo htmlspecialchars($site_name); ?>"
                             style="height:70px;">
                    <?php else: ?>
                        <i class="fa-solid fa-layer-group" style="color:#60a5fa;"></i>
                        <span><?php echo htmlspecialchars($site_name); ?></span>
                    <?php endif; ?>
                </div>
                <p class="footer-desc">
                    AikoCRM is your all-in-one business growth platform—managing leads, sales, teams, and operations from a single dashboard.
                </p>
                <!-- Social Icons -->
                <div class="footer-social">
                    <?php if ($social_twitter): ?>
                    <a href="<?php echo htmlspecialchars($social_twitter); ?>" target="_blank" rel="noopener" title="Twitter"
                       style="color:#1da1f2;" onmouseover="this.style.background='rgba(29,161,242,.15)'" onmouseout="this.style.background='rgba(255,255,255,.08)'">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($social_linkedin): ?>
                    <a href="<?php echo htmlspecialchars($social_linkedin); ?>" target="_blank" rel="noopener" title="LinkedIn"
                       style="color:#0a66c2;" onmouseover="this.style.background='rgba(10,102,194,.15)'" onmouseout="this.style.background='rgba(255,255,255,.08)'">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($social_facebook): ?>
                    <a href="<?php echo htmlspecialchars($social_facebook); ?>" target="_blank" rel="noopener" title="Facebook"
                       style="color:#1877f2;" onmouseover="this.style.background='rgba(24,119,242,.15)'" onmouseout="this.style.background='rgba(255,255,255,.08)'">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($social_instagram): ?>
                    <a href="<?php echo htmlspecialchars($social_instagram); ?>" target="_blank" rel="noopener" title="Instagram"
                       style="color:#e1306c;" onmouseover="this.style.background='rgba(225,48,108,.15)'" onmouseout="this.style.background='rgba(255,255,255,.08)'">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (!$social_twitter && !$social_linkedin && !$social_facebook && !$social_instagram): ?>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Links -->
            <div class="footer-col">
                <h4>Product</h4>
                <ul>
                    <?php if (!empty($nav_links)): ?>
                        <?php foreach ($nav_links as $link): ?>
                            <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['label']); ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="plan.php">Pricing</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Support Links -->
            <div class="footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="documentation.php">Help Center</a></li>
                    <li><a href="documentation.php">Documentation</a></li>
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    <li><a href="terms-of-service.php">Terms of Service</a></li>
                </ul>
            </div>

            <!-- Account + Contact -->
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <li><a href="signup.php">Register Company</a></li>
                    <li><a href="/crm/">Login to Dashboard</a></li>
                </ul>

                <?php if ($contact_email || $contact_phone || $contact_whatsapp || $contact_address || $contact_hours): ?>
                <div class="footer-contact-block">
                    <?php if ($contact_email): ?>
                    <a class="footer-contact-item" href="mailto:<?php echo htmlspecialchars($contact_email); ?>">
                        <i class="fas fa-envelope"></i><?php echo htmlspecialchars($contact_email); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($contact_phone): ?>
                    <a class="footer-contact-item" href="tel:<?php echo preg_replace('/\s+/','',$contact_phone); ?>">
                        <i class="fas fa-phone"></i><?php echo htmlspecialchars($contact_phone); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($contact_whatsapp): ?>
                    <a class="footer-contact-item" href="https://wa.me/<?php echo preg_replace('/\D/','',$contact_whatsapp); ?>" target="_blank" rel="noopener">
                        <i class="fab fa-whatsapp" style="color:#25d366;"></i>WhatsApp
                    </a>
                    <?php endif; ?>
                    <?php if ($contact_hours): ?>
                    <span class="footer-contact-item">
                        <i class="fas fa-clock"></i><?php echo htmlspecialchars($contact_hours); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</span>
            <span>Built for growing businesses.</span>
        </div>
    </div>
</footer>


</body>
</html>
