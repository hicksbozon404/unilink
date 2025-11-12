<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Register</title>
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

        .register-card {
            width: 100%; max-width: 460px;
            background: var(--bg-card);
            padding: 3rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 12px 30px -6px var(--shadow-color);
            border: 1px solid var(--border-color);
            transition: all .4s ease;
        }
        .register-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px -10px var(--shadow-color);
        }
        .register-card h1 {
            font-size: 2rem; font-weight: 800;
            color: var(--primary); text-align: center;
            margin-bottom: .5rem;
        }
        .register-card p.subtitle {
            color: var(--text-subtle); text-align: center;
            margin-bottom: 2rem; font-size: 1rem;
        }

        .form-group { margin-bottom: 1.25rem; }
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

        .register-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white; padding: 1rem;
            border-radius: .5rem; font-size: 1.1rem; font-weight: 700;
            border: none; cursor: pointer;
            transition: all .3s cubic-bezier(.25,.8,.25,1);
            box-shadow: 0 8px 15px -5px var(--shadow-color);
            margin-top: 1rem;
        }
        .register-btn:hover {
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
            .register-card { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<main class="register-card">
    <h1>Create Account</h1>
    <p class="subtitle">Join UniLink and manage your campus life in one place.</p>

    <!-- Messages -->
    <?php
    if (isset($_SESSION['register_error'])) {
        echo '<div class="message-box error">' . htmlspecialchars($_SESSION['register_error']) . '</div>';
        unset($_SESSION['register_error']);
    }
    if (isset($_SESSION['login_success'])) {
        echo '<div class="message-box success">' . htmlspecialchars($_SESSION['login_success']) . '</div>';
        unset($_SESSION['login_success']);
    }
    ?>

    <form action="register_user.php" method="POST">
        <div class="form-group">
            <label for="full_names">Full Name</label>
            <input type="text" id="full_names" name="full_names" required placeholder="eg Hicks Bozon">
        </div>

        <div class="form-group">
            <label for="email">Student Email</label>
            <input type="email" id="email" name="email" required placeholder="eg hicksbozon@gmail.com">
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" required placeholder="+260123456789">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required placeholder="8+ characters">
        </div>

        <button type="submit" class="register-btn">Register & Continue →</button>
    </form>

    <div class="alt-actions">
        <span>Already registered?</span> <a href="login.php">Log In Here</a>
    </div>
</main>

</ Coulter>
</body>
</html>