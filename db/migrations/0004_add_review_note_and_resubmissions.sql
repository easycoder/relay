-- 0004 — the reviewer's reason, and a record of each resubmission.
--
-- A rejected listing keeps its reason on the row, so the owner can see what to
-- fix without guessing. Each resubmission is also recorded, so the retry rate
-- can be limited: five per listing in any thirty days.
--
-- Run once, before deploying the reason and the retry limit:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0004_add_review_note_and_resubmissions.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE projects
    ADD COLUMN review_note VARCHAR(500) NULL AFTER review_status;

CREATE TABLE IF NOT EXISTS project_resubmissions (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resubmissions_project_created (project_id, created_at),
    KEY idx_resubmissions_user (user_id),
    CONSTRAINT fk_resubmissions_project
        FOREIGN KEY (project_id) REFERENCES projects (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resubmissions_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT review_status, COUNT(*) AS listings
FROM projects
GROUP BY review_status;
