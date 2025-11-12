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

// ---------- CREATE GRADES TABLE (RUN ONCE) ----------
$pdo->exec("CREATE TABLE IF NOT EXISTS grades (
    grade_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    course_code VARCHAR(20),
    course_name VARCHAR(100),
    credits INT,
    grade VARCHAR(2),
    semester VARCHAR(20),
    year YEAR,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)");

// ---------- ADD/EDIT GRADE ----------
if ($_POST['action'] ?? '' === 'save') {
    $stmt = $pdo->prepare("INSERT INTO grades (user_id,course_code,course_name,credits,grade,semester,year) 
        VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE 
        course_name=VALUES(course_name),credits=VALUES(credits),grade=VALUES(grade)");
    $stmt->execute([
        $userId,
        $_POST['code'],
        $_POST['name'],
        $_POST['credits'],
        $_POST['grade'],
        $_POST['semester'],
        $_POST['year']
    ]);
    header('Location: grades.php');
    exit;
}

// ---------- DELETE ----------
if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM grades WHERE grade_id=? AND user_id=?")->execute([$_GET['del'], $userId]);
    header('Location: grades.php');
    exit;
}

// ---------- FETCH GRADES ----------
$grades = $pdo->prepare("SELECT * FROM grades WHERE user_id=? ORDER BY year DESC, semester DESC");
$grades->execute([$userId]);
$allGrades = $grades->fetchAll();

// ---------- CALCULATE GPA ----------
$totalPoints = $totalCredits = 0;
$gradeMap = ['A'=>4.0,'A-'=>3.7,'B+'=>3.3,'B'=>3.0,'B-'=>2.7,'C+'=>2.3,'C'=>2.0,'C-'=>1.7,'D+'=>1.3,'D'=>1.0,'F'=>0];
foreach ($allGrades as $g) {
    if (isset($gradeMap[$g['grade']])) {
        $totalPoints += $gradeMap[$g['grade']] * $g['credits'];
        $totalCredits += $g['credits'];
    }
}
$gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | Grades</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --p:#06b6d4;--ph:#0e7490;--pg:#06b6d433;
            --bg:#f8fafc;--card:#fff;--glass:rgba(255,255,255,.15);
            --t:#1e293b;--ts:#64748b;--b:#e2e8f0;--s:#10b981;--e:#ef4444;
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
        .container{max-width:1280px;margin:auto;padding:0 1.5rem;}

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

        /* GPA RING */
        .gpa-container{display:flex;justify-content:center;margin:3rem 0;}
        .gpa-ring{width:220px;height:220px;position:relative;}
        .gpa-ring svg{width:100%;height:100%;transform:rotate(-90deg);}
        .gpa-bg{fill:none;stroke:var(--b);stroke-width:16;}
        .gpa-fill{fill:none;stroke:var(--p);stroke-width:16;stroke-linecap:round;transition:stroke-dashoffset .8s ease;}
        .gpa-text{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .gpa-value{font-size:2.8rem;font-weight:800;color:var(--p);}
        .gpa-label{font-size:1rem;color:var(--ts);margin-top:.25rem;}

        /* ADD GRADE FORM */
        .add-form{background:var(--card);border-radius:1.5rem;padding:2rem;box-shadow:var(--sh-md);border:1px solid var(--b);margin:2rem 0;}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;}
        .form-group label{display:block;font-weight:600;margin-bottom:.5rem;color:var(--t);}
        .form-group input,.form-group select{
            width:100%;padding:.75rem;border:1px solid var(--b);border-radius:.75rem;background:var(--bg);color:var(--t);
            font-size:1rem;transition:border .3s;
        }
        .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--p);}
        .btn-add{background:var(--p);color:#fff;padding:.75rem 1.5rem;border:none;border-radius:.75rem;font-weight:600;cursor:pointer;transition:all .3s;}
        .btn-add:hover{background:var(--ph);transform:translateY(-2px);}

        /* COURSE TABLE */
        .table-container{overflow-x:auto;margin:2rem 0;}
        table{width:100%;border-collapse:collapse;background:var(--card);border-radius:1.25rem;overflow:hidden;box-shadow:var(--sh-md);}
        th{background:var(--glass);color:var(--t);padding:1rem;text-align:left;font-weight:700;}
        td{padding:1rem;border-top:1px solid var(--b);}
        tr:hover{background:var(--bg);}
        .grade-badge{padding:.25rem .5rem;border-radius:99px;font-size:.85rem;font-weight:600;}
        .A{background:#10b981;color:#fff;}
        .B{background:#f59e0b;color:#fff;}
        .C{background:#f97316;color:#fff;}
        .D,.F{background:#ef4444;color:#fff;}
        .actions{display:flex;gap:.5rem;}
        .btn-small{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;transition:all .2s;}
        .btn-edit{background:var(--s);color:#fff;}
        .btn-del{background:var(--e);color:#fff;}
        .btn-small:hover{transform:scale(1.1);}

        /* EXPORT */
        .export-btn{background:var(--s);color:#fff;padding:.75rem 1.5rem;border:none;border-radius:.75rem;font-weight:600;cursor:pointer;margin:1rem 0;display:inline-flex;align-items:center;gap:.5rem;}
        .export-btn:hover{background:#0d8b63;}

        /* EMPTY */
        .empty{text-align:center;padding:3rem;color:var(--ts);}
        .empty i{font-size:3rem;margin-bottom:1rem;display:block;opacity:.6;}

        /* MODAL */
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(8px);align-items:center;justify-content:center;z-index:2000;padding:1rem;}
        .modal.active{display:flex;}
        .modal-box{background:var(--card);border-radius:1.5rem;padding:2rem;max-width:500px;width:100%;box-shadow:var(--sh-lg);border:1px solid var(--b);}
        .modal-box h3{margin-bottom:1rem;text-align:center;}
        .modal-actions{display:flex;gap:1rem;margin-top:1.5rem;}
        .modal-actions button{flex:1;padding:.75rem;border-radius:.75rem;border:none;font-weight:600;cursor:pointer;}
        .modal-actions .cancel{background:var(--ts);color:#fff;}
        .modal-actions .confirm{background:var(--e);color:#fff;}

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
        <h1>Academic <span>Progress</span></h1>
        <p>Track your grades, GPA, and download transcripts.</p>
    </section>

    <!-- GPA RING -->
    <div class="gpa-container">
        <div class="gpa-ring">
            <svg>
                <circle class="gpa-bg" cx="110" cy="110" r="100"></circle>
                <circle class="gpa-fill" cx="110" cy="110" r="100" stroke-dasharray="628.3" stroke-dashoffset="<?= 628.3 - (628.3 * $gpa / 4) ?>"></circle>
            </svg>
            <div class="gpa-text">
                <div class="gpa-value"><?= $gpa ?></div>
                <div class="gpa-label">GPA / 4.0</div>
            </div>
        </div>
    </div>

    <!-- ADD GRADE -->
    <div class="add-form">
        <h3 style="margin-bottom:1rem;">Add / Edit Course</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="action" value="save">
            <div class="form-group">
                <label>Course Code</label>
                <input type="text" name="code" required placeholder="e.g. CS101">
            </div>
            <div class="form-group">
                <label>Course Name</label>
                <input type="text" name="name" required placeholder="e.g. Web Development">
            </div>
            <div class="form-group">
                <label>Credits</label>
                <input type="number" name="credits" min="1" max="6" required value="3">
            </div>
            <div class="form-group">
                <label>Grade</label>
                <select name="grade" required>
                    <option value="A">A</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B">B</option>
                    <option value="B-">B-</option>
                    <option value="C+">C+</option>
                    <option value="C">C</option>
                    <option value="C-">C-</option>
                    <option value="D+">D+</option>
                    <option value="D">D</option>
                    <option value="F">F</option>
                </select>
            </div>
            <div class="form-group">
                <label>Semester</label>
                <select name="semester" required>
                    <option>Fall</option>
                    <option>Spring</option>
                    <option>Summer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Year</label>
                <input type="number" name="year" min="2000" max="2030" required value="<?= date('Y') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1;display:flex;align-items:end;">
                <button type="submit" class="btn-add">Save Grade</button>
            </div>
        </form>
    </div>

    <!-- COURSE TABLE -->
    <div class="table-container">
        <?php if (empty($allGrades)): ?>
            <div class="empty">
                <i class="fas fa-book-open"></i>
                <p>No grades yet. Add your first course!</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Course</th>
                        <th>Credits</th>
                        <th>Grade</th>
                        <th>Semester</th>
                        <th>Year</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allGrades as $g): ?>
                        <tr>
                            <td><?= htmlspecialchars($g['course_code']) ?></td>
                            <td><?= htmlspecialchars($g['course_name']) ?></td>
                            <td><?= $g['credits'] ?></td>
                            <td><span class="grade-badge <?= $g['grade'][0] ?>"><?= $g['grade'] ?></span></td>
                            <td><?= $g['semester'] ?></td>
                            <td><?= $g['year'] ?></td>
                            <td class="actions">
                                <a href="?del=<?= $g['grade_id'] ?>" class="btn-small btn-del" onclick="return confirmDelete(<?= $g['grade_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- EXPORT -->
    <div style="text-align:center;">
        <button class="export-btn" onclick="exportPDF()">
            <i class="fas fa-file-pdf"></i> Export Transcript
        </button>
    </div>

</main>

<!-- DELETE MODAL -->
<div id="delModal" class="modal">
    <div class="modal-box">
        <h3>Delete Grade?</h3>
        <p>This cannot be undone.</p>
        <div class="modal-actions">
            <button class="cancel" onclick="closeDel()">Cancel</button>
            <button class="confirm" id="confirmDel">Delete</button>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container">© 2025 UniLink | HICKS BOZON404.</div>
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

    // DELETE
    let delId = 0;
    function confirmDelete(id){
        delId = id;
        document.getElementById('delModal').classList.add('active');
        return false;
    }
    function closeDel(){ document.getElementById('delModal').classList.remove('active'); }
    document.getElementById('confirmDel').onclick = ()=>{ location.href='?del='+delId; };

    // EXPORT PDF (using jsPDF)
    function exportPDF(){
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFont('helvetica');
        doc.setFontSize(20);
        doc.text('Academic Transcript', 105, 20, { align: 'center' });
        doc.setFontSize(12);
        doc.text(`Student: <?= $fullName ?>`, 20, 35);
        doc.text(`GPA: <?= $gpa ?> / 4.0`, 20, 45);

        let y = 60;
        doc.setFontSize(10);
        <?php foreach ($allGrades as $g): ?>
            doc.text('<?= htmlspecialchars($g['course_code']) ?> | <?= htmlspecialchars($g['course_name']) ?> | <?= $g['credits'] ?> cr | <?= $g['grade'] ?> | <?= $g['semester'] ?> <?= $g['year'] ?>', 20, y);
            y += 10;
        <?php endforeach; ?>

        doc.save('transcript_<?= time() ?>.pdf');
    }
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</body>
</html>