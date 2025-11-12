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

// Get listing ID from URL
$listingId = $_GET['id'] ?? 0;
if (!$listingId) {
    header('Location: housing.php');
    exit();
}

// Fetch listing details
$stmt = $pdo->prepare("
    SELECT hl.*, u.full_names as owner_name 
    FROM housing_listings hl 
    JOIN users u ON hl.owner_id = u.user_id 
    WHERE hl.id = ?
");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: housing.php');
    exit();
}

$images = json_decode($listing['images'], true);
$amenities = json_decode($listing['amenities'], true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | <?= htmlspecialchars($listing['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Add the same CSS variables and base styles from housing.php */
        :root {
            --primary: #06b6d4;
            --primary-hover: #0e7490;
            --primary-glow: #06b6d433;
            --secondary: #8b5cf6;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --bg-glass: rgba(255,255,255,0.15);
            
            --text-main: #1e293b;
            --text-subtle: #64748b;
            --text-light: #94a3b8;
            
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            
            --shadow-sm: 0 4px 6px -1px rgba(0,0,0,.1);
            --shadow-md: 0 10px 15px -3px rgba(0,0,0,.1);
            --shadow-lg: 0 25px 50px -12px rgba(0,0,0,.15);
            
            --transition: .35s cubic-bezier(.2,.8,.2,1);
        }

        :root.dark {
            --primary: #41e1ff;
            --primary-hover: #06b6d4;
            --primary-glow: #41e1ff33;
            --secondary: #a78bfa;
            --accent: #fbbf24;
            
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --bg-glass: rgba(30,41,59,0.3);
            
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

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.6;
            transition: all var(--transition);
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 0 1.5rem;
        }

        /* Header and navigation styles same as housing.php */
        .header {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
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
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: .5rem;
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: #fff;
            padding: .6rem 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            box-shadow: var(--shadow-md);
            border: none;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .nav-btn.secondary {
            background: var(--secondary);
        }

        /* Listing Details */
        .listing-details {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin: 2rem 0;
        }

        .image-gallery {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .main-image {
            grid-column: 1 / -1;
            border-radius: 1.5rem;
            overflow: hidden;
            height: 400px;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .thumbnail {
            border-radius: 1rem;
            overflow: hidden;
            height: 120px;
            cursor: pointer;
            opacity: 0.7;
            transition: all 0.3s;
        }

        .thumbnail:hover, .thumbnail.active {
            opacity: 1;
            transform: scale(1.05);
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info-card {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
            position: sticky;
            top: 100px;
        }

        .price {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .availability {
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin: 2rem 0;
        }

        .btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            color: white;
        }

        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .details-section {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-main);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.25rem;
        }

        .feature-info h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feature-info p {
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .amenity {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-body);
            border-radius: 0.75rem;
            border: 1px solid var(--border);
        }

        .amenity i {
            color: var(--primary);
        }

        .map-container {
            border-radius: 1rem;
            overflow: hidden;
            height: 300px;
            margin-top: 1rem;
        }

        .map-placeholder {
            width: 100%;
            height: 100%;
            background: var(--bg-body);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-subtle);
            border: 2px dashed var(--border);
        }

        .owner-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-body);
            border-radius: 1rem;
            margin-top: 1.5rem;
        }

        .owner-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .footer {
            padding: 2rem 0;
            text-align: center;
            color: var(--text-subtle);
            border-top: 1px solid var(--border);
            margin-top: 4rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .listing-details {
                grid-template-columns: 1fr;
            }
            
            .image-gallery {
                grid-template-columns: 1fr;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .amenities-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container nav-content">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-link"></i> UniLink
            </a>
            <div class="nav-actions">
                <a href="housing.php" class="nav-btn secondary">
                    <i class="fas fa-home"></i> Browse Listings
                </a>
                <a href="post_housing.php" class="nav-btn">
                    <i class="fas fa-plus"></i> Post Listing
                </a>
            </div>
        </div>
    </header>

    <!-- Listing Details -->
    <section class="container">
        <div class="listing-details">
            <!-- Main Content -->
            <div>
                <!-- Image Gallery -->
                <div class="image-gallery">
                    <div class="main-image">
                        <img id="mainImage" src="<?= htmlspecialchars($images[0] ?? 'images/default-house.jpg') ?>" alt="<?= htmlspecialchars($listing['title']) ?>">
                    </div>
                    <?php foreach ($images as $index => $image): ?>
                        <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" onclick="changeImage('<?= htmlspecialchars($image) ?>', this)">
                            <img src="<?= htmlspecialchars($image) ?>" alt="Thumbnail <?= $index + 1 ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Property Details -->
                <div class="details-section">
                    <h1 class="section-title"><?= htmlspecialchars($listing['title']) ?></h1>
                    <div class="features-grid">
                        <div class="feature">
                            <div class="feature-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="feature-info">
                                <h4>Location</h4>
                                <p><?= htmlspecialchars($listing['location']) ?></p>
                            </div>
                        </div>
                        <div class="feature">
                            <div class="feature-icon">
                                <i class="fas fa-bed"></i>
                            </div>
                            <div class="feature-info">
                                <h4>Bed Spaces</h4>
                                <p><?= $listing['total_spaces'] ?> total, <?= $listing['available_spaces'] ?> available</p>
                            </div>
                        </div>
                        <div class="feature">
                            <div class="feature-icon">
                                <i class="fas fa-door-open"></i>
                            </div>
                            <div class="feature-info">
                                <h4>Rooms</h4>
                                <p><?= $listing['rooms_count'] ?> rooms, <?= $listing['beds_per_room'] ?> beds each</p>
                            </div>
                        </div>
                        <div class="feature">
                            <div class="feature-icon">
                                <i class="fas fa-<?= $listing['gender_preference'] === 'male' ? 'mars' : ($listing['gender_preference'] === 'female' ? 'venus' : 'neuter') ?>"></i>
                            </div>
                            <div class="feature-info">
                                <h4>Gender</h4>
                                <p><?= ucfirst($listing['gender_preference']) ?> only</p>
                            </div>
                        </div>
                        <div class="feature">
                            <div class="feature-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="feature-info">
                                <h4>Power Source</h4>
                                <p><?= ucfirst($listing['power_source']) ?></p>
                            </div>
                        </div>
                        <div class="feature">
                            <div class="feature-icon">
                                <i class="fas fa-bath"></i>
                            </div>
                            <div class="feature-info">
                                <h4>Bathroom</h4>
                                <p><?= $listing['is_self_contained'] ? 'Self Contained' : 'Shared' ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="details-section">
                    <h2 class="section-title">Description</h2>
                    <p style="white-space: pre-line; color: var(--text-subtle);"><?= htmlspecialchars($listing['description']) ?></p>
                </div>

                <!-- Amenities -->
                <?php if (!empty($amenities)): ?>
                <div class="details-section">
                    <h2 class="section-title">Amenities</h2>
                    <div class="amenities-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <div class="amenity">
                                <i class="fas fa-check"></i>
                                <span><?= htmlspecialchars($amenity) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Location -->
                <div class="details-section">
                    <h2 class="section-title">Location</h2>
                    <p style="color: var(--text-subtle); margin-bottom: 1rem;"><?= htmlspecialchars($listing['location']) ?></p>
                    <div class="map-container">
                        <div class="map-placeholder">
                            <a href="<?= htmlspecialchars($listing['map_url']) ?>" target="_blank" class="btn btn-primary">
                                <i class="fas fa-map"></i> View on Google Maps
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <div class="info-card">
                    <div class="price">K<?= number_format($listing['price'], 2) ?>/month</div>
                    <div class="availability">
                        <?= $listing['available_spaces'] ?> Bed Spaces Available
                    </div>
                    
                    <div class="action-buttons">
                        <a href="housing_chat.php?listing_id=<?= $listing['id'] ?>&owner_id=<?= $listing['owner_id'] ?>" class="btn btn-primary">
                            <i class="fas fa-comment"></i> Contact Owner
                        </a>
                        <a href="<?= htmlspecialchars($listing['map_url']) ?>" target="_blank" class="btn btn-secondary">
                            <i class="fas fa-map-marker-alt"></i> View on Map
                        </a>
                    </div>

                    <div class="owner-info">
                        <div class="owner-avatar">
                            <?= strtoupper(substr($listing['owner_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <h4 style="font-weight: 600;"><?= htmlspecialchars($listing['owner_name']) ?></h4>
                            <p style="color: var(--text-subtle);">Property Owner</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            © 2025 UniLink | HICKS BOZON404. All Rights Reserved.
        </div>
    </footer>

    <script>
        function changeImage(src, element) {
            document.getElementById('mainImage').src = src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
        }
    </script>
</body>
</html>