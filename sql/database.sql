-- ============================================================
--  University Admission Portal — Database Setup
--  Run this file once to create the database and seed data
-- ============================================================

CREATE DATABASE IF NOT EXISTS uni_admission
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE uni_admission;

-- ── GST Roll Table (filled by admin before admission opens) ──
CREATE TABLE IF NOT EXISTS gst_rolls (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    gst_roll    VARCHAR(20)  NOT NULL UNIQUE,
    merit       INT          NOT NULL,
    dept        VARCHAR(100) NOT NULL,
    marked      TINYINT(1)   NOT NULL DEFAULT 0,   -- 0 = unused, 1 = already registered
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── Registered Students Table ──
CREATE TABLE IF NOT EXISTS students (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    gst_roll    VARCHAR(20)  NOT NULL UNIQUE,
    email       VARCHAR(150)          DEFAULT NULL,
    phone       VARCHAR(15)           DEFAULT NULL,
    password    VARCHAR(255) NOT NULL,             -- stored as bcrypt hash
    merit       INT          NOT NULL,
    dept        VARCHAR(100) NOT NULL,
    reg_number  VARCHAR(30)           DEFAULT NULL UNIQUE,  -- assigned by Register Office
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_contact CHECK (email IS NOT NULL OR phone IS NOT NULL)
);

-- ── Admission Progress Master Steps ──
CREATE TABLE IF NOT EXISTS admission_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    step_order INT NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

INSERT IGNORE INTO admission_steps (step_order, title) VALUES
(1,'Form Submission'),
(2,'Department Viva'),
(3,'Bank Payment'),
(4,'Dean''s Office'),
(5,'Register Office'),
(6,'IT / ID Card'),
(7,'Exam Controller');

-- ── Per-student step status ──
CREATE TABLE IF NOT EXISTS student_step_status (
    student_id INT NOT NULL,
    step_id INT NOT NULL,
    status ENUM('waiting','pending','done') NOT NULL DEFAULT 'waiting',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     INT          NULL,
    transaction_id VARCHAR(100) NULL,
    PRIMARY KEY (student_id, step_id),
    CONSTRAINT fk_sss_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_sss_step FOREIGN KEY (step_id) REFERENCES admission_steps(id) ON DELETE CASCADE
);

-- Create default step rows for any existing students (safe to run multiple times)
INSERT IGNORE INTO student_step_status (student_id, step_id, status)
SELECT s.id, st.id, 'waiting'
FROM students s
CROSS JOIN admission_steps st;

-- ── Admins table (for updating admission progress) ──
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: username=admin, password=admin123
INSERT IGNORE INTO admins (username, password, name) VALUES
('admin', '$2b$12$.Aw//W5fyt.wGB8LboMGKurAxFGYUBKIyaoSJ0GXyaEs5zKB1XQny', 'Admin');

-- ── Seed Demo GST Rolls ──
INSERT IGNORE INTO gst_rolls (gst_roll, merit, dept) VALUES
('GST-2025-10001', 1,  'Computer Science & Engineering'),
('GST-2025-10002', 2,  'Electrical & Electronic Engineering'),
('GST-2025-10003', 3,  'Business Administration'),
('GST-2025-10004', 4,  'Economics'),
('GST-2025-10005', 5,  'Physics'),
('GST-2025-10006', 6,  'Mathematics'),
('GST-2025-10007', 7,  'Chemistry'),
('GST-2025-10008', 8,  'English'),
('GST-2025-99999', 1,  'Computer Science & Engineering'),
('GST-2025-88888', 2,  'Business Administration');

-- ── Deans table ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS deans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    name       VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Default dean: username=dean, password=dean123
INSERT IGNORE INTO deans (username, password, name) VALUES
('dean', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Dean''s Office');

-- ── Register Office table ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS register_office (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    name       VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Default: username=register / password=register123
INSERT IGNORE INTO register_office (username, password, name) VALUES
('register', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Register Office');

-- ── Exam Controller table ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_controller (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(60)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    name       VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Default: username=exam / password=exam123
INSERT IGNORE INTO exam_controller (username, password, name) VALUES
('exam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Exam Controller');

-- ── Payments table ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    tran_id      VARCHAR(60) NOT NULL UNIQUE,
    amount       DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
    method       VARCHAR(30) DEFAULT NULL,
    status       ENUM('pending','verified','failed') DEFAULT 'pending',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pay_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ── Admission Forms table ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS admission_forms (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    student_id           INT NOT NULL UNIQUE,
    full_name            VARCHAR(150) DEFAULT NULL,
    father_name          VARCHAR(150) DEFAULT NULL,
    mother_name          VARCHAR(150) DEFAULT NULL,
    dob                  DATE DEFAULT NULL,
    gender               VARCHAR(20) DEFAULT NULL,
    blood_group          VARCHAR(10) DEFAULT NULL,
    nationality          VARCHAR(50) DEFAULT NULL,
    religion             VARCHAR(50) DEFAULT NULL,
    nid                  VARCHAR(30) DEFAULT NULL,
    present_address      TEXT DEFAULT NULL,
    permanent_address    TEXT DEFAULT NULL,
    district             VARCHAR(100) DEFAULT NULL,
    division             VARCHAR(100) DEFAULT NULL,
    postal_code          VARCHAR(20) DEFAULT NULL,
    ssc_roll             VARCHAR(30) DEFAULT NULL,
    ssc_reg              VARCHAR(30) DEFAULT NULL,
    ssc_year             INT DEFAULT NULL,
    hsc_roll             VARCHAR(30) DEFAULT NULL,
    hsc_reg              VARCHAR(30) DEFAULT NULL,
    hsc_year             INT DEFAULT NULL,
    quota                VARCHAR(50) DEFAULT NULL,
    guardian_name        VARCHAR(150) DEFAULT NULL,
    guardian_relation    VARCHAR(50) DEFAULT NULL,
    guardian_phone       VARCHAR(20) DEFAULT NULL,
    guardian_occupation  VARCHAR(100) DEFAULT NULL,
    annual_income        VARCHAR(50) DEFAULT NULL,
    photo                VARCHAR(255) DEFAULT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_af_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- ── Teachers table ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS teachers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample teachers (password = teacher123)
INSERT IGNORE INTO teachers (name, email, password, department) VALUES
('CSE Teacher',  'cse@uob.edu.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Computer Science & Engineering'),
('EEE Teacher',  'eee@uob.edu.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Electrical & Electronic Engineering'),
('BBA Teacher',  'bba@uob.edu.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Business Administration'),
('Eco Teacher',  'eco@uob.edu.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Economics'),
('Phy Teacher',  'phy@uob.edu.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Physics'),
('Math Teacher', 'math@uob.edu.bd',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Mathematics'),
('Chem Teacher', 'chem@uob.edu.bd',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'Chemistry'),
('Eng Teacher',  'eng@uob.edu.bd',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uZutCouyO', 'English');
