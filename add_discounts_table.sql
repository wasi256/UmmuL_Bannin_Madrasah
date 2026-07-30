-- ============================================================
-- Run this in phpMyAdmin (SQL tab) on the ummul_bannin_madrasah
-- database to add support for fee discounts/waivers.
-- ============================================================

USE ummul_bannin_madrasah;

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
