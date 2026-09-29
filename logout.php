<?php
// logout.php - Session destruction & logout handler
require_once 'db.php';

// Clear session credentials
$_SESSION = array();

// Clear session cookie if stored
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session structure
session_destroy();

// Redirect back to login screen
header("Location: login.php");
exit();
?>