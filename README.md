UniLink - Campus Marketplace & Housing Platform
📋 Table of Contents
Project Overview

Features

Technology Stack

Installation Guide

Database Schema

File Structure

API Documentation

User Guide

Admin Guide

Development Guide

Security Features

Performance Optimization

Deployment Guide

Troubleshooting

Contributing

License

🎯 Project Overview
UniLink is a comprehensive campus marketplace and housing platform designed specifically for university students. The platform enables students to buy/sell items, find housing, and connect with other students in a secure, campus-focused environment.

Key Objectives
Provide a safe marketplace for students to trade goods and services

Simplify the process of finding campus housing

Foster student community through secure messaging

Reduce student expenses through peer-to-peer trading

Offer a modern, mobile-friendly user experience

Target Audience
University students

Campus organizations

Student landlords

Campus administration

✨ Features
🔐 Authentication System
Secure Registration & Login

Email verification system

Password strength validation

Session management

Remember me functionality

User Profiles

Customizable profile information

Rating and review system

Activity history

Privacy controls

🛍️ Marketplace Module
Item Listings

Categorized product listings (Textbooks, Electronics, Furniture, etc.)

Image upload with compression

Price negotiation system

Condition descriptions

Search & Filter

Advanced search functionality

Category filtering

Price range filters

Location-based sorting

Transaction Management

Secure messaging system

Meeting coordination

Sale confirmation

Dispute resolution

🏠 Housing Module
Property Listings

Detailed property information

Multiple image galleries

Amenity listings

Rental terms and conditions

Location Services

Campus proximity indicators

Neighborhood information

Transportation details

Roommate Finder

Compatibility matching

Profile verification

Shared preference system

💬 Messaging System
Real-time Chat

One-on-one conversations

Group chats for housing

File sharing capabilities

Read receipts and typing indicators

Notification System

Push notifications

Email alerts

In-app notifications

Customizable preferences

📱 Mobile Optimization
Responsive Design

Mobile-first approach

Touch-friendly interfaces

Optimized for various screen sizes

Progressive Web App (PWA) features

🛠 Technology Stack
Frontend Technologies
HTML5 - Semantic markup structure

CSS3 - Modern styling with CSS Grid and Flexbox

CSS Custom Properties (Variables)

Responsive design patterns

Dark/Light theme system

JavaScript (ES6+) - Client-side functionality

Vanilla JS for performance

AJAX for dynamic content

Modern browser APIs

Backend Technologies
PHP 7.4+ - Server-side processing

Object-oriented programming

PDO for database operations

Session management

MySQL 5.7+ - Database management

Relational database design

Optimized queries

Data integrity constraints

Additional Technologies
Font Awesome 6 - Icon library

Google Fonts - Typography (Inter, Space Grotesk)

Apache/Nginx - Web server

Git - Version control

📥 Installation Guide
Prerequisites
Web server (Apache/Nginx)

PHP 7.4 or higher

MySQL 5.7 or higher

Composer (for dependency management)

Step-by-Step Installation
Server Setup

bash
# Clone the repository
git clone https://github.com/your-username/unilink.git
cd unilink
Database Configuration

sql
-- Create database
CREATE DATABASE unilink_db;

-- Import database schema
mysql -u username -p unilink_db < database/schema.sql
Environment Configuration

bash
# Copy configuration file
cp config/config.example.php config/config.php

# Edit configuration with your settings
nano config/config.php
Configure config.php

php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'unilink_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_URL', 'https://yourdomain.com');
define('UPLOAD_PATH', '/path/to/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Security settings
define('ENCRYPTION_KEY', 'your-secure-key-here');
?>
File Permissions

bash
chmod 755 uploads/
chmod 644 config/config.php
Web Server Configuration

apache
# Apache .htaccess example
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?path=$1 [QSA,L]
🗃️ Database Schema
Core Tables
Users Table
sql
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_names VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    university VARCHAR(100),
    course VARCHAR(100),
    year_of_study INT,
    profile_picture VARCHAR(255),
    bio TEXT,
    rating DECIMAL(3,2) DEFAULT 5.00,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
Marketplace Items Table
sql
CREATE TABLE marketplace_items (
    item_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category ENUM('textbooks', 'electronics', 'furniture', 'clothing', 'other'),
    condition ENUM('new', 'like_new', 'good', 'fair', 'poor'),
    image_path VARCHAR(255),
    status ENUM('available', 'pending', 'sold') DEFAULT 'available',
    location VARCHAR(255),
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
Housing Listings Table
sql
CREATE TABLE housing_listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    owner_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    rent_amount DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2),
    property_type ENUM('apartment', 'house', 'condo', 'room', 'studio'),
    bedrooms INT,
    bathrooms DECIMAL(3,1),
    amenities JSON,
    images JSON,
    utilities_included BOOLEAN DEFAULT FALSE,
    pet_friendly BOOLEAN DEFAULT FALSE,
    available_from DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
);
Messaging Tables
sql
CREATE TABLE marketplace_conversations (
    conversation_id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES marketplace_items(item_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(user_id) ON DELETE CASCADE
);

CREATE TABLE marketplace_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_text TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES marketplace_conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
);
📁 File Structure
text
unilink/
├── index.php                 # Main entry point
├── dashboard.php             # User dashboard
├── login.php                 # Authentication
├── register.php              # User registration
├── market.php                # Marketplace main page
├── upload_to_market.php      # Item listing creation
├── view_item.php             # Individual item view
├── chat.php                  # Marketplace messaging
├── housing.php               # Housing listings
├── view_housing.php          # Individual housing view
├── housing_chat.php          # Housing messaging
├── profile.php               # User profile management
├── config/
│   └── config.php            # Application configuration
├── assets/
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   ├── images/               # Static images
│   └── uploads/              # User uploaded files
├── includes/
│   ├── header.php            # Common header
│   ├── footer.php            # Common footer
│   ├── db_connection.php     # Database connection
│   └── functions.php         # Utility functions
└── database/
    └── schema.sql            # Database schema
🔌 API Documentation
Authentication Endpoints
POST /login.php
http
POST /login.php
Content-Type: application/x-www-form-urlencoded

email=student@university.edu&password=securepassword
Response:

json
{
    "success": true,
    "message": "Login successful",
    "user": {
        "user_id": 123,
        "full_names": "John Doe",
        "email": "student@university.edu"
    }
}
POST /register.php
http
POST /register.php
Content-Type: application/x-www-form-urlencoded

email=student@university.edu&password=securepassword&full_names=John Doe&university=State University
Marketplace Endpoints
GET /market.php
Query Parameters:

category - Filter by category

search - Search term

min_price - Minimum price

max_price - Maximum price

page - Pagination

POST /upload_to_market.php
http
POST /upload_to_market.php
Content-Type: multipart/form-data

title=Textbook&description=Like new&price=25.00&category=textbooks&condition=like_new
👤 User Guide
Getting Started
Registration

Visit the registration page

Provide university email address

Verify email through confirmation link

Complete profile setup

Marketplace Usage

Browse items by category or search

Use filters to narrow results

Contact sellers through secure messaging

Arrange safe meetups on campus

Housing Search

Browse available properties

Filter by price, location, and amenities

Contact landlords directly

Schedule property viewings

Messaging

Access messages from dashboard

Start conversations from item/housing pages

Receive real-time notifications

Manage multiple conversations

Best Practices
For Buyers/Tenants
Verify item condition before purchase

Meet in safe, public locations on campus

Use the platform's messaging for all communications

Read seller/landlord reviews

For Sellers/Landlords
Provide clear, accurate descriptions

Upload multiple high-quality photos

Respond promptly to inquiries

Maintain good ratings through positive interactions

👨‍💼 Admin Guide
Administrative Features
User Management
View all registered users

Suspend problematic accounts

Verify user identities

Monitor user activity

Content Moderation
Review reported items/listings

Remove inappropriate content

Manage categories and filters

Monitor messaging for safety

Analytics
Platform usage statistics

Transaction volumes

User engagement metrics

Revenue reports (if applicable)

Admin Access
Administrators can access the admin panel at /admin/ with appropriate credentials.

🚀 Development Guide
Setting Up Development Environment
Local Development

bash
# Using XAMPP/WAMP
git clone https://github.com/your-username/unilink.git
cd unilink
# Place in htdocs/www directory
Development Tools

PHPStorm/VSCode for coding

MySQL Workbench for database

Browser developer tools

Git for version control

Coding Standards
PHP Standards
php
<?php
/**
 * Brief description of function
 * 
 * @param string $paramName Description of parameter
 * @return mixed Description of return value
 */
function exampleFunction($paramName) {
    // Use camelCase for variables and functions
    $variableName = "value";
    
    // Use prepared statements for database queries
    $stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");
    $stmt->execute([$id]);
    
    return $result;
}
?>
JavaScript Standards
javascript
// Use modern ES6+ features
const functionName = (param) => {
    // Descriptive variable names
    const userPreferences = getUserPreferences();
    
    // Use async/await for promises
    try {
        const response = await fetch('/api/endpoint');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error:', error);
    }
};
CSS Standards
css
/* Use CSS variables for theming */
:root {
    --primary-color: #06b6d4;
    --spacing-unit: 1rem;
}

/* Mobile-first responsive design */
.component {
    padding: var(--spacing-unit);
}

@media (min-width: 768px) {
    .component {
        padding: calc(var(--spacing-unit) * 2);
    }
}
Testing Procedures
Unit Testing

Test individual functions

Database query testing

Form validation testing

Integration Testing

User authentication flow

Messaging system

File upload functionality

User Acceptance Testing

Complete user workflows

Mobile device testing

Cross-browser compatibility

🔒 Security Features
Authentication Security
Password Hashing: bcrypt with cost factor 12

Session Management: Secure session handling with regeneration

CSRF Protection: Token-based cross-site request forgery protection

Input Validation: Server-side validation for all user inputs

Data Protection
SQL Injection Prevention: PDO prepared statements

XSS Prevention: htmlspecialchars() output encoding

File Upload Security: Type verification and size limits

Data Encryption: Sensitive data encryption at rest

Privacy Features
User Control: Privacy settings for personal information

Data Minimization: Collection of only necessary information

Secure Communications: HTTPS enforcement

Regular Audits: Security vulnerability assessments

⚡ Performance Optimization
Frontend Optimization
Image Optimization: WebP format with fallbacks

Lazy Loading: Images load as needed

CSS/JS Minification: Reduced file sizes

Caching Strategies: Browser and server caching

Backend Optimization
Database Indexing: Optimized query performance

Query Optimization: Efficient database operations

PHP OpCache: Bytecode caching

CDN Integration: Content delivery network

Mobile Optimization
Responsive Images: Multiple sizes for different devices

Touch Optimization: Larger touch targets

Performance Budget: Limited resource usage

Progressive Enhancement: Core functionality without JS

🌐 Deployment Guide
Production Server Requirements
Server Specifications
Operating System: Ubuntu 20.04 LTS or CentOS 8

Web Server: Apache 2.4+ or Nginx 1.18+

PHP: 7.4+ with required extensions

MySQL: 5.7+ or MariaDB 10.3+

Storage: SSD recommended for better performance

Required PHP Extensions
bash
sudo apt-get install php php-mysql php-gd php-curl php-json php-mbstring php-xml php-zip
Deployment Steps
Server Preparation

bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required software
sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql
Application Deployment

bash
# Clone to web directory
sudo git clone https://github.com/your-username/unilink.git /var/www/unilink

# Set permissions
sudo chown -R www-data:www-data /var/www/unilink
sudo chmod -R 755 /var/www/unilink
SSL Certificate (Let's Encrypt)

bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Obtain certificate
sudo certbot --apache -d yourdomain.com
Cron Jobs

bash
# Set up daily database backups
crontab -e
# Add: 0 2 * * * /usr/bin/mysqldump -u username -p password unilink_db > /backup/unilink_$(date +\%Y\%m\%d).sql
Monitoring and Maintenance
Log Monitoring
Application error logs

Access logs analysis

Database query logs

Security event monitoring

Backup Strategy
Daily Database Backups: Automated MySQL dumps

File System Backups: Weekly full backups

Configuration Backups: Version-controlled configuration

Disaster Recovery: Comprehensive recovery plan

🐛 Troubleshooting
Common Issues and Solutions
Database Connection Issues
php
// Check database configuration
try {
    $pdo = new PDO("mysql:host=localhost;dbname=unilink_db", "username", "password");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Display user-friendly error
}
File Upload Problems
Verify upload directory permissions

Check php.ini upload limits

Validate file types server-side

Implement proper error handling

Performance Issues
Enable PHP opcache

Optimize database queries

Implement caching strategies

Monitor server resources

Debug Mode
Enable debug mode in development by setting:

php
define('DEBUG_MODE', true);
This will display detailed error messages and log additional information.

🤝 Contributing
Development Workflow
Fork the Repository

Create Feature Branch

bash
git checkout -b feature/amazing-feature
Commit Changes

bash
git commit -m 'Add amazing feature'
Push to Branch

bash
git push origin feature/amazing-feature
Open Pull Request

Contribution Guidelines
Follow existing code style

Write clear commit messages

Add tests for new features

Update documentation

Ensure mobile responsiveness

📄 License
This project is licensed under the MIT License - see the LICENSE.md file for details.

📞 Support
For technical support or questions:

Email: h57630752@gmail.com

Documentation: docs.unilink.com

Issue Tracker: GitHub Issues

🗺️ Roadmap
Future Features
Mobile application development

Advanced recommendation engine

Payment integration

Campus event integration

Group buying features

International student support

Version History
v1.0 (Current): Basic marketplace and housing features

v1.1 (Planned): Enhanced messaging and notifications

v2.0 (Future): Mobile app and advanced features

UniLink - Connecting Campus Communities Through Secure Trading and Housing Solutions
