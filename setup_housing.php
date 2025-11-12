<?php
require_once 'config/db_connect.php';

// Create housing_listings table
$sql = "CREATE TABLE IF NOT EXISTS housing_listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    location VARCHAR(255) NOT NULL,
    map_url TEXT NOT NULL,
    total_spaces INT NOT NULL,
    available_spaces INT NOT NULL,
    rooms_count INT NOT NULL,
    beds_per_room INT NOT NULL,
    gender_preference ENUM('male', 'female', 'mixed') NOT NULL,
    is_self_contained BOOLEAN DEFAULT FALSE,
    power_source ENUM('zesco', 'solar', 'both') NOT NULL,
    images JSON NOT NULL,
    amenities JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql)) {
    echo "Housing listings table created successfully\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}

// Create housing_messages table
$sql = "CREATE TABLE IF NOT EXISTS housing_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    listing_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (listing_id) REFERENCES housing_listings(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conn, $sql)) {
    echo "Housing messages table created successfully\n";
} else {
    echo "Error creating table: " . mysqli_error($conn) . "\n";
}