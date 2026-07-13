-- =========================================================
-- ATU CONNECT — DATABASE SCHEMA
-- Engine: MySQL 8+ / MariaDB 10.4+
-- Charset: utf8mb4
-- =========================================================

-- Named atu_connect_platform (not atu_connect) to avoid colliding with an
-- unrelated pre-existing "atu_connect" database that may exist on this host.
CREATE DATABASE IF NOT EXISTS atu_connect_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE atu_connect_platform;

SET FOREIGN_KEY_CHECKS = 0;

-- =========================================================
-- ROLES & PERMISSIONS
-- =========================================================
CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,   -- student, lecturer, dept_executive, src_executive, hod, administrator
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE permissions (
  permission_id INT AUTO_INCREMENT PRIMARY KEY,
  permission_key VARCHAR(100) NOT NULL UNIQUE, -- e.g. announcement.create, assignment.grade
  description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- DEPARTMENTS
-- =========================================================
CREATE TABLE departments (
  department_id INT AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(150) NOT NULL,
  department_code VARCHAR(20) NOT NULL UNIQUE,
  hod_user_id INT NULL,  -- FK added after users table exists
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- USERS (base identity for every role)
-- =========================================================
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  identifier VARCHAR(50) NOT NULL UNIQUE,   -- student ID / staff ID
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  department_id INT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  notify_email TINYINT(1) NOT NULL DEFAULT 1,
  notify_sms TINYINT(1) NOT NULL DEFAULT 0,
  notify_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id),
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
  INDEX idx_users_role (role_id),
  INDEX idx_users_department (department_id)
) ENGINE=InnoDB;

ALTER TABLE departments
  ADD CONSTRAINT fk_dept_hod FOREIGN KEY (hod_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

-- =========================================================
-- STUDENTS (extends users)
-- =========================================================
CREATE TABLE students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  programme VARCHAR(150),
  level VARCHAR(10),          -- e.g. 100, 200, 300, 400
  academic_advisor_id INT NULL,
  emergency_contact_name VARCHAR(150),
  emergency_contact_phone VARCHAR(30),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (academic_advisor_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- LECTURERS (extends users)
-- =========================================================
CREATE TABLE lecturers (
  lecturer_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  staff_title VARCHAR(50),      -- Dr., Mr., Mrs., Prof.
  office_location VARCHAR(150),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- COURSES
-- =========================================================
CREATE TABLE courses (
  course_id INT AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(20) NOT NULL UNIQUE,
  course_name VARCHAR(150) NOT NULL,
  department_id INT NOT NULL,
  lecturer_id INT NULL,
  credits INT NOT NULL DEFAULT 3,
  semester VARCHAR(20),
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE CASCADE,
  FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE SET NULL,
  INDEX idx_courses_department (department_id)
) ENGINE=InnoDB;

CREATE TABLE course_registrations (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('active','dropped','completed') DEFAULT 'active',
  UNIQUE KEY uq_student_course (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- ASSIGNMENTS
-- =========================================================
CREATE TABLE assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  created_by INT NOT NULL,          -- users.user_id (lecturer)
  title VARCHAR(200) NOT NULL,
  description TEXT,
  brief_file VARCHAR(255) DEFAULT NULL,
  max_score DECIMAL(6,2) DEFAULT 100.00,
  due_date DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_assignments_course (course_id),
  INDEX idx_assignments_due (due_date)
) ENGINE=InnoDB;

CREATE TABLE assignment_submissions (
  submission_id INT AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT NOT NULL,
  student_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_late TINYINT(1) DEFAULT 0,
  grade DECIMAL(6,2) DEFAULT NULL,
  feedback TEXT DEFAULT NULL,
  graded_by INT NULL,
  graded_at DATETIME NULL,
  UNIQUE KEY uq_assignment_student (assignment_id, student_id),
  FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- ANNOUNCEMENTS
-- =========================================================
CREATE TABLE announcement_categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(50) NOT NULL UNIQUE  -- Academic, Department, SRC, Events, Emergency, Exams, Scholarships, General
) ENGINE=InnoDB;

CREATE TABLE announcements (
  announcement_id INT AUTO_INCREMENT PRIMARY KEY,
  author_id INT NOT NULL,
  category_id INT NOT NULL,
  department_id INT NULL,               -- NULL = university-wide
  title VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  priority ENUM('low','normal','high','emergency') DEFAULT 'normal',
  is_pinned TINYINT(1) DEFAULT 0,
  publish_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES announcement_categories(category_id),
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
  INDEX idx_announcements_publish (publish_at),
  INDEX idx_announcements_pinned (is_pinned)
) ENGINE=InnoDB;

CREATE TABLE announcement_attachments (
  attachment_id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE announcement_likes (
  announcement_id INT NOT NULL,
  user_id INT NOT NULL,
  liked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE announcement_comments (
  comment_id INT AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT NOT NULL,
  user_id INT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- MESSAGING
-- =========================================================
CREATE TABLE conversations (
  conversation_id INT AUTO_INCREMENT PRIMARY KEY,
  is_group TINYINT(1) DEFAULT 0,
  title VARCHAR(150) DEFAULT NULL,     -- used when is_group = 1
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE conversation_participants (
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  last_read_at DATETIME NULL,
  PRIMARY KEY (conversation_id, user_id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  message_id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender_id INT NOT NULL,
  body TEXT,
  attachment_path VARCHAR(255) DEFAULT NULL,
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  is_deleted TINYINT(1) DEFAULT 0,
  FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_messages_conversation (conversation_id, sent_at)
) ENGINE=InnoDB;

-- =========================================================
-- NOTIFICATIONS
-- =========================================================
CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,           -- assignment_due, assignment_graded, announcement, message, event, deadline
  title VARCHAR(200) NOT NULL,
  body VARCHAR(255),
  priority ENUM('low','medium','high') DEFAULT 'low',
  related_id INT NULL,                 -- id of the related record (announcement_id, assignment_id, etc.)
  is_read TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id, is_read)
) ENGINE=InnoDB;

-- =========================================================
-- CALENDAR / EVENTS
-- =========================================================
CREATE TABLE events (
  event_id INT AUTO_INCREMENT PRIMARY KEY,
  created_by INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  event_type ENUM('exam','deadline','meeting','src_event','holiday','guest_lecture','seminar','other') DEFAULT 'other',
  department_id INT NULL,
  location VARCHAR(150),
  start_datetime DATETIME NOT NULL,
  end_datetime DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
  INDEX idx_events_start (start_datetime)
) ENGINE=InnoDB;

-- =========================================================
-- DOCUMENTS (digital notice board / general docs)
-- =========================================================
CREATE TABLE documents (
  document_id INT AUTO_INCREMENT PRIMARY KEY,
  uploaded_by INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  category VARCHAR(50),
  is_pinned TINYINT(1) DEFAULT 0,
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- AUTH / SESSIONS / SECURITY
-- =========================================================
CREATE TABLE user_sessions (
  session_id VARCHAR(128) PRIMARY KEY,
  user_id INT NOT NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  remember_token VARCHAR(255) DEFAULT NULL,
  last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_history (
  login_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  status ENUM('success','failed') NOT NULL,
  attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  token_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  description VARCHAR(255),
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- SYSTEM SETTINGS
-- =========================================================
CREATE TABLE system_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- SEED DATA
-- =========================================================
-- Roles are split into two groups:
--   1) Base roles: student, lecturer, administrator (system access levels)
--   2) Announcement-authorized roles: everyone else below, all of whom
--      are permitted to author announcements (see permission
--      'announcement.create' in role_permissions once seeded).
INSERT INTO roles (role_name, description) VALUES
('student', 'Enrolled student'),
('lecturer', 'Course lecturer'),
('dept_executive', 'Departmental Executive'),
('src_executive', 'SRC (Students'' Representative Council) Executive'),
('jcrc_executive', 'JCRC (Hall) Executive'),
('faculty_executive', 'Faculty Executive'),
('hod', 'Head of Department'),
('faculty_dean', 'Faculty Dean'),
('registrar_office', 'Registrar''s Office'),
('student_affairs', 'Student Affairs'),
('university_management', 'University Management'),
('administrator', 'University Administration (system admin)');

INSERT INTO announcement_categories (category_name) VALUES
('Academic'), ('Department'), ('SRC'), ('Events'), ('Emergency'), ('Exams'), ('Scholarships'), ('General');

INSERT INTO system_settings (setting_key, setting_value) VALUES
('app_name', 'ATU Connect'),
('session_timeout_minutes', '60'),
('max_upload_size_mb', '25');
