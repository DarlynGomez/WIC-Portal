<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: mainpage.php');
    exit();
}

require_once 'db_connect.php';
$dbc = connect_to_database();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $emplid = $_SESSION['emplid'];
    
    // Update basic info
    if (isset($_POST['update_basic'])) {
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $new_password = $_POST['new_password'];
        
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $dbc->prepare("UPDATE students SET first_name = ?, last_name = ?, password = ? WHERE emplid = ?");
            $stmt->bind_param("sssi", $first_name, $last_name, $hashed_password, $emplid);
        } else {
            $stmt = $dbc->prepare("UPDATE students SET first_name = ?, last_name = ? WHERE emplid = ?");
            $stmt->bind_param("ssi", $first_name, $last_name, $emplid);
        }
        
        if ($stmt->execute()) {
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['success_message'] = "Basic information updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update information.";
        }
    }
    
    // Update academic info
    if (isset($_POST['update_academic'])) {
        $major = $_POST['major'];
        $semester = $_POST['semester'];
        
        $stmt = $dbc->prepare("UPDATE students SET major = ?, semester = ? WHERE emplid = ?");
        $stmt->bind_param("ssi", $major, $semester, $emplid);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Academic information updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update academic information.";
        }
    }
    
    // Add course (handles AJAX request)
    if (isset($_POST['add_course_ajax'])) {
        $course_id = $_POST['course_id'];
        $course_code = $_POST['course_code'];
        
        // Check if course already exists
        $check_stmt = $dbc->prepare("SELECT id FROM student_courses WHERE student_id = ? AND course_id = ? AND status = 'current'");
        $check_stmt->bind_param("ii", $emplid, $course_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows == 0) {
            $insert_stmt = $dbc->prepare("INSERT INTO student_courses (student_id, course_id, status) VALUES (?, ?, 'current')");
            $insert_stmt->bind_param("ii", $emplid, $course_id);
            
            if ($insert_stmt->execute()) {
                // Get full course info to return
                $course_stmt = $dbc->prepare("SELECT course_name FROM courses WHERE id = ?");
                $course_stmt->bind_param("i", $course_id);
                $course_stmt->execute();
                $course_result = $course_stmt->get_result();
                $course = $course_result->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'course_id' => $course_id,
                    'course_code' => $course_code,
                    'course_name' => $course['course_name']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add course.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Course already added.']);
        }
        exit();
    }
    
    // Remove course
    if (isset($_POST['remove_course'])) {
        $course_id = $_POST['course_id'];
        
        $stmt = $dbc->prepare("UPDATE student_courses SET status = 'dropped' WHERE student_id = ? AND course_id = ? AND status = 'current'");
        $stmt->bind_param("ii", $emplid, $course_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Course removed successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to remove course.";
        }
        
        header('Location: profile.php');
        exit();
    }
    
    // Handle other form submissions and redirect
    if (isset($_POST['update_basic']) || isset($_POST['update_academic'])) {
        header('Location: profile.php');
        exit();
    }
}

// Get user data
$emplid = $_SESSION['emplid'];
$stmt = $dbc->prepare("SELECT * FROM students WHERE emplid = ?");
$stmt->bind_param("i", $emplid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get majors for dropdown
$majors_result = $dbc->query("SELECT * FROM majors ORDER BY major_name");

// Get semesters for dropdown
$semesters_result = $dbc->query("SELECT id, CONCAT(semester_name, ' ', year) as semester_display FROM semesters ORDER BY year DESC, FIELD(semester_name, 'Spring', 'Summer', 'Fall')");

// Get user's current courses
$courses_stmt = $dbc->prepare("SELECT c.id, c.course_code, c.course_name 
                               FROM student_courses sc
                               JOIN courses c ON sc.course_id = c.id
                               WHERE sc.student_id = ? AND sc.status = 'current'
                               ORDER BY c.course_code");
$courses_stmt->bind_param("i", $emplid);
$courses_stmt->execute();
$user_courses = $courses_stmt->get_result();

// Get all courses for autocomplete
$all_courses_result = $dbc->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code");
$courses_json = [];
while ($course = $all_courses_result->fetch_assoc()) {
    $courses_json[] = [
        'id' => $course['id'],
        'code' => $course['course_code'],
        'name' => $course['course_name'],
        'display' => $course['course_code'] . ': ' . $course['course_name']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - WIC Portal</title>
    <link rel="stylesheet" href="styles.css">
    
    <!-- google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <!-- social media icons/links -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <style>
        body {
            background-color: #f5f5f5;
            background-image: none;
        }
        
        .back-button {
            position: fixed;
            top: 110px;
            left: 40px;
            background: white;
            border: 2px solid #333;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            z-index: 100;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .back-button ion-icon {
            font-size: 24px;
            color: #333;
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .profile-header {
            background: white;
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: linear-gradient(45deg, #e91e63, #9e4d69);
            border-radius: 50%;
            transform: translate(50%, -50%);
            opacity: 0.1;
        }
        
        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e91e63, #9e4d69);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .profile-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }
        
        .profile-name {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        
        .profile-details {
            color: #666;
            font-size: 18px;
            line-height: 1.6;
        }
        
        .profile-details p {
            margin: 5px 0;
        }
        
        .profile-content {
            display: grid;
            gap: 30px;
        }
        
        .profile-section {
            background: white;
            border-radius: 25px;
            padding: 35px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #333;
        }
        
        .section-title ion-icon {
            color: #e91e63;
            font-size: 32px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #444;
            font-size: 16px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Open Sans', sans-serif;
            transition: all 0.3s ease;
            background: #f9f9f9;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e91e63;
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.1);
            background: white;
        }
        
        .form-group input[readonly] {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        .save-button {
            background: linear-gradient(45deg, #e91e63, #d81b60);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(233, 30, 99, 0.3);
        }
        
        .save-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(233, 30, 99, 0.4);
        }
        
        /* Course Management Styles */
        .course-input-container {
            position: relative;
            margin-bottom: 30px;
        }
        
        .course-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Open Sans', sans-serif;
            transition: all 0.3s ease;
            background: #f9f9f9;
        }
        
        .course-input:focus {
            outline: none;
            border-color: #e91e63;
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.1);
            background: white;
        }
        
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e91e63;
            border-top: none;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .autocomplete-item {
            padding: 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover,
        .autocomplete-item.selected {
            background-color: #fce4ec;
        }
        
        .autocomplete-item strong {
            color: #e91e63;
        }
        
        .course-list {
            display: grid;
            gap: 15px;
        }
        
        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .course-item:hover {
            background: #f5f5f5;
            transform: translateX(5px);
        }
        
        .course-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .course-code {
            background: #e91e63;
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .course-name {
            color: #555;
            font-size: 16px;
        }
        
        .remove-btn {
            background: none;
            border: none;
            color: #e91e63;
            cursor: pointer;
            font-size: 24px;
            padding: 5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove-btn:hover {
            color: #d81b60;
            transform: scale(1.1);
        }
        
        .message {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 2px solid #4caf50;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            border: 2px solid #f44336;
        }
        
        .profile-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e91e63;
            color: white;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            margin-left: 20px;
        }
        
        .profile-button:hover {
            background: #d81b60;
        }
    </style>
</head>
<body>
    <!-- Back Button -->
    <div class="back-button" onclick="window.location.href='dashboard.php'">
        <ion-icon name="arrow-back-outline"></ion-icon>
    </div>

    <!-- Top bar with log out -->
    <div id="sign-in-top">
        <button onclick="window.location.href='authentication/logout.php'" class="logout-btn" style="position: absolute; right: 100px; color: black; text-decoration: none; font-size: 12px; border: 1.5px solid black; padding: 0px 18px; border-radius: 4px; height: 20px; line-height: 20px; background: none; cursor: pointer;">log out</button>
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
                <button class="profile-button" onclick="window.location.href='profile.php'">
                    <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                </button>
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
                    <a href="profile.php" class="active">
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

    <!-- Profile Content -->
    <div class="profile-container">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="message success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="message error">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
                <div class="profile-details">
                    <p><strong>EMPLID:</strong> <?php echo htmlspecialchars($user['emplid']); ?></p>
                    <?php if ($user['major']): ?>
                        <p><strong>Major:</strong> <?php echo htmlspecialchars($user['major']); ?></p>
                    <?php endif; ?>
                    <?php if ($user['semester']): ?>
                        <p><strong>Expected Graduation:</strong> <?php echo htmlspecialchars($user['semester']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <!-- Basic Information -->
            <div class="profile-section">
                <h2 class="section-title">
                    <ion-icon name="person-outline"></ion-icon>
                    Basic Information
                </h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_basic" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current" minlength="8">
                        </div>
                    </div>
                    <button type="submit" class="save-button">Save Changes</button>
                </form>
            </div>

            <!-- Academic Information -->
            <div class="profile-section">
                <h2 class="section-title">
                    <ion-icon name="school-outline"></ion-icon>
                    Academic Information
                </h2>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="update_academic" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="major">Major</label>
                            <select id="major" name="major">
                                <option value="">Select your major</option>
                                <?php while ($major = $majors_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($major['major_name']); ?>" 
                                        <?php echo ($user['major'] == $major['major_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($major['major_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="semester">Expected Graduation</label>
                            <select id="semester" name="semester">
                                <option value="">Select graduation semester</option>
                                <?php while ($semester = $semesters_result->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($semester['semester_display']); ?>"
                                        <?php echo ($user['semester'] == $semester['semester_display']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($semester['semester_display']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="save-button">Save Changes</button>
                </form>
            </div>

            <!-- Course Management -->
            <div class="profile-section">
                <h2 class="section-title">
                    <ion-icon name="book-outline"></ion-icon>
                    Current Courses
                </h2>
                
                <!-- Course Autocomplete Input -->
                <div class="course-input-container">
                    <input type="text" 
                           class="course-input" 
                           id="course-search" 
                           placeholder="Start typing course code or name (e.g., CSC, Data Structures)..." 
                           autocomplete="off">
                    <div class="autocomplete-results" id="course-results"></div>
                </div>
                
                <!-- Current Courses List -->
                <div class="course-list" id="course-list">
                    <?php while ($course = $user_courses->fetch_assoc()): ?>
                        <div class="course-item" data-course-id="<?php echo $course['id']; ?>">
                            <div class="course-info">
                                <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                <span class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></span>
                            </div>
                            <form method="POST" action="profile.php" style="display: inline;">
                                <input type="hidden" name="remove_course" value="1">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" class="remove-btn" onclick="return confirm('Are you sure you want to remove this course?')">
                                    <ion-icon name="close-circle-outline"></ion-icon>
                                </button>
                            </form>
                        </div>
                    <?php endwhile; ?>
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

    <script>
        // Navigation functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburger-menu');
            const navSidebar = document.getElementById('nav-sidebar');
            const closeNavBtn = document.querySelector('.close-nav-btn');
            const overlay = document.getElementById('overlay');

            function openNavSidebar() {
                navSidebar.classList.add('show');
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }

            function closeNavSidebar() {
                navSidebar.classList.remove('show');
                overlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            }

            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', openNavSidebar);
            }

            if (closeNavBtn) {
                closeNavBtn.addEventListener('click', closeNavSidebar);
            }

            if (overlay) {
                overlay.addEventListener('click', closeNavSidebar);
            }
        });

        // Course autocomplete functionality
        const courses = <?php echo json_encode($courses_json); ?>;
        const courseSearch = document.getElementById('course-search');
        const courseResults = document.getElementById('course-results');
        const courseList = document.getElementById('course-list');
        let selectedIndex = -1;

        courseSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            if (searchTerm.length < 1) {
                courseResults.style.display = 'none';
                return;
            }

            const filtered = courses.filter(course => 
                course.code.toLowerCase().includes(searchTerm) || 
                course.name.toLowerCase().includes(searchTerm)
            );

            if (filtered.length > 0) {
                displayResults(filtered);
            } else {
                courseResults.style.display = 'none';
            }
        });

        function displayResults(results) {
            courseResults.innerHTML = '';
            results.forEach((course, index) => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = `<strong>${course.code}</strong>: ${course.name}`;
                div.addEventListener('click', () => selectCourse(course));
                div.addEventListener('mouseover', () => {
                    selectedIndex = index;
                    updateSelectedItem();
                });
                courseResults.appendChild(div);
            });
            courseResults.style.display = 'block';
            selectedIndex = -1;
        }

        function selectCourse(course) {
            // Add course via AJAX
            const formData = new FormData();
            formData.append('add_course_ajax', '1');
            formData.append('course_id', course.id);
            formData.append('course_code', course.code);

            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add course to list
                    const courseItem = document.createElement('div');
                    courseItem.className = 'course-item';
                    courseItem.dataset.courseId = data.course_id;
                    courseItem.innerHTML = `
                        <div class="course-info">
                            <span class="course-code">${data.course_code}</span>
                            <span class="course-name">${data.course_name}</span>
                        </div>
                        <form method="POST" action="profile.php" style="display: inline;">
                            <input type="hidden" name="remove_course" value="1">
                            <input type="hidden" name="course_id" value="${data.course_id}">
                            <button type="submit" class="remove-btn" onclick="return confirm('Are you sure you want to remove this course?')">
                                <ion-icon name="close-circle-outline"></ion-icon>
                            </button>
                        </form>
                    `;
                    courseList.appendChild(courseItem);
                    
                    // Clear search
                    courseSearch.value = '';
                    courseResults.style.display = 'none';
                    
                    // Show success message
                    showMessage('success', 'Course added successfully!');
                } else {
                    showMessage('error', data.message || 'Failed to add course');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Failed to add course');
            });
        }

        function showMessage(type, text) {
            // Create message div if it doesn't exist
            let messageDiv = document.querySelector(`.message.${type}`);
            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.className = `message ${type}`;
                document.querySelector('.profile-container').insertBefore(messageDiv, document.querySelector('.profile-header'));
            }
            
            messageDiv.textContent = text;
            messageDiv.style.display = 'block';
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 3000);
        }

        // Keyboard navigation for autocomplete
        courseSearch.addEventListener('keydown', function(e) {
            const items = courseResults.getElementsByClassName('autocomplete-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelectedItem();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, -1);
                updateSelectedItem();
            } else if (e.key === 'Enter' && selectedIndex > -1) {
                e.preventDefault();
                const filtered = courses.filter(course => 
                    course.code.toLowerCase().includes(courseSearch.value.toLowerCase()) || 
                    course.name.toLowerCase().includes(courseSearch.value.toLowerCase())
                );
                if (filtered[selectedIndex]) {
                    selectCourse(filtered[selectedIndex]);
                }
            } else if (e.key === 'Escape') {
                courseResults.style.display = 'none';
                selectedIndex = -1;
            }
        });

        function updateSelectedItem() {
            const items = courseResults.getElementsByClassName('autocomplete-item');
            Array.from(items).forEach((item, index) => {
                item.classList.toggle('selected', index === selectedIndex);
            });
        }

        // Close autocomplete when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.course-input-container')) {
                courseResults.style.display = 'none';
                selectedIndex = -1;
            }
        });
    </script>
</body>
</html>