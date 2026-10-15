
<?php
session_start();

// Clear the user's session
$_SESSION = [];

// Destroy the session
session_destroy();

// Return to the login page
header("Location: index.php");
exit();
