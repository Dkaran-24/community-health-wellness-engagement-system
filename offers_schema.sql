-- =====================================================================
-- New Life Fitness Club — Offer Management & Email Campaign
-- Schema migration: adds offers, email_campaigns, email_logs tables.
--
-- Safe to run multiple times (uses CREATE TABLE IF NOT EXISTS).
-- Foreign keys reference existing `members` and `admins` tables.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Offers
--   Each promotional offer created by an admin. A poster/banner image
--   is stored as a relative path under uploads/offers/. Status controls
--   whether the offer is live (Active) or archived (Inactive).
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Email Campaigns
--   One row per "publish" action for an offer. Tracks the overall
--   campaign state: how many recipients, scheduled vs immediate,
--   aggregate counts (sent/failed/pending), and progress.
--   A single offer may have multiple campaigns (re-sends).
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Email Logs
--   One row per recipient per campaign — the granular delivery record.
--   Drives the campaign dashboard, resend-failed, and duplicate-send
--   protection (unique key on campaign + member prevents double sends).
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- END OF OFFER / EMAIL CAMPAIGN SCHEMA
-- =====================================================================
