<?php
require_once 'config/db_connect.php';

// Build the query based on filters
$where_clauses = [];
$params = [];
$types = "";

if (!empty($_POST['price_range'])) {
    $price_range = explode('-', $_POST['price_range']);
    if (count($price_range) == 2) {
        $where_clauses[] = "price BETWEEN ? AND ?";
        $params[] = floatval($price_range[0]);
        $params[] = floatval($price_range[1]);
        $types .= "dd";
    } elseif ($price_range[0] == "1501+") {
        $where_clauses[] = "price > ?";
        $params[] = 1501;
        $types .= "d";
    }
}

if (!empty($_POST['gender'])) {
    $where_clauses[] = "gender_preference = ?";
    $params[] = $_POST['gender'];
    $types .= "s";
}

if (!empty($_POST['room_type'])) {
    $where_clauses[] = "is_self_contained = ?";
    $params[] = ($_POST['room_type'] == 'self_contained' ? 1 : 0);
    $types .= "i";
}

if (!empty($_POST['power_source'])) {
    $where_clauses[] = "power_source = ?";
    $params[] = $_POST['power_source'];
    $types .= "s";
}

// Build the final query
$sql = "SELECT * FROM housing_listings";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY created_at DESC";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Output filtered listings
while($listing = mysqli_fetch_assoc($result)) {
    $images = json_decode($listing['images'], true);
    $amenities = json_decode($listing['amenities'], true);
    ?>
    <div class="col-md-6 col-lg-4">
        <div class="housing-card">
            <div class="card-img-container">
                <img src="uploads/housing/<?php echo $images[0]; ?>" alt="Housing Image">
            </div>
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($listing['title']); ?></h5>
                <div class="price-tag">
                    K<?php echo number_format($listing['price']); ?> <span class="fs-6 fw-normal">per bed space</span>
                </div>
                
                <div class="property-info">
                    <div class="info-item">
                        <span class="material-icons">location_on</span>
                        <?php echo htmlspecialchars($listing['location']); ?>
                    </div>
                    <div class="info-item">
                        <span class="material-icons">bed</span>
                        <?php echo $listing['available_spaces']; ?> of <?php echo $listing['total_spaces']; ?> spaces
                    </div>
                    <div class="info-item">
                        <span class="material-icons">people</span>
                        <?php echo ucfirst($listing['gender_preference']); ?>
                    </div>
                </div>

                <div class="amenities-container">
                    <?php foreach($amenities as $amenity) { ?>
                        <span class="amenity-badge"><?php echo htmlspecialchars($amenity); ?></span>
                    <?php } ?>
                </div>

                <div class="card-actions">
                    <button class="btn btn-primary" onclick="openLocation('<?php echo $listing['map_url']; ?>')">
                        <span class="material-icons me-2">map</span> View on Map
                    </button>
                    <button class="btn btn-outline-primary" onclick="startChat('<?php echo $listing['owner_id']; ?>')">
                        <span class="material-icons me-2">chat</span> Contact Owner
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}