<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Student Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        
        :root {
            --primary: #06b6d4; --primary-hover: #0e7490;
            --bg-body: #ffffff; --bg-card: #f9fafb;
            --text-main: #1f2937; --text-subtle: #4b5563;
            --shadow-color: rgba(6, 182, 212, 0.15); --border-color: #e5e7eb;
            --transition-speed: 0.5s;
        }
        :root.dark {
            --primary: #41e1ff; --primary-hover: #06b6d4;
            --bg-body: #121e35; --bg-card: #1c2b47;
            --text-main: #e2e8f0; --text-subtle: #94a3b8;
            --shadow-color: rgba(6, 182, 212, 0.35); --border-color: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex; justify-content: center; align-items: center;
            padding: 2rem;
            transition: background var(--transition-speed), color var(--transition-speed);
            line-height: 1.6;
        }
        a { color: var(--primary); text-decoration: none; transition: color .2s; }
        a:hover { color: var(--primary-hover); }

        .login-card {
            width: 100%; max-width: 400px;
            background: var(--bg-card);
            padding: 3rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 12px 30px -6px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all .4s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -10px var(--shadow-color);
        }
        .login-card h1 {
            font-size: 2rem; font-weight: 800;
            color: var(--primary); text-align: center;
            margin-bottom: .5rem;
        }
        .login-card p.subtitle {
            color: var(--text-subtle); text-align: center;
            margin-bottom: 2rem; font-size: 1rem;
        }

        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block; font-weight: 600; margin-bottom: .5rem;
            color: var(--text-main); font-size: .9rem;
        }
        .form-group input {
            width: 100%; padding: .75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: .5rem;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 1rem;
            transition: border .3s, box-shadow .3s;
        }
        .form-group input:focus {
            outline: none; border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, .2);
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white; padding: 1rem;
            border-radius: .5rem; font-size: 1.1rem; font-weight: 700;
            border: none; cursor: pointer;
            transition: all .3s cubic-bezier(.25,.8,.25,1);
            box-shadow: 0 8px 15px -5px var(--shadow-color);
            margin-top: 1rem;
        }
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px -8px var(--shadow-color);
        }

        .alt-actions {
            text-align: center; margin-top: 1.5rem;
            font-size: .95rem; color: var(--text-subtle);
        }

        .message-box {
            padding: 1rem; border-radius: .5rem;
            margin-bottom: 1.5rem; text-align: center;
            font-weight: 600; font-size: .95rem;
        }
        .error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        @media (max-width: 600px) {
            .login-card { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<main class="login-card">
    <h1>Welcome Back</h1>
    <p class="subtitle">Sign in to access your student dashboard.</p>

    <!-- Error / Success Messages -->
    <?php
    if (isset($_SESSION['login_error'])) {
        echo '<div class="message-box error">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
        unset($_SESSION['login_error']);
    }
    if (isset($_SESSION['login_success'])) {
        echo '<div class="message-box success">' . htmlspecialchars($_SESSION['login_success']) . '</div>';
        unset($_SESSION['login_success']);
    }
    ?>

    <form action="authenticate.php" method="POST">
        <div class="form-group">
            <label for="identifier">Email </label>
            <input type="text" id="identifier" name="identifier" required placeholder="e.g hicksbozon@gmail.com">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="password">
        </div>

        <button type="submit" class="login-btn">Secure Login</button>
    </form>

    <div class="alt-actions">
        <a href="forgot_password.php">Forgot Password?</a> | 
        <span>New here?</span> <a href="register.php">Register Now</a>
    </div>
</main>

</body>
</html>