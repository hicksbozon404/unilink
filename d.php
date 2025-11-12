<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId   = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');
$email    = htmlspecialchars($_SESSION['email'] ?? 'student@unilink.edu');

// ---------- DB ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ---------- VAULT (latest 5) ----------
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 5");
$stmt->execute([$userId]);
$vaultFiles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Dashboard</title>

    <!-- Fonts + Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* ----------------------------------------------------- */
        /* 1. Global Setup & CSS Variables for Theming */
        /* ----------------------------------------------------- */
        :root {
            /* Light Theme */
            --primary: #06b6d4; /* Vibrant Teal/Cyan */
            --primary-hover: #0e7490;
            --bg-body: #ffffff;
            --bg-card: #f9fafb;
            --bg-sidebar: #ffffff;
            --text-main: #1f2937;
            --text-subtle: #4b5563;
            --shadow-color: rgba(6, 182, 212, 0.15);
            --border-color: #e5e7eb;
            --success: #10b981;
            --error: #ef4444;
            --transition-speed: 0.3s;
        }

        :root.dark {
            /* Dark Theme (Futuristic/Modern) */
            --primary: #41e1ff;
            --primary-hover: #06b6d4;
            --bg-body: #0f172a; /* Dark Blue Slate */
            --bg-card: #1e293b; /* Slightly lighter card background */
            --bg-sidebar: #1e293b;
            --text-main: #e2e8f0;
            --text-subtle: #94a3b8;
            --shadow-color: rgba(65, 225, 255, 0.1);
            --border-color: #334155;
            --success: #34d399;
            --error: #f87171;
        }

        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            line-height: 1.6;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        /* ----------------------------------------------------- */
        /* 2. Layout & Sidebar */
        /* ----------------------------------------------------- */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border-color);
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
            transition: background-color var(--transition-speed);
        }
        .sidebar-header {
            text-align: center;
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        .sidebar-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0;
        }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            text-decoration: none;
            color: var(--text-subtle);
            font-weight: 500;
            transition: background-color 0.2s, color 0.2s;
            border-left: 4px solid transparent;
        }
        .nav-link:hover {
            background-color: var(--bg-card);
            color: var(--primary-hover);
        }
        .nav-link.active {
            background-color: rgba(6, 182, 212, 0.1);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        }
        .nav-link i {
            margin-right: 1rem;
            font-size: 1.1rem;
        }
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            background-color: var(--bg-body);
        }
        .user-info {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            margin-top: auto;
        }
        .user-info button {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-weight: 600;
            padding: 0.5rem 0;
            transition: color 0.2s;
        }
        .user-info button:hover {
            color: var(--primary-hover);
        }

        /* ----------------------------------------------------- */
        /* 3. Dashboard Grid & Cards */
        /* ----------------------------------------------------- */
        h2 {
            font-family: 'Space Grotesk', sans-serif;
            color: var(--primary);
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 10px var(--shadow-color);
            transition: all 0.3s;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px var(--shadow-color);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .card-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--text-main);
        }
        .card-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }
        .stat-label {
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        /* Vault List */
        .vault-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .vault-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px dashed var(--border-color);
        }
        .vault-list li:last-child {
            border-bottom: none;
        }
        .file-name {
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-main);
        }
        .file-name i {
            color: var(--text-subtle);
        }
        .view-btn {
            text-decoration: none;
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 600;
            transition: color 0.2s;
        }
        .view-btn:hover {
            color: var(--primary-hover);
        }

        /* ----------------------------------------------------- */
        /* 4. Profile Modal */
        /* ----------------------------------------------------- */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal.active { display: flex; }
        .modal-content {
            background-color: var(--bg-card);
            padding: 2rem;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        .modal-content h3 {
            color: var(--primary);
            margin-top: 0;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .modal-content form div {
            margin-bottom: 1rem;
        }
        .modal-content label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .modal-content input {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--bg-body);
            color: var(--text-main);
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .btn-cancel, .btn-save {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-cancel { background-color: var(--text-subtle); color: white; }
        .btn-save { background-color: var(--primary); color: white; }
        .btn-cancel:hover { background-color: #6b7280; }
        .btn-save:hover { background-color: var(--primary-hover); }

        /* ----------------------------------------------------- */
        /* 5. Mobile Adjustments */
        /* ----------------------------------------------------- */
        @media (max-width: 768px) {
            .app-layout { flex-direction: column; }
            .sidebar {
                width: 100%;
                padding: 1rem;
                flex-direction: row;
                justify-content: space-around;
                border-bottom: 1px solid var(--border-color);
                border-right: none;
            }
            .sidebar-header { display: none; }
            .user-info { display: none; }
            .sidebar nav { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; }
            .nav-link { 
                padding: 0.5rem 1rem;
                border-left: none;
                border-bottom: 3px solid transparent;
            }
            .nav-link.active {
                border-left: none;
                border-bottom: 3px solid var(--primary);
            }
            .nav-link span { display: none; }
            .nav-link i { margin-right: 0; }
            .main-content { padding: 1rem; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- Sidebar / Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>UniLink</h1>
        </div>
        <nav>
            <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            <a href="grades.php" class="nav-link"><i class="fas fa-graduation-cap"></i> <span>Grades</span></a>
            <a href="payments.php" class="nav-link"><i class="fas fa-money-bill-wave"></i> <span>Payments</span></a>
            <a href="vault.php" class="nav-link"><i class="fas fa-vault"></i> <span>Vault</span></a>
            <!-- NEW MARKETPLACE LINK -->
            <a href="market.php" class="nav-link"><i class="fas fa-store"></i> <span>Campus Market</span></a>
            <!-- END NEW LINK -->
        </nav>
        <div class="user-info">
            <p>Welcome, <strong><?= $fullName ?></strong></p>
            <button onclick="openProfile()">Manage Profile</button>
            <a href="logout.php"><button style="color: var(--error);">Log Out <i class="fas fa-sign-out-alt"></i></button></a>
            <button onclick="toggleTheme()" title="Toggle Dark/Light Mode" style="margin-top: 10px; color: var(--text-subtle);">
                <i class="fas fa-moon"></i> / <i class="fas fa-sun"></i> Theme
            </button>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <h2>👋 Hello, <?= $fullName ?>!</h2>
        <p class="stat-label mb-8">Your integrated student hub is ready.</p>

        <div class="dashboard-grid">
            
            <!-- Quick Stats Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Academic Snapshot</h3>
                    <i class="fas fa-chart-line"></i>
                </div>
                <p class="stat-value">3.4</p>
                <p class="stat-label">Current GPA (Avg 3.2)</p>
                <p class="stat-value mt-4">4</p>
                <p class="stat-label">Courses In Progress</p>
            </div>

            <!-- Payments Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Fees & Payments</h3>
                    <i class="fas fa-credit-card"></i>
                </div>
                <p class="stat-value text-subtle" style="color: var(--error);">K1,250.00</p>
                <p class="stat-label">Outstanding Balance (Due Oct 30)</p>
                <a href="payments.php" class="view-btn mt-4 block">View Payment History &raquo;</a>
            </div>

            <!-- Vault Card -->
            <div class="card">
                <div class="card-header">
                    <h3>Latest in Vault</h3>
                    <i class="fas fa-file-alt"></i>
                </div>
                <?php if ($vaultFiles): ?>
                    <ul class="vault-list">
                        <?php foreach ($vaultFiles as $file): ?>
                            <li>
                                <span class="file-name"><i class="fas fa-file"></i> <?= htmlspecialchars($file['file_name']) ?></span>
                                <a href="vault.php" class="view-btn">View</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-subtle">Your vault is empty. Upload your first document!</p>
                <?php endif; ?>
                <a href="vault.php" class="view-btn mt-4 block">Go to Vault &raquo;</a>
            </div>

        </div>
    </main>
</div>

<!-- Profile Modal (unchanged) -->
<div id="profileModal" class="modal">
    <div class="modal-content">
        <h3>Update Profile Details</h3>
        <form action="profile.php" method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div>
                <label>Full Name</label>
                <input type="text" name="full_names" value="<?= $fullName ?>" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?= $email ?>" required>
            </div>
            <div>
                <label>Phone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($_SESSION['phone'] ?? '') ?>" required>
            </div>
            <div class="modal-actions">
                <button type="button" onclick="closeProfile()" class="btn-cancel">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // ---------- THEME ----------
    const html = document.documentElement;
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (stored === 'dark' || (!stored && prefersDark)) html.classList.add('dark');
    function toggleTheme(){
        html.classList.toggle('dark');
        localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
    }

    // ---------- PROFILE MODAL ----------
    function openProfile(){ document.getElementById('profileModal').classList.add('active'); }
    function closeProfile(){ document.getElementById('profileModal').classList.remove('active'); }
</script>

</body>
</html>