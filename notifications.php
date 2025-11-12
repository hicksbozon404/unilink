<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'classes/Notification.php';

// Minimal time-ago helper (used in profile.php too)
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
    foreach ($string as $k => & $v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// PDO connection (same pattern used elsewhere)
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$notification = new Notification($pdo);
$userId = $_SESSION['user_id'];

// Handle actions
if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    $notification->markAsRead($nid, $userId);
    header('Location: notifications.php');
    exit();
}

if (isset($_GET['mark_all_read'])) {
    $notification->markAllAsRead($userId);
    header('Location: notifications.php');
    exit();
}

// Pagination basics
$limit = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch notifications with simple pager
$stmt = $pdo->prepare("SELECT SQL_CALC_FOUND_ROWS * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll();

$totalStmt = $pdo->query("SELECT FOUND_ROWS() AS total");
$total = (int)$totalStmt->fetch()['total'];
$totalPages = (int)ceil($total / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Notifications - UniLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #1e293b; margin:0; padding:0; }
        .container { max-width: 900px; margin: 1rem auto; padding: 1rem; }
        .header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1rem; }
        .title { font-weight:800; font-size:1.25rem; }
        .actions { display:flex; gap:.5rem; }
        .btn { padding:.5rem .75rem; border-radius:.5rem; border:1px solid #e2e8f0; background:#fff; cursor:pointer; }
        .btn.primary { background:linear-gradient(135deg,#06b6d4,#0e7490); color:#fff; border:none; }
        .list { background:#fff; border:1px solid #e2e8f0; border-radius:1rem; overflow:hidden; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
        .item { display:flex; gap:1rem; padding:1rem; border-bottom:1px solid #f1f5f9; align-items:flex-start; }
        .item:last-child { border-bottom:none; }
        .icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#06b6d4; background:rgba(6,182,212,0.08); flex-shrink:0; }
        .content { flex:1; min-width:0; }
        .title-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; }
        .notif-title { font-weight:700; font-size:1rem; }
        .notif-message { color:#64748b; margin-top:.25rem; font-size:.95rem; }
        .meta { color:#94a3b8; font-size:.85rem; margin-top:.5rem; }
        .unread { background: linear-gradient(90deg, rgba(6,182,212,0.06), transparent); }
        .dot { width:8px; height:8px; border-radius:50%; background:#06b6d4; margin-left:.5rem; }
        .small { font-size:.9rem; }
        .pager { display:flex; gap:.5rem; justify-content:center; padding:1rem; }
        a.link { color:var(--primary, #06b6d4); text-decoration:none; }
        @media (max-width:600px) {
            .item { padding:.75rem; gap:.75rem; }
            .icon { width:38px; height:38px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">Notifications</div>
            <div class="actions">
                <a class="btn" href="profile.php"><i class="fas fa-arrow-left"></i> Back</a>
                <a class="btn" href="notifications.php?mark_all_read=1">Mark all as read</a>
            </div>
        </div>

        <div class="list" role="list">
            <?php if (empty($notifications)): ?>
                <div class="item text-center" style="justify-content:center; color:#64748b;">
                    <div>No notifications yet.</div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="item <?php echo $n['is_read'] ? '' : 'unread'; ?>" role="listitem">
                        <div class="icon <?php echo htmlspecialchars($n['type']); ?>">
                            <?php
                                switch ($n['type']) {
                                    case 'success': echo '<i class="fas fa-check-circle"></i>'; break;
                                    case 'warning': echo '<i class="fas fa-exclamation-triangle"></i>'; break;
                                    case 'error': echo '<i class="fas fa-times-circle"></i>'; break;
                                    case 'marketplace': echo '<i class="fas fa-shopping-cart"></i>'; break;
                                    case 'academic': echo '<i class="fas fa-graduation-cap"></i>'; break;
                                    default: echo '<i class="fas fa-info-circle"></i>';
                                }
                            ?>
                        </div>
                        <div class="content">
                            <div class="title-row">
                                <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="small">
                                    <?php echo htmlspecialchars(time_elapsed_string($n['created_at'])); ?>
                                    <?php if (!$n['is_read']): ?><span class="dot" aria-hidden="true"></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
                            <div class="meta">
                                <?php if (!empty($n['action_url'])): ?>
                                    <a class="link" href="<?php echo htmlspecialchars($n['action_url']); ?>">Open</a>
                                    &middot; 
                                <?php endif; ?>
                                <a class="link" href="notifications.php?mark_read=<?php echo (int)$n['notification_id']; ?>">Mark as read</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pager">
                <?php if ($page > 1): ?>
                    <a class="btn" href="notifications.php?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                <?php endif; ?>

                <div class="small">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>

                <?php if ($page < $totalPages): ?>
                    <a class="btn" href="notifications.php?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>