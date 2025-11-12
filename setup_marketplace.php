<?php
// setup_marketplace.php
session_start();

// Database connection
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

try {
    // Create marketplace categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(50)
    )");

    // Create marketplace items table (FIXED: removed reserved words)
    $pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_items (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        category_id INT,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        price DECIMAL(10,2),
        image_path VARCHAR(500),
        item_condition ENUM('new', 'like_new', 'good', 'fair') DEFAULT 'good',
        item_status ENUM('available', 'sold', 'reserved') DEFAULT 'available',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES marketplace_categories(category_id)
    )");

    // Insert default categories
    $checkCategories = $pdo->query("SELECT COUNT(*) as count FROM marketplace_categories")->fetch();
    if ($checkCategories['count'] == 0) {
        $categories = [
            ['Textbooks & Course Materials', 'Academic books and study materials', 'fa-book'],
            ['Electronics & Gadgets', 'Laptops, phones, and tech accessories', 'fa-laptop'],
            ['Furniture & Home', 'Dorm furniture and home essentials', 'fa-couch'],
            ['Clothing & Fashion', 'Campus wear and fashion items', 'fa-tshirt'],
            ['Sports & Fitness', 'Sports equipment and fitness gear', 'fa-dumbbell'],
            ['Transportation', 'Bikes, scooters, and vehicles', 'fa-bicycle'],
            ['Tickets & Events', 'Event tickets and passes', 'fa-ticket-alt'],
            ['Services', 'Tutoring and other services', 'fa-hands-helping'],
            ['Other', 'Everything else', 'fa-ellipsis-h']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO marketplace_categories (name, description, icon) VALUES (?, ?, ?)");
        foreach ($categories as $category) {
            $stmt->execute($category);
        }
        echo "Marketplace tables created successfully! Categories added.<br>";
    } else {
        echo "Marketplace tables already exist.<br>";
    }
    
    echo "<a href='market.php'>Go to Marketplace</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>