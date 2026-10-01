-- ============================================================
-- Ummul Bannin Madrasah - COMPLETE Database Rebuild
-- Run this ONCE in phpMyAdmin (SQL tab) to recreate everything.
-- Safe to run even if the database is completely empty.
-- ============================================================

CREATE DATABASE IF NOT EXISTS ummul_bannin_madrasah;
USE ummul_bannin_madrasah;

-- ------------------------------------------------------------
-- 1. CLASSES
-- ------------------------------------------------------------
CREATE TABLE classes (
    class_id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    section VARCHAR(30) NOT NULL,
    term_fee DECIMAL(10,2) NOT NULL
);

INSERT INTO classes (class_name, section, term_fee) VALUES
('Baby Class', 'Nursery', 80000),
('Middle Class', 'Nursery', 80000),
('Top Class', 'Nursery', 80000),
('P.1', 'Lower Primary', 90000),
('P.2', 'Lower Primary', 90000),
('P.3', 'Lower Primary', 90000),
('P.4', 'Lower Primary', 90000),
('P.5', 'Upper Primary', 90000),
('P.6', 'Upper Primary', 90000),
('P.7', 'Upper Primary', 90000);

-- ------------------------------------------------------------
-- 2. ACADEMIC TERMS
-- ------------------------------------------------------------
CREATE TABLE academic_terms (
    term_id INT AUTO_INCREMENT PRIMARY KEY,
    academic_year INT NOT NULL,
    term_number TINYINT NOT NULL,
    is_current BOOLEAN DEFAULT FALSE
);

INSERT INTO academic_terms (academic_year, term_number, is_current) VALUES
(2026, 3, TRUE);

-- ------------------------------------------------------------
-- 3. STUDENTS
-- ------------------------------------------------------------
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female') NOT NULL,
    date_of_birth DATE,
    class_id INT NOT NULL,
    is_boarder BOOLEAN DEFAULT FALSE,
    guardian_name VARCHAR(100),
    guardian_phone VARCHAR(20),
    date_enrolled DATE DEFAULT (CURRENT_DATE),
    status ENUM('Active', 'Graduated', 'Withdrawn') DEFAULT 'Active',
    FOREIGN KEY (class_id) REFERENCES classes(class_id)
);

-- ------------------------------------------------------------
-- 4. BOARDING FEE
-- ------------------------------------------------------------
CREATE TABLE boarding_fee (
    id INT AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(10,2) NOT NULL
);

INSERT INTO boarding_fee (amount) VALUES (350000);

-- ------------------------------------------------------------
-- 5. FEE PAYMENTS
-- ------------------------------------------------------------
CREATE TABLE fee_payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    date_paid DATE DEFAULT (CURRENT_DATE),
    payment_method ENUM('Cash', 'Mobile Money', 'Bank') DEFAULT 'Cash',
    received_by VARCHAR(100),
    receipt_number VARCHAR(30) UNIQUE,
    notes VARCHAR(255),
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (term_id) REFERENCES academic_terms(term_id)
);

-- ------------------------------------------------------------
-- 6. FEE DISCOUNTS / WAIVERS
-- ------------------------------------------------------------
CREATE TABLE fee_discounts (
    discount_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    term_id INT NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255),
    approved_by VARCHAR(100),
    date_given DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (term_id) REFERENCES academic_terms(term_id)
);

-- ------------------------------------------------------------
-- 7. UNIFORM ITEMS
-- ------------------------------------------------------------
CREATE TABLE uniform_items (
    uniform_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(50) NOT NULL,
    applicable_section VARCHAR(30),
    applicable_gender ENUM('Male', 'Female', 'All') DEFAULT 'All',
    price DECIMAL(10,2) NOT NULL,
    quantity_in_stock INT DEFAULT 0
);

INSERT INTO uniform_items (item_name, applicable_section, applicable_gender, price, quantity_in_stock) VALUES
('Shorts', 'Nursery', 'All', 15000, 0),
('Trousers', 'Lower Primary', 'All', 20000, 0),
('Trousers', 'Upper Primary', 'All', 20000, 0),
('Sweater', 'All', 'All', 25000, 0),
('Veil', 'All', 'Female', 10000, 0),
('Cap', 'All', 'Male', 8000, 0);

-- ------------------------------------------------------------
-- 8. UNIFORM ISSUES / SALES
-- ------------------------------------------------------------
CREATE TABLE uniform_issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    uniform_id INT NOT NULL,
    quantity INT DEFAULT 1,
    total_price DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    date_issued DATE DEFAULT (CURRENT_DATE),
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (uniform_id) REFERENCES uniform_items(uniform_id)
);

-- ------------------------------------------------------------
-- 9. USERS (staff logins)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('Admin', 'Accountant', 'Teacher') DEFAULT 'Accountant',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- USEFUL VIEW: student balances at a glance
-- ------------------------------------------------------------
CREATE VIEW v_student_balances AS
SELECT
    s.student_id,
    s.admission_number,
    s.full_name,
    c.class_name,
    (c.term_fee + IF(s.is_boarder, (SELECT amount FROM boarding_fee LIMIT 1), 0)) AS total_due,
    COALESCE((
        SELECT SUM(fp.amount_paid)
        FROM fee_payments fp
        JOIN academic_terms at2 ON fp.term_id = at2.term_id
        WHERE fp.student_id = s.student_id AND at2.is_current = TRUE
    ), 0) AS total_paid,
    (c.term_fee + IF(s.is_boarder, (SELECT amount FROM boarding_fee LIMIT 1), 0))
        - COALESCE((
            SELECT SUM(fp.amount_paid)
            FROM fee_payments fp
            JOIN academic_terms at2 ON fp.term_id = at2.term_id
            WHERE fp.student_id = s.student_id AND at2.is_current = TRUE
        ), 0) AS balance
FROM students s
JOIN classes c ON s.class_id = c.class_id
WHERE s.status = 'Active';
