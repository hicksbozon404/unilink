<?php
session_start();
if (!isset($_SESSION['admin_loggedin']) || $_SESSION['admin_loggedin'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// ---------- DB Connection ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// ---------- Get Statistics ----------
// Total Users
$totalUsers = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];

// Total Marketplace Items
$totalItems = $pdo->query("SELECT COUNT(*) as count FROM marketplace_items")->fetch()['count'];

// Total Documents in Vault
$totalDocuments = $pdo->query("SELECT COUNT(*) as count FROM documents")->fetch()['count'];

// Recent Users (last 7 days)
$recentUsers = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['count'];

// Active Conversations
$activeConversations = $pdo->query("SELECT COUNT(*) as count FROM marketplace_conversations")->fetch()['count'];

// Platform Growth (users per month)
$growthData = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY month
")->fetchAll();

// Fixed Recent Activities - Separate queries instead of UNION
$recentActivities = [];

// Get recent user registrations
$usersQuery = $pdo->query("
    SELECT 'user_registered' as type, full_names as title, created_at as date 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 4
");
$recentActivities = array_merge($recentActivities, $usersQuery->fetchAll());

// Get recent marketplace items
$itemsQuery = $pdo->query("
    SELECT 'item_listed' as type, title, created_at as date 
    FROM marketplace_items 
    ORDER BY created_at DESC 
    LIMIT 3
");
$recentActivities = array_merge($recentActivities, $itemsQuery->fetchAll());

// Get recent document uploads
$docsQuery = $pdo->query("
    SELECT 'document_uploaded' as type, original_name as title, uploaded_at as date 
    FROM documents 
    ORDER BY uploaded_at DESC 
    LIMIT 3
");
$recentActivities = array_merge($recentActivities, $docsQuery->fetchAll());

// Sort all activities by date
usort($recentActivities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Limit to 10 most recent
$recentActivities = array_slice($recentActivities, 0, 10);

// Get users for management
$users = $pdo->query("SELECT user_id, full_names, email, created_at, phone FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Get reported items - check if table exists first
$reportedItems = [];
try {
    $reportedItems = $pdo->query("
        SELECT mi.item_id, mi.title, mi.user_id, u.full_names, 
               (SELECT COUNT(*) FROM reported_items WHERE item_id = mi.item_id) as report_count
        FROM marketplace_items mi
        LEFT JOIN users u ON mi.user_id = u.user_id
        WHERE EXISTS (SELECT 1 FROM reported_items WHERE item_id = mi.item_id)
        ORDER BY report_count DESC
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    // Table doesn't exist yet, we'll create it later
    $reportedItems = [];
}

// Get system health metrics
$systemHealth = [
    'database_size' => $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = 'unilink_db'")->fetch()['size_mb'],
    'active_today' => $pdo->query("SELECT COUNT(DISTINCT user_id) as count FROM (SELECT user_id FROM users WHERE last_login >= CURDATE() UNION SELECT user_id FROM marketplace_messages WHERE created_at >= CURDATE() UNION SELECT user_id FROM documents WHERE uploaded_at >= CURDATE()) as activity")->fetch()['count']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #06b6d4; --primary-hover: #0e7490; --primary-glow: #06b6d433;
            --secondary: #8b5cf6; --accent: #f59e0b; --success: #10b981; 
            --warning: #f59e0b; --error: #ef4444; --bg-body: #f8fafc;
            --bg-card: #ffffff; --text-main: #1e293b; --text-subtle: #64748b;
            --border: #e2e8f0; --shadow-lg: 0 25px 50px -12px rgba(0,0,0,0.15);
            --shadow-md: 0 10px 15px -3px rgba(0,0,0,0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text-main); line-height: 1.6; }

        .admin-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }

        .container { max-width: 1400px; margin: auto; padding: 0 1.5rem; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .admin-grid { grid-template-columns: 1fr; }
        }

        .admin-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-md);
        }

        .admin-card h3 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-main);
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: background-color 0.2s;
        }

        .activity-item:hover {
            background: var(--bg-body);
            border-radius: 0.5rem;
            margin: 0 -0.5rem;
            padding: 1rem 0.5rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .user-registered { background: var(--success); }
        .item-listed { background: var(--primary); }
        .document-uploaded { background: var(--secondary); }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .table th, .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            background: var(--bg-body);
            font-weight: 600;
            color: var(--text-subtle);
        }

        .table tr:hover {
            background: var(--bg-body);
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.875rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger { background: var(--error); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-secondary { background: var(--secondary); color: white; }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .system-health {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .health-metric {
            text-align: center;
            padding: 1rem;
            background: var(--bg-body);
            border-radius: 0.75rem;
        }

        .health-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .health-label {
            font-size: 0.875rem;
            color: var(--text-subtle);
            margin-top: 0.25rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-subtle);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <!-- Admin Header -->
    <header class="admin-header">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-family: 'Space Grotesk', sans-serif; color: var(--primary); margin-bottom: 0.25rem;">
                        <i class="fas fa-cogs"></i> UniLink Admin
                    </h1>
                    <p style="color: var(--text-subtle); font-size: 0.9rem;">
                        <i class="fas fa-user-shield"></i> Welcome back, <?= htmlspecialchars($_SESSION['admin_username']) ?>
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="admin_dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="admin_users.php" class="btn btn-secondary">
                        <i class="fas fa-users"></i> Users
                    </a>
                    <a href="admin_marketplace.php" class="btn" style="background: var(--accent); color: white;">
                        <i class="fas fa-store"></i> Marketplace
                    </a>
                    <a href="admin_logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
                <div style="color: var(--text-subtle); font-weight: 600;">Total Users</div>
                <div style="color: var(--success); font-size: 0.9rem; margin-top: 0.5rem;">
                    <i class="fas fa-arrow-up"></i> +<?= $recentUsers ?> this week
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalItems) ?></div>
                <div style="color: var(--text-subtle); font-weight: 600;">Marketplace Items</div>
                <div style="color: var(--primary); font-size: 0.9rem; margin-top: 0.5rem;">
                    <i class="fas fa-shopping-cart"></i> Active listings
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($totalDocuments) ?></div>
                <div style="color: var(--text-subtle); font-weight: 600;">Documents in Vault</div>
                <div style="color: var(--secondary); font-size: 0.9rem; margin-top: 0.5rem;">
                    <i class="fas fa-shield-alt"></i> Secure storage
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($activeConversations) ?></div>
                <div style="color: var(--text-subtle); font-weight: 600;">Active Conversations</div>
                <div style="color: var(--accent); font-size: 0.9rem; margin-top: 0.5rem;">
                    <i class="fas fa-comments"></i> User engagement
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="admin-grid">
            <!-- Left Column -->
            <div>
                <!-- Recent Activities -->
                <div class="admin-card">
                    <h3>
                        <i class="fas fa-history"></i> Recent Activities
                    </h3>
                    <div>
                        <?php if (!empty($recentActivities)): ?>
                            <?php foreach ($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon <?= $activity['type'] === 'user_registered' ? 'user-registered' : ($activity['type'] === 'item_listed' ? 'item-listed' : 'document-uploaded') ?>">
                                        <i class="fas <?= $activity['type'] === 'user_registered' ? 'fa-user-plus' : ($activity['type'] === 'item_listed' ? 'fa-tag' : 'fa-file-upload') ?>"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-main);">
                                            <?= htmlspecialchars($activity['title']) ?>
                                        </div>
                                        <div style="color: var(--text-subtle); font-size: 0.875rem;">
                                            <?= ucfirst(str_replace('_', ' ', $activity['type'])) ?> • 
                                            <?= date('M j, g:i A', strtotime($activity['date'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reported Items -->
                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h3 style="color: var(--error);">
                        <i class="fas fa-flag"></i> Reported Items
                        <?php if (!empty($reportedItems)): ?>
                            <span style="background: var(--error); color: white; padding: 0.25rem 0.5rem; border-radius: 99px; font-size: 0.8rem; margin-left: 0.5rem;">
                                <?= count($reportedItems) ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                    <div>
                        <?php if (!empty($reportedItems)): ?>
                            <?php foreach ($reportedItems as $item): ?>
                                <div class="activity-item">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600;"><?= htmlspecialchars($item['title']) ?></div>
                                        <div style="color: var(--text-subtle); font-size: 0.875rem;">
                                            By <?= htmlspecialchars($item['full_names']) ?> • 
                                            <?= $item['report_count'] ?> report<?= $item['report_count'] > 1 ? 's' : '' ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-warning" style="padding: 0.5rem; font-size: 0.8rem;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-danger" style="padding: 0.5rem; font-size: 0.8rem;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-flag"></i>
                                <p>No reported items</p>
                                <small style="color: var(--success); margin-top: 0.5rem;">
                                    <i class="fas fa-check-circle"></i> Everything looks good!
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Recent Users -->
                <div class="admin-card">
                    <h3>
                        <i class="fas fa-users"></i> Recent Users
                        <span style="background: var(--primary); color: white; padding: 0.25rem 0.5rem; border-radius: 99px; font-size: 0.8rem; margin-left: 0.5rem;">
                            <?= count($users) ?>
                        </span>
                    </h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= htmlspecialchars($user['full_names']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td style="color: var(--text-subtle); font-size: 0.875rem;">
                                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.25rem;">
                                            <a href="admin_user_view.php?id=<?= $user['user_id'] ?>" class="btn btn-primary" style="padding: 0.5rem; font-size: 0.8rem;">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-danger" style="padding: 0.5rem; font-size: 0.8rem;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="admin_users.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> View All Users
                        </a>
                    </div>
                </div>

                <!-- Quick Actions & System Health -->
                <div class="admin-card" style="margin-top: 1.5rem;">
                    <h3>
                        <i class="fas fa-bolt"></i> Quick Actions
                    </h3>
                    <div class="quick-actions">
                        <a href="admin_announcements.php" class="btn btn-success">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                        <a href="admin_backup.php" class="btn btn-secondary">
                            <i class="fas fa-database"></i> Backup
                        </a>
                        <a href="admin_analytics.php" class="btn" style="background: var(--accent); color: white;">
                            <i class="fas fa-chart-bar"></i> Analytics
                        </a>
                        <a href="admin_settings.php" class="btn" style="background: var(--text-subtle); color: white;">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </div>

                    <!-- System Health -->
                    <div class="system-health">
                        <div class="health-metric">
                            <div class="health-value"><?= $systemHealth['database_size'] ?>MB</div>
                            <div class="health-label">Database Size</div>
                        </div>
                        <div class="health-metric">
                            <div class="health-value"><?= $systemHealth['active_today'] ?></div>
                            <div class="health-label">Active Today</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Add interactive functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animations
            const cards = document.querySelectorAll('.stat-card, .admin-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add confirmation for delete actions
            const deleteButtons = document.querySelectorAll('.btn-danger');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });

            console.log('Admin dashboard loaded successfully');
        });
    </script>
</body>
</html>