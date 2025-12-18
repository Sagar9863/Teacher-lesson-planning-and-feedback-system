<?php
// logout.php
session_start();

// Determine the correct login page to redirect to based on the user's role.
$redirect_page = 'login.php'; // Default for students and teachers
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $redirect_page = 'admin_login.php';
}

// Unset all of the session variables.
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the appropriate login page.
header("Location: " . $redirect_page);
exit();
?>
