<?php
// Start a session to manage user login state and display messages
session_start();

// -----------------------------------------------------------------------------
// 1. CONFIGURATION: Update these settings for your XAMPP/MySQL environment
// -----------------------------------------------------------------------------
$host = 'localhost'; // Usually 'localhost' for XAMPP
$db   = 'unilink_db'; // Ensure this matches your database name
$user = 'root';      // Default XAMPP user
$pass = '';          // Default XAMPP password (often empty)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// -----------------------------------------------------------------------------
// 2. Database Connection
// -----------------------------------------------------------------------------
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Log the error and display a generic message to the user
    error_log("Database connection error: " . $e->getMessage());
    $_SESSION['login_error'] = "A server error occurred. Please try again later.";
    header('Location: login.php');
    exit();
}

// -----------------------------------------------------------------------------
// 3. Form Submission and Validation
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if required fields are present
    if (empty($_POST['identifier']) || empty($_POST['password'])) {
        $_SESSION['login_error'] = "Please enter your email/phone and password.";
        header('Location: login.php');
        exit();
    }

    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    // -------------------------------------------------------------------------
    // 4. Retrieve User Data by Email or Phone
    // -------------------------------------------------------------------------
    // We search the 'users' table using the identifier provided by the user,
    // checking both the 'email' and 'phone' columns.
    $sql = "SELECT user_id, full_names, email, password, phone FROM users WHERE email = ? OR phone = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    // -------------------------------------------------------------------------
    // 5. Authentication Check
    // -------------------------------------------------------------------------
    if ($user && password_verify($password, $user['password'])) {
        
        // Success: Password matches!
        
        // Start a new session or refresh the existing one
        session_regenerate_id(true); 
        
        // Store essential user information in the session
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_names'] = $user['full_names'];
        $_SESSION['email'] = $user['email'];
        
        // Clear any previous error message
        unset($_SESSION['login_error']);

        // Redirect to the secure dashboard page
        header('Location: dashboard.php');
        exit();

    } else {
        // Failure: User not found or password incorrect
        $_SESSION['login_error'] = "Invalid email/phone or password. Please try again.";
        header('Location: login.php');
        exit();
    }

} else {
    // If someone tries to access this page directly (not via POST), redirect them.
    header('Location: login.php');
    exit();
}
?>
