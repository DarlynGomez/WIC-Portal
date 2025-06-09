<?php
session_start();

// Redirect logged in users to dashboard 
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true ) {
    header('Location: dashboard.php');
    exit();     // Stops rest of script from running
}

// Keep sidebar open if there are errors
$showLoginSidebar = isset($_SESSION['loginErrors']) && !empty($_SESSION['loginErrors']);
$showSignupSidebar = isset($_SESSION['signupErrors']) && !empty($_SESSION['signupErrors'])
                    || isset($_SESSION['message']) && !empty($_SESSION['message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WIC Portal</title>
    <link rel="stylesheet" href="styles.css">

    <!-- google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <!-- social media icons/links -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>

    <script>
        // If sidebar should be displayed show overlay too
        <?php if($showLoginSidebar || $showSignupSidebar): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('overlay').style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
        <?php endif; ?>
    </script>

    <!-- Top sign-in bar -->
    <div id="sign-in-top">
        <button id="sign-up">sign up</button>
    </div>

    <!-- Navigation bar -->
    <nav id="nav-bar">
        <div class="nav-content">
            <div class="nav-left">
                <button class="hamburger-menu" id="hamburger-menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                <div class="logo-title">she&lt;CODE/&gt;</div>
            </div>
            <div class="nav-buttons">
                <button class="nav-btn outline" id="become-member-btn">Become a member</button>
                <button class="nav-btn text" id="sign-in-btn">sign in</button>
            </div>
        </div>
    </nav>

    <!-- Navigation Sidebar -->
    <div id="nav-sidebar" class="nav-sidebar">
        <div class="nav-sidebar-content">
            <button class="close-nav-btn">&times;</button>
            <ul class="nav-menu">
                <li>
                    <a href="dashboard.php">
                        <ion-icon name="grid-outline"></ion-icon>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php">
                        <ion-icon name="person-outline"></ion-icon>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="events.php">
                        <ion-icon name="calendar-outline"></ion-icon>
                        <span>Events</span>
                    </a>
                </li>
                <li>
                    <a href="mentoring.php">
                        <ion-icon name="people-outline"></ion-icon>
                        <span>Mentoring</span>
                    </a>
                </li>
                <li>
                    <a href="sip.php">
                        <ion-icon name="code-slash-outline"></ion-icon>
                        <span>SIP</span>
                    </a>
                </li>
                <li>
                    <a href="goals.php">
                        <ion-icon name="trophy-outline"></ion-icon>
                        <span>Goal Tracking</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <ion-icon name="settings-outline"></ion-icon>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Hero Section -->
    <div class="hero-section">
        <div class="hero-content column">
            <h1 class="hero-title">Build Your Future<br>in Computing</h1>
            <p class="hero-subtitle">Join BMCC's community of women<br>transforming technology</p>
            <button id="hero-member-btn" class="btn-primary">Become a member</button>
        </div>
        <div class="kitty column">
            <img src="images/kitty.png" id="kitty-image">
        </div>
    </div>

    <!-- Login sidebar popup -->
    <div id="login-sidebar" class="sidebar <?php echo $showLoginSidebar ? 'show' : ''; ?>">
        <div class="sidebar-container">
            <span class="close-btn">&times;</span>
            <div id="login-form">
                <h2>Log In</h2>
                <div id="form-container">
                    <form method="post" action="authentication/login.php">
                        <input type="email" name="email" placeholder="Enter BMCC Email (@stu.bmcc.cuny.edu)" required>
                        <input type="password" name="password" placeholder="Password" required>
                        <button type="button" id="forgot-password-link" class="text-link">Forgot your password?</button>
                        
                        <?php if(isset($_SESSION['loginErrors']) && !empty($_SESSION['loginErrors'])): ?>
                            <div class="error-container">
                                <?php foreach($_SESSION['loginErrors'] as $error): ?>
                                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php unset($_SESSION['loginErrors']); ?>
                        <?php endif; ?>
                        
                        <input type="submit" value="Sign In" class="submit-btn">
                        <button type="button" id="register-link" class="text-link">Don't have an account? Register Now!</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Signup sidebar popup -->
    <div id="signup-sidebar" class="sidebar <?php echo $showSignupSidebar ? 'show' : ''; ?>">
        <div class="sidebar-container">
            <span class="close-btn">&times;</span>
            <div id="signup-form">
                <h2 id="create-account">Create Account</h2>
                <div id="form-container">
                    <form method="post" action="authentication/signup.php">
                        <input type="text" name="firstName" placeholder="First name">
                        <input type="text" name="lastName" placeholder="Last name">
                        <div class="password-show">
                            <input type="password" id="emplid" name="emplid" placeholder="EMPLID">
                            <button type="button" id="toggleEmplid" class="toggle-hidden">SHOW</button>
                        </div>
                        <input type="email" name="email" placeholder="Enter BMCC Email (@stu.bmcc.cuny.edu)" required>
                        <div class="password-show">
                            <input type="password" id="passwordBox" name="password" placeholder="Password" required>
                            <button type="button" id="togglePassword" class="toggle-hidden">SHOW</button>
                        </div>
                        
                        <?php if(isset($_SESSION['signupErrors']) && !empty($_SESSION['signupErrors'])): ?>
                            <div class="message-container error">
                                <?php foreach($_SESSION['signupErrors'] as $error): ?>
                                    <div class="message"><?php echo htmlspecialchars($error); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php unset($_SESSION['signupErrors']); ?>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['message']) && !empty($_SESSION['message'])): ?>
                            <div class="message-container success">
                                <div class="message"><?php echo htmlspecialchars($_SESSION['message']); ?></div>
                            </div>
                            <?php unset($_SESSION['message']); ?>
                        <?php endif; ?>
                        
                        <input type="submit" value="Sign Up" class="submit-btn">
                        <button type="button" id="signin-link" class="text-link">Already have an account? Log in!</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Dark overlay -->
    <div id="overlay" style="display:none;"></div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="https://www.cuny.edu/about/administration/offices/cis/technology-services/brightspace/">BrightSpace</a>
                <a href="https://www.cuny.edu/about/administration/offices/cis/cunyfirst/">CUNYFirst</a>
                <a href="https://calendar.google.com/calendar/u/0/r?pli=1">Google Calendar</a>
                <a href="https://www.microsoft.com/en-us/microsoft-365/outlook/email-and-calendar-software-microsoft-outlook">Outlook</a>
            </div>
            <div class="footer-social">
                <a href="https://x.com/bmcc_cuny" target="_blank">
                    <ion-icon name="logo-twitter"></ion-icon>
                </a>
                <a href="https://www.instagram.com/bmcc_cuny/?hl=en" target="_blank">
                    <ion-icon name="logo-instagram"></ion-icon>
                </a>
                <a href="https://www.facebook.com/CUNY.BMCC/" target="_blank">
                    <ion-icon name="logo-facebook"></ion-icon>
                </a>
                <a href="https://www.bmcc.cuny.edu/" target="_blank">
                    <img src="images/BMCCLogo.png" alt="BMCC Logo" class="bmcc-logo">
                </a>
            </div>
        </div>
    </footer>

    <script src="script.js"></script>
</body>
</html>