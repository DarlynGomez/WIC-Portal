<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: mainpage.php');
    exit();
}

require_once 'db_connect.php';
$dbc = connect_to_database();

// Handle RSVP actions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_rsvp'])) {
    $event_id = $_POST['event_id'];
    $student_id = $_SESSION['emplid'];
    $action = $_POST['action'];
    
    if ($action === 'cancel') {
        $stmt = $dbc->prepare("UPDATE event_rsvps SET status = 'cancelled' WHERE event_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $event_id, $student_id);
    } else {
        $stmt = $dbc->prepare("INSERT INTO event_rsvps (event_id, student_id, status) VALUES (?, ?, 'registered') 
                              ON DUPLICATE KEY UPDATE status = 'registered', rsvp_date = CURRENT_TIMESTAMP");
        $stmt->bind_param("ii", $event_id, $student_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $dbc->error]);
    }
    exit();
}

// Get event details (AJAX)
if (isset($_GET['get_event_details'])) {
    $event_id = $_GET['event_id'];
    $student_id = $_SESSION['emplid'];
    
    $stmt = $dbc->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'registered') as attendee_count,
               (SELECT status FROM event_rsvps WHERE event_id = e.id AND student_id = ? AND status = 'registered') as user_rsvp_status,
               GROUP_CONCAT(et.tag_name) as tags
        FROM events e
        LEFT JOIN event_tag_relationships etr ON e.id = etr.event_id
        LEFT JOIN event_tags et ON etr.tag_id = et.id
        WHERE e.id = ?
        GROUP BY e.id
    ");
    
    $stmt->bind_param("ii", $student_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if ($event) {
        echo json_encode($event);
    } else {
        echo json_encode(['error' => 'Event not found']);
    }
    exit();
}

// Get user's upcoming events (no limit for dashboard)
$student_id = $_SESSION['emplid'];
$upcoming_events_query = "
    SELECT e.id, e.title, e.event_date, e.location, er.status
    FROM events e
    JOIN event_rsvps er ON e.id = er.event_id
    WHERE er.student_id = ? AND er.status = 'registered' AND e.event_date > NOW()
    ORDER BY e.event_date ASC";

$stmt = $dbc->prepare($upcoming_events_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$upcoming_events = $stmt->get_result();

// Handle notification actions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_action'])) {
    $notification_id = $_POST['notification_id'];
    $action = $_POST['action'];
    $student_id = $_SESSION['emplid'];
    
    if ($action === 'mark_read') {
        // For now, keep mark as read in session
        if (!isset($_SESSION['read_notifications'])) {
            $_SESSION['read_notifications'] = [];
        }
        $_SESSION['read_notifications'][] = $notification_id;
    } elseif ($action === 'dismiss') {
        // Store dismissed notifications in database
        $stmt = $dbc->prepare("INSERT IGNORE INTO dismissed_notifications (student_id, notification_id) VALUES (?, ?)");
        $stmt->bind_param("is", $student_id, $notification_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
    exit();
}

// Get recent notifications for the user
$notifications = [];

// Get upcoming mentor meetings
$mentor_meetings_query = "
    SELECT 
        mm.id as meeting_id,
        CONCAT('Mentor Session with ', m.first_name, ' ', m.last_name) as title,
        CONCAT('Your session starts at ', DATE_FORMAT(mm.meeting_datetime, '%h:%i %p')) as subtitle,
        TIMESTAMPDIFF(MINUTE, NOW(), mm.meeting_datetime) as minutes_until,
        mm.meeting_datetime,
        'people-outline' as icon,
        'high' as priority
    FROM mentor_meetings mm
    JOIN mentor_relationships mr ON mm.relationship_id = mr.id
    JOIN mentors m ON mr.mentor_id = m.id
    WHERE mr.mentee_id = ? 
    AND mm.status = 'confirmed'
    AND mm.meeting_datetime > NOW()
    AND mm.meeting_datetime < DATE_ADD(NOW(), INTERVAL 20 DAY)
    ORDER BY mm.meeting_datetime ASC
    LIMIT 2";

$stmt = $dbc->prepare($mentor_meetings_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$mentor_meetings = $stmt->get_result();

while ($meeting = $mentor_meetings->fetch_assoc()) {
    $time_ago = '';
    if ($meeting['minutes_until'] <= 60) {
        $time_ago = 'in ' . $meeting['minutes_until'] . ' minutes';
    } else {
        $hours = round($meeting['minutes_until'] / 60);
        $time_ago = 'in ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    
    $notifications[] = [
        'id' => 'mm_' . $meeting['meeting_id'],
        'icon' => $meeting['icon'],
        'title' => $meeting['title'],
        'subtitle' => $meeting['subtitle'],
        'time' => $time_ago,
        'priority' => $meeting['priority'],
        'read' => in_array('mm_' . $meeting['meeting_id'], $_SESSION['read_notifications'] ?? [])
    ];
}



// Get upcoming events for notifications
$events_query = "
    SELECT 
        e.id as event_id,
        CONCAT('Event Reminder: ', e.title) as title,
        CONCAT('Starting at ', DATE_FORMAT(e.event_date, '%h:%i %p')) as subtitle,
        TIMESTAMPDIFF(MINUTE, NOW(), e.event_date) as minutes_until,
        e.event_date,
        'calendar-outline' as icon,
        'normal' as priority
    FROM events e
    JOIN event_rsvps er ON e.id = er.event_id
    WHERE er.student_id = ? 
    AND er.status = 'registered'
    AND e.event_date > NOW()
    AND e.event_date < DATE_ADD(NOW(), INTERVAL 20 DAY)
    ORDER BY e.event_date ASC
    LIMIT 2";

$stmt = $dbc->prepare($events_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$events = $stmt->get_result();

while ($event = $events->fetch_assoc()) {
    $time_ago = '';
    if ($event['minutes_until'] <= 60) {
        $time_ago = 'in ' . $event['minutes_until'] . ' minutes';
    } else {
        $hours = round($event['minutes_until'] / 60);
        $time_ago = 'in ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    
    $notifications[] = [
        'id' => 'ev_' . $event['event_id'],
        'icon' => $event['icon'],
        'title' => $event['title'],
        'subtitle' => $event['subtitle'],
        'time' => $time_ago,
        'priority' => $event['priority'],
        'read' => in_array('ev_' . $event['event_id'], $_SESSION['read_notifications'] ?? [])
    ];
}

// Get dismissed notifications from database
$dismissed_notifications = [];
$stmt = $dbc->prepare("SELECT notification_id FROM dismissed_notifications WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $dismissed_notifications[] = $row['notification_id'];
}

// Filter out dismissed notifications
$notifications = array_filter($notifications, function($n) use ($dismissed_notifications) {
    return !in_array($n['id'], $dismissed_notifications);
});
// Reindex the array
$notifications = array_values($notifications);





$notifications = array_filter($notifications, function($n) {
    return !in_array($n['id'], $_SESSION['dismissed_notifications'] ?? []);
});

// Sort notifications by priority and time
usort($notifications, function($a, $b) {
    if ($a['priority'] === 'high' && $b['priority'] !== 'high') return -1;
    if ($a['priority'] !== 'high' && $b['priority'] === 'high') return 1;
    return 0;
});

// Count unread notifications
$unread_count = count(array_filter($notifications, function($n) { return !$n['read']; }));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WIC Portal</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="dashboard_styles.css">

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
    <div id="sign-in-top" class="logout-top">
        <button onclick="window.location.href='authentication/logout.php'" class="logout-btn">log out</button>
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
                    <a href="dashboard.php" class="active">
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

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <!-- Top row - 2 columns -->
        <div class="dashboard-row-top">
            <!-- Notifications Card (Left) -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <ion-icon name="notifications-outline"></ion-icon>
                        Notifications
                    </h2>
                    <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?> new</span>
                    <?php endif; ?>
                </div>
                <div class="notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="no-notifications">
                            <p>No new notifications</p>
                            <p style="font-size: 14px; color: #999;">Check back later for updates</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item" data-notification-id="<?php echo $notification['id']; ?>">
                            <ion-icon name="<?php echo $notification['icon']; ?>" class="notification-icon"></ion-icon>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                    <?php if ($notification['priority'] === 'high'): ?>
                                    <span class="notification-status high-priority">High Priority</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-subtitle"><?php echo htmlspecialchars($notification['subtitle']); ?></div>
                                <div class="notification-time"><?php echo $notification['time']; ?></div>
                            </div>
                            <div class="notification-actions">
                                <?php if (!$notification['read']): ?>
                                <button class="action-btn" title="Mark as read" onclick="handleNotificationAction('<?php echo $notification['id']; ?>', 'mark_read')">
                                    <ion-icon name="checkmark-outline"></ion-icon>
                                </button>
                                <?php endif; ?>
                                <button class="action-btn" title="Dismiss" onclick="handleNotificationAction('<?php echo $notification['id']; ?>', 'dismiss')">
                                    <ion-icon name="close-outline"></ion-icon>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- JS to managae notification actions -->
            <script>
            function handleNotificationAction(notificationId, action) {
                const formData = new FormData();
                formData.append('notification_action', '1');
                formData.append('notification_id', notificationId);
                formData.append('action', action);
                
                fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notificationItem = document.querySelector(`[data-notification-id="${notificationId}"]`);
                        
                        if (action === 'mark_read') {
                            // Remove the check button and update the div count
                            const checkBtn = notificationItem.querySelector('[title="Mark as read"]');
                            if (checkBtn) {
                                checkBtn.remove();
                            }
                            updateNotificationBadge();
                        } else if (action === 'dismiss') {
                            // Remove the notification item with animation
                            notificationItem.style.transition = 'opacity 0.3s, transform 0.3s';
                            notificationItem.style.opacity = '0';
                            notificationItem.style.transform = 'translateX(20px)';
                            setTimeout(() => {
                                notificationItem.remove();
                                updateNotificationBadge();
                            }, 300);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }

            function updateNotificationBadge() {
                const unreadNotifications = document.querySelectorAll('.notification-item [title="Mark as read"]').length;
                const badge = document.querySelector('.notification-badge');
                
                if (badge) {
                    if (unreadNotifications > 0) {
                        badge.textContent = unreadNotifications + ' new';
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
            </script>

            <!-- Upcoming events card  -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <ion-icon name="calendar-outline"></ion-icon>
                        Upcoming Events
                    </h2>
                </div>
                <div class="events-list">
                    <?php if ($upcoming_events->num_rows > 0): ?>
                        <?php while ($event = $upcoming_events->fetch_assoc()): ?>
                        <div class="event-item">
                            <div class="event-info">
                                <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                <div class="event-date">
                                    <?php 
                                    $eventDate = new DateTime($event['event_date']);
                                    $now = new DateTime();
                                    
                                    // Format the date based on how soon it is
                                    if ($eventDate->format('Y-m-d') === $now->format('Y-m-d')) {
                                        echo "Today, " . $eventDate->format('g:i A');
                                    } elseif ($eventDate->format('Y-m-d') === $now->modify('+1 day')->format('Y-m-d')) {
                                        echo "Tomorrow, " . $eventDate->format('g:i A');
                                    } else {
                                        // If the event is this wee show the day name
                                        $daysUntilEvent = $now->diff($eventDate)->days;
                                        if ($daysUntilEvent <= 7) {
                                            echo $eventDate->format('l, g:i A');
                                        } else {
                                            // For events farther away out show the full date
                                            echo $eventDate->format('M jS, g:i A');
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                            <button class="event-action" onclick="showEventModal(<?php echo $event['id']; ?>)">VIEW</button>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-events">
                            <p>No upcoming events. Check out the <a href="events.php" style="color: #e91e63; text-decoration: none;">events page</a> to RSVP for events!</p>
                        </div>
                    <?php endif; ?>
                </div>
                <button class="view-all-btn" onclick="window.location.href='events.php'">View All Events</button>
            </div>
        </div>

        <!-- Bottom row is 3 columns -->
        <div class="dashboard-row-bottom">
            <!-- Question Mark Card (future purposes) -->
            <div class="dashboard-card question-card">
                <div class="question-mark">?</div>
            </div>

            <!-- Find Your Mentor Card -->
            <div class="dashboard-card mentor-card">
                <h2 class="mentor-title">Find Your<br>Mentor</h2>
                <button class="apply-btn" onclick="window.location.href='mentoring.php'">Apply Here</button>
            </div>

            <!-- Goal Tracking Card -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2 class="card-title">
                        <ion-icon name="trophy-outline"></ion-icon>
                        Goal Tracking
                    </h2>
                </div>
                <div class="goal-tracking-content">
                    <div class="goal-item">
                        <div class="goal-header">
                            <span class="goal-title">Frontend Track</span>
                            <span class="goal-percentage">75%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 75%"></div>
                        </div>
                    </div>
                    
                    <div class="goal-item">
                        <div class="goal-header">
                            <span class="goal-title">Backend Track</span>
                            <span class="goal-percentage low">24%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill low" style="width: 24%"></div>
                        </div>
                    </div>
                    
                    <div class="goal-actions">
                        <button class="update-skills-btn">Update Skills</button>
                        <button class="view-roadmap-btn">View Roadmap</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Popup -->
    <div class="modal-overlay" id="modal-overlay"></div>
    <div class="event-modal" id="event-modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeEventModal()">&times;</button>
            <img class="modal-image" id="modal-image" src="" alt="">
            <div class="modal-body">
                <h2 class="modal-title" id="modal-title"></h2>
                <div class="modal-meta">
                    <div class="modal-meta-item">
                        <ion-icon name="calendar-outline"></ion-icon>
                        <span id="modal-date"></span>
                    </div>
                    <div class="modal-meta-item">
                        <ion-icon name="time-outline"></ion-icon>
                        <span id="modal-time"></span>
                    </div>
                    <div class="modal-meta-item" id="modal-location-container">
                        <ion-icon name="location-outline"></ion-icon>
                        <span id="modal-location"></span>
                    </div>
                </div>
                <div class="modal-description" id="modal-description"></div>
                <div class="modal-tags" id="modal-tags"></div>
                <div class="modal-footer">
                    <div class="modal-attendees" id="modal-attendees"></div>
                    <button class="modal-rsvp-btn" id="modal-rsvp-btn" onclick="toggleRSVP()">RSVP</button>
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
        // Dashboard-specific js to hand sidebar functionality and more
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburger-menu');
            const navSidebar = document.getElementById('nav-sidebar');
            const closeNavBtn = document.querySelector('.close-nav-btn');
            const overlay = document.getElementById('overlay');

            // Navigation sidebar functions
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

            // Event listeners for navigation sidebar
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', openNavSidebar);
            }

            if (closeNavBtn) {
                closeNavBtn.addEventListener('click', closeNavSidebar);
            }

            // Close sidebar when overlay is clicked
            if (overlay) {
                overlay.addEventListener('click', closeNavSidebar);
            }
        });

        // Event modal functionality
        let currentEventId = null;
        let currentEventData = null;

        function showEventModal(eventId) {
            currentEventId = eventId;
            
            // Fetch event details
            fetch(`dashboard.php?get_event_details=1&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error loading event details');
                        return;
                    }
                    
                    currentEventData = data;
                    
                    // Populate modal with event data
                    document.getElementById('modal-title').textContent = data.title;
                    
                    // Format date and time
                    const eventDate = new Date(data.event_date);
                    const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                    const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
                    
                    document.getElementById('modal-date').textContent = eventDate.toLocaleDateString('en-US', dateOptions);
                    document.getElementById('modal-time').textContent = eventDate.toLocaleTimeString('en-US', timeOptions);
                    
                    // Location
                    if (data.location) {
                        document.getElementById('modal-location-container').style.display = 'flex';
                        document.getElementById('modal-location').textContent = data.location;
                    } else {
                        document.getElementById('modal-location-container').style.display = 'none';
                    }
                    
                    // Description
                    document.getElementById('modal-description').textContent = data.description;
                    
                    // Tags
                    const tagsContainer = document.getElementById('modal-tags');
                    tagsContainer.innerHTML = '';
                    if (data.tags) {
                        data.tags.split(',').forEach(tag => {
                            const tagElement = document.createElement('span');
                            tagElement.className = 'modal-tag';
                            tagElement.textContent = `#${tag.trim()}`;
                            tagsContainer.appendChild(tagElement);
                        });
                    }
                    
                    // Attendees
                    const attendeesText = `${data.attendee_count} attending`;
                    const capacityText = data.capacity ? ` / ${data.capacity} capacity` : '';
                    document.getElementById('modal-attendees').textContent = attendeesText + capacityText;
                    
                    // RSVP button
                    const rsvpBtn = document.getElementById('modal-rsvp-btn');
                    if (data.user_rsvp_status === 'registered') {
                        rsvpBtn.textContent = 'Cancel RSVP';
                        rsvpBtn.classList.add('registered');
                    } else {
                        rsvpBtn.textContent = 'RSVP';
                        rsvpBtn.classList.remove('registered');
                    }
                    
                    // Event image
                    const modalImage = document.getElementById('modal-image');
                    if (data.image_url) {
                        modalImage.src = data.image_url;
                        modalImage.alt = data.title;
                    } else {
                        modalImage.src = '';
                        modalImage.alt = '';
                    }
                    
                    // Show event popup
                    document.getElementById('modal-overlay').classList.add('show');
                    document.getElementById('event-modal').classList.add('show');
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading event details');
                });
        }

        function closeEventModal() {
            document.getElementById('modal-overlay').classList.remove('show');
            document.getElementById('event-modal').classList.remove('show');
            document.body.style.overflow = 'auto';
            currentEventId = null;
            currentEventData = null;
        }

        function toggleRSVP() {
            if (!currentEventId) return;
            
            const rsvpBtn = document.getElementById('modal-rsvp-btn');
            const isRegistered = rsvpBtn.classList.contains('registered');
            const action = isRegistered ? 'cancel' : 'register';
            
            // Send AJAX request to update RSVP
            const formData = new FormData();
            formData.append('ajax_rsvp', '1');
            formData.append('event_id', currentEventId);
            formData.append('action', action);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (action === 'cancel') {
                        rsvpBtn.textContent = 'RSVP';
                        rsvpBtn.classList.remove('registered');
                        currentEventData.attendee_count--;
                    } else {
                        rsvpBtn.textContent = 'Cancel RSVP';
                        rsvpBtn.classList.add('registered');
                        currentEventData.attendee_count++;
                    }
                    
                    // Update attendee count
                    const attendeesText = `${currentEventData.attendee_count} attending`;
                    const capacityText = currentEventData.capacity ? ` / ${currentEventData.capacity} capacity` : '';
                    document.getElementById('modal-attendees').textContent = attendeesText + capacityText;
                    
                    // Reload the page to update the events list
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Error updating RSVP status');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating RSVP status');
            });
        }

        // Close modal when clicking outside
        document.getElementById('modal-overlay').addEventListener('click', closeEventModal);
        
        // Prevent modal from closing when clicking inside it
        document.getElementById('event-modal').addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventModal();
            }
        });
    </script>
</body>
</html>