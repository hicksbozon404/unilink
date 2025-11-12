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

// Handle new message
if ($_POST['action'] ?? '' === 'send_message') {
    if ($conversationId) {
        $stmt = $pdo->prepare("INSERT INTO marketplace_messages (conversation_id, sender_id, message_text) VALUES (?, ?, ?)");
        $stmt->execute([$conversationId, $userId, trim($_POST['message'])]);
        
        // Update conversation timestamp
        $pdo->prepare("UPDATE marketplace_conversations SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?")->execute([$conversationId]);
        
        header('Location: market_messages.php?conversation=' . $conversationId);
        exit;
    }
}

// Get marketplace conversations list
$conversations = $pdo->prepare("
    SELECT c.*, i.title as item_title, i.price, i.image_path, i.item_id,
           u1.full_names as seller_name, u2.full_names as buyer_name,
           (SELECT message_text FROM marketplace_messages m WHERE m.conversation_id = c.conversation_id ORDER BY m.created_at DESC LIMIT 1) as last_message,
           (SELECT COUNT(*) FROM marketplace_messages m WHERE m.conversation_id = c.conversation_id AND m.sender_id != ? AND m.is_read = FALSE) as unread_count
    FROM marketplace_conversations c
    LEFT JOIN marketplace_items i ON c.item_id = i.item_id
    LEFT JOIN users u1 ON c.seller_id = u1.user_id
    LEFT JOIN users u2 ON c.buyer_id = u2.user_id
    WHERE (c.buyer_id = ? OR c.seller_id = ?)
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
    <title>UniLink | Marketplace Messages</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --p:#06b6d4;--ph:#0e7490;--pg:#06b6d433;
            --bg:#f8fafc;--card:#fff;--glass:rgba(255,255,255,.15);
            --t:#1e293b;--ts:#64748b;--b:#e2e8f0;--s:#10b981;--e:#ef4444;--w:#f59e0b;
            --sh-sm:0 4px 6px -1px rgba(0,0,0,.1);--sh-md:0 10px 15px -3px rgba(0,0,0,.1);--sh-lg:0 25px 50px -12px rgba(0,0,0,.15);
            --tr:.35s cubic-bezier(.2,.8,.2,1);
        }
        :root.dark{
            --p:#41e1ff;--ph:#06b6d4;--pg:#41e1ff33;
            --bg:#0f172a;--card:#1e293b;--glass:rgba(30,41,59,.3);
            --t:#f1f5f9;--ts:#94a3b8;--b:#334155;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;transition:all var(--tr);}

        /* HEADER */
        .header{position:sticky;top:0;background:var(--glass);backdrop-filter:blur(12px);border-bottom:1px solid var(--b);box-shadow:var(--sh-sm);z-index:1000;}
        .nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 0;max-width:1400px;margin:auto;padding-left:1.5rem;padding-right:1.5rem;}
        .logo{font-family:'Space Grotesk',sans-serif;font-size:1.75rem;font-weight:700;color:var(--p);}
        .nav-right{display:flex;gap:1rem;align-items:center;}
        .theme-btn{background:none;border:none;color:var(--t);cursor:pointer;padding:.5rem;border-radius:50%;}
        .theme-btn:hover{background:var(--card);color:var(--p);}
        .theme-btn svg{width:22px;height:22px;}
        :root:not(.dark) .moon{display:none;}
        :root.dark .sun{display:none;}
        .logout{background:var(--e);color:#fff;padding:.5rem 1rem;border-radius:99px;font-weight:600;cursor:pointer;border:none;}

        /* HERO */
        .hero{padding:3rem 0 2rem;text-align:center;background:radial-gradient(circle at 30% 70%,var(--pg),transparent 60%);}
        .hero h1{font-size:2.5rem;font-weight:900;margin-bottom:.5rem;}
        .hero h1 span{color:var(--p);}
        .hero p{color:var(--ts);max-width:600px;margin:auto;}

        /* CHAT LAYOUT */
        .chat-container{display:flex;height:calc(100vh - 200px);max-width:1400px;margin:auto;padding:0 1.5rem;border-radius:1.5rem;overflow:hidden;box-shadow:var(--sh-lg);border:1px solid var(--b);}
        
        /* SIDEBAR */
        .sidebar{width:400px;border-right:1px solid var(--b);background:var(--card);overflow-y:auto;}
        .sidebar-header{padding:1.5rem;border-bottom:1px solid var(--b);background:var(--glass);}
        .sidebar-header h2{font-size:1.25rem;font-weight:700;display:flex;align-items:center;gap:.5rem;}
        .conversations-list{overflow-y:auto;}
        .conversation-item{
            padding:1rem 1.5rem;border-bottom:1px solid var(--b);cursor:pointer;transition:all .3s;
            display:flex;align-items:center;gap:1rem;
        }
        .conversation-item:hover{background:var(--bg);}
        .conversation-item.active{background:var(--pg);border-right:3px solid var(--p);}
        .conversation-avatar{width:50px;height:50px;border-radius:50%;background:var(--p);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;flex-shrink:0;}
        .conversation-info{flex:1;min-width:0;}
        .conversation-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.25rem;}
        .conversation-name{font-weight:600;font-size:.9rem;}
        .conversation-time{color:var(--ts);font-size:.75rem;}
        .conversation-preview{color:var(--ts);font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .conversation-item-title{font-weight:600;color:var(--p);font-size:.8rem;margin-top:.25rem;}
        .unread-badge{background:var(--p);color:#fff;padding:.1rem .5rem;border-radius:99px;font-size:.7rem;font-weight:600;}

        /* CHAT AREA */
        .chat-area{flex:1;display:flex;flex-direction:column;background:var(--card);}
        
        /* CHAT HEADER */
        .chat-header{padding:1.5rem;border-bottom:1px solid var(--b);display:flex;align-items:center;justify-content:between;background:var(--glass);}
        .chat-header-info{flex:1;}
        .chat-header h3{font-weight:700;margin-bottom:.25rem;}
        .chat-header p{color:var(--ts);font-size:.9rem;}
        .item-price{font-weight:700;color:var(--p);font-size:1.1rem;}
        .back-btn{display:none;background:none;border:none;color:var(--t);cursor:pointer;padding:.5rem;margin-right:1rem;}

        /* MESSAGES AREA */
        .messages-area{flex:1;padding:1.5rem;overflow-y:auto;display:flex;flex-direction:column;gap:1rem;}
        .message{
            max-width:70%;padding:1rem 1.25rem;border-radius:1.25rem;position:relative;
            animation:fadeIn .3s ease;
        }
        .message.sent{background:var(--p);color:#fff;align-self:flex-end;border-bottom-right-radius:.5rem;}
        .message.received{background:var(--bg);color:var(--t);align-self:flex-start;border-bottom-left-radius:.5rem;}
        .message-content{line-height:1.4;}
        .message-time{font-size:.7rem;opacity:.7;margin-top:.5rem;}
        .message.sent .message-time{text-align:right;}

        /* MESSAGE INPUT */
        .message-input{padding:1.5rem;border-top:1px solid var(--b);background:var(--card);}
        .input-group{display:flex;gap:1rem;align-items:end;}
        .message-textarea{
            flex:1;padding:1rem 1.5rem;border:2px solid var(--b);border-radius:1.5rem;
            background:var(--bg);color:var(--t);font-size:1rem;resize:none;min-height:60px;max-height:120px;
            font-family:inherit;transition:border .3s;
        }
        .message-textarea:focus{outline:none;border-color:var(--p);}
        .send-btn{
            background:var(--p);color:#fff;border:none;border-radius:50%;width:50px;height:50px;
            cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;
        }
        .send-btn:hover{background:var(--ph);transform:scale(1.05);}
        .send-btn:disabled{background:var(--ts);cursor:not-allowed;transform:none;}

        /* EMPTY STATES */
        .empty-chat{flex:1;display:flex;align-items:center;justify-content:center;text-align:center;color:var(--ts);}
        .empty-chat i{font-size:4rem;margin-bottom:1rem;opacity:.5;}
        .empty-chat h3{font-size:1.5rem;margin-bottom:.5rem;}
        .empty-chat p{max-width:400px;line-height:1.6;}

        /* STATS CARDS */
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;margin:2rem 0;}
        .stat-card{
            background:var(--card);padding:1.5rem;border-radius:1rem;text-align:center;
            border:1px solid var(--b);box-shadow:var(--sh-sm);
        }
        .stat-card i{font-size:2rem;color:var(--p);margin-bottom:.5rem;}
        .stat-number{font-size:2rem;font-weight:800;color:var(--p);}
        .stat-label{color:var(--ts);font-size:.9rem;}

        /* ANIMATIONS */
        @keyframes fadeIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

        /* RESPONSIVE */
        @media(max-width:768px){
            .sidebar{width:100%;display:<?= $conversationId ? 'none' : 'block' ?>;}
            .chat-area{display:<?= $conversationId ? 'flex' : 'none' ?>;}
            .back-btn{display:block;}
            .chat-container{padding:0;border-radius:0;height:calc(100vh - 140px);}
            .hero{padding:2rem 0 1rem;}
            .hero h1{font-size:2rem;}
        }

        /* FOOTER */
        .footer{padding:2rem 0;text-align:center;color:var(--ts);border-top:1px solid var(--b);margin-top:2rem;}
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="nav">
        <a href="market.php" class="logo">UniLink</a>
        <div class="nav-right">
            <button onclick="toggleTheme()" class="theme-btn">
                <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
            <a href="market.php" class="logout">← Marketplace</a>
            <form action="logout.php" method="post" style="margin:0;"><button type="submit" class="logout">Logout</button></form>
        </div>
    </div>
</header>

<main class="container" style="max-width:1400px;margin:auto;padding:0 1.5rem;">

    <!-- HERO -->
    <section class="hero">
        <h1>Marketplace <span>Messages</span></h1>
        <p>Manage all your buying and selling conversations in one place.</p>
    </section>

    <!-- STATS -->
    <?php 
    $totalConversations = count($conversationsList);
    $unreadCount = array_sum(array_column($conversationsList, 'unread_count'));
    $activeConversations = $totalConversations;
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-comments"></i>
            <div class="stat-number"><?= $totalConversations ?></div>
            <div class="stat-label">Total Conversations</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-bell"></i>
            <div class="stat-number"><?= $unreadCount ?></div>
            <div class="stat-label">Unread Messages</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-sync"></i>
            <div class="stat-number"><?= $activeConversations ?></div>
            <div class="stat-label">Active Chats</div>
        </div>
        <div class="stat-card">
            <i class="fas fa-check-circle"></i>
            <div class="stat-number">0</div>
            <div class="stat-label">Completed Deals</div>
        </div>
    </div>

    <!-- CHAT CONTAINER -->
    <div class="chat-container">
        
        <!-- SIDEBAR - CONVERSATIONS LIST -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-shopping-cart"></i> Marketplace Chats</h2>
            </div>
            <div class="conversations-list">
                <?php if (empty($conversationsList)): ?>
                    <div class="empty-chat" style="padding:2rem;">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No Conversations Yet</h3>
                        <p>Start chatting with buyers or sellers about items in the marketplace.</p>
                        <a href="market.php" class="logout" style="margin-top:1rem;display:inline-block;">Browse Marketplace</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversationsList as $conv): 
                        $otherPerson = $conv['seller_id'] == $userId ? $conv['buyer_name'] : $conv['seller_name'];
                        $lastMessage = $conv['last_message'] ?: "Start conversation...";
                        $lastMessageTime = date('M j g:i A', strtotime($conv['updated_at']));
                        $isSeller = $conv['seller_id'] == $userId;
                    ?>
                        <div class="conversation-item <?= $conversationId == $conv['conversation_id'] ? 'active' : '' ?>" 
                             onclick="location.href='market_messages.php?conversation=<?= $conv['conversation_id'] ?>'">
                            <div class="conversation-avatar" style="background:<?= $isSeller ? '#10b981' : '#06b6d4' ?>;">
                                <?= strtoupper(substr($otherPerson, 0, 1)) ?>
                            </div>
                            <div class="conversation-info">
                                <div class="conversation-header">
                                    <div class="conversation-name">
                                        <?= htmlspecialchars($otherPerson) ?>
                                        <small style="color:<?= $isSeller ? '#10b981' : '#06b6d4' ?>;font-size:.7rem;">
                                            (<?= $isSeller ? 'Buyer' : 'Seller' ?>)
                                        </small>
                                    </div>
                                    <div class="conversation-time"><?= $lastMessageTime ?></div>
                                </div>
                                <div class="conversation-preview"><?= htmlspecialchars($lastMessage) ?></div>
                                <div class="conversation-item-title"><?= htmlspecialchars($conv['item_title']) ?> • K<?= number_format($conv['price'], 2) ?></div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <div class="unread-badge"><?= $conv['unread_count'] ?> unread</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- CHAT AREA -->
        <div class="chat-area">
            <?php if ($currentConversation): 
                $otherPerson = $currentConversation['seller_id'] == $userId ? $currentConversation['buyer_name'] : $currentConversation['seller_name'];
                $isSeller = $currentConversation['seller_id'] == $userId;
            ?>
                <!-- CHAT HEADER -->
                <div class="chat-header">
                    <button class="back-btn" onclick="goBack()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="chat-header-info">
                        <h3>
                            <?= htmlspecialchars($otherPerson) ?>
                            <small style="color:<?= $isSeller ? '#10b981' : '#06b6d4' ?>;font-size:.8rem;">
                                (<?= $isSeller ? 'Buyer' : 'Seller' ?>)
                            </small>
                        </h3>
                        <p>About: <?= htmlspecialchars($currentConversation['item_title']) ?> • <span class="item-price">K<?= number_format($currentConversation['price'], 2) ?></span></p>
                    </div>
                </div>

                <!-- MESSAGES -->
                <div class="messages-area" id="messagesArea">
                    <?php if (empty($messages)): ?>
                        <div class="empty-chat">
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
                                        • <i class="fas fa-check-double" style="opacity:.7;"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- MESSAGE INPUT -->
                <form method="POST" class="message-input">
                    <input type="hidden" name="action" value="send_message">
                    <div class="input-group">
                        <textarea name="message" class="message-textarea" placeholder="Type your message..." required oninput="autoResize(this)"></textarea>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>

            <?php else: ?>
                <!-- NO CONVERSATION SELECTED -->
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <h3>Marketplace Messages</h3>
                    <p>Select a conversation from the sidebar to start chatting about marketplace items.</p>
                    <div style="margin-top:1.5rem;">
                        <a href="market.php" class="logout" style="margin-right:1rem;">Browse Marketplace</a>
                        <a href="upload_to_market.php" class="logout" style="background:var(--s);">List New Item</a>
                    </div>
                </div>
            <?php endif; ?>
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

    // GO BACK (MOBILE)
    function goBack() {
        window.location.href = 'market_messages.php';
    }

    // INITIAL SCROLL
    document.addEventListener('DOMContentLoaded', function() {
        scrollToBottom();
        
        // Auto-focus message input
        const textarea = document.querySelector('.message-textarea');
        if (textarea) {
            textarea.focus();
        }
    });

    // ENTER KEY TO SEND (SHIFT+ENTER FOR NEW LINE)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            const textarea = document.querySelector('.message-textarea');
            if (document.activeElement === textarea && textarea.value.trim()) {
                e.preventDefault();
                textarea.form.submit();
            }
        }
    });
</script>

</body>
</html>