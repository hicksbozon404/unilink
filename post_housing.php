<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image uploads
        $uploadedImages = [];
        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = 'uploads/housing/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = uniqid('housing_', true) . '_' . basename($_FILES['images']['name'][$key]);
                    $filePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($tmpName, $filePath)) {
                        $uploadedImages[] = $filePath;
                    }
                }
            }
        }
        
        // Get amenities as array
        $amenities = [];
        if (isset($_POST['amenities'])) {
            $amenities = is_array($_POST['amenities']) ? $_POST['amenities'] : [$_POST['amenities']];
        }
        
        // Insert listing
        $stmt = $pdo->prepare("
            INSERT INTO housing_listings (
                owner_id, title, description, price, location, map_url, 
                total_spaces, available_spaces, rooms_count, beds_per_room,
                gender_preference, is_self_contained, power_source, images, amenities
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $_POST['title'],
            $_POST['description'],
            $_POST['price'],
            $_POST['location'],
            $_POST['map_url'],
            $_POST['total_spaces'],
            $_POST['available_spaces'],
            $_POST['rooms_count'],
            $_POST['beds_per_room'],
            $_POST['gender_preference'],
            isset($_POST['is_self_contained']) ? 1 : 0,
            $_POST['power_source'],
            json_encode($uploadedImages),
            json_encode($amenities)
        ]);
        
        $message = '🎉 Listing posted successfully! Your property is now live.';
        $messageType = 'success';
        
        // Reset form
        $_POST = [];
        
    } catch (Exception $e) {
        $message = '❌ Error posting listing: ' . $e->getMessage();
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | List Your Property</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary: #06b6d4;
            --primary-hover: #0e7490;
            --primary-light: #ecfeff;
            --primary-glow: rgba(6, 182, 212, 0.15);
            
            --secondary: #8b5cf6;
            --secondary-light: #f5f3ff;
            
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            
            --text-main: #1e293b;
            --text-subtle: #64748b;
            --text-light: #94a3b8;
            
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            
            --shadow-xs: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            
            --radius-sm: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-2xl: 2rem;
            
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        :root.dark {
            --primary: #41e1ff;
            --primary-hover: #06b6d4;
            --primary-light: #083344;
            --primary-glow: rgba(65, 225, 255, 0.15);
            
            --secondary: #a78bfa;
            --secondary-light: #1e1b2e;
            
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            
            --text-main: #f1f5f9;
            --text-subtle: #94a3b8;
            --text-light: #64748b;
            
            --border: #334155;
            --border-light: #1e293b;
        }

        * {
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
            line-height: 1.6;
            transition: var(--transition);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Modern Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        :root.dark .header {
            background: rgba(30, 41, 59, 0.8);
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .nav-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .nav-btn:hover::before {
            left: 100%;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .nav-btn.secondary {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        .nav-btn.secondary:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        /* Hero Section */
        .hero {
            padding: 4rem 0 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
        }

        .hero-content {
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--primary-glow);
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--text-main), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            color: var(--text-subtle);
            margin-bottom: 2rem;
        }

        /* Modern Form */
        .form-container {
            background: var(--bg-card);
            border-radius: var(--radius-2xl);
            padding: 3rem;
            margin: 2rem 0 4rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
            background-size: 200% 100%;
            animation: shimmer 3s ease infinite;
        }

        @keyframes shimmer {
            0%, 100% { background-position: -200% 0; }
            50% { background-position: 200% 0; }
        }

        .form-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .form-subtitle {
            color: var(--text-subtle);
            font-size: 1.125rem;
        }

        /* Form Steps */
        .form-steps {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 3rem;
            position: relative;
        }

        .form-steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 50%;
            right: 50%;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            z-index: 2;
        }

        .step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--bg-body);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--text-light);
            transition: var(--transition);
        }

        .step.active .step-circle {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .step.completed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-light);
            transition: var(--transition);
        }

        .step.active .step-label {
            color: var(--primary);
        }

        .step.completed .step-label {
            color: var(--success);
        }

        /* Form Sections */
        .form-section {
            display: none;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* Modern Form Groups */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            position: relative;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label .required {
            color: var(--error);
            margin-left: 2px;
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
            transform: translateY(-1px);
        }

        .form-input:hover, .form-select:hover, .form-textarea:hover {
            border-color: var(--text-light);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
        }

        /* Modern Checkbox & Radio */
        .checkbox-group, .radio-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--bg-body);
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
        }

        .checkbox-group:hover, .radio-group:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .checkbox-group input, .radio-group input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .checkbox-label, .radio-label {
            font-weight: 500;
            color: var(--text-main);
        }

        /* Image Upload */
        .image-upload {
            border: 2px dashed var(--border);
            border-radius: var(--radius-xl);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            background: var(--bg-body);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .image-upload:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .image-upload.dragover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .upload-text {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }

        .upload-subtext {
            color: var(--text-subtle);
            font-size: 0.875rem;
        }

        .image-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .preview-item {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            aspect-ratio: 1;
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }

        .preview-item:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--error);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
            transition: var(--transition);
            opacity: 0;
        }

        .preview-item:hover .preview-remove {
            opacity: 1;
        }

        .preview-remove:hover {
            transform: scale(1.1);
        }

        /* Amenities Grid */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .amenity-card {
            background: var(--bg-body);
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .amenity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            opacity: 0;
            transition: var(--transition);
        }

        .amenity-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }

        .amenity-card.selected {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .amenity-card.selected::before {
            opacity: 0.05;
        }

        .amenity-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
            transition: var(--transition);
        }

        .amenity-card.selected .amenity-icon {
            color: var(--primary);
        }

        .amenity-name {
            font-weight: 600;
            color: var(--text-main);
            transition: var(--transition);
        }

        .amenity-card input {
            position: absolute;
            opacity: 0;
        }

        /* Form Navigation */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
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
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Alert */
        .alert {
            padding: 1.25rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideInDown 0.5s ease;
            border-left: 4px solid;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: var(--primary-light);
            border-left-color: var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--error);
            color: var(--error);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Footer */
        .footer {
            padding: 3rem 0;
            text-align: center;
            color: var(--text-subtle);
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .form-container {
                padding: 2rem 1.5rem;
                margin: 1rem 0 2rem;
                border-radius: var(--radius-xl);
            }
            
            .form-steps {
                gap: 1rem;
            }
            
            .step-label {
                font-size: 0.75rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .amenities-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hero {
                padding: 2rem 0 1rem;
            }
            
            .nav-btn {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
            
            .nav-btn span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .amenities-grid {
                grid-template-columns: 1fr;
            }
            
            .form-steps::before {
                display: none;
            }
            
            .form-steps {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .step {
                flex-direction: row;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="nav-content">
                <a href="dashboard.php" class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    UniLink
                </a>
                <div class="nav-actions">
                    <a href="housing.php" class="nav-btn secondary">
                        <i class="fas fa-search"></i>
                        <span>Browse Listings</span>
                    </a>
                    <a href="dashboard.php" class="nav-btn">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fas fa-rocket"></i>
                    List Your Property
                </div>
                <h1 class="hero-title">Host Students, Create Community</h1>
                <p class="hero-subtitle">Fill in your property details and start welcoming students in minutes</p>
            </div>
        </div>
    </section>

    <!-- Main Form -->
    <section class="container">
        <div class="form-container">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'error' ?>">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Form Steps -->
            <div class="form-steps">
                <div class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Basic Info</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Details</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Amenities</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-circle">4</div>
                    <div class="step-label">Media</div>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="housingForm">
                <!-- Step 1: Basic Information -->
                <div class="form-section active" data-section="1">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Basic Information
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Property Title <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="text" 
                                    name="title" 
                                    class="form-input" 
                                    placeholder="Modern Student Apartment near Campus" 
                                    value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-building form-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Price per Bed Space (K) <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="number" 
                                    name="price" 
                                    class="form-input" 
                                    placeholder="500" 
                                    step="0.01" 
                                    min="0" 
                                    value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-money-bill-wave form-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Description <span class="required">*</span>
                        </label>
                        <textarea 
                            name="description" 
                            class="form-textarea" 
                            placeholder="Describe your property, nearby facilities, rules, and what makes it special for students..."
                            required
                        ><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-navigation">
                        <div></div>
                        <button type="button" class="btn btn-primary next-step" data-next="2">
                            Next Step
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Property Details -->
                <div class="form-section" data-section="2">
                    <h2 class="section-title">
                        <i class="fas fa-home"></i>
                        Property Details
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Total Bed Spaces <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="number" 
                                    name="total_spaces" 
                                    class="form-input" 
                                    placeholder="10" 
                                    min="1" 
                                    value="<?= htmlspecialchars($_POST['total_spaces'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-bed form-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Available Spaces <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="number" 
                                    name="available_spaces" 
                                    class="form-input" 
                                    placeholder="5" 
                                    min="0" 
                                    value="<?= htmlspecialchars($_POST['available_spaces'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-door-open form-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Number of Rooms <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="number" 
                                    name="rooms_count" 
                                    class="form-input" 
                                    placeholder="3" 
                                    min="1" 
                                    value="<?= htmlspecialchars($_POST['rooms_count'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-door-closed form-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Beds per Room <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="number" 
                                    name="beds_per_room" 
                                    class="form-input" 
                                    placeholder="2" 
                                    min="1" 
                                    value="<?= htmlspecialchars($_POST['beds_per_room'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-bed form-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Gender Preference <span class="required">*</span>
                            </label>
                            <select name="gender_preference" class="form-select" required>
                                <option value="">Select preference...</option>
                                <option value="male" <?= ($_POST['gender_preference'] ?? '') === 'male' ? 'selected' : '' ?>>Male Only</option>
                                <option value="female" <?= ($_POST['gender_preference'] ?? '') === 'female' ? 'selected' : '' ?>>Female Only</option>
                                <option value="mixed" <?= ($_POST['gender_preference'] ?? '') === 'mixed' ? 'selected' : '' ?>>Mixed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Power Source <span class="required">*</span>
                            </label>
                            <select name="power_source" class="form-select" required>
                                <option value="">Select power source...</option>
                                <option value="zesco" <?= ($_POST['power_source'] ?? '') === 'zesco' ? 'selected' : '' ?>>ZESCO</option>
                                <option value="solar" <?= ($_POST['power_source'] ?? '') === 'solar' ? 'selected' : '' ?>>Solar</option>
                                <option value="both" <?= ($_POST['power_source'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_self_contained" id="is_self_contained" value="1" <?= isset($_POST['is_self_contained']) ? 'checked' : '' ?>>
                            <label class="checkbox-label" for="is_self_contained">
                                Self Contained Rooms (Private bathroom included)
                            </label>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary prev-step" data-prev="1">
                            <i class="fas fa-arrow-left"></i>
                            Previous
                        </button>
                        <button type="button" class="btn btn-primary next-step" data-next="3">
                            Next Step
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Amenities -->
                <div class="form-section" data-section="3">
                    <h2 class="section-title">
                        <i class="fas fa-star"></i>
                        Amenities & Features
                    </h2>
                    
                    <p style="color: var(--text-subtle); margin-bottom: 2rem;">
                        Select all amenities available at your property. This helps students find the perfect match.
                    </p>

                    <div class="amenities-grid">
                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="WiFi">
                            <div class="amenity-icon">
                                <i class="fas fa-wifi"></i>
                            </div>
                            <div class="amenity-name">WiFi</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Furniture">
                            <div class="amenity-icon">
                                <i class="fas fa-couch"></i>
                            </div>
                            <div class="amenity-name">Furniture</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Gas Stove">
                            <div class="amenity-icon">
                                <i class="fas fa-fire"></i>
                            </div>
                            <div class="amenity-name">Gas Stove</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Electric Stove">
                            <div class="amenity-icon">
                                <i class="fas fa-plug"></i>
                            </div>
                            <div class="amenity-name">Electric Stove</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Fridge">
                            <div class="amenity-icon">
                                <i class="fas fa-snowflake"></i>
                            </div>
                            <div class="amenity-name">Refrigerator</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Washing Machine">
                            <div class="amenity-icon">
                                <i class="fas fa-soap"></i>
                            </div>
                            <div class="amenity-name">Washing Machine</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Security">
                            <div class="amenity-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="amenity-name">Security</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Running Water">
                            <div class="amenity-icon">
                                <i class="fas fa-faucet"></i>
                            </div>
                            <div class="amenity-name">Running Water</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Study Area">
                            <div class="amenity-icon">
                                <i class="fas fa-desktop"></i>
                            </div>
                            <div class="amenity-name">Study Area</div>
                        </label>

                        <label class="amenity-card">
                            <input type="checkbox" name="amenities[]" value="Parking">
                            <div class="amenity-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="amenity-name">Parking</div>
                        </label>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary prev-step" data-prev="2">
                            <i class="fas fa-arrow-left"></i>
                            Previous
                        </button>
                        <button type="button" class="btn btn-primary next-step" data-next="4">
                            Next Step
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Media & Location -->
                <div class="form-section" data-section="4">
                    <h2 class="section-title">
                        <i class="fas fa-map-marked-alt"></i>
                        Location & Media
                    </h2>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">
                                Location <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="text" 
                                    name="location" 
                                    class="form-input" 
                                    placeholder="123 University Road, Lusaka" 
                                    value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-map-marker-alt form-icon"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Google Maps URL <span class="required">*</span>
                            </label>
                            <div class="form-input-wrapper">
                                <input 
                                    type="url" 
                                    name="map_url" 
                                    class="form-input" 
                                    placeholder="https://maps.google.com/?q=123+University+Road" 
                                    value="<?= htmlspecialchars($_POST['map_url'] ?? '') ?>" 
                                    required
                                >
                                <i class="fas fa-link form-icon"></i>
                            </div>
                            <small style="color: var(--text-subtle); margin-top: 0.5rem; display: block;">
                                Paste the Google Maps link for your property location
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Property Images</label>
                        <div class="image-upload" id="imageUpload">
                            <input type="file" name="images[]" id="images" multiple accept="image/*" style="display: none;">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">Click to upload or drag and drop</div>
                            <div class="upload-subtext">PNG, JPG, GIF up to 10MB (Max 5 images)</div>
                        </div>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary prev-step" data-prev="3">
                            <i class="fas fa-arrow-left"></i>
                            Previous
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Publish Listing
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            © 2025 UniLink | HICKS BOZON404. All Rights Reserved.
        </div>
    </footer>

    <script>
        // Multi-step form functionality
        class MultiStepForm {
            constructor() {
                this.currentStep = 1;
                this.totalSteps = 4;
                this.init();
            }

            init() {
                this.bindEvents();
                this.updateProgress();
            }

            bindEvents() {
                // Next step buttons
                document.querySelectorAll('.next-step').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const nextStep = parseInt(e.target.dataset.next);
                        if (this.validateStep(this.currentStep)) {
                            this.goToStep(nextStep);
                        }
                    });
                });

                // Previous step buttons
                document.querySelectorAll('.prev-step').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const prevStep = parseInt(e.target.dataset.prev);
                        this.goToStep(prevStep);
                    });
                });

                // Image upload
                this.initImageUpload();
                
                // Amenity cards
                this.initAmenityCards();
            }

            goToStep(step) {
                // Hide current step
                document.querySelector(`[data-section="${this.currentStep}"]`).classList.remove('active');
                document.querySelector(`[data-step="${this.currentStep}"]`).classList.remove('active');

                // Show new step
                document.querySelector(`[data-section="${step}"]`).classList.add('active');
                document.querySelector(`[data-step="${step}"]`).classList.add('active');

                // Update progress
                for (let i = 1; i < step; i++) {
                    document.querySelector(`[data-step="${i}"]`).classList.add('completed');
                }

                this.currentStep = step;
                this.updateProgress();
            }

            validateStep(step) {
                const section = document.querySelector(`[data-section="${step}"]`);
                const inputs = section.querySelectorAll('input[required], select[required], textarea[required]');
                
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        isValid = false;
                        this.showError(input, 'This field is required');
                    } else {
                        this.clearError(input);
                    }
                });

                // Specific validations
                if (step === 2) {
                    const availableSpaces = parseInt(document.querySelector('input[name="available_spaces"]').value);
                    const totalSpaces = parseInt(document.querySelector('input[name="total_spaces"]').value);
                    
                    if (availableSpaces > totalSpaces) {
                        isValid = false;
                        this.showError(document.querySelector('input[name="available_spaces"]'), 'Available spaces cannot exceed total spaces');
                    }
                }

                return isValid;
            }

            showError(input, message) {
                this.clearError(input);
                input.style.borderColor = 'var(--error)';
                
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = 'var(--error)';
                errorDiv.style.fontSize = '0.875rem';
                errorDiv.style.marginTop = '0.5rem';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
                
                input.parentNode.parentNode.appendChild(errorDiv);
            }

            clearError(input) {
                input.style.borderColor = '';
                const errorMsg = input.parentNode.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }

            updateProgress() {
                // Update header based on current step
                const steps = {
                    1: 'Basic Information',
                    2: 'Property Details',
                    3: 'Amenities & Features',
                    4: 'Location & Media'
                };
                
                // You could update a progress bar or header here
            }

            initImageUpload() {
                const uploadArea = document.getElementById('imageUpload');
                const fileInput = document.getElementById('images');
                const preview = document.getElementById('imagePreview');

                uploadArea.addEventListener('click', () => fileInput.click());

                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });

                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });

                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    fileInput.files = e.dataTransfer.files;
                    this.handleImagePreview();
                });

                fileInput.addEventListener('change', () => this.handleImagePreview());
            }

            handleImagePreview() {
                const fileInput = document.getElementById('images');
                const preview = document.getElementById('imagePreview');
                preview.innerHTML = '';

                const files = Array.from(fileInput.files).slice(0, 5);

                files.forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const previewItem = document.createElement('div');
                            previewItem.className = 'preview-item';
                            previewItem.innerHTML = `
                                <img src="${e.target.result}" alt="Preview ${index + 1}">
                                <button type="button" class="preview-remove" data-index="${index}">
                                    <i class="fas fa-times"></i>
                                </button>
                            `;
                            preview.appendChild(previewItem);

                            // Add remove functionality
                            previewItem.querySelector('.preview-remove').addEventListener('click', (e) => {
                                e.stopPropagation();
                                this.removeImage(index);
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });

                // Update file input
                const dt = new DataTransfer();
                files.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
            }

            removeImage(index) {
                const fileInput = document.getElementById('images');
                const files = Array.from(fileInput.files);
                files.splice(index, 1);

                const dt = new DataTransfer();
                files.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;

                this.handleImagePreview();
            }

            initAmenityCards() {
                document.querySelectorAll('.amenity-card').forEach(card => {
                    card.addEventListener('click', () => {
                        const checkbox = card.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                        card.classList.toggle('selected', checkbox.checked);
                    });
                });
            }
        }

        // Initialize the form when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            new MultiStepForm();
        });

        // Add some interactive effects
        document.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Add character counter for description
        const descriptionTextarea = document.querySelector('textarea[name="description"]');
        if (descriptionTextarea) {
            const counter = document.createElement('div');
            counter.style.textAlign = 'right';
            counter.style.color = 'var(--text-light)';
            counter.style.fontSize = '0.875rem';
            counter.style.marginTop = '0.5rem';
            descriptionTextarea.parentNode.appendChild(counter);

            descriptionTextarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/1000 characters`;
                
                if (length > 900) {
                    counter.style.color = 'var(--warning)';
                } else if (length > 1000) {
                    counter.style.color = 'var(--error)';
                } else {
                    counter.style.color = 'var(--text-light)';
                }
            });
        }
    </script>
</body>
</html>