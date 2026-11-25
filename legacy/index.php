<?php
include 'db.php'; // ഡാറ്റാബേസ് കണക്ഷൻ

// അഡ്മിൻ പാനലിൽ നിന്ന് Outreach സ്റ്റാറ്റസ് എടുക്കുന്നു
$outreach_state = 'open'; 
$settings_q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='outreach_status'");
if ($settings_q && $settings_q->num_rows > 0) {
    $outreach_state = $settings_q->fetch_assoc()['setting_value'];
}

// Home page: show the actual current calendar year's committee only.
$home_committee_year = (int) (new DateTimeImmutable(
    'now', new DateTimeZone('Asia/Kolkata')
))->format('Y');
$home_committee_members = [];
$home_committee_error = false;

function home_committee_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function home_committee_photo($value) {
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)
        || substr($value, 0, 2) === '//') {
        return '';
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
        return preg_match('~^https?://~i', $value) ? $value : '';
    }
    return $value;
}

try {
    $home_committee_stmt = $conn->prepare(
        "SELECT name, position, photo FROM leadership
         WHERE committee_year = ?
         ORDER BY CASE LOWER(TRIM(position))
            WHEN 'president' THEN 1
            WHEN 'general secretary' THEN 2
            WHEN 'treasurer' THEN 3
            ELSE 4
         END, name ASC"
    );
    if (!$home_committee_stmt) {
        throw new RuntimeException('Could not prepare current committee query.');
    }
    $home_committee_stmt->bind_param('i', $home_committee_year);
    if (!$home_committee_stmt->execute()) {
        throw new RuntimeException('Could not load current committee.');
    }
    $home_committee_stmt->bind_result($home_member_name, $home_member_position, $home_member_photo);
    while ($home_committee_stmt->fetch()) {
        $home_committee_members[] = [
            'name' => $home_member_name,
            'position' => $home_member_position,
            'photo' => $home_member_photo
        ];
    }
    $home_committee_stmt->close();
} catch (Throwable $error) {
    error_log('Home page committee: ' . $error->getMessage());
    $home_committee_error = true;
    $home_committee_members = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NUS'HA - Noorul Hidaya</title>
    <link rel="preload" as="image" href="/bg-image.jpg" fetchpriority="high">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/brand.css">
    <link rel="stylesheet" href="/portal-login.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="footer.css">
    
    <style>
        /* =========================================================
           MOBILE RESPONSIVENESS FIXES ONLY (No Design Changes)
           ========================================================= */
        
        /* സ്ക്രോൾ ചെയ്യുമ്പോൾ പേജ് സൈഡിലേക്ക് പോകാതിരിക്കാൻ */
        html, body { overflow-x: hidden; width: 100%; }

        /* Desktop Header Alignment */
        .header-right { display: flex; align-items: center; gap: 15px; }
        
        /* Mobile Menu Button (Hidden on Desktop) */
        .mobile-menu-toggle { display: none; background: transparent; border: none; font-size: 28px; cursor: pointer; color: #0b1c3c; padding: 5px; transition: 0.3s; }
        .mobile-portal-li { display: none; }

        /* Footer portal link fix */
        ul.f-links li a.open-portal-modal { background: none !important; padding: 0 !important; border: none !important; box-shadow: none !important; display: inline !important; color: inherit; }

        @media (max-width: 992px) {
            .responsive-split { grid-template-columns: 1fr !important; }
            .modern-vm-sec { flex-direction: column !important; }
            .modern-vm-card { width: 100% !important; box-sizing: border-box !important; }
        }

        /* -------------------------------------------
           MOBILE VIEW (ഫോണിൽ കാണാൻ വേണ്ടി മാത്രം)
           ------------------------------------------- */
        @media (max-width: 768px) {
            
            /* 1. Header & Hamburger Menu Layout */
            #main-header { 
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 15px 20px !important;
                position: relative; 
                background: #ffffff !important; /* White bg for black logo visibility */
                box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            }
            
            .logo-sub { display: none !important; } 
            .mobile-menu-toggle { display: block !important; }
            .header-right { display: none !important; } /* Hide desktop right section */
            .mobile-portal-li { display: block !important; border-bottom: none !important; }
            
            /* Logo Black Fix on Mobile Menu */
            .logo-icon { object-fit: contain; filter: brightness(0) !important; }
            .logo, .logo-text, .logo-title, .logo-sub, .logo * { color: #000000 !important; text-decoration: none !important; }
            
            /* 2. Mobile Nav Dropdown */
            nav#navbar {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: #ffffff;
                box-shadow: 0 20px 30px rgba(0,0,0,0.15);
                padding: 20px 30px;
                z-index: 1000;
                border-top: 2px solid #fbbc05;
                border-bottom-left-radius: 20px;
                border-bottom-right-radius: 20px;
                margin-top: 0 !important;
            }
            
            nav#navbar.active-menu {
                display: block !important;
                animation: slideDown 0.4s ease forwards;
            }
            
            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            nav#navbar ul { 
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0 !important;
                margin: 0; padding: 0;
            }
            
            nav#navbar ul li { 
                width: 100%;
                border-bottom: 1px solid #f1f5f9;
                text-align: center;
            }
            
            nav#navbar ul li a { 
                display: block;
                padding: 15px 0;
                font-size: 16px;
                font-weight: 700;
                color: #0b1c3c !important;
                text-decoration: none;
            }
            
            /* Styled Portal Button in Mobile Menu */
            .mobile-portal-btn {
                background: #0b1c3c !important;
                color: #fff !important;
                border-radius: 10px;
                margin-top: 10px;
                display: inline-block !important;
                width: auto;
                padding: 12px 30px !important;
            }
            .mobile-portal-btn:hover {
                background: #fbbc05 !important;
                color: #0b1c3c !important;
            }
            
            /* 3. Hero Text Cut-off Fix */
            .main-title { font-size: 2.6rem !important; word-wrap: break-word !important; hyphens: auto; }
            .hero { padding-left: 15px !important; padding-right: 15px !important; }
            
            /* 4. Grid Stacking Fix */
            .responsive-split { grid-template-columns: 1fr !important; gap: 30px !important; }
            .responsive-cards { grid-template-columns: 1fr !important; }
            .leaders-grid { grid-template-columns: 1fr !important; }
            
            /* 5. Footer Stacking */
            .footer-container { display: flex !important; flex-direction: column !important; text-align: center !important; gap: 30px !important; }
            .footer-logo, .social-icons, .f-bottom-links { justify-content: center !important; }
            .c-item { flex-direction: column !important; align-items: center !important; text-align: center !important; gap: 5px !important; }
        }

        /* Modal Styles (നിങ്ങളുടെ ഒറിജിനൽ കോഡ്) */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 28, 60, 0.8); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; padding: 40px; border-radius: 20px; width: 90%; max-width: 400px; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.2); box-sizing: border-box; max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 25px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #ef4444; transform: rotate(90deg); }
        .login-tabs { display: flex; margin-bottom: 25px; background: #f1f5f9; border-radius: 10px; padding: 5px; }
        .tab-btn { flex: 1; padding: 10px; border: none; background: transparent; font-family: 'Montserrat', sans-serif; font-weight: 700; font-size: 14px; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.3s; }
        .tab-btn.active { background: #fff; color: #0b1c3c; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .login-form { display: none; }
        .login-form.active-form { display: block; animation: slideUp 0.4s ease; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: #0b1c3c; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px; font-family: 'Montserrat', sans-serif; font-size: 14px; color: #0b1c3c; outline: none; transition: 0.3s; box-sizing: border-box; }
        .form-group input:focus { border-color: #fbbc05; background: #fffdf0; }
        .submit-btn { width: 100%; padding: 14px; background: #0b1c3c; color: #fff; border: none; border-radius: 10px; font-family: 'Montserrat', sans-serif; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .submit-btn:hover { background: #fbbc05; color: #0b1c3c; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(251, 188, 5, 0.3); }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* Responsive footer. Scoped classes keep its layout independent. */
        .nus-footer, .nus-footer * { box-sizing:border-box; }
        .nus-footer {
            position:relative; width:100%; margin:0; padding:0;
            background:#0b1c3c; color:#c0cada;
            font-family:'Montserrat',sans-serif; border-top:3px solid #fbbc05;
        }
        .nus-footer .nus-footer-wrap { width:100%; max-width:1200px; margin:0 auto; padding:56px 28px 0; }
        .nus-footer .nus-footer-grid { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,.75fr) minmax(0,.85fr) minmax(0,1.25fr); gap:36px; align-items:start; padding-bottom:40px; }
        .nus-footer .nus-footer-col { min-width:0; margin:0; padding:0; text-align:left; }
        .nus-footer .nus-footer-brand { display:flex; align-items:center; gap:14px; margin:0 0 18px; color:#fff; text-decoration:none; }
        .nus-footer .nus-footer-logo-box { display:flex; align-items:center; justify-content:center; width:64px; height:64px; padding:8px; flex:0 0 64px; background:#fff; border-radius:14px; }
        .nus-footer .nus-footer-logo-box img { display:block; width:100%; height:100%; object-fit:contain; }
        .nus-footer .nus-footer-brand-name { display:block; margin:0 0 5px; color:#fff; font-size:23px; line-height:1.1; font-weight:900; letter-spacing:.5px; }
        .nus-footer .nus-footer-brand-sub { display:block; color:#c0cada; font-size:9px; line-height:1.65; font-weight:600; letter-spacing:1px; }
        .nus-footer .nus-footer-description { max-width:310px; margin:0 0 20px; color:#c0cada; font-size:12px; font-weight:400; line-height:1.9; }
        .nus-footer .nus-footer-description strong { color:#e9edf5; font-weight:600; }
        .nus-footer .nus-footer-socials { display:flex; flex-wrap:wrap; justify-content:flex-start; gap:9px; margin:0; }
        .nus-footer .nus-footer-socials a { display:flex; align-items:center; justify-content:center; width:36px; height:36px; color:#e9edf5; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.14); border-radius:10px; font-size:14px; text-decoration:none; transition:background .2s,color .2s; }
        .nus-footer .nus-footer-socials a:hover { background:#fbbc05; border-color:#fbbc05; color:#0b1c3c; }
        .nus-footer .nus-footer-heading { margin:0 0 20px; padding:0; color:#fff; font-size:14px; font-weight:700; line-height:1.5; letter-spacing:.2px; }
        .nus-footer .nus-footer-heading::after { content:''; display:block; width:24px; height:3px; margin-top:11px; background:#fbbc05; border-radius:3px; }
        .nus-footer .nus-footer-links { display:grid; gap:10px; list-style:none; margin:0; padding:0; }
        .nus-footer .nus-footer-links li { margin:0; padding:0; }
        .nus-footer .nus-footer-links a { display:inline-block; padding:3px 0; margin:0; color:#c0cada; background:none; border:0; box-shadow:none; border-radius:0; font-size:12px; font-weight:500; line-height:1.65; text-decoration:none; transition:color .2s; }
        .nus-footer .nus-footer-links a:hover { color:#fbbc05; }
        .nus-footer .nus-footer-contact-list { display:grid; gap:16px; list-style:none; margin:0; padding:0; }
        .nus-footer .nus-footer-contact { display:flex; align-items:flex-start; gap:11px; min-width:0; margin:0; padding:0; }
        .nus-footer .nus-footer-contact-icon { display:flex; align-items:center; justify-content:center; flex:0 0 32px; width:32px; height:32px; border-radius:9px; background:rgba(251,188,5,.1); color:#fbbc05; font-size:12px; }
        .nus-footer .nus-footer-contact-text { min-width:0; padding-top:3px; }
        .nus-footer .nus-footer-contact-label { display:block; margin:0 0 3px; color:#8fa1bb; font-size:9px; line-height:1.4; font-weight:600; text-transform:uppercase; letter-spacing:.8px; }
        .nus-footer .nus-footer-contact p, .nus-footer .nus-footer-contact a { display:block; margin:0; color:#d8e0ed; font-size:12px; font-weight:500; line-height:1.7; text-decoration:none; overflow-wrap:anywhere; }
        .nus-footer .nus-footer-contact a:hover { color:#fbbc05; }
        .nus-footer .nus-footer-bottom { display:flex; align-items:center; justify-content:space-between; gap:16px 24px; border-top:1px solid rgba(255,255,255,.12); padding:20px 0; }
        .nus-footer .nus-footer-copyright { margin:0; color:#96a7be; font-size:10px; line-height:1.8; font-weight:500; }
        .nus-footer .nus-footer-legal { display:flex; flex-wrap:wrap; align-items:center; gap:18px; margin:0; }
        .nus-footer .nus-footer-legal a { color:#96a7be; font-size:10px; line-height:1.8; font-weight:500; text-decoration:none; }
        .nus-footer .nus-footer-legal a:hover { color:#fbbc05; }
        .nus-footer a:focus-visible { outline:2px solid #fbbc05; outline-offset:5px; border-radius:3px; }
        @media (max-width:1050px) {
            .nus-footer .nus-footer-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:34px 42px; }
            .nus-footer .nus-footer-description { max-width:380px; }
        }
        @media (max-width:600px) {
            .nus-footer .nus-footer-wrap { padding:36px 22px 0; }
            .nus-footer .nus-footer-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:28px 20px; padding-bottom:28px; }
            .nus-footer .nus-footer-about, .nus-footer .nus-footer-connect { grid-column:1 / -1; }
            .nus-footer .nus-footer-brand { gap:12px; }
            .nus-footer .nus-footer-logo-box { width:56px; height:56px; flex-basis:56px; border-radius:12px; }
            .nus-footer .nus-footer-brand-name { font-size:22px; }
            .nus-footer .nus-footer-description { max-width:none; font-size:12px; margin-bottom:16px; }
            .nus-footer .nus-footer-heading { margin-bottom:16px; font-size:13px; }
            .nus-footer .nus-footer-links { gap:8px; }
            .nus-footer .nus-footer-links a { padding:5px 0; }
            .nus-footer .nus-footer-contact-list { gap:14px; }
            .nus-footer .nus-footer-bottom { flex-direction:column; align-items:flex-start; gap:10px; padding:18px 0 22px; }
            .nus-footer .nus-footer-copyright { font-size:10px; }
            .nus-footer .nus-footer-legal { gap:20px; }
        }
        @media (max-width:340px) {
            .nus-footer .nus-footer-wrap { padding-left:16px; padding-right:16px; }
            .nus-footer .nus-footer-grid { gap:26px 14px; }
        }
        @media (prefers-reduced-motion:reduce) {
            .nus-footer a { transition:none !important; }
        }
    </style>
</head>
<body>

    <!-- സൈഡിലെ മഞ്ഞ വെളിച്ചം -->
    <div class="side-glow"></div>

    <!-- നാവിഗേഷൻ ബാർ -->
    <header id="main-header" class="fade-in">
        <!-- Logo -->
        <div class="logo">
            <span class="nusha-mark" aria-hidden="true"></span>
            <div class="logo-text">
                <span class="logo-title">NUS'HA</span>
                <span class="logo-sub">Noorul hidaya union for<br>heterogeneous activities</span>
            </div>
        </div>
        
        <!-- Mobile Hamburger Menu Button -->
        <button id="mobile-menu-btn" class="mobile-menu-toggle"><i class="fa-solid fa-bars"></i></button>

        <nav id="navbar">
            <ul>
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="leadership.php">Leadership</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="calendar.php">Event Calendar</a></li>
                
                <!-- Outreach അഡ്മിൻ പെർമിഷൻ അനുസരിച്ച് മാത്രം കാണിക്കുന്നു -->
                <?php if($outreach_state == 'open') { ?>
                <li><a href="outreach.php">Outreach</a></li>
                <?php } ?>
                
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                
                <!-- Portal Link inside mobile menu -->
                <li class="mobile-portal-li">
                    <a href="/portals" class="portal-btn open-portal-modal mobile-portal-btn" data-portal-login="student">Portal Login</a>
                </li>
            </ul>
        </nav>

        <div class="header-right">
            <a href="/portals" class="portal-btn open-portal-modal" data-portal-login="student">Portal</a>
        </div>
    </header>

    <!-- പ്രധാന ഭാഗം (Hero Section) -->
    <section id="home" class="hero">
        <div class="hero-content">
            <div class="title-wrapper animate-up delay-1">
                <h1 class="main-title">Strength<br>in Knowledge</h1>
                <div class="yellow-line"></div>
            </div>
            <div class="sub-section animate-up delay-2">
                <h2 class="sub-title-bold">NUS'HA</h2>
                <h2 class="sub-title-light">Noorul hidaya union for<br>heterogeneous activities</h2>
            </div>
            <div class="bottom-info animate-up delay-3">
                <p>Noorul Hidaya Islamic Academy, Pattambi<br>
                   Affiliated to Darul Huda Islamic University<br>
                   Run by : Noorul Hidaya Islamic Center</p>
            </div>
        </div>

        <a href="#white-section" class="scroll-indicator">
            <span class="scroll-track" aria-hidden="true"><span></span></span>
            <span>Scroll down</span>
        </a>
    </section>

    <!-- താഴെയുള്ള വെളുത്ത ഭാഗം -->
    <main id="white-section" class="white-content">
        
        <!-- Vision & Mission -->
        <section class="modern-vm-sec">
            <div class="modern-vm-card scroll-anim reveal-left">
                <div class="vm-icon-float"><i class="fa-solid fa-eye"></i></div>
                <div class="vm-text-content">
                    <h3>Our Vision</h3>
                    <div class="small-yellow-line"></div>
                    <p>To cultivate an enlightened and value-driven student community rooted in Ta'lim, Tarbiya, and Da'wah, capable of leading society with knowledge and social responsibility.</p>
                </div>
            </div>
            
            <div class="modern-vm-card scroll-anim reveal-right">
                <div class="vm-icon-float"><i class="fa-solid fa-bullseye"></i></div>
                <div class="vm-text-content">
                    <h3>Our Mission</h3>
                    <div class="small-yellow-line"></div>
                    <p>To nurture students through academic excellence, moral and spiritual development, leadership formation, and constructive social engagement.</p>
                </div>
            </div>
        </section>

        <!-- RECENT PROGRAMMES & LATEST UPDATES -->
        <section class="leadership-sec" style="padding-top: 50px; padding-bottom: 20px; font-family: 'Montserrat', sans-serif;">
            
            <div class="responsive-split" style="width: 100%; display: grid; grid-template-columns: 1.6fr 1fr; gap: 40px; align-items: start;">
                
                <!-- LEFT COLUMN: RECENT PROGRAMMES -->
                <div style="width: 100%;">
                    
                    <!-- Section Header -->
                    <div class="scroll-anim reveal-up" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px;">
                        <h2 style="font-size: 32px; color: #0b1c3c; font-weight: 900; margin: 0;">Recent Programmes</h2>
                        <a href="events.php" style="color: #fbbc05; font-weight: 700; text-decoration: none; font-size: 14px;">View All <i class="fa-solid fa-arrow-right"></i></a>
                    </div>

                    <!-- Cards Grid -->
                    <div class="responsive-cards" style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <?php
                        $hl_res = $conn->query("SELECT * FROM highlights ORDER BY id DESC LIMIT 2");
                        $delay = 100;
                        
                        if ($hl_res && $hl_res->num_rows > 0) {
                            while($row = $hl_res->fetch_assoc()) {
                                echo '
                                <div class="scroll-anim reveal-up delay-'.$delay.'" style="background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 5px 25px rgba(0,0,0,0.04); display: flex; flex-direction: column; transition: transform 0.3s; box-sizing: border-box;">
                                    
                                    <!-- ഫോട്ടോ വരുന്ന ഭാഗം -->
                                    <div style="height: 180px; background-image: url(\''.$row['image'].'\'); background-size: cover; background-position: center; position: relative;">
                                        <!-- തീയതി ഫോട്ടോയ്ക്ക് മുകളിൽ വരാൻ -->
                                        <div style="position: absolute; top: 15px; left: 15px;">
                                            <span style="background: #fbbc05; color: #0b1c3c; font-size: 11px; font-weight: 800; padding: 6px 16px; border-radius: 20px;">'.$row['event_date'].'</span>
                                        </div>
                                    </div>
                                    
                                    <!-- പേരും വിവരണവും -->
                                    <div style="padding: 20px; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                                        <div>
                                            <h4 style="color: #0b1c3c; font-size: 18px; margin-bottom: 8px; font-weight: 800; line-height: 1.4;">'.$row['title'].'</h4>
                                            <p style="color: #94a3b8; font-size: 13px; line-height: 1.6; font-weight: 500; margin: 0;">'.substr($row['description'], 0, 80).'...'.'</p>
                                        </div>
                                    </div>
                                </div>';
                                $delay += 100;
                            }
                        } else {
                            echo "<p style='grid-column: span 2; color: #64748b;'>No programmes available.</p>";
                        }
                        ?>
                    </div>
                </div>

                <!-- RIGHT COLUMN: LATEST UPDATES -->
                <div style="width: 100%;">
                    
                    <!-- Section Header -->
                    <div class="scroll-anim reveal-up delay-200" style="margin-bottom: 25px; height: 38px; display: flex; align-items: flex-end;">
                        <h2 style="font-size: 32px; color: #0b1c3c; font-weight: 900; margin: 0;">Latest Updates</h2>
                    </div>

                    <!-- Updates Container with White Background -->
                    <div class="scroll-anim reveal-up delay-300" style="background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 5px 25px rgba(0,0,0,0.04); display: flex; flex-direction: column; gap: 15px; box-sizing: border-box;">
                        <?php
                        $up_res = $conn->query("SELECT * FROM updates ORDER BY id DESC LIMIT 3");
                        $up_delay = 400;
                        
                        if ($up_res && $up_res->num_rows > 0) {
                            while($u = $up_res->fetch_assoc()) {
                                
                                $tag = strtoupper(trim($u['tag']));
                                if ($tag == 'NEW') {
                                    $icon = 'fa-bell'; $icon_bg = '#fee2e2'; $icon_col = '#ef4444'; $tag_bg = '#ef4444';
                                } elseif ($tag == 'ALERT') {
                                    $icon = 'fa-file-lines'; $icon_bg = '#fef3c7'; $icon_col = '#f59e0b'; $tag_bg = '#f59e0b';
                                } else {
                                    $icon = 'fa-users'; $icon_bg = '#eff6ff'; $icon_col = '#3b82f6'; $tag_bg = '#3b82f6';
                                }

                                echo '
                                <div class="scroll-anim reveal-up delay-'.$up_delay.'" style="display: flex; gap: 15px; padding: 15px; background: #f8fafc; border-radius: 12px; transition: 0.3s; align-items: center;">
                                    <!-- Dynamic Icon Box -->
                                    <div style="width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: '.$icon_bg.'; color: '.$icon_col.'; font-size: 16px;">
                                        <i class="fa-solid '.$icon.'"></i>
                                    </div>
                                    <!-- Dynamic Content -->
                                    <div style="flex-grow: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <span style="background: '.$tag_bg.'; color: #fff; font-size: 9px; font-weight: 800; padding: 3px 10px; border-radius: 15px; letter-spacing: 0.5px;">'.$u['tag'].'</span>
                                            <span style="font-size: 11px; color: #94a3b8; font-weight: 600;">'.$u['time_label'].'</span>
                                        </div>
                                        <p style="color: #475569; font-size: 13px; font-weight: 600; line-height: 1.4; margin: 0;">'.$u['content'].'</p>
                                    </div>
                                </div>';
                                $up_delay += 100;
                            }
                        } else {
                            echo "<p style='color: #64748b; text-align: center; margin: 0;'>No new updates posted.</p>";
                        }
                        ?>
                    </div>
                </div>

            </div>
        </section>

        <!-- ഹോം പേജിലെ Leadership Section -->
        <section class="leadership-sec" id="leadership">
            <div class="section-title-box scroll-anim reveal-up">
                <h2>Our Leadership</h2>
                <div class="yellow-line-center"></div>
                <p>Guided by Vision, Driven by Passion</p>
                <span style="display:inline-block; margin-top:12px; padding:7px 16px; border-radius:20px; background:#fff4cc; color:#0b1c3c; font-size:12px; font-weight:800;">
                    <?= home_committee_escape($home_committee_year) ?> Committee
                </span>
            </div>

            <div class="leaders-grid">
                <?php if ($home_committee_error): ?>
                    <p style="grid-column:1 / -1; text-align:center; width:100%; color:#64748b;" role="alert">
                        Committee details are temporarily unavailable. Please try again later.
                    </p>
                <?php elseif (!$home_committee_members): ?>
                    <p style="grid-column:1 / -1; text-align:center; width:100%; color:#64748b;">
                        Committee details for <?= home_committee_escape($home_committee_year) ?> have not been added yet.
                    </p>
                <?php else: ?>
                    <?php foreach ($home_committee_members as $home_index => $home_member):
                        $home_photo = home_committee_photo($home_member['photo']);
                        $home_delay = (($home_index % 3) + 1) * 100;
                    ?>
                    <div class="leader-card scroll-anim reveal-up delay-<?= $home_delay ?>">
                        <div class="leader-img-wrapper">
                            <div class="leader-img" style="position:relative; overflow:hidden; min-height:140px; background-color:#e2e8f0; display:flex; align-items:center; justify-content:center;">
                                <i class="fa-solid fa-user" aria-hidden="true" style="font-size:45px; color:#64748b;"></i>
                                <?php if ($home_photo !== ''): ?>
                                    <img src="<?= home_committee_escape($home_photo) ?>"
                                         alt="<?= home_committee_escape($home_member['name']) ?>"
                                         loading="lazy"
                                         style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover;"
                                         onerror="this.style.display='none'">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="leader-info">
                            <h4><?= home_committee_escape($home_member['name']) ?></h4>
                            <p><?= home_committee_escape($home_member['position']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- ഫൂട്ടർ -->
    <?php include __DIR__ . '/footer.php'; ?>

    <!-- Portal Login Popup (Modal) -->
    <?php include __DIR__ . '/portal-login.php'; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            // സ്ക്രോൾ അനിമേഷൻസ്
            const header = document.getElementById('main-header');
            if(header) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 50) {
                        header.classList.add('scrolled');
                    } else {
                        header.classList.remove('scrolled');
                    }
                });
            }

            // Scroll Reveal Observer
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('active');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.1 });
            document.querySelectorAll('.scroll-anim').forEach((el) => observer.observe(el));

            // Mobile Hamburger Menu Toggle Logic
            const menuBtn = document.getElementById('mobile-menu-btn');
            const navbar = document.getElementById('navbar');
            
            if(menuBtn && navbar) {
                const icon = menuBtn.querySelector('i');
                menuBtn.addEventListener('click', () => {
                    navbar.classList.toggle('active-menu');
                    if(navbar.classList.contains('active-menu')){
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-xmark');
                    } else {
                        icon.classList.remove('fa-xmark');
                        icon.classList.add('fa-bars');
                    }
                });
            }

            // Portal Login Popup Fix

        });
    </script>
</body>
</html>