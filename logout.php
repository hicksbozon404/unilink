<?php
session_start();

// Regenerate ID to prevent fixation
session_regenerate_id(true);

// Destroy all session data
$_SESSION = [];
session_destroy();

// Expire cookie if you set one
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login
header('Location: login.php');
exit();
?>