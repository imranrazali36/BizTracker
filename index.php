<?php
session_start();
include_once('hms/include/config.php');

// ============================================================
// FETCH ABOUT US CONTENT
// ============================================================
$aboutRow = null;
if ($con) {
    $aboutStmt = mysqli_prepare($con,
        "SELECT PageTitle, PageDescription FROM tblpage WHERE PageType = 'aboutus' LIMIT 1"
    );
    if ($aboutStmt) {
        mysqli_stmt_execute($aboutStmt);
        $aboutResult = mysqli_stmt_get_result($aboutStmt);
        $aboutRow    = mysqli_fetch_assoc($aboutResult);
        mysqli_stmt_close($aboutStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>BizTracker</title>
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/fontawsom-all.min.css">
    <link rel="stylesheet" href="assets/css/animate.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>

        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Arial', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
        }

        /* ── Navbar ── */
        .navbar-custom {
            background: linear-gradient(90deg, #4b6cb7, #182848);
            box-shadow: 0 2px 12px rgba(0,0,0,0.18);
            padding: 0.6rem 0;
        }
        .navbar-custom .navbar-brand {
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff !important;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-custom .navbar-brand img {
            height: 42px;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3));
        }
        .navbar-custom .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-size: 1.2rem;
            font-weight: 500;
            margin: 0 20px;
            padding: 6px 2px !important;
            position: relative;
            transition: color 0.2s;
        }
        .navbar-custom .nav-link::after {
            content: '';
            position: absolute;
            width: 0; height: 2px;
            bottom: 0; left: 0;
            background: #ffc107;
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        .navbar-custom .nav-link:hover,
        .navbar-custom .nav-link.active { color: #ffc107 !important; }
        .navbar-custom .nav-link:hover::after,
        .navbar-custom .nav-link.active::after { width: 100%; }
        .navbar-custom .nav-link.active { font-weight: 700; }

        /* ── Hero ── */
        .hero-section {
            position: relative;
            background: url('assets/images/slider/business1.jpg') no-repeat center center / cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            overflow: hidden;
        }
        .hero-section::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(24,40,72,0.82) 0%, rgba(75,108,183,0.72) 100%);
        }
        .hero-section > * { position: relative; z-index: 2; }
        .hero-content { max-width: 760px; padding: 0 1.5rem; }
        .hero-eyebrow {
            display: inline-block;
            background: rgba(255,193,7,0.18);
            border: 1px solid rgba(255,193,7,0.4);
            color: #ffc107;
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 5px 16px;
            border-radius: 999px;
            margin-bottom: 1.25rem;
        }
        .hero-section h1 {
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 800;
            line-height: 1.15;
            text-shadow: 0 2px 16px rgba(0,0,0,0.3);
            margin-bottom: 1rem;
        }
        .hero-section h1 span { color: #ffc107; }
        .hero-section p {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: rgba(255,255,255,0.85);
            margin-bottom: 2rem;
        }
        .hero-cta { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn-hero-primary {
            background: linear-gradient(90deg, #ff9800, #ff5722);
            color: #fff;
            border: none;
            padding: 14px 32px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            box-shadow: 0 4px 20px rgba(255,87,34,0.4);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(255,87,34,0.5);
            color: #fff;
            text-decoration: none;
        }
        .btn-hero-secondary {
            background: rgba(255,255,255,0.12);
            color: #fff;
            border: 2px solid rgba(255,255,255,0.5);
            padding: 13px 32px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            text-decoration: none;
            backdrop-filter: blur(4px);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-hero-secondary:hover {
            background: rgba(255,255,255,0.22);
            border-color: #fff;
            color: #fff;
            text-decoration: none;
        }

        /* ── Stats bar ── */
        .stats-bar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5rem 0;
        }
        .stat-item { text-align: center; padding: 0.5rem 1rem; }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #4b6cb7;
            line-height: 1;
        }
        .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 4px; font-weight: 500; }

        /* ── Shared section ── */
        .section-title {
            font-size: clamp(1.6rem, 3vw, 2.4rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #182848;
        }
        .section-subtitle {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2.5rem;
        }
        .section-badge {
            display: inline-block;
            background: #eff6ff;
            color: #4b6cb7;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 4px 14px;
            border-radius: 999px;
            margin-bottom: 0.75rem;
        }

        /* ── Login cards ── */
        .login-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.13);
        }
        .login-card-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .login-card-body { padding: 1.5rem; text-align: center; }
        .login-card-icon {
            width: 56px; height: 56px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.4rem;
        }
        .login-card-icon.user-icon   { background: #eff6ff; color: #4b6cb7; }
        .login-card-icon.admin-icon  { background: #fef3c7; color: #d97706; }
        .login-card-icon.reg-icon    { background: #f0fdf4; color: #16a34a; }
        .login-card h5 { font-weight: 700; color: #182848; margin-bottom: 0.4rem; }
        .login-card p  { font-size: 0.85rem; color: #64748b; margin-bottom: 1.25rem; }
        .btn-card {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 24px;
            border-radius: 999px;
            font-weight: 600; font-size: 0.9rem;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none; cursor: pointer;
        }
        .btn-card:hover { transform: translateY(-2px); text-decoration: none; }
        .btn-card-blue  { background: linear-gradient(90deg,#4b6cb7,#182848); color: #fff !important; box-shadow: 0 4px 14px rgba(75,108,183,0.35); }
        .btn-card-blue:hover  { box-shadow: 0 8px 20px rgba(75,108,183,0.45); }
        .btn-card-amber { background: linear-gradient(90deg,#f59e0b,#d97706); color: #fff !important; box-shadow: 0 4px 14px rgba(245,158,11,0.35); }
        .btn-card-amber:hover { box-shadow: 0 8px 20px rgba(245,158,11,0.45); }
        .btn-card-green { background: linear-gradient(90deg,#22c55e,#16a34a); color: #fff !important; box-shadow: 0 4px 14px rgba(34,197,94,0.35); }
        .btn-card-green:hover { box-shadow: 0 8px 20px rgba(34,197,94,0.45); }

        /* ── Features ── */
        .features-section { background: #f1f5f9; }
        .feature-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }
        .feature-icon {
            width: 68px; height: 68px;
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 1.6rem;
        }
        .feature-card h5 { font-weight: 700; color: #182848; margin-bottom: 0.5rem; font-size: 1rem; }
        .feature-card p  { font-size: 0.875rem; color: #64748b; line-height: 1.6; margin: 0; }

        /* ── About ── */
        .about-section { background: #fff; }
        .about-text-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 2.5rem;
            height: 100%;
            border: 1px solid #e2e8f0;
        }
        .about-text-card h3 { color: #182848; font-weight: 800; margin-bottom: 1rem; }
        .about-text-card p  { color: #475569; line-height: 1.8; }
        .about-img {
            border-radius: 16px;
            width: 100%;
            height: 400px;
            object-fit: cover;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        }

        /* ── Contact ── */
        .contact-section { background: #f1f5f9; }
        .contact-card {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            height: 100%;
        }
        .contact-card h3 {
            font-weight: 700; color: #182848;
            font-size: 1.15rem; margin-bottom: 1.5rem;
            text-align: center;
        }
        .contact-row {
            display: flex; align-items: flex-start; gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .contact-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: #eff6ff; color: #4b6cb7;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; flex-shrink: 0;
        }
        .contact-label { font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2px; }
        .contact-value { font-size: 0.9rem; color: #1e293b; margin: 0; }

        /* ── Footer ── */
        .footer {
            background: linear-gradient(135deg, #182848 0%, #0f1f45 100%);
            color: #fff;
            padding: 3rem 0 2rem;
        }
        .footer-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 1.3rem; font-weight: 800;
            color: #fff; margin-bottom: 0.5rem;
        }
        .footer-brand img { height: 36px; }
        .footer-desc { color: rgba(255,255,255,0.55); font-size: 0.85rem; max-width: 260px; }
        .footer-links h6 { color: #fff; font-weight: 700; margin-bottom: 1rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; }
        .footer-links ul { list-style: none; padding: 0; margin: 0; }
        .footer-links li { margin-bottom: 0.5rem; }
        .footer-links a { color: rgba(255,255,255,0.6); font-size: 0.875rem; text-decoration: none; transition: color 0.2s; }
        .footer-links a:hover { color: #ffc107; }
        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 2rem; padding-top: 1.25rem;
            color: rgba(255,255,255,0.4); font-size: 0.8rem;
            text-align: center;
        }

        /* ── Scroll-to-top ── */
        #scrollTopBtn {
            display: none;
            position: fixed;
            bottom: 30px; right: 30px;
            z-index: 999;
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4b6cb7, #182848);
            color: white;
            border: none; cursor: pointer;
            font-size: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        #scrollTopBtn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.3); }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .hero-section { min-height: 85vh; }
            .stats-bar .col-6 { border-right: none !important; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
         NAVBAR
         ============================================================ -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo">
                BizTracker
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#logins">Logins</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about_us">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact_us">Contact Us</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ============================================================
         HERO SECTION
         ============================================================ -->
    <section id="home" class="hero-section">
        <div class="hero-content">
            <div class="hero-eyebrow animate__animated animate__fadeInDown">Smart Business Finance</div>
            <h1 class="animate__animated animate__fadeInDown" style="font-size: 80px;">
                Take Control of Your<br><span>Business Finances</span>
            </h1>
            <p class="animate__animated animate__fadeInUp">
                Track income, manage expenses, and predict financial risks — all in one intelligent platform.
            </p>
            <div class="hero-cta animate__animated animate__fadeInUp">
                <a href="#logins" class="btn-hero-primary">
                    Get Started <i class="fas fa-arrow-right"></i>
                </a>
                <a href="#services" class="btn-hero-secondary">
                    <i class="fas fa-play-circle"></i> Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- ============================================================
         STATS BAR
         ============================================================ -->
    <div class="stats-bar">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-6 col-md-3">
                    <div class="stat-item">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                </div>
                <div class="col-6 col-md-3" style="border-left:1px solid #e2e8f0;">
                    <div class="stat-item">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Transactions Tracked</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mt-3 mt-md-0" style="border-left:1px solid #e2e8f0;">
                    <div class="stat-item">
                        <div class="stat-number">98%</div>
                        <div class="stat-label">Prediction Accuracy</div>
                    </div>
                </div>
                <div class="col-6 col-md-3 mt-3 mt-md-0" style="border-left:1px solid #e2e8f0;">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">System Availability</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         LOGINS SECTION
         ============================================================ -->
    <section id="logins" class="py-5" style="background:#fff;">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">Access Portal</span>
                <h2 class="section-title">Sign In to BizTracker</h2>
                <p class="section-subtitle">Choose your access level to get started with your financial dashboard.</p>
            </div>
            <div class="row justify-content-center">

                <!-- User Login -->
                <div class="col-md-4 mb-4">
                    <div class="login-card">
                        <img src="assets/images/admin.jpg" class="login-card-img" alt="User Login">
                        <div class="login-card-body">
                            <div class="login-card-icon user-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <h5>User Login</h5>
                            <p>Access your personal bookkeeping dashboard and manage your business records.</p>
                            <a href="hms/user-login.php" class="btn-card btn-card-blue">
                                <i class="fas fa-sign-in-alt"></i> Login as User
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Admin Login -->
                <div class="col-md-4 mb-4">
                    <div class="login-card">
                        <img src="assets/images/admin2.jpg" class="login-card-img" alt="Admin Login">
                        <div class="login-card-body">
                            <div class="login-card-icon admin-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <h5>Admin Login</h5>
                            <p>Manage users, review feedback, and oversee the full system configuration.</p>
                            <a href="hms/admin" class="btn-card btn-card-amber">
                                <i class="fas fa-lock"></i> Login as Admin
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Registration -->
                <div class="col-md-4 mb-4">
                    <div class="login-card">
                        <img src="assets/images/registration.jpg" class="login-card-img" alt="Registration">
                        <div class="login-card-body">
                            <div class="login-card-icon reg-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h5>New User?</h5>
                            <p>Create your free BizTracker account today and start tracking in minutes.</p>
                            <a href="hms/registration.php" class="btn-card btn-card-green">
                                <i class="fas fa-rocket"></i> Register Now
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ============================================================
         KEY FEATURES SECTION
         ============================================================ -->
    <section id="services" class="py-5 features-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">What We Offer</span>
                <h2 class="section-title">Powerful Features for Your Business</h2>
                <p class="section-subtitle">Everything you need to run a financially healthy business, in one place.</p>
            </div>
            <div class="row">

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#eff6ff;">
                            <i class="fas fa-clipboard" style="color:#4b6cb7;"></i>
                        </div>
                        <h5>Systematic Recording</h5>
                        <p>Accurately record every income and expense entry in one organised, searchable place.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#f0fdf4;">
                            <i class="fas fa-list-ol" style="color:#16a34a;"></i>
                        </div>
                        <h5>Financial Statements</h5>
                        <p>Generate clear financial summaries and downloadable reports at any time.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#fef3c7;">
                            <i class="fas fa-brain" style="color:#d97706;"></i>
                        </div>
                        <h5>AI Risk Prediction</h5>
                        <p>Get intelligent financial risk predictions powered by a machine learning neural network.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#fdf2f8;">
                            <i class="fas fa-chart-line" style="color:#9333ea;"></i>
                        </div>
                        <h5>Budget Tracking</h5>
                        <p>Monitor income vs. expenses and stay firmly within your financial goals.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#fff1f2;">
                            <i class="fas fa-shield-alt" style="color:#dc2626;"></i>
                        </div>
                        <h5>Secure Data</h5>
                        <p>Your financial data is protected with server-side validation and encrypted passwords.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#ecfeff;">
                            <i class="fas fa-tags" style="color:#0891b2;"></i>
                        </div>
                        <h5>Category Management</h5>
                        <p>Create custom income and expense categories to keep your records perfectly organised.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#ffe4e6;">
                            <i class="fas fa-file-pdf" style="color:#f43f5e;"></i>
                        </div>
                        <h5>PDF Reports</h5>
                        <p>Export professional income and expense reports as PDF files for any date range.</p>
                    </div>
                </div>

                <div class="col-md-3 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon" style="background:#f0fdf4;">
                            <i class="fas fa-comments" style="color:#16a34a;"></i>
                        </div>
                        <h5>Feedback System</h5>
                        <p>Submit feedback to administrators and receive support directly through the platform.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ============================================================
         ABOUT US SECTION
         ============================================================ -->
    <section id="about_us" class="py-5 about-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">Who We Are</span>
                <h2 class="section-title">About Our System</h2>
                <p class="section-subtitle">Built for small businesses and entrepreneurs who need smart, simple financial management.</p>
            </div>
            <div class="row align-items-center">
                <div class="col-md-6 mb-4">
                    <div class="about-text-card">
                        <?php if ($aboutRow): ?>
                            <?php if (!empty($aboutRow['PageTitle'])): ?>
                                <h3><?php echo htmlspecialchars($aboutRow['PageTitle'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php endif; ?>
                            <div style="color:#475569;line-height:1.8;">
                                <?php echo strip_tags(
                                    $aboutRow['PageDescription'],
                                    '<p><b><strong><i><em><u><br><h2><h3><h4><ul><ol><li><hr>'
                                ); ?>
                            </div>
                        <?php else: ?>
                            <h3>BizTracker</h3>
                            <p>BizTracker is an intelligent bookkeeping platform designed to help small business owners and entrepreneurs track income, manage expenses, and make smarter financial decisions through AI-powered predictions.</p>
                            <p>Developed at Universiti Sultan Zainal Abidin, our system combines modern web technology with machine learning to give you real-time insights into your business health.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <img src="assets/images/about.jpg" class="about-img" alt="About BizTracker">
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================
         CONTACT US SECTION
         ============================================================ -->
    <section id="contact_us" class="py-5 contact-section">
        <div class="container">
            <div class="text-center mb-5">
                <span class="section-badge">Get In Touch</span>
                <h2 class="section-title">Contact Us</h2>
                <p class="section-subtitle">Have questions? We're here to help.</p>
            </div>
            <div class="row">

                <!-- Contact Details -->
                <div class="col-md-6 mb-4">
                    <div class="contact-card">
                        <h3>BizTracker Headquarters</h3>

                        <div class="contact-row">
                            <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="contact-label">Address</div>
                                <p class="contact-value">
                                    Universiti Sultan Zainal Abidin (UniSZA)<br>
                                    Kampus Besut, 22200 Besut<br>
                                    Terengganu Darul Iman, Malaysia
                                </p>
                            </div>
                        </div>

                        <div class="contact-row">
                            <div class="contact-icon"><i class="fas fa-phone"></i></div>
                            <div>
                                <div class="contact-label">Phone</div>
                                <p class="contact-value">+609-699 8888</p>
                            </div>
                        </div>

                        <div class="contact-row">
                            <div class="contact-icon"><i class="fas fa-fax"></i></div>
                            <div>
                                <div class="contact-label">Fax</div>
                                <p class="contact-value">+609-699 9999</p>
                            </div>
                        </div>

                        <div class="contact-row">
                            <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                            <div>
                                <div class="contact-label">Email</div>
                                <p class="contact-value">info@biztracker.com</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map -->
                <div class="col-md-6 mb-4">
                    <div class="contact-card" style="padding:0;overflow:hidden;">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3970.4255305476226!2d102.62517831475637!3d5.764839996093706!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31b5ac3c6d6a9fa5%3A0x8a52e16e57e5b2e0!2sUniversiti+Sultan+Zainal+Abidin+(UniSZA)+Kampus+Besut%2C+Jalan+Tembila%2C+22200+Besut%2C+Terengganu!5e0!3m2!1sen!2smy!4v1700000000001!5m2!1sen!2smy"
                            width="100%"
                            height="100%"
                            style="border:0; min-height:380px; display:block;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="UniSZA Kampus Besut">
                        </iframe>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ============================================================
         FOOTER
         ============================================================ -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="footer-brand">
                        <img src="assets/images/biztracker.png" alt="BizTracker">
                        BizTracker
                    </div>
                    <p class="footer-desc">Smart bookkeeping and financial risk prediction for modern small businesses.</p>
                </div>
                <div class="col-md-2 mb-4 footer-links">
                    <h6>Navigate</h6>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#logins">Logins</a></li>
                        <li><a href="#services">Features</a></li>
                        <li><a href="#about_us">About Us</a></li>
                        <li><a href="#contact_us">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 footer-links">
                    <h6>Access</h6>
                    <ul>
                        <li><a href="hms/user-login.php">User Login</a></li>
                        <li><a href="hms/admin">Admin Login</a></li>
                        <li><a href="hms/registration.php">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4 footer-links">
                    <h6>Contact</h6>
                    <ul>
                        <li><a href="mailto:info@biztracker.com">info@biztracker.com</a></li>
                        <li><a href="tel:+60966998888">+609-699 8888</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> BizTracker. All Rights Reserved. &mdash; Universiti Sultan Zainal Abidin, Kampus Besut, Terengganu, Malaysia</p>
            </div>
        </div>
    </footer>

    <!-- Scroll-to-top -->
    <button id="scrollTopBtn" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script src="assets/js/jquery-3.2.1.min.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/plugins/scroll-nav/js/jquery.easing.min.js"></script>
    <script src="assets/plugins/scroll-nav/js/scrolling-nav.js"></script>
    <script src="assets/plugins/scroll-fixed/jquery-scrolltofixed-min.js"></script>
    <script src="assets/js/script.js"></script>

    <script>
    $(document).ready(function () {

        var NAVBAR_H = $('.navbar-custom').outerHeight() || 70;

        /* ── Smooth scrolling ── */
        $('.navbar-nav .nav-link[href^="#"]').on('click', function (e) {
            var hash = this.hash;
            if (hash === '' || !$(hash).length) return;
            e.preventDefault();

            var $section  = $(hash);
            var $heading  = $section.find('h1, h2, h3').first();
            var targetY   = $heading.length
                            ? $heading.offset().top - NAVBAR_H - 12
                            : $section.offset().top - NAVBAR_H;

            var maxScroll = $(document).height() - $(window).height();
            var extraPad  = 0;
            if (targetY > maxScroll) {
                extraPad = targetY - maxScroll + 4;
                $('body').css('padding-bottom', extraPad + 'px');
            }

            $('html, body').animate(
                { scrollTop: targetY },
                900,
                'easeInOutExpo',
                function () {
                    window.location.hash = hash;
                    if (extraPad > 0) $('body').css('padding-bottom', '');
                }
            );
        });

        /* ── Hero CTA smooth scroll ── */
        $('.btn-hero-primary[href^="#"], .btn-hero-secondary[href^="#"]').on('click', function (e) {
            var hash = this.hash;
            if (hash === '' || !$(hash).length) return;
            e.preventDefault();
            var targetY = $(hash).offset().top - NAVBAR_H;
            $('html, body').animate({ scrollTop: targetY }, 800, 'easeInOutExpo');
        });

        /* ── Active nav link on scroll ── */
        var sections = $('section[id]');
        var navLinks = $('.navbar-nav .nav-link');

        $(window).on('scroll', function () {
            var scrollPos = $(window).scrollTop() + NAVBAR_H + 16;
            sections.each(function (i) {
                var top    = $(this).offset().top;
                var bottom = top + $(this).outerHeight();
                if (scrollPos >= top && scrollPos < bottom) {
                    navLinks.removeClass('active');
                    navLinks.eq(i).addClass('active');
                }
            });
        }).trigger('scroll');

        /* ── Scroll-to-top button ── */
        $(window).on('scroll', function () {
            if ($(this).scrollTop() > 300) {
                $('#scrollTopBtn').fadeIn(200);
            } else {
                $('#scrollTopBtn').fadeOut(200);
            }
        });
    });
    </script>

</body>
</html>