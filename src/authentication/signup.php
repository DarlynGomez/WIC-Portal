<?php

session_start();    // Start the session

require_once '../db_connect.php';      // Include DB connection file
$dbc = connect_to_database();

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = $_POST['firstName'];
    $lastname = $_POST['lastName'];
    $emplid = $_POST['emplid'];
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Array to store sign up errors
    $signupErrors = [];

    // Validate the email
    if(!str_ends_with($email, "@stu.bmcc.cuny.edu") && 
        !str_ends_with($email, "@bmcc.cuny.edu")) {
        $signupErrors[] = "Invalid email domain. Please use @stu.bmcc.cuny.edu or @bmcc.cuny.edu";
    }

    // Check for illegal characters in email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signupErrors[] = "Invalid email format!";
    }

    // Check if password contains minimum characters
    if(strlen($password) < 8) {
        $signupErrors[] = "Password must be at least 8 characters long";
    }

    // Check if email already exists
    $checkStmt = $dbc->prepare("SELECT email FROM students WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if($result->num_rows > 0) {
        $signupErrors[] = "Email already exists. Please use a different email or try logging in.";
    }
    $checkStmt->close();

    // Check if EMPLID already exists
    $checkStmt = $dbc->prepare("SELECT emplid FROM students WHERE emplid = ?");
    $checkStmt->bind_param("s", $emplid);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if($result->num_rows > 0) {
        $signupErrors[] = "EMPLID already exists. Please use a different EMPLID or try logging in.";
    }
    $checkStmt->close();

    // If there are errors, redirect to mainpage
    if(!empty($signupErrors)) {
        $_SESSION['signupErrors'] = $signupErrors;
        header('Location: ../mainpage.php');
        exit();
    } 

    // If there are no errors, store user data and redirect to login
    $stmt = $dbc->prepare("INSERT INTO students (emplid, first_name, last_name, email, password) VALUES (?, ?, ?, ?, ?)");

    if($stmt === false) {
        die("Insertion failed: " . $dbc->error);
    }

    $stmt->bind_param("issss", $emplid, $firstname, $lastname, $email, $hashedPassword);
    $result = $stmt->execute();

    // Check if insertion was successful
    if ($result) {
        // Succesful sign up messages
        $_SESSION['signup_success'] = true;
        $_SESSION['message'] = "Account created successfully! You can now log in.";
        header('Location: ../mainpage.php');
    } else {
        // Handle database insertion error
        $_SESSION['signupErrors'] = ["Database error: " . $stmt->error];
        header('Location: ../mainpage.php');
    }

    $stmt->close();
    $dbc->close();
    exit();
}
?>