<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: mainpage.php');
    exit();
}

require_once 'db_connect.php';
$dbc = connect_to_database();

// Check if user has an active mentorship
$student_id = $_SESSION['emplid'];
$mentorship_query = "
    SELECT m.*, mp.specialization, mp.years_experience, mp.meeting_preference, mp.bio,
           mr.id as relationship_id, mr.created_at, mr.last_meeting_date
    FROM mentor_relationships mr
    JOIN mentors m ON mr.mentor_id = m.id
    LEFT JOIN mentor_profiles mp ON m.id = mp.mentor_id
    WHERE mr.mentee_id = ? AND mr.status = 'active'
    LIMIT 1";

$stmt = $dbc->prepare($mentorship_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// If no active mentorship, redirect to mentoring.php
if ($result->num_rows === 0) {
    header('Location: mentoring.php');
    exit();
}

$mentor = $result->fetch_assoc();

// Handle scheduling form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_meeting'])) {
    $meeting_date = $_POST['meeting_date'];
    $meeting_time = $_POST['meeting_time'];
    $meeting_type = $_POST['meeting_type'];
    $meeting_topic = $_POST['meeting_topic'];
    
    $datetime = $meeting_date . ' ' . $meeting_time;
    
    // Insert meeting request - automatically approved for testing
    $stmt = $dbc->prepare("INSERT INTO mentor_meetings (relationship_id, meeting_datetime, meeting_type, topic, status) VALUES (?, ?, ?, ?, 'confirmed')");
    $stmt->bind_param("isss", $mentor['relationship_id'], $datetime, $meeting_type, $meeting_topic);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Meeting scheduled successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to schedule meeting.";
    }
    
    header('Location: mentee.php');
    exit();
}

// Get upcoming meetings
$meetings_query = "
    SELECT * FROM mentor_meetings 
    WHERE relationship_id = ? AND meeting_datetime > NOW()
    ORDER BY meeting_datetime ASC";

$stmt = $dbc->prepare($meetings_query);
$stmt->bind_param("i", $mentor['relationship_id']);
$stmt->execute();
$upcoming_meetings = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Mentor - WIC Portal</title>
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
        
        .mentee-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .mentor-profile {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .mentor-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e91e63, #9e4d69);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            margin-right: 30px;
        }
        
        .mentor-info h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .mentor-title {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .mentor-company {
            font-size: 18px;
            color: #888;
        }
        
        .mentor-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            color: #555;
        }
        
        .detail-item ion-icon {
            margin-right: 10px;
            color: #e91e63;
            font-size: 20px;
        }
        
        .mentor-bio {
            line-height: 1.6;
            color: #555;
            margin-bottom: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
        }
        
        .action-btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-btn.primary {
            background: #e91e63;
            color: white;
        }
        
        .action-btn.primary:hover {
            background: #d81b60;
        }
        
        .action-btn.secondary {
            background: #f5f5f5;
            color: #333;
            border: 2px solid #ddd;
        }
        
        .action-btn.secondary:hover {
            background: #eee;
        }
        
        .meetings-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        
        .meeting-list {
            display: grid;
            gap: 15px;
        }
        
        .meeting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            border: 1px solid #eee;
        }
        
        .meeting-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .meeting-date {
            font-weight: 600;
            color: #333;
        }
        
        .meeting-type {
            background: #e91e63;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .meeting-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .status-confirmed {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .no-meetings {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        /* Schedule Meeting Form */
        .schedule-form {
            display: none;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
        }
        
        .schedule-form.show {
            display: block;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #eee;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .submit-btn {
            background: #e91e63;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .submit-btn:hover {
            background: #d81b60;
        }
        
        .cancel-btn {
            background: #f5f5f5;
            color: #666;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .cancel-btn:hover {
            background: #eee;
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

    <!-- Main Content -->
    <div class="mentee-container">
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

        <!-- Mentor Profile Section -->
        <div class="mentor-profile">
            <div class="profile-header">
                <div class="mentor-avatar">
                    <?php echo strtoupper(substr($mentor['first_name'], 0, 1)); ?>
                </div>
                <div class="mentor-info">
                    <h1><?php echo htmlspecialchars($mentor['first_name'] . ' ' . $mentor['last_name']); ?></h1>
                    <div class="mentor-title"><?php echo htmlspecialchars($mentor['title']); ?></div>
                    <div class="mentor-company"><?php echo htmlspecialchars($mentor['company']); ?></div>
                </div>
            </div>
            
            <div class="mentor-details">
                <div class="detail-item">
                    <ion-icon name="mail-outline"></ion-icon>
                    <span><?php echo htmlspecialchars($mentor['email']); ?></span>
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
                    <span><?php echo ucfirst($mentor['meeting_preference']); ?> meetings</span>
                </div>
            </div>
            
            <?php if ($mentor['bio']): ?>
            <div class="mentor-bio">
                <?php echo nl2br(htmlspecialchars($mentor['bio'])); ?>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons">
                <button class="action-btn primary" onclick="toggleScheduleForm()">
                    <ion-icon name="calendar-outline"></ion-icon>
                    Schedule Meeting
                </button>
                <button class="action-btn secondary" onclick="window.location.href='mailto:<?php echo htmlspecialchars($mentor['email']); ?>'">
                    <ion-icon name="mail-outline"></ion-icon>
                    Send Email
                </button>
                <button class="action-btn secondary" onclick="showMessageForm()">
                    <ion-icon name="chatbubble-outline"></ion-icon>
                    Send Message
                </button>
            </div>
        </div>

        <!-- Schedule Meeting Form -->
        <div class="schedule-form" id="schedule-form">
            <h2 class="section-title">Schedule a Meeting</h2>
            
            <form method="POST" action="mentee.php">
                <input type="hidden" name="schedule_meeting" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="meeting_date">Date</label>
                        <input type="date" id="meeting_date" name="meeting_date" required 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="meeting_time">Time</label>
                        <input type="time" id="meeting_time" name="meeting_time" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="meeting_type">Meeting Type</label>
                        <select id="meeting_type" name="meeting_type" required>
                            <option value="">Select type</option>
                            <?php
                            $meeting_pref = $mentor['meeting_preference'];
                            if ($meeting_pref === 'virtual' || $meeting_pref === 'hybrid'): ?>
                                <option value="virtual">Virtual Meeting</option>
                            <?php endif; ?>
                            <?php if ($meeting_pref === 'in-person' || $meeting_pref === 'hybrid'): ?>
                                <option value="in-person">In-Person Meeting</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="meeting_topic">Meeting Topic</label>
                        <textarea id="meeting_topic" name="meeting_topic" required 
                                  placeholder="What would you like to discuss in this meeting?"></textarea>
                    </div>
                </div>
                
                <div>
                    <button type="submit" class="submit-btn">Send Request</button>
                    <button type="button" class="cancel-btn" onclick="toggleScheduleForm()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Upcoming Meetings Section -->
        <div class="meetings-section">
            <h2 class="section-title">Upcoming Meetings</h2>
            
            <?php if ($upcoming_meetings->num_rows > 0): ?>
                <div class="meeting-list">
                    <?php while ($meeting = $upcoming_meetings->fetch_assoc()): ?>
                        <div class="meeting-item">
                            <div class="meeting-info">
                                <div class="meeting-date">
                                    <?php echo date('M j, Y - g:i A', strtotime($meeting['meeting_datetime'])); ?>
                                </div>
                                <div class="meeting-type"><?php echo ucfirst($meeting['meeting_type']); ?></div>
                                <div><?php echo htmlspecialchars($meeting['topic']); ?></div>
                            </div>
                            <div class="meeting-status status-<?php echo $meeting['status']; ?>">
                                <?php echo ucfirst($meeting['status']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-meetings">
                    <p>No upcoming meetings scheduled.</p>
                    <p>Click "Schedule Meeting" to request a meeting with your mentor.</p>
                </div>
            <?php endif; ?>
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

        // Toggle schedule form
        function toggleScheduleForm() {
            const form = document.getElementById('schedule-form');
            form.classList.toggle('show');
            
            if (form.classList.contains('show')) {
                form.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Show message form (placeholder - you can implement this later)
        function showMessageForm() {
            alert('Message feature coming soon!');
        }
    </script>
</body>
</html>