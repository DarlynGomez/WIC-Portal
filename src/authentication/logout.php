
<?php

// Temporary script to logiut
session_start();

$_SESSION = array();

session_destroy();       // Destroy the session

// Redirect to main page
header("Location: ../mainpage.php");
exit();
?>

