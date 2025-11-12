<?php
// notifications.php

function createNotification($userId, $title, $message, $type = 'info', $actionUrl = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, action_url) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$userId, $title, $message, $type, $actionUrl]);
    
    return $pdo->lastInsertId();
}

function getUserNotifications($userId, $unreadOnly = false, $limit = 50) {
    global $pdo;
    
    $sql = "
        SELECT * FROM notifications 
        WHERE user_id = ? 
    ";
    
    if ($unreadOnly) {
        $sql .= " AND is_read = FALSE";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $limit]);
    
    return $stmt->fetchAll();
}

function markNotificationAsRead($notificationId, $userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE notification_id = ? AND user_id = ?
    ");
    
    return $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsAsRead($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE user_id = ? AND is_read = FALSE
    ");
    
    return $stmt->execute([$userId]);
}

function getUnreadNotificationCount($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
    ");
    
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    return $result['count'];
}

// Common notification templates
function notifyNewMessage($userId, $fromUser, $messagePreview, $conversationId) {
    return createNotification(
        $userId,
        "New Message from {$fromUser}",
        $messagePreview,
        'info',
        "market_messages.php?conversation={$conversationId}"
    );
}

function notifyMarketplaceInterest($userId, $itemTitle, $buyerName, $itemId) {
    return createNotification(
        $userId,
        "Interest in Your Item",
        "{$buyerName} is interested in your item: {$itemTitle}",
        'marketplace',
        "market_messages.php?item={$itemId}"
    );
}

function notifyGradeAdded($userId, $courseCode, $grade) {
    return createNotification(
        $userId,
        "Grade Updated",
        "Your grade for {$courseCode} has been posted: {$grade}",
        'academic',
        "grades.php"
    );
}

function notifyDocumentUploaded($userId, $fileName) {
    return createNotification(
        $userId,
        "Document Uploaded",
        "Your file '{$fileName}' has been securely stored in your vault",
        'success',
        "vault.php"
    );
}

function notifyClearanceUpdate($userId, $status) {
    return createNotification(
        $userId,
        "Clearance Status Updated",
        "Your clearance application is now: {$status}",
        'academic',
        "clearance.php"
    );
}
?>