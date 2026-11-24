<?php
require 'db.php';

// Optional: set an existing DATE/DATETIME/TIMESTAMP column here.
// Empty means auto-detect a submission date column. Monthly rankings use
// submission dates, not the date on which marks were edited.
$RANKING_DATE_COLUMN = '';
$ranking_timezone = new DateTimeZone('Asia/Kolkata');
$ranking_now = new DateTimeImmutable('now', $ranking_timezone);
$outreach_state = 'open';
try {
    $settings_q = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='outreach_status'");
    if ($settings_q && $settings_q->num_rows > 0) {
        $outreach_state = $settings_q->fetch_assoc()['setting_value'];
    }
} catch (Throwable $error) {
    error_log('Ranking settings: ' . $error->getMessage());
}

function ranking_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function ranking_assign_ranks($rows) {
    $previous = null;
    $rank = 0;
    foreach ($rows as $index => &$row) {
        $points = (float) $row['points'];
        if ($previous === null || $points !== $previous) {
            $rank = $index + 1;
        }
        $row['points'] = $points;
        $row['entries'] = (int) $row['entries'];
        $row['rank'] = $rank;
        $previous = $points;
    }
    unset($row);
    return $rows;
}
function ranking_load($conn, $date_column = null, $start = null, $end = null) {
    $date_filter = '';
    if ($date_column !== null) {
        // The identifier is checked against SHOW COLUMNS before use.
        $identifier = 's.' . chr(96) . $date_column . chr(96);
        $date_filter = " AND $identifier >= ? AND $identifier < ?";
    }
    $statement = $conn->prepare(
        "SELECT u.id, u.name, u.user_type, COALESCE(SUM(s.marks), 0) AS points,
                COUNT(s.user_id) AS entries
         FROM portal_users u
         LEFT JOIN submissions s ON u.id = s.user_id AND s.is_judged = 1 $date_filter
         WHERE u.user_type IN ('Class', 'Wing')
         GROUP BY u.id, u.name, u.user_type
         ORDER BY u.user_type ASC, points DESC, u.name ASC, u.id ASC"
    );
    if (!$statement) {
        throw new RuntimeException('Ranking query could not be prepared.');
    }
    if ($date_column !== null) {
        $statement->bind_param('ss', $start, $end);
    }
    if (!$statement->execute()) {
        throw new RuntimeException('Ranking query failed.');
    }
    $statement->bind_result($id, $name, $type, $points, $entries);
    $groups = ['Class' => [], 'Wing' => []];
    while ($statement->fetch()) {
        $groups[$type][] = [
            'id' => (int) $id, 'name' => (string) $name,
            'points' => $points, 'entries' => $entries
        ];
    }
    $statement->close();
    foreach ($groups as $type => $rows) {
        $groups[$type] = ranking_assign_ranks($rows);
    }
    return $groups;
}

$total_data = ['Class' => [], 'Wing' => []];
$monthly_data = ['Class' => [], 'Wing' => []];
$total_error = false;
$monthly_ready = false;
$monthly_message = '';
$undated_count = 0;
$date_column = null;
$available_months = [];
$month_notice = '';
$active_type = isset($_GET['type']) && $_GET['type'] === 'Wing' ? 'Wing' : 'Class';

try {
    $total_data = ranking_load($conn);
} catch (Throwable $error) {
    error_log('Overall rankings: ' . $error->getMessage());
    $total_error = true;
}

try {
    $columns = [];
    $column_query = $conn->query('SHOW COLUMNS FROM submissions');
    if (!$column_query) {
        throw new RuntimeException('Submission date columns could not be read.');
    }
    while ($column = $column_query->fetch_assoc()) {
        if (preg_match('/^(date|datetime|timestamp)(?:\(|$)/i', $column['Type'])) {
            $columns[$column['Field']] = true;
        }
    }
    $column_query->free();
    if ($RANKING_DATE_COLUMN !== '') {
        if (!isset($columns[$RANKING_DATE_COLUMN])) {
            throw new RuntimeException('Configured ranking date column is missing or not a date type.');
        }
        $date_column = $RANKING_DATE_COLUMN;
    } else {
        foreach (['ranking_date', 'submission_date', 'submitted_at', 'created_at',
                  'date_submitted', 'submitted_on', 'created_on', 'date'] as $candidate) {
            if (isset($columns[$candidate])) {
                $date_column = $candidate;
                break;
            }
        }
    }
    if ($date_column !== null) {
        $identifier = 's.' . chr(96) . $date_column . chr(96);
        $months_query = $conn->query(
            "SELECT DISTINCT DATE_FORMAT($identifier, '%Y-%m') AS score_month
             FROM submissions s
             INNER JOIN portal_users u ON u.id = s.user_id
             WHERE s.is_judged = 1 AND u.user_type IN ('Class', 'Wing')
             AND $identifier >= '1000-01-01' AND $identifier < '9999-01-01'
             ORDER BY score_month DESC"
        );
        if (!$months_query) {
            throw new RuntimeException('Ranking months could not be read.');
        }
        while ($month_row = $months_query->fetch_assoc()) {
            $available_months[] = $month_row['score_month'];
        }
        $months_query->free();
        $undated_query = $conn->query(
            "SELECT COUNT(*) AS missing_dates FROM submissions s
             INNER JOIN portal_users u ON u.id = s.user_id
             WHERE s.is_judged = 1 AND u.user_type IN ('Class', 'Wing')
             AND ($identifier IS NULL OR $identifier < '1000-01-01' OR $identifier >= '9999-01-01')"
        );
        if (!$undated_query) {
            throw new RuntimeException('Undated submissions could not be counted.');
        }
        $undated_count = (int) $undated_query->fetch_assoc()['missing_dates'];
    } else {
        $monthly_message = 'Monthly rankings will be available once submission dates are recorded.';
    }
} catch (Throwable $error) {
    error_log('Monthly ranking setup: ' . $error->getMessage());
    $monthly_message = 'Monthly rankings are temporarily unavailable. Overall points are shown separately.';
    $date_column = null;
}

$selected_month = $available_months ? $available_months[0] : $ranking_now->format('Y-m');
if (isset($_GET['month'])) {
    $requested = $_GET['month'];
    if (is_string($requested) && preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $requested)
        && (int) substr($requested, 0, 4) >= 1000 && (int) substr($requested, 0, 4) <= 9998) {
        $selected_month = $requested;
    } else {
        http_response_code(400);
        $month_notice = 'Invalid month. Please choose a month from the selector.';
    }
}
$month_date = DateTimeImmutable::createFromFormat('!Y-m', $selected_month, $ranking_timezone);
$selected_month_label = $month_date->format('F Y');
$month_start = $month_date->format('Y-m-d');
$month_end = $month_date->modify('+1 month')->format('Y-m-d');
$available_months[] = $ranking_now->format('Y-m');
$available_months[] = $selected_month;
$available_months = array_values(array_unique($available_months));
rsort($available_months, SORT_STRING);

if ($date_column !== null) {
    try {
        $monthly_data = ranking_load($conn, $date_column, $month_start, $month_end);
        $monthly_ready = true;
    } catch (Throwable $error) {
        error_log('Monthly rankings: ' . $error->getMessage());
        $monthly_message = 'The selected month could not be loaded. Please try again later.';
    }
}
$ranking_payload = [
    'total' => $total_data, 'monthly' => $monthly_data,
    'monthlyReady' => $monthly_ready, 'monthlyMessage' => $monthly_message,
    'totalError' => $total_error, 'month' => $selected_month,
    'monthLabel' => $selected_month_label, 'type' => $active_type,
    'undatedCount' => $undated_count, 'generated' => $ranking_now->format('d M Y, h:i A') . ' IST'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Monthly &amp; Overall Rankings - NUS'HA</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/brand.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- ഗ്രാഫ് വരയ്ക്കാൻ Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js" defer></script>
    <script src="/load-pdf.js" defer></script>
    <link rel="stylesheet" href="footer.css">

    <style>
        /* നിങ്ങളുടെ ഒറിജിനൽ ഡിസൈൻ കോഡുകൾ - മാറ്റം വരുത്തിയിട്ടില്ല */
        /* Top 3 Podium Design (For Classes) */
        .podium-container { display: flex; justify-content: center; align-items: flex-end; gap: 20px; margin-top: 40px; margin-bottom: 70px; height: 260px; }
        .podium-box { width: 140px; text-align: center; border-radius: 16px 16px 0 0; box-shadow: 0 -10px 25px rgba(0,0,0,0.06); display: flex; flex-direction: column; justify-content: flex-start; padding-top: 35px; position: relative; transition: 0.3s; }
        .podium-box:hover { transform: translateY(-5px); }
        
        .podium-1 { height: 100%; border: 3px solid #fbbc05; border-bottom: none; background: linear-gradient(to top, #fffbeb, #fff); z-index: 3; }
        .podium-2 { height: 75%; border: 3px solid #94a3b8; border-bottom: none; background: linear-gradient(to top, #f8fafc, #fff); z-index: 2; }
        .podium-3 { height: 60%; border: 3px solid #b45309; border-bottom: none; background: linear-gradient(to top, #fff7ed, #fff); z-index: 1; }
        
        .p-rank { width: 45px; height: 45px; border-radius: 50%; background: #0b1c3c; color: white; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 20px; position: absolute; top: -22px; left: 50%; transform: translateX(-50%); box-shadow: 0 5px 15px rgba(0,0,0,0.15); border: 3px solid #fff; }
        .podium-1 .p-rank { background: #fbbc05; color: #0b1c3c; width: 60px; height: 60px; top: -30px; font-size: 26px; }
        .podium-2 .p-rank { background: #94a3b8; }
        .podium-3 .p-rank { background: #b45309; }
        
        .p-class { font-weight: 800; color: #0b1c3c; font-size: 18px; margin-bottom: 5px; }
        .p-pts { font-weight: 800; color: #10b981; font-size: 15px; background: rgba(16, 185, 129, 0.1); padding: 4px 10px; border-radius: 20px; display: inline-block; }

        /* Two Column Grid for Wing and Class tables */
        .ranking-split { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: start; margin-top: 30px; }
        @media (max-width: 900px) { .ranking-split { grid-template-columns: 1fr; } }

        .ranking-wrapper { background: #fff; padding: 35px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.04); border: 1px solid #f1f5f9; }
        .table-responsive { overflow-x: auto; }
        .rank-table { width: 100%; border-collapse: collapse; }
        .rank-table th { background: #0b1c3c; color: #fff; padding: 15px; text-align: left; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; text-transform: uppercase; }
        .rank-table th:first-child { border-radius: 12px 0 0 0; text-align: center; }
        .rank-table th:last-child { border-radius: 0 12px 0 0; }
        .rank-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 600; font-size: 14px; transition: 0.2s; }
        .rank-table tr:hover td { background: #f8fafc; }
        .rank-table td:first-child { text-align: center; font-weight: 900; font-size: 16px; }
        
        .rank-1 td { background: #fffdf0; } .rank-1 td:first-child { color: #f59e0b; font-size: 20px; }
        .rank-2 td { background: #f8fafc; } .rank-2 td:first-child { color: #94a3b8; font-size: 18px; }
        .rank-3 td { background: #fff7ed; } .rank-3 td:first-child { color: #b45309; font-size: 18px; }
        
        .total-pts { font-weight: 900; color: #3b82f6; font-size: 16px; }
        .rank-1 .total-pts { color: #059669; font-size: 18px; }

        /* Chart container */
        .chart-container { position: relative; height: 250px; width: 100%; margin-bottom: 20px; }

        /* Portal Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 28, 60, 0.8); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; padding: 40px; border-radius: 20px; width: 90%; max-width: 400px; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 25px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #ef4444; }
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
        .submit-btn:hover { background: #fbbc05; color: #0b1c3c; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* =========================================================
           MOBILE RESPONSIVENESS FIXES ONLY (No Design Changes)
           ========================================================= */
        
        /* സ്ക്രീൻ ഞെരുങ്ങാതിരിക്കാനുള്ള അടിസ്ഥാന സെറ്റിംഗ്സ് */
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
                background: #ffffff !important; /* White bg for black logo visibility */
                box-shadow: 0 4px 15px rgba(0,0,0,0.05);
                width: 100vw !important; box-sizing: border-box !important;
            }
            
            .logo-sub { display: none !important; } 
            .logo-icon { height: 38px !important; }
            .logo-title { font-size: 22px !important; margin: 0 !important; }
            .mobile-menu-toggle { display: block !important; color: #000 !important; }
            .header-right { display: none !important; } /* Hide desktop right section */
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

            /* Page Specific Content Padding */
            .inner-page-content { padding-left: 15px !important; padding-right: 15px !important; width: 100vw !important; box-sizing: border-box !important; }
            .ranking-split { grid-template-columns: 1fr !important; gap: 20px !important; }
            
            /* Podium alignment fix for mobile */
            .podium-container { gap: 10px !important; height: 200px !important; margin-bottom: 50px !important; }
            .podium-box { width: 30% !important; }
            .p-rank { width: 35px !important; height: 35px !important; font-size: 16px !important; top: -17px !important; }
            .podium-1 .p-rank { width: 45px !important; height: 45px !important; top: -22px !important; font-size: 20px !important; }
            .p-class { font-size: 14px !important; }
            .p-pts { font-size: 12px !important; padding: 2px 6px !important; }

            /* Footer Mobile Fix */
            .main-footer { padding: 40px 20px !important; width: 100vw !important; box-sizing: border-box !important; }
            .footer-container { display: flex !important; flex-direction: column !important; text-align: center !important; gap: 35px !important; }
            .footer-logo, .social-icons, .f-bottom-links { justify-content: center !important; }
            .c-item { flex-direction: column !important; justify-content: center !important; align-items: center !important; gap: 8px !important; text-align: center !important; }
            .f-links { display: flex !important; flex-direction: column !important; align-items: center !important; }
        }
/* Monthly and overall ranking dashboard */
.rk-page { background:#f4f7f9; padding-top:100px; padding-bottom:65px; font-family:'Montserrat',sans-serif; }
.rk-dashboard { max-width:1280px; margin:30px auto 0; padding:0 24px; }
.rk-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; margin-bottom:22px; }
.rk-tabs { display:inline-flex; gap:4px; background:#e9eef4; border:1px solid #e0e7ef; border-radius:12px; padding:5px; }
.rk-tabs button { display:flex; align-items:center; justify-content:center; gap:9px; padding:11px 19px; border:0; background:transparent; color:#586d86; border-radius:8px; font:inherit; font-size:12px; font-weight:700; cursor:pointer; }
.rk-tabs button[aria-pressed="true"] { color:#0b1c3c; background:#fff; box-shadow:0 2px 6px rgba(11,28,60,.07); }
.rk-report-actions { display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
.rk-download { display:flex; align-items:center; justify-content:center; gap:8px; padding:9px 12px; border:1px solid #dce3ec; border-radius:8px; color:#5c6d84; background:transparent; font:inherit; font-size:11px; font-weight:600; cursor:pointer; }
.rk-download:hover { background:#fff; color:#0b1c3c; }
.rk-download:disabled { opacity:.5; cursor:wait; }
.rk-report-hint { color:#75859a; font-size:9px; padding-right:2px; }
.rk-status { margin:0 0 14px; color:#586d86; font-size:11px; line-height:1.7; }
.rk-status:empty { display:none; }
.rk-layout { display:grid; grid-template-columns:minmax(0,.85fr) minmax(0,1.6fr); gap:24px; align-items:start; }
.rk-panel { background:#fff; border:1px solid #e5ebf2; border-radius:20px; padding:25px; min-width:0; box-shadow:0 8px 28px rgba(11,28,60,.035); }
.rk-overall { padding:30px; border-top:3px solid #fbbc05; }
.rk-panel-heading { display:flex; align-items:center; justify-content:space-between; gap:14px; }
.rk-eyebrow { display:block; color:#77869b; font-size:9px; text-transform:uppercase; letter-spacing:1.2px; line-height:1.7; font-weight:700; margin-bottom:7px; }
.rk-panel-heading h2 { color:#0b1c3c; font-size:21px; line-height:1.3; font-weight:800; margin:0; letter-spacing:-.5px; }
.rk-overall .rk-panel-heading h2 { font-size:27px; }
.rk-icon { display:flex; align-items:center; justify-content:center; width:36px; height:36px; flex:0 0 36px; background:#f0f4f8; color:#586d86; border-radius:10px; font-size:15px; }
.rk-all-time { padding:6px 10px; border:1px solid #f2dfa5; border-radius:30px; color:#796017; background:#fff8e1; font-size:10px; font-weight:700; flex-shrink:0; }
.rk-month-form { margin:23px 0 16px; }
.rk-month-form label { display:block; margin-bottom:8px; color:#64748b; font-size:10px; font-weight:600; }
.rk-month-controls { display:flex; gap:8px; }
.rk-month-controls select { min-width:0; flex:1; border:1px solid #dce3ec; border-radius:9px; padding:12px; background:#f8fafc; color:#0b1c3c; font:inherit; font-size:12px; font-weight:600; }
.rk-month-controls button { width:42px; flex:0 0 42px; border:0; border-radius:9px; background:#0b1c3c; color:#fff; cursor:pointer; }
.rk-month-caption { display:flex; justify-content:space-between; gap:12px; margin-bottom:14px; color:#0b1c3c; font-size:11px; font-weight:700; line-height:1.6; }
.rk-month-caption span:last-child { color:#75859a; font-size:10px; font-weight:500; }
.rk-panel-description { color:#64748b; margin:12px 0 20px; font-size:12px; line-height:1.8; }
.rk-stat-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin:20px 0 25px; }
.rk-stat-grid > div { border:1px solid #e8edf3; background:#f8fafc; border-radius:12px; padding:15px 12px; min-width:0; }
.rk-stat-grid span { display:block; color:#75859a; font-size:9px; font-weight:600; line-height:1.6; }
.rk-stat-grid strong { display:block; margin-top:7px; color:#0b1c3c; font-size:24px; font-weight:800; line-height:1.3; overflow-wrap:anywhere; font-variant-numeric:tabular-nums; }
.rk-chart-scroll { overflow:auto; max-height:420px; padding-bottom:8px; }
.rk-overall-scroll { max-height:610px; }
.rk-chart { position:relative; width:100%; }
.rk-chart-month { min-height:210px; }
.rk-chart-total { min-height:360px; }
.rk-table-wrap { overflow-x:auto; margin-top:16px; }
.rk-table { width:100%; border-collapse:collapse; }
.rk-table th { padding:12px 8px; background:#f3f6fa; color:#75859a; font-size:9px; font-weight:700; line-height:1.6; text-align:left; text-transform:uppercase; letter-spacing:.5px; }
.rk-table td { padding:14px 8px; border-bottom:1px solid #edf1f5; color:#354a66; font-size:11px; line-height:1.7; font-weight:600; overflow-wrap:anywhere; }
.rk-table th:first-child, .rk-table td:first-child { width:46px; text-align:center; }
.rk-table th:last-child, .rk-table td:last-child { text-align:right; white-space:nowrap; }
.rk-table td:last-child { color:#0b1c3c; font-weight:800; font-variant-numeric:tabular-nums; }
.rk-table .rk-first { background:#fffbef; }
.rk-rank-badge { display:inline-flex; align-items:center; justify-content:center; min-width:25px; height:25px; padding:0 5px; border-radius:8px; background:#edf2f7; color:#586d86; font-size:10px; font-weight:800; }
.rk-first .rk-rank-badge { background:#fbbc05; color:#0b1c3c; }
.rk-total-table td { padding-top:16px; padding-bottom:16px; font-size:13px; }
.rk-footnote { margin:18px 0 0; color:#8a97a9; font-size:9px; line-height:1.8; }
.rk-inline-note { margin:10px 0; color:#75859a; font-size:11px; line-height:1.8; }
.rk-inline-note:empty { display:none; }
.rk-empty-state { margin:20px 0; padding:28px 18px; text-align:center; color:#75859a; background:#f8fafc; border-radius:12px; font-size:12px; line-height:1.9; }
.rk-empty-state i { font-size:22px; }
.rk-empty-state p { margin:12px 0 0; }
.rk-notice, .rk-data-note { color:#796017; background:#fff9e8; border:1px solid #f1e4c0; padding:13px 16px; border-radius:10px; font-size:11px; line-height:1.8; margin:0 0 20px; }
.rk-data-note { margin:20px 0 0; color:#64748b; border-color:#e3e9f1; background:#f8fafc; }
.rk-data-note i { margin-right:6px; }
.rk-updated { margin:20px 0 0; color:#8a97a9; font-size:9px; text-align:right; line-height:1.8; }
.rk-sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
.rk-dashboard button:focus-visible, .rk-dashboard select:focus-visible { outline:3px solid #fbbc05; outline-offset:3px; }
@media(max-width:1000px) {
    .rk-layout { grid-template-columns:minmax(0,1fr); }
    .rk-monthly { order:2; }
    .rk-overall { order:1; }
}
@media(max-width:600px) {
    .rk-page { padding-top:55px; }
    .rk-dashboard { padding:0; margin-top:25px; }
    .rk-panel, .rk-overall { padding:20px 16px; border-radius:16px; }
    .rk-layout { gap:18px; }
    .rk-tabs button { padding:10px 14px; font-size:11px; }
    .rk-overall .rk-panel-heading h2 { font-size:23px; }
    .rk-stat-grid { gap:7px; }
    .rk-stat-grid > div { padding:12px 9px; }
    .rk-stat-grid strong { font-size:20px; }
    .rk-stat-grid span { font-size:8px; }
    .rk-total-table td { font-size:11px; }
    .rk-report-hint { display:none; }
    .rk-updated { text-align:left; }
}

    </style>
</head>
<body>

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
                <li><a href="ranking.php" class="active">Ranking</a></li>
                <li><a href="calendar.php">Event Calendar</a></li>
                
                <?php if($outreach_state == 'open') { ?>
                <li><a href="outreach.php">Outreach</a></li>
                <?php } ?>
                
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                
                <!-- Portal Link inside mobile menu -->
                <li class="mobile-portal-li">
                    <a href="#" class="portal-btn open-portal-modal mobile-portal-btn">Portal Login</a>
                </li>
            </ul>
        </nav>
        
        <div class="header-right desktop-portal-btn">
            <a href="#" class="portal-btn open-portal-modal">Portal</a>
        </div>
    </header>

    <!-- Ranking പേജിന്റെ പ്രധാന ഭാഗം -->
    <main class="inner-page-content rk-page">
        <div class="page-title-box">
            <h1>Performance Rankings</h1>
            <div class="yellow-line-center"></div>
            <p>Monthly progress and the complete picture, side by side.</p>
        </div>
        <section class="rk-dashboard" aria-label="Class and wing rankings">
            <div class="rk-toolbar">
                <div class="rk-tabs" role="group" aria-label="Ranking category">
                    <button type="button" data-rk-type="Class" aria-pressed="<?= $active_type === 'Class' ? 'true' : 'false' ?>"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Classes</button>
                    <button type="button" data-rk-type="Wing" aria-pressed="<?= $active_type === 'Wing' ? 'true' : 'false' ?>"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Wings</button>
                </div>
                <div class="rk-report-actions">
                    <button id="rk-download" class="rk-download" type="button" <?= $total_error ? 'disabled' : '' ?>><i class="fa-regular fa-file-pdf" aria-hidden="true"></i> Download report</button>
                    <span class="rk-report-hint">PDF · Graphs &amp; points</span>
                </div>
            </div>
            <p id="rk-download-status" class="rk-status" role="status" aria-live="polite"></p>
            <?php if ($month_notice !== ''): ?>
                <p class="rk-notice" role="alert"><?= ranking_escape($month_notice) ?></p>
            <?php endif; ?>
            <?php if ($total_error): ?>
                <p class="rk-notice" role="alert">Overall rankings could not be loaded. Please try again later.</p>
            <?php endif; ?>

            <div class="rk-layout">
                <aside class="rk-panel rk-monthly" aria-labelledby="rk-monthly-heading">
                    <div class="rk-panel-heading">
                        <div><span class="rk-eyebrow">Month by month</span><h2 id="rk-monthly-heading">Monthly ranking</h2></div>
                        <span class="rk-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
                    </div>
                    <form action="ranking.php" method="GET" class="rk-month-form">
                        <input type="hidden" name="type" id="rk-type-field" value="<?= ranking_escape($active_type) ?>">
                        <label for="rk-month">Choose a month</label>
                        <div class="rk-month-controls">
                            <select name="month" id="rk-month">
                                <?php foreach ($available_months as $month):
                                    $label_date = DateTimeImmutable::createFromFormat('!Y-m', $month, $ranking_timezone);
                                ?>
                                    <option value="<?= ranking_escape($month) ?>" <?= $month === $selected_month ? 'selected' : '' ?>><?= ranking_escape($label_date->format('F Y')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" aria-label="View selected month"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                        </div>
                    </form>
                    <div class="rk-month-caption"><span><?= ranking_escape($selected_month_label) ?></span><span id="rk-month-entries"></span></div>
                    <?php if ($monthly_ready): ?>
                        <div class="rk-chart-scroll"><div class="rk-chart rk-chart-month" id="rk-month-chart-wrap"><canvas id="rk-month-chart" role="img" aria-label="Monthly ranking points graph"></canvas></div></div>
                        <p id="rk-month-empty" class="rk-inline-note"></p>
                        <div class="rk-table-wrap">
                            <table class="rk-table"><caption class="rk-sr-only">Selected month rankings</caption><thead><tr><th scope="col">Rank</th><th scope="col" class="rk-name-heading">Class</th><th scope="col">Points</th></tr></thead><tbody id="rk-month-rows"></tbody></table>
                        </div>
                    <?php else: ?>
                        <div class="rk-empty-state"><i class="fa-regular fa-calendar" aria-hidden="true"></i><p><?= ranking_escape($monthly_message) ?></p></div>
                    <?php endif; ?>
                    <p class="rk-footnote">Only evaluated activities submitted in this month are counted.</p>
                </aside>

                <div class="rk-panel rk-overall" aria-labelledby="rk-overall-heading">
                    <div class="rk-panel-heading">
                        <div><span class="rk-eyebrow">The complete picture</span><h2 id="rk-overall-heading">Overall ranking</h2></div>
                        <span class="rk-all-time">All time</span>
                    </div>
                    <p class="rk-panel-description">Total points from all evaluated activities across every month.</p>
                    <div class="rk-stat-grid">
                        <div><span>Combined points</span><strong id="rk-total-points">—</strong></div>
                        <div><span id="rk-participant-label">Classes</span><strong id="rk-participants">—</strong></div>
                        <div><span>Evaluated entries</span><strong id="rk-total-entries">—</strong></div>
                    </div>
                    <?php if (!$total_error): ?>
                        <div class="rk-chart-scroll rk-overall-scroll"><div class="rk-chart rk-chart-total" id="rk-total-chart-wrap"><canvas id="rk-total-chart" role="img" aria-label="Overall ranking points graph"></canvas></div></div>
                        <p id="rk-total-empty" class="rk-inline-note"></p>
                        <div class="rk-table-wrap">
                            <table class="rk-table rk-total-table"><caption class="rk-sr-only">All-time rankings</caption><thead><tr><th scope="col">Rank</th><th scope="col" class="rk-name-heading">Class</th><th scope="col">Total points</th></tr></thead><tbody id="rk-total-rows"></tbody></table>
                        </div>
                    <?php else: ?>
                        <p class="rk-empty-state">Overall ranking data is unavailable.</p>
                    <?php endif; ?>
                    <p class="rk-footnote">Equal points share a rank. Selecting a month does not change these totals.</p>
                </div>
            </div>
            <?php if ($undated_count > 0): ?>
                <p class="rk-data-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?= $undated_count ?> evaluated <?= $undated_count === 1 ? 'submission has' : 'submissions have' ?> no usable date. These points appear in the overall ranking only.</p>
            <?php endif; ?>
            <p class="rk-updated">Loaded <?= ranking_escape($ranking_payload['generated']) ?> · Refresh for the latest evaluated results.</p>
            <noscript><p class="rk-notice">Enable JavaScript to view the ranking graphs, points tables, and PDF download.</p></noscript>
        </section>
    </main>
    <script id="rk-data" type="application/json"><?= json_encode($ranking_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>


    <!-- ഫൂട്ടർ -->
    <?php include __DIR__ . '/footer.php'; ?>

    <!-- Portal Login Popup (Modal) -->
    <div id="portalModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal"><i class="fa-solid fa-xmark"></i></span>
            <div class="modal-header" style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #0b1c3c; margin: 0 0 5px 0; font-weight: 900;">Portal Login</h2>
                <p style="color: #64748b; font-size: 13px; margin: 0; font-weight: 600;">Select your account type to continue</p>
            </div>
            
            <!-- ലോഗിൻ ടാബുകൾ -->
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

    <!-- സ്ക്രിപ്റ്റുകൾ (Animations, Login Modal & Graph) -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // സ്ക്രോൾ അനിമേഷൻസ്
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
                    } else {
                        icon.classList.remove('fa-xmark');
                        icon.classList.add('fa-bars');
                    }
                });
            }

            // Portal Login Modal Logic
            const portalBtns = document.querySelectorAll('.open-portal-modal, .portal-btn');
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
                window.addEventListener('click', (e) => { if (e.target === portalModal) portalModal.classList.remove('show'); });
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
                    if (targetForm) {
                        targetForm.classList.add('active-form');
                    }
                });
            });
        });
    </script>
<script>
(function () {
    'use strict';
    var dataElement = document.getElementById('rk-data');
    if (!dataElement) return;
    var data = JSON.parse(dataElement.textContent);
    var type = data.type;
    var charts = {};
    var numberFormat = new Intl.NumberFormat('en-IN', {maximumFractionDigits: 4});
    function points(value) { return numberFormat.format(Number(value) || 0); }
    function setText(id, value) {
        var element = document.getElementById(id);
        if (element) element.textContent = value;
    }
    function sum(rows, key) {
        return rows.reduce(function (total, row) { return total + Number(row[key] || 0); }, 0);
    }
    function graphemes(text) {
        if (typeof Intl.Segmenter === 'function') {
            return Array.from(new Intl.Segmenter(undefined, {granularity: 'grapheme'}).segment(text), function (part) { return part.segment; });
        }
        return Array.from(text);
    }
    function chartName(name, maxLength) {
        var characters = graphemes(String(name));
        var lines = [];
        for (var i = 0; i < characters.length; i += maxLength) {
            lines.push(characters.slice(i, i + maxLength).join(''));
        }
        return lines.length ? lines : ['Unnamed'];
    }
    function table(id, rows) {
        var body = document.getElementById(id);
        if (!body) return;
        body.replaceChildren();
        var judged = sum(rows, 'entries') > 0;
        if (!rows.length) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 3;
            emptyCell.textContent = 'No registered ' + (type === 'Class' ? 'classes' : 'wings') + '.';
            emptyRow.appendChild(emptyCell);
            body.appendChild(emptyRow);
            return;
        }
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            if (row.rank === 1 && judged) tr.className = 'rk-first';
            var rank = document.createElement('td');
            var badge = document.createElement('span');
            badge.className = 'rk-rank-badge';
            badge.textContent = row.rank;
            rank.appendChild(badge);
            var name = document.createElement('td');
            name.textContent = row.name;
            var score = document.createElement('td');
            score.textContent = points(row.points);
            tr.append(rank, name, score);
            body.appendChild(tr);
        });
    }
    var pointLabels = {
        id: 'rankingPointLabels',
        afterDatasetsDraw: function (chart) {
            var context = chart.ctx;
            context.save();
            context.font = '600 10px Montserrat, sans-serif';
            context.fillStyle = '#354a66';
            context.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach(function (bar, index) {
                var value = Number(chart.data.datasets[0].data[index]);
                context.textAlign = value < 0 ? 'right' : 'left';
                context.fillText(points(value), bar.x + (value < 0 ? -7 : 7), bar.y);
            });
            context.restore();
        }
    };
    function graph(scope, rows) {
        var canvas = document.getElementById('rk-' + scope + '-chart');
        var wrap = document.getElementById('rk-' + scope + '-chart-wrap');
        if (!canvas || !wrap) return;
        if (charts[scope]) {
            charts[scope].destroy();
            delete charts[scope];
        }
        wrap.style.display = rows.length ? 'block' : 'none';
        if (!rows.length) return;
        if (typeof window.Chart !== 'function') {
            wrap.style.display = 'none';
            setText('rk-' + scope + '-empty', 'Graph could not load. Points remain available in the table.');
            return;
        }
        var monthly = scope === 'month';
        var labels = rows.map(function (row) { return chartName(row.name, monthly ? 18 : 25); });
        var height = labels.reduce(function (total, lines) { return total + Math.max(46, 28 + lines.length * 13); }, 40);
        wrap.style.height = Math.max(monthly ? 210 : 360, height) + 'px';
        var scores = rows.map(function (row) { return Number(row.points); });
        var low = Math.min.apply(null, [0].concat(scores));
        var high = Math.max.apply(null, [0].concat(scores));
        charts[scope] = new window.Chart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Evaluated points', data: scores,
                    backgroundColor: rows.map(function (row) { return row.rank === 1 && sum(rows, 'entries') > 0 ? '#fbbc05' : (monthly ? '#7d94b1' : '#0b1c3c'); }),
                    borderRadius: 5, maxBarThickness: monthly ? 17 : 25
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                animation: false,
                layout: {padding: {right: 30, left: 4}},
                scales: {
                    x: {min: low < 0 ? low * 1.4 : 0, max: high > 0 ? high * 1.4 : 1, grid: {color: '#edf1f5'}, ticks: {color: '#8a97a9', font: {size: 9}, maxTicksLimit: 5}},
                    y: {grid: {display: false}, border: {display: false}, ticks: {autoSkip: false, color: '#586d86', font: {size: monthly ? 9 : 11}}}
                },
                plugins: {legend: {display: false}, tooltip: {callbacks: {label: function (item) { return ' ' + points(item.raw) + ' points'; }}}}
            },
            plugins: [pointLabels]
        });
    }
    function render() {
        var total = data.total[type] || [];
        var monthly = data.monthly[type] || [];
        document.querySelectorAll('[data-rk-type]').forEach(function (button) {
            button.setAttribute('aria-pressed', String(button.dataset.rkType === type));
        });
        document.querySelectorAll('.rk-name-heading').forEach(function (heading) { heading.textContent = type; });
        var field = document.getElementById('rk-type-field');
        if (field) field.value = type;
        setText('rk-participant-label', type === 'Class' ? 'Classes' : 'Wings');
        if (!data.totalError) {
            setText('rk-total-points', points(sum(total, 'points')));
            setText('rk-participants', total.length);
            setText('rk-total-entries', points(sum(total, 'entries')));
            setText('rk-total-empty', sum(total, 'entries') === 0 ? 'No evaluated activities yet. Registered groups remain listed with zero points.' : '');
            table('rk-total-rows', total);
            graph('total', total);
        }
        if (data.monthlyReady) {
            setText('rk-month-entries', points(sum(monthly, 'entries')) + ' entries');
            setText('rk-month-empty', sum(monthly, 'entries') === 0 ? 'No evaluated submissions in this month.' : '');
            table('rk-month-rows', monthly);
            graph('month', monthly);
        }
    }
    document.querySelectorAll('[data-rk-type]').forEach(function (button) {
        button.addEventListener('click', function () {
            type = button.dataset.rkType;
            render();
            var url = new URL(window.location.href);
            url.searchParams.set('type', type);
            url.searchParams.set('month', data.month);
            window.history.replaceState(null, '', url);
        });
    });
    var monthSelect = document.getElementById('rk-month');
    if (monthSelect) {
        monthSelect.addEventListener('change', function () {
            if (typeof monthSelect.form.requestSubmit === 'function') monthSelect.form.requestSubmit();
            else monthSelect.form.submit();
        });
    }
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", render, { once: true });
    else render();

    // PDF pages are drawn directly to canvas. Browser fonts preserve Malayalam
    // names, and every row is paginated without clipping a table screenshot.
    function wrappedText(context, text, maxWidth) {
        var result = [];
        var line = '';
        graphemes(String(text)).forEach(function (character) {
            var proposed = line + character;
            if (line && context.measureText(proposed).width > maxWidth) {
                result.push(line);
                line = character;
            } else line = proposed;
        });
        if (line || !result.length) result.push(line);
        return result;
    }
    function drawPdfSection(pdf, title, rows, reportState) {
        var width = 1240, height = 1754;
        var low = Math.min.apply(null, [0].concat(rows.map(function (row) { return row.points; })));
        var high = Math.max.apply(null, [0].concat(rows.map(function (row) { return row.points; })));
        if (high === low) high = low + 1;
        var offset = 0;
        var continuation = false;
        do {
            var canvas = document.createElement('canvas');
            canvas.width = width; canvas.height = height;
            var context = canvas.getContext('2d');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, width, height);
            context.fillStyle = '#0b1c3c';
            context.fillRect(0, 0, width, 170);
            context.fillStyle = '#fbbc05';
            context.fillRect(0, 170, width, 6);
            context.fillStyle = '#fff';
            context.font = '800 34px Montserrat, sans-serif';
            context.fillText("NUS'HA | Ranking report", 60, 70);
            context.font = '400 17px Montserrat, sans-serif';
            context.fillText('Generated ' + data.generated, 60, 112);
            context.fillText('Monthly report: ' + data.monthLabel + ' | Evaluated activities only', 60, 143);
            context.fillStyle = '#0b1c3c';
            context.font = '800 29px Montserrat, sans-serif';
            context.fillText(title + (continuation ? ' (continued)' : ''), 60, 235);
            context.fillStyle = '#64748b';
            context.font = '400 17px Montserrat, sans-serif';
            context.fillText('Equal points share a rank. ' + rows.length + ' registered groups.', 60, 271);
            context.fillStyle = '#f3f6fa';
            context.fillRect(60, 300, 1120, 48);
            context.fillStyle = '#586d86';
            context.font = '700 16px Montserrat, sans-serif';
            context.fillText('RANK', 76, 330);
            context.fillText('NAME', 156, 330);
            context.fillText('POINTS GRAPH', 570, 330);
            context.textAlign = 'right';
            context.fillText('POINTS', 1160, 330);
            context.textAlign = 'left';
            var y = 364;
            if (!rows.length) {
                context.font = '400 22px Montserrat, sans-serif';
                context.fillText('No registered groups are available.', 76, 415);
            }
            while (offset < rows.length) {
                var row = rows[offset];
                context.font = '500 20px Montserrat, sans-serif';
                var nameLines = wrappedText(context, row.name, 375);
                var rowHeight = Math.max(76, nameLines.length * 27 + 30);
                if (y + rowHeight > 1490 && y > 364) break;
                if (rowHeight > 1120) {
                    throw new Error('A group name is too long for the report. Please shorten that name before exporting.');
                }
                if (row.rank === 1 && sum(rows, 'entries') > 0) {
                    context.fillStyle = '#fffbef';
                    context.fillRect(60, y, 1120, rowHeight);
                }
                context.fillStyle = '#0b1c3c';
                context.font = '700 20px Montserrat, sans-serif';
                context.fillText(String(row.rank), 85, y + 34);
                context.font = '500 20px Montserrat, sans-serif';
                nameLines.forEach(function (line, index) { context.fillText(line, 156, y + 34 + index * 27); });
                var plotLeft = 570, plotWidth = 435;
                var barY = nameLines.length > 5 ? y + rowHeight - 26 : y + rowHeight / 2;
                for (var tick = 0; tick <= 4; tick++) {
                    context.strokeStyle = '#e5ebf2';
                    var tickX = plotLeft + plotWidth * tick / 4;
                    context.beginPath(); context.moveTo(tickX, barY - 17); context.lineTo(tickX, barY + 17); context.stroke();
                }
                var zeroX = plotLeft + plotWidth * (0 - low) / (high - low);
                var scoreX = plotLeft + plotWidth * (row.points - low) / (high - low);
                context.fillStyle = row.rank === 1 && sum(rows, 'entries') > 0 ? '#fbbc05' : '#0b1c3c';
                context.fillRect(Math.min(zeroX, scoreX), barY - 9, Math.max(0, Math.abs(scoreX - zeroX)), 18);
                context.fillStyle = '#0b1c3c';
                context.font = '700 20px Montserrat, sans-serif';
                context.textAlign = 'right';
                context.fillText(points(row.points), 1160, barY + 7);
                context.textAlign = 'left';
                context.strokeStyle = '#edf1f5';
                context.beginPath(); context.moveTo(60, y + rowHeight); context.lineTo(1180, y + rowHeight); context.stroke();
                y += rowHeight;
                offset++;
            }
            context.fillStyle = '#64748b';
            context.font = '400 15px Montserrat, sans-serif';
            context.fillText('Graph scale: ' + points(low) + ' to ' + points(high) + ' points. Same scale throughout this section.', 60, 1544);
            var notes = [];
            if (!data.monthlyReady) notes.push('Monthly data unavailable: ' + data.monthlyMessage);
            if (data.undatedCount > 0) notes.push(data.undatedCount + ' undated evaluated submissions are counted only in overall totals.');
            notes.push('Monthly points use submission dates. Overall totals include every evaluated submission.');
            var noteLines = wrappedText(context, notes.join(' '), 1120);
            noteLines.slice(0, 4).forEach(function (line, index) { context.fillText(line, 60, 1580 + index * 21); });
            context.strokeStyle = '#e2e8f0';
            context.beginPath(); context.moveTo(60, 1680); context.lineTo(1180, 1680); context.stroke();
            context.fillText("Noorul Hidaya Students' Union", 60, 1712);
            context.textAlign = 'right';
            context.fillText('Page ' + (reportState.pages + 1), 1180, 1712);
            if (reportState.pages > 0) pdf.addPage();
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, 210, 297);
            reportState.pages++;
            canvas.width = 1; canvas.height = 1;
            continuation = true;
        } while (offset < rows.length);
    }
    var download = document.getElementById('rk-download');
    if (download) {
        download.addEventListener('click', async function () {
            if (data.totalError) return;
            download.disabled = true;
            setText('rk-download-status', 'Preparing the report with graphs and all ranking points…');
            try {
                await window.loadNushaPdf();
                if (document.fonts && document.fonts.ready) await document.fonts.ready;
                await new Promise(function (resolve) { requestAnimationFrame(resolve); });
                var pdf = new window.jspdf.jsPDF({orientation: 'portrait', unit: 'mm', format: 'a4', compress: true});
                pdf.setProperties({title: "NUS'HA Ranking Report — " + data.monthLabel, author: "NUS'HA", subject: 'Monthly and overall class and wing rankings'});
                var state = {pages: 0};
                ['Class', 'Wing'].forEach(function (group) {
                    var label = group === 'Class' ? 'Classes' : 'Wings';
                    drawPdfSection(pdf, 'Overall ranking | ' + label, data.total[group], state);
                    if (data.monthlyReady) drawPdfSection(pdf, data.monthLabel + ' | ' + label, data.monthly[group], state);
                });
                pdf.save('NUSHA-ranking-report-' + data.month + '.pdf');
                setText('rk-download-status', 'Report download started. Includes graphs and points for Classes and Wings.');
            } catch (error) {
                console.error('Ranking report:', error);
                setText('rk-download-status', 'Report could not be created. Please try again. ' + (error.message || ''));
            } finally {
                download.disabled = false;
            }
        });
    }
})();

</script>
</body>
</html>
