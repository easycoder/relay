-- 0001 — add the human review gate to listings.
--
-- New submissions start pending; only approved listings are built into the
-- public catalogue. Existing rows predate the gate and are owner-created, so
-- they are approved here.
--
-- Run once, BEFORE deploying the build that requires this column:
--   mysql -h mysql.eclecity.net -u eclecity_relay -p eclecity_relay \
--     < db/migrations/0001_add_review_status.sql

SET NAMES utf8mb4;
SET time_zone = '+00:00';

USE eclecity_relay;

ALTER TABLE projects
    ADD COLUMN review_status VARCHAR(16) NOT NULL DEFAULT 'pending' AFTER status,
    ADD KEY idx_projects_review_status (review_status);

UPDATE projects SET review_status = 'approved';

SELECT review_status, COUNT(*) AS projects
FROM projects
GROUP BY review_status;
