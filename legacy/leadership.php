<?php
include 'db.php'; // ഡാറ്റാബേസ് കണക്ഷൻ

// അഡ്മിൻ പാനലിൽ നിന്ന് Outreach സ്റ്റാറ്റസ് എടുക്കുന്നു
$outreach_state = 'open'; 
$settings_q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='outreach_status'");
if ($settings_q && $settings_q->num_rows > 0) {
    $outreach_state = $settings_q->fetch_assoc()['setting_value'];
}

function leadership_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function leadership_photo($value) {
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $value)) {
        return '';
    }
    if (substr($value, 0, 2) === '//') {
        return '';
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
        return preg_match('~^https?://~i', $value) ? $value : '';
    }
    return $value;
}

$committee_years = [];
$committee_members = [];
$has_unassigned = false;
$selected_year = null;
$committee_error = '';
$committee_label = 'Committee';

try {
    $years_result = $conn->query(
        "SELECT DISTINCT committee_year FROM leadership ORDER BY committee_year DESC"
    );
    if (!$years_result) {
        throw new RuntimeException('Could not read committee years.');
    }
    while ($year_row = $years_result->fetch_assoc()) {
        if ($year_row['committee_year'] === null) {
            $has_unassigned = true;
        } else {
            $committee_years[] = (int) $year_row['committee_year'];
        }
    }
    $years_result->free();

    $requested_year = $_GET['year'] ?? null;
    if ($requested_year === null) {
        $selected_year = $committee_years
            ? $committee_years[0]
            : ($has_unassigned ? 'unassigned' : null);
    } elseif ($requested_year === 'unassigned' && $has_unassigned) {
        $selected_year = 'unassigned';
    } elseif (is_string($requested_year)
        && preg_match('/^[0-9]{4}$/D', $requested_year)
        && in_array((int) $requested_year, $committee_years, true)) {
        $selected_year = (int) $requested_year;
    } else {
        http_response_code(400);
        $committee_error = 'Please select one of the available committee years.';
    }

    if ($selected_year !== null) {
        $committee_label = $selected_year === 'unassigned'
            ? 'Executive Committee — Year not assigned'
            : 'Executive Committee — ' . $selected_year;

        $condition = $selected_year === 'unassigned'
            ? 'committee_year IS NULL'
            : 'committee_year = ?';

        $members_stmt = $conn->prepare(
            "SELECT name, position, photo FROM leadership
             WHERE $condition
             ORDER BY CASE LOWER(TRIM(position))
                 WHEN 'president' THEN 1
                 WHEN 'general secretary' THEN 2
                 WHEN 'treasurer' THEN 3
                 ELSE 4
             END, name ASC"
        );
        if (!$members_stmt) {
            throw new RuntimeException('Could not prepare committee query.');
        }
        if ($selected_year !== 'unassigned') {
            $members_stmt->bind_param('i', $selected_year);
        }
        if (!$members_stmt->execute()) {
            throw new RuntimeException('Could not load committee members.');
        }
        $members_stmt->bind_result($member_name, $member_position, $member_photo);
        while ($members_stmt->fetch()) {
            $committee_members[] = [
                'name' => $member_name,
                'position' => $member_position,
                'photo' => $member_photo
            ];
        }
        $members_stmt->close();
    }
} catch (Throwable $error) {
    error_log('Leadership page: ' . $error->getMessage());
    http_response_code(503);
    $committee_error = 'Committee details are temporarily unavailable. Please try again later.';
    $committee_members = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leadership - NUS'HA</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/brand.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="footer.css">
    
    <style>
        /* =========================================================
           BASE & STRUCTURAL RESET
           ========================================================= */
        html, body { overflow-x: hidden; width: 100%; margin: 0; padding: 0; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: inherit; }

        .header-right { display: flex; align-items: center; gap: 15px; }
        .mobile-menu-toggle { display: none; background: transparent; border: none; font-size: 28px; cursor: pointer; color: #0b1c3c; padding: 5px; transition: 0.3s; outline: none; }
        .mobile-portal-li { display: none; }

        ul.f-links li a.portal-trigger, ul.f-links li a.open-portal-modal { background: none !important; padding: 0 !important; border: none !important; box-shadow: none !important; display: inline !important; color: inherit; }

        .logo { text-decoration: none !important; display: flex; align-items: center; gap: 15px; }
        .logo-icon { object-fit: contain !important; filter: brightness(0) !important; width: auto !important; max-height: 45px !important; }
        .logo, .logo-text, .logo-title, .logo-sub, .logo * { color: #000000 !important; text-decoration: none !important; }

        /* =========================================================
           ✨ UNIQUE MODERN YEAR SELECTOR (TIMELINE PILL TABS) ✨
           ========================================================= */
        .year-selector-container {
            max-width: 950px;
            margin: 35px auto 20px;
            padding: 0 15px;
            text-align: center;
        }

        .year-selector-wrapper {
            background: #ffffff;
            padding: 8px 12px;
            border-radius: 60px;
            box-shadow: 0 10px 30px rgba(11, 28, 60, 0.06);
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            max-width: 100%;
        }

        .year-label-tag {
            font-size: 13px;
            font-weight: 800;
            color: #64748b;
            padding: 8px 14px 8px 16px;
            display: flex;
            align-items: center;
            gap: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-right: 2px solid #f1f5f9;
        }

        .year-pills-list {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .year-pill-link {
            position: relative;
            padding: 10px 22px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            text-decoration: none;
            background: transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1.5px solid transparent;
        }

        .year-pill-link:hover {
            color: #0b1c3c;
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .year-pill-link.active {
            background: #0b1c3c;
            color: #ffffff !important;
            box-shadow: 0 6px 18px rgba(11, 28, 60, 0.22);
            border-color: #0b1c3c;
            transform: translateY(-1px);
        }

        .year-pill-link.active .pill-dot {
            width: 7px;
            height: 7px;
            background-color: #fbbc05;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px #fbbc05;
            animation: pulseDot 1.8s infinite;
        }

        @keyframes pulseDot {
            0% { transform: scale(0.9); opacity: 0.8; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.8; }
        }

        /* SECTION HEADING WITH STYLISH ACCENT */
        .committee-badge-box {
            text-align: center;
            margin: 25px 0 35px;
        }
        .committee-badge-box h2 {
            font-size: 24px;
            font-weight: 900;
            color: #0b1c3c;
            margin: 0 0 8px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }
        .committee-badge-box span.active-tag {
            background: #fffbeb;
            color: #b45309;
            font-size: 13px;
            font-weight: 800;
            padding: 4px 14px;
            border-radius: 20px;
            border: 1px solid #fde68a;
        }

        /* Leader Card Structural Layout */
        .leaders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 30px; justify-content: center; align-items: stretch; }
        .leader-card { 
            height: 100% !important; 
            display: flex !important; 
            flex-direction: column !important; 
            padding: 30px 20px !important; 
            box-sizing: border-box !important; 
            border-radius: 18px !important;
            background: #ffffff;
            box-shadow: 0 8px 25px rgba(0,0,0,0.03);
            border: 1px solid #f1f5f9;
            transition: 0.3s ease;
        }
        .leader-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 15px 35px rgba(11,28,60,0.08);
            border-color: #fbbc05;
        }
        .leader-img-wrapper { margin: 0 auto 20px auto !important; }
        .leader-info { 
            flex-grow: 1 !important; 
            display: flex !important; 
            flex-direction: column !important; 
            justify-content: flex-end !important; 
            text-align: center;
        }
        .leader-info h4 { font-size: 18px; font-weight: 800; color: #0b1c3c; margin: 0 0 6px 0; }
        .leader-info p { font-size: 13px; color: #64748b; font-weight: 600; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }

        .committee-message { grid-column:1 / -1; text-align:center; color:#64748b; padding:35px; background:#fff; border-radius:16px; border: 1px solid #e2e8f0; font-weight:600; }
        .committee-photo { position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#e2e8f0; color:#64748b; width:130px; height:130px; border-radius:50%; border:3px solid #fbbc05; box-shadow:0 4px 15px rgba(0,0,0,0.08); }
        .committee-photo img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
        .committee-photo .fa-user { font-size:48px; }

        /* =========================================================
           MOBILE VIEW STRICT FIXES
           ========================================================= */
        @media (max-width: 768px) {
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
            nav#navbar.active-menu { display: block !important; animation: slideDown 0.4s ease forwards; }
            @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
            nav#navbar ul { flex-direction: column !important; align-items: stretch !important; gap: 0 !important; margin: 0; padding: 0; }
            nav#navbar ul li { width: 100%; border-bottom: 1px solid #f1f5f9; text-align: center; }
            nav#navbar ul li a { display: block; padding: 15px 0; font-size: 16px; font-weight: 700; color: #0b1c3c !important; }
            
            .mobile-portal-btn { background: #0b1c3c !important; color: #fff !important; border-radius: 10px; margin-top: 10px; display: inline-block !important; width: auto; padding: 12px 30px !important; }
            .mobile-portal-btn:hover { background: #fbbc05 !important; color: #0b1c3c !important; }

            .inner-page-content { padding: 40px 20px !important; width: 100vw !important; box-sizing: border-box !important; }
            .page-title-box h1 { font-size: 2.2rem !important; line-height: 1.2 !important; margin-bottom: 10px !important; }
            
            /* Responsive Year Selector Mobile */
            .year-selector-wrapper {
                border-radius: 20px;
                padding: 10px;
                width: 100%;
                flex-direction: column;
                gap: 12px;
            }
            .year-label-tag { border-right: none; border-bottom: 2px solid #f1f5f9; padding-bottom: 8px; width: 100%; justify-content: center; }
            .year-pills-list { width: 100%; justify-content: center; overflow-x: auto; padding-bottom: 5px; }
            .year-pill-link { padding: 8px 16px; font-size: 13px; }

            .leaders-grid { grid-template-columns: 1fr !important; gap: 20px !important; }
            
            .main-footer { padding: 40px 20px !important; width: 100vw !important; box-sizing: border-box !important; }
            .footer-container { display: flex !important; flex-direction: column !important; text-align: center !important; gap: 35px !important; }
            .footer-logo, .social-icons, .f-bottom-links { justify-content: center !important; }
            .c-item { flex-direction: column !important; justify-content: center !important; align-items: center !important; gap: 8px !important; text-align: center !important; }
        }

        /* PORTAL LOGIN MODAL */
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
<body style="background-color: #f8fafc;">

    <!-- നാവിഗേഷൻ ബാർ -->
    <header id="main-header" class="scrolled fade-in">
        <a href="index.php" class="logo">
            <span class="nusha-mark" aria-hidden="true">NH</span>
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
                <li><a href="leadership.php" class="active">Leadership</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="calendar.php">Event Calendar</a></li>
                
                <?php if($outreach_state == 'open') { ?>
                <li><a href="outreach.php">Outreach</a></li>
                <?php } ?>
                
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                
                <!-- Portal Link inside mobile menu -->
                <li class="mobile-portal-li">
                    <a href="#" class="portal-btn portal-trigger mobile-portal-btn">Portal Login</a>
                </li>
            </ul>
        </nav>

        <div class="header-right desktop-portal-btn">
            <a href="#" class="portal-btn portal-trigger">Portal</a>
        </div>
    </header>

    <!-- Leadership പേജിന്റെ ഉള്ളടക്കം -->
    <main class="inner-page-content" style="padding-top: 100px;">
        <div class="page-title-box fade-in">
            <h1>Our Leadership</h1>
            <div class="yellow-line-center"></div>
            <p>Meet the dedicated team guiding NUS'HA towards excellence.</p>
        </div>

        <!-- ✨ UNIQUE MODERN TIMELINE YEAR SELECTOR ✨ -->
        <?php if ($committee_years || $has_unassigned): ?>
        <div class="year-selector-container fade-in">
            <div class="year-selector-wrapper">
                <div class="year-label-tag">
                    <i class="fa-solid fa-calendar-check" style="color: #fbbc05;"></i> Academic Session
                </div>
                <div class="year-pills-list">
                    <?php foreach ($committee_years as $year): 
                        $isActive = ($selected_year === $year);
                    ?>
                        <a href="leadership.php?year=<?= leadership_escape($year) ?>" 
                           class="year-pill-link <?= $isActive ? 'active' : '' ?>">
                            <?php if ($isActive): ?><span class="pill-dot"></span><?php endif; ?>
                            <span><?= leadership_escape($year) ?></span>
                        </a>
                    <?php endforeach; ?>

                    <?php if ($has_unassigned): 
                        $isUnassignedActive = ($selected_year === 'unassigned');
                    ?>
                        <a href="leadership.php?year=unassigned" 
                           class="year-pill-link <?= $isUnassignedActive ? 'active' : '' ?>">
                            <?php if ($isUnassignedActive): ?><span class="pill-dot"></span><?php endif; ?>
                            <span>Other / Unassigned</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION HEADING -->
        <div class="committee-badge-box">
            <h2>
                <span><?= leadership_escape($committee_label) ?></span>
                <?php if (is_int($selected_year)): ?>
                    <span class="active-tag"><i class="fa-solid fa-sparkles"></i> Active Batch</span>
                <?php endif; ?>
            </h2>
        </div>

        <section class="leadership-sec" style="margin-top: 10px;" aria-labelledby="committee-heading">
            <div class="leaders-grid">
                <?php if ($committee_error !== ''): ?>
                    <p class="committee-message" role="alert"><?= leadership_escape($committee_error) ?></p>
                <?php elseif (!$committee_members): ?>
                    <p class="committee-message">No committee records found<?= is_int($selected_year) ? ' for ' . leadership_escape($selected_year) : '' ?>.</p>
                <?php else: ?>
                    <?php foreach ($committee_members as $row):
                        $photo = leadership_photo($row['photo']);
                    ?>
                    <div class="leader-card">
                        <div class="leader-img-wrapper">
                            <div class="leader-img committee-photo">
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                                <?php if ($photo !== ''): ?>
                                    <img src="<?= leadership_escape($photo) ?>"
                                         alt="<?= leadership_escape($row['name']) ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none'">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="leader-info">
                            <h4><?= leadership_escape($row['name']) ?></h4>
                            <p><?= leadership_escape($row['position']) ?></p>
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
    <div id="portalModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal"><i class="fa-solid fa-xmark"></i></span>
            <div style="text-align:center; margin-bottom:20px;">
                <h2 style="color:#0b1c3c; margin:0 0 5px 0; font-weight:900;">Portal Login</h2>
                <p style="color:#64748b; font-size:13px; margin:0; font-weight:600;">Select your account type to continue</p>
            </div>
            
            <div class="login-tabs">
                <button class="tab-btn active" data-target="wing-login">Wing</button>
                <button class="tab-btn" data-target="class-login">Class</button>
                <button class="tab-btn" data-target="student-login">Student</button>
                <button class="tab-btn" data-target="admin-login">Admin</button>
            </div>

            <!-- Wing Login Form -->
            <div id="wing-login" class="login-form active-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=wing">Continue to Wing Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>

            <!-- Class Login Form -->
            <div id="class-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=class">Continue to Class Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>

            <!-- Student Login Form -->
            <div id="student-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=student">Continue to Student Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>
            <div id="admin-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=admin">Continue to Admin Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>
        </div>
    </div> 

    <!-- സ്ക്രോൾ അനിമേഷൻ & ജാവാസ്ക്രിപ്റ്റ് -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Scroll Reveal Observer
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) { entry.target.classList.add('active'); observer.unobserve(entry.target); }
                });
            }, { threshold: 0.1 });
            document.querySelectorAll('.scroll-anim').forEach((el) => observer.observe(el));

            // Header Scroll Effect
            const header = document.getElementById('main-header');
            if(header) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 50) header.classList.add('scrolled');
                    else header.classList.remove('scrolled');
                });
            }

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

            // Portal Login Popup Logic
            const portalBtns = document.querySelectorAll('.portal-trigger');
            const portalModal = document.getElementById('portalModal');
            const closeModal = document.querySelector('.close-modal');

            if (portalBtns.length > 0 && portalModal) {
                portalBtns.forEach(btn => {
                    btn.addEventListener('click', (e) => { 
                        e.preventDefault(); 
                        portalModal.classList.add('show'); 
                        if(navbar && navbar.classList.contains('active-menu')) menuBtn.click(); 
                    });
                });
                closeModal.addEventListener('click', () => portalModal.classList.remove('show'));
                window.addEventListener('click', (e) => { 
                    if (e.target === portalModal) portalModal.classList.remove('show'); 
                });
            }

            // ലോഗിൻ ടാബ് മാറ്റാനുള്ള കോഡ്
            const tabBtns = document.querySelectorAll('.tab-btn');
            const loginForms = document.querySelectorAll('.login-form');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabBtns.forEach(b => b.classList.remove('active'));
                    loginForms.forEach(f => f.classList.remove('active-form'));
                    
                    btn.classList.add('active');
                    const targetForm = document.getElementById(btn.getAttribute('data-target'));
                    if(targetForm) targetForm.classList.add('active-form');
                });
            });
        });
    </script>
</body>
</html> 