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

// Handle search and filters
$search = $_GET['search'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$gender = $_GET['gender'] ?? '';
$powerSource = $_GET['power_source'] ?? '';
$selfContained = $_GET['self_contained'] ?? '';
$amenities = $_GET['amenities'] ?? [];

// Build query
$sql = "SELECT hl.*, u.full_names as owner_name, u.email as owner_email 
        FROM housing_listings hl 
        JOIN users u ON hl.owner_id = u.user_id 
        WHERE hl.available_spaces > 0";
$params = [];

if (!empty($search)) {
    $sql .= " AND (hl.title LIKE ? OR hl.description LIKE ? OR hl.location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($minPrice)) {
    $sql .= " AND hl.price >= ?";
    $params[] = $minPrice;
}

if (!empty($maxPrice)) {
    $sql .= " AND hl.price <= ?";
    $params[] = $maxPrice;
}

if (!empty($gender) && in_array($gender, ['male', 'female', 'mixed'])) {
    $sql .= " AND hl.gender_preference = ?";
    $params[] = $gender;
}

if (!empty($powerSource) && in_array($powerSource, ['zesco', 'solar', 'both'])) {
    $sql .= " AND hl.power_source = ?";
    $params[] = $powerSource;
}

if (!empty($selfContained)) {
    $sql .= " AND hl.is_self_contained = ?";
    $params[] = ($selfContained === 'yes') ? 1 : 0;
}

// Handle amenities filter
if (!empty($amenities) && is_array($amenities)) {
    foreach ($amenities as $amenity) {
        $sql .= " AND JSON_CONTAINS(hl.amenities, ?)";
        $params[] = json_encode($amenity);
    }
}

$sql .= " ORDER BY hl.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

// Get unique values for filter options
$amenitiesOptions = ['WiFi', 'Furniture', 'Gas Stove', 'Electric Stove', 'Fridge', 'Washing Machine', 'Security', 'Running Water', 'Study Area', 'Parking'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Find Student Housing</title>
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
        }

        .container {
            max-width: 1400px;
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
            padding: 6rem 0 4rem;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-light) 0%, transparent 50%);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
        }

        .hero-content {
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-xl);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 2rem;
            border: 1px solid var(--primary-glow);
            backdrop-filter: blur(10px);
        }

        .hero-title {
            font-size: clamp(3rem, 6vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--text-main), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-subtitle {
            font-size: 1.375rem;
            color: var(--text-subtle);
            margin-bottom: 3rem;
            line-height: 1.6;
        }

        /* Modern Search Bar */
        .search-hero {
            background: var(--bg-card);
            border-radius: var(--radius-2xl);
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1.25rem 1.5rem 1.25rem 3rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-xl);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 1.125rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.25rem;
        }

        .search-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            border: none;
            border-radius: var(--radius-xl);
            padding: 1.25rem 2rem;
            font-weight: 600;
            font-size: 1.125rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-md);
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Modern Filter System */
        .filter-system {
            margin: 3rem 0;
        }

        .filter-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .filter-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .filter-toggle {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-toggle:hover {
            border-color: var(--primary);
        }

        .filter-panel {
            background: var(--bg-card);
            border-radius: var(--radius-2xl);
            padding: 2.5rem;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border);
            margin-bottom: 2rem;
            display: none;
        }

        .filter-panel.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .filter-label {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input, .filter-select {
            padding: 1rem 1.25rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .price-inputs {
            display: flex;
            gap: 1rem;
        }

        .price-inputs .filter-input {
            flex: 1;
        }

        /* Modern Amenities Filter */
        .amenities-filter {
            margin-top: 1.5rem;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .amenity-filter-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: var(--bg-body);
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: var(--transition);
        }

        .amenity-filter-item:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .amenity-filter-item.selected {
            background: var(--primary-light);
            border-color: var(--primary);
            color: var(--primary);
        }

        .amenity-filter-item input {
            display: none;
        }

        .amenity-icon {
            font-size: 1.25rem;
            color: var(--text-light);
            transition: var(--transition);
        }

        .amenity-filter-item.selected .amenity-icon {
            color: var(--primary);
        }

        .amenity-name {
            font-weight: 500;
            color: var(--text-main);
            transition: var(--transition);
        }

        .amenity-filter-item.selected .amenity-name {
            color: var(--primary);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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

        /* Results Header */
        .results-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .results-count {
            font-size: 1.125rem;
            color: var(--text-subtle);
        }

        .results-count strong {
            color: var(--text-main);
        }

        .sort-select {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius-lg);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.875rem;
            cursor: pointer;
        }

        /* Modern Listings Grid */
        .listings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .listing-card {
            background: var(--bg-card);
            border-radius: var(--radius-2xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
        }

        .listing-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
            border-color: var(--primary);
        }

        .listing-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .listing-card:hover .listing-image {
            transform: scale(1.05);
        }

        .listing-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-xl);
            font-size: 0.875rem;
            font-weight: 700;
            box-shadow: var(--shadow-md);
        }

        .listing-content {
            padding: 2rem;
        }

        .listing-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .listing-title {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.3;
            flex: 1;
        }

        .listing-price {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--primary);
            white-space: nowrap;
        }

        .listing-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-subtle);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .listing-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-subtle);
        }

        .feature i {
            color: var(--primary);
            width: 16px;
            font-size: 1rem;
        }

        /* Landlord Profile */
        .landlord-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-body);
            border-radius: var(--radius-xl);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .landlord-profile:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
        }

        .landlord-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .landlord-info {
            flex: 1;
        }

        .landlord-name {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.25rem;
        }

        .landlord-verified {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--success);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .view-profile-btn {
            background: var(--bg-card);
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-lg);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            white-space: nowrap;
        }

        .view-profile-btn:hover {
            background: var(--primary);
            color: white;
        }

        .listing-actions {
            display: flex;
            gap: 0.75rem;
        }

        .action-btn {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .action-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .action-btn.secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 2px solid var(--border);
        }

        .action-btn.secondary:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 6rem 2rem;
            color: var(--text-subtle);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 2rem;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-main);
        }

        .empty-description {
            font-size: 1.125rem;
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer */
        .footer {
            padding: 4rem 0;
            text-align: center;
            color: var(--text-subtle);
            border-top: 1px solid var(--border);
            background: var(--bg-card);
            margin-top: 4rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-btn {
                width: 100%;
            }
            
            .listings-grid {
                grid-template-columns: 1fr;
            }
            
            .listing-features {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .amenities-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .listing-actions {
                flex-direction: column;
            }
            
            .nav-btn span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .amenities-grid {
                grid-template-columns: 1fr;
            }
            
            .hero {
                padding: 4rem 0 2rem;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .landlord-profile {
                flex-direction: column;
                text-align: center;
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
                    <a href="post_housing.php" class="nav-btn secondary">
                        <i class="fas fa-plus"></i>
                        <span>List Property</span>
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
                    <i class="fas fa-home"></i>
                    Find Your Perfect Student Home
                </div>
                <h1 class="hero-title">Discover Your Ideal Student Accommodation</h1>
                <p class="hero-subtitle">Browse through verified boarding houses with modern amenities, trusted landlords, and prime locations near campus.</p>
                
                <!-- Search Bar -->
                <div class="search-hero">
                    <form method="GET" class="search-form">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input 
                                type="text" 
                                name="search" 
                                class="search-input" 
                                placeholder="Search by location, property name, or landlord..." 
                                value="<?= htmlspecialchars($search) ?>"
                            >
                        </div>
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i>
                            Search
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="container">
        <!-- Filter System -->
        <div class="filter-system">
            <div class="filter-header">
                <h2 class="filter-title">Refine Your Search</h2>
                <button class="filter-toggle" id="filterToggle">
                    <i class="fas fa-sliders-h"></i>
                    Show Filters
                </button>
            </div>

            <div class="filter-panel" id="filterPanel">
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Price Range (K)</label>
                            <div class="price-inputs">
                                <input 
                                    type="number" 
                                    name="min_price" 
                                    class="filter-input" 
                                    placeholder="Min" 
                                    value="<?= htmlspecialchars($minPrice) ?>"
                                >
                                <input 
                                    type="number" 
                                    name="max_price" 
                                    class="filter-input" 
                                    placeholder="Max" 
                                    value="<?= htmlspecialchars($maxPrice) ?>"
                                >
                            </div>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Gender Preference</label>
                            <select name="gender" class="filter-select">
                                <option value="">Any Gender</option>
                                <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>Male Only</option>
                                <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>Female Only</option>
                                <option value="mixed" <?= $gender === 'mixed' ? 'selected' : '' ?>>Mixed</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Power Source</label>
                            <select name="power_source" class="filter-select">
                                <option value="">Any Power Source</option>
                                <option value="zesco" <?= $powerSource === 'zesco' ? 'selected' : '' ?>>ZESCO</option>
                                <option value="solar" <?= $powerSource === 'solar' ? 'selected' : '' ?>>Solar</option>
                                <option value="both" <?= $powerSource === 'both' ? 'selected' : '' ?>>Both</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Bathroom Type</label>
                            <select name="self_contained" class="filter-select">
                                <option value="">Any Type</option>
                                <option value="yes" <?= $selfContained === 'yes' ? 'selected' : '' ?>>Self Contained</option>
                                <option value="no" <?= $selfContained === 'no' ? 'selected' : '' ?>>Shared</option>
                            </select>
                        </div>
                    </div>

                    <!-- Amenities Filter -->
                    <div class="amenities-filter">
                        <label class="filter-label">Amenities</label>
                        <div class="amenities-grid">
                            <?php foreach ($amenitiesOptions as $amenity): ?>
                                <label class="amenity-filter-item <?= in_array($amenity, $amenities) ? 'selected' : '' ?>">
                                    <input 
                                        type="checkbox" 
                                        name="amenities[]" 
                                        value="<?= $amenity ?>" 
                                        <?= in_array($amenity, $amenities) ? 'checked' : '' ?>
                                    >
                                    <div class="amenity-icon">
                                        <?php
                                        $icons = [
                                            'WiFi' => 'wifi',
                                            'Furniture' => 'couch',
                                            'Gas Stove' => 'fire',
                                            'Electric Stove' => 'plug',
                                            'Fridge' => 'snowflake',
                                            'Washing Machine' => 'soap',
                                            'Security' => 'shield-alt',
                                            'Running Water' => 'faucet',
                                            'Study Area' => 'desktop',
                                            'Parking' => 'car'
                                        ];
                                        ?>
                                        <i class="fas fa-<?= $icons[$amenity] ?? 'check' ?>"></i>
                                    </div>
                                    <span class="amenity-name"><?= $amenity ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <a href="housing.php" class="btn btn-secondary">
                            <i class="fas fa-refresh"></i>
                            Reset Filters
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Header -->
        <div class="results-header">
            <div class="results-count">
                <strong><?= count($listings) ?></strong> properties found
                <?php if ($search || $minPrice || $maxPrice || $gender || $powerSource || $selfContained || !empty($amenities)): ?>
                    <span style="color: var(--primary);">• Filters applied</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Listings Grid -->
        <?php if (empty($listings)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-home"></i>
                </div>
                <h3 class="empty-title">No properties found</h3>
                <p class="empty-description">
                    <?php if ($search || $minPrice || $maxPrice || $gender || $powerSource || $selfContained || !empty($amenities)): ?>
                        Try adjusting your search criteria or 
                        <a href="housing.php" style="color: var(--primary); text-decoration: none;">clear all filters</a>.
                    <?php else: ?>
                        No properties are currently available. 
                        <a href="post_housing.php" style="color: var(--primary); text-decoration: none;">Be the first to list one</a>!
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="listings-grid">
                <?php foreach ($listings as $listing): 
                    $images = json_decode($listing['images'], true);
                    $amenitiesList = json_decode($listing['amenities'], true);
                ?>
                    <div class="listing-card">
                        <img src="<?= htmlspecialchars($images[0] ?? 'images/default-house.jpg') ?>" alt="<?= htmlspecialchars($listing['title']) ?>" class="listing-image">
                        <div class="listing-badge">
                            <?= $listing['available_spaces'] ?> Available
                        </div>
                        
                        <div class="listing-content">
                            <div class="listing-header">
                                <h3 class="listing-title"><?= htmlspecialchars($listing['title']) ?></h3>
                                <div class="listing-price">K<?= number_format($listing['price'], 2) ?></div>
                            </div>
                            
                            <div class="listing-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($listing['location']) ?></span>
                            </div>

                            <!-- Landlord Profile -->
                            <div class="landlord-profile">
                                <div class="landlord-avatar">
                                    <?= strtoupper(substr($listing['owner_name'], 0, 1)) ?>
                                </div>
                                <div class="landlord-info">
                                    <div class="landlord-name"><?= htmlspecialchars($listing['owner_name']) ?></div>
                                    <div class="landlord-verified">
                                        <i class="fas fa-check-circle"></i>
                                        Verified Landlord
                                    </div>
                                </div>
                                <a href="view_profile.php?user_id=<?= $listing['owner_id'] ?>" class="view-profile-btn">
                                    View Profile
                                </a>
                            </div>

                            <div class="listing-features">
                                <div class="feature">
                                    <i class="fas fa-bed"></i>
                                    <span><?= $listing['beds_per_room'] ?> beds/room</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-door-open"></i>
                                    <span><?= $listing['rooms_count'] ?> rooms</span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-<?= $listing['gender_preference'] === 'male' ? 'mars' : ($listing['gender_preference'] === 'female' ? 'venus' : 'neuter') ?>"></i>
                                    <span><?= ucfirst($listing['gender_preference']) ?></span>
                                </div>
                                <div class="feature">
                                    <i class="fas fa-bolt"></i>
                                    <span><?= ucfirst($listing['power_source']) ?></span>
                                </div>
                                <?php if ($listing['is_self_contained']): ?>
                                <div class="feature">
                                    <i class="fas fa-bath"></i>
                                    <span>Self Contained</span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($amenitiesList)): ?>
                                <div class="feature">
                                    <i class="fas fa-star"></i>
                                    <span><?= count($amenitiesList) ?> amenities</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="listing-actions">
                                <a href="view_housing.php?id=<?= $listing['id'] ?>" class="action-btn primary">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                <a href="housing_chat.php?listing_id=<?= $listing['id'] ?>&owner_id=<?= $listing['owner_id'] ?>" class="action-btn secondary">
                                    <i class="fas fa-comment"></i>
                                    Contact
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            © 2025 UniLink | HICKS BOZON404. All Rights Reserved.
        </div>
    </footer>

    <script>
        // Filter toggle functionality
        const filterToggle = document.getElementById('filterToggle');
        const filterPanel = document.getElementById('filterPanel');

        filterToggle.addEventListener('click', () => {
            filterPanel.classList.toggle('active');
            filterToggle.innerHTML = filterPanel.classList.contains('active') 
                ? '<i class="fas fa-times"></i> Hide Filters' 
                : '<i class="fas fa-sliders-h"></i> Show Filters';
        });

        // Amenity filter items
        document.querySelectorAll('.amenity-filter-item').forEach(item => {
            item.addEventListener('click', () => {
                item.classList.toggle('selected');
                const checkbox = item.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
            });
        });

        // Auto-submit form when amenities are selected
        document.querySelectorAll('.amenity-filter-item input').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                document.getElementById('filterForm').submit();
            });
        });

        // Price range validation
        const minPriceInput = document.querySelector('input[name="min_price"]');
        const maxPriceInput = document.querySelector('input[name="max_price"]');

        function validatePriceRange() {
            const minPrice = parseInt(minPriceInput.value);
            const maxPrice = parseInt(maxPriceInput.value);
            
            if (minPrice && maxPrice && minPrice > maxPrice) {
                minPriceInput.style.borderColor = 'var(--error)';
                maxPriceInput.style.borderColor = 'var(--error)';
                return false;
            } else {
                minPriceInput.style.borderColor = '';
                maxPriceInput.style.borderColor = '';
                return true;
            }
        }

        minPriceInput.addEventListener('blur', validatePriceRange);
        maxPriceInput.addEventListener('blur', validatePriceRange);

        // Form submission validation
        document.getElementById('filterForm').addEventListener('submit', (e) => {
            if (!validatePriceRange()) {
                e.preventDefault();
                alert('Minimum price cannot be greater than maximum price');
            }
        });

        // Add some interactive effects
        document.querySelectorAll('.listing-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Search input focus effect
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            searchInput.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        }
    </script>
</body>
</html>