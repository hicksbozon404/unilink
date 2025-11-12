<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Get listing and owner info
$listingId = $_GET['listing_id'] ?? 0;
$ownerId = $_GET['owner_id'] ?? 0;

if (!$listingId || !$ownerId) {
    header('Location: housing.php');
    exit();
}

// Fetch listing info
$stmt = $pdo->prepare("SELECT title, owner_id FROM housing_listings WHERE id = ?");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: housing.php');
    exit();
}

// Fetch owner info
$stmt = $pdo->prepare("SELECT full_names FROM users WHERE user_id = ?");
$stmt->execute([$ownerId]);
$owner = $stmt->fetch();

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = $pdo->prepare("
            INSERT INTO housing_messages (listing_id, sender_id, receiver_id, message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$listingId, $userId, $ownerId, $message]);
        
        // Mark previous messages as read
        $stmt = $pdo->prepare("
            UPDATE housing_messages 
            SET is_read = TRUE 
            WHERE listing_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$listingId, $ownerId, $userId]);
        
        // Redirect to avoid form resubmission
        header("Location: housing_chat.php?listing_id=$listingId&owner_id=$ownerId");
        exit();
    }
}

// Fetch chat messages
$stmt = $pdo->prepare("
    SELECT hm.*, u.full_names as sender_name 
    FROM housing_messages hm 
    JOIN users u ON hm.sender_id = u.user_id 
    WHERE hm.listing_id = ? AND ((hm.sender_id = ? AND hm.receiver_id = ?) OR (hm.sender_id = ? AND hm.receiver_id = ?))
    ORDER BY hm.created_at ASC
");
$stmt->execute([$listingId, $userId, $ownerId, $ownerId, $userId]);
$messages = $stmt->fetchAll();

// Mark received messages as read
$stmt = $pdo->prepare("
    UPDATE housing_messages 
    SET is_read = TRUE 
    WHERE listing_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = FALSE
");
$stmt->execute([$listingId, $userId, $ownerId]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #06b6d4;
            --primary-hover: #0e7490;
            --primary-light: #e0f7fa;
            --secondary: #8b5cf6;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-chat: #f1f5f9;
            
            --text-main: #1e293b;
            --text-subtle: #64748b;
            --text-light: #94a3b8;
            
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
            
            --transition: all 0.3s ease;
        }

        :root.dark {
            --primary: #41e1ff;
            --primary-hover: #06b6d4;
            --primary-light: #083344;
            --secondary: #a78bfa;
            --accent: #fbbf24;
            
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-chat: #1e293b;
            
            --text-main: #f1f5f9;
            --text-subtle: #94a3b8;
            --text-light: #64748b;
            
            --border: #334155;
            --border-light: #1e293b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            line-height: 1.6;
            transition: var(--transition);
            height: 100vh;
            overflow: hidden;
        }

        .container {
            max-width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            padding: 1rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
            flex-shrink: 0;
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .nav-btn {
            background: var(--bg-body);
            color: var(--text-main);
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--border);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .nav-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        /* Chat Header */
        .chat-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-shrink: 0;
        }

        .back-btn {
            background: none;
            border: none;
            color: var(--text-main);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: var(--transition);
        }

        .back-btn:hover {
            background: var(--bg-body);
        }

        .chat-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .chat-info {
            flex: 1;
            min-width: 0;
        }

        .chat-info h2 {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-info p {
            color: var(--text-subtle);
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Chat Messages */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1rem 0;
            background: var(--bg-chat);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .message {
            max-width: 85%;
            padding: 0.75rem 1rem;
            border-radius: 1.125rem;
            position: relative;
            animation: fadeIn 0.3s ease;
            word-wrap: break-word;
            line-height: 1.4;
        }

        @keyframes fadeIn {
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
            align-self: flex-end;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border-bottom-right-radius: 0.5rem;
            margin-left: auto;
        }

        .message.received {
            align-self: flex-start;
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-bottom-left-radius: 0.5rem;
            margin-right: auto;
        }

        .message-content {
            margin-bottom: 0.375rem;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            text-align: right;
        }

        .message.received .message-time {
            text-align: left;
        }

        /* Chat Input */
        .chat-input-container {
            background: var(--bg-card);
            border-top: 1px solid var(--border);
            padding: 1rem 1.5rem;
            flex-shrink: 0;
            position: sticky;
            bottom: 0;
        }

        .chat-form {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .input-wrapper {
            flex: 1;
            position: relative;
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
            padding: 0.5rem 1rem;
            transition: var(--transition);
        }

        .input-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }

        .message-input {
            width: 100%;
            border: none;
            background: transparent;
            color: var(--text-main);
            font-size: 1rem;
            resize: none;
            outline: none;
            line-height: 1.4;
            max-height: 120px;
            min-height: 24px;
            font-family: inherit;
        }

        .message-input::placeholder {
            color: var(--text-light);
        }

        .send-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .send-btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Empty State */
        .empty-chat {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-subtle);
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .empty-chat i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-chat h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .empty-chat p {
            font-size: 0.875rem;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .container {
                height: 100vh;
                height: 100dvh; /* Dynamic viewport height for mobile */
            }
            
            .header {
                padding: 0.875rem 1rem;
            }
            
            .logo {
                font-size: 1.25rem;
            }
            
            .nav-btn {
                padding: 0.5rem 0.75rem;
                font-size: 0.8rem;
            }
            
            .nav-btn span {
                display: none;
            }
            
            .chat-header {
                padding: 0.875rem 1rem;
            }
            
            .chat-avatar {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
            }
            
            .chat-info h2 {
                font-size: 1rem;
            }
            
            .chat-info p {
                font-size: 0.8rem;
            }
            
            .chat-messages {
                padding: 0.75rem 0.75rem 0;
                gap: 0.5rem;
            }
            
            .message {
                max-width: 90%;
                padding: 0.625rem 0.875rem;
                font-size: 0.9375rem;
            }
            
            .message-time {
                font-size: 0.7rem;
            }
            
            .chat-input-container {
                padding: 0.875rem 1rem;
            }
            
            .input-wrapper {
                padding: 0.5rem 0.875rem;
            }
            
            .message-input {
                font-size: 0.9375rem;
            }
            
            .send-btn {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 480px) {
            .message {
                max-width: 95%;
            }
            
            .chat-messages {
                padding: 0.5rem 0.5rem 0;
            }
            
            .empty-chat {
                padding: 2rem 1rem;
            }
            
            .empty-chat i {
                font-size: 2.5rem;
            }
        }

        /* Scrollbar Styling */
        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 2px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--text-light);
        }

        /* Message Status */
        .message-status {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        /* Typing Indicator */
        .typing-indicator {
            align-self: flex-start;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1.125rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            display: none;
        }

        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }

        .typing-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--text-light);
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typing {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
            40% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="nav-content">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-link"></i> UniLink
                </a>
                <div class="nav-actions">
                    <a href="housing.php" class="nav-btn">
                        <i class="fas fa-home"></i>
                        <span>Browse</span>
                    </a>
                    <a href="dashboard.php" class="nav-btn">
                        <i class="fas fa-grid"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Chat Header -->
        <div class="chat-header">
            <a href="view_housing.php?id=<?= $listingId ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="chat-avatar">
                <?= strtoupper(substr($owner['full_names'], 0, 1)) ?>
            </div>
            <div class="chat-info">
                <h2><?= htmlspecialchars($owner['full_names']) ?></h2>
                <p><?= htmlspecialchars($listing['title']) ?></p>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div class="empty-chat">
                    <i class="fas fa-comments"></i>
                    <h3>No messages yet</h3>
                    <p>Start the conversation by sending a message below.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?= $message['sender_id'] == $userId ? 'sent' : 'received' ?>">
                        <div class="message-content">
                            <?= htmlspecialchars($message['message']) ?>
                        </div>
                        <div class="message-time">
                            <?= date('g:i A', strtotime($message['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Chat Input -->
        <div class="chat-input-container">
            <form method="POST" class="chat-form" id="chatForm">
                <div class="input-wrapper">
                    <textarea 
                        name="message" 
                        class="message-input" 
                        placeholder="Type a message..." 
                        required
                        maxlength="1000"
                        rows="1"
                        id="messageInput"
                    ></textarea>
                </div>
                <button type="submit" class="send-btn" id="sendBtn">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of chat
        const chatMessages = document.getElementById('chatMessages');
        function scrollToBottom() {
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // Focus on input when page loads
            messageInput.focus();
        }

        // Form submission
        const chatForm = document.getElementById('chatForm');
        if (chatForm) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const message = messageInput.value.trim();
                
                if (!message) return;
                
                // Disable input and button temporarily
                messageInput.disabled = true;
                document.getElementById('sendBtn').disabled = true;
                
                // Remove empty state if it exists
                const emptyChat = document.querySelector('.empty-chat');
                if (emptyChat) {
                    emptyChat.remove();
                }
                
                // Add message to chat immediately
                const messageDiv = document.createElement('div');
                messageDiv.className = 'message sent';
                messageDiv.innerHTML = `
                    <div class="message-content">${message}</div>
                    <div class="message-time">Just now</div>
                `;
                
                chatMessages.appendChild(messageDiv);
                scrollToBottom();
                
                // Show typing indicator
                const typingIndicator = document.getElementById('typingIndicator');
                typingIndicator.style.display = 'block';
                scrollToBottom();
                
                // Send message via AJAX
                fetch('housing_chat.php?listing_id=<?= $listingId ?>&owner_id=<?= $ownerId ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.ok) {
                        // Hide typing indicator after a delay (simulating response)
                        setTimeout(() => {
                            typingIndicator.style.display = 'none';
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    typingIndicator.style.display = 'none';
                })
                .finally(() => {
                    // Re-enable input and clear it
                    messageInput.disabled = false;
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    messageInput.focus();
                    document.getElementById('sendBtn').disabled = false;
                });
            });
        }

        // Initial scroll to bottom
        scrollToBottom();

        // Handle virtual keyboard on mobile
        if ('visualViewport' in window) {
            const visualViewport = window.visualViewport;
            visualViewport.addEventListener('resize', scrollToBottom);
        }

        // Prevent zoom on double-tap (mobile)
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Handle orientation change
        window.addEventListener('orientationchange', function() {
            setTimeout(scrollToBottom, 100);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to send
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                chatForm?.requestSubmit();
            }
        });
    </script>
</body>
</html>