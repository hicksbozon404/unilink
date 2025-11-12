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

// Get conversation ID from URL
$conversationId = $_GET['conversation'] ?? null;
$itemId = $_GET['item'] ?? null;

// Handle new message
if ($_POST['action'] ?? '' === 'send_message') {
    if ($conversationId) {
        $stmt = $pdo->prepare("INSERT INTO marketplace_messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$conversationId, $userId, trim($_POST['message'])]);
        
        // Update conversation timestamp
        $pdo->prepare("UPDATE marketplace_conversations SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?")->execute([$conversationId]);
        
        header('Location: chat.php?conversation=' . $conversationId);
        exit;
    }
}

// Handle starting new conversation from item page
if ($itemId && !$conversationId) {
    // Get item details
    $stmt = $pdo->prepare("SELECT user_id as seller_id, title, price FROM marketplace_items WHERE item_id = ?");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    
    if ($item) {
        // Check if user is trying to message themselves
        if ($item['seller_id'] == $userId) {
            $error = "You cannot message yourself about your own item.";
        } else {
            // Check if conversation already exists
            $stmt = $pdo->prepare("SELECT conversation_id FROM marketplace_conversations WHERE item_id = ? AND buyer_id = ?");
            $stmt->execute([$itemId, $userId]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Redirect to existing conversation
                header('Location: chat.php?conversation=' . $existing['conversation_id']);
                exit;
            } else {
                // Create new conversation
                $stmt = $pdo->prepare("INSERT INTO marketplace_conversations (item_id, buyer_id, seller_id) VALUES (?, ?, ?)");
                $stmt->execute([$itemId, $userId, $item['seller_id']]);
                $conversationId = $pdo->lastInsertId();
                
                // Redirect to the new conversation
                header('Location: chat.php?conversation=' . $conversationId);
                exit;
            }
        }
    } else {
        $error = "Item not found.";
    }
}

// Get conversations list for sidebar
$conversations = $pdo->prepare("
    SELECT c.*, i.title as item_title, i.price, i.image_path, 
           u1.full_names as seller_name, u2.full_names as buyer_name,
           (SELECT message_text FROM marketplace_messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
           (SELECT COUNT(*) FROM marketplace_messages m WHERE m.conversation_id = c.conversation_id AND m.sender_id != ? AND m.is_read = FALSE) as unread_count
    FROM marketplace_conversations c
    LEFT JOIN marketplace_items i ON c.item_id = i.item_id
    LEFT JOIN users u1 ON c.seller_id = u1.user_id
    LEFT JOIN users u2 ON c.buyer_id = u2.user_id
    WHERE c.buyer_id = ? OR c.seller_id = ?
    ORDER BY c.updated_at DESC
");
$conversations->execute([$userId, $userId, $userId]);
$conversationsList = $conversations->fetchAll();

// Get current conversation details and messages
$currentConversation = null;
$messages = [];

if ($conversationId) {
    // Get conversation details
    $stmt = $pdo->prepare("
        SELECT c.*, i.title as item_title, i.price, i.image_path, i.item_id,
               u1.full_names as seller_name, u2.full_names as buyer_name,
               i.user_id as item_seller_id
        FROM marketplace_conversations c
        LEFT JOIN marketplace_items i ON c.item_id = i.item_id
        LEFT JOIN users u1 ON c.seller_id = u1.user_id
        LEFT JOIN users u2 ON c.buyer_id = u2.user_id
        WHERE c.conversation_id = ? AND (c.buyer_id = ? OR c.seller_id = ?)
    ");
    $stmt->execute([$conversationId, $userId, $userId]);
    $currentConversation = $stmt->fetch();
    
    if ($currentConversation) {
        // Mark messages as read
        $pdo->prepare("UPDATE marketplace_messages SET is_read = TRUE WHERE conversation_id = ? AND sender_id != ?")->execute([$conversationId, $userId]);
        
        // Get messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_names as sender_name
            FROM marketplace_messages m
            LEFT JOIN users u ON m.sender_id = u.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversationId]);
        $messages = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Messages</title>
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
            overflow: hidden;
        }

        /* ---------- MOBILE FIRST LAYOUT ---------- */
        .app-container {
            display: flex;
            height: 100vh;
            position: relative;
        }

        /* ---------- SIDEBAR (Hidden on mobile by default) ---------- */
        .sidebar {
            width: 400px;
            background: var(--bg-card);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 50;
        }

        /* ---------- CHAT AREA ---------- */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-card);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ---------- HEADER ---------- */
        .header {
            position: sticky;
            top: 0;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            z-index: 40;
            padding: 1rem 1.5rem;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .theme-btn, .menu-btn {
            background: none;
            border: none;
            color: var(--text-main);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .theme-btn:hover, .menu-btn:hover {
            background: var(--bg-body);
        }

        .nav-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            padding: 0.6rem 1.25rem;
            border-radius: 9999px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        /* ---------- SIDEBAR CONTENT ---------- */
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg-card);
        }

        .sidebar-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 0.5rem;
            border: 1px solid transparent;
        }

        .conversation-item:hover {
            background: var(--bg-body);
            border-color: var(--border);
        }

        .conversation-item.active {
            background: var(--primary-glow);
            border-color: var(--primary);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            flex-shrink: 0;
            font-size: 1.1rem;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
        }

        .conversation-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .conversation-time {
            color: var(--text-light);
            font-size: 0.75rem;
        }

        .conversation-preview {
            color: var(--text-subtle);
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-item-title {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        .unread-badge {
            background: var(--accent);
            color: #fff;
            padding: 0.1rem 0.5rem;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* ---------- CHAT HEADER ---------- */
        .chat-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg-card);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            background: none;
            border: none;
            color: var(--text-main);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
        }

        .back-btn:hover {
            background: var(--bg-body);
        }

        .chat-user-info {
            flex: 1;
        }

        .chat-user-info h3 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }

        .chat-user-info p {
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        .item-price {
            font-weight: 700;
            color: var(--primary);
        }

        .user-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
        }

        /* ---------- MESSAGES AREA ---------- */
        .messages-area {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: var(--bg-body);
        }

        .message {
            max-width: 85%;
            padding: 1rem 1.25rem;
            border-radius: 1.25rem;
            position: relative;
            animation: messageSlideIn 0.3s ease-out;
            word-wrap: break-word;
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .message.sent {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            align-self: flex-end;
            border-bottom-right-radius: 0.5rem;
        }

        .message.received {
            background: var(--bg-card);
            color: var(--text-main);
            align-self: flex-start;
            border-bottom-left-radius: 0.5rem;
            border: 1px solid var(--border);
        }

        .message-content {
            line-height: 1.4;
            font-size: 0.95rem;
        }

        .message-time {
            font-size: 0.7rem;
            opacity: 0.8;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .message.sent .message-time {
            text-align: right;
            justify-content: flex-end;
        }

        .message.received .message-time {
            color: var(--text-light);
        }

        /* ---------- MESSAGE INPUT ---------- */
        .message-input {
            padding: 1rem 1.5rem;
            background: var(--bg-card);
            border-top: 1px solid var(--border);
        }

        .input-group {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            max-width: 100%;
        }

        .message-textarea {
            flex: 1;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border);
            border-radius: 1.5rem;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 1rem;
            resize: none;
            min-height: 56px;
            max-height: 120px;
            font-family: inherit;
            transition: all 0.3s;
            line-height: 1.4;
        }

        .message-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .send-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        .send-btn:disabled {
            background: var(--text-light);
            cursor: not-allowed;
            transform: none;
        }

        /* ---------- EMPTY STATES ---------- */
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            color: var(--text-subtle);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.6;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .empty-state p {
            max-width: 400px;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        /* ---------- MOBILE RESPONSIVE ---------- */
        @media (max-width: 768px) {
            .app-container {
                height: 100vh;
                height: 100dvh; /* Dynamic viewport height for mobile */
            }

            .sidebar {
                position: absolute;
                top: 0;
                left: 0;
                bottom: 0;
                width: 100%;
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .chat-area {
                width: 100%;
            }

            .back-btn {
                display: flex;
            }

            .menu-btn {
                display: flex;
            }

            .message {
                max-width: 90%;
            }

            .messages-area {
                padding: 0.75rem;
            }

            .message-input {
                padding: 1rem;
            }

            .input-group {
                gap: 0.75rem;
            }

            /* Improve touch targets */
            .conversation-item,
            .theme-btn,
            .menu-btn,
            .back-btn,
            .send-btn {
                min-height: 44px;
            }

            .message-textarea {
                min-height: 50px;
            }
        }

        @media (max-width: 480px) {
            .header {
                padding: 1rem;
            }

            .logo {
                font-size: 1.25rem;
            }

            .nav-btn {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }

            .chat-header {
                padding: 1rem;
            }

            .messages-area {
                padding: 0.5rem;
            }

            .message-input {
                padding: 0.75rem;
            }

            .message {
                max-width: 95%;
                padding: 0.875rem 1rem;
            }

            .empty-state {
                padding: 1rem;
            }

            .empty-state i {
                font-size: 3rem;
            }
        }

        /* ---------- DARK MODE IMPROVEMENTS ---------- */
        .dark .message.received {
            background: var(--bg-card);
            border-color: var(--border);
        }

        .dark .conversation-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* ---------- SCROLLBAR STYLING ---------- */
        .conversations-list::-webkit-scrollbar,
        .messages-area::-webkit-scrollbar {
            width: 6px;
        }

        .conversations-list::-webkit-scrollbar-track,
        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .conversations-list::-webkit-scrollbar-thumb,
        .messages-area::-webkit-scrollbar-thumb {
            background: var(--text-light);
            border-radius: 3px;
        }

        .conversations-list::-webkit-scrollbar-thumb:hover,
        .messages-area::-webkit-scrollbar-thumb:hover {
            background: var(--text-subtle);
        }

        /* ---------- LOADING ANIMATION ---------- */
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--text-light);
            animation: typingAnimation 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typingAnimation {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-comments"></i> Messages</h2>
        </div>
        <div class="conversations-list">
            <?php if (empty($conversationsList)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No Messages Yet</h3>
                    <p>Start a conversation by contacting a seller about their item.</p>
                    <a href="market.php" class="nav-btn" style="margin-top:1rem;">
                        <i class="fas fa-store"></i> Browse Marketplace
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($conversationsList as $conv): 
                    $otherPerson = $conv['seller_id'] == $userId ? $conv['buyer_name'] : $conv['seller_name'];
                    $lastMessage = $conv['last_message'] ?: "Start conversation...";
                    $lastMessageTime = date('M j, g:i A', strtotime($conv['updated_at']));
                    $isSeller = $conv['seller_id'] == $userId;
                ?>
                    <div class="conversation-item <?= $conversationId == $conv['conversation_id'] ? 'active' : '' ?>" 
                         onclick="openConversation(<?= $conv['conversation_id'] ?>)">
                        <div class="conversation-avatar">
                            <?= strtoupper(substr($otherPerson, 0, 1)) ?>
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-header">
                                <div class="conversation-name">
                                    <?= htmlspecialchars($otherPerson) ?>
                                    <?php if ($isSeller): ?>
                                        <small style="color:var(--success);font-size:.7rem;"> (Buyer)</small>
                                    <?php else: ?>
                                        <small style="color:var(--primary);font-size:.7rem;"> (Seller)</small>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-time"><?= date('g:i A', strtotime($conv['updated_at'])) ?></div>
                            </div>
                            <div class="conversation-preview"><?= htmlspecialchars($lastMessage) ?></div>
                            <div class="conversation-item-title"><?= htmlspecialchars($conv['item_title']) ?> • K<?= number_format($conv['price'], 2) ?></div>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <div class="unread-badge"><?= $conv['unread_count'] ?> new</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-area" id="chatArea">
        <!-- HEADER -->
        <header class="header">
            <div class="nav">
                <button class="menu-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="market.php" class="logo">UniLink</a>
                <div class="nav-actions">
                    <button onclick="toggleTheme()" class="theme-btn">
                        <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    </button>
                    <a href="market.php" class="nav-btn">
                        <i class="fas fa-store"></i> Marketplace
                    </a>
                </div>
            </div>
        </header>

        <?php if ($currentConversation): 
            $otherPerson = $currentConversation['seller_id'] == $userId ? $currentConversation['buyer_name'] : $currentConversation['seller_name'];
            $isSeller = $currentConversation['seller_id'] == $userId;
        ?>
            <!-- CHAT HEADER -->
            <div class="chat-header">
                <button class="back-btn" onclick="closeConversation()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="conversation-avatar">
                    <?= strtoupper(substr($otherPerson, 0, 1)) ?>
                </div>
                <div class="chat-user-info">
                    <h3><?= htmlspecialchars($otherPerson) ?></h3>
                    <p>About: <?= htmlspecialchars($currentConversation['item_title']) ?> • <span class="item-price">K<?= number_format($currentConversation['price'], 2) ?></span></p>
                </div>
                <div class="user-status">
                    <div class="status-dot"></div>
                    <span>Online</span>
                </div>
            </div>

            <!-- MESSAGES -->
            <div class="messages-area" id="messagesArea">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-medical"></i>
                        <h3>Start a Conversation</h3>
                        <p>Send a message to discuss this item. Be respectful and clear about your questions or offers.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?= $msg['sender_id'] == $userId ? 'sent' : 'received' ?>">
                            <div class="message-content"><?= htmlspecialchars($msg['message_text']) ?></div>
                            <div class="message-time">
                                <?= date('g:i A', strtotime($msg['created_at'])) ?>
                                <?php if ($msg['sender_id'] == $userId && $msg['is_read']): ?>
                                    <i class="fas fa-check-double"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- MESSAGE INPUT -->
            <form method="POST" class="message-input" id="messageForm">
                <input type="hidden" name="action" value="send_message">
                <div class="input-group">
                    <textarea name="message" class="message-textarea" placeholder="Type your message..." required oninput="autoResize(this)" id="messageInput"></textarea>
                    <button type="submit" class="send-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>

        <?php else: ?>
            <!-- NO CONVERSATION SELECTED -->
            <div class="empty-state">
                <i class="fas fa-comments"></i>
                <h3>Your Messages</h3>
                <p>Select a conversation from the sidebar or start a new one by contacting a seller.</p>
                <div style="display:flex;gap:1rem;margin-top:1.5rem;flex-wrap:wrap;justify-content:center;">
                    <a href="market.php" class="nav-btn">
                        <i class="fas fa-store"></i> Browse Marketplace
                    </a>
                    <a href="upload_to_market.php" class="nav-btn" style="background:var(--success);">
                        <i class="fas fa-plus"></i> List New Item
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // THEME
    const html = document.documentElement;
    const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
    if (theme==='dark') html.classList.add('dark');
    
    function toggleTheme(){
        html.classList.toggle('dark');
        localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    }

    // MOBILE NAVIGATION
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }

    function openConversation(conversationId) {
        window.location.href = 'chat.php?conversation=' + conversationId;
        // On mobile, sidebar will auto-close due to page reload
    }

    function closeConversation() {
        window.location.href = 'chat.php';
    }

    // AUTO-RESIZE TEXTAREA
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    // SCROLL TO BOTTOM OF MESSAGES
    function scrollToBottom() {
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    }

    // ENTER KEY TO SEND (SHIFT+ENTER FOR NEW LINE)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            const textarea = document.getElementById('messageInput');
            if (document.activeElement === textarea && textarea.value.trim()) {
                e.preventDefault();
                document.getElementById('messageForm').submit();
            }
        }
    });

    // AUTO-FOCUS AND SCROLL ON LOAD
    document.addEventListener('DOMContentLoaded', function() {
        scrollToBottom();
        
        const textarea = document.getElementById('messageInput');
        if (textarea) {
            textarea.focus();
        }

        // Handle mobile view based on conversation state
        const isMobile = window.innerWidth <= 768;
        const hasConversation = <?= $currentConversation ? 'true' : 'false' ?>;
        
        if (isMobile && hasConversation) {
            document.getElementById('sidebar').classList.remove('active');
        }
    });

    // HANDLE MOBILE ORIENTATION CHANGES
    window.addEventListener('resize', function() {
        scrollToBottom();
    });

    // IMPROVE TOUCH EXPERIENCE
    document.addEventListener('touchstart', function() {}, { passive: true });

    // ADD SMOOTH TRANSITIONS FOR MESSAGES
    const observerOptions = {
        threshold: 0.1
    };

    const messageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animationPlayState = 'running';
            }
        });
    }, observerOptions);

    // Observe new messages for animation
    document.addEventListener('DOMContentLoaded', () => {
        const messages = document.querySelectorAll('.message');
        messages.forEach(message => {
            message.style.animationPlayState = 'paused';
            messageObserver.observe(message);
        });
    });
</script>

</body>
</html>