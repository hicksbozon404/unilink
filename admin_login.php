<?php
session_start();
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_username = 'admin'; // In production, use environment variables
    $admin_password = 'admin123'; // In production, use hashed passwords
    
    if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
        $_SESSION['admin_loggedin'] = true;
        $_SESSION['admin_username'] = $admin_username;
        header('Location: admin_dashboard.php');
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Admin Login</title>
    <style>
        /* Reuse your existing theme variables */
        :root {
            --primary: #06b6d4; --primary-hover: #0e7490; --bg-body: #f8fafc;
            --bg-card: #ffffff; --text-main: #1e293b; --border: #e2e8f0;
        }
        .login-container { 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: var(--bg-body); 
        }
        .login-card { 
            background: var(--bg-card); 
            padding: 3rem; 
            border-radius: 1.5rem; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); 
            width: 100%; 
            max-width: 400px; 
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2 style="text-align:center; margin-bottom: 2rem;">Admin Login</h2>
            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="username" placeholder="Admin Username" required 
                       style="width: 100%; padding: 1rem; margin-bottom: 1rem; border: 2px solid var(--border); border-radius: 0.75rem;">
                <input type="password" name="password" placeholder="Password" required 
                       style="width: 100%; padding: 1rem; margin-bottom: 1.5rem; border: 2px solid var(--border); border-radius: 0.75rem;">
                <button type="submit" 
                        style="width: 100%; padding: 1rem; background: var(--primary); color: white; border: none; border-radius: 0.75rem; font-weight: 600;">
                    Login
                </button>
            </form>
        </div>
    </div>
</body>
</html>