<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// ---------- DB ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Create notifications table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'error', 'marketplace', 'academic') DEFAULT 'info',
        is_read BOOLEAN DEFAULT FALSE,
        action_url VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )
");

// Create user_courses table if it doesn't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_code VARCHAR(20) NOT NULL,
        course_name VARCHAR(100) NOT NULL,
        credits INT NOT NULL,
        semester VARCHAR(20) NOT NULL,
        year INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
    )
");

// Create sample notifications for this user
function createSampleNotifications($userId) {
    global $pdo;
    
    // Check if user already has notifications
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $sampleNotifications = [
            [
                'title' => 'Welcome to UniLink! 🎉',
                'message' => 'Get started by exploring your dashboard and setting up your profile.',
                'type' => 'info',
                'action_url' => 'dashboard.php'
            ],
            [
                'title' => 'Profile Setup Reminder',
                'message' => 'Complete your academic information to unlock all features.',
                'type' => 'academic',
                'action_url' => 'profile.php'
            ],
            [
                'title' => 'Marketplace Tip',
                'message' => 'Sell your old textbooks and buy from fellow students in the marketplace.',
                'type' => 'marketplace',
                'action_url' => 'market.php'
            ],
            [
                'title' => 'Secure Vault Available',
                'message' => 'Upload and store your important documents securely in your vault.',
                'type' => 'success',
                'action_url' => 'vault.php'
            ],
            [
                'title' => 'Campus Event',
                'message' => 'Tech Talk: Building Modern Web Applications - Tomorrow at 3 PM',
                'type' => 'info',
                'action_url' => 'events.php'
            ]
        ];
        
        foreach ($sampleNotifications as $notification) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, action_url) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $notification['title'],
                $notification['message'],
                $notification['type'],
                $notification['action_url']
            ]);
        }
    }
}

// Get ALL user data from database
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        die("User not found!");
    }
    
    // Set data from database with proper fallbacks
    $fullName = htmlspecialchars($userData['full_names'] ?? 'Student');
    $email = htmlspecialchars($userData['email'] ?? 'student@unilink.edu');
    $phone = htmlspecialchars($userData['phone'] ?? 'Not set');
    
    // Academic data from users table
    $regNumber = htmlspecialchars($userData['student_id'] ?? 'Not set');
    $faculty = htmlspecialchars($userData['faculty'] ?? 'Not set');
    $program = htmlspecialchars($userData['program'] ?? 'Not set');
    $duration = htmlspecialchars($userData['duration'] ?? 'Not set');
    $currentYear = htmlspecialchars($userData['current_year'] ?? 'Not set');
    $whatsapp = htmlspecialchars($userData['whatsapp'] ?? 'Not set');
    $portalUsername = htmlspecialchars($userData['portal_username'] ?? 'Not set');
    
    // Create sample notifications for this user
    createSampleNotifications($userId);
    
    // Get unread notification count
    $unreadStmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $unreadStmt->execute([$userId]);
    $unreadResult = $unreadStmt->fetch();
    $unreadCount = $unreadResult['count'];
    
    // Update session with current data
    $_SESSION['full_names'] = $userData['full_names'] ?? '';
    $_SESSION['email'] = $userData['email'] ?? '';
    $_SESSION['phone'] = $userData['phone'] ?? '';
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Create avatar directory with proper permissions
$avatarDir = 'avatar/';
if (!is_dir($avatarDir)) {
    if (!mkdir($avatarDir, 0755, true)) {
        $error = "Failed to create avatar directory. Check folder permissions.";
    }
}

// Check current avatar
$avatarFile = $avatarDir . $userId . '.jpg';
$hasAvatar = file_exists($avatarFile);

// ---------- SIMPLE AVATAR UPLOAD ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['avatar'];
    
    // Simple validation
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= 2*1024*1024) {
            $targetFile = $avatarDir . $userId . '.jpg';
            
            // Simple file move (no image processing for now)
            if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                header('Location: profile.php?avatar=success');
                exit;
            } else {
                header('Location: profile.php?avatar=error&message=Failed to save file');
                exit;
            }
        } else {
            header('Location: profile.php?avatar=error&message=Invalid file type or size');
            exit;
        }
    } else {
        header('Location: profile.php?avatar=error&message=Upload error: ' . $file['error']);
        exit;
    }
}

// ---------- UPDATE PROFILE ----------
if (($_POST['action'] ?? '') === 'update') {
    try {
        // Prepare the update query - handle empty values properly
        $updateFields = [];
        $updateValues = [];
        
        // Always update these fields
        $updateFields[] = "full_names = ?";
        $updateValues[] = $_POST['full_names'] ?? '';
        
        $updateFields[] = "email = ?";
        $updateValues[] = $_POST['email'] ?? '';
        
        $updateFields[] = "phone = ?";
        $updateValues[] = $_POST['phone'] ?? '';
        
        // Handle academic fields - set to NULL if empty
        $updateFields[] = "student_id = ?";
        $updateValues[] = !empty($_POST['student_id']) ? $_POST['student_id'] : null;
        
        $updateFields[] = "faculty = ?";
        $updateValues[] = !empty($_POST['faculty']) ? $_POST['faculty'] : null;
        
        $updateFields[] = "program = ?";
        $updateValues[] = !empty($_POST['program']) ? $_POST['program'] : null;
        
        $updateFields[] = "duration = ?";
        $updateValues[] = !empty($_POST['duration']) ? $_POST['duration'] : null;
        
        $updateFields[] = "current_year = ?";
        $updateValues[] = !empty($_POST['current_year']) ? $_POST['current_year'] : null;
        
        $updateFields[] = "whatsapp = ?";
        $updateValues[] = !empty($_POST['whatsapp']) ? $_POST['whatsapp'] : null;
        
        $updateFields[] = "portal_username = ?";
        $updateValues[] = !empty($_POST['portal_username']) ? $_POST['portal_username'] : null;
        
        // Only update portal password if provided
        if (!empty($_POST['portal_password'])) {
            $updateFields[] = "portal_password = ?";
            $updateValues[] = password_hash($_POST['portal_password'], PASSWORD_BCRYPT);
        }
        
        // Add user_id for WHERE clause
        $updateValues[] = $userId;
        
        // Build and execute the query
        $sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Create notification for profile update
        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, action_url) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $notifStmt->execute([
            $userId,
            "Profile Updated ✅",
            "Your profile information has been successfully updated.",
            'success',
            'profile.php'
        ]);
        
        // Update session
        $_SESSION['full_names'] = $_POST['full_names'] ?? '';
        $_SESSION['email'] = $_POST['email'] ?? '';
        $_SESSION['phone'] = $_POST['phone'] ?? '';
        
        header('Location: profile.php?saved=1');
        exit;
    } catch (PDOException $e) {
        // Handle duplicate email error
        if ($e->getCode() == 23000) {
            header('Location: profile.php?error=email_exists');
            exit;
        } else {
            header('Location: profile.php?error=update_failed');
            exit;
        }
    }
}

// ---------- ADD COURSE ----------
if (($_POST['action'] ?? '') === 'add_course') {
    $stmt = $pdo->prepare("
        INSERT INTO user_courses (user_id, course_code, course_name, credits, semester, year) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $userId,
        $_POST['course_code'] ?? '',
        $_POST['course_name'] ?? '',
        $_POST['credits'] ?? 0,
        $_POST['semester'] ?? '',
        $_POST['year'] ?? date('Y')
    ]);
    
    // Create notification for course addition
    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, action_url) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $notifStmt->execute([
        $userId,
        "Course Added 📚",
        "Course {$_POST['course_code']} - {$_POST['course_name']} has been added to your profile.",
        'academic',
        'profile.php'
    ]);
    
    header('Location: profile.php?course_added=1');
    exit;
}

// ---------- DELETE COURSE ----------
if (isset($_GET['delete_course'])) {
    $courseId = (int)$_GET['delete_course'];
    $stmt = $pdo->prepare("DELETE FROM user_courses WHERE id = ? AND user_id = ?");
    $stmt->execute([$courseId, $userId]);
    
    header('Location: profile.php?course_deleted=1');
    exit;
}

// Get user courses
$coursesStmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? ORDER BY year DESC, semester, course_code");
$coursesStmt->execute([$userId]);
$userCourses = $coursesStmt->fetchAll();

// Handle notification actions
if (isset($_GET['mark_read'])) {
    $notifId = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
    header('Location: profile.php');
    exit;
}

if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$userId]);
    header('Location: profile.php');
    exit;
}

// Get notifications for display
$notificationsStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notificationsStmt->execute([$userId]);
$notifications = $notificationsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#06b6d4">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ---------- 2025 MODERN VARIABLES ---------- */
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
            --bg-card-rgb: 255, 255, 255;
            --bg-glass: rgba(255,255,255,0.8);
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
            
            --safe-bottom: env(safe-area-inset-bottom);
            --safe-top: env(safe-area-inset-top);
            
            --transition: .35s cubic-bezier(.2,.8,.2,1);
            --transition-slow: .5s cubic-bezier(.2,.8,.2,1);
            
            --header-height: 60px;
            --bottom-nav-height: calc(60px + var(--safe-bottom, 0px));
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

        /* ---------- BASE STYLES 2025 ---------- */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
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

        /* ---------- GLASS MORPHISM 2025 ---------- */
        .glass {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* ---------- HEADER 2025 ---------- */
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

        /* ==================== NOTIFICATION STYLES 2025 ==================== */
        .notification-bell {
            position: relative;
            display: inline-block;
        }

        .notification-btn {
            position: relative;
            background: var(--primary);
            color: white;
            padding: 0.6rem;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--error);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-card);
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 400px;
            max-width: 90vw;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            display: none;
            margin-top: 0.5rem;
        }

        .notification-dropdown.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .notification-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .mark-all-read {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.8rem;
            cursor: pointer;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            transition: background 0.2s;
        }

        .mark-all-read:hover {
            background: var(--bg-body);
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            position: relative;
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background: var(--bg-body);
        }

        .notification-item.unread {
            background: var(--primary-glow);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-body);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
            font-size: 1rem;
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .notification-message {
            color: var(--text-subtle);
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .notification-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            flex-shrink: 0;
            margin-top: 0.5rem;
        }

        .notification-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border);
            text-align: center;
        }

        .notification-footer a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .loading-notifications,
        .no-notifications,
        .error-notifications {
            padding: 2rem;
            text-align: center;
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        /* Notification types colors */
        .notification-icon.info {
            color: var(--primary);
            background: var(--primary-glow);
        }

        .notification-icon.success {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
        }

        .notification-icon.warning {
            color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
        }

        .notification-icon.error {
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }

        .notification-icon.marketplace {
            color: var(--secondary);
            background: rgba(139, 92, 246, 0.1);
        }

        .notification-icon.academic {
            color: var(--primary);
            background: var(--primary-glow);
        }

        /* ---------- HERO SECTION 2025 ---------- */
        .hero {
            padding: 2rem 0;
            position: relative;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .hero-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        /* ---------- PROFILE CARD 2025 ---------- */
        .profile-card {
            background: var(--bg-card);
            border-radius: 2rem;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            background: linear-gradient(145deg, 
                rgba(var(--bg-card-rgb, 255, 255, 255), 0.9),
                rgba(var(--bg-card-rgb, 255, 255, 255), 0.7)
            );
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s ease infinite;
            z-index: 1;
        }
        
        .profile-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 2rem;
            padding: 1px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        @keyframes shimmer {
            0%, 100% { background-position: -200% 0; }
            50% { background-position: 200% 0; }
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 3px solid var(--primary);
            transition: all .4s ease;
            cursor: pointer;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 0 0 6px var(--primary-glow);
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }

        .avatar-upload-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity .3s;
            color: white;
            font-size: 1.5rem;
        }

        .profile-avatar:hover .avatar-upload-overlay {
            opacity: 1;
        }

        .profile-text h1 {
            font-size: clamp(1.5rem, 4vw, 2rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: .5rem;
        }

        .profile-text h1 span {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .profile-text p {
            color: var(--text-subtle);
            font-size: 1rem;
        }

        /* ---------- INFO GRID 2025 ---------- */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .info-card {
            background: var(--bg-card);
            padding: 1.25rem;
            border-radius: 1.25rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            transition: all .3s ease;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
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

        .info-card:hover::before {
            opacity: 1;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .info-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            background: var(--primary)15;
            color: var(--primary);
        }

        .info-label {
            font-weight: 600;
            color: var(--text-subtle);
            font-size: .85rem;
            margin-bottom: .5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 700;
            word-break: break-word;
        }

        /* ---------- MODERN ACADEMIC SECTION 2025 ---------- */
        .academic-section {
            background: var(--bg-card);
            border-radius: 2rem;
            padding: 2rem;
            margin: 1.5rem 0;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .section-header {
            position: relative;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .title-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 8px 32px var(--primary-glow);
        }

        .title-group h2 {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--text-main), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.25rem;
        }

        .title-group p {
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        .status-badge {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 0.6rem 1.25rem;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
        }

        .pulse-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Academic Grid */
        .academic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .academic-card {
            background: var(--bg-body);
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .academic-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .card-header i {
            font-size: 1.1rem;
            color: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 0.75rem;
            background: var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-label {
            font-weight: 700;
            color: var(--text-subtle);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card-value {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.3;
        }

        /* Courses Section */
        .courses-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }

        .section-subheader {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .section-subheader h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .course-count {
            background: var(--primary);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 700;
        }

        /* ---------- COURSES TABLE 2025 ---------- */
        .courses-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: var(--bg-card);
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .courses-table th {
            background: var(--bg-body);
            padding: 1rem 1.25rem;
            text-align: left;
            font-weight: 700;
            color: var(--text-subtle);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        .courses-table td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.2s;
        }

        .courses-table tr:last-child td {
            border-bottom: none;
        }

        .courses-table tr:hover td {
            background: var(--bg-body);
        }

        .course-code {
            font-weight: 700;
            color: var(--primary);
        }

        .course-name {
            color: var(--text-main);
            font-weight: 600;
        }

        .course-credits {
            font-weight: 700;
            color: var(--text-subtle);
        }

        .course-semester {
            background: var(--bg-body);
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-subtle);
        }

        .delete-btn {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border: none;
            padding: 0.6rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delete-btn:hover {
            background: var(--error);
            color: white;
            transform: scale(1.05);
        }

        /* ---------- FORMS 2025 ---------- */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 1rem;
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--primary);
        }

        /* ---------- MODALS 2025 ---------- */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 2rem;
            padding: 2rem;
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border);
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-subtle);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: var(--bg-body);
            color: var(--text-main);
        }

        /* ---------- ALERTS 2025 ---------- */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* ---------- MOBILE RESPONSIVE 2025 ---------- */
        @media (max-width: 768px) {
            .container {
                padding: 0 0.75rem;
            }
            
            .nav-content {
                flex-wrap: wrap;
                gap: 0.75rem;
                padding: 0.75rem 0;
            }
            
            .nav-actions {
                order: 3;
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .nav-btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            
            .hero {
                padding: 0.75rem 0;
            }
            
            .hero-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .profile-card, .academic-section {
                border-radius: 1.25rem;
                padding: 1.25rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                margin: 0 auto;
            }
            
            .profile-text h1 {
                font-size: 1.5rem;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .info-card {
                padding: 1rem;
                border-radius: 1rem;
            }
            
            .info-icon {
                width: 36px;
                height: 36px;
                border-radius: 10px;
                font-size: 1rem;
                margin-bottom: 0.75rem;
            }
            
            .academic-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .academic-card {
                padding: 1rem;
                border-radius: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .title-group {
                width: 100%;
            }
            
            .icon-wrapper {
                width: 40px;
                height: 40px;
                border-radius: 0.75rem;
                font-size: 1rem;
            }
            
            .courses-section {
                margin-top: 1.5rem;
                padding-top: 1rem;
            }
            
            .courses-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 1rem;
                white-space: nowrap;
            }
            
            .courses-table td, 
            .courses-table th {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .course-semester {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .delete-btn {
                padding: 0.4rem;
                border-radius: 0.5rem;
            }
            
            .modal {
                align-items: flex-end;
                padding: 0;
            }
            
            .modal-content {
                border-radius: 1.25rem 1.25rem 0 0;
                padding: 1.25rem;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .notification-dropdown {
                position: fixed;
                top: auto;
                bottom: 0;
                left: 0;
                right: 0;
                width: 100%;
                max-width: 100%;
                margin: 0;
                border-radius: 1.25rem 1.25rem 0 0;
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .notification-list {
                max-height: 60vh;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            
            .form-group {
                margin-bottom: 1rem;
            }
            
            .form-input {
                padding: 0.6rem 0.75rem;
                border-radius: 0.75rem;
                font-size: 0.95rem;
            }
            
            .btn {
                padding: 0.6rem 1.25rem;
                border-radius: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 0.5rem;
            }
            
            .nav-actions {
                gap: 0.25rem;
            }
            
            .nav-btn {
                width: calc(50% - 0.25rem);
                justify-content: center;
                padding: 0.5rem;
                font-size: 0.85rem;
            }
            
            .notification-btn {
                width: 38px;
                height: 38px;
            }
            
            .notification-item {
                padding: 0.75rem;
                gap: 0.75rem;
            }
            
            .notification-icon {
                width: 32px;
                height: 32px;
            }
            
            .notification-title {
                font-size: 0.9rem;
            }
            
            .notification-message {
                font-size: 0.85rem;
            }
            
            .notification-time {
                font-size: 0.75rem;
            }
            
            .modal-content {
                padding: 1rem;
            }
            
            .modal-title {
                font-size: 1.1rem;
            }
            
            .form-label {
                font-size: 0.85rem;
            }
            
            .form-input {
                font-size: 0.9rem;
            }
        }

        /* ---------- DARK MODE TOGGLE ---------- */
        .dark-mode-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        /* ---------- UTILITY CLASSES ---------- */
        .text-center { text-align: center; }
        .mb-0 { margin-bottom: 0; }
        .mt-2 { margin-top: 2rem; }
        .hidden { display: none; }
        /* Mobile notification drawer styles */
        .mobile-notif-drawer { display: none; }
        @media (max-width: 768px) {
            .mobile-notif-drawer { display: block; position: fixed; inset: 0; z-index: 2200; pointer-events: none; }
            .mobile-notif-drawer .drawer-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.35); opacity: 0; transition: opacity .25s ease; pointer-events: none; }
            .mobile-notif-drawer.open .drawer-backdrop { opacity: 1; pointer-events: auto; }
            .mobile-notif-drawer .drawer-panel { position: fixed; right: 0; top: 0; bottom: 0; width: 86%; max-width: 420px; background: var(--bg-card); box-shadow: var(--shadow-xl); transform: translateX(100%); transition: transform .28s ease; overflow-y: auto; border-radius: 12px 0 0 12px; padding: 1rem; pointer-events: auto; }
            .mobile-notif-drawer.open .drawer-panel { transform: translateX(0); }
            .mobile-notif-drawer .drawer-header { display:flex; justify-content:space-between; align-items:center; gap:.5rem; margin-bottom:.5rem; }
            .mobile-notif-drawer .drawer-list { max-height: calc(100vh - 140px); overflow-y: auto; }
            .mobile-notif-drawer .drawer-list .notification-item { display:block; }
            .mobile-notif-drawer .drawer-footer { padding-top:.75rem; text-align:center; border-top:1px solid var(--border-light); }
            .mobile-notif-drawer .close-drawer { background:none; border:none; font-size:1.4rem; cursor:pointer; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav-content">
                <a href="dashboard.php" class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>UniLink</span>
                </a>
                
                <div class="nav-actions">
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>
                    
                    <!-- Notification Bell -->
                    <div class="notification-bell">
                        <a href="notifications.php" class="notification-btn" id="notificationBtn" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-badge" id="notificationBadge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <!-- Mobile quick-access drawer (mobile-only). Dropdown removed for simplicity. -->
                        <div id="mobileNotifDrawer" class="mobile-notif-drawer" aria-hidden="true">
                            <div class="drawer-backdrop" id="drawerBackdrop"></div>
                            <div class="drawer-panel">
                                <div class="drawer-header">
                                    <h4>Notifications</h4>
                                    <div class="drawer-actions">
                                        <?php if ($unreadCount > 0): ?>
                                            <a href="notifications.php?mark_all_read=1" class="mark-all-read">Mark all as read</a>
                                        <?php endif; ?>
                                        <button class="close-drawer" id="closeDrawerBtn" aria-label="Close">&times;</button>
                                    </div>
                                </div>
                                <div class="drawer-list" id="drawerList">
                                    <div class="loading-notifications">Loading...</div>
                                </div>
                                <div class="drawer-footer">
                                    <a href="notifications.php">View all notifications</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="dashboard.php" class="nav-btn secondary">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                    <a href="logout.php" class="nav-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container">
        <div class="hero">
            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Profile updated successfully!</span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['course_added'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Course added successfully!</span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['course_deleted'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Course deleted successfully!</span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['avatar']) && $_GET['avatar'] === 'error'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Avatar upload failed: <?php echo $_GET['message'] ?? 'Unknown error'; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['avatar']) && $_GET['avatar'] === 'success'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Avatar updated successfully!</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'email_exists'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Email already exists. Please use a different email address.</span>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'update_failed'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Failed to update profile. Please try again.</span>
                </div>
            <?php endif; ?>

            <div class="hero-grid">
                <!-- Left Column: Profile Card -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar" onclick="document.getElementById('avatarUpload').click()">
                            <?php if ($hasAvatar): ?>
                                <img src="<?php echo $avatarFile; ?>?t=<?php echo time(); ?>" alt="Profile Avatar" class="avatar-img">
                            <?php else: ?>
                                <div class="avatar-fallback">
                                    <?php echo strtoupper(substr($fullName, 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-upload-overlay">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        
                        <div class="profile-text">
                            <h1><span><?php echo $fullName; ?></span></h1>
                            <p><?php echo $email; ?></p>
                        </div>
                    </div>

                    <!-- Hidden Avatar Upload Form -->
                    <form method="post" enctype="multipart/form-data" class="hidden">
                        <input type="file" name="avatar" id="avatarUpload" accept="image/jpeg,image/jpg,image/png" onchange="this.form.submit()">
                    </form>

                    <!-- Personal Information Grid -->
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo $email; ?></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?php echo $phone; ?></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="info-label">Registration Number</div>
                            <div class="info-value"><?php echo $regNumber; ?></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="info-label">Faculty</div>
                            <div class="info-value"><?php echo $faculty; ?></div>
                        </div>
                    </div>

                    <button class="btn btn-primary" onclick="openEditModal()" style="width: 100%;">
                        <i class="fas fa-edit"></i>
                        Edit Profile Information
                    </button>
                </div>

                <!-- Right Column: Academic Information -->
                <div class="academic-section">
                    <div class="section-header">
                        <div class="header-content">
                            <div class="title-group">
                                <div class="icon-wrapper">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div>
                                    <h2>Academic Profile</h2>
                                    <p>Your academic information and course registration</p>
                                </div>
                            </div>
                            <div class="status-badge">
                                <div class="pulse-dot"></div>
                                Active Student
                            </div>
                        </div>
                    </div>

                    <!-- Academic Grid -->
                    <div class="academic-grid">
                        <div class="academic-card">
                            <div class="card-header">
                                <i class="fas fa-book"></i>
                                <div class="card-label">Program</div>
                            </div>
                            <div class="card-value"><?php echo $program; ?></div>
                        </div>
                        
                        <div class="academic-card">
                            <div class="card-header">
                                <i class="fas fa-calendar-alt"></i>
                                <div class="card-label">Duration</div>
                            </div>
                            <div class="card-value"><?php echo $duration; ?></div>
                        </div>
                        
                        <div class="academic-card">
                            <div class="card-header">
                                <i class="fas fa-calendar-check"></i>
                                <div class="card-label">Current Year</div>
                            </div>
                            <div class="card-value"><?php echo $currentYear; ?></div>
                        </div>
                        
                        <div class="academic-card">
                            <div class="card-header">
                                <i class="fab fa-whatsapp"></i>
                                <div class="card-label">WhatsApp</div>
                            </div>
                            <div class="card-value"><?php echo $whatsapp; ?></div>
                        </div>
                    </div>

                    <!-- Courses Section -->
                    <div class="courses-section">
                        <div class="section-subheader">
                            <h3>Registered Courses</h3>
                            <span class="course-count"><?php echo count($userCourses); ?> courses</span>
                        </div>

                        <?php if (!empty($userCourses)): ?>
                            <div class="table-container">
                                <table class="courses-table">
                                    <thead>
                                        <tr>
                                            <th>Course Code</th>
                                            <th>Course Name</th>
                                            <th>Credits</th>
                                            <th>Semester</th>
                                            <th>Year</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userCourses as $course): ?>
                                            <tr>
                                                <td class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                <td class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></td>
                                                <td class="course-credits"><?php echo htmlspecialchars($course['credits']); ?></td>
                                                <td>
                                                    <span class="course-semester"><?php echo htmlspecialchars($course['semester']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($course['year']); ?></td>
                                                <td>
                                                    <button class="delete-btn" onclick="deleteCourse(<?php echo $course['id']; ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 3rem; color: var(--text-subtle);">
                                <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p>No courses registered yet.</p>
                            </div>
                        <?php endif; ?>

                        <button class="btn btn-primary mt-2" onclick="openCourseModal()" style="width: 100%;">
                            <i class="fas fa-plus"></i>
                            Add New Course
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Profile</h3>
                <button class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="update">
                
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_names" class="form-input" value="<?php echo $fullName; ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" value="<?php echo $email; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-input" value="<?php echo $phone; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Registration Number</label>
                        <input type="text" name="student_id" class="form-input" value="<?php echo $regNumber; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current Year</label>
                        <select name="current_year" class="form-input">
                            <option value="">Select Year</option>
                            <option value="Year 1" <?php echo $currentYear == 'Year 1' ? 'selected' : ''; ?>>Year 1</option>
                            <option value="Year 2" <?php echo $currentYear == 'Year 2' ? 'selected' : ''; ?>>Year 2</option>
                            <option value="Year 3" <?php echo $currentYear == 'Year 3' ? 'selected' : ''; ?>>Year 3</option>
                            <option value="Year 4" <?php echo $currentYear == 'Year 4' ? 'selected' : ''; ?>>Year 4</option>
                            <option value="Year 5" <?php echo $currentYear == 'Year 5' ? 'selected' : ''; ?>>Year 5</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Faculty</label>
                    <input type="text" name="faculty" class="form-input" value="<?php echo $faculty; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Program</label>
                    <input type="text" name="program" class="form-input" value="<?php echo $program; ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Duration</label>
                        <select name="duration" class="form-input">
                            <option value="">Select Duration</option>
                            <option value="1 Year" <?php echo $duration == '1 Year' ? 'selected' : ''; ?>>1 Year</option>
                            <option value="2 Years" <?php echo $duration == '2 Years' ? 'selected' : ''; ?>>2 Years</option>
                            <option value="3 Years" <?php echo $duration == '3 Years' ? 'selected' : ''; ?>>3 Years</option>
                            <option value="4 Years" <?php echo $duration == '4 Years' ? 'selected' : ''; ?>>4 Years</option>
                            <option value="5 Years" <?php echo $duration == '5 Years' ? 'selected' : ''; ?>>5 Years</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">WhatsApp</label>
                        <input type="text" name="whatsapp" class="form-input" value="<?php echo $whatsapp; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Portal Username</label>
                        <input type="text" name="portal_username" class="form-input" value="<?php echo $portalUsername; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Portal Password</label>
                        <input type="password" name="portal_password" class="form-input" placeholder="Leave blank to keep current">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal" id="courseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Course</h3>
                <button class="close-modal" onclick="closeCourseModal()">&times;</button>
            </div>
            
            <form method="post">
                <input type="hidden" name="action" value="add_course">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Course Code</label>
                        <input type="text" name="course_code" class="form-input" placeholder="e.g., CS101" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Credits</label>
                        <input type="number" name="credits" class="form-input" placeholder="e.g., 3" required min="1" max="6">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Course Name</label>
                    <input type="text" name="course_name" class="form-input" placeholder="e.g., Introduction to Computer Science" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-input" required>
                            <option value="">Select Semester</option>
                            <option value="Semester 1">Semester 1</option>
                            <option value="Semester 2">Semester 2</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-input" value="<?php echo date('Y'); ?>" required min="2000" max="2030">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-plus"></i>
                        Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dark Mode Toggle -->
    <div class="dark-mode-toggle">
        <button class="theme-toggle" id="themeToggleBottom">
            <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        </button>
    </div>

    <script>
        // ==================== THEME TOGGLE ====================
        const themeToggle = document.getElementById('themeToggle');
        const themeToggleBottom = document.getElementById('themeToggleBottom');
        
        function toggleTheme() {
            document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        }
        
        if (themeToggle) themeToggle.addEventListener('click', toggleTheme);
        if (themeToggleBottom) themeToggleBottom.addEventListener('click', toggleTheme);
        
        // Set initial theme
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }

        // ==================== MODAL FUNCTIONS ====================
        function openEditModal() {
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function openCourseModal() {
            document.getElementById('courseModal').classList.add('active');
        }
        
        function closeCourseModal() {
            document.getElementById('courseModal').classList.remove('active');
        }
        
        function deleteCourse(courseId) {
            if (confirm('Are you sure you want to delete this course?')) {
                window.location.href = `profile.php?delete_course=${courseId}`;
            }
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // ==================== NOTIFICATION SYSTEM ====================
        const notificationBtn = document.getElementById('notificationBtn');
        const mobileDrawer = document.getElementById('mobileNotifDrawer');
        const drawerBackdrop = document.getElementById('drawerBackdrop');
        const closeDrawerBtn = document.getElementById('closeDrawerBtn');
        const drawerList = document.getElementById('drawerList');

        function openDrawer() {
            if (!mobileDrawer) return;
            mobileDrawer.setAttribute('aria-hidden', 'false');
            mobileDrawer.classList.add('open');
            document.body.style.overflow = 'hidden';
            loadMobileNotifications();
        }

        function closeDrawer() {
            if (!mobileDrawer) return;
            mobileDrawer.setAttribute('aria-hidden', 'true');
            mobileDrawer.classList.remove('open');
            document.body.style.overflow = '';
        }

        if (notificationBtn) {
            notificationBtn.addEventListener('click', function(e) {
                // On small screens, intercept the click and open the drawer.
                if (window.matchMedia('(max-width: 768px)').matches) {
                    e.preventDefault();
                    openDrawer();
                }
                // On larger screens the anchor navigates to notifications.php as intended.
            });
        }

        if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);
        if (closeDrawerBtn) closeDrawerBtn.addEventListener('click', closeDrawer);

        // Load recent notifications into the mobile drawer via AJAX
        async function loadMobileNotifications() {
            if (!drawerList) return;
            drawerList.innerHTML = '<div class="loading-notifications">Loading...</div>';
            try {
                const res = await fetch('/api/get-notifications.php?limit=10');
                if (!res.ok) throw new Error('Network response was not ok');
                const items = await res.json();

                if (!items || items.length === 0) {
                    drawerList.innerHTML = '<div class="no-notifications">No notifications</div>';
                    return;
                }

                drawerList.innerHTML = items.map(n => {
                    const readClass = n.is_read ? '' : 'unread';
                    const time = (new Date(n.created_at)).toLocaleString();
                    const action = n.action_url ? n.action_url : `notifications.php?mark_read=${n.notification_id}`;
                    return `
                        <a href="${action}" class="notification-item ${readClass}" style="display:flex;gap:0.75rem;align-items:flex-start;padding:0.75rem;border-bottom:1px solid var(--border-light);text-decoration:none;color:inherit;">
                            <div style="width:40px;height:40px;border-radius:10px;background:rgba(6,182,212,0.08);display:flex;align-items:center;justify-content:center;color:var(--primary);flex-shrink:0;">
                                ${getIconHtml(n.type)}
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
                                    <div style="font-weight:700;">${escapeHtml(n.title)}</div>
                                    <div style="font-size:0.85rem;color:var(--text-light);">${time}</div>
                                </div>
                                <div style="color:var(--text-subtle);margin-top:0.25rem;">${escapeHtml(n.message)}</div>
                            </div>
                        </a>
                    `;
                }).join('');
            } catch (err) {
                console.error('Error loading notifications:', err);
                drawerList.innerHTML = '<div class="error-notifications">Failed to load notifications</div>';
            }
        }

        // Small helper to escape HTML (client-side)
        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"})[m]; });
        }

        // Small helper to return icon HTML based on type
        function getIconHtml(type) {
            switch (type) {
                case 'success': return '<i class="fas fa-check-circle"></i>';
                case 'warning': return '<i class="fas fa-exclamation-triangle"></i>';
                case 'error': return '<i class="fas fa-times-circle"></i>';
                case 'marketplace': return '<i class="fas fa-shopping-cart"></i>';
                case 'academic': return '<i class="fas fa-graduation-cap"></i>';
                default: return '<i class="fas fa-info-circle"></i>';
            }
        }

        // ==================== BROWSER NOTIFICATIONS ====================
        class NotificationManager {
            constructor() {
                this.permission = null;
                this.init();
            }

            async init() {
                // Check if browser supports notifications
                if (!('Notification' in window)) {
                    console.log('This browser does not support notifications');
                    return;
                }

                this.permission = Notification.permission;
                
                // If permission is not granted, request it
                if (this.permission === 'default') {
                    await this.requestPermission();
                }

                // Show welcome notification if granted
                if (this.permission === 'granted') {
                    this.showWelcomeNotification();
                }
            }

            async requestPermission() {
                try {
                    this.permission = await Notification.requestPermission();
                    return this.permission;
                } catch (error) {
                    console.error('Error requesting notification permission:', error);
                    return 'denied';
                }
            }

            showWelcomeNotification() {
                if (this.permission === 'granted') {
                    const notification = new Notification('UniLink Notifications Enabled 🔔', {
                        body: 'You will now receive important updates from UniLink.',
                        icon: '/favicon.ico',
                        badge: '/favicon.ico',
                        tag: 'welcome',
                        requireInteraction: false
                    });

                    notification.onclick = () => {
                        window.focus();
                        notification.close();
                    };

                    // Auto close after 4 seconds
                    setTimeout(() => {
                        notification.close();
                    }, 4000);
                }
            }

            showNotification(title, message, options = {}) {
                if (this.permission !== 'granted') return null;

                const notification = new Notification(title, {
                    body: message,
                    icon: options.icon || '/favicon.ico',
                    badge: options.badge || '/favicon.ico',
                    tag: options.tag || 'unilink-notification',
                    data: options.data || {},
                    requireInteraction: options.requireInteraction || false,
                    silent: options.silent || false
                });

                // Handle notification click
                notification.onclick = () => {
                    window.focus();
                    
                    // Navigate to specific page if URL provided
                    if (options.url) {
                        window.location.href = options.url;
                    }
                    
                    notification.close();
                };

                // Auto close after 5 seconds unless requireInteraction is true
                if (!options.requireInteraction) {
                    setTimeout(() => {
                        notification.close();
                    }, 5000);
                }

                return notification;
            }

            // Show different types of notifications
            showSuccessNotification(message, options = {}) {
                return this.showNotification('✅ Success', message, options);
            }

            showErrorNotification(message, options = {}) {
                return this.showNotification('❌ Error', message, options);
            }

            showInfoNotification(message, options = {}) {
                return this.showNotification('ℹ️ Info', message, options);
            }
        }

        // Initialize notification manager
        let notificationManager;
        document.addEventListener('DOMContentLoaded', function() {
            notificationManager = new NotificationManager();
            
            // Test notification after 3 seconds (remove in production)
            setTimeout(() => {
                if (notificationManager.permission === 'granted') {
                    notificationManager.showInfoNotification(
                        'Welcome to your UniLink profile! Manage your academic information and stay updated with notifications.',
                        { requireInteraction: false }
                    );
                }
            }, 3000);
        });

        // ==================== AVATAR UPLOAD PREVIEW ====================
        const avatarUpload = document.getElementById('avatarUpload');
        if (avatarUpload) {
            avatarUpload.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const avatarImg = document.querySelector('.avatar-img');
                        const avatarFallback = document.querySelector('.avatar-fallback');
                        
                        if (avatarImg) {
                            avatarImg.src = e.target.result;
                            avatarImg.style.display = 'block';
                        }
                        if (avatarFallback) {
                            avatarFallback.style.display = 'none';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>

<?php
// Helper function for time formatting
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>