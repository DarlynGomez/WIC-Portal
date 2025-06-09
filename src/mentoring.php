<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: mainpage.php');
    exit();
}

require_once 'db_connect.php';
$dbc = connect_to_database();

// Check if user already has mentoring preferences or an active mentorship
$student_id = $_SESSION['emplid'];
$check_preferences = $dbc->prepare("SELECT * FROM mentoring_preferences WHERE student_id = ?");
$check_preferences->bind_param("i", $student_id);
$check_preferences->execute();
$preferences_result = $check_preferences->get_result();
$has_preferences = $preferences_result->num_rows > 0;

// Check if user has an active mentorship
$check_mentorship = $dbc->prepare("SELECT * FROM mentor_relationships WHERE mentee_id = ? AND status = 'active'");
$check_mentorship->bind_param("i", $student_id);
$check_mentorship->execute();
$mentorship_result = $check_mentorship->get_result();
$has_active_mentorship = $mentorship_result->num_rows > 0;

// If user has an active mentorship, redirect to mentee.php
if ($has_active_mentorship) {
    header('Location: mentee.php');
    exit();
}

// Handle form submission for preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_preferences'])) {
    $age_group = $_POST['age_group'];
    $industry = $_POST['industry'];
    $field = $_POST['field'];
    $meeting_preference = $_POST['meeting_preference'];
    $goals = $_POST['goals'];
    
    if ($has_preferences) {
        // Update existing preferences
        $stmt = $dbc->prepare("UPDATE mentoring_preferences SET age_group = ?, industry = ?, field = ?, meeting_preference = ?, goals = ? WHERE student_id = ?");
        $stmt->bind_param("sssssi", $age_group, $industry, $field, $meeting_preference, $goals, $student_id);
    } else {
        // Insert new preferences
        $stmt = $dbc->prepare("INSERT INTO mentoring_preferences (student_id, age_group, industry, field, meeting_preference, goals) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $student_id, $age_group, $industry, $field, $meeting_preference, $goals);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Preferences saved successfully!";
        header('Location: mentoring.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to save preferences.";
    }
}

// Handle mentor selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_mentor'])) {
    $mentor_id = $_POST['mentor_id'];
    
    // Create mentorship relationship
    $stmt = $dbc->prepare("INSERT INTO mentor_relationships (mentor_id, mentee_id, status, created_at) VALUES (?, ?, 'active', NOW())");
    $stmt->bind_param("ii", $mentor_id, $student_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Mentor selected successfully!";
        header('Location: mentee.php');
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to select mentor.";
    }
}

// Get user's preferences if they exist
$user_preferences = null;
if ($has_preferences) {
    $user_preferences = $preferences_result->fetch_assoc();
    
    // Get recommended mentors based on PREFERENVES from the form prior
    $mentor_query = "
        SELECT m.id, m.first_name, m.last_name, m.email, m.title, m.company, 
            mp.specialization, mp.years_experience, mp.meeting_preference as mentor_meeting_pref,
            mp.industry, mp.field,
            -- Count active mentees to ensure mentors arent overloaded 
            COUNT(mr.id) as current_mentees
        FROM mentors m
        LEFT JOIN mentor_profiles mp ON m.id = mp.mentor_id
        LEFT JOIN mentor_relationships mr ON m.id = mr.mentor_id AND mr.status = 'active'
        -- Main matching is finding mentors whose industry OR field matches student preferences
        WHERE mp.industry = ? OR mp.field = ?
        GROUP BY m.id, m.first_name, m.last_name, m.email, m.title, m.company,
                mp.specialization, mp.years_experience, mp.meeting_preference,
                mp.industry, mp.field
        -- Limit to mentors with less than 5 active mentees to ensure mentors to mentee is a quality relationship
        HAVING current_mentees < 5
        -- Ranking system for recommendation determined by calculating match score based on how many preferences align
        ORDER BY 
            -- Give 2 points if mentors industry matches students industry preference
            CASE WHEN mp.industry = ? THEN 2 ELSE 0 END +
            -- Give 2 points if mentors field matches students field preference
            CASE WHEN mp.field = ? THEN 2 ELSE 0 END DESC
        -- Show top 6 matching mentors
        LIMIT 6";

    // Bind the parameters
    $stmt = $dbc->prepare($mentor_query);
    $stmt->bind_param("ssss", 
        $user_preferences['industry'],     // For WHERE clause industry match
        $user_preferences['field'],        // For WHERE clause field match
        $user_preferences['industry'],     // For ORDER BY industry scoring
        $user_preferences['field']         // For ORDER BY field scoring
    );
    $stmt->execute();
    $recommended_mentors = $stmt->get_result();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentoring - WIC Portal</title>
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
        
        .mentoring-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: 42px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            font-size: 18px;
            color: #666;
        }
        
        .preferences-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 30px;
            color: #333;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
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
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #eee;
            border-radius: 10px;
            font-size: 16px;
            font-family: 'Open Sans', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e91e63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .submit-btn {
            background: #e91e63;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .submit-btn:hover {
            background: #d81b60;
            transform: translateY(-2px);
        }
        
        .mentors-section {
            margin-top: 60px;
        }
        
        .mentors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .mentor-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .mentor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0, 0, 0, 0.15);
        }
        
        .mentor-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .mentor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e91e63, #9e4d69);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            margin-right: 20px;
        }
        
        .mentor-info h3 {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .mentor-info p {
            color: #666;
            font-size: 16px;
        }
        
        .mentor-details {
            margin-bottom: 20px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: #555;
        }
        
        .detail-item ion-icon {
            margin-right: 10px;
            color: #e91e63;
        }
        
        .select-mentor-btn {
            width: 100%;
            background: #e91e63;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .select-mentor-btn:hover {
            background: #d81b60;
        }
        
        .message {
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #f44336;
        }
        
        /* Agreement popup */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            max-width: 500px;
            width: 90%;
        }
        
        .modal h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .modal p {
            margin-bottom: 30px;
            color: #555;
            line-height: 1.6;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .modal-btn {
            padding: 10px 25px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }
        
        .modal-btn.cancel {
            background: #f5f5f5;
            color: #666;
        }
        
        .modal-btn.confirm {
            background: #e91e63;
            color: white;
        }
        
        .modal-btn:hover {
            transform: translateY(-2px);
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

    <!-- Navigation sidebar -->
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
                    <a href="mentoring.php" class="active">
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

    <!-- Main content of mentoring page -->
    <div class="mentoring-container">
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

        <div class="page-header">
            <h1 class="page-title">Find Your Mentor</h1>
            <p class="page-subtitle">Connect with experienced professionals in your field</p>
        </div>

        <!-- Preferences Form -->
        <div class="preferences-section">
            <h2 class="section-title"><?php echo $has_preferences ? 'Update Your' : 'Set Your'; ?> Mentoring Preferences</h2>
            
            <form method="POST" action="mentoring.php">
                <input type="hidden" name="save_preferences" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="age_group">Preferred Mentor Age Group</label>
                        <select id="age_group" name="age_group" required>
                            <option value="">Select age group</option>
                            <option value="20-30" <?php echo ($user_preferences && $user_preferences['age_group'] === '20-30') ? 'selected' : ''; ?>>20-30 years</option>
                            <option value="30-40" <?php echo ($user_preferences && $user_preferences['age_group'] === '30-40') ? 'selected' : ''; ?>>30-40 years</option>
                            <option value="40-50" <?php echo ($user_preferences && $user_preferences['age_group'] === '40-50') ? 'selected' : ''; ?>>40-50 years</option>
                            <option value="50+" <?php echo ($user_preferences && $user_preferences['age_group'] === '50+') ? 'selected' : ''; ?>>50+ years</option>
                            <option value="any" <?php echo ($user_preferences && $user_preferences['age_group'] === 'any') ? 'selected' : ''; ?>>No preference</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_preference">Meeting Preference</label>
                        <select id="meeting_preference" name="meeting_preference" required>
                            <option value="">Select preference</option>
                            <option value="virtual" <?php echo ($user_preferences && $user_preferences['meeting_preference'] === 'virtual') ? 'selected' : ''; ?>>Virtual only</option>
                            <option value="in-person" <?php echo ($user_preferences && $user_preferences['meeting_preference'] === 'in-person') ? 'selected' : ''; ?>>In-person only</option>
                            <option value="hybrid" <?php echo ($user_preferences && $user_preferences['meeting_preference'] === 'hybrid') ? 'selected' : ''; ?>>Both virtual and in-person</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="industry">Industry Interest</label>
                        <select id="industry" name="industry" required>
                            <option value="">Select industry</option>
                            <option value="software" <?php echo ($user_preferences && $user_preferences['industry'] === 'software') ? 'selected' : ''; ?>>Software Development</option>
                            <option value="data" <?php echo ($user_preferences && $user_preferences['industry'] === 'data') ? 'selected' : ''; ?>>Data Science</option>
                            <option value="cybersecurity" <?php echo ($user_preferences && $user_preferences['industry'] === 'cybersecurity') ? 'selected' : ''; ?>>Cybersecurity</option>
                            <option value="ai" <?php echo ($user_preferences && $user_preferences['industry'] === 'ai') ? 'selected' : ''; ?>>Artificial Intelligence</option>
                            <option value="web" <?php echo ($user_preferences && $user_preferences['industry'] === 'web') ? 'selected' : ''; ?>>Web Development</option>
                            <option value="mobile" <?php echo ($user_preferences && $user_preferences['industry'] === 'mobile') ? 'selected' : ''; ?>>Mobile Development</option>
                            <option value="cloud" <?php echo ($user_preferences && $user_preferences['industry'] === 'cloud') ? 'selected' : ''; ?>>Cloud Computing</option>
                            <option value="other" <?php echo ($user_preferences && $user_preferences['industry'] === 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="field">Desired Future Field</label>
                        <select id="field" name="field" required>
                            <option value="">Select field</option>
                            <option value="frontend" <?php echo ($user_preferences && $user_preferences['field'] === 'frontend') ? 'selected' : ''; ?>>Frontend Development</option>
                            <option value="backend" <?php echo ($user_preferences && $user_preferences['field'] === 'backend') ? 'selected' : ''; ?>>Backend Development</option>
                            <option value="fullstack" <?php echo ($user_preferences && $user_preferences['field'] === 'fullstack') ? 'selected' : ''; ?>>Full Stack Development</option>
                            <option value="data-analysis" <?php echo ($user_preferences && $user_preferences['field'] === 'data-analysis') ? 'selected' : ''; ?>>Data Analysis</option>
                            <option value="machine-learning" <?php echo ($user_preferences && $user_preferences['field'] === 'machine-learning') ? 'selected' : ''; ?>>Machine Learning</option>
                            <option value="devops" <?php echo ($user_preferences && $user_preferences['field'] === 'devops') ? 'selected' : ''; ?>>DevOps</option>
                            <option value="product" <?php echo ($user_preferences && $user_preferences['field'] === 'product') ? 'selected' : ''; ?>>Product Management</option>
                            <option value="research" <?php echo ($user_preferences && $user_preferences['field'] === 'research') ? 'selected' : ''; ?>>Research</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="goals">What are your goals for mentorship?</label>
                        <textarea id="goals" name="goals" required placeholder="Describe what you hope to achieve through mentorship..."><?php echo $user_preferences ? htmlspecialchars($user_preferences['goals']) : ''; ?></textarea>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">Save Preferences</button>
            </form>
        </div>

        <!-- Recommended mentors -->
        <?php if ($has_preferences && isset($recommended_mentors) && $recommended_mentors->num_rows > 0): ?>
        <div class="mentors-section">
            <h2 class="section-title">Recommended Mentors</h2>
            
            <div class="mentors-grid">
                <?php while ($mentor = $recommended_mentors->fetch_assoc()): ?>
                <div class="mentor-card">
                    <div class="mentor-header">
                        <div class="mentor-avatar">
                            <?php echo strtoupper(substr($mentor['first_name'], 0, 1)); ?>
                        </div>
                        <div class="mentor-info">
                            <h3><?php echo htmlspecialchars($mentor['first_name'] . ' ' . $mentor['last_name']); ?></h3>
                            <p><?php echo htmlspecialchars($mentor['title']); ?></p>
                        </div>
                    </div>
                    
                    <div class="mentor-details">
                        <div class="detail-item">
                            <ion-icon name="business-outline"></ion-icon>
                            <span><?php echo htmlspecialchars($mentor['company']); ?></span>
                        </div>
                        <div class="detail-item">
                            <ion-icon name="briefcase-outline"></ion-icon>
                            <span><?php echo htmlspecialchars($mentor['specialization']); ?></span>
                        </div>
                        <div class="detail-item">
                            <ion-icon name="time-outline"></ion-icon>
                            <span><?php echo $mentor['years_experience']; ?> years experience</span>
                        </div>
                        <div class="detail-item">
                            <ion-icon name="videocam-outline"></ion-icon>
                            <span><?php echo ucfirst($mentor['mentor_meeting_pref']); ?> meetings</span>
                        </div>
                    </div>
                    
                    <form method="POST" action="mentoring.php" onsubmit="return confirmMentorSelection(this);">
                        <input type="hidden" name="select_mentor" value="1">
                        <input type="hidden" name="mentor_id" value="<?php echo $mentor['id']; ?>">
                        <button type="submit" class="select-mentor-btn">Select Mentor</button>
                    </form>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Agreement Pop up -->
    <div class="modal-overlay" id="modal-overlay"></div>
    <div class="modal" id="agreement-modal">
        <h3>Mentorship Agreement</h3>
        <p>By selecting this mentor, you agree to:</p>
        <ul style="margin-bottom: 20px; padding-left: 20px;">
            <li>Attend scheduled meetings punctually</li>
            <li>Prepare for meetings with specific questions or topics</li>
            <li>Respect your mentor's time and expertise</li>
            <li>Maintain professional communication</li>
            <li>Provide feedback on the mentorship experience</li>
        </ul>
        <p>Do you wish to proceed with this mentor selection?</p>
        <div class="modal-buttons">
            <button type="button" class="modal-btn cancel" onclick="closeModal()">Cancel</button>
            <button type="button" class="modal-btn confirm" id="confirm-btn">Yes, Proceed</button>
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

        // Modal functionality
        let currentForm = null;

        function confirmMentorSelection(form) {
            currentForm = form;
            document.getElementById('modal-overlay').style.display = 'block';
            document.getElementById('agreement-modal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            return false; // Prevent form submission
        }

        function closeModal() {
            document.getElementById('modal-overlay').style.display = 'none';
            document.getElementById('agreement-modal').style.display = 'none';
            document.body.style.overflow = 'auto';
            currentForm = null;
        }

        document.getElementById('confirm-btn').addEventListener('click', function() {
            if (currentForm) {
                currentForm.submit();
            }
        });

        document.getElementById('modal-overlay').addEventListener('click', closeModal);
    </script>
</body>
</html>