-- ATU Connect — sample courses spread across the existing lecturers.
-- Idempotent-ish: safe to re-run (INSERT IGNORE on enrolments/assignments
-- keyed by natural columns). Assigns each catalogue course an owning
-- lecturer, gives it an outline, enrols students, and posts assignments.

-- --- Make sure lecturer profiles have a department (CS) ---
UPDATE users SET department_id = 1 WHERE identifier IN ('00998876B', 'FRESHLEC1', 'PL1', 'PROFDEPT3');

-- --- Assign courses to lecturers + add course outlines/topics ---
-- Lecturer 3 (Martin Doe, user 8)
UPDATE courses SET lecturer_id = 3, description = 'Relational database design, SQL, normalization (1NF-BCNF), transactions and indexing.' WHERE course_code = 'CS 204';
UPDATE courses SET lecturer_id = 3, description = 'Modern web apps: semantic HTML, responsive CSS, JavaScript, REST APIs and deployment.' WHERE course_code = 'WEB 302';
-- Lecturer 4 (Fresh Lecturer, user 9)
UPDATE courses SET lecturer_id = 4, description = 'Distributed and grid computing: resource scheduling, MapReduce, fault tolerance.' WHERE course_code = 'GRID 401';
UPDATE courses SET lecturer_id = 4, description = 'From idea to venture: opportunity analysis, business models, pitching and funding.' WHERE course_code = 'NVC 101';
UPDATE courses SET description = 'Sandbox test course for the CS department.' WHERE course_code = 'CS 999';
-- Lecturer 5 (Pend Lec, user 13)
UPDATE courses SET lecturer_id = 5, description = 'Object-oriented programming in Java: classes, inheritance, collections, exceptions, JavaFX.' WHERE course_code = 'JAVA 201';
UPDATE courses SET lecturer_id = 5, description = 'Design thinking, ideation techniques, lateral thinking and creative problem solving.' WHERE course_code = 'CRT 101';
-- Lecturer 6 (Prof Dept Test3, user 17)
UPDATE courses SET lecturer_id = 6, description = 'Emerging tech survey: AI, blockchain, IoT, edge computing and quantum basics.' WHERE course_code = 'EFT 401';
UPDATE courses SET lecturer_id = 6, description = 'Advanced database systems: query optimization, NoSQL, data warehousing, ER modelling.' WHERE course_code = 'DSD 301';
UPDATE courses SET lecturer_id = 6, description = 'Core data structures & algorithms: lists, trees, graphs, sorting, complexity analysis.' WHERE course_code = 'DSA 202';

-- --- Enrol the sample students across several courses ---
INSERT IGNORE INTO course_registrations (student_id, course_id, status)
SELECT s.student_id, c.course_id, 'active'
FROM students s
JOIN courses c ON c.course_code IN ('CS 204', 'WEB 302', 'JAVA 201', 'DSA 202', 'EFT 401', 'GRID 401', 'NVC 101', 'CRT 101')
WHERE s.student_id BETWEEN 1 AND 5;

-- --- Post sample assignments (created_by = the course's lecturer's user) ---
INSERT IGNORE INTO assignments (course_id, created_by, title, description, max_score, due_date)
SELECT c.course_id, u.user_id, x.title, x.descr, 100.00,
       DATE_ADD(NOW(), INTERVAL x.due_days DAY)
FROM (
  SELECT 'CS 204'   AS code, 'ER Diagram Project'        AS title, 'Design an ER diagram for a library system.'      AS descr, 7   AS due_days UNION ALL
  SELECT 'CS 204',        'Normalization Worksheet',     'Normalize the given schema to BCNF.',                    -3          UNION ALL
  SELECT 'WEB 302',       'Responsive Portfolio Site',   'Build a responsive personal portfolio page.',            10          UNION ALL
  SELECT 'JAVA 201',      'Bank Account OOP Task',       'Model accounts using classes and inheritance.',          5          UNION ALL
  SELECT 'DSA 202',       'Sorting Algorithms Report',   'Compare bubble, merge and quicksort empirically.',       12          UNION ALL
  SELECT 'EFT 401',       'AI in Industry Essay',        'Write a 1500-word essay on applied AI.',                 14          UNION ALL
  SELECT 'GRID 401',      'MapReduce Word Count',        'Implement a distributed word-count job.',                 9
) x
JOIN courses c ON c.course_code = x.code
JOIN lecturers l ON l.lecturer_id = c.lecturer_id
JOIN users u ON u.user_id = l.user_id;
