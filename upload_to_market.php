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

// Get categories
$categories = $pdo->query("SELECT * FROM marketplace_categories ORDER BY name")->fetchAll();

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = ['title', 'category_id', 'description', 'price', 'condition'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Validate price
        $price = floatval($_POST['price']);
        if ($price <= 0) {
            throw new Exception("Please enter a valid price.");
        }

        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/marketplace/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = mime_content_type($_FILES['item_image']['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes)) {
                throw new Exception("Please upload only JPG, PNG, or GIF images.");
            }

            // Validate file size (max 5MB)
            if ($_FILES['item_image']['size'] > 5 * 1024 * 1024) {
                throw new Exception("Image size must be less than 5MB.");
            }

            $fileExt = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid('item_', true) . '.' . $fileExt;
            $targetFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $targetFile)) {
                $imagePath = $targetFile;
            } else {
                throw new Exception("Failed to upload image. Please try again.");
            }
        }

        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO marketplace_items (user_id, category_id, title, description, price, image_path, item_condition) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $_POST['category_id'],
            trim($_POST['title']),
            trim($_POST['description']),
            $price,
            $imagePath,
            $_POST['condition']
        ]);

        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UniLink | List Item</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --p:#06b6d4;--ph:#0e7490;--pg:#06b6d433;
            --bg:#f8fafc;--card:#fff;--glass:rgba(255,255,255,.15);
            --t:#1e293b;--ts:#64748b;--b:#e2e8f0;--s:#10b981;--e:#ef4444;--w:#f59e0b;
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
        .container{max-width:800px;margin:auto;padding:0 1.5rem;}

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
        .hero{padding:3rem 0 2rem;text-align:center;}
        .hero h1{font-size:2.5rem;font-weight:900;margin-bottom:.5rem;}
        .hero h1 span{color:var(--p);}
        .hero p{color:var(--ts);max-width:600px;margin:auto;}

        /* UPLOAD FORM */
        .upload-form{
            background:var(--card);border-radius:1.5rem;padding:2.5rem;
            box-shadow:var(--sh-lg);border:1px solid var(--b);margin:2rem 0;
        }
        .form-section{margin-bottom:2.5rem;}
        .form-section:last-child{margin-bottom:0;}
        .section-title{
            font-size:1.25rem;font-weight:700;margin-bottom:1.5rem;padding-bottom:.75rem;
            border-bottom:2px solid var(--p);color:var(--p);display:flex;align-items:center;gap:.5rem;
        }
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
        @media(max-width:768px){.form-grid{grid-template-columns:1fr;}}
        .form-group-full{grid-column:1/-1;}
        .form-group label{display:block;font-weight:600;margin-bottom:.75rem;font-size:.95rem;}
        .form-group .required::after{content:'*';color:var(--e);margin-left:.25rem;}
        .form-group input,.form-group select,.form-group textarea{
            width:100%;padding:1rem;border:2px solid var(--b);border-radius:.75rem;
            background:var(--bg);color:var(--t);font-size:1rem;transition:all .3s;
        }
        .form-group input:focus,.form-group select:focus,.form-group textarea:focus{
            outline:none;border-color:var(--p);box-shadow:0 0 0 3px var(--pg);
        }
        .form-group textarea{min-height:120px;resize:vertical;font-family:inherit;}
        .form-help{color:var(--ts);font-size:.85rem;margin-top:.5rem;}

        /* IMAGE UPLOAD */
        .image-upload{
            border:2px dashed var(--b);border-radius:1rem;padding:2rem;text-align:center;
            transition:all .3s;cursor:pointer;position:relative;background:var(--bg);
        }
        .image-upload:hover{border-color:var(--p);background:var(--pg);}
        .image-upload.dragover{border-color:var(--p);background:var(--pg);}
        .image-upload input{position:absolute;inset:0;opacity:0;cursor:pointer;}
        .upload-icon{font-size:3rem;color:var(--p);margin-bottom:1rem;}
        .upload-text{font-weight:600;margin-bottom:.5rem;}
        .upload-subtext{color:var(--ts);font-size:.9rem;}
        .image-preview{display:none;margin-top:1rem;}
        .preview-image{max-width:100%;max-height:200px;border-radius:.5rem;box-shadow:var(--sh-sm);}

        /* PRICE INPUT */
        .price-input{position:relative;}
        .price-prefix{
            position:absolute;left:1rem;top:50%;transform:translateY(-50%);
            font-weight:600;color:var(--ts);
        }
        .price-input input{padding-left:3rem;}

        /* CONDITION CARDS */
        .condition-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;}
        .condition-card{
            border:2px solid var(--b);border-radius:1rem;padding:1.5rem;text-align:center;
            cursor:pointer;transition:all .3s;background:var(--bg);
        }
        .condition-card:hover{border-color:var(--p);}
        .condition-card.selected{border-color:var(--p);background:var(--pg);}
        .condition-card input{display:none;}
        .condition-icon{font-size:1.5rem;color:var(--p);margin-bottom:.5rem;}
        .condition-name{font-weight:600;margin-bottom:.25rem;}
        .condition-desc{color:var(--ts);font-size:.8rem;}

        /* SUBMIT BUTTON */
        .submit-section{text-align:center;margin-top:2rem;padding-top:2rem;border-top:1px solid var(--b);}
        .btn-submit{
            background:linear-gradient(135deg,var(--p),var(--ph));color:#fff;
            padding:1rem 3rem;border:none;border-radius:.75rem;font-size:1.1rem;
            font-weight:700;cursor:pointer;transition:all .3s;box-shadow:var(--sh-md);
            display:inline-flex;align-items:center;gap:.75rem;
        }
        .btn-submit:hover{
            transform:translateY(-3px);box-shadow:var(--sh-lg);
        }
        .btn-submit:active{transform:translateY(-1px);}

        /* ALERTS */
        .alert{
            padding:1rem 1.5rem;border-radius:1rem;margin-bottom:2rem;font-weight:600;
            display:flex;align-items:center;gap:.75rem;
        }
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
        .dark .alert-success{background:#064e3b;color:#a7f3d0;border-color:#065f46;}
        .dark .alert-error{background:#7f1d1d;color:#fecaca;border-color:#991b1b;}

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
            <a href="market.php" class="logout">← Back to Market</a>
            <form action="logout.php" method="post" style="margin:0;"><button type="submit" class="logout">Logout</button></form>
        </div>
    </div>
</header>

<main class="container">

    <!-- HERO -->
    <section class="hero">
        <h1>List Your <span>Item</span></h1>
        <p>Reach thousands of students on campus. Sell your items quickly and safely.</p>
    </section>

    <!-- ALERTS -->
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>Success!</strong> Your item has been listed successfully.
                <br><small>You'll be redirected to the marketplace in a moment...</small>
            </div>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = 'market.php';
            }, 3000);
        </script>
    <?php elseif ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Error!</strong> <?= htmlspecialchars($error) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- UPLOAD FORM -->
    <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
        
        <!-- BASIC INFORMATION -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-info-circle"></i> Basic Information</h2>
            <div class="form-grid">
                <div class="form-group form-group-full">
                    <label class="required">Item Title</label>
                    <input type="text" name="title" required placeholder="e.g., Calculus Textbook 2024 Edition, MacBook Pro 2022, etc." value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    <div class="form-help">Be specific and descriptive to attract more buyers</div>
                </div>
                
                <div class="form-group">
                    <label class="required">Category</label>
                    <select name="category_id" required>
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="required">Price (K)</label>
                    <div class="price-input">
                        <span class="price-prefix">K</span>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="0.00" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- ITEM DESCRIPTION -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-align-left"></i> Description</h2>
            <div class="form-group form-group-full">
                <label class="required">Item Description</label>
                <textarea name="description" required placeholder="Describe your item in detail. Include brand, model, specifications, reason for selling, and any notable features or flaws..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <div class="form-help">Detailed descriptions receive 3x more responses</div>
            </div>
        </div>

        <!-- CONDITION -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Condition</h2>
            <div class="condition-grid" id="conditionGrid">
                <label class="condition-card" data-value="new">
                    <input type="radio" name="condition" value="new" <?= ($_POST['condition'] ?? '') == 'new' ? 'checked' : '' ?> required>
                    <div class="condition-icon"><i class="fas fa-tags"></i></div>
                    <div class="condition-name">New</div>
                    <div class="condition-desc">Never used, with tags</div>
                </label>
                
                <label class="condition-card" data-value="like_new">
                    <input type="radio" name="condition" value="like_new" <?= ($_POST['condition'] ?? '') == 'like_new' ? 'checked' : '' ?>>
                    <div class="condition-icon"><i class="fas fa-gem"></i></div>
                    <div class="condition-name">Like New</div>
                    <div class="condition-desc">Minimal signs of use</div>
                </label>
                
                <label class="condition-card" data-value="good">
                    <input type="radio" name="condition" value="good" <?= ($_POST['condition'] ?? 'good') == 'good' ? 'checked' : '' ?>>
                    <div class="condition-icon"><i class="fas fa-thumbs-up"></i></div>
                    <div class="condition-name">Good</div>
                    <div class="condition-desc">Normal wear, works perfectly</div>
                </label>
                
                <label class="condition-card" data-value="fair">
                    <input type="radio" name="condition" value="fair" <?= ($_POST['condition'] ?? '') == 'fair' ? 'checked' : '' ?>>
                    <div class="condition-icon"><i class="fas fa-tools"></i></div>
                    <div class="condition-name">Fair</div>
                    <div class="condition-desc">Visible wear, fully functional</div>
                </label>
            </div>
        </div>

        <!-- IMAGES -->
        <div class="form-section">
            <h2 class="section-title"><i class="fas fa-camera"></i> Photos</h2>
            <div class="form-group form-group-full">
                <label>Item Image</label>
                <div class="image-upload" id="imageUpload">
                    <input type="file" name="item_image" accept="image/*" id="fileInput">
                    <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <div class="upload-text">Click to upload or drag and drop</div>
                    <div class="upload-subtext">PNG, JPG, GIF up to 5MB</div>
                </div>
                <div class="image-preview" id="imagePreview">
                    <img src="" alt="Preview" class="preview-image" id="previewImage">
                </div>
                <div class="form-help">Clear photos help items sell 50% faster</div>
            </div>
        </div>

        <!-- SUBMIT -->
        <div class="submit-section">
            <button type="submit" class="btn-submit">
                <i class="fas fa-rocket"></i> List Item on Marketplace
            </button>
        </div>

    </form>

</main>

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

    // CONDITION CARDS
    document.querySelectorAll('.condition-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.condition-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input').checked = true;
        });
        
        // Set initial selected state
        if (card.querySelector('input').checked) {
            card.classList.add('selected');
        }
    });

    // IMAGE UPLOAD WITH PREVIEW
    const fileInput = document.getElementById('fileInput');
    const imageUpload = document.getElementById('imageUpload');
    const imagePreview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');

    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        imageUpload.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        imageUpload.addEventListener(eventName, () => imageUpload.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        imageUpload.addEventListener(eventName, () => imageUpload.classList.remove('dragover'), false);
    });

    imageUpload.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        handleFiles(files);
    }

    fileInput.addEventListener('change', function() {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        if (files.length > 0) {
            const file = files[0];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    imagePreview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
    }

    // FORM VALIDATION
    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        const price = parseFloat(this.querySelector('input[name="price"]').value);
        if (price <= 0) {
            e.preventDefault();
            alert('Please enter a valid price greater than 0.');
            return false;
        }
    });
</script>

</body>
</html>