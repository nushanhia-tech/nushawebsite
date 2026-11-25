<?php
include 'db.php'; // ഡാറ്റാബേസ് കണക്ഷൻ

// അഡ്മിൻ പാനലിൽ നിന്ന് Outreach സ്റ്റാറ്റസ് എടുക്കുന്നു
$outreach_state = 'open'; 
$settings_q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='outreach_status'");
if ($settings_q && $settings_q->num_rows > 0) {
    $outreach_state = $settings_q->fetch_assoc()['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Contact Us - NUS'HA</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/brand.css">
    <link rel="stylesheet" href="/portal-login.css">
    <!-- Google Fonts -->
    <link rel="stylesheet" href="/vendor/fonts/montserrat.css">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="/vendor/fontawesome/css/site-icons.css">
    <link rel="stylesheet" href="footer.css">

    <style>
        /* =========================================================
           BASE & STRUCTURAL FIXES
           ========================================================= */
        html, body { overflow-x: hidden; width: 100%; margin: 0; padding: 0; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: inherit; }

        /* Desktop Header Alignment */
        .header-right { display: flex; align-items: center; gap: 15px; }
        
        /* Mobile Menu Button (Hidden on Desktop) */
        .mobile-menu-toggle { display: none; background: transparent; border: none; font-size: 28px; cursor: pointer; color: #0b1c3c; padding: 5px; transition: 0.3s; outline: none; }
        .mobile-portal-li { display: none; }

        /* Footer portal link fix */
        ul.f-links li a.open-portal-modal { background: none !important; padding: 0 !important; border: none !important; box-shadow: none !important; display: inline !important; color: inherit; }

        /* Logo Link Color & Stretch Fix (ലോഗോയും ടെക്സ്റ്റും കറുപ്പാക്കി) */
        .logo { text-decoration: none !important; display: flex; align-items: center; gap: 15px; }
        .logo-icon { object-fit: contain !important; filter: brightness(0) !important; width: auto !important; max-height: 45px !important; }
        .logo, .logo-text, .logo-title, .logo-sub, .logo * { color: #000000 !important; text-decoration: none !important; }

        /* Contact Layout Styles */
        .contact-layout { display: grid; grid-template-columns: 1fr 1.5fr; gap: 40px; margin-top: 40px; }
        
        /* Contact Info Cards */
        .info-col { display: flex; flex-direction: column; gap: 20px; }
        .info-card { background: #fff; padding: 30px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; align-items: flex-start; gap: 20px; transition: 0.3s; }
        .info-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); border-color: #e2e8f0; }
        .i-icon { width: 60px; height: 60px; background: #fffbeb; color: #fbbc05; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; flex-shrink: 0; }
        .i-details h4 { font-size: 18px; color: #0b1c3c; margin-bottom: 8px; font-weight: 800; }
        .i-details p { font-size: 14px; color: #64748b; line-height: 1.6; font-weight: 500; }
        
        /* Contact Form */
        .form-col { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; }
        .form-col h3 { font-size: 24px; color: #0b1c3c; margin-bottom: 25px; font-weight: 800; }
        
        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .c-input { width: 100%; padding: 15px 20px; border: 2px solid #f1f5f9; border-radius: 10px; font-family: 'Montserrat', sans-serif; font-size: 14px; color: #334155; transition: 0.3s; background: #f8fafc; outline: none; box-sizing: border-box; }
        .c-input:focus { border-color: #fbbc05; background: #fff; box-shadow: 0 0 0 4px rgba(251, 188, 5, 0.1); }
        textarea.c-input { min-height: 150px; resize: vertical; margin-bottom: 25px; }

        /* -------------------------------------------
           MOBILE VIEW STRICT FIXES
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
                background: #ffffff !important;
                box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                width: 100vw !important; box-sizing: border-box !important;
            }
            
            .logo-sub { display: none !important; } 
            .logo-icon { height: 38px !important; }
            .logo-title { font-size: 22px !important; margin: 0 !important; }
            .mobile-menu-toggle { display: block !important; color: #000 !important; }
            .header-right { display: none !important; }
            .mobile-portal-li { display: block !important; border-bottom: none !important; }
            
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

            /* Page Title Box Padding */
            .inner-page-content { padding: 40px 20px !important; width: 100vw !important; box-sizing: border-box !important; }
            .page-title-box h1 { font-size: 2.2rem !important; word-wrap: break-word !important; line-height: 1.2 !important; margin-bottom: 10px !important; text-align: center; }
            .page-title-box p { font-size: 14px !important; line-height: 1.5 !important; text-align: center; }
            
            /* Contact Layout Stacking */
            .contact-layout { grid-template-columns: 1fr !important; gap: 30px !important; }
            .input-row { grid-template-columns: 1fr !important; gap: 15px !important; }
            .form-col { padding: 25px 20px !important; }
            .info-card { padding: 20px !important; }
            
            /* Footer Mobile Fix */
            .main-footer { padding: 40px 20px !important; width: 100vw !important; box-sizing: border-box !important; }
            .footer-container { display: flex !important; flex-direction: column !important; text-align: center !important; gap: 35px !important; }
            .footer-logo, .social-icons, .f-bottom-links { justify-content: center !important; }
            .c-item { flex-direction: column !important; justify-content: center !important; align-items: center !important; gap: 8px !important; text-align: center !important; }
        }

        /* =========================================================
           PORTAL LOGIN MODAL
           ========================================================= */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 28, 60, 0.8); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
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
    </style>
</head>
<body style="background-color: #f4f7f9;">

    <!-- നാവിഗേഷൻ ബാർ -->
    <header id="main-header" class="scrolled fade-in">
        <a href="index.php" class="logo">
            <span class="nusha-mark" aria-hidden="true"></span>
            <div class="logo-text">
                <span class="logo-title">NUS'HA</span>
                <span class="logo-sub">Noorul hidaya union for<br>heterogeneous activities</span>
            </div>
        </a>
        
        <!-- Mobile Hamburger Menu Button -->
        <button id="mobile-menu-btn" class="mobile-menu-toggle"><i class="fa-solid fa-bars"></i></button>

        <nav id="navbar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="leadership.php">Leadership</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="calendar.php">Event Calendar</a></li>
                
                <?php if($outreach_state == 'open') { ?>
                <li><a href="outreach.php">Outreach</a></li>
                <?php } ?>
                
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php" class="active">Contact</a></li>

                <!-- Portal Link inside mobile menu -->
                <li class="mobile-portal-li">
                    <a href="/portals" class="portal-btn open-portal-modal mobile-portal-btn" data-portal-login="student">Portal Login</a>
                </li>
            </ul>
        </nav>

        <div class="header-right desktop-portal-btn">
            <a href="/portals" class="portal-btn open-portal-modal" data-portal-login="student">Portal</a>
        </div>
    </header>

    <!-- Contact പേജിന്റെ ഉള്ളടക്കം -->
    <main class="inner-page-content" style="padding-top: 100px;">
        
        <!-- Page Title -->
        <div class="page-title-box fade-in">
            <h1>Contact Us</h1>
            <div class="yellow-line-center"></div>
            <p>Have questions or want to collaborate? We'd love to hear from you!</p>
        </div>

        <section style="padding: 0 5%; max-width: 1200px; margin: 40px auto 100px;">
            
            <div class="contact-layout">
                
                <!-- Left Side: Contact Information -->
                <div class="info-col scroll-anim reveal-left">
                    
                    <div class="info-card">
                        <div class="i-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="i-details">
                            <h4>Our Campus</h4>
                            <p>Noorul Hidaya Islamic Academy,<br>Pattambi, Kerala<br>Affiliated to Darul Huda Islamic Univ.</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="i-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fa-solid fa-phone"></i></div>
                        <div class="i-details">
                            <h4>Phone Number</h4>
                            <p>+91 494 2463129<br>+91 98765 43210 (Union Sec)</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="i-icon" style="background: #f0fdf4; color: #10b981;"><i class="fa-solid fa-envelope-open-text"></i></div>
                        <div class="i-details">
                            <h4>Email Address</h4>
                            <p>dsu@dhiu.in<br>info@nusha-academy.com</p>
                        </div>
                    </div>

                    <!-- Social Media Links -->
                    <div style="margin-top: 10px; display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="#" style="width: 45px; height: 45px; background: #0b1c3c; color: white; display: flex; justify-content: center; align-items: center; border-radius: 50%; font-size: 18px; transition: 0.3s;"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#" style="width: 45px; height: 45px; background: #0b1c3c; color: white; display: flex; justify-content: center; align-items: center; border-radius: 50%; font-size: 18px; transition: 0.3s;"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" style="width: 45px; height: 45px; background: #0b1c3c; color: white; display: flex; justify-content: center; align-items: center; border-radius: 50%; font-size: 18px; transition: 0.3s;"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#" style="width: 45px; height: 45px; background: #0b1c3c; color: white; display: flex; justify-content: center; align-items: center; border-radius: 50%; font-size: 18px; transition: 0.3s;"><i class="fa-brands fa-youtube"></i></a>
                    </div>
                </div>

                <!-- Right Side: Contact Form -->
                <div class="form-col scroll-anim reveal-right">
                    <h3>Send us a Message <i class="fa-regular fa-paper-plane" style="color: #fbbc05; margin-left: 10px;"></i></h3>
                    
                    <form action="#" method="POST">
                        <div class="input-row">
                            <input type="text" class="c-input" placeholder="Your Full Name" required>
                            <input type="email" class="c-input" placeholder="Your Email Address" required>
                        </div>
                        
                        <div class="input-row">
                            <input type="text" class="c-input" placeholder="Phone Number (Optional)">
                            <input type="text" class="c-input" placeholder="Subject" required>
                        </div>
                        
                        <textarea class="c-input" placeholder="Write your message here..." required></textarea>
                        
                        <button type="submit" class="submit-btn" style="width: 100%;">
                            Send Message Now <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>

            </div>
        </section>
    </main>

    <!-- ഫൂട്ടർ -->
    <?php include __DIR__ . '/footer.php'; ?>

    <!-- Portal Login Popup (Modal) -->
    <?php include __DIR__ . '/portal-login.php'; ?>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            // Header Scroll Effect
            const header = document.getElementById('main-header');
            if(header) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 50) header.classList.add('scrolled');
                    else header.classList.remove('scrolled');
                });
            }

            // Scroll Reveal Observer
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) { entry.target.classList.add('active'); observer.unobserve(entry.target); }
                });
            }, { threshold: 0.1 });
            document.querySelectorAll('.scroll-anim').forEach((el) => observer.observe(el));

            // Mobile Hamburger Menu Toggle
            const menuBtn = document.getElementById('mobile-menu-btn');
            const navbar = document.getElementById('navbar');
            if(menuBtn && navbar) {
                menuBtn.addEventListener('click', () => {
                    navbar.classList.toggle('active-menu');
                    const icon = menuBtn.querySelector('i');
                    if(navbar.classList.contains('active-menu')){
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-xmark');
                        header.classList.add('scrolled'); 
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