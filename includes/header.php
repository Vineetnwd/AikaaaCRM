<?php require_once __DIR__ . '/../data.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        echo htmlspecialchars($site_name);
        if (!empty($data['site_tagline'])) echo ' — ' . htmlspecialchars($data['site_tagline']);
    ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(!empty($data['site_description']) ? $data['site_description'] : $site_name . ' — CRM platform to manage leads, customers, and grow your business.'); ?>">
    <meta property="og:title"       content="<?php echo htmlspecialchars($site_name); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(!empty($data['site_description']) ? $data['site_description'] : ''); ?>">
    <meta property="og:type"        content="website">
    <?php if ($favicon_url): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon_url); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Cache-busting version from file mtime -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">

    <script>
        window.applyThemeColor = function(primaryHex, secondaryHex) {
            if (primaryHex) {
                document.documentElement.style.setProperty('--primary', primaryHex);
                
                // Generate darker and lighter shades dynamically
                let r = parseInt(primaryHex.slice(1, 3), 16) || 0,
                    g = parseInt(primaryHex.slice(3, 5), 16) || 0,
                    b = parseInt(primaryHex.slice(5, 7), 16) || 0;
                    
                let dr = Math.max(0, Math.floor(r * 0.8)),
                    dg = Math.max(0, Math.floor(g * 0.8)),
                    db = Math.max(0, Math.floor(b * 0.8));
                document.documentElement.style.setProperty('--primary-dark', `rgb(${dr}, ${dg}, ${db})`);
                
                let lr = Math.floor(r + (255 - r) * 0.94),
                    lg = Math.floor(g + (255 - g) * 0.94),
                    lb = Math.floor(b + (255 - b) * 0.94);
                document.documentElement.style.setProperty('--primary-light', `rgb(${lr}, ${lg}, ${lb})`);

                let mr = Math.floor(r + (255 - r) * 0.85),
                    mg = Math.floor(g + (255 - g) * 0.85),
                    mb = Math.floor(b + (255 - b) * 0.85);
                document.documentElement.style.setProperty('--primary-mid', `rgb(${mr}, ${mg}, ${mb})`);
            }
            if (secondaryHex) {
                document.documentElement.style.setProperty('--accent', secondaryHex);
                let sr = parseInt(secondaryHex.slice(1, 3), 16) || 0,
                    sg = parseInt(secondaryHex.slice(3, 5), 16) || 0,
                    sb = parseInt(secondaryHex.slice(5, 7), 16) || 0;
                let slr = Math.floor(sr + (255 - sr) * 0.92),
                    slg = Math.floor(sg + (255 - sg) * 0.92),
                    slb = Math.floor(sb + (255 - sb) * 0.92);
                document.documentElement.style.setProperty('--accent-light', `rgb(${slr}, ${slg}, ${slb})`);
            }
        };

        const primaryColor = '<?php echo htmlspecialchars(!empty($data["theme_color_hex"]) ? $data["theme_color_hex"] : "#2563eb"); ?>';
        const secondaryColor = '<?php echo htmlspecialchars(!empty($data["theme_color_secondary_hex"]) ? $data["theme_color_secondary_hex"] : "#06b6d4"); ?>';
        window.applyThemeColor(primaryColor, secondaryColor);
    </script>

    <style>
        /* Pure CSS Animations — highly reliable */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate {
            animation: fadeUp 0.6s ease-out forwards;
            opacity: 0; /* starts hidden */
        }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
    </style>
</head>
<body>

<!-- ── NAVBAR ──────────────────────────────────── -->
<nav class="navbar" id="navbar">
    <div class="container">
        <a href="index.php" class="navbar-brand">
            <?php if ($logo_url): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>"
                     alt="<?php echo htmlspecialchars($site_name); ?>"
                     style="height:80px;width:auto;object-fit:contain;vertical-align:middle;">
            <?php else: ?>
                <i class="fa-solid fa-layer-group"></i>
                <span><?php echo htmlspecialchars($site_name); ?></span>
            <?php endif; ?>
        </a>

        <ul class="nav-links" id="navLinks">
            <?php if (!empty($nav_links)): ?>
                <?php foreach ($nav_links as $link): ?>
                    <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['label']); ?></a></li>
                <?php endforeach; ?>
            <?php else: ?>
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="plan.php">Pricing</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="signup.php">Register Now</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-actions">
            <a href="/crm/" class="btn btn-outline"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
            <a href="signup.php" class="btn btn-primary">Register Now <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<script>
(function(){
    /* ── Navbar ── */
    const nav    = document.getElementById('navbar');
    const toggle = document.getElementById('navToggle');
    const links  = document.getElementById('navLinks');
    window.addEventListener('scroll', () => nav.classList.toggle('scrolled', scrollY > 16));
    toggle.addEventListener('click', () => links.classList.toggle('open'));
    const path = location.pathname.split('/').pop() || 'index.php';
    links.querySelectorAll('a').forEach(a => { if (a.getAttribute('href') === path) a.classList.add('active'); });
})();
</script>
