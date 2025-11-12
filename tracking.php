<?php
// tracking.php
function trackActivity($userId, $activityType, $details = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $activityType,
        $details,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    return $pdo->lastInsertId();
}

function trackPageView($userId, $pageUrl, $pageTitle = null, $referrer = null) {
    global $pdo;
    
    // Get or create session ID
    if (!isset($_SESSION['tracking_session_id'])) {
        $_SESSION['tracking_session_id'] = uniqid('session_', true);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO page_views (user_id, page_url, page_title, referrer, session_id) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $pageUrl,
        $pageTitle,
        $referrer,
        $_SESSION['tracking_session_id']
    ]);
    
    return $pdo->lastInsertId();
}

function trackMarketplaceInteraction($userId, $itemId, $interactionType, $details = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO marketplace_interactions (user_id, item_id, interaction_type, interaction_details) 
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $itemId,
        $interactionType,
        $details ? json_encode($details) : null
    ]);
    
    return $pdo->lastInsertId();
}

function trackAcademicProgress($userId, $progressType, $progressValue = null, $details = null, $courseId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO academic_progress (user_id, course_id, progress_type, progress_value, progress_details) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $courseId,
        $progressType,
        $progressValue,
        $details
    ]);
    
    return $pdo->lastInsertId();
}

// Update page view duration when user leaves
function updatePageViewDuration($viewId, $duration) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE page_views SET view_duration = ? WHERE view_id = ?");
    $stmt->execute([$duration, $viewId]);
}
?>