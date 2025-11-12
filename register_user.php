<?php
session_start();

$host = 'localhost';
$db   = 'unilink_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    $_SESSION['register_error'] = "Database connection failed.";
    header('Location: register.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_names = trim($_POST['full_names']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $password   = $_POST['password'];

    // Validation
    if (empty($full_names) || empty($email) || empty($phone) || empty($password)) {
        $_SESSION['register_error'] = "All fields are required.";
        header('Location: register.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Invalid email format.";
        header('Location: register.php');
        exit();
    }

    if (strlen($password) < 8) {
        $_SESSION['register_error'] = "Password must be 8+ characters.";
        header('Location: register.php');
        exit();
    }

    // Check for existing user
    $sql_check = "SELECT user_id FROM users WHERE email = ? OR phone = ?";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$email, $phone]);

    if ($stmt_check->rowCount() > 0) {
        $_SESSION['register_error'] = "Email or phone already registered.";
        header('Location: register.php');
        exit();
    }

    // Hash password & insert
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $sql_insert = "INSERT INTO users (full_names, email, phone, password) VALUES (?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([$full_names, $email, $phone, $hashed_password]);

        // AUTO-LOGIN
        $user_id = $pdo->lastInsertId();
        session_regenerate_id(true);

        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['full_names'] = $full_names;
        $_SESSION['email'] = $email;

        header('Location: dashboard.php');
        exit();

    } catch (\PDOException $e) {
        error_log("Register error: " . $e->getMessage());
        $_SESSION['register_error'] = "Registration failed. Try again.";
        header('Location: register.php');
        exit();
    }
} else {
    header('Location: register.php');
    exit();
}
?>