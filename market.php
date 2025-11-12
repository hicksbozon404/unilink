<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');

// ---------- DB ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ---------- DELETE ITEM ----------
if (isset($_GET['delete_item'])) {
    $itemId = (int)$_GET['delete_item'];
    $stmt = $pdo->prepare("SELECT image_path FROM marketplace_items WHERE item_id = ? AND user_id = ?");
    $stmt->execute([$itemId, $userId]);
    $item = $stmt->fetch();
    
    if ($item) {
        if ($item['image_path'] && file_exists($item['image_path'])) {
            unlink($item['image_path']);
        }
        $pdo->prepare("DELETE FROM marketplace_items WHERE item_id = ? AND user_id = ?")->execute([$itemId, $userId]);
    }
    header('Location: market.php');
    exit;
}

// ---------- FETCH DATA ----------
// Get categories
$categories = $pdo->query("SELECT * FROM marketplace_categories ORDER BY name")->fetchAll();

// Get items with filters
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$conditionFilter = $_GET['condition'] ?? '';

$whereClause = "WHERE item_status = 'available'";
$params = [];

if ($categoryFilter) {
    $whereClause .= " AND i.category_id = ?";
    $params[] = $categoryFilter;
}

if ($searchQuery) {
    $whereClause .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($conditionFilter) {
    $whereClause .= " AND i.item_condition = ?";
    $params[] = $conditionFilter;
}

// Get items
$itemsQuery = $pdo->prepare("
    SELECT i.*, u.full_names as seller_name, c.name as category_name, c.icon as category_icon 
    FROM marketplace_items i 
    LEFT JOIN users u ON i.user_id = u.user_id 
    LEFT JOIN marketplace_categories c ON i.category_id = c.category_id 
    $whereClause 
    ORDER BY i.created_at DESC
");
$itemsQuery->execute($params);
$items = $itemsQuery->fetchAll();

// Get user's own items
$userItems = $pdo->prepare("SELECT * FROM marketplace_items WHERE user_id = ? ORDER BY created_at DESC");
$userItems->execute([$userId]);
$myItems = $userItems->fetchAll();

// Get unread messages count for badge
$unreadMsgs = $pdo->prepare("
    SELECT COUNT(*) as count FROM marketplace_messages m 
    JOIN marketplace_conversations c ON m.conversation_id = c.conversation_id 
    WHERE (c.buyer_id = ? OR c.seller_id = ?) AND m.sender_id != ? AND m.is_read = FALSE
");
$unreadMsgs->execute([$userId, $userId, $userId]);
$unreadCount = $unreadMsgs->fetch()['count'];

// Get active conversations for sidebar
$conversations = $pdo->prepare("
    SELECT c.*, i.title as item_title, i.price, 
           u1.full_names as seller_name, u2.full_names as buyer_name,
           (SELECT message_text FROM marketplace_messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
           (SELECT COUNT(*) FROM marketplace_messages m WHERE m.conversation_id = c.conversation_id AND m.sender_id != ? AND m.is_read = FALSE) as unread_count
    FROM marketplace_conversations c
    LEFT JOIN marketplace_items i ON c.item_id = i.item_id
    LEFT JOIN users u1 ON c.seller_id = u1.user_id
    LEFT JOIN users u2 ON c.buyer_id = u2.user_id
    WHERE (c.buyer_id = ? OR c.seller_id = ?)
    ORDER BY c.updated_at DESC
    LIMIT 5
");
$conversations->execute([$userId, $userId, $userId]);
$recentConversations = $conversations->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Campus Marketplace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
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
        }

        .container {
            max-width: 1400px;
            margin: auto;
            padding: 0 1.5rem;
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

        .nav {
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
        }

        .nav-right {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .theme-btn {
            background: none;
            border: none;
            color: var(--text-main);
            cursor: pointer;
            padding: .5rem;
            border-radius: 50%;
            transition: all .3s;
        }

        .theme-btn:hover {
            background: var(--bg-card);
            color: var(--primary);
        }

        .theme-btn svg {
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

        .nav-btn.accent {
            background: var(--accent);
        }

        /* ---------- HERO ---------- */
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
                radial-gradient(circle at 80% 20%, var(--secondary)22, transparent 50%);
            z-index: -1;
        }

        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            margin-bottom: 1rem;
            line-height: 1.1;
        }

        .hero h1 span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--text-subtle);
            margin-bottom: 2rem;
        }

        /* ---------- MARKET LAYOUT ---------- */
        .market-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin: 2rem 0;
        }

        @media (max-width: 1200px) {
            .market-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ---------- MARKET CONTROLS ---------- */
        .market-controls {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr auto auto auto auto;
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .controls-grid {
                grid-template-columns: 1fr;
            }
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border);
            border-radius: 1rem;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 1rem;
            transition: border .3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-subtle);
        }

        .filter-select {
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: 1rem;
            background: var(--bg-body);
            color: var(--text-main);
            min-width: 150px;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--secondary);
            color: #fff;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* ---------- ITEMS GRID ---------- */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .item-card {
            background: var(--bg-card);
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            transition: all .4s cubic-bezier(.2,.8,.2,1);
            position: relative;
        }

        .item-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .item-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            padding: .5rem 1rem;
            border-radius: 99px;
            font-size: .8rem;
            font-weight: 600;
            z-index: 2;
        }

        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .item-placeholder {
            width: 100%;
            height: 200px;
            background: var(--bg-body);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-placeholder i {
            font-size: 3rem;
            color: var(--text-subtle);
        }

        .item-content {
            padding: 1.5rem;
        }

        .item-title {
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: .5rem;
            line-height: 1.3;
        }

        .item-description {
            color: var(--text-subtle);
            font-size: .95rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .item-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .item-condition {
            background: var(--bg-body);
            padding: .5rem 1rem;
            border-radius: 99px;
            font-size: .8rem;
            font-weight: 600;
        }

        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-seller {
            color: var(--text-subtle);
            font-size: .9rem;
        }

        .item-actions {
            display: flex;
            gap: .5rem;
        }

        .btn-small {
            padding: .75rem 1rem;
            border-radius: .75rem;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .btn-small:hover {
            transform: scale(1.05);
        }

        .btn-contact {
            background: var(--success);
            color: #fff;
        }

        .btn-delete {
            background: var(--error);
            color: #fff;
        }

        /* ---------- CHATS SIDEBAR ---------- */
        .chats-sidebar {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .sidebar-header h3 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .unread-badge {
            background: var(--accent);
            color: #fff;
            padding: .25rem .75rem;
            border-radius: 99px;
            font-size: .8rem;
            font-weight: 600;
        }

        .conversations-list {
            space-y: 1rem;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 1rem;
            border: 1px solid var(--border-light);
            cursor: pointer;
            transition: all .3s;
            margin-bottom: .75rem;
        }

        .conversation-item:hover {
            background: var(--bg-body);
            border-color: var(--primary);
        }

        .conversation-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            flex-shrink: 0;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            font-size: .9rem;
            margin-bottom: .25rem;
        }

        .conversation-preview {
            color: var(--text-subtle);
            font-size: .8rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-meta {
            text-align: right;
            font-size: .75rem;
            color: var(--text-light);
        }

        .dot-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            margin-left: auto;
        }

        .empty-conversations {
            text-align: center;
            padding: 2rem;
            color: var(--text-subtle);
        }

        .empty-conversations i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
            opacity: .6;
        }

        /* ---------- TABS ---------- */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border);
            margin: 2rem 0;
        }

        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all .3s;
            color: var(--text-subtle);
        }

        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }

        .tab-badge {
            background: var(--primary);
            color: #fff;
            padding: .1rem .5rem;
            border-radius: 99px;
            font-size: .7rem;
            margin-left: .5rem;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ---------- EMPTY STATES ---------- */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-subtle);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            display: block;
            opacity: .6;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: .5rem;
            color: var(--text-main);
        }

        /* ---------- FOOTER ---------- */
        .footer {
            padding: 2rem 0;
            text-align: center;
            color: var(--text-subtle);
            border-top: 1px solid var(--border);
            margin-top: 4rem;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .hero {
                padding: 2rem 0 1rem;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .nav-right {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <a href="dashboard.php" class="logo">UniLink</a>
        <div class="nav-right">
            <button onclick="toggleTheme()" class="theme-btn">
                <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
            <a href="dashboard.php" class="nav-btn secondary">Dashboard</a>
            <form action="logout.php" method="post" style="margin:0;"><button type="submit" class="nav-btn" style="background:var(--error);">Logout</button></form>
        </div>
    </div>
</header>

<main class="container">
    <!-- HERO -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-content">
            <h1>Campus <span>Marketplace</span></h1>
            <p>Buy, sell, and trade with fellow students. Textbooks, electronics, furniture, and more!</p>
        </div>
    </section>

    <!-- MARKET LAYOUT -->
    <div class="market-layout">
        <!-- MAIN CONTENT -->
        <div>
            <!-- MARKET CONTROLS -->
            <div class="market-controls">
                <form method="GET" class="controls-grid">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search items..." value="<?= htmlspecialchars($searchQuery) ?>">
                    </div>
                    <select name="category" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $categoryFilter == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="condition" class="filter-select" onchange="this.form.submit()">
                        <option value="">Any Condition</option>
                        <option value="new" <?= $conditionFilter == 'new' ? 'selected' : '' ?>>New</option>
                        <option value="like_new" <?= $conditionFilter == 'like_new' ? 'selected' : '' ?>>Like New</option>
                        <option value="good" <?= $conditionFilter == 'good' ? 'selected' : '' ?>>Good</option>
                        <option value="fair" <?= $conditionFilter == 'fair' ? 'selected' : '' ?>>Fair</option>
                    </select>
                    <a href="upload_to_market.php" class="btn-primary">
                        <i class="fas fa-plus"></i> List Item
                    </a>
                    <a href="market_messages.php" class="btn-secondary">
                        <i class="fas fa-comments"></i> All Messages
                    </a>
                </form>
            </div>

            <!-- TABS -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('browse')">
                    Browse Items
                    <span class="tab-badge"><?= count($items) ?></span>
                </div>
                <div class="tab" onclick="switchTab('myItems')">
                    My Items
                    <span class="tab-badge"><?= count($myItems) ?></span>
                </div>
            </div>

            <!-- BROWSE ITEMS TAB -->
            <div id="browse" class="tab-content active">
                <?php if (empty($items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Items Found</h3>
                        <p>No items match your search criteria. Try adjusting your filters or be the first to list an item!</p>
                        <a href="upload_to_market.php" class="btn-primary" style="margin-top:1rem;">
                            <i class="fas fa-plus"></i> List Your First Item
                        </a>
                    </div>
                <?php else: ?>
                    <div class="items-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="item-card">
                                <div class="item-badge"><?= htmlspecialchars($item['category_name']) ?></div>
                                <?php if ($item['image_path']): ?>
                                    <img src="<?= $item['image_path'] ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image">
                                <?php else: ?>
                                    <div class="item-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="item-content">
                                    <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
                                    <p class="item-description"><?= htmlspecialchars($item['description']) ?></p>
                                    <div class="item-meta">
                                        <div class="item-price">K<?= number_format($item['price'], 2) ?></div>
                                        <div class="item-condition"><?= ucfirst(str_replace('_', ' ', $item['item_condition'])) ?></div>
                                    </div>
                                    <div class="item-footer">
                                        <div class="item-seller">By <?= htmlspecialchars($item['seller_name']) ?></div>
                                        <div class="item-actions">
                                            <a href="chat.php?item=<?= $item['item_id'] ?>" class="btn-small btn-contact">
                                                <i class="fas fa-message"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- MY ITEMS TAB -->
            <div id="myItems" class="tab-content">
                <?php if (empty($myItems)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>No Items Listed</h3>
                        <p>You haven't listed any items yet. Start selling to the campus community!</p>
                        <a href="upload_to_market.php" class="btn-primary" style="margin-top:1rem;">
                            <i class="fas fa-plus"></i> List Your First Item
                        </a>
                    </div>
                <?php else: ?>
                    <div class="items-grid">
                        <?php foreach ($myItems as $item): ?>
                            <div class="item-card">
                                <div class="item-badge"><?= $item['item_status'] == 'sold' ? 'SOLD' : htmlspecialchars($categories[$item['category_id']-1]['name'] ?? 'Unknown') ?></div>
                                <?php if ($item['image_path']): ?>
                                    <img src="<?= $item['image_path'] ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image">
                                <?php else: ?>
                                    <div class="item-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="item-content">
                                    <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
                                    <p class="item-description"><?= htmlspecialchars($item['description']) ?></p>
                                    <div class="item-meta">
                                        <div class="item-price">K<?= number_format($item['price'], 2) ?></div>
                                        <div class="item-condition"><?= ucfirst(str_replace('_', ' ', $item['item_condition'])) ?></div>
                                    </div>
                                    <div class="item-footer">
                                        <div class="item-seller">Listed <?= date('M j', strtotime($item['created_at'])) ?></div>
                                        <div class="item-actions">
                                            <a href="?delete_item=<?= $item['item_id'] ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this item? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHATS SIDEBAR -->
        <div class="chats-sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-comments"></i> Recent Chats</h3>
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?= $unreadCount ?> unread</span>
                <?php endif; ?>
            </div>

            <div class="conversations-list">
                <?php if (empty($recentConversations)): ?>
                    <div class="empty-conversations">
                        <i class="fas fa-comment-slash"></i>
                        <p>No active conversations</p>
                        <small>Start chatting with buyers or sellers!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentConversations as $conv): 
                        $otherPerson = $conv['seller_id'] == $userId ? $conv['buyer_name'] : $conv['seller_name'];
                        $lastMessage = $conv['last_message'] ?: "Start conversation...";
                    ?>
                        <div class="conversation-item" onclick="location.href='market_messages.php?conversation=<?= $conv['conversation_id'] ?>'">
                            <div class="conversation-avatar">
                                <?= strtoupper(substr($otherPerson, 0, 1)) ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-name"><?= htmlspecialchars($otherPerson) ?></div>
                                <div class="conversation-preview"><?= htmlspecialchars($lastMessage) ?></div>
                            </div>
                            <div class="conversation-meta">
                                <div><?= date('M j', strtotime($conv['updated_at'])) ?></div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <div class="dot-indicator"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <a href="market_messages.php" class="btn-primary" style="width:100%;text-align:center;margin-top:1rem;">
                <i class="fas fa-inbox"></i> View All Messages
            </a>
        </div>
    </div>
</main>

<footer class="footer">
    <div class="container">© 2025 UniLink | HICKS BOZON404.</div>
</footer>

<script>
    // THEME
    const html = document.documentElement;
    const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
    if (theme==='dark') html.classList.add('dark');
    function toggleTheme(){
        html.classList.toggle('dark');
        localStorage.setItem('theme',html.classList.contains('dark')?'dark':'light');
    }

    // TABS
    function switchTab(tabName) {
        document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        event.target.classList.add('active');
        document.getElementById(tabName).classList.add('active');
    }

    // AUTO-SUBMIT FORMS ON FILTER CHANGE
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // ADD ANIMATIONS
    document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('.item-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>

</body>
</html>