CREATE DATABASE IF NOT EXISTS project_db;
USE project_db;

-- Students table
CREATE TABLE IF NOT EXISTS students (
    emplid INT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL,
    notification_days INT DEFAULT 3,
    major VARCHAR(100) DEFAULT NULL,
    semester VARCHAR(20) DEFAULT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Majors table
CREATE TABLE IF NOT EXISTS majors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    major_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Basic majors from BMCC, I am using INSERT IGNORE to ignore duplicates since srudents can be the same major
INSERT IGNORE INTO majors (id, major_name, department) VALUES
(1, 'Computer Science', 'Computer Information Systems'),
(2, 'Computer Information Systems', 'Computer Information Systems'),
(3, 'Computer Network Technology', 'Computer Information Systems'),
(4, 'Management Information Systems', 'Business'),
(5, 'Mathematics', 'Science'),
(6, 'Engineering Science', 'Science'),
(7, 'Liberal Arts: Mathematics & Science', 'Liberal Arts');

-- Semesters table
CREATE TABLE IF NOT EXISTS semesters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    semester_name VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert some semesters (for samples)
INSERT IGNORE INTO semesters (id, semester_name, year) VALUES
(1, 'Spring', 2025),
(2, 'Fall', 2025),
(3, 'Summer', 2025);

-- Courses table
CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(10) NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    credits INT DEFAULT 3,
    department VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert common computer science courses
INSERT IGNORE INTO courses (id, course_code, course_name, credits, department) VALUES
(1, 'CSC 111', 'Introduction to Programming', 3, 'Computer Information Systems'),
(2, 'CSC 110', 'Computer Programming I', 3, 'Computer Information Systems'),
(3, 'CSC 211', 'Adv. Programming Techniques', 4, 'Computer Information Systems'),
(4, 'CSC 231', 'Discrete Structures and App. to Computer Science', 4, 'Computer Information Systems'),
(5, 'CSC 215', 'Fundamentals of Computer Systems', 3, 'Computer Information Systems'),
(6, 'CSC 331', 'Data Structures', 3, 'Computer Information Systems'),
(7, 'CSC 350', 'Software Development', 3, 'Computer Information Systems'),
(8, 'CSC 440', 'Unix', 3, 'Computer Information Systems'),
(9, 'CSC 450', 'Computer Graphics', 3, 'Computer Information Systems'),
(10, 'MAT 206', 'Intermediate Algebra & Precalculus', 4, 'Mathematics'),
(11, 'MAT 206.5', 'Intermediate Algebra & Precalculus', 4, 'Mathematics'),
(12, 'MAT 301', 'Analytic Geometry and Calculus I', 4, 'Mathematics'),
(13, 'MAT 302', 'Analytic Geometry and Calculus II', 3, 'Mathematics'),
(14, 'PHY 215', 'University Physics I', 4, 'Science'),
(15, 'PHY 225', 'University Physics II', 4, 'Science');

-- Student courses table 
CREATE TABLE IF NOT EXISTS student_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    semester_id INT,
    grade VARCHAR(5),
    status ENUM('current', 'completed', 'dropped') DEFAULT 'current',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(emplid) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id),
    UNIQUE KEY unique_student_course (student_id, course_id, semester_id)
);

-- Skills table
CREATE TABLE IF NOT EXISTS skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skill_name VARCHAR(50) NOT NULL,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Student skills table
CREATE TABLE IF NOT EXISTS student_skills (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    skill_id INT NOT NULL,
    proficiency_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(emplid) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_skill (student_id, skill_id)
);

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    short_description VARCHAR(500),
    event_date DATETIME NOT NULL,
    location VARCHAR(200),
    capacity INT DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    event_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Event tags table
CREATE TABLE IF NOT EXISTS event_tags (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tag_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Event tag relationships table
CREATE TABLE IF NOT EXISTS event_tag_relationships (
    event_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (event_id, tag_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES event_tags(id) ON DELETE CASCADE
);

-- Event RSVPs table
CREATE TABLE IF NOT EXISTS event_rsvps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    rsvp_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'cancelled', 'attended') DEFAULT 'registered',
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(emplid) ON DELETE CASCADE,
    UNIQUE KEY unique_event_student (event_id, student_id)
);

-- Notification system
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- example: 'meeting' 'event' 'message' etc.
    reference_id INT NULL, -- ID of the related record (meeting_id, event_id)
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(255),
    icon VARCHAR(50) DEFAULT 'notifications-outline',
    priority ENUM('high', 'normal', 'low') DEFAULT 'normal',
    is_read BOOLEAN DEFAULT FALSE,
    is_dismissed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(emplid) ON DELETE CASCADE
);

-- Tracks which notifications have been dismissed so that they dont show up again
CREATE TABLE IF NOT EXISTS dismissed_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    notification_id VARCHAR(50) NOT NULL,
    dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(emplid) ON DELETE CASCADE,
    UNIQUE KEY unique_dismissal (student_id, notification_id)
);

-- Index for faster queries (for future purposes)
-- Since every time a student loads their dashboard we need to quickly grab their unread
-- and non-dismissed notifications I made an INDEX since this makes getting a students 
-- notifications much more fast, especially when checking for unread ones
-- without this, the dashboard would be slower with thousands of notifications
CREATE INDEX idx_student_notifications ON notifications(student_id, is_dismissed, is_read);
CREATE INDEX idx_notification_expiry ON notifications(expires_at);

-- Insert sample tags
INSERT IGNORE INTO event_tags (id, tag_name) VALUES
(1, 'computer_science'),
(2, 'software_engineering'),
(3, 'internships'),
(4, 'career'),
(5, 'networking'),
(6, 'panel'),
(7, 'workshop'),
(8, 'info_session'),
(9, 'alumni'),
(10, 'graduates'),
(11, 'women_in_tech'),
(12, 'programming'),
(13, 'web_development'),
(14, 'data_science'),
(15, 'artificial_intelligence'),
(16, 'cybersecurity'),
(17, 'mobile_development'),
(18, 'cloud_computing'),
(19, 'hackathon'),
(20, 'professional_development');

-- Insert sample events with pre-chosen images
INSERT IGNORE INTO events (id, title, description, short_description, event_date, location, capacity, event_type, image_url) VALUES
(1, 'Tech Panel: Women in AI', 
'Join us for an inspiring panel discussion featuring leading women in artificial intelligence. Our panelists will share their journeys, discuss current trends in AI, and answer your questions about careers in this exciting field.', 
'Panel discussion with leading women in AI sharing insights on innovation, ethics, and career paths.', 
'2025-05-15 14:30:00', 
'BMCC Main Building - Room 404', 
100, 
'panel',
'images/events/tech-panel-women-ai.png'),

(2, 'Google Software Engineering Info Session', 
'Representatives from Google will discuss software engineering opportunities, the interview process, and what it\'s like to work at one of the world\'s leading tech companies. Open Q&A session included.', 
'Learn about software engineering opportunities at Google directly from company representatives.', 
'2025-05-20 15:00:00', 
'BMCC Fiterman Hall - Conference Room A', 
80, 
'info_session',
'images/events/google-info-session.jpg'),

(3, 'Web Development Workshop: React Fundamentals', 
'This hands-on workshop will introduce you to React, one of the most popular JavaScript libraries for building user interfaces. Perfect for students with basic JavaScript knowledge.', 
'Hands-on workshop covering React basics and building interactive web applications.', 
'2025-05-25 13:00:00', 
'BMCC Computer Lab - Room 715', 
30, 
'workshop',
'images/events/react-workshop.jpg'),

(4, 'Alumni Panel: From BMCC to Tech Industry', 
'Hear from successful BMCC alumni who have transitioned into tech careers. Learn about their paths, challenges they overcame, and advice for current students.', 
'BMCC alumni share their journeys from community college to successful tech careers.', 
'2025-06-01 16:00:00', 
'BMCC Theatre', 
150, 
'panel',
'images/events/alumni-panel.jpg'),

(5, 'Bloomberg Summer Internship Info Session', 
'Bloomberg recruiters will present summer internship opportunities for 2025. Learn about the application process, required skills, and what makes a strong candidate.', 
'Information session about Bloomberg\'s summer internship program for 2025.', 
'2025-05-28 14:00:00', 
'BMCC Career Center', 
60, 
'info_session',
'images/events/bloomberg-internship.jpg'),

(6, 'Data Structures & Algorithms Workshop', 
'Strengthen your problem-solving skills with this comprehensive workshop on data structures and algorithms. We\'ll cover arrays, linked lists, trees, and common interview questions.', 
'Deep dive into essential data structures and algorithms for technical interviews.', 
'2025-06-05 13:00:00', 
'BMCC Computer Lab - Room 720', 
40, 
'workshop',
'images/events/data-structures-workshop.jpg'),

(7, 'Women in Cybersecurity: Career Paths', 
'Explore the diverse career opportunities in cybersecurity. This event features women professionals from various cybersecurity roles discussing their work and career advice.', 
'Women cybersecurity professionals discuss career opportunities and paths in the field.', 
'2025-06-10 15:30:00', 
'BMCC Main Building - Room 505', 
75, 
'panel',
'images/events/women-cybersecurity.jpg'),

(8, 'Microsoft Cloud Computing Workshop', 
'Learn the basics of cloud computing with Microsoft Azure. This practical workshop includes hands-on exercises and a free Azure student account setup.', 
'Hands-on introduction to cloud computing with Microsoft Azure.', 
'2025-06-15 14:00:00', 
'BMCC Computer Lab - Room 715', 
35, 
'workshop',
'images/events/azure-workshop.jpg'),

(9, 'Tech Interview Prep: Behavioral Questions', 
'Master the art of answering behavioral interview questions. Learn the STAR method and practice with mock interviews led by industry professionals.', 
'Workshop focusing on behavioral interview preparation and practice sessions.', 
'2025-06-20 16:00:00', 
'BMCC Career Center', 
50, 
'workshop',
'images/events/interview-prep.jpg'),

(10, 'Women Who Code: Mobile App Development', 
'Introduction to mobile app development with React Native. Build your first cross-platform mobile app in this beginner-friendly workshop.', 
'Learn mobile app development basics with React Native in this hands-on workshop.', 
'2025-06-25 13:30:00', 
'BMCC Computer Lab - Room 720', 
30, 
'workshop',
'images/events/mobile-dev-workshop.jpg');

-- Insert event-tag relationships
-- Women in AI Panel
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(1, 15), -- artificial_intelligence tag
(1, 11), -- women_in_tech  
(1, 6), -- panel
(1, 4); -- career

-- Google Info Session
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(2, 2), -- software_engineering
(2, 4), -- career
(2, 8); -- info_session

-- React Workshop
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(3, 13), -- web_development
(3, 12), -- programming tag
(3, 7); -- workshop

-- Alumni Panel
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(4, 9), -- alumni
(4, 4), -- career
(4, 6), -- panel
(4, 10); -- graduates

-- Bloomberg Info Session
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(5, 3),  -- internships
(5, 4),  -- career
(5, 8);  -- info_session

-- Data Structures Workshop
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(6, 12), -- programming
(6, 1), -- computer_science
(6, 7); -- workshop

-- Women in Cybersecurity
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(7, 16), -- cybersecurity
(7, 11), -- women_in_tech
(7, 4), -- career
(7, 6); -- panel

-- Azure Workshop
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(8, 18), -- cloud_computing
(8, 7); -- workshop

-- Interview Prep Workshop
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(9, 4), -- career
(9, 20), -- professional_development
(9, 7);  -- workshop

-- Mobile Dev Workshop
INSERT IGNORE INTO event_tag_relationships (event_id, tag_id) VALUES
(10, 17), -- mobile_development
(10, 12), -- programming
(10, 7), -- workshop
(10, 11); -- women_in_tech

-- MENTOR-TO-MENTEE RELATIONSHIPS
-- Mentors table
CREATE TABLE IF NOT EXISTS mentors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(100) NOT NULL,
    title VARCHAR(100),
    company VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Mentor profiles table
CREATE TABLE IF NOT EXISTS mentor_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentor_id INT NOT NULL,
    specialization VARCHAR(100),
    industry VARCHAR(50),
    field VARCHAR(50),
    years_experience INT,
    meeting_preference ENUM('virtual', 'in-person', 'hybrid'),
    bio TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE
);

-- Mentoring preferences table
CREATE TABLE IF NOT EXISTS mentoring_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    age_group VARCHAR(20),
    industry VARCHAR(50),
    field VARCHAR(50),
    meeting_preference ENUM('virtual', 'in-person', 'hybrid'),
    goals TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(emplid) ON DELETE CASCADE
);

-- Mentor relationships table
CREATE TABLE IF NOT EXISTS mentor_relationships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mentor_id INT NOT NULL,
    mentee_id INT NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_meeting_date TIMESTAMP NULL,
    FOREIGN KEY (mentor_id) REFERENCES mentors(id) ON DELETE CASCADE,
    FOREIGN KEY (mentee_id) REFERENCES students(emplid) ON DELETE CASCADE
);

-- Mentor meetings table
CREATE TABLE IF NOT EXISTS mentor_meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    relationship_id INT NOT NULL,
    meeting_datetime DATETIME NOT NULL,
    meeting_type ENUM('virtual', 'in-person'),
    topic TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (relationship_id) REFERENCES mentor_relationships(id) ON DELETE CASCADE
);

-- Adding some mentor sample data for testing (fake generated hash passwords meaning password123)
INSERT INTO mentors (first_name, last_name, email, password, title, company) VALUES
('Sarah', 'Johnson', 'sarah.johnson@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Senior Software Engineer', 'Tech Corp'),
('Michael', 'Chen', 'michael.chen@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Data Scientist', 'DataTech Inc'),
('Emily', 'Rodriguez', 'emily.rodriguez@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Cloud Architect', 'CloudSystems'),
('David', 'Kim', 'david.kim@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Product Manager', 'InnovateNow'),
('Lisa', 'Patel', 'lisa.patel@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Security Engineer', 'CyberSafe Solutions'),
('Robert', 'Taylor', 'robert.taylor@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'AI Research Scientist', 'AI Labs');

-- Insert fake generated mentor profiles
INSERT INTO mentor_profiles (mentor_id, specialization, industry, field, years_experience, meeting_preference, bio) VALUES
(1, 'Full Stack Development', 'software', 'fullstack', 8, 'hybrid', 'Passionate about mentoring the next generation of developers. Experienced in React, Node.js, and cloud technologies.'),
(2, 'Machine Learning & Analytics', 'data', 'data-analysis', 6, 'virtual', 'Helping students navigate the world of data science and machine learning. Expert in Python, TensorFlow, and statistical analysis.'),
(3, 'Cloud Infrastructure', 'cloud', 'devops', 10, 'hybrid', 'Specialized in AWS and Azure architectures. Love helping students understand cloud computing and DevOps practices.'),
(4, 'Product Development', 'software', 'product', 7, 'in-person', 'Former engineer turned product manager. Helping students transition into product roles and understand the business side of tech.'),
(5, 'Cybersecurity', 'cybersecurity', 'backend', 9, 'virtual', 'Focused on application security and secure coding practices. Passionate about teaching security principles to new developers.'),
(6, 'Artificial Intelligence', 'ai', 'machine-learning', 12, 'hybrid', 'Research scientist with expertise in deep learning and neural networks. Excited to guide students in AI and ML projects.');


