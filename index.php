<?php include 'includes/header.php'; ?>
<main>
<!-- ══ HERO ══════════════════════════════════════════════════ -->
<section class="hero">
    <div class="container">
        <div class="hero-inner">
            <!-- Left -->
            <div>
                <div class="hero-badge animate">
                    <i class="fa-solid fa-bolt"></i>
                    <?php echo htmlspecialchars($hero_badge ?: 'Trusted by 500+ Businesses'); ?>
                </div>
                <h1 class="animate delay-1">
                    <?php if ($hero_title): ?>
                        <?php echo nl2br(htmlspecialchars($hero_title)); ?>
                    <?php else: ?>
                        Manage Customers.<br><span>Close More Deals.</span>
                    <?php endif; ?>
                </h1>
                <p class="hero-desc animate delay-2">
                    <?php echo htmlspecialchars($hero_subtext
                        ?: $site_name . ' is the all-in-one CRM built for modern sales teams — track leads, automate follow-ups, and grow revenue, all in one place.'); ?>
                </p>
                <div class="hero-actions animate delay-3">
                    <a href="signup.php" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-rocket"></i>
                        <?php echo htmlspecialchars($hero_cta_text ?: 'Start Free Trial'); ?>
                    </a>
                    <a href="/crm/" class="btn btn-outline btn-lg">
                        <i class="fa-solid fa-right-to-bracket"></i> Login
                    </a>
                </div>
                <div class="hero-stats animate delay-4">
                    <div>
                        <div class="hero-stat-val">500+</div>
                        <div class="hero-stat-lbl">Companies</div>
                    </div>
                    <div>
                        <div class="hero-stat-val">98%</div>
                        <div class="hero-stat-lbl">Satisfaction</div>
                    </div>
                    <div>
                        <div class="hero-stat-val">3x</div>
                        <div class="hero-stat-lbl">Revenue Growth</div>
                    </div>
                </div>
            </div>

            <!-- Right — Dashboard Preview Card -->
            <div class="hero-visual animate delay-2">
                <div class="hero-card">
                    <div class="hero-card-header">
                        <div class="hero-card-avatar"><i class="fa-solid fa-chart-line"></i></div>
                        <div>
                            <div class="hero-card-title">Sales Dashboard</div>
                            <div class="hero-card-sub">Live performance overview</div>
                        </div>
                        <span style="margin-left:auto;font-size:.7rem;background:#d1fae5;color:#059669;padding:.25rem .625rem;border-radius:6px;font-weight:700;">LIVE</span>
                    </div>
                    <div class="hero-metric">
                        <div class="hero-metric-label">Monthly Revenue</div>
                        <div><span class="hero-metric-val">₹4,82,000</span><span class="hero-metric-up"><i class="fa-solid fa-arrow-trend-up"></i> +24%</span></div>
                        <div class="hero-bar"><div class="hero-bar-fill" style="width:78%;"></div></div>
                    </div>
                    <div class="hero-metric">
                        <div class="hero-metric-label">Leads Converted</div>
                        <div><span class="hero-metric-val">142</span><span class="hero-metric-up"><i class="fa-solid fa-arrow-trend-up"></i> +18%</span></div>
                        <div class="hero-bar"><div class="hero-bar-fill" style="width:62%;background:#06b6d4;"></div></div>
                    </div>
                    <div class="hero-mini-grid">
                        <div class="hero-mini-stat" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                            <div class="val" style="color:#16a34a;">38</div>
                            <div class="lbl" style="color:#15803d;">New Today</div>
                        </div>
                        <div class="hero-mini-stat" style="background:#eff6ff;border:1px solid #bfdbfe;">
                            <div class="val" style="color:#2563eb;">214</div>
                            <div class="lbl" style="color:#1d4ed8;">Pipeline</div>
                        </div>
                        <div class="hero-mini-stat" style="background:#fdf4ff;border:1px solid #e9d5ff;">
                            <div class="val" style="color:#9333ea;">91%</div>
                            <div class="lbl" style="color:#7c3aed;">Retention</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ TRUST STRIP ══════════════════════════════════════════ -->
<div class="trust-strip">
    <div class="container">
        <div class="trust-strip-label">Trusted by fast-growing teams across industries</div>
        <div class="trust-logos">
            <div class="trust-logo"><i class="fa-solid fa-building"></i> TechCorp</div>
            <div class="trust-logo"><i class="fa-solid fa-store"></i> RetailHub</div>
            <div class="trust-logo"><i class="fa-solid fa-hospital"></i> MedGroup</div>
            <div class="trust-logo"><i class="fa-solid fa-graduation-cap"></i> EduSphere</div>
            <div class="trust-logo"><i class="fa-solid fa-chart-pie"></i> FinEdge</div>
            <div class="trust-logo"><i class="fa-solid fa-industry"></i> ManufCo</div>
        </div>
    </div>
</div>

<!-- ══ FEATURES ═════════════════════════════════════════════ -->
<section class="section section-alt">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-star"></i> Core Features</div>
            <h2 class="section-title">
                <?php echo !empty($features) ? 'Why Choose ' . htmlspecialchars($site_name) . '?' : 'Manage Your Whole Business in One Software'; ?>
            </h2>
            <p class="section-subtitle">360 Degree Management of Your Business — from first contact to closed deal.</p>
        </div>
        <div class="features-grid">
            <?php if (!empty($features)): ?>
                <?php foreach ($features as $i => $f): ?>
                <div class="feature-card animate delay-<?php echo ($i % 4) + 1; ?>">
                    <div class="feature-icon"><i class="<?php echo htmlspecialchars($f['icon']); ?>"></i></div>
                    <h3><?php echo htmlspecialchars($f['title']); ?></h3>
                    <p><?php echo htmlspecialchars($f['description']); ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="feature-card animate delay-1">
                    <div class="feature-icon"><i class="fa-solid fa-users"></i></div>
                    <h3>Lead Management</h3>
                    <p>Capture, assign, and track every lead through your pipeline with complete visibility and zero data loss.</p>
                </div>
                <div class="feature-card animate delay-2">
                    <div class="feature-icon"><i class="fa-solid fa-bell"></i></div>
                    <h3>Smart Follow-ups</h3>
                    <p>Never miss a follow-up. Set reminders, log notes, and get notified at exactly the right time.</p>
                </div>
                <div class="feature-card animate delay-3">
                    <div class="feature-icon"><i class="fa-solid fa-chart-bar"></i></div>
                    <h3>Analytics & Reports</h3>
                    <p>Deep-dive into team performance with beautiful, actionable dashboards and detailed reports.</p>
                </div>
                <div class="feature-card animate delay-4">
                    <div class="feature-icon"><i class="fa-solid fa-building-user"></i></div>
                    <h3>Multi-Company</h3>
                    <p>Manage multiple organizations under one roof with isolated data, roles, and permissions.</p>
                </div>
                <div class="feature-card animate delay-1">
                    <div class="feature-icon"><i class="fa-solid fa-file-invoice"></i></div>
                    <h3>Invoicing & Quotations</h3>
                    <p>Generate professional invoices and quotations directly from the CRM, saving hours each week.</p>
                </div>
                <div class="feature-card animate delay-2">
                    <div class="feature-icon"><i class="fa-solid fa-clock"></i></div>
                    <h3>Attendance & HRMS</h3>
                    <p>Track employee attendance, leaves, and performance — fully integrated in one platform.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══ HOW IT WORKS ══════════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-map"></i> How It Works</div>
            <h2 class="section-title">Up and Running in Minutes</h2>
            <p class="section-subtitle">No technical knowledge required — just sign up and start closing deals.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card animate delay-1">
                <div class="step-number">1</div>
                <h3>Register Your Company</h3>
                <p>Sign up your organization in under 2 minutes. Set up your team, assign roles, and configure your pipeline.</p>
            </div>
            <div class="step-card animate delay-2">
                <div class="step-number">2</div>
                <h3>Import Your Leads</h3>
                <p>Bring in existing leads via CSV or add them manually. Assign to sales reps instantly.</p>
            </div>
            <div class="step-card animate delay-3">
                <div class="step-number">3</div>
                <h3>Track & Follow Up</h3>
                <p>Log calls, update statuses, and schedule follow-ups — all tracked in real time by your team.</p>
            </div>
            <div class="step-card animate delay-4">
                <div class="step-number">4</div>
                <h3>Close & Grow</h3>
                <p>Analyze what's working, double down on it, and watch your conversion rates climb.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══ CORE VALUES (if configured) ══════════════════════════ -->
<?php if (!empty($core_values)): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-heart"></i> Our Values</div>
            <h2 class="section-title">Built on Principles That Matter</h2>
        </div>
        <div class="values-grid">
            <?php foreach ($core_values as $i => $v): ?>
            <div class="value-card animate delay-<?php echo ($i % 4) + 1; ?>">
                <div class="value-icon"><i class="fa-solid fa-check"></i></div>
                <h3><?php echo htmlspecialchars($v['title']); ?></h3>
                <p><?php echo htmlspecialchars($v['description']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══ PRICING (if configured) ══════════════════════════════ -->
<?php if (!empty($pricing_plans)): ?>
<section class="section<?php echo empty($core_values) ? ' section-alt' : ''; ?>">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-tag"></i> Pricing</div>
            <h2 class="section-title">Simple, Transparent Pricing</h2>
            <p class="section-subtitle">Choose the plan that fits your team. No hidden fees. Cancel anytime.</p>
        </div>
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
                    <p style="font-size:.8375rem;color:var(--primary);font-weight:600;margin:.25rem 0 0;"><?php echo htmlspecialchars($plan['subtitle']); ?></p>
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
    </div>
</section>
<?php endif; ?>

<!-- ══ TESTIMONIALS ══════════════════════════════════════════ -->
<section class="section<?php echo (empty($pricing_plans) && empty($core_values)) ? ' section-alt' : ''; ?>">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-quote-left"></i> Testimonials</div>
            <h2 class="section-title">Loved by Teams Everywhere</h2>
            <p class="section-subtitle">See what customers say about how <?php echo htmlspecialchars($site_name); ?> transformed their sales process.</p>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card animate delay-1">
                <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                <p class="testimonial-text">"Since switching to <?php echo htmlspecialchars($site_name); ?>, our lead conversion rate jumped by 40%. The follow-up reminders alone are worth every rupee."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">RK</div>
                    <div>
                        <div class="testimonial-name">Rajesh Kumar</div>
                        <div class="testimonial-role">Sales Director, TechCorp India</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card animate delay-2">
                <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                <p class="testimonial-text">"The multi-company feature saved us so much time. We manage 4 brands from one dashboard without any confusion. Brilliant product."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar" style="background:#06b6d4;">PM</div>
                    <div>
                        <div class="testimonial-name">Priya Mehta</div>
                        <div class="testimonial-role">CEO, RetailHub Group</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card animate delay-3">
                <div class="testimonial-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                <p class="testimonial-text">"The analytics dashboard gives insights we never had before — exactly which reps are performing and where the pipeline is stuck. Game changer."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar" style="background:#10b981;">AS</div>
                    <div>
                        <div class="testimonial-name">Amit Sharma</div>
                        <div class="testimonial-role">Founder, FinEdge Solutions</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ STATS DARK ════════════════════════════════════════════ -->
<section class="section section-dark">
    <div class="container">
        <div class="section-header animate" style="margin-bottom:2.5rem;">
            <h2 class="section-title">Results That Speak for Themselves</h2>
            <p class="section-subtitle">Numbers from real businesses using <?php echo htmlspecialchars($site_name); ?> every day.</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:2rem;text-align:center;">
            <?php
            $stats = [
                ['500+','Companies Onboarded'],
                ['1M+', 'Leads Tracked'],
                ['98%', 'Customer Satisfaction'],
                ['3x',  'Average Revenue Growth'],
            ];
            foreach ($stats as $i => [$n,$l]): ?>
            <div class="animate delay-<?php echo $i+1; ?>">
                <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:2.75rem;font-weight:800;color:#fff;line-height:1;"><?php echo $n; ?></div>
                <div style="color:rgba(255,255,255,.55);font-size:.875rem;margin-top:.5rem;font-weight:500;"><?php echo $l; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ CTA ═══════════════════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <div class="cta-banner animate">
            <h2>Ready to Transform Your Business?</h2>
            <p>Join 500+ companies already using <?php echo htmlspecialchars($site_name); ?> to build stronger customer relationships and drive growth.</p>
            <div class="cta-actions">
                <a href="signup.php" class="btn btn-white btn-lg">
                    <i class="fa-solid fa-rocket"></i> Start Free Today
                </a>
                <a href="/crm/" class="btn btn-ghost btn-lg">
                    <i class="fa-solid fa-right-to-bracket"></i> Sign In
                </a>
                <a href="contact.php" class="btn btn-ghost btn-lg">
                    <i class="fa-solid fa-envelope"></i> Talk to Sales
                </a>
            </div>
        </div>
    </div>
</section>

</main>

<?php include 'includes/footer.php'; ?>
