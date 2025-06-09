<?php
session_start();    // Start the session

require_once '../db_connect.php';      // Include DB connection file
$dbc = connect_to_database();

// Stores form data
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Array to store log in errors
    $loginErrors = [];

    // Validate the email
    if(!str_ends_with($email, "@stu.bmcc.cuny.edu") && 
       !str_ends_with($email, "@bmcc.cuny.edu")) {
        $loginErrors[] = "Invalid email domain. Please use @stu.bmcc.cuny.edu or @bmcc.cuny.edu";
    }

    // Check for illegal characters in email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loginErrors[] = "Invalid email format!";
    }

    // Check if password contains minimum characters
    if(strlen($password) < 8) {
        $loginErrors[] = "Password must be at least 8 characters long";
    }

    // If there are errors, redirect to mainpage
    if(!empty($loginErrors)) {
        $_SESSION['loginErrors'] = $loginErrors;
        header('Location: ../mainpage.php');
        exit();
    } 

    // If there are no errors, validate user data and redirect
    // Using prepared statements
    $stmt = $dbc->prepare("SELECT emplid, first_name, last_name, email, password FROM students WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1) {
        $user = $result->fetch_assoc();     // Get user login data

        // Check if password is the same
        if(password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['emplid'] = $user['emplid'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['logged_in'] = true;

            header("Location: ../dashboard.php");
            exit();
        
        // Redirect to mainpage if password isn't the same
        } else {
            $_SESSION['loginErros'] = ['Incorrect email or password'];
            header('Location: ../mainpage.php');
            exit();
        }
    // No user was found with inputted email 
    } else {
        $_SESSION['loginErrors'] = ["User not found"];
        header('Location: ../mainpage.php');
        exit();
    }

    $stmt->close();

// Someone tries to enter login.php directly
} else {
    header('Location: ../mainpage.php');
    exit();
}

// Close connection
$dbc->close();
?>