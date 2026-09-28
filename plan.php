<?php include 'includes/header.php'; ?>

<main>

<!-- ══ PAGE HERO ════════════════════════════════════════════ -->
<div class="page-hero">
    <div class="container">
        <div class="section-badge animate" style="margin-bottom:.875rem;"><i class="fa-solid fa-tag"></i> Pricing</div>
        <h1 class="animate delay-1">Simple, <span>Transparent</span> Pricing</h1>
        <p class="animate delay-2">Choose the plan that fits your team. No hidden fees, no surprises, no lock-ins. Cancel anytime.</p>
    </div>
</div>

<!-- ══ PLANS ═════════════════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <?php if (!empty($pricing_plans)): ?>
        <div class="pricing-grid">
            <?php foreach ($pricing_plans as $i => $plan): ?>
            <div class="pricing-card <?php echo !empty($plan['highlight']) ? 'featured' : ''; ?> animate delay-<?php echo ($i % 3) + 1; ?>">
                <?php if (!empty($plan['highlight'])): ?>
                    <div class="pricing-badge"><i class="fa-solid fa-star"></i> Most Popular</div>
                <?php endif; ?>
                <div class="pricing-plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                <div class="pricing-price">
                    <span class="pricing-currency">₹</span>
                    <span class="pricing-amount"><?php echo htmlspecialchars($plan['price']); ?></span>
                    <span class="pricing-duration"><?php echo htmlspecialchars($plan['duration'] ?? ''); ?></span>
                </div>
                <?php if (!empty($plan['subtitle'])): ?>
                    <p style="font-size:.8375rem;color:var(--primary);font-weight:600;margin:.375rem 0 0;"><?php echo htmlspecialchars($plan['subtitle']); ?></p>
                <?php endif; ?>
                <div class="pricing-divider"></div>
                <?php if (!empty($plan['features'])): ?>
                <ul class="pricing-features">
                    <?php foreach ($plan['features'] as $pf): ?>
                    <li><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($pf); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <a href="signup.php"
                   class="btn <?php echo !empty($plan['highlight']) ? 'btn-primary' : 'btn-outline'; ?>"
                   style="width:100%;justify-content:center;">
                    <?php echo htmlspecialchars($plan['button_text'] ?? 'Get Started'); ?>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Placeholder when no plans configured -->
        <div style="text-align:center;padding:4rem 1rem;">
            <div style="width:64px;height:64px;background:var(--primary-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:1.75rem;color:var(--primary);">
                <i class="fas fa-tag"></i>
            </div>
            <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:1.375rem;font-weight:800;color:var(--text-main);margin-bottom:.75rem;">Pricing Coming Soon</h3>
            <p style="color:var(--text-muted);max-width:420px;margin:0 auto 1.75rem;line-height:1.7;">Our plans are being configured. In the meantime, reach out and we'll find the perfect solution for your team.</p>
            <a href="contact.php" class="btn btn-primary"><i class="fas fa-envelope"></i> Contact Us for Pricing</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ══ FEATURE COMPARE STRIP ════════════════════════════════ -->
<section class="section section-alt">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-check-double"></i> All Plans Include</div>
            <h2 class="section-title">Everything You Need, Right Out of the Box</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;max-width:900px;margin:0 auto;">
            <?php
            $included = [
                ['fa-shield-halved', 'SSL & Data Security'],
                ['fa-headset',       '24/7 Support'],
                ['fa-cloud',         'Cloud Hosted'],
                ['fa-mobile-alt',    'Mobile Friendly'],
                ['fa-sync',          'Free Updates'],
                ['fa-users',         'Team Collaboration'],
                ['fa-chart-bar',     'Analytics & Reports'],
                ['fa-file-export',   'CSV Export'],
            ];
            foreach ($included as $i => [$icon, $label]): ?>
            <div class="animate delay-<?php echo ($i % 4) + 1; ?>"
                 style="display:flex;align-items:center;gap:.75rem;padding:1rem 1.125rem;background:#fff;border:1px solid var(--border);border-radius:10px;">
                <div style="width:34px;height:34px;background:var(--primary-light);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:.875rem;flex-shrink:0;">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <span style="font-size:.875rem;font-weight:600;color:var(--text-sub);"><?php echo $label; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ FAQ ════════════════════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-circle-question"></i> FAQ</div>
            <h2 class="section-title">Frequently Asked Questions</h2>
        </div>
        <div class="faq-list animate delay-1">
            <?php
            $faqs = [
                ['Can I switch plans later?',                    'Yes, you can upgrade or downgrade your plan at any time from your account settings. Changes take effect immediately.'],
                ['Is there a free trial?',                       'Absolutely. Every plan comes with a 14-day free trial — no credit card required. You can explore all features risk-free.'],
                ['How many users can I add?',                    'User limits depend on your chosen plan. All plans allow you to add your team members and assign them different roles.'],
                ['Is my data secure?',                           'Yes. We use industry-standard SSL encryption, daily backups, and strict access controls to keep your data protected at all times.'],
                ['Can I cancel anytime?',                        'Yes, there are no long-term contracts. You can cancel your subscription at any point — no questions asked.'],
                ['Do you offer custom enterprise pricing?',      'Yes. For larger teams or custom requirements, reach out to us and we\'ll put together a tailored package for your business.'],
            ];
            foreach ($faqs as $i => [$q, $a]): ?>
            <div class="faq-item">
                <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">
                    <span><?php echo htmlspecialchars($q); ?></span>
                    <i class="fas fa-chevron-right"></i>
                </div>
                <div class="faq-a"><?php echo htmlspecialchars($a); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ CTA ═══════════════════════════════════════════════════ -->
<section class="section section-alt" style="padding-bottom:5rem;">
    <div class="container">
        <div class="cta-banner animate">
            <h2>Still Have Questions?</h2>
            <p>Our team is ready to help you find the right plan. Talk to a sales expert today — no pressure, just answers.</p>
            <div class="cta-actions">
                <a href="signup.php" class="btn btn-white btn-lg"><i class="fas fa-rocket"></i> Start Free Trial</a>
                <a href="contact.php" class="btn btn-ghost btn-lg"><i class="fas fa-comments"></i> Talk to Sales</a>
            </div>
        </div>
    </div>
</section>

</main>

<?php include 'includes/footer.php'; ?>
