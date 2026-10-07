-- =====================================================================
-- New Life Fitness Club — Gym Management System
-- Database: newlife_fitness
-- Engine: MySQL (XAMPP / MariaDB compatible)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS newlife_fitness
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE newlife_fitness;

-- ---------------------------------------------------------------------
-- Settings / Club configuration (branded gym name, address, contact)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gym_name VARCHAR(120) NOT NULL DEFAULT 'New Life Fitness Club',
  address VARCHAR(255) DEFAULT '123 Wellness Avenue, Downtown, Cityville',
  contact VARCHAR(60) DEFAULT '+1 (555) 240-8810',
  email VARCHAR(120) DEFAULT 'info@newlifefitness.club',
  logo_path VARCHAR(255) DEFAULT 'assets/images/logo.jpg',
  currency VARCHAR(10) DEFAULT '$'
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Admins (authentication) — password is hashed with PHP password_hash()
-- Default seed login:  username = admin   password = admin123
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Membership Plans
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membership_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plan_name VARCHAR(80) NOT NULL,
  duration_months INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  description VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Trainers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trainers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  specialization VARCHAR(120) NOT NULL,
  contact VARCHAR(60) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  schedule VARCHAR(120) DEFAULT NULL,
  salary DECIMAL(10,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Members
--   username      — unique member portal login name (nullable so existing
--                   members without credentials are not broken; set by admin)
--   password      — bcrypt hash (PHP password_hash). NULL = no login yet.
--   password_set_at — timestamp of last password set/reset
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  contact VARCHAR(60) DEFAULT NULL,
  email VARCHAR(120) DEFAULT NULL,
  username VARCHAR(60) DEFAULT NULL,
  password VARCHAR(255) DEFAULT NULL,
  password_set_at TIMESTAMP NULL DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  dob DATE DEFAULT NULL,
  gender ENUM('Male','Female','Other') DEFAULT 'Male',
  join_date DATE NOT NULL,
  photo VARCHAR(255) DEFAULT NULL,
  plan_id INT DEFAULT NULL,
  trainer_id INT DEFAULT NULL,
  status ENUM('Active','Inactive') DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_members_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL,
  CONSTRAINT fk_members_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL,
  UNIQUE KEY uniq_members_username (username)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- MIGRATION: add username + password columns to an EXISTING members table
-- (safe to run repeatedly — each ADD COLUMN is guarded by a check).
-- This block is a no-op on a fresh install where the columns already exist.
-- ---------------------------------------------------------------------
-- NOTE: MySQL does not support "ADD COLUMN IF NOT EXISTS" on all versions,
-- so the application also runs an idempotent migration in PHP
-- (see includes/member_auth.php :: ensure_member_login_columns()).
-- For manual runs, execute the guarded statements below one by one.

-- ---------------------------------------------------------------------
-- Attendance
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  attend_date DATE NOT NULL,
  status ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
  marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_att_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_member_date (member_id, attend_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Fees / Payments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  plan_id INT DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_date DATE NOT NULL,
  payment_mode ENUM('Cash','Card','Bank Transfer','UPI','Other') DEFAULT 'Cash',
  status ENUM('Paid','Pending','Overdue') DEFAULT 'Paid',
  receipt_no VARCHAR(60) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fees_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  CONSTRAINT fk_fees_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Settings (single branded row)
INSERT INTO settings (gym_name, address, contact, email, logo_path, currency)
VALUES ('New Life Fitness Club',
        '123 Wellness Avenue, Downtown, Cityville',
        '+1 (555) 240-8810',
        'info@newlifefitness.club',
        'assets/images/logo.jpg',
        '$');

-- Admin user — password hash for "admin123" (PHP password_hash bcrypt $2y$)
INSERT INTO admins (username, password, full_name) VALUES
('admin', '$2y$12$vstC85kEdZyG5aZEdhqVLOm0q0WFzyUclBn7LM5Z9e1tMfSKkdh9O', 'System Administrator');

-- Membership plans
INSERT INTO membership_plans (plan_name, duration_months, price, description) VALUES
('Monthly',    1,  40.00, 'Standard monthly membership'),
('Quarterly',  3, 110.00, 'Three months — save 8%'),
('Half-Yearly',6, 210.00, 'Six months — save 12%'),
('Yearly',    12, 400.00, 'Best value — annual membership');

-- Trainers
INSERT INTO trainers (name, specialization, contact, email, schedule, salary) VALUES
('Marcus Reid',     'Strength & Conditioning', '+1 (555) 221-9087', 'marcus@newlifefitness.club', 'Mon-Fri 06:00-12:00', 3200.00),
('Elena Cruz',      'Yoga & Flexibility',      '+1 (555) 332-1456', 'elena@newlifefitness.club',  'Tue-Sat 08:00-14:00', 2800.00),
('Devon Walker',    'CrossFit & HIIT',         '+1 (555) 774-2210', 'devon@newlifefitness.club', 'Mon-Sat 16:00-20:00', 3000.00),
('Aisha Khan',      'Nutrition & Weight Loss', '+1 (555) 998-3345', 'aisha@newlifefitness.club', 'Wed-Sun 10:00-16:00', 2900.00);

-- Members
-- NOTE: username & password are intentionally left NULL for seeded members.
-- An admin must set login credentials per member from the Edit Member page,
-- or set them at creation time from the Add Member page. We never auto-assign
-- insecure default passwords.
INSERT INTO members (name, contact, email, address, dob, gender, join_date, plan_id, trainer_id, status) VALUES
('John Carter',    '+1 (555) 101-2020', 'john.c@email.com',  '21 Maple Street, Cityville', '1992-04-15', 'Male',   '2024-09-01', 2, 1, 'Active'),
('Sara Mitchell',  '+1 (555) 102-3030', 'sara.m@email.com',  '88 Oak Lane, Cityville',     '1995-08-22', 'Female', '2024-10-12', 1, 2, 'Active'),
('Liam O''Connor',  '+1 (555) 103-4040', 'liam.o@email.com',  '5 Pine Road, Cityville',     '1988-12-03', 'Male',   '2024-07-20', 4, 3, 'Active'),
('Nina Patel',     '+1 (555) 104-5050', 'nina.p@email.com',  '12 Cedar Court, Cityville',  '1999-02-18', 'Female', '2025-01-05', 1, 4, 'Active'),
('Robert King',    '+1 (555) 105-6060', 'rob.k@email.com',   '47 Birch Blvd, Cityville',   '1990-06-30', 'Male',   '2024-06-15', 3, 1, 'Inactive'),
('Emily Stone',    '+1 (555) 106-7070', 'emily.s@email.com', '30 Elm Street, Cityville',   '1997-11-11', 'Female', '2025-02-01', 2, 2, 'Active'),
('Carlos Mendez',  '+1 (555) 107-8080', 'carlos.m@email.com','9 Willow Way, Cityville',    '1985-03-25', 'Male',   '2024-08-10', 4, 3, 'Active'),
('Daisy Lin',      '+1 (555) 108-9090', 'daisy.l@email.com', '65 Aspen Drive, Cityville',  '2000-09-09', 'Female', '2025-01-22', 1, 4, 'Active');

-- Attendance (sample recent entries)
INSERT INTO attendance (member_id, attend_date, status) VALUES
(1, CURDATE(), 'Present'),
(2, CURDATE(), 'Present'),
(3, CURDATE(), 'Present'),
(5, CURDATE(), 'Absent'),
(6, CURDATE(), 'Present'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present'),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Absent'),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present'),
(4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present'),
(7, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Present');

-- Fees / payments
INSERT INTO fees (member_id, plan_id, amount, payment_date, payment_mode, status, receipt_no) VALUES
(1, 2, 110.00, '2024-09-01', 'Cash',        'Paid', 'NLF-2024-0001'),
(2, 1,  40.00, '2024-10-12', 'Card',        'Paid', 'NLF-2024-0002'),
(3, 4, 400.00, '2024-07-20', 'Bank Transfer','Paid','NLF-2024-0003'),
(4, 1,  40.00, '2025-01-05', 'UPI',         'Paid', 'NLF-2025-0004'),
(5, 3, 210.00, '2024-06-15', 'Cash',        'Paid', 'NLF-2024-0005'),
(6, 2, 110.00, '2025-02-01', 'Card',        'Paid', 'NLF-2025-0006'),
(7, 4, 400.00, '2024-08-10', 'Cash',        'Paid', 'NLF-2024-0007'),
(8, 1,  40.00, '2025-01-22', 'Cash',        'Pending', 'NLF-2025-0008');

-- =====================================================================
-- OFFER MANAGEMENT & EMAIL CAMPAIGN MODULE
-- (See offers_schema.sql for the standalone migration script.)
-- =====================================================================

-- ---------------------------------------------------------------------
-- Offers — promotional offers created by admins
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS offers (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(160)   NOT NULL,
  description     TEXT           NULL,
  discount        VARCHAR(160)   NOT NULL DEFAULT '',
  poster_path     VARCHAR(255)   NULL,
  start_date      DATE           NOT NULL,
  end_date        DATE           NOT NULL,
  status          ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_by      INT            NULL,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_offers_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_offers_status (status),
  INDEX idx_offers_dates (start_date, end_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Email Campaigns — one row per publish/send action for an offer
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_campaigns (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  offer_id        INT            NOT NULL,
  admin_id        INT            NULL,
  subject         VARCHAR(200)   NOT NULL DEFAULT '🎉 Exclusive Gym Offer Just for You!',
  total_recipients INT           NOT NULL DEFAULT 0,
  sent_count      INT            NOT NULL DEFAULT 0,
  failed_count    INT            NOT NULL DEFAULT 0,
  pending_count   INT            NOT NULL DEFAULT 0,
  status          ENUM('Pending','Processing','Completed','Failed','Scheduled','Cancelled') NOT NULL DEFAULT 'Pending',
  schedule_at     DATETIME       NULL,
  started_at      DATETIME       NULL,
  completed_at    DATETIME       NULL,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_campaigns_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_campaigns_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_campaigns_offer (offer_id),
  INDEX idx_campaigns_status (status),
  INDEX idx_campaigns_schedule (schedule_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Email Logs — one row per recipient per campaign (granular delivery)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_logs (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id     INT            NOT NULL,
  offer_id        INT            NOT NULL,
  member_id       INT            NULL,
  email           VARCHAR(160)   NOT NULL,
  member_name     VARCHAR(160)   NULL,
  status          ENUM('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  error_message   TEXT           NULL,
  opened          TINYINT(1)     NOT NULL DEFAULT 0,
  opened_at       DATETIME       NULL,
  sent_at         DATETIME       NULL,
  attempts        INT            NOT NULL DEFAULT 0,
  created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_logs_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_logs_offer     FOREIGN KEY (offer_id)     REFERENCES offers(id)        ON DELETE CASCADE,
  CONSTRAINT fk_logs_member    FOREIGN KEY (member_id)    REFERENCES members(id)       ON DELETE SET NULL,
  UNIQUE KEY uniq_campaign_member (campaign_id, member_id),
  INDEX idx_logs_status (status),
  INDEX idx_logs_offer (offer_id),
  INDEX idx_logs_campaign (campaign_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Email campaign audit log (tracks every campaign action by an admin)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_campaign_actions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  campaign_id   INT            NULL,
  offer_id      INT            NULL,
  admin_id      INT            NULL,
  action        VARCHAR(40)    NOT NULL,
  detail        VARCHAR(255)   NULL,
  created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_actions_campaign (campaign_id),
  INDEX idx_actions_offer (offer_id)
) ENGINE=InnoDB;

-- =====================================================================
-- END OF SCRIPT
-- =====================================================================
