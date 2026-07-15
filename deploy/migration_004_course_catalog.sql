-- Migration 004 — seed the semester course catalogue (additive, re-runnable).
-- Courses start unassigned (lecturer_id NULL); a lecturer claims the ones
-- they teach when they register. INSERT IGNORE keeps this safe to re-run.

INSERT IGNORE INTO courses (course_code, course_name, description, lecturer_id) VALUES
  ('GRID 401', 'Grid Computing', 'Distributed and grid computing concepts and applications.', NULL),
  ('JAVA 201', 'Programming with Java', 'Object-oriented programming with Java.', NULL),
  ('WEB 302', 'Web Development II', 'Advanced web development.', NULL),
  ('NVC 101', 'New Venture Creation', 'Entrepreneurship and new venture creation.', NULL),
  ('CRT 101', 'Creative Thinking', 'Creative and critical thinking skills.', NULL),
  ('EFT 401', 'Emerging Frontiers in Technology', 'Emerging and frontier technologies.', NULL),
  ('DSD 301', 'Database Systems and Design', 'Database systems, modelling and design.', NULL),
  ('DSA 202', 'Data Structures and Algorithm', 'Data structures and algorithm design and analysis.', NULL);
