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
