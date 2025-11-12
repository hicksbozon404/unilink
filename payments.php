<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');

// ---------- DB ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ---------- CREATE PAYMENTS TABLE ----------
$pdo->exec("CREATE TABLE IF NOT EXISTS payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'ZMW',
    description VARCHAR(255),
    provider VARCHAR(20),
    phone VARCHAR(20),
    status VARCHAR(20) DEFAULT 'PENDING',
    reference VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

// ---------- PROCESS PAYMENT ----------
if ($_POST['action'] ?? '' === 'pay') {
    $amount = $_POST['amount'];
    $desc = $_POST['description'];
    $provider = $_POST['provider'];
    $phone = $_POST['phone'];

    $ref = 'TXN' . time() . rand(100, 999);
    $status = rand(1, 10) > 2 ? 'COMPLETED' : 'FAILED';

    $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, description, provider, phone, status, reference) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$userId, $amount, $desc, $provider, $phone, $status, $ref]);

    header("Location: payments.php?ref=$ref&status=$status");
    exit;
}

// ---------- FETCH HISTORY ----------
$stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$userId]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Mobile Money</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --p:#06b6d4;--ph:#0e7490;--pg:#06b6d433;
            --bg:#f8fafc;--card:#fff;--glass:rgba(255,255,255,.15);
            --t:#1e293b;--ts:#64748b;--b:#e2e8f0;--s:#10b981;--e:#ef4444;
            --airtel:#ff0000;--mtn:#ffff00;--zamtel:#008000;
            --sh-sm:0 4px 6px -1px rgba(0,0,0,.1);--sh-md:0 10px 15px -3px rgba(0,0,0,.1);--sh-lg:0 25px 50px -12px rgba(0,0,0,.15);
            --tr:.35s cubic-bezier(.2,.8,.2,1);
        }
        :root.dark{
            --p:#41e1ff;--ph:#06b6d4;--pg:#41e1ff33;
            --bg:#0f172a;--card:#1e293b;--glass:rgba(30,41,59,.3);
            --t:#f1f5f9;--ts:#94a3b8;--b:#334155;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;transition:all var(--tr);}
        .container{max-width:1100px;margin:auto;padding:0 1.5rem;}

        /* HEADER */
        .header{position:sticky;top:0;background:var(--glass);backdrop-filter:blur(12px);border-bottom:1px solid var(--b);box-shadow:var(--sh-sm);z-index:1000;}
        .nav{display:flex;justify-content:space-between;align-items:center;padding:1rem 0;}
        .logo{font-family:'Space Grotesk',sans-serif;font-size:1.75rem;font-weight:700;color:var(--p);}
        .nav-right{display:flex;gap:1rem;align-items:center;}
        .theme-btn{background:none;border:none;color:var(--t);cursor:pointer;padding:.5rem;border-radius:50%;}
        .theme-btn:hover{background:var(--card);color:var(--p);}
        .theme-btn svg{width:22px;height:22px;}
        :root:not(.dark) .moon{display:none;}
        :root.dark .sun{display:none;}
        .logout{background:var(--e);color:#fff;padding:.5rem 1rem;border-radius:99px;font-weight:600;cursor:pointer;border:none;}

        /* HERO */
        .hero{padding:4rem 0;text-align:center;background:radial-gradient(circle at 30% 70%,var(--pg),transparent 60%);}
        .hero h1{font-size:clamp(2.2rem,5vw,3.5rem);font-weight:900;margin-bottom:.5rem;}
        .hero h1 span{color:var(--p);}
        .hero p{color:var(--ts);max-width:600px;margin:auto;}

        /* PAYMENT FORM */
        .pay-form{background:var(--card);border-radius:1.5rem;padding:2rem;box-shadow:var(--sh-md);border:1px solid var(--b);margin:2rem 0;}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;}
        .form-group label{display:block;font-weight:600;margin-bottom:.5rem;color:var(--t);}
        .form-group input,.form-group select{
            width:100%;padding:.75rem 1rem;border:1px solid var(--b);border-radius:.75rem;background:var(--bg);color:var(--t);font-size:1rem;transition:border .3s;
        }
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--p);}

        /* PROVIDER CARDS */
        .provider-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;margin:2rem 0;}
        .provider-card{
            background:var(--card);border-radius:1.25rem;padding:1.5rem;text-align:center;
            box-shadow:var(--sh-md);border:2px solid var(--b);transition:all .3s;position:relative;cursor:pointer;
        }
        .provider-card:hover{transform:translateY(-8px);box-shadow:var(--sh-lg);}
        .provider-airtel{border-color:var(--airtel);}
        .provider-mtn{border-color:var(--mtn);}
        .provider-zamtel{border-color:var(--zamtel);}
        .provider-icon{width:80px;height:80px;border-radius:50%;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:#fff;}
        .provider-airtel .provider-icon{background:var(--airtel);}
        .provider-mtn .provider-icon{background:var(--mtn);}
        .provider-zamtel .provider-icon{background:var(--zamtel);}
        .provider-name{font-weight:700;font-size:1.1rem;margin-bottom:.5rem;}
        .provider-phone{font-size:.9rem;color:var(--ts);margin-bottom:1rem;}
        .btn-pay{
            background:var(--p);color:#fff;padding:.75rem 1.5rem;border:none;border-radius:99px;
            font-weight:600;cursor:pointer;transition:all .3s;width:100%;
        }
        .btn-pay:hover{background:var(--ph);transform:translateY(-2px);}

        /* QR CODE */
        .qr-code{width:200px;height:200px;background:#f0f0f0;border-radius:12px;margin:1rem auto;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:var(--ts);border:2px dashed var(--b);}

        /* HISTORY */
        .table-container{overflow-x:auto;margin:2rem 0;}
        table{width:100%;border-collapse:collapse;background:var(--card);border-radius:1.25rem;overflow:hidden;box-shadow:var(--sh-md);}
        th{background:var(--glass);color:var(--t);padding:1rem;text-align:left;font-weight:700;}
        td{padding:1rem;border-top:1px solid var(--b);}
        tr:hover{background:var(--bg);}
        .status{padding:.25rem .75rem;border-radius:99px;font-size:.85rem;font-weight:600;}
        .COMPLETED{background:var(--s);color:#fff;}
        .PENDING{background:#f59e0b;color:#fff;}
        .FAILED{background:var(--e);color:#fff;}
        .amount{font-weight:700;color:var(--p);}

        /* TOAST */
        .toast{position:fixed;bottom:2rem;right:2rem;padding:1rem 1.5rem;border-radius:.75rem;color:#fff;font-weight:600;box-shadow:var(--sh-lg);z-index:2000;opacity:0;transform:translateY(20px);transition:all .3s;}
        .toast.show{opacity:1;transform:translateY(0);}
        .toast.success{background:var(--s);}
        .toast.error{background:var(--e);}

        /* FOOTER */
        .footer{padding:2rem 0;text-align:center;color:var(--ts);border-top:1px solid var(--b);}
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="container nav">
        <a href="dashboard.php" class="logo">UniLink</a>
        <div class="nav-right">
            <button onclick="toggleTheme()" class="theme-btn">
                <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707-.707M6.343 17.657l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
            <a href="dashboard.php" class="logout">Dashboard</a>
            <form action="logout.php" method="post" style="margin:0;"><button type="submit" class="logout">Logout</button></form>
        </div>
    </div>
</header>

<main class="container">

    <!-- HERO -->
    <section class="hero">
        <h1>Pay with <span>Mobile Money</span></h1>
        <p>Airtel Money • MTN MoMo • Zamtel Kwacha</p>
    </section>

    <!-- PAYMENT FORM -->
    <div class="pay-form">
        <h3 style="margin-bottom:1.5rem;font-size:1.5rem;font-weight:700;">Make Payment</h3>
        <div class="form-grid">
            <div class="form-group">
                <label>Amount (ZMW)</label>
                <input type="number" id="amount" min="1" step="0.01" value="50.00" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <select id="desc">
                    <option>Tuition Fee</option>
                    <option>Housing</option>
                    <option>Library Fine</option>
                    <option>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="phone" placeholder="0977123456" required>
            </div>
        </div>

        <!-- PROVIDERS -->
        <div class="provider-grid">
            <div class="provider-card provider-airtel" onclick="selectProvider('airtel')">
                <div class="provider-icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="provider-name">Airtel Money</div>
                <div class="provider-phone"> </div>
                <button class="btn-pay">Pay with Airtel</button>
            </div>
            <div class="provider-card provider-mtn" onclick="selectProvider('mtn')">
                <div class="provider-icon"><i class="fas fa-sim-card"></i></div>
                <div class="provider-name">MTN MoMo</div>
                <div class="provider-phone"> </div>
                <button class="btn-pay">Pay with MTN</button>
            </div>
            <div class="provider-card provider-zamtel" onclick="selectProvider('zamtel')">
                <div class="provider-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="provider-name">Zamtel Kwacha</div>
                <div class="provider-phone"> </div>
                <button class="btn-pay">Pay with Zamtel</button>
            </div>
        </div>

        <!-- QR CODE (SIMULATED) -->
        <div id="qrSection" style="display:none;text-align:center;">
            <p>Scan with your mobile money app</p>
            <div class="qr-code">SCAN ME</div>
            <p id="qrInfo"></p>
        </div>
    </div>

    <!-- HISTORY -->
    <div class="table-container">
        <h3 style="margin:1.5rem 0 .5rem;font-size:1.5rem;font-weight:700;">Payment History</h3>
        <?php if (empty($payments)): ?>
            <p style="text-align:center;color:var(--ts);padding:2rem;">No payments yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Provider</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
                            <td><?= ucfirst($p['provider']) ?></td>
                            <td><?= htmlspecialchars($p['description']) ?></td>
                            <td class="amount">K<?= number_format($p['amount'], 2) ?></td>
                            <td><span class="status <?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

<!-- TOAST -->
<div id="toast" class="toast"></div>

<footer class="footer">
    <div class="container">© 2025 UniLink | Zambian Mobile Money</div>
</footer>

<script>
    // THEME
    const html = document.documentElement;
    const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');
    if (theme==='dark') html.classList.add('dark');
    function toggleTheme(){
        html.classList.toggle('dark');
        localStorage.setItem('theme',html.classList.contains('dark')?'dark':'light');
    }

    // SELECT PROVIDER
    function selectProvider(provider) {
        const amount = document.getElementById('amount').value;
        const desc = document.getElementById('desc').value;
        const phone = document.getElementById('phone').value;

        if (!amount || !phone) {
            showToast('error', 'Fill amount and phone');
            return;
        }

        // Show QR
        document.getElementById('qrSection').style.display = 'block';
        document.getElementById('qrInfo').innerHTML = `
            <strong>${provider.toUpperCase()} PAYMENT</strong><br>
            Amount: K${amount}<br>
            Phone: ${phone}<br>
            Ref: TXN${Date.now().toString().slice(-6)}
        `;

        // Simulate payment
        setTimeout(() => {
            const form = new FormData();
            form.append('action', 'pay');
            form.append('amount', amount);
            form.append('description', desc);
            form.append('provider', provider);
            form.append('phone', phone);

            fetch('', {
                method: 'POST',
                body: form
            }).then(() => {
                location.reload();
            });
        }, 2000);
    }

    // TOAST
    function showToast(type, msg){
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + type + ' show';
        setTimeout(() => t.classList.remove('show'), 4000);
    }

    // URL MESSAGES
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] === 'COMPLETED'): ?>
            showToast('success', 'Payment received! Ref: <?= $_GET['ref'] ?>');
        <?php else: ?>
            showToast('error', 'Payment failed. Try again.');
        <?php endif; ?>
    <?php endif; ?>
</script>

</body>
</html>