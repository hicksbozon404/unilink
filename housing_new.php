<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];
$fullName = htmlspecialchars($_SESSION['full_names'] ?? 'Student');

require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniLink | Student Housing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ---------- VARIABLES ---------- */
        :root {
            /* Colors */
            --primary: #06b6d4;
            --primary-hover: #0e7490;
            --bg-body: #ffffff;
            --bg-card: #f9fafb;
            --bg-card-hover: #f3f4f6;
            --text-main: #1f2937;
            --text-subtle: #4b5563;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 12px 24px -4px rgba(0,0,0,0.12);
            --border-color: #e5e7eb;
            
            /* Transitions */
            --tr: 0.3s ease;
            
            /* Status Colors */
            --success: #059669;
            --warning: #eab308;
            --error: #dc2626;
            
            /* Other */
            --header-height: 4rem;
            --radius: 0.75rem;
        }

        /* Dark Mode */
        :root.dark {
            --primary: #41e1ff;
            --primary-hover: #06b6d4;
            --bg-body: #121e35;
            --bg-card: #1c2b47;
            --bg-card-hover: #233254;
            --text-main: #e2e8f0;
            --text-subtle: #94a3b8;
            --border-color: #334155;
        }

        /* Base Styles */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-body);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            transition: background var(--tr), color var(--tr);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Header Styles */
        .header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            height: var(--header-height);
            position: sticky;
            top: 0;
            z-index: 100;
            transition: all var(--tr);
        }

        .nav {
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--tr);
        }

        .nav-link:hover {
            color: var(--primary);
        }

        .nav-link.active {
            color: var(--primary);
        }

        /* Hero Section */
        .hero {
            text-align: center;
            padding: 3rem 0;
        }

        .hero h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.5rem;
            color: var(--text-main);
            margin-bottom: 1rem;
        }

        .hero p {
            color: var(--text-subtle);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Filter Section */
        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            transition: all var(--tr);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .filter-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-subtle);
            margin-bottom: 0.5rem;
        }

        .filter-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: calc(var(--radius) * 0.75);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.95rem;
            transition: all var(--tr);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
        }

        /* Housing Cards */
        .housing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .housing-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: all var(--tr);
        }

        .housing-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .card-image {
            position: relative;
            padding-top: 60%;
            background: var(--bg-card-hover);
        }

        .card-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-content {
            padding: 1.5rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .card-price {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .card-price span {
            font-size: 0.875rem;
            color: var(--text-subtle);
            font-weight: 400;
        }

        .card-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-subtle);
            font-size: 0.9rem;
        }

        .detail-item i {
            color: var(--primary);
            font-size: 1rem;
        }

        .card-amenities {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .amenity-tag {
            background: rgba(6, 182, 212, 0.1);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .card-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1rem;
            border-radius: calc(var(--radius) * 0.75);
            font-weight: 500;
            text-align: center;
            transition: all var(--tr);
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 2rem 0;
            color: var(--text-subtle);
            border-top: 1px solid var(--border-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .housing-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<header class="header">
    <div class="container nav">
        <a href="#" class="nav-brand">
            <i class="fas fa-building"></i>
            UniLink Housing
        </a>
        <div class="nav-links">
            <a href="housing.php" class="nav-link active">Browse</a>
            <a href="housing_post.php" class="nav-link">Post Listing</a>
            <a href="#" class="nav-link" onclick="toggleTheme()">
                <i class="fas fa-moon"></i>
            </a>
        </div>
    </div>
</header>

<main class="container">
    <!-- Hero Section -->
    <section class="hero">
        <h1>Find Your Perfect Student Housing</h1>
        <p>Browse through verified boarding houses near campus. Filter by your preferences and connect with landlords directly.</p>
    </section>

    <!-- Filter Section -->
    <div class="filter-section">
        <form id="filterForm" class="filter-grid">
            <div class="filter-group">
                <label>Price Range</label>
                <select class="filter-select" name="price_range">
                    <option value="">All Prices</option>
                    <option value="0-500">K0 - K500</option>
                    <option value="501-1000">K501 - K1000</option>
                    <option value="1001-1500">K1001 - K1500</option>
                    <option value="1501+">K1501+</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Gender Preference</label>
                <select class="filter-select" name="gender">
                    <option value="">All</option>
                    <option value="male">Males Only</option>
                    <option value="female">Females Only</option>
                    <option value="mixed">Mixed Gender</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Room Type</label>
                <select class="filter-select" name="room_type">
                    <option value="">All Types</option>
                    <option value="self_contained">Self Contained</option>
                    <option value="shared">Shared Facilities</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Power Source</label>
                <select class="filter-select" name="power_source">
                    <option value="">All Sources</option>
                    <option value="zesco">ZESCO</option>
                    <option value="solar">Solar</option>
                    <option value="both">Both</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Listings Grid -->
    <div class="housing-grid" id="listings">
        <?php
        $query = "SELECT h.*, u.full_names as owner_name, u.phone as owner_phone 
                  FROM housing_listings h 
                  LEFT JOIN users u ON h.owner_id = u.user_id 
                  ORDER BY h.created_at DESC";
        $result = mysqli_query($conn, $query);

        while($listing = mysqli_fetch_assoc($result)) {
            $images = json_decode($listing['images'], true);
            $amenities = json_decode($listing['amenities'], true);
        ?>
        <div class="housing-card">
            <div class="card-image">
                <img src="uploads/housing/<?php echo $images[0]; ?>" alt="Housing Image">
            </div>
            <div class="card-content">
                <h3 class="card-title"><?php echo htmlspecialchars($listing['title']); ?></h3>
                <div class="card-price">
                    K<?php echo number_format($listing['price']); ?> <span>per bed space</span>
                </div>
                
                <div class="card-details">
                    <div class="detail-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($listing['location']); ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-bed"></i>
                        <?php echo $listing['available_spaces']; ?> of <?php echo $listing['total_spaces']; ?> spaces
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <?php echo ucfirst($listing['gender_preference']); ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-bolt"></i>
                        <?php echo ucfirst($listing['power_source']); ?>
                    </div>
                </div>

                <div class="card-amenities">
                    <?php foreach($amenities as $amenity) { ?>
                        <span class="amenity-tag"><?php echo htmlspecialchars($amenity); ?></span>
                    <?php } ?>
                </div>

                <div class="card-actions">
                    <button class="btn btn-primary" onclick="openLocation('<?php echo $listing['map_url']; ?>')">
                        <i class="fas fa-map-marked-alt"></i> View Map
                    </button>
                    <button class="btn btn-outline" onclick="startChat('<?php echo $listing['owner_id']; ?>')">
                        <i class="fas fa-comments"></i> Contact
                    </button>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</main>

<footer class="footer">
    <div class="container">© 2025 UniLink | HICKS BOZON404.</div>
</footer>

<script>
// Theme Toggle
const html = document.documentElement;
const theme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
if (theme === 'dark') html.classList.add('dark');

function toggleTheme() {
    html.classList.toggle('dark');
    localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
}

// Map and Chat Functions
function openLocation(mapUrl) {
    window.open(mapUrl, '_blank');
}

function startChat(ownerId) {
    window.location.href = `housing_chat.php?owner=${ownerId}`;
}

// Filter Functionality
document.querySelectorAll('#filterForm select').forEach(select => {
    select.addEventListener('change', function() {
        const formData = new FormData(document.getElementById('filterForm'));
        fetch('housing_filter.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            document.getElementById('listings').innerHTML = html;
        });
    });
});
</script>

</body>
</html>