<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear PHP session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Clear JWT (remove cookie or blacklist it)
if (isset($_COOKIE['jwt'])) {
    // Optionally: blacklist_token($_COOKIE['jwt']);
    setcookie("jwt", "", time() - 3600, "/");
}

// Redirect to login or selection page
header("Location: ../stall_admin/stall_selection.php");
exit();
?>
