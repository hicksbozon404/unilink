<?php
// setup_messaging.php
session_start();

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

try {
    // Create conversations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_conversations (
        conversation_id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT,
        buyer_id INT,
        seller_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES marketplace_items(item_id) ON DELETE CASCADE,
        FOREIGN KEY (buyer_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE,
        UNIQUE KEY unique_conversation (item_id, buyer_id)
    )");

    // Create messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT,
        sender_id INT,
        message_text TEXT,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES marketplace_conversations(conversation_id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
    )");

    echo "Messaging tables created successfully!<br>";
    echo "<a href='market.php'>Go to Marketplace</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>