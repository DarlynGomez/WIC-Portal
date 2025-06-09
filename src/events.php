<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: mainpage.php');
    exit();
}

require_once 'db_connect.php';
$dbc = connect_to_database();

// Handle RSVP actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user clicked RSVP or cancel RSVP button
    if (isset($_POST['rsvp_action'])) {
        // Get event id and student id for database update
        $event_id = $_POST['event_id'];
        $student_id = $_SESSION['emplid'];

        // Process RSVP registration
        if ($_POST['rsvp_action'] === 'register') {
            // Insert new RSVP or update existing one to registered enum status
            $stmt = $dbc->prepare("INSERT INTO event_rsvps (event_id, student_id, status) VALUES (?, ?, 'registered') 
                                  ON DUPLICATE KEY UPDATE status = 'registered', rsvp_date = CURRENT_TIMESTAMP");
            $stmt->bind_param("ii", $event_id, $student_id);
        } elseif ($_POST['rsvp_action'] === 'cancel') {
            // Cancel existing RSVP by updating status to cancelled enum value
            $stmt = $dbc->prepare("UPDATE event_rsvps SET status = 'cancelled' WHERE event_id = ? AND student_id = ?");
            $stmt->bind_param("ii", $event_id, $student_id);
        }
        
        // Execute the prepared statement and set success or error message
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "RSVP updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update RSVP.";
        }
        
        header('Location: events.php');
        exit();
    }
}

// Get filter criteria
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Get recommended events for the student
$student_id = $_SESSION['emplid'];

// First get the students major and semester for recommendations
$student_info_query = "SELECT major, semester FROM students WHERE emplid = ?";
$stmt = $dbc->prepare($student_info_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student_info = $stmt->get_result()->fetch_assoc();

// Get recommended events based on student pre-filled PROFILE
$recommended_query = "
    SELECT e.*, 
           -- Count how many tags match between event and student interests
           COUNT(DISTINCT etr.tag_id) as matching_tags,
           
           -- Boost relevance score based on students major and graduation year
           CASE 
               -- If student is in a computing related major prioritize tech events
               WHEN ? IN ('Computer Science', 'Computer Information Systems', 'Computer Network Technology')
                    AND EXISTS (SELECT 1 FROM event_tag_relationships etr2 
                              JOIN event_tags et2 ON etr2.tag_id = et2.id 
                              WHERE etr2.event_id = e.id 
                              AND et2.tag_name IN ('computer_science', 'programming', 'software_engineering'))
               THEN 2  -- Double the relevance score
               
               -- If student is graduating in 2025 prioritize career alumni events since it's important for them
               WHEN ? LIKE '%2025'
                    AND EXISTS (SELECT 1 FROM event_tag_relationships etr3 
                              JOIN event_tags et3 ON etr3.tag_id = et3.id 
                              WHERE etr3.event_id = e.id 
                              AND et3.tag_name IN ('graduates', 'alumni', 'career'))
               THEN 2  -- Double the relevance score
               
               -- Default relevance boost for all other cases
               ELSE 1
           END as relevance_boost,
           
           -- Get current attendee count for each event
           (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'registered') as attendee_count,
           
           -- Check if current user has already RSVPed to this event
           (SELECT status FROM event_rsvps WHERE event_id = e.id AND student_id = ? AND status = 'registered') as user_rsvp_status
    
    FROM events e
    LEFT JOIN event_tag_relationships etr ON e.id = etr.event_id
    LEFT JOIN event_tags et ON etr.tag_id = et.id
    
    -- Only show future events
    WHERE e.event_date > NOW()
    
    -- Exclude events the student has already registered for
    AND NOT EXISTS (
        SELECT 1 FROM event_rsvps er 
        WHERE er.event_id = e.id 
        AND er.student_id = ? 
        AND er.status = 'registered'
    )
    
    -- Group by event to count matching tags
    GROUP BY e.id
    
    -- Only include events that have at least one matching tag
    HAVING COUNT(DISTINCT etr.tag_id) > 0
    
    -- Sort by relevance score (matching tags * boost) then by date
    ORDER BY (COUNT(DISTINCT etr.tag_id) * relevance_boost) DESC, e.event_date ASC
    
    -- Limit to top 3 most relevant events
    LIMIT 3";

$stmt = $dbc->prepare($recommended_query);
$stmt->bind_param("ssii", 
    $student_info['major'], 
    $student_info['semester'], 
    $student_id, 
    $student_id
);
$stmt->execute();
$recommended_events = $stmt->get_result();

// Build the main query for all events
$query = "
    SELECT e.*, 
           GROUP_CONCAT(et.tag_name) as tags,
           (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'registered') as attendee_count,
           (SELECT status FROM event_rsvps WHERE event_id = e.id AND student_id = ? AND status = 'registered') as user_rsvp_status
    FROM events e
    LEFT JOIN event_tag_relationships etr ON e.id = etr.event_id
    LEFT JOIN event_tags et ON etr.tag_id = et.id
    WHERE e.event_date > NOW()";

$params = [$student_id];
$types = "i";

if ($filter_type) {
    $query .= " AND e.event_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($search_query) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR et.tag_name LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " GROUP BY e.id ORDER BY e.event_date ASC";

$stmt = $dbc->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$events_result = $stmt->get_result();

// Get event types for filter dropdown
$types_query = "SELECT DISTINCT event_type FROM events ORDER BY event_type";
$types_result = $dbc->query($types_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - WIC Portal</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="event_styles.css">

    <!-- google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    <!-- social media icons/links -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
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
                    <a href="events.php" class="active">
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

    <!-- Events content section -->
    <div class="events-container">
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
            <h1 class="page-title">Events</h1>
            <p class="page-subtitle">Discover workshops, panels, and networking opportunities</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="events.php" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label" for="type">Event Type</label>
                    <select id="type" name="type" class="filter-select">
                        <option value="">All Types</option>
                        <?php while ($type = $types_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($type['event_type']); ?>" 
                                    <?php echo $filter_type === $type['event_type'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $type['event_type'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label" for="search">Search</label>
                    <input type="text" id="search" name="search" class="filter-input" 
                           placeholder="Search events, tags, descriptions..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="filter-group" style="flex: 0; margin-top: 28px;">
                    <button type="submit" class="filter-button">Filter</button>
                    <?php if ($filter_type || $search_query): ?>
                        <a href="events.php" class="clear-filters" style="margin-left: 10px; text-decoration: none;">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Recommended Events Section -->
        <?php if ($recommended_events->num_rows > 0): ?>
        <div class="recommended-section">
            <h2 class="section-title">
                <ion-icon name="star-outline"></ion-icon>
                Recommended for You
            </h2>
            <div class="recommended-events-grid">
                <?php while ($event = $recommended_events->fetch_assoc()): ?>
                    <div class="event-card">
                        <div class="event-image">
                            <?php if ($event['image_url'] && file_exists($event['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($event['image_url']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php endif; ?>
                        </div>
                        <div class="event-content">
                            <span class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></span>
                            <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="event-description"><?php echo htmlspecialchars($event['short_description']); ?></p>
                            
                            <div class="event-meta">
                                <div class="event-meta-item">
                                    <ion-icon name="calendar-outline"></ion-icon>
                                    <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                </div>
                                <div class="event-meta-item">
                                    <ion-icon name="time-outline"></ion-icon>
                                    <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                                </div>
                                <?php if ($event['location']): ?>
                                <div class="event-meta-item">
                                    <ion-icon name="location-outline"></ion-icon>
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-footer">
                                <span class="attendee-count">
                                    <?php echo $event['attendee_count']; ?> attending
                                    <?php if ($event['capacity']): ?>
                                        / <?php echo $event['capacity']; ?> capacity
                                    <?php endif; ?>
                                </span>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <?php if ($event['user_rsvp_status'] === 'registered'): ?>
                                        <button type="submit" name="rsvp_action" value="cancel" class="rsvp-button registered">Cancel RSVP</button>
                                    <?php else: ?>
                                        <button type="submit" name="rsvp_action" value="register" class="rsvp-button">RSVP</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Events Section -->
        <div class="all-events-section">
            <h2 class="section-title">
                <ion-icon name="calendar-outline"></ion-icon>
                All Upcoming Events
            </h2>
            
            <?php if ($events_result->num_rows > 0): ?>
                <div class="events-grid">
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                        <div class="event-card-horizontal">
                            <div class="event-image-horizontal">
                                <?php if ($event['image_url'] && file_exists($event['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($event['image_url']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            <div class="event-content-horizontal">
                                <div>
                                    <span class="event-type"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                    <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                                    
                                    <div class="event-meta">
                                        <div class="event-meta-item">
                                            <ion-icon name="calendar-outline"></ion-icon>
                                            <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        <div class="event-meta-item">
                                            <ion-icon name="time-outline"></ion-icon>
                                            <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                                        </div>
                                        <?php if ($event['location']): ?>
                                        <div class="event-meta-item">
                                            <ion-icon name="location-outline"></ion-icon>
                                            <?php echo htmlspecialchars($event['location']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($event['tags']): ?>
                                    <div class="event-tags">
                                        <?php 
                                        $tags = explode(',', $event['tags']);
                                        foreach ($tags as $tag): 
                                        ?>
                                            <span class="event-tag">#<?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-footer">
                                    <span class="attendee-count">
                                        <?php echo $event['attendee_count']; ?> attending
                                        <?php if ($event['capacity']): ?>
                                            / <?php echo $event['capacity']; ?> capacity
                                        <?php endif; ?>
                                    </span>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <?php if ($event['user_rsvp_status'] === 'registered'): ?>
                                            <button type="submit" name="rsvp_action" value="cancel" class="rsvp-button registered">Cancel RSVP</button>
                                        <?php else: ?>
                                            <button type="submit" name="rsvp_action" value="register" class="rsvp-button">RSVP</button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-events">
                    <p>No events found matching your criteria.</p>
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
    </script>
</body>
</html>