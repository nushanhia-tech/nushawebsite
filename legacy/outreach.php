<?php
include 'db.php'; // ഡാറ്റാബേസ് കണക്ഷൻ

$status = $_GET['submission'] ?? '';
$msg = $status === 'success' ? 'success' : (in_array($status, ['error', 'unavailable'], true) ? 'error' : '');

$posts_query = $conn->query("SELECT * FROM outreach_posts WHERE status = 'published' OR status IS NULL ORDER BY id DESC");
$all_posts = [];
if ($posts_query && $posts_query->num_rows > 0) {
    while ($p = $posts_query->fetch_assoc()) {
        $all_posts[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Arabic Outreach - NUS'HA</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="/brand.css">
    <!-- Google Fonts: Montserrat & Arabic Fonts (Cairo) -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="footer.css">
    
    <style>
        /* BASE & STRUCTURAL RESET */
        html, body { overflow-x: hidden; width: 100%; margin: 0; padding: 0; box-sizing: border-box; font-family: 'Cairo', 'Montserrat', sans-serif; }
        *, *::before, *::after { box-sizing: inherit; }

        /* Header Alignment */
        #main-header { direction: ltr !important; }
        .header-right { display: flex; align-items: center; gap: 15px; }
        .mobile-menu-toggle { display: none; background: transparent; border: none; font-size: 28px; cursor: pointer; color: #0b1c3c; padding: 5px; transition: 0.3s; outline: none; }
        .mobile-portal-li { display: none; }

        .logo { text-decoration: none !important; display: flex; align-items: center; gap: 15px; }
        .logo-icon { object-fit: contain !important; filter: brightness(0) !important; width: auto !important; max-height: 45px !important; }
        .logo, .logo-text, .logo-title, .logo-sub, .logo * { color: #000000 !important; text-decoration: none !important; }

        /* ARABIC OUTREACH CONTENT STYLES */
        .outreach-container { max-width: 1200px; margin: 40px auto 100px; padding: 0 5%; }
        
        .category-filter { display: flex; justify-content: center; align-items: center; gap: 12px; margin-bottom: 45px; flex-wrap: wrap; }
        .cat-btn { padding: 10px 24px; border: 2px solid #e2e8f0; background: #fff; border-radius: 30px; font-weight: 700; font-size: 15px; color: #475569; cursor: pointer; transition: 0.3s; }
        .cat-btn:hover, .cat-btn.active { background: #0b1c3c; color: #fff; border-color: #0b1c3c; box-shadow: 0 5px 15px rgba(11,28,60,0.15); }

        .posts-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); 
            gap: 35px; 
            align-items: stretch; 
        }

        .posts-grid.single-item {
            display: flex !important;
            justify-content: center !important;
        }
        .posts-grid.single-item .arabic-card {
            width: 100%;
            max-width: 480px;
        }
        
        .arabic-card {
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            transition: 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        .arabic-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); border-color: #fbbc05; }

        .card-thumb { height: 210px; width: 100%; position: relative; overflow: hidden; background-color: #e2e8f0; }
        .card-thumb img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .arabic-card:hover .card-thumb img { transform: scale(1.06); }
        .card-badge { position: absolute; top: 15px; right: 15px; background: #fbbc05; color: #0b1c3c; font-weight: 800; font-size: 12px; padding: 5px 14px; border-radius: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }

        .card-body { padding: 25px; display: flex; flex-direction: column; flex-grow: 1; justify-content: space-between; }
        .card-title { font-size: 20px; font-weight: 800; color: #0b1c3c; margin: 0 0 12px 0; line-height: 1.5; }
        .card-snippet { font-size: 14px; color: #64748b; line-height: 1.7; margin-bottom: 20px; flex-grow: 1; }

        .card-footer { display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 18px; margin-top: auto; }
        .author-box { display: flex; align-items: center; gap: 12px; }
        .author-img { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #fbbc05; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .author-info h5 { margin: 0; font-size: 14px; font-weight: 700; color: #0b1c3c; }
        .author-info span { font-size: 11px; color: #94a3b8; font-weight: 600; display: block; }

        .read-btn { background: #f8fafc; color: #0b1c3c; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .read-btn:hover { background: #0b1c3c; color: #fff; border-color: #0b1c3c; }

        /* FLOATING SEND OUTREACH BUTTON */
        .floating-send-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, #0b1c3c, #1e3a8a);
            color: #fff;
            padding: 14px 24px;
            border-radius: 50px;
            box-shadow: 0 10px 25px rgba(11,28,60,0.3);
            border: 2px solid #fbbc05;
            cursor: pointer;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 800;
            transition: 0.3s;
            text-decoration: none;
        }
        .floating-send-btn:hover {
            transform: translateY(-5px) scale(1.03);
            background: #fbbc05;
            color: #0b1c3c;
            box-shadow: 0 15px 30px rgba(251,188,5,0.4);
        }

        /* ARTICLE FULL VIEW POPUP MODAL */
        .article-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11,28,60,0.85); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(6px); }
        .article-modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
        .article-modal-box { background: #fff; width: 92%; max-width: 780px; max-height: 90vh; border-radius: 20px; overflow-y: auto; padding: 40px; position: relative; box-shadow: 0 25px 60px rgba(0,0,0,0.25); text-align: right; }
        .close-article-modal { position: absolute; top: 20px; left: 25px; font-size: 26px; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .close-article-modal:hover { color: #ef4444; }

        .modal-post-img { width: 100%; max-height: 340px; object-fit: cover; border-radius: 14px; margin-bottom: 25px; }
        .modal-post-category { display: inline-block; background: #fbbc05; color: #0b1c3c; font-weight: 800; font-size: 12px; padding: 5px 15px; border-radius: 20px; margin-bottom: 15px; }
        .modal-post-title { font-size: 28px; font-weight: 900; color: #0b1c3c; margin: 0 0 20px 0; line-height: 1.4; }
        .modal-author-wrap { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #f1f5f9; }
        .modal-post-body { font-size: 16px; line-height: 2; color: #334155; white-space: pre-line; text-align: justify; }

        /* SEND OUTREACH MODAL FORM */
        .send-outreach-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11,28,60,0.85); z-index: 999999; align-items: center; justify-content: center; backdrop-filter: blur(6px); }
        .send-outreach-modal.show { display: flex; animation: fadeIn 0.3s ease; }
        .send-modal-box { background: #fff; width: 92%; max-width: 650px; max-height: 90vh; border-radius: 20px; overflow-y: auto; padding: 35px; position: relative; text-align: right; direction: rtl; box-shadow: 0 25px 60px rgba(0,0,0,0.25); }
        .close-send-modal { position: absolute; top: 20px; left: 25px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .close-send-modal:hover { color: #ef4444; }
        .send-form-group { margin-bottom: 18px; }
        .send-form-group label { display: block; font-size: 13px; font-weight: 700; color: #0b1c3c; margin-bottom: 6px; }
        .send-form-group input, .send-form-group select, .send-form-group textarea { width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-family: 'Cairo', sans-serif; font-size: 14px; outline: none; transition: 0.3s; }
        .send-form-group input:focus, .send-form-group select:focus, .send-form-group textarea:focus { border-color: #fbbc05; background: #fffdf0; }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        /* FOOTER STRICT LTR (ENGLISH) */
        .main-footer { direction: ltr !important; text-align: left !important; font-family: 'Montserrat', sans-serif !important; }
        .main-footer .footer-container { direction: ltr !important; text-align: left !important; }
        .main-footer .footer-logo, .main-footer .c-item, .main-footer .f-links, .main-footer .social-icons { justify-content: flex-start !important; text-align: left !important; }
        .main-footer .footer-bottom { direction: ltr !important; text-align: center !important; }

        /* PORTAL LOGIN MODAL */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(11, 28, 60, 0.8); z-index: 99999; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
        .modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
        .modal-content { background: #fff; padding: 40px; border-radius: 20px; width: 90%; max-width: 400px; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.2); box-sizing: border-box; max-height: 90vh; overflow-y: auto; text-align: left; direction: ltr; }
        .close-modal { position: absolute; top: 20px; right: 25px; font-size: 24px; color: #94a3b8; cursor: pointer; transition: 0.3s; }
        .close-modal:hover { color: #ef4444; }
        .login-tabs { display: flex; margin-bottom: 25px; background: #f1f5f9; border-radius: 10px; padding: 5px; }
        .tab-btn { flex: 1; padding: 10px; border: none; background: transparent; font-family: 'Montserrat', sans-serif; font-weight: 700; font-size: 14px; color: #64748b; cursor: pointer; border-radius: 8px; transition: 0.3s; }
        .tab-btn.active { background: #fff; color: #0b1c3c; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .login-form { display: none; }
        .login-form.active-form { display: block; }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: #0b1c3c; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px; font-family: 'Montserrat', sans-serif; font-size: 14px; color: #0b1c3c; outline: none; transition: 0.3s; }
        .submit-btn { width: 100%; padding: 14px; background: #0b1c3c; color: #fff; border: none; border-radius: 10px; font-family: 'Montserrat', 'Cairo', sans-serif; font-weight: 800; font-size: 15px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .submit-btn:hover { background: #fbbc05; color: #0b1c3c; }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @media (max-width: 768px) {
            #main-header { display: flex !important; flex-wrap: wrap !important; align-items: center !important; justify-content: space-between !important; padding: 15px 20px !important; position: relative; background: #ffffff !important; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100vw !important; }
            .logo-sub { display: none !important; } 
            .logo-icon { height: 38px !important; }
            .logo-title { font-size: 22px !important; margin: 0 !important; }
            .mobile-menu-toggle { display: block !important; color: #000 !important; }
            .header-right { display: none !important; }
            .mobile-portal-li { display: block !important; border-bottom: none !important; }
            nav#navbar { display: none; position: absolute; top: 100%; left: 0; width: 100%; background: #ffffff; box-shadow: 0 20px 30px rgba(0,0,0,0.15); padding: 20px 30px; z-index: 1000; border-top: 2px solid #fbbc05; }
            nav#navbar.active-menu { display: block !important; }
            nav#navbar ul { flex-direction: column !important; align-items: stretch !important; gap: 0 !important; margin: 0; padding: 0; }
            nav#navbar ul li { width: 100%; border-bottom: 1px solid #f1f5f9; text-align: center; }
            nav#navbar ul li a { display: block; padding: 15px 0; font-size: 16px; font-weight: 700; color: #0b1c3c !important; }
            .posts-grid { grid-template-columns: 1fr !important; gap: 25px !important; }
            .floating-send-btn { bottom: 20px; right: 20px; padding: 12px 18px; font-size: 14px; }
            .form-row-2 { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body style="background-color: #f4f7f9;">

    <!-- നാവിഗേഷൻ ബാർ -->
    <header id="main-header" class="scrolled fade-in">
        <a href="index.php" class="logo">
            <span class="nusha-mark" aria-hidden="true">NH</span>
            <div class="logo-text">
                <span class="logo-title">NUS'HA</span>
                <span class="logo-sub">Noorul hidaya union for<br>heterogeneous activities</span>
            </div>
        </a>
        
        <button id="mobile-menu-btn" class="mobile-menu-toggle"><i class="fa-solid fa-bars"></i></button>

        <nav id="navbar">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="leadership.php">Leadership</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="ranking.php">Ranking</a></li>
                <li><a href="calendar.php">Event Calendar</a></li>
                <li><a href="outreach.php" class="active">Outreach</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li class="mobile-portal-li">
                    <a href="#" class="portal-btn open-portal-modal mobile-portal-btn">Portal Login</a>
                </li>
            </ul>
        </nav>

        <div class="header-right desktop-portal-btn">
            <a href="#" class="portal-btn open-portal-modal">Portal</a>
        </div>
    </header>

    <!-- FLOATING SEND OUTREACH BUTTON -->
    <button class="floating-send-btn" onclick="openSendModal()">
        <i class="fa-solid fa-feather-pointed"></i> إرسال مشاركة (Submit Post)
    </button>

    <!-- Outreach പേജിന്റെ പ്രധാന ഭാഗം -->
    <main class="inner-page-content" style="padding-top: 100px;">
        
        <div class="page-title-box fade-in" style="text-align: center;">
            <h1 style="font-family: 'Cairo';">المنصة الأدبية والثقافية</h1>
            <div class="yellow-line-center"></div>
            <p style="font-family: 'Cairo';">إبداعات طلابية، مقالات فكرية، وقصص أدبية متنوعة من إعداد اتحاد نزهة</p>
            
            <?php if ($msg === 'success'): ?>
                <div style="max-width: 600px; margin: 20px auto; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; padding: 15px; border-radius: 12px; font-weight: 700;">
                    <i class="fa-solid fa-circle-check"></i> تم إرسال مشاركتك بنجاح! سيتم نشرها بعد مراجعة الإدارة. (Submitted successfully! It will be published after admin review.)
                </div>
            <?php elseif ($msg === 'error'): ?>
                <div style="max-width: 600px; margin: 20px auto; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 15px; border-radius: 12px; font-weight: 700;">
                    حدث خطأ أثناء الإرسال. يرجى المحاولة مرة أخرى.
                </div>
            <?php endif; ?>
        </div>

        <section class="outreach-container">
            
            <!-- Category Filter Tabs -->
            <div class="category-filter scroll-anim reveal-up">
                <button class="cat-btn active" onclick="filterPosts('all', this)">الكل (All)</button>
                <button class="cat-btn" onclick="filterPosts('Article', this)">المقالات (Articles)</button>
                <button class="cat-btn" onclick="filterPosts('Story', this)">القصص (Stories)</button>
                <button class="cat-btn" onclick="filterPosts('Poem', this)">القصائد (Poems)</button>
            </div>

            <!-- Posts Grid -->
            <div class="posts-grid <?php echo (count($all_posts) == 1) ? 'single-item' : ''; ?>" id="postsGrid">
                <?php
                if (count($all_posts) > 0) {
                    foreach ($all_posts as $post) {
                        $p_id = (int) $post['id'];
                        $p_title = htmlspecialchars($post['title']);
                        $p_cat = htmlspecialchars($post['category']);
                        $p_content = htmlspecialchars($post['content']);
                        $p_snippet = public_text(mb_substr(strip_tags($post['content']), 0, 110, 'UTF-8')) . '...';
                        $p_thumb = !empty($post['thumbnail']) ? $post['thumbnail'] : 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&auto=format&fit=crop&q=60';
                        $p_author = htmlspecialchars($post['author_name']);
                        $p_role = htmlspecialchars($post['author_role'] ?? 'طالب');
                        $p_author_img = !empty($post['author_photo']) ? $post['author_photo'] : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                        
                        echo '
                        <div class="arabic-card post-item" data-category="'.$p_cat.'">
                            <div class="card-thumb">
                                <img src="'.$p_thumb.'" alt="'.$p_title.'">
                                <span class="card-badge">'.$p_cat.'</span>
                            </div>
                            <div class="card-body">
                                <div>
                                    <h3 class="card-title">'.$p_title.'</h3>
                                    <p class="card-snippet">'.$p_snippet.'</p>
                                </div>
                                <div class="card-footer">
                                    <div class="author-box">
                                        <img src="'.$p_author_img.'" alt="'.$p_author.'" class="author-img">
                                        <div class="author-info">
                                            <h5>'.$p_author.'</h5>
                                            <span>'.$p_role.'</span>
                                        </div>
                                    </div>
                                    <button class="read-btn" onclick="openArticleModal('.$p_id.')">
                                        اقرأ المزيد <i class="fa-solid fa-arrow-left"></i>
                                    </button>
                                </div>
                            </div>
                        </div>';
                    }
                } else {
                    echo '<div style="text-align: center; grid-column: 1/-1; padding: 40px; color: #64748b; font-weight: 700;">لا توجد منشورات حالياً (No published posts available)</div>';
                }
                ?>
            </div>

        </section>
    </main>

    <!-- SEND OUTREACH POST POPUP MODAL -->
    <div id="sendOutreachModal" class="send-outreach-modal">
        <div class="send-modal-box">
            <span class="close-send-modal" onclick="closeSendModal()"><i class="fa-solid fa-xmark"></i></span>
            <div style="text-align: center; margin-bottom: 25px;">
                <h2 style="margin: 0; color: #0b1c3c; font-size: 24px; font-weight: 900;">إرسال مشاركة جديدة (Submit Outreach)</h2>
                <p style="margin: 5px 0 0; color: #64748b; font-size: 13px;">شارك مقالك أو قصتك ليتم نشرها بعد مراجعة الإدارة</p>
            </div>

            <form action="outreach.php" method="POST">
                <input type="hidden" name="submit_user_outreach" value="1">

                <div class="send-form-group">
                    <label>عنوان المشاركة (Title) *</label>
                    <input type="text" name="title" placeholder="أدخل العنوان هنا..." required>
                </div>

                <div class="form-row-2">
                    <div class="send-form-group">
                        <label>التصنيف (Category) *</label>
                        <select name="category" required>
                            <option value="Article">مقال (Article)</option>
                            <option value="Story">قصة (Story)</option>
                            <option value="Poem">قصيدة (Poem)</option>
                        </select>
                    </div>
                    <div class="send-form-group">
                        <label>رابط صورة الغلاف (Thumbnail URL)</label>
                        <input type="url" name="thumbnail" placeholder="https://example.com/image.jpg">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="send-form-group">
                        <label>اسم الكاتب (Author Name) *</label>
                        <input type="text" name="author_name" placeholder="اسم الكاتب..." required>
                    </div>
                    <div class="send-form-group">
                        <label>صفة الكاتب (Author Role / Wing)</label>
                        <input type="text" name="author_role" placeholder="طالب / عضو...">
                    </div>
                </div>

                <div class="send-form-group">
                    <label>رابط صورة الكاتب الشخصية (Author Photo URL)</label>
                    <input type="url" name="author_photo" placeholder="https://example.com/author.jpg">
                </div>

                <div class="send-form-group">
                    <label>المحتوى الكامل (Content) *</label>
                    <textarea name="content" rows="6" placeholder="اكتب النص كاملاً هنا..." required></textarea>
                </div>

                <button type="submit" class="submit-btn" style="background:#0b1c3c; color:#fff;">
                    <i class="fa-solid fa-paper-plane"></i> إرسال للمراجعة (Submit)
                </button>
            </form>
        </div>
    </div>

    <!-- ARTICLE READING POPUP MODAL -->
    <div id="articleModal" class="article-modal-overlay">
        <div class="article-modal-box">
            <span class="close-article-modal" onclick="closeArticleModal()"><i class="fa-solid fa-xmark"></i></span>
            <img id="mImg" src="" alt="Post Thumbnail" class="modal-post-img">
            <span id="mCategory" class="modal-post-category"></span>
            <h2 id="mTitle" class="modal-post-title"></h2>
            
            <div class="modal-author-wrap">
                <img id="mAuthorImg" src="" alt="Author" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #fbbc05;">
                <div>
                    <h4 id="mAuthorName" style="margin: 0; font-size: 16px; font-weight: 800; color: #0b1c3c;"></h4>
                    <span id="mAuthorRole" style="font-size: 12px; color: #94a3b8; font-weight: 600;"></span>
                </div>
            </div>

            <div id="mContent" class="modal-post-body"></div>
        </div>
    </div>

    <!-- ഫൂട്ടർ (Strict LTR English Style) -->
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

            <div id="wing-login" class="login-form active-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=wing">Continue to Wing Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>

            <div id="class-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=class">Continue to Class Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>

            <div id="student-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=student">Continue to Student Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>
            <div id="admin-login" class="login-form"><a class="submit-btn" style="display:block; text-align:center; text-decoration:none;" href="/portals?role=admin">Continue to Admin Portal <i class="fa-solid fa-arrow-right-to-bracket"></i></a></div>
        </div>
    </div> 

    <!-- ജാവാസ്ക്രിപ്റ്റ് ലോജിക് -->
    <script>
        const postsData = <?php echo json_encode($all_posts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function filterPosts(cat, btn) {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const cards = document.querySelectorAll('.post-item');
            const grid = document.getElementById('postsGrid');
            let visibleCount = 0;

            cards.forEach(card => {
                if (cat === 'all' || card.getAttribute('data-category').toLowerCase() === cat.toLowerCase()) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 1) {
                grid.classList.add('single-item');
            } else {
                grid.classList.remove('single-item');
            }
        }

        // Article Modal
        function openArticleModal(id) {
            const post = postsData.find(p => p.id == id);
            if (post) {
                document.getElementById('mTitle').innerText = post.title;
                document.getElementById('mCategory').innerText = post.category;
                document.getElementById('mContent').innerText = post.content;
                document.getElementById('mImg').src = post.thumbnail ? post.thumbnail : 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&auto=format&fit=crop&q=60';
                document.getElementById('mAuthorName').innerText = post.author_name;
                document.getElementById('mAuthorRole').innerText = post.author_role || 'Author';
                document.getElementById('mAuthorImg').src = post.author_photo ? post.author_photo : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                
                document.getElementById('articleModal').classList.add('show');
            }
        }
        function closeArticleModal() {
            document.getElementById('articleModal').classList.remove('show');
        }

        // Send Outreach Modal
        function openSendModal() {
            document.getElementById('sendOutreachModal').classList.add('show');
        }
        function closeSendModal() {
            document.getElementById('sendOutreachModal').classList.remove('show');
        }

        window.addEventListener('click', (e) => {
            const aModal = document.getElementById('articleModal');
            const sModal = document.getElementById('sendOutreachModal');
            if (e.target === aModal) closeArticleModal();
            if (e.target === sModal) closeSendModal();
        });

        document.addEventListener("DOMContentLoaded", function() {
            const visibleCards = document.querySelectorAll('.post-item');
            const grid = document.getElementById('postsGrid');
            if (visibleCards.length === 1) {
                grid.classList.add('single-item');
            }

            // Mobile Hamburger
            const menuBtn = document.getElementById('mobile-menu-btn');
            const navbar = document.getElementById('navbar');
            if(menuBtn && navbar) {
                menuBtn.addEventListener('click', () => {
                    navbar.classList.toggle('active-menu');
                });
            }

            // Portal Login Modal
            const portalBtns = document.querySelectorAll('.portal-btn, .open-portal-modal');
            const portalModal = document.getElementById('portalModal');
            const closeModal = document.querySelector('.close-modal');

            if (portalBtns.length > 0 && portalModal) {
                portalBtns.forEach(btn => {
                    btn.addEventListener('click', (e) => { 
                        e.preventDefault(); 
                        portalModal.classList.add('show'); 
                    });
                });
                closeModal.addEventListener('click', () => portalModal.classList.remove('show'));
                window.addEventListener('click', (e) => { 
                    if (e.target === portalModal) portalModal.classList.remove('show'); 
                });
            }

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