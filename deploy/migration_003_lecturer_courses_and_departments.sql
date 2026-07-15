-- Migration 003 — additive, safe on a populated database.
-- 1) A free-text "courses I teach" field captured at lecturer sign-up.
-- 2) A fuller set of ATU departments so executives/lecturers can pick one.

ALTER TABLE lecturers
  ADD COLUMN courses_taught VARCHAR(255) NULL AFTER office_location;

-- Seed common ATU departments (Computer Science already exists from the
-- base seed). INSERT IGNORE keeps this safe to re-run and avoids clobbering
-- the existing row / duplicate slugs.
INSERT IGNORE INTO departments (department_name, slug, description) VALUES
  ('Accountancy', 'accountancy', 'Department of Accountancy.'),
  ('Marketing', 'marketing', 'Department of Marketing.'),
  ('Procurement & Supply Science', 'procurement-supply-science', 'Department of Procurement and Supply Science.'),
  ('Management & Public Administration', 'management-public-administration', 'Department of Management and Public Administration.'),
  ('Civil Engineering', 'civil-engineering', 'Department of Civil Engineering.'),
  ('Electrical & Electronic Engineering', 'electrical-electronic-engineering', 'Department of Electrical and Electronic Engineering.'),
  ('Mechanical Engineering', 'mechanical-engineering', 'Department of Mechanical Engineering.'),
  ('Building Technology', 'building-technology', 'Department of Building Technology.'),
  ('Hospitality Management', 'hospitality-management', 'Department of Hospitality Management.'),
  ('Fashion Design & Textiles', 'fashion-design-textiles', 'Department of Fashion Design and Textiles.'),
  ('Mathematics & Statistics', 'mathematics-statistics', 'Department of Mathematics and Statistics.'),
  ('Science Laboratory Technology', 'science-laboratory-technology', 'Department of Science Laboratory Technology.');
