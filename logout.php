<?php
// logout.php

// 1. Initialize the session
session_start();

// 2. Unset all of the session variables
$_SESSION = array();

// 3. Destroy the session cookie in the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session on the server
session_destroy();

// 5. Force the browser NOT to cache this redirect
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 6. Send the user back to the login page
header("Location: index.php");
exit();
?>