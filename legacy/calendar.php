<?php
include 'db.php';
date_default_timezone_set('Asia/Kolkata');

// Use the same Outreach access setting as the other public pages.
$outreach_state = 'open';
$outreach_settings = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'outreach_status' LIMIT 1");
if ($outreach_settings && $outreach_settings->num_rows > 0) {
    $outreach_state = strtolower(trim((string) $outreach_settings->fetch_assoc()['setting_value']));
}

// അഡ്മിൻ അപ്രൂവ് ചെയ്തതോ നടത്തിയതോ ആയ പ്രോഗ്രാമുകൾ മാത്രം എടുക്കുന്നു
$query = "SELECT b.*, u.name as wing_name 
          FROM bookings b 
          JOIN portal_users u ON b.user_id = u.id 
          WHERE b.status IN ('Approved', 'Conducted') 
          ORDER BY b.prog_date ASC, b.prog_time ASC";
$result = $conn->query($query);

$all_events = [];
$events_by_date = [];
$today_events = [];
$today_date = date('Y-m-d');

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $all_events[] = $row;
        // തീയതി അനുസരിച്ച് ഗ്രൂപ്പ് ചെയ്യുന്നു (JS കലണ്ടറിന് വേണ്ടി)
        $events_by_date[$row['prog_date']][] = $row;
        
        // ഇന്നത്തെ പ്രോഗ്രാമുകൾ
        if ($row['prog_date'] == $today_date) {
            $today_events[] = $row;
        }
    }
}

// JS ലേക്ക് ഡാറ്റ പാസ്സ് ചെയ്യാൻ JSON ആക്കുന്നു
$events_json = json_encode($events_by_date, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
// PDF report uses the same approved/conducted programme records shown on this page.
$report_events_json = json_encode($all_events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Calendar - NUS'HA</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/brand.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Reliable PDF generator: reports are drawn page-by-page, not as one long HTML screenshot. -->
    <script src="/load-pdf.js" defer></script>
    <link rel="stylesheet" href="footer.css">

    <style>
        body { 
            background-color: #f4f7f9; 
            width: 100%; 
            overflow-x: hidden; 
        }
        
        /* Today's Programmes Box */
        .today-box { background: linear-gradient(135deg, #0b1c3c 0%, #1e3a8a 100%); border-radius: 20px; padding: 30px; color: white; margin-bottom: 40px; box-shadow: 0 15px 35px rgba(11, 28, 60, 0.15); position: relative; overflow: hidden; }
        .today-box::before { content: ''; position: absolute; top: -50%; right: -10%; width: 300px; height: 300px; background: rgba(251, 188, 5, 0.1); border-radius: 50%; animation: pulseBg 4s infinite alternate; }
        .today-box h2 { font-weight: 900; margin-top: 0; display: flex; align-items: center; gap: 10px; color: #fbbc05; }
        .today-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; position: relative; z-index: 2; }
        .today-card { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.2); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .today-card:hover { transform: translateY(-8px) scale(1.02); background: rgba(255, 255, 255, 0.15); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .t-wing { font-size: 11px; background: #fbbc05; color: #0b1c3c; padding: 3px 10px; border-radius: 15px; font-weight: 800; text-transform: uppercase; display: inline-block; margin-bottom: 10px; }
        .t-title { font-size: 18px; font-weight: 800; margin: 0 0 10px 0; }
        .t-details { font-size: 13px; color: #cbd5e1; display: flex; align-items: center; gap: 15px; }

        /* Calendar Split Section */
        .calendar-split { display: grid; grid-template-columns: 1.2fr 1fr; gap: 30px; align-items: start; }
        @media (max-width: 900px) { .calendar-split { grid-template-columns: 1fr; } }
        
        /* Calendar UI */
        .cal-wrapper { background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
        .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .cal-nav-btn { background: #f1f5f9; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; color: #0b1c3c; font-size: 16px; transition: 0.3s; display: flex; align-items: center; justify-content: center; }
        .cal-nav-btn:hover { background: #3b82f6; color: white; transform: scale(1.1); }
        .cal-month-year { font-size: 20px; font-weight: 900; color: #0b1c3c; text-transform: uppercase; animation: fadeIn 0.5s ease; }
        
        .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; text-align: center; }
        .cal-day-name { font-weight: 800; color: #64748b; font-size: 13px; padding-bottom: 10px; }
        .cal-cell { aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8fafc; border-radius: 12px; font-weight: 700; font-size: 15px; color: #0b1c3c; cursor: pointer; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; border: 2px solid transparent; animation: popIn 0.5s ease forwards; opacity: 0; }
        .cal-cell:hover:not(.empty) { background: #eff6ff; border-color: #3b82f6; transform: scale(1.08); z-index: 2; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.2); }
        .cal-cell.empty { background: transparent; cursor: default; }
        .cal-cell.active-date { background: #3b82f6; color: white; box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4); border-color: #3b82f6; transform: scale(1.1); z-index: 3; }
        .cal-cell.today { border-color: #fbbc05; color: #b45309; }
        
        .event-dot { width: 6px; height: 6px; background: #ef4444; border-radius: 50%; position: absolute; bottom: 8px; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2); animation: pulseDot 2s infinite; }
        .active-date .event-dot { background: #fff; box-shadow: none; animation: none; }

        /* Selected Day Details Box */
        .details-wrapper { background: #fff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); height: 100%; min-height: 400px; overflow: hidden;}
        .details-header { border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
        .d-date { font-size: 18px; font-weight: 800; color: #0b1c3c; }
        
        .event-list { display: flex; flex-direction: column; gap: 15px; }
        .event-item { padding: 15px; border-left: 4px solid #3b82f6; background: #f8fafc; border-radius: 0 8px 8px 0; animation: slideInRight 0.4s ease forwards; opacity: 0; transform: translateX(20px); }
        .event-item.Conducted { border-left-color: #10b981; }
        .e-time { font-size: 12px; font-weight: 800; color: #3b82f6; margin-bottom: 5px; display: flex; align-items: center; gap: 5px; }
        .Conducted .e-time { color: #10b981; }
        .e-title { font-size: 16px; font-weight: 800; color: #0b1c3c; margin: 0 0 5px 0; }
        .e-meta { font-size: 12px; color: #64748b; font-weight: 600; display: flex; gap: 10px; }
        .empty-msg { text-align: center; color: #94a3b8; font-weight: 600; padding: 40px 0; animation: fadeIn 0.5s ease; }
        .empty-msg i { font-size: 40px; margin-bottom: 15px; color: #cbd5e1; display: block; }

        .pdf-btn { background: #ef4444; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: 0.3s; font-family: 'Montserrat'; font-size: 14px; animation: floatBtn 3s ease-in-out infinite; }
        .pdf-btn:hover { background: #dc2626; box-shadow: 0 8px 20px rgba(239,68,68,0.3); transform: translateY(-2px); animation: none; }
        .pdf-btn:disabled { opacity: .7; cursor: wait; animation: none; transform: none; }
        .pdf-download-status { width: 100%; margin: 6px 0 0; color: #dce7f5; font-size: 11px; line-height: 1.6; font-weight: 600; }

        /* =========================================
           PDF REPORT STYLES (Strictly formatted for A4 Portrait)
           ========================================= */
        #pdf-report-container { display: none; } 
        .pdf-content-wrapper { padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: white; width: 750px; margin: 0 auto; color: #0b1c3c; }
        .pdf-header { text-align: center; border-bottom: 2px solid #0b1c3c; padding-bottom: 15px; margin-bottom: 15px; }
        .pdf-header h1 { color: #0b1c3c; margin: 0 0 5px 0; font-size: 20px; text-transform: uppercase; font-weight: 900;}
        .pdf-header h2 { color: #64748b; margin: 0; font-size: 14px; }
        .pdf-header p { color: #94a3b8; font-size: 10px; margin-top: 5px; }
        
        .pdf-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .pdf-table th { background: #0b1c3c; color: white; text-align: left; padding: 8px 6px; border: 1px solid #cbd5e1; font-size: 11px; text-transform: uppercase;}
        .pdf-table td { padding: 6px; border: 1px solid #cbd5e1; font-size: 10px; vertical-align: top; word-wrap: break-word; }
        .pdf-table tr { page-break-inside: avoid; }
        
        .status-Conducted { color: #10b981; font-weight: bold; }
        .status-Approved { color: #f59e0b; font-weight: bold; }

        /* Portal Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 28, 60, 0.8); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 25px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #ef4444; transform: rotate(90deg); }
        .login-tabs { display: flex; margin-bottom: 25px; background: #f1f5f9; border-radius: 10px; padding: 5px; }
        .tab-btn { flex: 1; padding: 10px; border: none; background: transparent; font-family: 'Montserrat', sans-serif; font-weight: 700; font-size: 14px; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.3s; }
        .tab-btn.active { background: #fff; color: #0b1c3c; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .login-form { display: none; }
        .login-form.active-form { display: block; animation: slideUp 0.4s ease; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: #0b1c3c; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px; font-family: 'Montserrat', sans-serif; font-size: 14px; color: #0b1c3c; outline: none; transition: 0.3s; box-sizing: border-box; }
        .form-group input:focus { border-color: #fbbc05; background: #fffdf0; }
        .submit-btn { width: 100%; padding: 14px; background: #0b1c3c; color: #fff; border: none; border-radius: 10px; font-family: 'Montserrat', sans-serif; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .submit-btn:hover { background: #fbbc05; color: #0b1c3c; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(251, 188, 5, 0.3); }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.5); } 100% { opacity: 1; transform: scale(1); } }
        @keyframes slideInRight { to { opacity: 1; transform: translateX(0); } }
        @keyframes pulseBg { 0% { transform: scale(1); } 100% { transform: scale(1.1); } }
        @keyframes pulseDot { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        @keyframes floatBtn { 0% { transform: translateY(0px); } 50% { transform: translateY(-4px); box-shadow: 0 8px 15px rgba(239, 68, 68, 0.2); } 100% { transform: translateY(0px); } }

        /* =========================================
           MOBILE MENU & OVERRIDES
           ========================================= */
        
        /* Hidden Mobile Menu Button on Desktop */
        .mobile-menu-btn {
            display: none;
            background: transparent;
            border: none;
            font-size: 28px;
            color: #0b1c3c;
            cursor: pointer;
            transition: 0.3s;
        }
        .mobile-portal-li { display: none; }

        @media (max-width: 768px) {
            .today-grid { grid-template-columns: 1fr; }
            .calendar-split { grid-template-columns: 1fr; }
            .today-card, .event-item { height: auto; word-wrap: break-word; }
            
            /* Mobile Header Layout (Hamburger Support) */
            #main-header { 
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 15px 20px !important;
                position: relative; 
            }
            
            .mobile-menu-btn { display: block; }
            .desktop-portal-btn { display: none !important; }
            .mobile-portal-li { display: block !important; border-bottom: none !important; }
            
            /* Mobile Nav Dropdown */
            #main-nav {
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
            }
            
            #main-nav.nav-active {
                display: block !important;
                animation: slideDown 0.4s ease forwards;
            }
            
            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            #main-nav ul { 
                display: flex !important; 
                flex-direction: column !important;
                gap: 0 !important;
                margin: 0; padding: 0;
            }
            
            #main-nav ul li { 
                width: 100%;
                border-bottom: 1px solid #f1f5f9;
            }
            
            #main-nav ul li a { 
                display: block;
                padding: 15px 0;
                font-size: 16px;
                font-weight: 700;
                color: #0b1c3c !important;
                text-align: center;
            }
            
            /* Styled Portal Button in Mobile Menu */
            .mobile-portal-btn {
                background: #fbbc05 !important;
                color: #02032c !important;
                border-radius: 10px;
                margin-top: 10px;
                display: inline-block;
                width: auto;
                padding: 12px 30px !important;
            }
            .mobile-portal-btn:hover {
                background: #e4dfd2 !important;
                color: #0b1c3c !important;
            }
        }
        
        /* Ensure Logo Image and Text are Strictly Black */
        .logo-icon { 
            object-fit: contain; 
            filter: brightness(0) !important; 
        }
        .logo, .logo-text, .logo-title, .logo-sub, .logo * { 
            color: #000000 !important; 
            text-decoration: none !important; 
        }
        
        ul.f-links li a.open-portal-modal { background: none !important; padding: 0 !important; border: none !important; box-shadow: none !important; display: inline !important; color: inherit; }
    </style>
</head>
<body>

    <!-- Header -->
    <header id="main-header" class="scrolled fade-in">
        <div class="logo">
            <span class="nusha-mark" aria-hidden="true"></span>
            <div class="logo-text">
                <span class="logo-title">NUS'HA</span>
                <span class="logo-sub">Noorul hidaya union for<br>heterogeneous activities</span>
            </div>
        </div>
        
        <!-- Hamburger Menu Button -->
        <button class="mobile-menu-btn" id="mobile-menu-btn">
            <i class="fa-solid fa-bars"></i>
        </button>

        <nav id="main-nav">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="leadership.php">Leadership</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="calendar.php" class="active">Event Calendar</a></li>
                <?php if ($outreach_state === 'open'): ?>
                    <li><a href="outreach.php">Outreach</a></li>
                <?php endif; ?>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                
                <!-- Portal Link inside mobile menu -->
                <li class="mobile-portal-li">
                    <a href="#" class="portal-btn open-portal-modal mobile-portal-btn">Portal Login</a>
                </li>
            </ul>
        </nav>
        <a href="#" class="portal-btn open-portal-modal desktop-portal-btn">Portal</a>
    </header>

    <main class="inner-page-content" style="padding-top: 100px;">
        
        <div class="page-title-box fade-in">
            <h1>Event Calendar</h1>
            <div class="yellow-line-center"></div>
            <p>Track all upcoming and conducted programmes across various wings.</p>
        </div>

        <section style="padding: 0 5%; max-width: 1200px; margin: 20px auto 100px;">
            
            <!-- Today's Programmes Section -->
            <div class="today-box scroll-anim reveal-up">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                    <h2><i class="fa-solid fa-bolt" style="animation: pulseDot 2s infinite;"></i> Today's Programmes</h2>
                    <!-- PDF Generate Button -->
                    <button onclick="generatePDF()" class="pdf-btn"><i class="fa-solid fa-download"></i> Download Report PDF</button>
                </div>
                <p id="pdf-download-status" class="pdf-download-status" role="status" aria-live="polite"></p>
                
                <div class="today-grid">
                    <?php
                    if (count($today_events) > 0) {
                        $anim_delay = 0;
                        foreach ($today_events as $t) {
                            $stat_col = ($t['status'] == 'Conducted') ? '#10b981' : '#fbbc05';
                            echo '<div class="today-card" style="animation: popIn 0.5s ease forwards; animation-delay: '.$anim_delay.'s; opacity:0;">
                                    <span class="t-wing">'.$t['wing_name'].'</span>
                                    <h3 class="t-title">'.$t['title'].'</h3>
                                    <div class="t-details">
                                        <span><i class="fa-regular fa-clock" style="color:'.$stat_col.';"></i> '.$t['session_time'].' ('.$t['prog_time'].')</span>
                                        <span><i class="fa-solid fa-layer-group" style="color:'.$stat_col.';"></i> '.$t['category'].'</span>
                                        <span style="color:'.$stat_col.'; font-weight:800; margin-left:auto;">'.$t['status'].'</span>
                                    </div>
                                  </div>';
                            $anim_delay += 0.1;
                        }
                    } else {
                        echo '<div style="grid-column: 1 / -1; padding: 20px 0; color: #cbd5e1; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                                <i class="fa-solid fa-mug-hot" style="font-size: 24px;"></i> No programmes scheduled for today. Relax!
                              </div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Interactive Calendar Split -->
            <div class="calendar-split scroll-anim reveal-up delay-100">
                
                <!-- Left: Calendar Grid -->
                <div class="cal-wrapper">
                    <div class="cal-header">
                        <button class="cal-nav-btn" onclick="prevMonth()"><i class="fa-solid fa-chevron-left"></i></button>
                        <div class="cal-month-year" id="month-year-display">Loading...</div>
                        <button class="cal-nav-btn" onclick="nextMonth()"><i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    
                    <div class="cal-grid">
                        <div class="cal-day-name">Sun</div><div class="cal-day-name">Mon</div><div class="cal-day-name">Tue</div>
                        <div class="cal-day-name">Wed</div><div class="cal-day-name">Thu</div><div class="cal-day-name">Fri</div><div class="cal-day-name">Sat</div>
                    </div>
                    
                    <div class="cal-grid" id="calendar-days">
                        <!-- JS will inject days here -->
                    </div>
                </div>

                <!-- Right: Selected Day Details -->
                <div class="details-wrapper">
                    <div class="details-header">
                        <span style="font-size: 12px; font-weight: 800; color: #3b82f6; text-transform: uppercase; letter-spacing: 1px;">Selected Date</span>
                        <div class="d-date" id="selected-date-display">Select a date</div>
                    </div>
                    
                    <div class="event-list" id="day-events-list">
                        <div class="empty-msg"><i class="fa-regular fa-hand-pointer" style="animation: floatBtn 2s infinite;"></i> Select a highlighted date from the calendar to view programmes.</div>
                    </div>
                </div>

            </div>
        </section>
    </main>

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

            <!-- Wing Login -->
            <div id="wing-login" class="login-form active-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=wing">Continue to Wing Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>

            <!-- Class Login -->
            <div id="class-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=class">Continue to Class Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>

            <!-- Student Login -->
            <div id="student-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=student">Continue to Student Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>
            <div id="admin-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=admin">Continue to Admin Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>
        </div>
    </div> 

    <!-- ഫൂട്ടർ -->
    <?php include __DIR__ . '/footer.php'; ?>

    <!-- JavaScript For Interactive Calendar & PDF -->
    <script>
        const eventsData = <?php echo $events_json; ?>;
        
        let currentDate = new Date(); 
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        
        function renderCalendar() {
            const monthYearDisplay = document.getElementById("month-year-display");
            const calendarDays = document.getElementById("calendar-days");
            
            monthYearDisplay.innerText = `${monthNames[currentMonth]} ${currentYear}`;
            calendarDays.innerHTML = "";
            
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            for (let i = 0; i < firstDay; i++) {
                const emptyCell = document.createElement("div");
                emptyCell.classList.add("cal-cell", "empty");
                calendarDays.appendChild(emptyCell);
            }
            
            let animDelay = 0;
            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement("div");
                cell.classList.add("cal-cell");
                cell.innerText = day;
                cell.style.animationDelay = `${animDelay}s`;
                animDelay += 0.02; 
                
                const formattedMonth = String(currentMonth + 1).padStart(2, '0');
                const formattedDay = String(day).padStart(2, '0');
                const dateString = `${currentYear}-${formattedMonth}-${formattedDay}`;
                
                const todayStr = new Date().toISOString().split('T')[0];
                if (dateString === todayStr) { cell.classList.add("today"); }
                
                if (eventsData[dateString]) {
                    const dot = document.createElement("div");
                    dot.classList.add("event-dot");
                    cell.appendChild(dot);
                }
                
                cell.addEventListener("click", function() {
                    document.querySelectorAll(".cal-cell").forEach(c => c.classList.remove("active-date"));
                    cell.classList.add("active-date");
                    showDayEvents(dateString, `${day} ${monthNames[currentMonth]} ${currentYear}`);
                });
                
                calendarDays.appendChild(cell);
            }
        }
        
        function showDayEvents(dateString, displayDateText) {
            document.getElementById("selected-date-display").innerText = displayDateText;
            const listContainer = document.getElementById("day-events-list");
            listContainer.innerHTML = "";
            
            if (eventsData[dateString]) {
                let listDelay = 0;
                eventsData[dateString].forEach(ev => {
                    const icon = (ev.status === 'Conducted') ? 'fa-solid fa-check-circle' : 'fa-regular fa-clock';
                    const itemHTML = `
                        <div class="event-item ${ev.status}" style="animation-delay: ${listDelay}s;">
                            <div class="e-time"><i class="${icon}"></i> ${ev.session_time} (${ev.prog_time})</div>
                            <h4 class="e-title">${ev.title}</h4>
                            <div class="e-meta">
                                <span><i class="fa-solid fa-users"></i> ${ev.wing_name}</span>
                                <span><i class="fa-solid fa-tag"></i> ${ev.category} (${ev.stage_type})</span>
                            </div>
                        </div>
                    `;
                    listContainer.insertAdjacentHTML('beforeend', itemHTML);
                    listDelay += 0.1;
                });
            } else {
                listContainer.innerHTML = `
                    <div class="empty-msg">
                        <i class="fa-regular fa-calendar-xmark" style="animation: pulseDot 2s infinite;"></i>
                        No programmes scheduled for this day.
                    </div>
                `;
            }
        }
        
        function prevMonth() { currentMonth--; if(currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(); }
        function nextMonth() { currentMonth++; if(currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(); }
        
        document.addEventListener("DOMContentLoaded", function() {
            renderCalendar();
            
            // Scroll Animations
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('active'); observer.unobserve(entry.target); } });
            }, { threshold: 0.1 });
            document.querySelectorAll('.scroll-anim').forEach((el) => observer.observe(el));

            // Mobile Hamburger Menu Logic
            const mobileBtn = document.getElementById('mobile-menu-btn');
            const mainNav = document.getElementById('main-nav');
            
            if(mobileBtn && mainNav) {
                const menuIcon = mobileBtn.querySelector('i');
                mobileBtn.addEventListener('click', () => {
                    mainNav.classList.toggle('nav-active');
                    if(mainNav.classList.contains('nav-active')) {
                        menuIcon.classList.remove('fa-bars');
                        menuIcon.classList.add('fa-xmark');
                    } else {
                        menuIcon.classList.remove('fa-xmark');
                        menuIcon.classList.add('fa-bars');
                    }
                });
            }

            // Modal Logic
            const portalBtns = document.querySelectorAll('.portal-btn, .open-portal-modal');
            const portalModal = document.getElementById('portalModal');
            const closeModal = document.querySelector('.close-modal');

            if (portalBtns.length > 0 && portalModal) {
                portalBtns.forEach(btn => {
                    btn.addEventListener('click', (e) => { 
                        e.preventDefault(); 
                        portalModal.classList.add('show'); 
                        
                        // Close mobile menu if it is open when modal opens
                        if(mainNav && mainNav.classList.contains('nav-active')){
                            mainNav.classList.remove('nav-active');
                            if(mobileBtn) {
                                const menuIcon = mobileBtn.querySelector('i');
                                menuIcon.classList.remove('fa-xmark');
                                menuIcon.classList.add('fa-bars');
                            }
                        }
                    });
                });
                closeModal.addEventListener('click', () => portalModal.classList.remove('show'));
                window.addEventListener('click', (e) => { if (e.target === portalModal) portalModal.classList.remove('show'); });
            }

            const tabBtns = document.querySelectorAll('.tab-btn');
            const loginForms = document.querySelectorAll('.login-form');

            tabBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabBtns.forEach(b => b.classList.remove('active'));
                    loginForms.forEach(f => f.classList.remove('active-form'));
                    btn.classList.add('active');
                    document.getElementById(btn.getAttribute('data-target')).classList.add('active-form');
                });
            });
        });
    </script>
    <script>window.calendarReportEvents = <?php echo $report_events_json; ?>;</script>
    <script src="calendar-pdf.js" defer></script>
</body>
</html>
