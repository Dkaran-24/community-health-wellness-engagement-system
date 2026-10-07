-- =====================================================================
-- NEW LIFE FITNESS — COMMUNITY ENGAGEMENT PROJECT (CEP)
-- MASTER INSTALLER — import this ONE file into the CEP database
-- ---------------------------------------------------------------------
-- HOW TO USE (on the CEP hosting ONLY — e.g. the SEPARATE InfinityFree
-- account created for the college CEP):
--   1. Create a MySQL database + user in the CEP hosting control panel
--      (e.g.  if0_XXXXXXXX_newlife_cep )
--   2. Open phpMyAdmin and SELECT that CEP database in the left sidebar
--   3. Import this file (Import tab -> Choose File -> cep_install.sql)
--
-- ⚠ CEP ISOLATION RULES:
--   * This file is for the CEP database ONLY.
--   * NEVER import it into the personal project's production database.
--   * The personal project keeps using its own database.sql, untouched.
--
-- CONTENTS:
--   PART 1 — Original gym tables (settings, admins, membership_plans,
--            trainers, members, attendance, fees, offers, email_*)
--            with demo seed rows, exactly as the personal project uses.
--   PART 2 — CEP community tables (19 new tables) with relationships.
--            Also trainer_payments + member progress tables (the app
--            auto-creates the progress tables at runtime, but they are
--            included here so ONE import is always sufficient).
-- =====================================================================

/* ===================== PART 1 — GYM BASE ===================== */

-- =====================================================================
-- New Life Fitness Club — Gym Management System
-- Database: newlife_fitness
-- Engine: MySQL (XAMPP / MariaDB compatible)
-- =====================================================================

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
-- TRAINER PAYMENTS MODULE  (from admin/trainer-payments/schema.sql)
-- Note: the app's Trainer Payment history page needs this table. It is
-- included here so importing ONE file is enough.
-- =====================================================================

CREATE TABLE IF NOT EXISTS trainer_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_date DATE NOT NULL,
  pay_period VARCHAR(40) DEFAULT NULL,
  payment_mode ENUM('Cash','Bank Transfer','UPI','Cheque','Other') DEFAULT 'Cash',
  reference_no VARCHAR(80) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trpay_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  INDEX idx_trpay_trainer (trainer_id),
  INDEX idx_trpay_date (payment_date)
) ENGINE=InnoDB;

-- Demo salary payment history (DEMO DATA, same as the rest of Part 1).
INSERT INTO trainer_payments (trainer_id, amount, payment_date, pay_period, payment_mode, reference_no, notes) VALUES
(1, 18000.00, '2025-01-01', 'January 2025',   'Bank Transfer', 'TRP-2025-0001', 'Monthly salary'),
(1, 18000.00, '2025-02-01', 'February 2025',  'Bank Transfer', 'TRP-2025-0002', 'Monthly salary'),
(2, 15000.00, '2025-01-04', 'January 2025',   'UPI',           'TRP-2025-0003', 'Monthly salary'),
(3, 16000.00, '2025-01-05', 'January 2025',   'Cash',           'TRP-2025-0004', 'Monthly salary'),
(4, 14000.00, '2025-02-03', 'February 2025',  'Bank Transfer', 'TRP-2025-0005', 'Monthly salary');

-- =====================================================================
-- MEMBER PROGRESS MODULE tables (mirrors includes/progress.php
-- ensure_progress_tables() auto-migration, listed here so a fresh import
-- is complete on hosts where runtime migration may be restricted).
-- =====================================================================

CREATE TABLE IF NOT EXISTS member_weight_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  member_id   INT NOT NULL,
  weight      DECIMAL(6,2) NOT NULL,
  unit        ENUM('kg','lb') NOT NULL DEFAULT 'kg',
  logged_date DATE NOT NULL,
  notes       VARCHAR(255) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wlog_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_wlog_member_date (member_id, logged_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_measurements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  member_id   INT NOT NULL,
  chest       DECIMAL(6,2) DEFAULT NULL,
  waist       DECIMAL(6,2) DEFAULT NULL,
  hips        DECIMAL(6,2) DEFAULT NULL,
  arm         DECIMAL(6,2) DEFAULT NULL,
  thigh       DECIMAL(6,2) DEFAULT NULL,
  shoulder    DECIMAL(6,2) DEFAULT NULL,
  height      DECIMAL(6,2) DEFAULT NULL,
  height_unit ENUM('cm','in') NOT NULL DEFAULT 'cm',
  unit        ENUM('cm','in') NOT NULL DEFAULT 'cm',
  logged_date DATE NOT NULL,
  notes       VARCHAR(255) DEFAULT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_meas_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_meas_member_date (member_id, logged_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_goals (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  member_id     INT NOT NULL,
  goal_type     ENUM('weight_loss','weight_gain','bmi_target','attendance','workout_days','custom') NOT NULL DEFAULT 'custom',
  title         VARCHAR(120) NOT NULL,
  target_value  DECIMAL(8,2) DEFAULT NULL,
  start_value   DECIMAL(8,2) DEFAULT NULL,
  current_value DECIMAL(8,2) DEFAULT NULL,
  unit          VARCHAR(20) DEFAULT NULL,
  target_date   DATE DEFAULT NULL,
  status        ENUM('active','achieved','abandoned') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_goal_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_goal_member (member_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_workouts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  member_id     INT NOT NULL,
  workout_date  DATE NOT NULL,
  workout_type  VARCHAR(60) DEFAULT NULL,
  duration_min  INT DEFAULT NULL,
  calories_burn INT DEFAULT NULL,
  sets          INT DEFAULT NULL,
  reps          INT DEFAULT NULL,
  weight_lifted DECIMAL(8,2) DEFAULT NULL,
  intensity     ENUM('Low','Medium','High') DEFAULT 'Medium',
  notes         VARCHAR(255) DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wo_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_wo_member_date (member_id, workout_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS member_milestones (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  member_id     INT NOT NULL,
  title         VARCHAR(120) NOT NULL,
  description   VARCHAR(255) DEFAULT NULL,
  category      ENUM('weight','strength','endurance','attendance','nutrition','other') NOT NULL DEFAULT 'other',
  achieved_date DATE NOT NULL,
  icon          VARCHAR(40) DEFAULT NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ms_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
  INDEX idx_ms_member_date (member_id, achieved_date)
) ENGINE=InnoDB;

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

/* ===================== PART 2 — CEP COMMUNITY ================= */

-- =====================================================================
-- NEW LIFE FITNESS — COMMUNITY ENGAGEMENT PROJECT (CEP) EXTENSION SCHEMA
-- ---------------------------------------------------------------------
-- This script ADDS the community tables to the CEP database only.
-- It does NOT touch any existing gym tables (settings, admins, trainers,
-- members, membership_plans, attendance, fees, offers, email_* ...).
--
-- IMPORTANT (CEP isolation rules):
--   * Run this ONLY on the CEP database (e.g. if0_XXXXXXXX_newlife_cep).
--   * NEVER run it on the personal production database.
--   * The CEP database is created from scratch from database.sql + this file.
--
-- Design notes:
--   * Community users get their own table — a community participant does
--     NOT need a gym membership (complete independence from gym members).
--   * Every table has a PRIMARY KEY, relevant FOREIGN KEYS, timestamps,
--     status fields and UNIQUE constraints where duplication is illegal.
--   * Existing gym tables are never altered or duplicated.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) COMMUNITY USERS — local residents with a free community account.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_users (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  full_name            VARCHAR(120) NOT NULL,
  email                VARCHAR(160) NOT NULL,
  mobile               VARCHAR(20)  NOT NULL,
  age                  INT          NOT NULL,
  gender               ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other',
  address_area         VARCHAR(255) NOT NULL,
  password             VARCHAR(255) NOT NULL,
  fitness_level        ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
  fitness_goal         ENUM('General Fitness','Weight Loss','Strength','Flexibility','Endurance','Wellness') NOT NULL DEFAULT 'General Fitness',
  preferred_activities VARCHAR(120) DEFAULT 'Any',
  status               ENUM('Active','Blocked') NOT NULL DEFAULT 'Active',
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cuser_email (email),
  UNIQUE KEY uniq_cuser_mobile (mobile),
  INDEX idx_cuser_goal (fitness_goal),
  INDEX idx_cuser_level (fitness_level)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2) COMMUNITY EVENTS — community activities run for local residents.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_events (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  event_name       VARCHAR(160) NOT NULL,
  description      TEXT,
  category         ENUM('Fitness Camp','Yoga','Zumba','Walking','Running','Health Awareness','Nutrition Workshop','Wellness','Senior Fitness','Women Wellness','Community Challenge','Other') NOT NULL DEFAULT 'Other',
  event_date       DATE NOT NULL,
  start_time       TIME NOT NULL DEFAULT '06:00:00',
  end_time         TIME NOT NULL DEFAULT '08:00:00',
  location         VARCHAR(200) NOT NULL,
  organizer        VARCHAR(120) NOT NULL,
  trainer_id       INT NULL,
  max_participants INT NOT NULL DEFAULT 50,
  reg_deadline     DATE NULL,
  status           ENUM('Upcoming','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  image            VARCHAR(255) DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL,
  INDEX idx_event_date (event_date),
  INDEX idx_event_cat (category)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) EVENT REGISTRATIONS — one row per (user, event); duplicates blocked.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_registrations (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  community_user_id INT NOT NULL,
  event_id          INT NOT NULL,
  reg_date          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status            ENUM('Registered','Attended','Missed','Cancelled') NOT NULL DEFAULT 'Registered',
  UNIQUE KEY uniq_user_event (community_user_id, event_id),
  CONSTRAINT fk_reg_user  FOREIGN KEY (community_user_id) REFERENCES community_users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id)          REFERENCES community_events(id)  ON DELETE CASCADE,
  INDEX idx_reg_event (event_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4) EVENT ATTENDANCE — community event attendance
--    (SEPARATE from the existing gym `attendance` table).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_attendance (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  event_id          INT NOT NULL,
  community_user_id INT NOT NULL,
  status            ENUM('Present','Absent') NOT NULL DEFAULT 'Present',
  marked_by         INT NULL,
  marked_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_eatt_event_user (event_id, community_user_id),
  CONSTRAINT fk_eatt_event FOREIGN KEY (event_id)          REFERENCES community_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_eatt_user  FOREIGN KEY (community_user_id) REFERENCES community_users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_eatt_admin FOREIGN KEY (marked_by)         REFERENCES admins(id)           ON DELETE SET NULL,
  INDEX idx_eatt_event (event_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5) VOLUNTEER APPLICATIONS — apply to become a volunteer.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS volunteer_applications (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  community_user_id  INT NOT NULL,
  skills             VARCHAR(255) NOT NULL,
  interest_areas     VARCHAR(255) NOT NULL,
  availability       VARCHAR(120) NOT NULL,
  previous_experience VARCHAR(255) DEFAULT NULL,
  reason             TEXT,
  status             ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  reviewed_by        INT NULL,
  reviewed_at        DATETIME NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vapp_user  FOREIGN KEY (community_user_id) REFERENCES community_users(id) ON DELETE CASCADE,
  CONSTRAINT fk_vapp_admin FOREIGN KEY (reviewed_by)       REFERENCES admins(id)          ON DELETE SET NULL,
  UNIQUE KEY uniq_vapp_user (community_user_id),
  INDEX idx_vapp_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6) VOLUNTEERS — approved volunteers (one row per approved user).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS volunteers (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  community_user_id INT NOT NULL,
  application_id    INT NULL,
  status            ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  total_hours       DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_vol_user (community_user_id),
  CONSTRAINT fk_vol_user FOREIGN KEY (community_user_id)  REFERENCES community_users(id)          ON DELETE CASCADE,
  CONSTRAINT fk_vol_app  FOREIGN KEY (application_id)     REFERENCES volunteer_applications(id)  ON DELETE SET NULL,
  INDEX idx_vol_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7) VOLUNTEER EVENT ASSIGNMENTS — admin assigns volunteer to event.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS volunteer_event_assignments (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  volunteer_id INT NOT NULL,
  event_id     INT NOT NULL,
  role         VARCHAR(80) NOT NULL DEFAULT 'General Support',
  assigned_by  INT NULL,
  assigned_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status       ENUM('Assigned','In Progress','Completed','Withdrawn') NOT NULL DEFAULT 'Assigned',
  CONSTRAINT fk_vea_vol   FOREIGN KEY (volunteer_id) REFERENCES volunteers(id)       ON DELETE CASCADE,
  CONSTRAINT fk_vea_event FOREIGN KEY (event_id)     REFERENCES community_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_vea_admin FOREIGN KEY (assigned_by)  REFERENCES admins(id)           ON DELETE SET NULL,
  UNIQUE KEY uniq_vea (volunteer_id, event_id),
  INDEX idx_vea_event (event_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8) VOLUNTEER HOURS — time contributed per event.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS volunteer_hours (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  volunteer_id INT NOT NULL,
  event_id     INT NOT NULL,
  work_date    DATE NOT NULL,
  role         VARCHAR(80) NOT NULL DEFAULT 'General Support',
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  total_hours  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  status       ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vh_vol   FOREIGN KEY (volunteer_id) REFERENCES volunteers(id)       ON DELETE CASCADE,
  CONSTRAINT fk_vh_event FOREIGN KEY (event_id)     REFERENCES community_events(id) ON DELETE CASCADE,
  INDEX idx_vh_vol (volunteer_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9) COMMUNITY FEEDBACK — rating + comments after an event.
--    sentiment + topics are filled by the AI feedback analysis module.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_feedback (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  event_id          INT NOT NULL,
  community_user_id INT NOT NULL,
  rating            TINYINT NOT NULL,
  satisfaction      ENUM('Very Satisfied','Satisfied','Neutral','Dissatisfied','Very Dissatisfied') NOT NULL DEFAULT 'Satisfied',
  comments          TEXT,
  suggestions       TEXT,
  would_attend_again ENUM('Yes','No') NOT NULL DEFAULT 'Yes',
  sentiment         ENUM('Positive','Neutral','Negative') DEFAULT NULL,
  topics            VARCHAR(255) DEFAULT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fb_event FOREIGN KEY (event_id)          REFERENCES community_events(id) ON DELETE CASCADE,
  CONSTRAINT fk_fb_user  FOREIGN KEY (community_user_id) REFERENCES community_users(id)  ON DELETE CASCADE,
  UNIQUE KEY uniq_fb_user_event (community_user_id, event_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10) SURVEYS & POLLS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_surveys (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(160) NOT NULL,
  description TEXT,
  type        ENUM('Survey','Poll') NOT NULL DEFAULT 'Poll',
  status      ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
  created_by  INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at   DATETIME NULL,
  CONSTRAINT fk_survey_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_survey_status (status)
) ENGINE=InnoDB;

-- 10a) survey questions (multi-question surveys supported)
CREATE TABLE IF NOT EXISTS survey_questions (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  survey_id     INT NOT NULL,
  question_text VARCHAR(255) NOT NULL,
  question_type ENUM('single','multi','text') NOT NULL DEFAULT 'single',
  options       TEXT NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sq_survey FOREIGN KEY (survey_id) REFERENCES community_surveys(id) ON DELETE CASCADE,
  INDEX idx_sq_survey (survey_id)
) ENGINE=InnoDB;

-- 10b) survey responses — one row per user per question
CREATE TABLE IF NOT EXISTS survey_responses (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  survey_id         INT NOT NULL,
  question_id       INT NOT NULL,
  community_user_id INT NOT NULL,
  option_text       VARCHAR(160) DEFAULT NULL,
  response_text     TEXT,
  submitted_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sr_survey   FOREIGN KEY (survey_id)        REFERENCES community_surveys(id)  ON DELETE CASCADE,
  CONSTRAINT fk_sr_question FOREIGN KEY (question_id)      REFERENCES survey_questions(id)  ON DELETE CASCADE,
  CONSTRAINT fk_sr_user     FOREIGN KEY (community_user_id) REFERENCES community_users(id)  ON DELETE CASCADE,
  UNIQUE KEY uniq_sr_user_question (community_user_id, question_id),
  INDEX idx_sr_question (question_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11) COMMUNITY REQUESTS — residents request new activities.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_requests (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  community_user_id  INT NOT NULL,
  request_type       ENUM('Yoga Sessions','Fitness Camp','Health Camp','Nutrition Workshop','Senior Fitness Program','Women Wellness Program','Other') NOT NULL DEFAULT 'Other',
  title              VARCHAR(160) NOT NULL,
  description        TEXT,
  status             ENUM('Submitted','Under Review','Approved','Scheduled','Completed','Rejected') NOT NULL DEFAULT 'Submitted',
  admin_note         VARCHAR(255) DEFAULT NULL,
  converted_event_id INT NULL,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_req_user  FOREIGN KEY (community_user_id) REFERENCES community_users(id)  ON DELETE CASCADE,
  CONSTRAINT fk_req_event FOREIGN KEY (converted_event_id) REFERENCES community_events(id) ON DELETE SET NULL,
  INDEX idx_req_status (status),
  INDEX idx_req_type (request_type)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12) WELLNESS RESOURCES — educational content (admin managed).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS wellness_resources (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(160) NOT NULL,
  category    ENUM('Exercise Guide','Nutrition','Healthy Lifestyle','Fitness Education','Exercise Safety','Wellness Awareness','Beginner Fitness','Other') NOT NULL DEFAULT 'Other',
  summary     VARCHAR(255) NOT NULL,
  content     MEDIUMTEXT,
  image       VARCHAR(255) DEFAULT NULL,
  status      ENUM('Published','Draft') NOT NULL DEFAULT 'Published',
  views       INT NOT NULL DEFAULT 0,
  created_by  INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_res_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_res_cat (category)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13) COMMUNITY ANNOUNCEMENTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_announcements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(160) NOT NULL,
  message     TEXT NOT NULL,
  type        ENUM('Event','Health Camp','Challenge','Workshop','Volunteer Opportunity','Notice','Other') NOT NULL DEFAULT 'Notice',
  status      ENUM('Active','Archived') NOT NULL DEFAULT 'Active',
  created_by  INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ann_admin FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_ann_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 14) FITNESS CHALLENGES
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS fitness_challenges (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  challenge_name VARCHAR(160) NOT NULL,
  description   TEXT,
  category      ENUM('Walking','Running','Yoga','General Fitness','Steps','Other') NOT NULL DEFAULT 'General Fitness',
  target_value  INT NOT NULL DEFAULT 30,           -- e.g. 30 days, 10000 steps
  target_unit   ENUM('days','steps','sessions','km') NOT NULL DEFAULT 'days',
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  status        ENUM('Upcoming','Active','Completed') NOT NULL DEFAULT 'Active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chal_status (status)
) ENGINE=InnoDB;

-- 14a) challenge participants
CREATE TABLE IF NOT EXISTS challenge_participants (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  challenge_id      INT NOT NULL,
  community_user_id INT NOT NULL,
  joined_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  current_value     INT NOT NULL DEFAULT 0,
  completed         TINYINT(1) NOT NULL DEFAULT 0,
  completed_at      DATETIME NULL,
  CONSTRAINT fk_cp_chal FOREIGN KEY (challenge_id)      REFERENCES fitness_challenges(id)  ON DELETE CASCADE,
  CONSTRAINT fk_cp_user FOREIGN KEY (community_user_id) REFERENCES community_users(id)     ON DELETE CASCADE,
  UNIQUE KEY uniq_cp_user_chal (community_user_id, challenge_id),
  INDEX idx_cp_chal (challenge_id)
) ENGINE=InnoDB;

-- 14b) challenge progress log (daily entries)
CREATE TABLE IF NOT EXISTS challenge_progress (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  participant_id    INT NOT NULL,
  progress_date     DATE NOT NULL,
  value_logged      INT NOT NULL DEFAULT 1,
  notes             VARCHAR(255) DEFAULT NULL,
  logged_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_prog_part FOREIGN KEY (participant_id) REFERENCES challenge_participants(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_prog_date (participant_id, progress_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 15) AI RECOMMENDATIONS — output of the recommendation engine.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_recommendations (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  community_user_id INT NOT NULL,
  event_id          INT NULL,
  challenge_id      INT NULL,
  reason            VARCHAR(255) DEFAULT NULL,
  score             DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  engine_version    VARCHAR(20)  NOT NULL DEFAULT 'rule-v1',
  shown_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ai_user    FOREIGN KEY (community_user_id) REFERENCES community_users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_ai_event   FOREIGN KEY (event_id)          REFERENCES community_events(id)    ON DELETE CASCADE,
  CONSTRAINT fk_ai_chal    FOREIGN KEY (challenge_id)      REFERENCES fitness_challenges(id) ON DELETE CASCADE,
  INDEX idx_ai_user (community_user_id)
) ENGINE=InnoDB;

-- =====================================================================
-- END OF CEP EXTENSION SCHEMA — 16 tables added:
--   community_users, community_events, event_registrations,
--   event_attendance, volunteer_applications, volunteers,
--   volunteer_event_assignments, volunteer_hours, community_feedback,
--   community_surveys, survey_questions, survey_responses,
--   community_requests, wellness_resources, community_announcements,
--   fitness_challenges (+challenge_participants, challenge_progress),
--   ai_recommendations
-- =====================================================================
