
-- ===================== IDENTITY & RBAC =====================

CREATE TABLE roles (
  role_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_name     VARCHAR(40) NOT NULL UNIQUE, -- student, lecturer, dept_executive, src_executive, jcrc_executive,
                                              -- faculty_executive, hod, faculty_dean, registrar_office,
                                              -- student_affairs, university_management, administrator
  description   VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE departments (
  department_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_name VARCHAR(150) NOT NULL UNIQUE,
  slug             VARCHAR(160) NOT NULL UNIQUE,
  description       TEXT NULL,
  hod_user_id       INT UNSIGNED NULL, -- FK added after users table exists
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name         VARCHAR(150) NOT NULL,
  email             VARCHAR(150) NOT NULL UNIQUE,
  identifier        VARCHAR(50) NOT NULL UNIQUE, -- staff/student ID number, used for login
  password_hash     VARCHAR(255) NOT NULL,
  role_id           INT UNSIGNED NOT NULL,
  department_id     INT UNSIGNED NULL,
  phone             VARCHAR(30) NULL,
  profile_picture   VARCHAR(255) NULL,
  notify_email      TINYINT(1) NOT NULL DEFAULT 1,
  notify_sms        TINYINT(1) NOT NULL DEFAULT 0,
  notify_whatsapp   TINYINT(1) NOT NULL DEFAULT 0,
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  -- lecturers sign up 'pending' and must be approved by their HOD;
  -- every other role is auto-'approved' on registration
  approval_status   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  last_login_at     DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE RESTRICT,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE departments
  ADD CONSTRAINT fk_departments_hod FOREIGN KEY (hod_user_id) REFERENCES users(user_id) ON DELETE SET NULL;

CREATE TABLE login_history (
  login_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  ip_address    VARCHAR(45) NULL,
  user_agent    VARCHAR(255) NULL,
  status        ENUM('success','failed') NOT NULL,
  attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  token_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  token_hash    VARCHAR(255) NOT NULL,
  expires_at    DATETIME NOT NULL,
  used          TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== PROFILE EXTENSIONS =====================

CREATE TABLE students (
  student_id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id                   INT UNSIGNED NOT NULL UNIQUE,
  programme                 VARCHAR(150) NULL,
  level                     VARCHAR(20) NULL,
  academic_advisor_id       INT UNSIGNED NULL,
  emergency_contact_name    VARCHAR(150) NULL,
  emergency_contact_phone   VARCHAR(30) NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (academic_advisor_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lecturers (
  lecturer_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id           INT UNSIGNED NOT NULL UNIQUE,
  staff_title       VARCHAR(100) NULL,
  office_location   VARCHAR(150) NULL,
  courses_taught    VARCHAR(255) NULL, -- free-text captured at sign-up
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== COURSES =====================

CREATE TABLE courses (
  course_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_code   VARCHAR(20) NOT NULL,
  course_name   VARCHAR(150) NOT NULL,
  description   TEXT NULL,
  lecturer_id   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE SET NULL,
  UNIQUE KEY uniq_course_code (course_code)
) ENGINE=InnoDB;

CREATE TABLE course_registrations (
  registration_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id        INT UNSIGNED NOT NULL,
  course_id         INT UNSIGNED NOT NULL,
  status            ENUM('active','dropped') NOT NULL DEFAULT 'active',
  registered_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  UNIQUE KEY uniq_registration (student_id, course_id)
) ENGINE=InnoDB;

-- ===================== ASSIGNMENTS & SUBMISSIONS =====================

CREATE TABLE assignments (
  assignment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id     INT UNSIGNED NOT NULL,
  created_by    INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT NULL,
  brief_file    VARCHAR(255) NULL,
  max_score     DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  due_date      DATETIME NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE assignment_submissions (
  submission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  submitted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_late       TINYINT(1) NOT NULL DEFAULT 0,
  grade         DECIMAL(5,2) NULL,
  feedback      TEXT NULL,
  graded_by     INT UNSIGNED NULL,
  graded_at     DATETIME NULL,
  FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL,
  UNIQUE KEY uniq_submission (assignment_id, student_id)
) ENGINE=InnoDB;

-- ===================== ANNOUNCEMENTS =====================

CREATE TABLE announcement_categories (
  category_id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_name   VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE announcements (
  announcement_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id       INT UNSIGNED NOT NULL,
  category_id     INT UNSIGNED NOT NULL,
  department_id   INT UNSIGNED NULL, -- NULL = university-wide
  title           VARCHAR(200) NOT NULL,
  body            TEXT NOT NULL,
  priority        ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  is_pinned       TINYINT(1) NOT NULL DEFAULT 0,
  publish_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE RESTRICT,
  FOREIGN KEY (category_id) REFERENCES announcement_categories(category_id) ON DELETE RESTRICT,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE announcement_attachments (
  attachment_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT UNSIGNED NOT NULL,
  file_path       VARCHAR(255) NOT NULL,
  original_name   VARCHAR(255) NULL,
  uploaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE announcement_likes (
  announcement_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE announcement_comments (
  comment_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  comment_text    TEXT NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(announcement_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== DOCUMENTS (NOTICE BOARD) =====================

CREATE TABLE documents (
  document_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uploaded_by   INT UNSIGNED NOT NULL,
  title         VARCHAR(200) NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  category      VARCHAR(60) NULL,
  is_pinned     TINYINT(1) NOT NULL DEFAULT 0,
  uploaded_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ===================== EVENTS =====================

CREATE TABLE events (
  event_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by      INT UNSIGNED NOT NULL,
  title           VARCHAR(200) NOT NULL,
  description     TEXT NULL,
  event_type      ENUM('exam','deadline','meeting','src_event','holiday','guest_lecture','seminar','other') NOT NULL DEFAULT 'other',
  department_id   INT UNSIGNED NULL,
  location        VARCHAR(200) NULL,
  start_datetime  DATETIME NOT NULL,
  end_datetime    DATETIME NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
  FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ===================== MESSAGING =====================

CREATE TABLE conversations (
  conversation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  is_group        TINYINT(1) NOT NULL DEFAULT 0,
  created_by      INT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE conversation_participants (
  conversation_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  last_read_at    DATETIME NULL,
  -- 'pending' = message request awaiting this participant's acceptance
  -- (keeps unsolicited DMs out of the inbox); 'accepted' = in the inbox
  status          ENUM('pending','accepted') NOT NULL DEFAULT 'accepted',
  joined_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (conversation_id, user_id),
  FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE messages (
  message_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id       INT UNSIGNED NOT NULL,
  body            TEXT NULL,
  attachment_path VARCHAR(255) NULL,
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
  sent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== NOTIFICATIONS =====================

CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  type            VARCHAR(40) NOT NULL, -- announcement, assignment_graded, assignment_due, message, etc.
  title           VARCHAR(200) NOT NULL,
  body            TEXT NULL,
  priority        ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  related_id      INT UNSIGNED NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== SECTIONS (GROUP CHANNELS) =====================

CREATE TABLE sections (
  section_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope_type   ENUM('course','department','school') NOT NULL,
  scope_id     INT UNSIGNED NULL,          -- course_id / department_id; NULL for school
  name         VARCHAR(150) NOT NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE section_messages (
  message_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id   INT UNSIGNED NOT NULL,
  sender_id    INT UNSIGNED NOT NULL,
  body         TEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ===================== INDEXES =====================

CREATE INDEX idx_notifications_user_unread ON notifications(user_id, is_read);
CREATE INDEX idx_messages_conversation ON messages(conversation_id, sent_at);
CREATE INDEX idx_announcements_department ON announcements(department_id);
CREATE INDEX idx_announcements_category ON announcements(category_id);
CREATE INDEX idx_courses_lecturer ON courses(lecturer_id);
CREATE INDEX idx_assignment_submissions_assignment ON assignment_submissions(assignment_id);
CREATE INDEX idx_course_registrations_course ON course_registrations(course_id);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_users_department ON users(department_id);
CREATE INDEX idx_section_messages_section ON section_messages(section_id, created_at);
CREATE INDEX idx_sections_scope ON sections(scope_type, scope_id);
