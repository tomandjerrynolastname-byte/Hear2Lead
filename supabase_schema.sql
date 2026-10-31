-- ====================================================================
-- Hear2Lead Student Leadership Management System
-- Supabase (PostgreSQL) Database Initialization & Migration Script
-- ====================================================================

-- 1. Helper Functions for MySQL-to-PostgreSQL Compatibility
-- --------------------------------------------------------------------

CREATE OR REPLACE FUNCTION IFNULL(val anyelement, default_val anyelement)
RETURNS anyelement AS $$
BEGIN
    RETURN COALESCE(val, default_val);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION DATEDIFF(d1 anyelement, d2 anyelement)
RETURNS integer AS $$
BEGIN
    RETURN (d1::date - d2::date);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION CURDATE()
RETURNS date AS $$
BEGIN
    RETURN CURRENT_DATE;
END;
$$ LANGUAGE plpgsql STABLE;

CREATE OR REPLACE FUNCTION DATE_FORMAT(ts timestamp with time zone, fmt text)
RETURNS text AS $$
DECLARE
    f text := fmt;
BEGIN
    f := replace(f, '%b', 'Mon');
    f := replace(f, '%M', 'Month');
    f := replace(f, '%d', 'DD');
    f := replace(f, '%Y', 'YYYY');
    f := replace(f, '%y', 'YY');
    f := replace(f, '%h', 'HH12');
    f := replace(f, '%H', 'HH24');
    f := replace(f, '%i', 'MI');
    f := replace(f, '%s', 'SS');
    f := replace(f, '%p', 'AM');
    RETURN to_char(ts, f);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION DATE_FORMAT(ts timestamp without time zone, fmt text)
RETURNS text AS $$
BEGIN
    RETURN DATE_FORMAT(ts::timestamp with time zone, fmt);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION DATE_FORMAT(ts date, fmt text)
RETURNS text AS $$
BEGIN
    RETURN DATE_FORMAT(ts::timestamp with time zone, fmt);
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION CRC32(val text)
RETURNS bigint AS $$
BEGIN
    RETURN ('x' || substr(md5(COALESCE(val, '')), 1, 8))::bit(32)::bigint;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION FIELD(val text, VARIADIC list text[])
RETURNS integer AS $$
DECLARE
    idx integer;
BEGIN
    FOR idx IN 1 .. array_length(list, 1) LOOP
        IF list[idx] = val THEN
            RETURN idx;
        END IF;
    END LOOP;
    RETURN 999;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- 2. Table Definitions
-- --------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS organizations (
    id SERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    acronym VARCHAR(20) NOT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'Student Council',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_roles (
    id SERIAL PRIMARY KEY,
    role_name VARCHAR(100) NOT NULL UNIQUE,
    base_role VARCHAR(50) NOT NULL DEFAULT 'Officer',
    description VARCHAR(255) DEFAULT NULL,
    badge_style VARCHAR(50) NOT NULL DEFAULT 'badge-role-officer',
    can_manage_users SMALLINT NOT NULL DEFAULT 0,
    can_manage_tasks SMALLINT NOT NULL DEFAULT 0,
    can_manage_projects SMALLINT NOT NULL DEFAULT 1,
    can_manage_accomplishments SMALLINT NOT NULL DEFAULT 1,
    can_manage_feedbacks SMALLINT NOT NULL DEFAULT 1,
    can_view_reports SMALLINT NOT NULL DEFAULT 1,
    can_manage_orgs SMALLINT NOT NULL DEFAULT 0,
    is_system SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    org_id INT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(100) NOT NULL DEFAULT 'Officer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
    id SERIAL PRIMARY KEY,
    student_lrn VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    grade_section VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_logs (
    id SERIAL PRIMARY KEY,
    student_id INT REFERENCES students(id) ON DELETE SET NULL,
    student_lrn VARCHAR(50) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    previous_status VARCHAR(30) DEFAULT NULL,
    new_status VARCHAR(30) NOT NULL,
    log_details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id SERIAL PRIMARY KEY,
    org_id INT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'Planning',
    progress_percent INT DEFAULT 0,
    start_date DATE DEFAULT NULL,
    target_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS project_logs (
    id SERIAL PRIMARY KEY,
    project_id INT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    log_details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tasks (
    id SERIAL PRIMARY KEY,
    org_id INT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    project_id INT REFERENCES projects(id) ON DELETE SET NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    status VARCHAR(30) DEFAULT 'To Do',
    priority VARCHAR(20) DEFAULT 'Medium',
    deadline DATE NOT NULL,
    assigned_to TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS task_logs (
    id SERIAL PRIMARY KEY,
    task_id INT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    log_details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS accomplishments (
    id SERIAL PRIMARY KEY,
    org_id INT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    impact_summary VARCHAR(255) DEFAULT NULL,
    date_accomplished DATE NOT NULL,
    badge_tag VARCHAR(50) DEFAULT 'Success',
    attachment_file VARCHAR(255) DEFAULT NULL,
    attachment_type VARCHAR(20) DEFAULT NULL,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachments_json TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS accomplishment_logs (
    id SERIAL PRIMARY KEY,
    acc_id INT NOT NULL REFERENCES accomplishments(id) ON DELETE CASCADE,
    user_id INT DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    log_details TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS feedbacks (
    id SERIAL PRIMARY KEY,
    org_id INT NOT NULL REFERENCES organizations(id) ON DELETE CASCADE,
    student_id INT DEFAULT NULL REFERENCES students(id) ON DELETE SET NULL,
    user_id INT DEFAULT NULL,
    student_name VARCHAR(100) DEFAULT 'Anonymous Student',
    student_email VARCHAR(100) DEFAULT NULL,
    student_grade_section VARCHAR(50) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'Suggestion',
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(30) DEFAULT 'Pending',
    officer_response TEXT DEFAULT NULL,
    responded_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS feedback_logs (
    id SERIAL PRIMARY KEY,
    feedback_id INT NOT NULL REFERENCES feedbacks(id) ON DELETE CASCADE,
    user_id INT DEFAULT NULL,
    officer_name VARCHAR(100) NOT NULL,
    officer_role VARCHAR(50) NOT NULL,
    status VARCHAR(50) NOT NULL,
    reply_message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_logs (
    id SERIAL PRIMARY KEY,
    account_type VARCHAR(20) NOT NULL DEFAULT 'Officer',
    account_id INT DEFAULT NULL,
    org_id INT DEFAULT NULL,
    org_acronym VARCHAR(30) DEFAULT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_role VARCHAR(100) NOT NULL,
    log_details VARCHAR(255) NOT NULL,
    ip_address VARCHAR(60) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Persistent Serverless Sessions Table (for Vercel & Multi-Instance Deployments)
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT NOT NULL,
    last_activity INT NOT NULL
);

-- 3. Initial Seed Data
-- --------------------------------------------------------------------

-- Seed Default System Roles
INSERT INTO user_roles (role_name, base_role, description, badge_style, can_manage_users, can_manage_tasks, can_manage_projects, can_manage_accomplishments, can_manage_feedbacks, can_view_reports, can_manage_orgs, is_system)
VALUES 
('Admin', 'Admin', 'Full system-wide administrator across all student organizations, student accounts, and role library.', 'badge-role-admin', 1, 1, 1, 1, 1, 1, 1, 1),
('President', 'President', 'Chief executive leader of an organization. Can manage organization officers and assign/manage tasks.', 'badge-role-president', 1, 1, 1, 1, 1, 1, 0, 1),
('Vice President', 'Vice President', 'Co-executive leader of an organization. Can manage organization officers and assign/manage tasks.', 'badge-role-vp', 1, 1, 1, 1, 1, 1, 0, 1),
('Secretary', 'Secretary', 'Organization secretary handling documentation, projects, accomplishments, feedbacks, and reports.', 'badge-role-secretary', 0, 0, 1, 1, 1, 1, 0, 1),
('Treasurer', 'Treasurer', 'Organization treasurer handling financial records, projects, accomplishments, feedbacks, and reports.', 'badge-role-treasurer', 0, 0, 1, 1, 1, 1, 0, 1),
('Officer', 'Officer', 'General organization officer with access to assigned tasks, projects, accomplishments, feedbacks, and reports.', 'badge-role-officer', 0, 0, 1, 1, 1, 1, 0, 1)
ON CONFLICT (role_name) DO NOTHING;

-- Seed Student Organizations
INSERT INTO organizations (id, name, acronym, logo, description, category)
VALUES
(1, 'Supreme Secondary Learner Government', 'SSLG', 'logo_1792455662_6ad6b3eed16b4.jpg', 'To protect and promote the right of every learner to quality, equitable, culture-based, and complete basic education.', 'Student Council'),
(2, 'Management Information System', 'MIS', 'logo_1791535811_6ac8aac3a6145.jpg', 'Dedicated to information technology, digital systems, and innovation across student councils.', 'Special Interest'),
(4, 'Supreme Secondary Learner Government - Sub', 'SLLG', 'logo_1792455662_6ad6b3eed16b4.jpg', 'To protect and promote learner development.', 'Student Council'),
(5, 'Technical-Vocational-Livelihood', 'TVL', 'logo_1792560808_6ad84ea899f26.jpg', 'Practical skills and technical craftsmanship student association.', 'Academic')
ON CONFLICT (id) DO NOTHING;

SELECT setval('organizations_id_seq', (SELECT COALESCE(MAX(id), 1) FROM organizations));

-- Seed Users / Officers
-- Note: Default admin credentials: username: admin / password: password (or current hashed password from db)
INSERT INTO users (id, org_id, full_name, username, email, password, role)
VALUES
(3, 2, 'Administrator', 'admin', 'admin@hear2lead.edu.ph', '$2y$10$1Y3eYW5YIuKStNA/ypBpYOgbKpGV9O9cq2Z5tLEybjs.TQNayBpsS', 'Admin'),
(4, 1, 'Maria Santos', 'maria_sslg', 'maria@hear2lead.edu.ph', '$2y$10$fIIr/lCvdZA2l9i6Culi0eRodt5S8t9V11JfBaVZB5CPINN.hdooW', 'President'),
(5, 1, 'Mario Bautista', 'mario_vp', 'mario@hear2lead.edu.ph', '$2y$10$T4Vi7WYLS1poEwuUBtpONOQP35RehVA9Nk8nqsyWd3pXMmnCCTDsm', 'Vice President'),
(6, 1, 'Marie Cruz', 'marie_sec', 'marie@hear2lead.edu.ph', '$2y$10$aP6vzQ5CN9vkTJd1Jl37/O/4X1Dr9TmgKwtjRbClJPZ0i/UKDRrf6', 'Officer'),
(7, 1, 'Juan Dela Cruz', 'juan_treasurer', 'juan@hear2lead.edu.ph', '$2y$10$0xuKDHBHrKQdp8NF3o2k5.6GyEJJKZBsEDjSqVWI71zQw6dnqEW3K', 'Treasurer')
ON CONFLICT (id) DO NOTHING;

SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 1) FROM users));

-- Seed Demo Students (Default password: student123)
INSERT INTO students (id, student_lrn, full_name, grade_section, email, password, status, approved_by, approved_at)
VALUES
(1, '2026-0001', 'Alex Morgan', 'Grade 11 - STEM', 'alex.morgan@student.edu.ph', '$2y$10$q0Ukk9uQQ92vvLmHUBJpI.Ft6y4/8R2nrIbGJqaozXC.sd7FF8a9.', 'Approved', 3, CURRENT_TIMESTAMP),
(2, '2026-0002', 'Samantha Cruz', 'Grade 10 - Rizal', 'samantha.cruz@student.edu.ph', '$2y$10$DJiHb/PBlxM8nEwP7i3jhex99G6Bm9MacJIn4oYxmgklSEUZdwdcq', 'Approved', 3, CURRENT_TIMESTAMP),
(3, '2026-0003', 'Juan Dela Cruz', 'Grade 12 - STEM', 'juan.student@hear2lead.edu.ph', '$2y$10$dUHysNgmk6Q2.NG7U9A11exvwZx7XI6/caGn8eA/tRGQTfs0iPw9m', 'Approved', 3, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

SELECT setval('students_id_seq', (SELECT COALESCE(MAX(id), 1) FROM students));

-- Seed Initial Projects
INSERT INTO projects (id, org_id, title, description, status, progress_percent, start_date, target_date)
VALUES
(1, 1, 'Campus Digital Grievance & Feedback Portal', 'Launching a student feedback channel for student wellness and campus improvement.', 'Ongoing', 75, CURRENT_DATE - 10, CURRENT_DATE + 20),
(2, 1, 'Leadership Development Workshop 2026', 'Empowering junior student leaders with project management skills and ethics.', 'Planning', 40, CURRENT_DATE, CURRENT_DATE + 30)
ON CONFLICT (id) DO NOTHING;

SELECT setval('projects_id_seq', (SELECT COALESCE(MAX(id), 1) FROM projects));

-- Seed Initial Tasks
INSERT INTO tasks (id, org_id, project_id, title, description, status, priority, deadline, assigned_to)
VALUES
(1, 1, 1, 'Finalize Survey Form & Feedback Categories', 'Coordinate with advisers on the final feedback questions.', 'Accomplished', 'High', CURRENT_DATE - 2, 'Maria Santos (President), Marie Cruz (Officer)'),
(2, 1, 1, 'Publish Student Announcements & Guidelines', 'Distribute flyers and post online announcements regarding portal access.', 'In Progress', 'Medium', CURRENT_DATE + 5, 'Mario Bautista (Vice President)'),
(3, 1, 2, 'Venue and Equipment Coordination', 'Coordinate with the school administration for audio-visual equipment reservation.', 'To Do', 'Medium', CURRENT_DATE + 12, 'Juan Dela Cruz (Treasurer)')
ON CONFLICT (id) DO NOTHING;

SELECT setval('tasks_id_seq', (SELECT COALESCE(MAX(id), 1) FROM tasks));

-- --------------------------------------------------------
-- Table: password_reset_tickets (Student & Leadership Reset Requests)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_reset_tickets (
    id SERIAL PRIMARY KEY,
    account_type VARCHAR(20) NOT NULL DEFAULT 'Student',
    account_id INT DEFAULT NULL,
    identifier_submitted VARCHAR(150) NOT NULL,
    user_name VARCHAR(150) NOT NULL,
    user_email VARCHAR(150) DEFAULT NULL,
    account_details VARCHAR(255) DEFAULT NULL,
    request_reason TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending',
    admin_notes TEXT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_by_name VARCHAR(100) DEFAULT NULL,
    resolved_at TIMESTAMP DEFAULT NULL,
    new_temp_password VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(60) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
