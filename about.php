<?php include 'includes/header.php'; ?>

<main>

<!-- ══ PAGE HERO ════════════════════════════════════════════ -->
<div class="page-hero">
    <div class="container">
        <div class="section-badge animate" style="margin-bottom:.875rem;"><i class="fa-solid fa-building"></i> About Us</div>
        <h1 class="animate delay-1">The Team Behind <span><?php echo htmlspecialchars($site_name); ?></span></h1>
        <p class="animate delay-2">We build tools that help sales teams work smarter, close faster, and grow bigger — without the complexity.</p>
    </div>
</div>

<!-- ══ MISSION ══════════════════════════════════════════════ -->
<section class="section section-alt">
    <div class="container">
        <div class="about-grid">
            <!-- Left — Text -->
            <div>
                <div class="section-badge animate"><i class="fa-solid fa-bullseye"></i> Our Mission</div>
                <h2 class="section-title animate delay-1" style="text-align:left;margin-top:.875rem;">Empowering Businesses to Build Lasting Relationships</h2>
                <p class="animate delay-2" style="color:var(--text-muted);line-height:1.8;margin-top:1rem;font-size:1rem;">
                    At <?php echo htmlspecialchars($site_name); ?>, our mission is to make customer relationship management simple, powerful, and accessible for every business — from startups to enterprises.
                </p>
                <p class="animate delay-3" style="color:var(--text-muted);line-height:1.8;margin-top:.875rem;font-size:1rem;">
                    We believe that technology should simplify your workflow, not complicate it. That's why we combine modern design with robust architecture to create an experience your team will actually love using every day.
                </p>
                <!-- Stats -->
                <div class="about-stat-row animate delay-4">
                    <div class="about-stat-box">
                        <div class="n">500+</div>
                        <div class="l">Companies Served</div>
                    </div>
                    <div class="about-stat-box">
                        <div class="n">98%</div>
                        <div class="l">Retention Rate</div>
                    </div>
                    <div class="about-stat-box">
                        <div class="n">1M+</div>
                        <div class="l">Leads Managed</div>
                    </div>
                    <div class="about-stat-box">
                        <div class="n">3x</div>
                        <div class="l">Avg. Growth</div>
                    </div>
                </div>
            </div>
            <!-- Right — Visual card -->
            <div class="animate delay-2">
                <div style="background:linear-gradient(135deg,#2563eb,#1e40af);border-radius:20px;padding:2.25rem;color:#fff;position:relative;overflow:hidden;">
                    <div style="position:absolute;top:-40px;right:-40px;width:150px;height:150px;background:rgba(255,255,255,.06);border-radius:50%;"></div>
                    <div style="font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.6);margin-bottom:1.25rem;">What Drives Us</div>
                    <?php
                    $drivers = [
                        ['fa-rocket',         'Speed',       'Build and iterate faster than ever before'],
                        ['fa-shield-halved',  'Reliability', 'Your data is always safe and always available'],
                        ['fa-users',          'People First','Designed with the end user in mind, always'],
                        ['fa-lightbulb',      'Innovation',  'Constantly pushing the boundaries of CRM'],
                    ];
                    foreach ($drivers as [$icon, $t, $d]): ?>
                    <div style="display:flex;gap:.875rem;align-items:flex-start;margin-bottom:1.125rem;">
                        <div style="width:36px;height:36px;background:rgba(255,255,255,.12);border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:.875rem;flex-shrink:0;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.9rem;"><?php echo $t; ?></div>
                            <div style="font-size:.8rem;color:rgba(255,255,255,.65);margin-top:2px;"><?php echo $d; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ CORE VALUES ═══════════════════════════════════════════ -->
<?php if (!empty($core_values)): ?>
<section class="section">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-heart"></i> Core Values</div>
            <h2 class="section-title">The Principles We Live By</h2>
            <p class="section-subtitle">Every decision we make is guided by these foundational values that define who we are.</p>
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
<?php else: ?>
<!-- Default values if none configured -->
<section class="section">
    <div class="container">
        <div class="section-header animate">
            <div class="section-badge"><i class="fa-solid fa-heart"></i> Core Values</div>
            <h2 class="section-title">The Principles We Live By</h2>
        </div>
        <div class="values-grid">
            <?php
            $defaults = [
                ['fa-rocket',         'Innovation',     'We constantly push boundaries to deliver features that genuinely move the needle.'],
                ['fa-shield-halved',  'Trust',          'Your data security and privacy is non-negotiable. We treat it with the utmost care.'],
                ['fa-users',          'Customer First', 'Every feature we build starts with one question: does this make our customers more successful?'],
                ['fa-hand-holding',   'Simplicity',     'Powerful software doesn\'t have to be complicated. We obsess over clean, intuitive UX.'],
            ];
            foreach ($defaults as $i => [$icon, $t, $d]): ?>
            <div class="value-card animate delay-<?php echo $i + 1; ?>">
                <div class="value-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                <h3><?php echo $t; ?></h3>
                <p><?php echo $d; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ══ OUR STORY ════════════════════════════════════════════ -->
<section class="section section-alt">
    <div class="container">
        <div class="story-section animate">
            <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:3rem;align-items:center;">
                <div>
                    <div style="font-size:.7rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.45);margin-bottom:.875rem;">
                        <i class="fas fa-book-open" style="margin-right:.35rem;"></i>Our Story
                    </div>
                    <h2>Born Out of Frustration. Built with Purpose.</h2>
                    <div style="display:flex;gap:1.5rem;margin-top:1.75rem;">
                        <div>
                            <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:2rem;font-weight:800;color:#60a5fa;">2020</div>
                            <div style="font-size:.8rem;color:rgba(255,255,255,.5);margin-top:2px;">Founded</div>
                        </div>
                        <div style="width:1px;background:rgba(255,255,255,.1);"></div>
                        <div>
                            <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:2rem;font-weight:800;color:#60a5fa;">500+</div>
                            <div style="font-size:.8rem;color:rgba(255,255,255,.5);margin-top:2px;">Clients</div>
                        </div>
                        <div style="width:1px;background:rgba(255,255,255,.1);"></div>
                        <div>
                            <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:2rem;font-weight:800;color:#60a5fa;">4.9★</div>
                            <div style="font-size:.8rem;color:rgba(255,255,255,.5);margin-top:2px;">Rating</div>
                        </div>
                    </div>
                </div>
                <div>
                    <p><?php echo htmlspecialchars($site_name); ?> was born out of frustration with clunky, expensive, and overcomplicated CRM systems that sales teams dreaded opening every morning.</p>
                    <p>We built <?php echo htmlspecialchars($site_name); ?> from scratch — combining modern design principles with a robust backend — to create a platform that managers and sales reps actually enjoy working with. Something that gets out of your way and lets you focus on what matters: your customers.</p>
                    <div style="margin-top:1.5rem;">
                        <a href="signup.php" class="btn btn-white">
                            <i class="fas fa-rocket"></i> Start Your Journey
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ CTA ════════════════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <div class="cta-banner animate">
            <h2>Ready to Join 500+ Growing Businesses?</h2>
            <p>Start your free trial today. No credit card required. Set up in under 2 minutes.</p>
            <div class="cta-actions">
                <a href="signup.php" class="btn btn-white btn-lg"><i class="fas fa-rocket"></i> Get Started Free</a>
                <a href="contact.php" class="btn btn-ghost btn-lg"><i class="fas fa-envelope"></i> Talk to Us</a>
            </div>
        </div>
    </div>
</section>

</main>

<?php include 'includes/footer.php'; ?>
