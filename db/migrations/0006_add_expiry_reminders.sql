-- 0006 — record the expiry reminder sent for a listing.
--
-- An approved listing carries a six-month expiry and the build hides expired
-- rows, so an owner needs warning before the page disappears. One row per
-- listing per expiry date, so a cron run cannot send the same reminder twice,
-- and a reconfirmation (which moves the expiry) makes the listing eligible
-- again next time round.
--
-- Run once, before deploying expiry reminders:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0006_add_expiry_reminders.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

CREATE TABLE IF NOT EXISTS project_expiry_reminders (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    expires_at DATETIME     NOT NULL, -- the expiry this reminder was about
    sent_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expiry_reminder (project_id, expires_at),
    KEY idx_expiry_reminders_sent (sent_at),
    CONSTRAINT fk_expiry_reminder_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT COUNT(*) AS approved_with_expiry
FROM projects
WHERE review_status = 'approved' AND expires_at IS NOT NULL;

SELECT COUNT(*) AS expiring_within_14_days
FROM projects
WHERE review_status = 'approved'
  AND expires_at IS NOT NULL
  AND expires_at > UTC_TIMESTAMP()
  AND expires_at <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 14 DAY);
