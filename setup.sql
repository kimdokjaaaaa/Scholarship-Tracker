-- ============================================================
--  SCHOLARSHIP APPLICATION TRACKER
--  Database Setup Script
--  Compatible with MySQL 5.7+ / MariaDB (XAMPP)
-- ============================================================

CREATE DATABASE IF NOT EXISTS scholarship_tracker
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scholarship_tracker;

-- ============================================================
-- TABLE 1: users  (Login System)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,         -- bcrypt hash
    full_name  VARCHAR(100) NOT NULL,
    role       ENUM('admin','reviewer','applicant') NOT NULL DEFAULT 'reviewer',
    email      VARCHAR(100) UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 2: scholarships
-- ============================================================
CREATE TABLE IF NOT EXISTS scholarships (
    scholarship_id   INT AUTO_INCREMENT PRIMARY KEY,
    title            VARCHAR(150) NOT NULL,
    provider         VARCHAR(150) NOT NULL,
    description      TEXT,
    amount           DECIMAL(12,2) NOT NULL,
    slots_available  INT NOT NULL DEFAULT 1,
    min_gpa          DECIMAL(3,2) DEFAULT 1.00,
    deadline         DATE NOT NULL,
    category         ENUM('academic','athletic','need-based','merit','community','research') NOT NULL,
    status           ENUM('open','closed','paused') NOT NULL DEFAULT 'open',
    created_by       INT,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 3: applicants
-- ============================================================
CREATE TABLE IF NOT EXISTS applicants (
    applicant_id  INT AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(60)  NOT NULL,
    last_name     VARCHAR(60)  NOT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    phone         VARCHAR(20),
    address       TEXT,
    birth_date    DATE,
    gpa           DECIMAL(3,2),
    school        VARCHAR(150),
    year_level    ENUM('1st Year','2nd Year','3rd Year','4th Year','Graduate','Others'),
    course        VARCHAR(150),
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 4: applications  (core entity)
-- ============================================================
CREATE TABLE IF NOT EXISTS applications (
    application_id   INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id     INT NOT NULL,
    scholarship_id   INT NOT NULL,
    reviewer_id      INT,
    status           ENUM('submitted','under_review','shortlisted','approved','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
    essay            TEXT,
    score            DECIMAL(5,2),
    remarks          TEXT,
    submitted_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id)   REFERENCES applicants(applicant_id)   ON DELETE CASCADE,
    FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id)    REFERENCES users(user_id)               ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 5: documents
-- ============================================================
CREATE TABLE IF NOT EXISTS documents (
    document_id    INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    doc_type       ENUM('transcript','recommendation','essay','id','certificate','financial','other') NOT NULL,
    filename       VARCHAR(255) NOT NULL,
    file_path      VARCHAR(500),
    uploaded_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified       TINYINT(1) DEFAULT 0,
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABLE 6: status_history  (audit trail)
-- ============================================================
CREATE TABLE IF NOT EXISTS status_history (
    history_id      INT AUTO_INCREMENT PRIMARY KEY,
    application_id  INT NOT NULL,
    changed_by      INT,
    old_status      VARCHAR(50),
    new_status      VARCHAR(50) NOT NULL,
    notes           TEXT,
    changed_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by)     REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Users  (password = "password123" hashed with bcrypt)
INSERT INTO users (username, password, full_name, role, email) VALUES
('admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin',    'admin@school.edu'),
('jreyes',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Juan Reyes',           'reviewer', 'jreyes@school.edu'),
('mcruz',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Maria Cruz',           'reviewer', 'mcruz@school.edu');

-- Scholarships
INSERT INTO scholarships (title, provider, description, amount, slots_available, min_gpa, deadline, category, status, created_by) VALUES
('Presidential Excellence Award',     'State University Foundation', 'For top-performing students with outstanding academic records.',            50000.00, 5,  1.75, '2026-07-31', 'academic',   'open',   1),
('STEM Innovation Grant',             'Tech Philippines Inc.',       'Supporting future engineers and scientists.',                               35000.00, 10, 1.80, '2026-06-30', 'merit',      'open',   1),
('Community Leaders Scholarship',     'Bayanihan Fund',              'For students active in community service and civic engagement.',            25000.00, 8,  2.00, '2026-08-15', 'community',  'open',   1),
('Financial Assistance Program',      'DOST-SEI',                    'Need-based grant for underprivileged but deserving scholars.',              20000.00, 20, 2.50, '2026-07-01', 'need-based', 'open',   1),
('Sports Excellence Scholarship',     'National Athletic Association','For varsity athletes maintaining good academic standing.',                 30000.00, 6,  2.25, '2026-06-15', 'athletic',   'open',   1),
('Graduate Research Fellowship',      'National Research Council',   'Supporting post-graduate research in priority fields.',                   100000.00, 3,  1.50, '2026-09-30', 'research',   'open',   1),
('Heritage Arts & Culture Award',     'Cultural Commission',         'Scholarship for students pursuing arts, music, and cultural programs.',    15000.00, 4,  2.50, '2026-05-31', 'merit',      'closed', 1),
('Entrepreneurship Seed Scholarship', 'Junior Chamber of Commerce',  'For business students with viable entrepreneurship proposals.',            40000.00, 5,  2.00, '2026-08-31', 'merit',      'open',   1);

-- Applicants
INSERT INTO applicants (first_name, last_name, email, phone, gpa, school, year_level, course) VALUES
('Katrina',  'Santos',    'katrina.santos@email.com',  '09171234567', 1.50, 'State University',     '3rd Year',  'BS Computer Science'),
('Miguel',   'Fernandez', 'miguel.fern@email.com',     '09181234567', 1.75, 'State University',     '4th Year',  'BS Civil Engineering'),
('Anna',     'Reyes',     'anna.reyes@email.com',      '09191234567', 1.25, 'State University',     '2nd Year',  'BS Biology'),
('Carlo',    'Dela Cruz', 'carlo.dc@email.com',        '09201234567', 2.00, 'City College',         '3rd Year',  'BS Business Administration'),
('Sophia',   'Lim',       'sophia.lim@email.com',      '09211234567', 1.60, 'State University',     '4th Year',  'BS Mathematics'),
('Jerome',   'Aquino',    'jerome.aq@email.com',       '09221234567', 2.25, 'City College',         '1st Year',  'BS Nursing'),
('Bianca',   'Torres',    'bianca.t@email.com',        '09231234567', 1.80, 'Technical University', '3rd Year',  'BS Electronics'),
('Rafael',   'Gomez',     'rafael.g@email.com',        '09241234567', 2.10, 'State University',     '2nd Year',  'BA Political Science'),
('Jasmine',  'Uy',        'jasmine.uy@email.com',      '09251234567', 1.45, 'State University',     'Graduate',  'MS Computer Science'),
('Patrick',  'Villanueva','patrick.v@email.com',       '09261234567', 1.90, 'Technical University', '3rd Year',  'BS Mechanical Engineering'),
('Lorraine', 'Bautista',  'lorraine.b@email.com',      '09271234567', 2.30, 'City College',         '1st Year',  'BS Accountancy'),
('Ethan',    'Co',        'ethan.co@email.com',        '09281234567', 1.70, 'State University',     '4th Year',  'BS Physics');

-- Applications
INSERT INTO applications (applicant_id, scholarship_id, reviewer_id, status, score, remarks, submitted_at) VALUES
(1,  1, 2, 'approved',      96.50, 'Exceptional academic record.',              '2026-01-10 09:00:00'),
(2,  2, 2, 'approved',      88.00, 'Strong technical background.',              '2026-01-12 10:00:00'),
(3,  1, 3, 'shortlisted',   93.00, 'Pending final interview.',                  '2026-01-15 11:00:00'),
(4,  4, 2, 'under_review',  NULL,  NULL,                                        '2026-01-18 08:30:00'),
(5,  1, 3, 'approved',      91.00, 'Consistent dean''s lister.',                '2026-01-20 09:45:00'),
(6,  4, 2, 'submitted',     NULL,  NULL,                                        '2026-02-01 14:00:00'),
(7,  2, 3, 'shortlisted',   85.50, 'Good technical skills.',                    '2026-02-05 10:30:00'),
(8,  3, 2, 'under_review',  NULL,  NULL,                                        '2026-02-10 11:00:00'),
(9,  6, 3, 'approved',      98.00, 'Outstanding research proposal.',            '2026-02-12 09:00:00'),
(10, 2, 2, 'rejected',      65.00, 'GPA below requirement.',                   '2026-02-14 15:00:00'),
(11, 4, 3, 'submitted',     NULL,  NULL,                                        '2026-02-20 13:00:00'),
(12, 5, 2, 'approved',      87.00, 'Varsity athlete, good standing.',           '2026-02-22 10:00:00'),
(1,  2, 3, 'under_review',  NULL,  NULL,                                        '2026-03-01 09:00:00'),
(3,  3, 2, 'shortlisted',   90.00, 'Active community leader.',                  '2026-03-05 11:00:00'),
(5,  5, 3, 'submitted',     NULL,  NULL,                                        '2026-03-10 14:00:00'),
(4,  8, 2, 'under_review',  NULL,  NULL,                                        '2026-03-15 09:30:00'),
(2,  8, 3, 'submitted',     NULL,  NULL,                                        '2026-03-18 10:00:00'),
(7,  5, 2, 'withdrawn',     NULL,  'Withdrew application.',                     '2026-03-20 16:00:00');

-- Documents
INSERT INTO documents (application_id, doc_type, filename, verified) VALUES
(1, 'transcript',      'transcript_katrina.pdf',   1),
(1, 'recommendation',  'rec_letter_1.pdf',         1),
(2, 'transcript',      'transcript_miguel.pdf',    1),
(3, 'transcript',      'transcript_anna.pdf',      1),
(5, 'transcript',      'transcript_sophia.pdf',    1),
(5, 'certificate',     'cert_sophia.pdf',          0),
(9, 'transcript',      'transcript_jasmine.pdf',   1),
(9, 'essay',           'research_proposal.pdf',    1),
(12,'transcript',      'transcript_ethan.pdf',     1),
(12,'certificate',     'varsity_cert.pdf',         1);

-- Status History
INSERT INTO status_history (application_id, changed_by, old_status, new_status, notes) VALUES
(1,  2, 'submitted', 'under_review', 'Started review process.'),
(1,  2, 'under_review', 'shortlisted', 'Passed document screening.'),
(1,  1, 'shortlisted', 'approved', 'Approved by committee.'),
(2,  2, 'submitted', 'under_review', 'Reviewing documents.'),
(2,  1, 'under_review', 'approved', 'Meets all criteria.'),
(10, 2, 'submitted', 'under_review', 'Review started.'),
(10, 2, 'under_review', 'rejected', 'GPA does not meet 1.80 minimum.');


-- ============================================================
-- COMPLEX QUERY EXAMPLES  (used in reports.php)
-- ============================================================

-- CTE: Monthly application summary with aggregations
/*
WITH monthly_summary AS (
    SELECT
        DATE_FORMAT(submitted_at, '%Y-%m') AS month_year,
        COUNT(*)                           AS total_apps,
        SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) AS rejected,
        AVG(score)                         AS avg_score,
        MAX(score)                         AS top_score,
        MIN(score)                         AS lowest_score
    FROM applications
    GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
)
SELECT * FROM monthly_summary ORDER BY month_year DESC;
*/

-- Subquery 1: Scholarships with more than average number of applications
/*
SELECT s.title, COUNT(a.application_id) AS app_count
FROM scholarships s
JOIN applications a ON s.scholarship_id = a.scholarship_id
GROUP BY s.scholarship_id
HAVING COUNT(a.application_id) > (
    SELECT AVG(app_cnt) FROM (
        SELECT COUNT(*) AS app_cnt FROM applications GROUP BY scholarship_id
    ) sub
);
*/

-- Subquery 2: Applicants who have NOT applied to any scholarship
/*
SELECT * FROM applicants
WHERE applicant_id NOT IN (
    SELECT DISTINCT applicant_id FROM applications
);
*/

-- Subquery 3: Top scorer per scholarship
/*
SELECT s.title, ap.first_name, ap.last_name, a.score
FROM applications a
JOIN applicants ap  ON a.applicant_id   = ap.applicant_id
JOIN scholarships s ON a.scholarship_id = s.scholarship_id
WHERE a.score = (
    SELECT MAX(a2.score)
    FROM applications a2
    WHERE a2.scholarship_id = a.scholarship_id
);
*/
