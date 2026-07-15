-- ATU Connect — Demo seed data
-- Run this AFTER schema.sql. Gives you one working login of each key role.
-- Default password for every seeded account: Password123!

USE atu_connect_platform;

INSERT INTO roles (role_name, description) VALUES
  ('student', 'Student'),
  ('lecturer', 'Teaches courses'),
  ('dept_executive', 'Department executive'),
  ('src_executive', 'SRC executive'),
  ('jcrc_executive', 'JCRC executive'),
  ('faculty_executive', 'Faculty executive'),
  ('hod', 'Head of Department'),
  ('faculty_dean', 'Faculty Dean'),
  ('registrar_office', 'Registrar''s Office'),
  ('student_affairs', 'Student Affairs'),
  ('university_management', 'University Management'),
  ('administrator', 'Full system control');

INSERT INTO departments (department_name, slug, description) VALUES
  ('Computer Science', 'computer-science', 'Department of Computer Science, Accra Technical University.');

-- Verified bcrypt hash of plaintext: Password123!
-- (generated + round-tripped with password_verify() against XAMPP's own php.exe;
-- if you ever regenerate it, use: php config/generate_password_hash.php Password123!)
INSERT INTO users (full_name, email, identifier, password_hash, role_id, department_id, phone, is_active) VALUES
  ('Dr. Ama Serwaa', 'hod.cs@atu.edu.gh', 'HOD001', '$2y$10$uugeM1Q0AOJPUpZV/inIqugtrj06FLbxCNbhhJl5cgDA3ewZ3dRKy', (SELECT role_id FROM roles WHERE role_name = 'hod'), 1, '0244000001', 1),
  ('Mr. Kwabena Owusu', 'lecturer.cs@atu.edu.gh', 'LEC001', '$2y$10$uugeM1Q0AOJPUpZV/inIqugtrj06FLbxCNbhhJl5cgDA3ewZ3dRKy', (SELECT role_id FROM roles WHERE role_name = 'lecturer'), 1, '0244000002', 1),
  ('System Administrator', 'admin@atu.edu.gh', 'ADMIN001', '$2y$10$uugeM1Q0AOJPUpZV/inIqugtrj06FLbxCNbhhJl5cgDA3ewZ3dRKy', (SELECT role_id FROM roles WHERE role_name = 'administrator'), NULL, '0244000003', 1),
  ('Kojo Mensah', '00934522B@st.atu.edu.gh', 'CS20250001', '$2y$10$uugeM1Q0AOJPUpZV/inIqugtrj06FLbxCNbhhJl5cgDA3ewZ3dRKy', (SELECT role_id FROM roles WHERE role_name = 'student'), 1, '0244000004', 1);

UPDATE departments SET hod_user_id = (SELECT user_id FROM users WHERE identifier = 'HOD001') WHERE department_id = 1;

INSERT INTO lecturers (user_id, staff_title, office_location) VALUES
  ((SELECT user_id FROM users WHERE identifier = 'LEC001'), 'Senior Lecturer', 'CS Block, Room 12');

INSERT INTO students (user_id, programme, level) VALUES
  ((SELECT user_id FROM users WHERE identifier = 'CS20250001'), 'BTech Computer Science', 'Level 200');

INSERT INTO courses (course_code, course_name, description, lecturer_id) VALUES
  ('CS 204', 'Database Management Systems', 'Relational database design, SQL, normalization.',
   (SELECT lecturer_id FROM lecturers WHERE user_id = (SELECT user_id FROM users WHERE identifier = 'LEC001')));

INSERT INTO course_registrations (student_id, course_id, status) VALUES
  ((SELECT student_id FROM students WHERE user_id = (SELECT user_id FROM users WHERE identifier = 'CS20250001')),
   (SELECT course_id FROM courses WHERE course_code = 'CS 204'), 'active');

INSERT INTO assignments (course_id, created_by, title, description, max_score, due_date) VALUES
  ((SELECT course_id FROM courses WHERE course_code = 'CS 204'),
   (SELECT user_id FROM users WHERE identifier = 'LEC001'),
   'ER Diagram Assignment', 'Design an ER diagram for a library management system.', 100.00, DATE_ADD(NOW(), INTERVAL 7 DAY));

-- Names match the filter chip labels in announcements.html exactly —
-- the frontend looks up a category id by chip text, so any mismatch
-- silently breaks that filter (it quietly falls back to "All").
INSERT INTO announcement_categories (category_name) VALUES
  ('Academic'), ('Department'), ('SRC'), ('Events'), ('Emergency'), ('Exams'), ('Scholarships'), ('General');

INSERT INTO announcements (author_id, category_id, department_id, title, body, priority) VALUES
  ((SELECT user_id FROM users WHERE identifier = 'ADMIN001'), (SELECT category_id FROM announcement_categories WHERE category_name = 'General'), NULL,
   'Welcome to ATU Connect', 'This platform now centralizes all academic communication for Accra Technical University.', 'normal'),
  ((SELECT user_id FROM users WHERE identifier = 'HOD001'), (SELECT category_id FROM announcement_categories WHERE category_name = 'General'), 1,
   'CS Department Meeting', 'All Computer Science students should attend the department meeting this Friday at 2pm.', 'normal');

INSERT INTO events (title, description, event_type, department_id, start_datetime, created_by) VALUES
  ('Matriculation Ceremony', 'New students matriculation ceremony.', 'other', NULL, DATE_ADD(NOW(), INTERVAL 14 DAY),
   (SELECT user_id FROM users WHERE identifier = 'ADMIN001'));

INSERT INTO documents (uploaded_by, title, file_path, category, is_pinned) VALUES
  ((SELECT user_id FROM users WHERE identifier = 'ADMIN001'), 'Academic Calendar 2026', 'academic-calendar-2026.pdf', 'General', 1),
  ((SELECT user_id FROM users WHERE identifier = 'HOD001'), 'CS Dept Exam Timetable', 'cs-exam-timetable.pdf', 'Exam', 0);

INSERT INTO conversations (is_group, created_by) VALUES
  (0, (SELECT user_id FROM users WHERE identifier = 'LEC001'));

INSERT INTO conversation_participants (conversation_id, user_id) VALUES
  (1, (SELECT user_id FROM users WHERE identifier = 'LEC001')),
  (1, (SELECT user_id FROM users WHERE identifier = 'CS20250001'));

INSERT INTO messages (conversation_id, sender_id, body) VALUES
  (1, (SELECT user_id FROM users WHERE identifier = 'LEC001'), 'Hi Kojo, just a reminder that the ER Diagram Assignment is due next week.');
