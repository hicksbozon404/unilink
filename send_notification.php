<?php
require_once 'vendor/autoload.php';
require_once 'classes/Notification.php';
require_once 'classes/PushNotification.php';

session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Initialize PDO connection
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Initialize notification classes
$notification = new Notification($pdo);
$pushNotification = new PushNotification($pdo);

// Create notification instance
$notification = new Notification($pdo);

// Example 1: Send a notification to a single user
if (isset($_POST['send_single'])) {
    $userId = $_POST['user_id'];
    $title = $_POST['title'] ?? "New Message";
    $message = $_POST['message'] ?? "You have received a new message.";
    $type = $_POST['type'] ?? "info";
    $url = $_POST['url'] ?? null;
    
    // Send database notification
    $success = $notification->send(
        $userId,
        $title,
        $message,
        $type,
        $url
    );
    
    // Send push notification
    $pushSuccess = $pushNotification->sendPushNotification(
        $userId,
        $title,
        $message,
        $url
    );
    
    if ($success && $pushSuccess) {
        echo "Notification sent successfully (Push and Database)!";
    } elseif ($success) {
        echo "Database notification sent, but push notification failed.";
    } else {
        echo "Failed to send notification.";
    }
}

// Example 2: Send notification to multiple users
if (isset($_POST['send_bulk'])) {
    $userIds = $_POST['user_ids']; // Array of user IDs
    $successful = $notification->sendBulk(
        $userIds,
        "Course Update",
        "New course materials have been uploaded.",
        "academic",
        "courses.php"
    );
    
    echo "Notification sent to " . count($successful) . " users.";
}

// Example 3: Broadcast to all users
if (isset($_POST['broadcast'])) {
    $count = $notification->broadcast(
        "System Maintenance",
        "The system will be down for maintenance on Sunday at 2 AM.",
        "warning",
        "maintenance.php"
    );
    
    echo "Broadcast sent to " . $count . " users.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - UniLink</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        /* Use the same styles as your main application */
        body {
            font-family: 'Inter', sans-serif;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .btn {
            background: #06b6d4;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn:hover {
            background: #0e7490;
        }
    </style>
</head>
<body>
    <h1>Send Notifications</h1>
    
    <!-- Send to Single User -->
    <form method="post" class="form-group">
        <h2>Send to Single User</h2>
        <label class="form-label">User ID:</label>
        <input type="number" name="user_id" class="form-input" required>
        
        <label class="form-label">Title:</label>
        <input type="text" name="title" class="form-input" placeholder="Enter notification title" required>
        
        <label class="form-label">Message:</label>
        <textarea name="message" class="form-input" placeholder="Enter notification message" required></textarea>
        
        <label class="form-label">Type:</label>
        <select name="type" class="form-input" required>
            <option value="info">Info</option>
            <option value="success">Success</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
            <option value="marketplace">Marketplace</option>
            <option value="academic">Academic</option>
        </select>
        
        <label class="form-label">Redirect URL (optional):</label>
        <input type="text" name="url" class="form-input" placeholder="e.g., messages.php">
        
        <button type="submit" name="send_single" class="btn">Send Notification</button>
    </form>
    
    <!-- Send to Multiple Users -->
    <form method="post" class="form-group">
        <h2>Send to Multiple Users</h2>
        <label class="form-label">User IDs (comma-separated):</label>
        <input type="text" name="user_ids" class="form-input" placeholder="1,2,3,4" required>
        <button type="submit" name="send_bulk" class="btn">Send Bulk Notification</button>
    </form>
    
    <!-- Broadcast to All -->
    <form method="post" class="form-group">
        <h2>Broadcast to All Users</h2>
        <button type="submit" name="broadcast" class="btn">Send Broadcast</button>
    </form>
</body>
</html>