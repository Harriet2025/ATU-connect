-- Migration 002 — group message "sections" (channels).
-- Additive only; safe on a populated database.
--
-- A section is a broadcast/discussion channel scoped to one course
-- (class channel), one department, or the whole school. Membership is
-- virtual (computed from scope: enrolled students + lecturer for a course,
-- department members for a department, everyone for the school), so there's
-- no members table to maintain.

CREATE TABLE IF NOT EXISTS sections (
  section_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scope_type   ENUM('course','department','school') NOT NULL,
  scope_id     INT UNSIGNED NULL,          -- course_id / department_id; NULL for school
  name         VARCHAR(150) NOT NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS section_messages (
  message_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id   INT UNSIGNED NOT NULL,
  sender_id    INT UNSIGNED NOT NULL,
  body         TEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES sections(section_id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_section_messages_section ON section_messages(section_id, created_at);
CREATE INDEX idx_sections_scope ON sections(scope_type, scope_id);
