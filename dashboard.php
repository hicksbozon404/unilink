<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId   = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');
$email    = htmlspecialchars($_SESSION['email'] ?? 'student@unilink.edu');

// ---------- DB ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ---------- VAULT (latest 5) ----------
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 5");
$stmt->execute([$userId]);
$vaultFiles = $stmt->fetchAll();

// ---------- GET USER STATS ----------
// Marketplace items count
$marketItems = $pdo->prepare("SELECT COUNT(*) as count FROM marketplace_items WHERE user_id = ?");
$marketItems->execute([$userId]);
$itemsCount = $marketItems->fetch()['count'];

// Unread messages count
$unreadMsgs = $pdo->prepare("
    SELECT COUNT(*) as count FROM marketplace_messages m 
    JOIN marketplace_conversations c ON m.conversation_id = c.conversation_id 
    WHERE (c.buyer_id = ? OR c.seller_id = ?) AND m.sender_id != ? AND m.is_read = FALSE
");
$unreadMsgs->execute([$userId, $userId, $userId]);
$unreadCount = $unreadMsgs->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Dashboard</title>

    <!-- Fonts + Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ---------- VARIABLES ---------- */
        :root {
            --primary: #06b6d4;
            --primary-hover: #0e7490;
            --primary-glow: #06b6d433;
            --secondary: #8b5cf6;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-glass: rgba(255,255,255,0.15);
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            
            --text-main: #1e293b;
            --text-subtle: #64748b;
            --text-light: #94a3b8;
            
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            
            --shadow-sm: 0 4px 6px -1px rgba(0,0,0,.1);
            --shadow-md: 0 10px 15px -3px rgba(0,0,0,.1);
            --shadow-lg: 0 25px 50px -12px rgba(0,0,0,.15);
            --shadow-xl: 0 35px 60px -15px rgba(0,0,0,.2);
            
            --transition: .35s cubic-bezier(.2,.8,.2,1);
            --transition-slow: .5s cubic-bezier(.2,.8,.2,1);
        }

        :root.dark {
            --primary: #41e1ff;
            --primary-hover: #06b6d4;
            --primary-glow: #41e1ff33;
            --secondary: #a78bfa;
            --accent: #fbbf24;
            
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-glass: rgba(30,41,59,0.3);
            --bg-gradient: linear-gradient(135deg, #1e3a8a 0%, #7e22ce 100%);
            
            --text-main: #f1f5f9;
            --text-subtle: #94a3b8;
            --text-light: #64748b;
            
            --border: #334155;
            --border-light: #1e293b;
        }

        /* ---------- BASE STYLES ---------- */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
            transition: all var(--transition);
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: auto;
            padding: 0 1.5rem;
        }

        a {
            color: var(--primary);
            text-decoration: none;
            transition: color .2s;
        }

        a:hover {
            color: var(--primary-hover);
        }

        /* ---------- GLASS MORPHISM EFFECT ---------- */
        .glass {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* ---------- HEADER ---------- */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .logo i {
            font-size: 1.5rem;
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-main);
            cursor: pointer;
            padding: .5rem;
            border-radius: 50%;
            transition: all .3s;
        }

        .theme-toggle:hover {
            background: var(--bg-card);
            color: var(--primary);
        }

        .theme-toggle svg {
            width: 22px;
            height: 22px;
        }

        :root:not(.dark) .moon { display: none; }
        :root.dark .sun { display: none; }

        .nav-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            padding: .6rem 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            border: none;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .nav-btn.secondary {
            background: var(--secondary);
        }

        /* ---------- HERO SECTION ---------- */
        .hero {
            padding: 4rem 0 3rem;
            position: relative;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, var(--primary-glow), transparent 50%),
                radial-gradient(circle at 80% 20%, var(--secondary)22, transparent 50%),
                radial-gradient(circle at 40% 40%, var(--accent)11, transparent 50%);
            z-index: -1;
        }

        .hero-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
            align-items: start;
        }

        @media (max-width: 992px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }
        }

        /* ---------- WELCOME CARD ---------- */
        .welcome-card {
            background: var(--bg-card);
            border-radius: 2rem;
            padding: 2.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s ease infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: -200% 0; }
            50% { background-position: 200% 0; }
        }

        .welcome-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .welcome-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .welcome-text h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: .5rem;
        }

        .welcome-text h1 span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .welcome-text p {
            color: var(--text-subtle);
            font-size: 1.1rem;
        }

        /* ---------- STATS GRID ---------- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary)08, transparent 70%);
            opacity: 0;
            transition: opacity .3s;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .stat-icon.primary { background: var(--primary)15; color: var(--primary); }
        .stat-icon.secondary { background: var(--secondary)15; color: var(--secondary); }
        .stat-icon.accent { background: var(--accent)15; color: var(--accent); }
        .stat-icon.success { background: var(--success)15; color: var(--success); }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: .25rem;
        }

        .stat-label {
            color: var(--text-subtle);
            font-size: .9rem;
            font-weight: 600;
        }

        /* ---------- QUICK ACTIONS ---------- */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .action-btn {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all .3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-main);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .action-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: .5rem;
        }

        .action-label {
            font-weight: 600;
            font-size: .9rem;
        }

        /* ---------- VAULT PREVIEW ---------- */
        .vault-preview {
            background: var(--bg-card);
            border-radius: 2rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            margin: 2rem 0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .section-badge {
            background: var(--primary);
            color: #fff;
            padding: .25rem .75rem;
            border-radius: 99px;
            font-size: .8rem;
            font-weight: 600;
        }

        .view-all {
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .5rem;
            transition: all .2s;
        }

        .view-all:hover {
            gap: .75rem;
        }

        .vault-list {
            list-style: none;
        }

        .vault-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-light);
            transition: all .2s;
        }

        .vault-item:hover {
            background: var(--bg-body);
            border-radius: .75rem;
            margin: 0 -.5rem;
            padding: 1rem .5rem;
        }

        .vault-item:last-child {
            border-bottom: none;
        }

        .vault-file {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .vault-icon {
            font-size: 1.6rem;
            color: var(--primary);
        }

        .vault-info {
            flex: 1;
        }

        .vault-name {
            font-weight: 600;
            font-size: 1rem;
            display: block;
        }

        .vault-meta {
            color: var(--text-subtle);
            font-size: .85rem;
        }

        .vault-actions {
            display: flex;
            gap: .5rem;
        }

        .btn-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: all .2s;
            opacity: .8;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-small:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        .btn-view { background: var(--primary); color: #fff; }
        .btn-delete { background: var(--error); color: #fff; }

        .empty-vault {
            text-align: center;
            padding: 3rem;
            color: var(--text-subtle);
        }

        .empty-vault i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: .6;
        }

        /* ---------- SERVICES GRID ---------- */
        .services-section {
            padding: 4rem 0;
            background: var(--bg-body);
            border-top: 1px solid var(--border);
        }

        .services-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .services-header h2 {
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .services-header p {
            color: var(--text-subtle);
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background: var(--bg-card);
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            transition: all .4s cubic-bezier(.2,.8,.2,1);
            position: relative;
            text-decoration: none;
            color: inherit;
        }

        .service-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .service-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            transition: transform .5s ease;
        }

        .service-card:hover .service-image {
            transform: scale(1.08);
        }

        .service-content {
            padding: 1.75rem;
        }

        .service-card h3 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: .5rem;
            transition: color .3s;
        }

        .service-card:hover h3 {
            color: var(--primary);
        }

        .service-card p {
            font-size: 1rem;
            color: var(--text-subtle);
        }

        /* ---------- FEATURES GRID ---------- */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .feature-card {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            transition: all .4s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .4s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .feature-card i {
            font-size: 2.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .feature-card p {
            font-size: .95rem;
            color: var(--text-subtle);
        }

        /* ---------- FOOTER ---------- */
        .footer {
            padding: 2.5rem 0;
            text-align: center;
            font-size: .9rem;
            color: var(--text-subtle);
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .hero {
                padding: 2rem 0 1rem;
            }
            
            .welcome-card {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            /* Keep header actions on a single horizontal line on small screens.
               Make them horizontally scrollable if they overflow the viewport. */
            .nav-actions {
                flex-wrap: nowrap; /* don't wrap into multiple rows */
                justify-content: flex-end; /* keep actions to the right */
                gap: .5rem;
                overflow-x: auto; /* allow horizontal scrolling if needed */
                -webkit-overflow-scrolling: touch; /* smooth scrolling on iOS */
                scrollbar-width: none; /* hide scrollbar in Firefox */
            }

            /* hide webkit scrollbar */
            .nav-actions::-webkit-scrollbar { display: none; }

            /* Ensure each action stays inline and doesn't stretch */
            .nav-actions > * {
                flex: 0 0 auto;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .vault-item {
                flex-direction: column;
                align-items: flex-start;
                gap: .75rem;
            }
            
            .vault-actions {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>

<!-- ==================== HEADER ==================== -->
<header class="header">
    <div class="container nav-content">
        <a href="dashboard.php" class="logo"><i class="fas fa-link"></i> UniLink</a>

        <div class="nav-actions">
            <button onclick="toggleTheme()" class="theme-toggle" aria-label="Toggle Theme">
                <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>

            <a href="profile.php" class="nav-btn secondary">
                <i class="fas fa-user"></i>
            </a>

            <form action="logout.php" method="post" style="margin:0;">
                <button type="submit" class="nav-btn" style="background:var(--error);">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>
</header>

<main class="container">
    <!-- ==================== HERO SECTION ==================== -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-content">
            <!-- LEFT: MAIN CONTENT -->
            <div>
                <!-- WELCOME CARD -->
                <div class="welcome-card">
                    <div class="welcome-header">
                        <div class="welcome-avatar">
                            <?= strtoupper(substr($fullName, 0, 1)) ?>
                        </div>
                        <div class="welcome-text">
                            <h1>Welcome back, <span><?= $fullName ?></span>! 👋</h1>
                            <p>Here's what's happening with your campus life today.</p>
                        </div>
                    </div>

                    <!-- STATS GRID -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon primary">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="stat-value"><?= count($vaultFiles) ?></div>
                            <div class="stat-label">Files in Vault</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon secondary">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-value"><?= $itemsCount ?></div>
                            <div class="stat-label">Marketplace Items</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon accent">
                                <i class="fas fa-comments"></i>
                            </div>
                            <div class="stat-value"><?= $unreadCount ?></div>
                            <div class="stat-label">Unread Messages</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-value">0</div>
                            <div class="stat-label">Pending Tasks</div>
                        </div>
                    </div>

                    <!-- QUICK ACTIONS -->
                    <div class="quick-actions">
                        <a href="vault.php" class="action-btn">
                            <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="action-label">Secure Vault</div>
                        </a>
                        
                        <a href="market.php" class="action-btn">
                            <div class="action-icon"><i class="fas fa-store"></i></div>
                            <div class="action-label">Marketplace</div>
                        </a>
                        
                        <a href="grades.php" class="action-btn">
                            <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="action-label">Grades</div>
                        </a>
                        
                        <a href="market_messages.php" class="action-btn">
                            <div class="action-icon"><i class="fas fa-comments"></i></div>
                            <div class="action-label">Messages</div>
                        </a>
                    </div>
                </div>

                <!-- VAULT PREVIEW -->
                <div class="vault-preview">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-shield-alt"></i> Recent Files
                        </h3>
                        <a href="vault.php" class="view-all">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (empty($vaultFiles)): ?>
                        <div class="empty-vault">
                            <i class="fas fa-inbox"></i>
                            <p>No files yet. <a href="vault.php" style="color:var(--primary);">Upload your first file →</a></p>
                        </div>
                    <?php else: ?>
                        <ul class="vault-list">
                            <?php foreach ($vaultFiles as $f):
                                $icon = str_contains($f['file_type'], 'pdf') ? 'fa-file-pdf' : 'fa-file-image';
                                $size = $f['file_size'] < 1024*1024 ? round($f['file_size']/1024,1).' KB' : round($f['file_size']/(1024*1024),1).' MB';
                            ?>
                                <li class="vault-item">
                                    <div class="vault-file">
                                        <i class="fas <?= $icon ?> vault-icon"></i>
                                        <div class="vault-info">
                                            <span class="vault-name"><?= htmlspecialchars($f['original_name']) ?></span>
                                            <small class="vault-meta"><?= $size ?> · <?= date('M j', strtotime($f['uploaded_at'])) ?></small>
                                        </div>
                                    </div>
                                    <div class="vault-actions">
                                        <a href="/unilink/uploads/vault/<?= $f['filename'] ?>" target="_blank" class="btn-small btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="vault.php?delete=<?= $f['doc_id'] ?>" class="btn-small btn-delete" title="Delete" onclick="return confirm('Delete forever?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT: FEATURES -->
            <div>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-bolt"></i>
                        <h3>Lightning Fast</h3>
                        <p>Instant access to all campus services</p>
                    </div>
                    
                    <div class="feature-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Bank-Level Security</h3>
                        <p>Your data is encrypted and protected</p>
                    </div>
                    
                    <div class="feature-card">
                        <i class="fas fa-bell"></i>
                        <h3>Smart Notifications</h3>
                        <p>Never miss important updates</p>
                    </div>
                    
                    <div class="feature-card">
                        <i class="fas fa-mobile-alt"></i>
                        <h3>Mobile First</h3>
                        <p>Works perfectly on all devices</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== SERVICES SECTION ==================== -->
    <!-- ==================== SERVICES SECTION ==================== -->
    <section class="services-section">
        <div class="services-header">
            <h2>Campus Services</h2>
            <p>Everything you need for a seamless university experience</p>
        </div>

        <div class="services-grid">
            <a href="housing.php" class="service-card">
                <img src="./images/bh.png" alt="Housing" class="service-image">
                <div class="service-content">
                    <h3>Housing & Boarding</h3>
                    <p>Find approved accommodation near campus.</p>
                </div>
            </a>

            <a href="vault.php" class="service-card">
                <img src="./images/vault.png" alt="Vault" class="service-image">
                <div class="service-content">
                    <h3>Secure Document Vault</h3>
                    <p>Store transcripts and forms safely.</p>
                </div>
            </a>

            <a href="grades.php" class="service-card">
                <img src="./images/grades.png" alt="Grades" class="service-image">
                <div class="service-content">
                    <h3>Grades & Progress</h3>
                    <p>Track your academic journey in real‑time.</p>
                </div>
            </a>

            <a href="market.php" class="service-card">
                <img src="./images/market.png" alt="Marketplace" class="service-image">
                <div class="service-content">
                    <h3>Campus Marketplace</h3>
                    <p>Buy and sell with fellow students safely.</p>
                </div>
            </a>

            <a href="clearance.php" class="service-card">
                <img src="./images/clearance.png" alt="Clearance" class="service-image">
                <div class="service-content">
                    <h3>Online Clearance</h3>
                    <p>Submit and track clearance forms digitally.</p>
                </div>
            </a>

            <a href="payments.php" class="service-card">
                <img src="./images/payments.png" alt="Payments" class="service-image">
                <div class="service-content">
                    <h3>Tuition & Payments</h3>
                    <p>Secure, hassle‑free tuition payments.</p>
                </div>
            </a>

            <a href="news.php" class="service-card">
                <img src="./images/news.png" alt="News" class="service-image">
                <div class="service-content">
                    <h3>Campus Events & News</h3>
                    <p>Stay updated with deadlines and events.</p>
                </div>
            </a>
        </div>
    </section>

<!-- ==================== FOOTER ==================== -->
<footer class="footer">
    <div class="container">© 2025 UniLink | HICKS BOZON404. All Rights Reserved.</div>
</footer>

<script>
    // ---------- THEME ----------
    const html = document.documentElement;
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (stored === 'dark' || (!stored && prefersDark)) html.classList.add('dark');
    
    function toggleTheme(){
        html.classList.toggle('dark');
        localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    }

    // ---------- ANIMATIONS ----------
    // Add subtle animations on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe cards for animation
    document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('.stat-card, .feature-card, .service-card');
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });
    });
</script>

</body>
</html>