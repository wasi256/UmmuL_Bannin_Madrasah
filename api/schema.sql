-- PostgreSQL Schema for Ummul Bannin Madrasah
-- This schema is automatically created by api/db.js on first run
-- Use this for reference or manual setup

CREATE TABLE IF NOT EXISTS classes (
  class_id SERIAL PRIMARY KEY,
  class_name VARCHAR(50) NOT NULL,
  section VARCHAR(30) NOT NULL,
  term_fee DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS academic_terms (
  term_id SERIAL PRIMARY KEY,
  academic_year INT NOT NULL,
  term_number SMALLINT NOT NULL,
  is_current BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS students (
  student_id SERIAL PRIMARY KEY,
  admission_number VARCHAR(20) UNIQUE NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  gender VARCHAR(10) NOT NULL CHECK (gender IN ('Male', 'Female')),
  date_of_birth DATE,
  class_id INT NOT NULL REFERENCES classes(class_id),
  is_boarder BOOLEAN DEFAULT FALSE,
  guardian_name VARCHAR(100),
  guardian_phone VARCHAR(20),
  date_enrolled DATE DEFAULT CURRENT_DATE,
  status VARCHAR(20) DEFAULT 'Active' CHECK (status IN ('Active', 'Graduated', 'Withdrawn'))
);

CREATE TABLE IF NOT EXISTS boarding_fee (
  id SERIAL PRIMARY KEY,
  amount DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS fee_payments (
  payment_id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(student_id),
  term_id INT NOT NULL REFERENCES academic_terms(term_id),
  amount_paid DECIMAL(10,2) NOT NULL,
  date_paid DATE DEFAULT CURRENT_DATE,
  payment_method VARCHAR(20) DEFAULT 'Cash' CHECK (payment_method IN ('Cash', 'Mobile Money', 'Bank')),
  received_by VARCHAR(100),
  receipt_number VARCHAR(30) UNIQUE,
  notes VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS fee_discounts (
  discount_id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(student_id),
  term_id INT NOT NULL REFERENCES academic_terms(term_id),
  discount_amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(255),
  approved_by VARCHAR(100),
  date_given DATE DEFAULT CURRENT_DATE
);

CREATE TABLE IF NOT EXISTS uniform_items (
  uniform_id SERIAL PRIMARY KEY,
  item_name VARCHAR(50) NOT NULL,
  applicable_section VARCHAR(30),
  applicable_gender VARCHAR(10) DEFAULT 'All' CHECK (applicable_gender IN ('Male', 'Female', 'All')),
  price DECIMAL(10,2) NOT NULL,
  quantity_in_stock INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS uniform_issues (
  issue_id SERIAL PRIMARY KEY,
  student_id INT NOT NULL REFERENCES students(student_id),
  uniform_id INT NOT NULL REFERENCES uniform_items(uniform_id),
  quantity INT DEFAULT 1,
  total_price DECIMAL(10,2) NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL,
  date_issued DATE DEFAULT CURRENT_DATE
);

CREATE TABLE IF NOT EXISTS users (
  user_id SERIAL PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100),
  role VARCHAR(20) DEFAULT 'Accountant' CHECK (role IN ('Admin', 'Accountant', 'Teacher')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Session table for connect-pg-simple
CREATE TABLE IF NOT EXISTS "session" (
  "sid" varchar NOT NULL COLLATE "default",
  "sess" json NOT NULL,
  "expire" timestamp(6) NOT NULL
);
CREATE INDEX IF NOT EXISTS "IDX_session_expire" ON "session" ("expire");
